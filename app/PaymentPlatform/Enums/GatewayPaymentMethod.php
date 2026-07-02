<?php

namespace App\PaymentPlatform\Enums;

enum GatewayPaymentMethod: string
{
    case MobileMoney = 'mobile_money';
    case Bank = 'bank';
    case Card = 'card';
    case Manual = 'manual';
}
