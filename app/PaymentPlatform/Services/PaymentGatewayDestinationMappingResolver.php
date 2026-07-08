<?php

namespace App\PaymentPlatform\Services;

use App\Models\Loan;
use App\Models\PaymentGateway;
use App\Models\PaymentGatewayDestinationMapping;

class PaymentGatewayDestinationMappingResolver
{
    public function environmentForGateway(PaymentGateway $gateway): string
    {
        // Default to Laravel env if we can't infer a gateway environment.
        $appEnv = (string) config('app.env', 'local');

        // cGrate UAT is currently distinguished by base URL containing test.543.
        if ($gateway->code === 'cgrate') {
            $baseUrl = (string) config('cgrate.base_url', '');
            if ($appEnv === 'production') {
                return 'production';
            }

            if (str_contains($baseUrl, 'test.543')) {
                return 'uat';
            }

            return 'local';
        }

        return $appEnv;
    }

    /**
     * @return array{mapping:?PaymentGatewayDestinationMapping, matchEnvironment:?string}
     */
    public function resolve(
        PaymentGateway $gateway,
        string $destinationType,
        ?int $financialInstitutionId,
        ?int $channelId,
        string $gatewayKey,
        ?string $environment = null
    ): array {
        $env = $environment ?? $this->environmentForGateway($gateway);

        $mapping = $this->findMapping(
            $gateway->id,
            $destinationType,
            $financialInstitutionId,
            $channelId,
            $gatewayKey,
            $env
        );

        if ($mapping) {
            return ['mapping' => $mapping, 'matchEnvironment' => $env];
        }

        // Optional global fallback: only environment=NULL mapping.
        $globalMapping = $this->findMapping(
            $gateway->id,
            $destinationType,
            $financialInstitutionId,
            $channelId,
            $gatewayKey,
            null
        );

        return ['mapping' => $globalMapping, 'matchEnvironment' => null];
    }

    private function findMapping(
        int $paymentGatewayId,
        string $destinationType,
        ?int $financialInstitutionId,
        ?int $channelId,
        string $gatewayKey,
        ?string $environment
    ): ?PaymentGatewayDestinationMapping {
        $query = PaymentGatewayDestinationMapping::query()
            ->where('payment_gateway_id', $paymentGatewayId)
            ->where('destination_type', $destinationType)
            ->where('gateway_key', $gatewayKey)
            ->whereIn('status', ['active', 'verification_required'])
            ->where('environment', $environment)
            ->where('financial_institution_id', $financialInstitutionId)
            ->where('channel_id', $channelId)
            ->orderByDesc('updated_at');

        /** @var PaymentGatewayDestinationMapping|null $mapping */
        $mapping = $query->first();

        return $mapping;
    }
}

