<?php

namespace App\Sms\Providers;

use App\Sms\Contracts\SmsGatewayInterface;
use App\Sms\DTOs\SmsMessage;
use App\Sms\DTOs\SmsResult;
use App\Sms\Support\SmsMessageSanitizer;
use App\Sms\Support\SmsPhoneNormalizer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class ZamtelSmsGateway implements SmsGatewayInterface
{
    public function __construct(
        private readonly SmsPhoneNormalizer $phoneNormalizer,
        private readonly SmsMessageSanitizer $sanitizer,
    ) {
    }

    public function name(): string
    {
        return 'zamtel';
    }

    public function send(SmsMessage $message): SmsResult
    {
        $apiKey = trim((string) config('sms.zamtel.api_key'));
        // Preserve casing exactly as configured (Zamtel sender IDs are case-sensitive).
        $senderId = trim((string) config('sms.zamtel.sender_id'));

        if ($apiKey === '' || $senderId === '') {
            return new SmsResult(
                provider: $this->name(),
                successful: false,
                accepted: false,
                retryable: false,
                responseMessage: 'Zamtel SMS credentials are not configured.',
                rawResponse: ['success' => false, 'message' => 'Missing API credentials.'],
                error: 'missing_credentials',
            );
        }

        try {
            $contacts = $this->phoneNormalizer->normalizeForZamtel($message->phone);
        } catch (\InvalidArgumentException $e) {
            return new SmsResult(
                provider: $this->name(),
                successful: false,
                accepted: false,
                retryable: false,
                responseMessage: $e->getMessage(),
                rawResponse: ['success' => false, 'message' => $e->getMessage()],
                error: 'invalid_phone',
            );
        }

        $baseUrl = rtrim((string) config('sms.zamtel.base_url'), '/');
        $url = sprintf(
            '%s/api/v2.1/action/send/api_key/%s/contacts/%s/senderId/%s/message/%s',
            $baseUrl,
            rawurlencode($apiKey),
            rawurlencode($contacts),
            rawurlencode($senderId),
            rawurlencode($message->body),
        );

        $timeout = (int) config('sms.zamtel.timeout', 30);
        $connectTimeout = (int) config('sms.zamtel.connect_timeout', 10);

        try {
            $response = Http::withOptions([
                'verify' => (bool) config('sms.zamtel.verify_ssl', true),
            ])
                ->connectTimeout($connectTimeout)
                ->timeout($timeout)
                ->acceptJson()
                ->get($url);
        } catch (ConnectionException) {
            return new SmsResult(
                provider: $this->name(),
                successful: false,
                accepted: false,
                retryable: true,
                responseMessage: 'Connection failed.',
                rawResponse: ['success' => false, 'message' => 'Connection failed.'],
                error: 'connection_error',
            );
        }

        $httpStatus = $response->status();
        $parsed = $this->parseBody($response->body());
        $sanitized = $this->sanitizer->sanitizeProviderResponse($parsed, $apiKey);
        $providerSuccess = (bool) data_get($parsed, 'success', false);
        $accepted = in_array($httpStatus, [200, 202], true) && $providerSuccess;
        $transient = ! $accepted && ($httpStatus === 0 || $httpStatus >= 500 || $response->serverError());

        return new SmsResult(
            provider: $this->name(),
            successful: $accepted,
            accepted: $accepted,
            retryable: $transient,
            providerReference: $this->extractReference($parsed),
            httpStatus: $httpStatus,
            responseCode: is_string(data_get($parsed, 'code')) ? (string) data_get($parsed, 'code') : null,
            responseMessage: is_string(data_get($parsed, 'message')) ? (string) data_get($parsed, 'message') : null,
            rawResponse: $sanitized,
            error: $accepted ? null : ($providerSuccess ? 'unexpected_status' : 'provider_rejected'),
        );
    }

    public function healthCheck(): SmsResult
    {
        $apiKey = trim((string) config('sms.zamtel.api_key'));
        $senderId = trim((string) config('sms.zamtel.sender_id'));
        $baseUrl = trim((string) config('sms.zamtel.base_url'));

        if ($apiKey === '' || $senderId === '') {
            return SmsResult::fromHealth(
                provider: $this->name(),
                ok: false,
                message: 'Zamtel API key or sender ID is not configured.',
                details: [
                    'api_key_configured' => $apiKey !== '',
                    'sender_id_configured' => $senderId !== '',
                    'base_url' => $baseUrl,
                ],
            );
        }

        $timeout = min(10, (int) config('sms.zamtel.timeout', 30));

        try {
            $response = Http::withOptions([
                'verify' => (bool) config('sms.zamtel.verify_ssl', true),
            ])
                ->connectTimeout(5)
                ->timeout($timeout)
                ->get(rtrim($baseUrl, '/'));
        } catch (ConnectionException) {
            return SmsResult::fromHealth(
                provider: $this->name(),
                ok: false,
                message: 'Could not reach Zamtel Bulk SMS host.',
                details: ['base_url' => $baseUrl],
            );
        }

        return SmsResult::fromHealth(
            provider: $this->name(),
            ok: true,
            message: 'Zamtel Bulk SMS host is reachable and credentials are configured.',
            details: [
                'base_url' => $baseUrl,
                'sender_id' => $senderId,
                'probe_http_status' => $response->status(),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function parseBody(string $body): array
    {
        $body = trim($body);
        if ($body === '') {
            return [];
        }

        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : ['message' => mb_substr($body, 0, 500)];
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function extractReference(array $parsed): ?string
    {
        foreach (['reference', 'messageId', 'message_id', 'id'] as $key) {
            $value = data_get($parsed, $key);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
