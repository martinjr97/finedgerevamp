<?php

namespace App\PaymentPlatform\Providers\Kazang;

use App\Models\PaymentGatewayAttempt;
use App\Models\Repayment;
use App\PaymentPlatform\Contracts\DisbursementGatewayInterface;
use App\PaymentPlatform\Contracts\PaymentGatewayInterface;
use App\PaymentPlatform\DTOs\CollectMoneyRequest;
use App\PaymentPlatform\DTOs\DisburseMoneyRequest;
use App\PaymentPlatform\DTOs\GatewayResult;
use App\PaymentPlatform\DTOs\GatewayStatusResult;
use App\PaymentPlatform\Enums\GatewayDirection;
use App\PaymentPlatform\Enums\GatewayPaymentMethod;

final class KazangPaymentGateway implements PaymentGatewayInterface, DisbursementGatewayInterface
{
    public function __construct(
        private readonly KazangApiClient $apiClient,
        private readonly KazangAmqpPublisher $amqpPublisher,
        private readonly KazangOperatorResolver $operatorResolver,
        private readonly KazangPayloadFactory $payloadFactory,
    ) {}

    public function collect(CollectMoneyRequest $request): GatewayResult
    {
        if (! (bool) config('kazang.enabled')) {
            throw new KazangException('Kazang payments are disabled.');
        }

        if ($request->paymentMethod !== GatewayPaymentMethod::MobileMoney->value) {
            throw new KazangException('Kazang gateway only supports mobile money collections.');
        }

        $repaymentId = (int) ($request->metadata['repayment_id'] ?? 0);
        $repayment = Repayment::query()->with('customer')->find($repaymentId);

        if (! $repayment) {
            throw new KazangException('Repayment context is required for Kazang collection.');
        }

        $payload = $this->payloadFactory->buildCollectionPayload($repayment, $request->customerPhone);
        $operator = $this->operatorResolver->resolveFromPhone($payload['phone_number']);

        if ($operator === null) {
            throw new KazangException('Could not determine mobile money operator for Kazang collection.');
        }

        $queue = $this->operatorResolver->queueForOperator($operator);
        $this->amqpPublisher->publish($payload, $queue);

        return new GatewayResult(
            success: true,
            providerReference: (string) ($request->providerReference ?? $request->internalReference),
            providerTransactionId: (string) $repayment->id,
            responseCode: 0,
            responseMessage: 'Repayment queued for Kazang processing.',
            normalizedStatus: 'pending',
            rawPayload: [
                'kazang' => [
                    'operator' => $operator,
                    'queue' => $queue,
                    'payload' => $payload,
                ],
            ],
        );
    }

    public function disburse(DisburseMoneyRequest $request): GatewayResult
    {
        if (! (bool) config('kazang.enabled')) {
            throw new KazangException('Kazang payments are disabled.');
        }

        if ($request->paymentMethod !== GatewayPaymentMethod::MobileMoney->value) {
            throw new KazangException('Kazang disbursement supports mobile money only.');
        }

        $operator = $this->operatorResolver->resolveFromPhone($request->customerAccount);

        if ($operator === null) {
            throw new KazangException('Could not determine mobile money operator for Kazang disbursement.');
        }

        $auth = $this->apiClient->authenticate();
        $sessionUuid = (string) ($auth['session_uuid'] ?? '');

        if ($sessionUuid === '') {
            throw new KazangException('Kazang authentication failed.');
        }

        $referenceNumber = date('His');
        $cashInPayload = [
            'request_reference' => $referenceNumber,
            'session_uuid' => $sessionUuid,
            'product_id' => $this->operatorResolver->productIdForOperator($operator),
            'amount' => (int) round($request->amount * 100),
            'reference' => $this->normalizeDisbursementReference($request->customerAccount),
            'sender_name' => 'Finedge Limited',
            'sender_reference' => 'Cash In',
        ];

        $cashInResponse = $this->apiClient->nfsCashIn($cashInPayload);

        if ((string) ($cashInResponse['response_code'] ?? '') !== '0') {
            return new GatewayResult(
                success: false,
                providerReference: $request->providerReference ?? $request->internalReference,
                providerTransactionId: null,
                responseCode: is_numeric($cashInResponse['response_code'] ?? null) ? (int) $cashInResponse['response_code'] : null,
                responseMessage: (string) ($cashInResponse['response_message'] ?? 'Kazang cash-in failed.'),
                normalizedStatus: 'failed',
                rawPayload: ['kazang' => ['cash_in' => $cashInResponse]],
            );
        }

        $confirmPayload = [
            'request_reference' => date('His'),
            'session_uuid' => $sessionUuid,
            'product_id' => $this->operatorResolver->productIdForOperator($operator),
            'confirmation_number' => (string) ($cashInResponse['confirmation_number'] ?? ''),
        ];

        $confirmResponse = $this->apiClient->confirm($confirmPayload);
        $confirmed = (string) ($confirmResponse['response_code'] ?? '') === '0';

        return new GatewayResult(
            success: $confirmed,
            providerReference: $request->providerReference ?? $request->internalReference,
            providerTransactionId: isset($confirmResponse['transaction_reference'])
                ? (string) $confirmResponse['transaction_reference']
                : null,
            responseCode: is_numeric($confirmResponse['response_code'] ?? null) ? (int) $confirmResponse['response_code'] : null,
            responseMessage: (string) ($confirmResponse['response_message'] ?? ($confirmed ? 'Disbursement confirmed.' : 'Kazang confirmation failed.')),
            normalizedStatus: $confirmed ? 'confirmed' : 'failed',
            rawPayload: [
                'kazang' => [
                    'operator' => $operator,
                    'cash_in' => $cashInResponse,
                    'confirm' => $confirmResponse,
                ],
            ],
        );
    }

    public function queryStatus(PaymentGatewayAttempt $attempt): GatewayStatusResult
    {
        throw new KazangException('Kazang status is delivered via callback, not polling.');
    }

    public function supports(string $paymentMethod, string $direction): bool
    {
        if ($direction === GatewayDirection::Collection->value) {
            return $paymentMethod === GatewayPaymentMethod::MobileMoney->value;
        }

        if ($direction === GatewayDirection::Disbursement->value) {
            return $paymentMethod === GatewayPaymentMethod::MobileMoney->value;
        }

        return false;
    }

    private function normalizeDisbursementReference(string $account): string
    {
        $digits = preg_replace('/\D+/', '', $account) ?? $account;

        if (str_starts_with($digits, '260')) {
            return substr($digits, 3);
        }

        if (str_starts_with($digits, '0')) {
            return substr($digits, 1);
        }

        return $digits;
    }
}
