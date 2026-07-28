<?php

namespace App\Sms\Services;

use App\Models\Customer;
use App\Models\SmsTemplate;
use App\Sms\DTOs\SmsMessage;
use App\Sms\Enums\SmsCategory;
use App\Sms\Support\SmsMessageValidator;
use Illuminate\Support\Facades\Log;

class SmsTemplateService
{
    public function __construct(
        private readonly SmsService $smsService,
        private readonly SmsMessageValidator $validator,
    ) {}

    /**
     * @param  array<string, scalar|null>  $variables
     */
    public function render(string $key, array $variables = []): ?string
    {
        $template = $this->findActiveTemplate($key);
        if (! $template) {
            Log::warning('SMS template not found or inactive.', ['template_key' => $key]);

            return null;
        }

        $variables = $this->normalizeVariables($variables);
        $body = $this->substitute($template->body, $variables);

        if (mb_strlen($body) > $template->max_length) {
            Log::warning('Rendered SMS exceeds template max length.', [
                'template_key' => $key,
                'max_length' => $template->max_length,
                'actual_length' => mb_strlen($body),
            ]);

            return null;
        }

        return $body;
    }

    /**
     * @param  array<string, scalar|null>  $variables
     * @param  array<string, mixed>  $metadata
     */
    public function queueForCustomer(
        Customer $customer,
        string $templateKey,
        array $variables,
        string $messageType,
        array $metadata = [],
        ?int $loanId = null,
    ): ?\App\Models\SmsMessage {
        if (! $customer->phone) {
            return null;
        }

        $template = $this->findActiveTemplate($templateKey);
        if (! $template) {
            return null;
        }

        $variables = $this->normalizeVariables($variables);
        $body = $this->substitute($template->body, $variables);

        if (mb_strlen($body) > $template->max_length) {
            Log::warning('SMS not queued: rendered body exceeds max length.', [
                'template_key' => $templateKey,
                'customer_id' => $customer->id,
                'max_length' => $template->max_length,
                'actual_length' => mb_strlen($body),
            ]);

            return $this->smsService->queueSend(new SmsMessage(
                phone: $customer->phone,
                body: $body,
                category: $template->category,
                messageType: $messageType,
                recipientType: $customer->getMorphClass(),
                recipientId: (int) $customer->id,
                customerId: (int) $customer->id,
                loanId: $loanId,
                metadata: array_merge($metadata, [
                    'template_key' => $templateKey,
                    'skip_reason' => 'template_too_long',
                ]),
            ));
        }

        try {
            $this->validator->assertValidLength($body);
        } catch (\InvalidArgumentException) {
            Log::warning('SMS not queued: global max length exceeded.', [
                'template_key' => $templateKey,
                'customer_id' => $customer->id,
            ]);

            return null;
        }

        return $this->smsService->queueSend(new SmsMessage(
            phone: $customer->phone,
            body: $body,
            category: $template->category,
            messageType: $messageType,
            recipientType: $customer->getMorphClass(),
            recipientId: (int) $customer->id,
            customerId: (int) $customer->id,
            loanId: $loanId,
            metadata: array_merge($metadata, ['template_key' => $templateKey]),
        ));
    }

    public function categoryForKey(string $key): SmsCategory
    {
        return $this->findActiveTemplate($key)?->category ?? SmsCategory::General;
    }

    public function reminderTemplateKey(string $reminderType): string
    {
        return match ($reminderType) {
            '1_week_before' => 'reminder_1_week_before',
            '2_days_before' => 'reminder_2_days_before',
            '1_day_before' => 'reminder_1_day_before',
            'missed_1' => 'reminder_missed_1',
            'missed_2' => 'reminder_missed_2',
            default => 'reminder_1_day_before',
        };
    }

    /**
     * @param  array<string, scalar|null>  $variables
     * @return array<string, string>
     */
    public function normalizeVariables(array $variables): array
    {
        $normalized = [];
        foreach ($variables as $key => $value) {
            if ($value === null) {
                continue;
            }
            $normalized[strtoupper((string) $key)] = $this->formatValue((string) $key, $value);
        }

        if (! isset($normalized['APP_NAME'])) {
            $normalized['APP_NAME'] = (string) config('app.name', 'FineEdge');
        }

        return $normalized;
    }

    private function findActiveTemplate(string $key): ?SmsTemplate
    {
        return SmsTemplate::query()
            ->where('key', $key)
            ->where('is_active', true)
            ->first();
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function substitute(string $body, array $variables): string
    {
        $rendered = $body;
        foreach ($variables as $key => $value) {
            $rendered = str_replace('{'.$key.'}', $value, $rendered);
        }

        return trim(preg_replace('/\s+/', ' ', $rendered) ?? $rendered);
    }

    private function formatValue(string $key, mixed $value): string
    {
        $upper = strtoupper($key);
        if (in_array($upper, ['AMOUNT', 'BALANCE'], true) && is_numeric($value)) {
            return $this->formatMoney((float) $value);
        }

        return trim((string) $value);
    }

    private function formatMoney(float $amount): string
    {
        if (fmod($amount, 1.0) === 0.0) {
            return number_format($amount, 0, '.', ',');
        }

        return number_format($amount, 2, '.', ',');
    }
}
