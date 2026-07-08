<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentGatewayDestinationMapping extends Model
{
    protected $fillable = [
        'payment_gateway_id',
        'destination_type',
        'financial_institution_id',
        'channel_id',
        'gateway_key',
        'gateway_value',
        'environment',
        'status',
        'last_verified_at',
        'metadata',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'last_verified_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function paymentGateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class);
    }

    public function financialInstitution(): BelongsTo
    {
        return $this->belongsTo(FinancialInstitution::class, 'financial_institution_id');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'channel_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isVerified(): bool
    {
        // By default, only 'active' is considered verified.
        return $this->isActive();
    }

    public function isVerificationRequired(): bool
    {
        return $this->status === 'verification_required';
    }

    public function appliesToBank(): bool
    {
        return $this->destination_type === 'bank';
    }

    public function appliesToMobileMoney(): bool
    {
        return $this->destination_type === 'mobile_money';
    }
}

