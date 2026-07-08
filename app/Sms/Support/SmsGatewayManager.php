<?php

namespace App\Sms\Support;

use App\PaymentPlatform\Support\ZambiaMsisdnNormalizer;
use App\Sms\Contracts\SmsGatewayInterface;
use App\Sms\Providers\LogSmsGateway;
use App\Sms\Providers\ZamtelSmsGateway;

final class SmsGatewayManager
{
    public function resolve(?string $provider = null): SmsGatewayInterface
    {
        $name = $provider ?? (string) config('sms.provider', 'log');

        return match ($name) {
            'zamtel', 'zamtel_api' => app(ZamtelSmsGateway::class),
            'log' => app(LogSmsGateway::class),
            default => throw new \RuntimeException("Unsupported SMS provider [{$name}]."),
        };
    }
}
