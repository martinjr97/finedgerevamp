<?php

namespace App\Sms\Jobs;

use App\Models\SmsMessage as SmsMessageModel;
use App\Sms\Support\SmsGatewayManager;
use App\Sms\Support\SmsPhoneNormalizer;
use App\Support\Queue\ApplicationQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 90;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public readonly int $smsMessageId,
        public readonly string $sendBody,
    ) {
        $this->onConnection(ApplicationQueue::connection());
        $this->onQueue((string) config('sms.queues.sms', ApplicationQueue::notifications()));
    }

    public function handle(SmsGatewayManager $gatewayManager, SmsPhoneNormalizer $phoneNormalizer): void
    {
        $record = SmsMessageModel::query()->find($this->smsMessageId);

        if (! $record || $record->status !== 'queued') {
            return;
        }

        if (! (bool) config('sms.enabled', false)) {
            $this->markSkipped($record, 'disabled');

            return;
        }

        $provider = $gatewayManager->resolve((string) $record->provider);

        $dto = new \App\Sms\DTOs\SmsMessage(
            phone: (string) $record->phone_number,
            body: $this->sendBody,
            category: $record->message_category,
            messageType: (string) $record->message_type,
            recipientType: $record->recipient_type,
            recipientId: $record->recipient_id,
            customerId: $record->customer_id,
            adminId: $record->admin_id,
            loanId: $record->loan_id,
            metadata: is_array($record->metadata) ? $record->metadata : [],
        );

        try {
            $result = $provider->send($dto);
        } catch (\Throwable $e) {
            Log::warning('SMS provider threw an exception.', [
                'sms_message_id' => $record->id,
                'message_type' => $record->message_type,
                'phone' => $phoneNormalizer->mask((string) $record->phone_number),
                'error' => $e->getMessage(),
            ]);

            $this->markFailed($record, 0, ['success' => false, 'message' => 'Provider exception.'], null);

            if ($this->attempts() < $this->tries) {
                throw $e;
            }

            return;
        }

        $record->forceFill([
            'attempt_count' => (int) $record->attempt_count + 1,
            'http_status' => $result->httpStatus,
            'provider_response' => $result->rawResponse,
            'provider_reference' => $result->providerReference,
        ])->save();

        if ($result->success()) {
            $record->forceFill([
                'status' => 'sent',
                'sent_at' => now(),
            ])->save();

            return;
        }

        $this->markFailed($record, $result->httpStatus ?? 0, $result->rawResponse, $result->providerReference);

        if ($result->shouldRetry() && $this->attempts() < $this->tries) {
            throw new \RuntimeException($result->error ?? 'transient_sms_failure');
        }
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        $record = SmsMessageModel::query()->find($this->smsMessageId);
        $maskedPhone = $record
            ? app(SmsPhoneNormalizer::class)->mask((string) $record->phone_number)
            : 'unknown';

        return array_values(array_filter([
            'sms',
            'sms:'.$this->smsMessageId,
            $record ? 'provider:'.(string) $record->provider : null,
            $record ? 'category:'.($record->message_category instanceof \App\Sms\Enums\SmsCategory
                ? $record->message_category->value
                : (string) $record->message_category) : null,
            'recipient:'.$maskedPhone,
        ]));
    }

    private function markSkipped(SmsMessageModel $record, string $reason): void
    {
        $record->forceFill([
            'status' => 'skipped',
            'skip_reason' => $reason,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function markFailed(SmsMessageModel $record, int $httpStatus, array $response, ?string $reference): void
    {
        $record->forceFill([
            'status' => 'failed',
            'failed_at' => now(),
            'http_status' => $httpStatus > 0 ? $httpStatus : $record->http_status,
            'provider_response' => $response,
            'provider_reference' => $reference,
            'attempt_count' => (int) $record->attempt_count + 1,
        ])->save();
    }
}
