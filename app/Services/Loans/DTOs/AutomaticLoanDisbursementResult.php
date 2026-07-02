<?php

namespace App\Services\Loans\DTOs;

use App\Models\PaymentGatewayAttempt;
use App\Models\PaymentGatewayRoute;
use App\PaymentPlatform\Enums\GatewayRouteKey;
use App\Services\Loans\Enums\AutomaticLoanDisbursementStatus;

readonly class AutomaticLoanDisbursementResult
{
    public function __construct(
        public AutomaticLoanDisbursementStatus $status,
        public string $message,
        public ?PaymentGatewayAttempt $attempt = null,
        public ?PaymentGatewayRoute $route = null,
        public ?GatewayRouteKey $routeKey = null,
    ) {}

    public static function skipped(
        AutomaticLoanDisbursementStatus $status,
        string $message,
        ?PaymentGatewayRoute $route = null,
        ?GatewayRouteKey $routeKey = null,
    ): self {
        return new self(
            status: $status,
            message: $message,
            route: $route,
            routeKey: $routeKey,
        );
    }

    public static function initiated(
        PaymentGatewayAttempt $attempt,
        ?PaymentGatewayRoute $route = null,
        ?GatewayRouteKey $routeKey = null,
    ): self {
        return new self(
            status: AutomaticLoanDisbursementStatus::Initiated,
            message: 'Gateway disbursement has been initiated.',
            attempt: $attempt,
            route: $route,
            routeKey: $routeKey,
        );
    }

    public static function failed(string $message, ?PaymentGatewayRoute $route = null, ?GatewayRouteKey $routeKey = null): self
    {
        return new self(
            status: AutomaticLoanDisbursementStatus::Failed,
            message: $message,
            route: $route,
            routeKey: $routeKey,
        );
    }

    public function wasInitiated(): bool
    {
        return $this->status === AutomaticLoanDisbursementStatus::Initiated;
    }

    public function requiresManualDisbursement(): bool
    {
        return $this->status->isManualFallback();
    }

    public function userFlashMessage(): string
    {
        if ($this->status === AutomaticLoanDisbursementStatus::Initiated) {
            return 'Loan approved successfully. Gateway disbursement has been initiated.';
        }

        if (in_array($this->status, [
            AutomaticLoanDisbursementStatus::SkippedNoAutoRoute,
            AutomaticLoanDisbursementStatus::SkippedRouteDisabled,
            AutomaticLoanDisbursementStatus::SkippedAutoProcessOff,
        ], true)) {
            return 'Loan approved successfully. Please complete manual disbursement.';
        }

        return 'Loan approved successfully. Automatic gateway disbursement could not be started: '
            .$this->message
            .'. Manual disbursement remains available.';
    }

    public function flashSessionKey(): string
    {
        return $this->status->usesWarningFlash() ? 'warning' : 'status';
    }

    public function gatewayAttemptId(): ?int
    {
        return $this->attempt?->id;
    }
}
