<?php

namespace App\PaymentPlatform\Support;

class PaymentQueue
{
    public static function high(): string
    {
        return (string) config('payments.queues.high', 'payments-high');
    }

    public static function polling(): string
    {
        return (string) config('payments.queues.polling', 'payments');
    }
}
