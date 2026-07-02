<?php

namespace App\PaymentPlatform\Enums;

enum PaymentGatewayStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Maintenance = 'maintenance';
    case Disabled = 'disabled';

    public function isOperational(): bool
    {
        return $this === self::Active;
    }
}
