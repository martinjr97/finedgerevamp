<?php

namespace App\Models;

use App\PaymentPlatform\Enums\GatewayRouteKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentGatewayRoute extends Model
{
    protected $fillable = [
        'route_key',
        'payment_gateway_id',
        'enabled',
        'auto_process',
        'fallback_to_manual',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'route_key' => GatewayRouteKey::class,
            'enabled' => 'boolean',
            'auto_process' => 'boolean',
            'fallback_to_manual' => 'boolean',
        ];
    }

    public function paymentGateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class);
    }

    public function isConfigured(): bool
    {
        return $this->enabled && $this->payment_gateway_id !== null;
    }
}
