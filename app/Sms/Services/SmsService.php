<?php

namespace App\Sms\Services;

use App\Models\SmsMessage as SmsMessageModel;
use App\Sms\DTOs\SmsMessage;
use App\Sms\DTOs\SmsResult;
use App\Sms\Jobs\SendSmsJob;
use App\Sms\Support\SmsGatewayManager;
use App\Sms\Support\SmsMessageSanitizer;
use App\Sms\Support\SmsMessageValidator;
use App\Sms\Support\SmsPhoneNormalizer;
use Illuminate\Support\Facades\Log;

class SmsService
{
    public function __construct(
        private readonly SmsGatewayManager $gatewayManager,
        private readonly SmsPhoneNormalizer $phoneNormalizer,
        private readonly SmsMessageValidator $validator,
        private readonly SmsMessageSanitizer $sanitizer,
    ) {
    }

    /**
     * @param  array<string, mixed>|SmsMessage  $message
     */
    public function queueSend(array|SmsMessage $message): ?SmsMessageModel
    {
        $dto = $message instanceof SmsMessage ? $message : SmsMessage::fromArray($message);

        return $this->processQueue($dto, sync: false);
    }

    /**
     * Create an sms_messages audit row and deliver immediately (no async queue).
     * Use for admin UI flows where the operator needs Zamtel success/failure right away.
     *
     * @param  array<string, mixed>|SmsMessage  $message
     */
    public function sendRecorded(array|SmsMessage $message): ?SmsMessageModel
    {
        $dto = $message instanceof SmsMessage ? $message : SmsMessage::fromArray($message);

        return $this->processQueue($dto, sync: true);
    }

    /**
     * @param  array<string, mixed>|SmsMessage  $message
     */
    public function sendNow(array|SmsMessage $message, ?string $provider = null): SmsResult
    {
        $dto = $message instanceof SmsMessage ? $message : SmsMessage::fromArray($message);
        $resolvedProvider = $this->resolveProvider($dto, $provider);

        if (! $this->isSendingAllowed($dto)) {
            return SmsResult::skippedResult($resolvedProvider, 'SMS sending is disabled.');
        }

        if (! $this->phoneNormalizer->isValid($dto->phone)) {
            return new SmsResult(
                provider: $resolvedProvider,
                successful: false,
                accepted: false,
                retryable: false,
                responseMessage: 'Invalid phone number.',
                rawResponse: ['success' => false, 'message' => 'Invalid phone number.'],
                error: 'invalid_phone',
                skipped: true,
            );
        }

        try {
            $this->validator->assertValidLength($dto->body);
        } catch (\InvalidArgumentException $e) {
            return new SmsResult(
                provider: $resolvedProvider,
                successful: false,
                accepted: false,
                retryable: false,
                responseMessage: $e->getMessage(),
                rawResponse: ['success' => false, 'message' => $e->getMessage()],
                error: 'too_long',
                skipped: true,
            );
        }

        try {
            return $this->gatewayManager->resolve($resolvedProvider)->send($dto);
        } catch (\Throwable $e) {
            Log::warning('SMS sendNow failed.', [
                'provider' => $resolvedProvider,
                'message_type' => $dto->messageType,
                'phone' => $this->phoneNormalizer->mask($dto->phone),
                'error' => $e->getMessage(),
            ]);

            return new SmsResult(
                provider: $resolvedProvider,
                successful: false,
                accepted: false,
                retryable: true,
                responseMessage: 'Provider exception.',
                rawResponse: ['success' => false, 'message' => 'Provider exception.'],
                error: 'provider_exception',
            );
        }
    }

    /**
     * Resolve the configured provider (used only when SMS is enabled / forceSend).
     */
    public function resolveProvider(SmsMessage $dto, ?string $override = null): string
    {
        if (is_string($override) && $override !== '') {
            return $override;
        }

        return (string) config('sms.provider', 'log');
    }

    private function processQueue(SmsMessage $dto, bool $sync = false): ?SmsMessageModel
    {
        $provider = $this->resolveProvider($dto);
        $phone = trim($dto->phone);

        if ($phone === '') {
            return null;
        }

        $record = $this->createRecord($dto, $provider, 'queued');

        if (! $this->phoneNormalizer->isValid($phone)) {
            return $this->markSkipped($record, 'invalid_phone');
        }

        try {
            $this->validator->assertValidLength($dto->body);
        } catch (\InvalidArgumentException) {
            return $this->markSkipped($record, 'too_long');
        }

        // SMS_ENABLED=false → do not send and do not write via the log provider.
        if (! $this->isSendingAllowed($dto)) {
            return $this->markSkipped($record, 'disabled');
        }

        try {
            $normalizedStorage = $this->phoneNormalizer->normalizeForStorage($phone);
            $providerContacts = $this->phoneNormalizer->normalizeForProvider($phone, $provider);

            $record->forceFill([
                'normalized_phone' => $providerContacts,
                'phone_number' => $normalizedStorage,
            ])->save();
        } catch (\InvalidArgumentException) {
            return $this->markSkipped($record, 'invalid_phone');
        }

        try {
            if ($sync) {
                SendSmsJob::dispatchSync($record->id, $dto->body);
            } else {
                SendSmsJob::dispatch($record->id, $dto->body);
            }

            return $record->fresh();
        } catch (\Throwable $e) {
            $record->forceFill([
                'status' => 'failed',
                'failed_at' => now(),
                'skip_reason' => 'dispatch_failed',
            ])->save();

            Log::warning('SMS queue dispatch failed.', [
                'sms_message_id' => $record->id,
                'message_type' => $dto->messageType,
                'phone' => $this->phoneNormalizer->mask($phone),
                'error' => $e->getMessage(),
            ]);

            return $record->fresh();
        }
    }

    private function createRecord(SmsMessage $dto, string $provider, string $status): SmsMessageModel
    {
        return SmsMessageModel::create([
            'recipient_type' => $dto->recipientType,
            'recipient_id' => $dto->recipientId,
            'customer_id' => $dto->customerId,
            'admin_id' => $dto->adminId,
            'loan_id' => $dto->loanId,
            'phone_number' => $dto->phone,
            'normalized_phone' => null,
            'message_category' => $dto->category,
            'message_type' => $dto->messageType,
            'message_body' => $this->sanitizer->storageBody($dto->category, $dto->body),
            'message_preview' => $this->sanitizer->preview($dto->category, $dto->body),
            'message_length' => mb_strlen($dto->body),
            'provider' => $provider,
            'status' => $status,
            'metadata' => $dto->metadata !== [] ? $dto->metadata : null,
        ]);
    }

    private function markSkipped(SmsMessageModel $record, string $reason): SmsMessageModel
    {
        $record->forceFill([
            'status' => 'skipped',
            'skip_reason' => $reason,
        ])->save();

        return $record->fresh();
    }

    private function isSendingAllowed(SmsMessage $dto): bool
    {
        if ($dto->forceSend) {
            return true;
        }

        return (bool) config('sms.enabled', false);
    }
}
