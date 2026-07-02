<?php

namespace App\PaymentPlatform\Enums;

enum GatewayDirection: string
{
    case Collection = 'collection';
    case Disbursement = 'disbursement';
}
