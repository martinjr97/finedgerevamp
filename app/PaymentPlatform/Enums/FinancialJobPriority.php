<?php

namespace App\PaymentPlatform\Enums;

enum FinancialJobPriority: string
{
    case High = 'high';
    case Polling = 'polling';
}
