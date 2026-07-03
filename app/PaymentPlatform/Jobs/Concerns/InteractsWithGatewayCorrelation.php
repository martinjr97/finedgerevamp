<?php

namespace App\PaymentPlatform\Jobs\Concerns;

use App\Models\PaymentGatewayAttempt;
use App\PaymentPlatform\Enums\GatewayDirection;
use App\PaymentPlatform\Support\GatewayHorizonTagBuilder;
use Illuminate\Support\Facades\Log;

trait InteractsWithGatewayCorrelation
{
    protected ?string $correlationId = null;

    protected ?GatewayDirection $gatewayDirection = null;

    protected function applyGatewayCorrelationContext(int $attemptId): ?PaymentGatewayAttempt
    {
        $attempt = PaymentGatewayAttempt::query()->find($attemptId);

        if (! $attempt) {
            return null;
        }

        $this->correlationId = $attempt->correlationId();
        $this->gatewayDirection = $attempt->direction;

        Log::shareContext([
            'correlation_id' => $this->correlationId,
            'gateway_attempt_id' => $attempt->id,
            'gateway_direction' => $attempt->direction->value,
        ]);

        return $attempt;
    }

    abstract protected function horizonJobKind(): string;

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        $attempt = PaymentGatewayAttempt::query()
            ->with(['paymentGateway', 'attemptable'])
            ->find($this->paymentGatewayAttemptId);

        return app(GatewayHorizonTagBuilder::class)->build($attempt, $this->horizonJobKind());
    }
}
