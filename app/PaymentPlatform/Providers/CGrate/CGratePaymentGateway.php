<?php

namespace App\PaymentPlatform\Providers\CGrate;

use App\Models\PaymentGatewayAttempt;
use App\PaymentPlatform\Contracts\DisbursementGatewayInterface;
use App\PaymentPlatform\Contracts\PaymentGatewayInterface;
use App\PaymentPlatform\DTOs\CollectMoneyRequest;
use App\PaymentPlatform\DTOs\DisburseMoneyRequest;
use App\PaymentPlatform\DTOs\GatewayResult;
use App\PaymentPlatform\DTOs\GatewayStatusResult;
use App\PaymentPlatform\Enums\GatewayDirection;
use App\PaymentPlatform\Enums\GatewayPaymentMethod;
use App\PaymentPlatform\Support\ZambiaMsisdnNormalizer;

final class CGratePaymentGateway implements PaymentGatewayInterface, DisbursementGatewayInterface
{
    public function __construct(
        private readonly CGrateClient $client,
    ) {}

    public function collect(CollectMoneyRequest $request): GatewayResult
    {
        if (! (bool) config('cgrate.enabled')) {
            throw new CGrateException('cGrate payments are disabled.');
        }

        if ($request->paymentMethod !== GatewayPaymentMethod::MobileMoney->value) {
            throw new CGrateException('cGrate gateway only supports mobile money collections.');
        }

        $msisdn = ZambiaMsisdnNormalizer::normalizeForCGrate(
            (string) $request->customerPhone,
            (string) config('cgrate.msisdn_format', 'local')
        );

        $paymentReference = $request->providerReference ?? $request->internalReference;
        $transactionAmount = $this->formatTransactionAmount($request->amount);

        $resp = $this->client->processCustomerPayment(
            transactionAmount: $transactionAmount,
            customerMobile: $msisdn,
            paymentReference: $paymentReference,
        );

        $success = $resp->isSuccessfulRequest();
        $normalizedStatus = $success ? 'pending' : 'failed';

        return new GatewayResult(
            success: $success,
            providerReference: $paymentReference,
            providerTransactionId: $resp->paymentId,
            responseCode: $resp->responseCode,
            responseMessage: $resp->responseMessage,
            normalizedStatus: $normalizedStatus,
            rawPayload: ['cgrate' => $resp->toArray()],
        );
    }

    public function disburse(DisburseMoneyRequest $request): GatewayResult
    {
        if (! (bool) config('cgrate.enabled')) {
            throw new CGrateException('cGrate payments are disabled.');
        }

        if (! in_array($request->paymentMethod, [
            GatewayPaymentMethod::MobileMoney->value,
            GatewayPaymentMethod::Bank->value,
        ], true)) {
            throw new CGrateException('cGrate disbursement supports mobile money and bank only.');
        }

        $depositorReference = $request->providerReference ?? $request->internalReference;
        $transactionAmount = $this->formatTransactionAmount($request->amount);

        $customerAccount = $request->customerAccount;
        if ($request->paymentMethod === GatewayPaymentMethod::MobileMoney->value) {
            $customerAccount = ZambiaMsisdnNormalizer::normalizeForCGrate(
                $customerAccount,
                (string) config('cgrate.msisdn_format', 'local')
            );
        }

        $resp = $this->client->processCashDeposit(
            transactionAmount: $transactionAmount,
            customerAccount: $customerAccount,
            issuerName: $request->issuerName,
            depositorReference: $depositorReference,
        );

        $success = $resp->isSuccessfulRequest();
        $normalizedStatus = $success ? 'pending' : 'failed';

        return new GatewayResult(
            success: $success,
            providerReference: $depositorReference,
            providerTransactionId: $resp->paymentId,
            responseCode: $resp->responseCode,
            responseMessage: $resp->responseMessage,
            normalizedStatus: $normalizedStatus,
            rawPayload: ['cgrate' => $resp->toArray()],
        );
    }

    public function queryStatus(PaymentGatewayAttempt $attempt): GatewayStatusResult
    {
        if (! (bool) config('cgrate.enabled')) {
            throw new CGrateException('cGrate payments are disabled.');
        }

        $reference = (string) ($attempt->provider_reference ?? $attempt->internal_reference);
        $resp = $this->client->queryCustomerPayment($reference);

        $normalized = $this->normalizeQueryStatus($resp);

        return new GatewayStatusResult(
            normalizedStatus: $normalized,
            providerTransactionId: $resp->paymentId,
            responseCode: $resp->responseCode,
            responseMessage: $resp->responseMessage,
            rawPayload: ['cgrate' => $resp->toArray()],
        );
    }

    public function supports(string $paymentMethod, string $direction): bool
    {
        if ($direction === GatewayDirection::Collection->value) {
            return $paymentMethod === GatewayPaymentMethod::MobileMoney->value;
        }

        if ($direction === GatewayDirection::Disbursement->value) {
            return in_array($paymentMethod, [
                GatewayPaymentMethod::MobileMoney->value,
                GatewayPaymentMethod::Bank->value,
            ], true);
        }

        return false;
    }

    private function formatTransactionAmount(float $amount): string
    {
        $mode = (string) config('cgrate.amount_mode', 'kwacha_decimal');
        $amountCents = (int) round($amount * 100);

        return match ($mode) {
            'minor_units' => (string) $amountCents,
            'kwacha_decimal' => number_format($amount, 2, '.', ''),
            default => throw new CGrateException('Invalid cGrate amount mode configuration.'),
        };
    }

    private function normalizeQueryStatus(CGratePaymentResponse $resp): string
    {
        if ($resp->isApproved()) {
            return 'confirmed';
        }
        if ($resp->isRejected()) {
            return 'rejected';
        }
        if ($resp->isFailed()) {
            return 'failed';
        }
        if ($resp->isPending()) {
            return 'pending';
        }
        if ($resp->isUnknown()) {
            return 'unknown';
        }
        if ($resp->responseCode === 0) {
            return 'pending';
        }
        if ($resp->isConfigOrAuthError()) {
            return 'failed';
        }

        return 'unknown';
    }
}
