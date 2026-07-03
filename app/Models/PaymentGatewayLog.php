<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentGatewayLog extends Model
{
    protected $fillable = [
        'payment_gateway_id',
        'payment_gateway_attempt_id',
        'direction',
        'event',
        'level',
        'message',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function paymentGateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class);
    }

    public function paymentGatewayAttempt(): BelongsTo
    {
        return $this->belongsTo(PaymentGatewayAttempt::class);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function log(
        PaymentGateway $gateway,
        string $event,
        string $message,
        ?PaymentGatewayAttempt $attempt = null,
        string $level = 'info',
        ?string $direction = null,
        array $payload = []
    ): self {
        return self::create([
            'payment_gateway_id' => $gateway->id,
            'payment_gateway_attempt_id' => $attempt?->id,
            'direction' => $direction,
            'event' => $event,
            'level' => $level,
            'message' => $message,
            'payload' => self::redactSecrets(array_merge(
                $payload,
                $attempt ? ['correlation_id' => $attempt->correlationId()] : [],
            )),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function redactSecrets(array $payload): array
    {
        $redacted = $payload;
        foreach (['password', 'username', 'token', 'secret'] as $key) {
            if (array_key_exists($key, $redacted)) {
                $redacted[$key] = '[REDACTED]';
            }
        }

        return $redacted;
    }
}
