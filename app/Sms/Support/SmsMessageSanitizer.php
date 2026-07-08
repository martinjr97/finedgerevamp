<?php

namespace App\Sms\Support;

use App\Sms\Enums\SmsCategory;

final class SmsMessageSanitizer
{
    private const REDACTED_SENSITIVE = '[REDACTED OTP MESSAGE]';

    public function storageBody(SmsCategory $category, string $body): string
    {
        if ($category->isSensitive()) {
            return self::REDACTED_SENSITIVE;
        }

        return $body;
    }

    public function preview(SmsCategory $category, string $body): string
    {
        if ($category->isSensitive()) {
            return self::REDACTED_SENSITIVE;
        }

        return mb_strlen($body) > 80 ? mb_substr($body, 0, 77).'...' : $body;
    }

    public function logPreview(SmsCategory $category, string $body): string
    {
        return $this->preview($category, $body);
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    public function sanitizeProviderResponse(array $parsed, ?string $apiKey = null): array
    {
        $json = json_encode($parsed) ?: '';
        if (is_string($apiKey) && $apiKey !== '') {
            $json = str_replace($apiKey, '[REDACTED]', $json);
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : ['success' => data_get($parsed, 'success')];
    }
}
