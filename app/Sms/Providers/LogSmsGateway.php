<?php

namespace App\Sms\Providers;

use App\Sms\Contracts\SmsGatewayInterface;
use App\Sms\DTOs\SmsMessage;
use App\Sms\DTOs\SmsResult;
use App\Sms\Support\SmsMessageSanitizer;
use App\Sms\Support\SmsPhoneNormalizer;
use Illuminate\Support\Facades\Log;

final class LogSmsGateway implements SmsGatewayInterface
{
    public function __construct(
        private readonly SmsPhoneNormalizer $phoneNormalizer,
        private readonly SmsMessageSanitizer $sanitizer,
    ) {
    }

    public function name(): string
    {
        return 'log';
    }

    public function send(SmsMessage $message): SmsResult
    {
        $maskedPhone = $this->phoneNormalizer->mask($message->phone);
        $preview = $this->sanitizer->logPreview($message->category, $message->body);

        Log::info('SMS (log provider)', [
            'to' => $maskedPhone,
            'category' => $message->category->value,
            'message_type' => $message->messageType,
            'preview' => $preview,
            'length' => mb_strlen($message->body),
        ]);

        return new SmsResult(
            provider: $this->name(),
            successful: true,
            accepted: true,
            retryable: false,
            httpStatus: 202,
            responseCode: 'logged',
            responseMessage: 'Logged locally.',
            rawResponse: [
                'success' => true,
                'message' => 'Logged locally.',
            ],
        );
    }

    public function healthCheck(): SmsResult
    {
        return SmsResult::fromHealth(
            provider: $this->name(),
            ok: true,
            message: 'Log SMS provider is configured.',
            details: ['provider' => $this->name()],
        );
    }
}
