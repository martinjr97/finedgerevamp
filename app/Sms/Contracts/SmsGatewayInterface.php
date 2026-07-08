<?php

namespace App\Sms\Contracts;

use App\Sms\DTOs\SmsMessage;
use App\Sms\DTOs\SmsResult;

interface SmsGatewayInterface
{
    public function name(): string;

    public function send(SmsMessage $message): SmsResult;

    public function healthCheck(): SmsResult;
}
