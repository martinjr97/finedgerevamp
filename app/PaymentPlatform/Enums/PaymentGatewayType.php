<?php

namespace App\PaymentPlatform\Enums;

enum PaymentGatewayType: string
{
    case Collection = 'collection';
    case Disbursement = 'disbursement';
    case Both = 'both';
}
