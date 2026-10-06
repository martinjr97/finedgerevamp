<?php

namespace App\Models;

use App\PaymentPlatform\Enums\GatewayDirection;
use App\PaymentPlatform\Enums\GatewayPaymentMethod;
use App\PaymentPlatform\Enums\GatewayRouteKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentGatewayProductRule extends Model
{
    protected $fillable = [
        'loan_product_id',
        'direction',
        'payment_method',
        'payment_gateway_id',
        'enabled',
        'auto_process',
        'priority',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'direction' => GatewayDirection::class,
            'payment_method' => GatewayPaymentMethod::class,
            'enabled' => 'boolean',
            'auto_process' => 'boolean',
        ];
    }

    public function loanProduct(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class);
    }

    public function paymentGateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class);
    }

    public function routeKey(): ?GatewayRouteKey
    {
        return match ([$this->direction, $this->payment_method]) {
            [GatewayDirection::Collection, GatewayPaymentMethod::MobileMoney] => GatewayRouteKey::WalletCollection,
            [GatewayDirection::Collection, GatewayPaymentMethod::Bank] => GatewayRouteKey::BankCollection,
            [GatewayDirection::Disbursement, GatewayPaymentMethod::MobileMoney] => GatewayRouteKey::WalletDisbursement,
            [GatewayDirection::Disbursement, GatewayPaymentMethod::Bank] => GatewayRouteKey::BankDisbursement,
            default => null,
        };
    }

    public function displayLabel(): string
    {
        $direction = $this->direction === GatewayDirection::Collection ? 'Collection' : 'Disbursement';

        $method = match ($this->payment_method) {
            GatewayPaymentMethod::MobileMoney => 'Mobile Money',
            GatewayPaymentMethod::Bank => 'Bank',
            GatewayPaymentMethod::Card => 'Card',
            GatewayPaymentMethod::Manual => 'Manual',
        };

        return "{$direction} · {$method}";
    }
}
