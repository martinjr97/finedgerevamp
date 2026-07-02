<?php

namespace App\Models;

use App\PaymentPlatform\Enums\GatewayAttemptPurpose;
use App\PaymentPlatform\Enums\GatewayAttemptStatus;
use App\PaymentPlatform\Enums\GatewayDirection;
use App\PaymentPlatform\Enums\GatewayPaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PaymentGatewayAttempt extends Model
{
    protected $fillable = [
        'payment_gateway_id',
        'direction',
        'purpose',
        'attemptable_type',
        'attemptable_id',
        'internal_reference',
        'provider_reference',
        'provider_transaction_id',
        'payment_method',
        'amount',
        'currency',
        'source_account',
        'destination_account',
        'customer_phone',
        'issuer_name',
        'customer_account',
        'status',
        'response_code',
        'response_message',
        'request_payload',
        'response_payload',
        'callback_payload',
        'query_attempts',
        'next_query_at',
        'last_queried_at',
        'initiated_at',
        'confirmed_at',
        'failed_at',
        'expired_at',
    ];

    protected function casts(): array
    {
        return [
            'direction' => GatewayDirection::class,
            'purpose' => GatewayAttemptPurpose::class,
            'payment_method' => GatewayPaymentMethod::class,
            'status' => GatewayAttemptStatus::class,
            'amount' => 'decimal:2',
            'request_payload' => 'array',
            'response_payload' => 'array',
            'callback_payload' => 'array',
            'next_query_at' => 'datetime',
            'last_queried_at' => 'datetime',
            'initiated_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'failed_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    public function paymentGateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class);
    }

    public function attemptable(): MorphTo
    {
        return $this->morphTo();
    }

    public function logs(): HasMany
    {
        return $this->hasMany(PaymentGatewayLog::class);
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    public function markInitiated(array $payload = []): void
    {
        $this->update([
            'status' => GatewayAttemptStatus::Initiated,
            'initiated_at' => now(),
            'request_payload' => $payload ?: $this->request_payload,
        ]);
    }

    public function markPending(?string $message = null, ?array $responsePayload = null): void
    {
        $this->update([
            'status' => GatewayAttemptStatus::Pending,
            'response_message' => $message ?? $this->response_message,
            'response_payload' => $responsePayload ?? $this->response_payload,
            'next_query_at' => now()->addSeconds((int) config('cgrate.poll_interval_seconds', 15)),
        ]);
    }

    public function markConfirmed(?string $providerTransactionId = null, ?array $responsePayload = null): void
    {
        $this->update([
            'status' => GatewayAttemptStatus::Confirmed,
            'provider_transaction_id' => $providerTransactionId ?? $this->provider_transaction_id,
            'response_payload' => $responsePayload ?? $this->response_payload,
            'confirmed_at' => now(),
        ]);
    }

    public function markFailed(?string $message = null, ?int $responseCode = null): void
    {
        $this->update([
            'status' => GatewayAttemptStatus::Failed,
            'response_message' => $message ?? $this->response_message,
            'response_code' => $responseCode ?? $this->response_code,
            'failed_at' => now(),
        ]);
    }

    public function markRejected(?string $message = null, ?int $responseCode = null): void
    {
        $this->update([
            'status' => GatewayAttemptStatus::Rejected,
            'response_message' => $message ?? $this->response_message,
            'response_code' => $responseCode ?? $this->response_code,
            'failed_at' => now(),
        ]);
    }

    public function markExpired(?string $message = null): void
    {
        $this->update([
            'status' => GatewayAttemptStatus::Expired,
            'response_message' => $message ?? $this->response_message,
            'expired_at' => now(),
        ]);
    }

    public static function generateInternalReference(int $repaymentId, int $attemptId): string
    {
        $rand = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 10));

        return 'FINEDGE-'.$repaymentId.'-'.$attemptId.'-'.$rand;
    }

    public static function generateDisbursementInternalReference(int $loanId, int $attemptId): string
    {
        $rand = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 10));

        return 'FINEDGE-OUT-'.$loanId.'-'.$attemptId.'-'.$rand;
    }
}
