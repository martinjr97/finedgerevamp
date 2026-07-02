<?php

namespace App\PaymentPlatform\Enums;

enum FinancialAccountType: string
{
    case Bank = 'bank';
    case Wallet = 'wallet';
}
