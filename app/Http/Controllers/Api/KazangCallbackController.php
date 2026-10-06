<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentGateway;
use App\Models\PaymentGatewayAttempt;
use App\Models\Repayment;
use App\PaymentPlatform\DTOs\GatewayStatusResult;
use App\PaymentPlatform\Enums\GatewayAttemptStatus;
use App\PaymentPlatform\Enums\GatewayDirection;
use App\PaymentPlatform\Services\GatewayIntegrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class KazangCallbackController extends Controller
{
    public function __invoke(Request $request, GatewayIntegrationService $integrationService): JsonResponse
    {
        if (! (bool) config('kazang.enabled')) {
            return $this->legacyResponse('285', 'Invalid Credentials');
        }

        $username = (string) $request->header('username', '');
        $password = (string) $request->header('password', '');

        if ($username === '' || $password === '') {
            Log::channel('stack')->info('Kazang callback rejected: missing credentials');

            return $this->legacyResponse('285', 'Invalid Credentials');
        }

        $expectedUsername = (string) config('kazang.callback.username');
        $expectedPassword = (string) config('kazang.callback.password');

        if ($expectedUsername === '' || $expectedPassword === ''
            || ! hash_equals($expectedUsername, $username)
            || ! hash_equals($expectedPassword, $password)) {
            Log::channel('stack')->info('Kazang callback rejected: invalid credentials');

            return $this->legacyResponse('285', 'Invalid Credentials');
        }

        $payload = $request->json()->all() ?: $request->all();
        $transactionId = $payload['transaction_id'] ?? null;
        $transactionStatus = $payload['transaction_status'] ?? null;

        if (! is_numeric($transactionId) || $transactionStatus === null || $transactionStatus === '') {
            return $this->legacyResponse('284', 'Invalid Payload');
        }

        $repayment = Repayment::query()->find((int) $transactionId);

        if (! $repayment) {
            Log::channel('stack')->info('Kazang callback: repayment not found', [
                'transaction_id' => $transactionId,
            ]);

            return $this->legacyResponse('280', 'transaction not found');
        }

        $gateway = PaymentGateway::query()->where('code', 'kazang')->first();
        $attempt = PaymentGatewayAttempt::query()
            ->where('attemptable_type', Repayment::class)
            ->where('attemptable_id', $repayment->id)
            ->where('direction', GatewayDirection::Collection)
            ->when($gateway, fn ($query) => $query->where('payment_gateway_id', $gateway->id))
            ->latest('id')
            ->first();

        if (! $attempt) {
            Log::channel('stack')->warning('Kazang callback: no gateway attempt found', [
                'repayment_id' => $repayment->id,
            ]);

            return $this->legacyResponse('280', 'transaction not found');
        }

        $successStatus = (string) config('kazang.callback.success_status', '301');
        $confirmed = (string) $transactionStatus === $successStatus;

        $attempt->update([
            'callback_payload' => $payload,
            'provider_transaction_id' => isset($payload['supplier_transaction_id'])
                ? (string) $payload['supplier_transaction_id']
                : $attempt->provider_transaction_id,
            'response_message' => isset($payload['response_message'])
                ? (string) $payload['response_message']
                : $attempt->response_message,
        ]);

        if ($confirmed) {
            if ($attempt->status !== GatewayAttemptStatus::Confirmed) {
                $integrationService->handleStatusResult($attempt->fresh(), new GatewayStatusResult(
                    normalizedStatus: 'confirmed',
                    providerTransactionId: isset($payload['supplier_transaction_id'])
                        ? (string) $payload['supplier_transaction_id']
                        : $attempt->provider_transaction_id,
                    responseCode: is_numeric($transactionStatus) ? (int) $transactionStatus : null,
                    responseMessage: (string) ($payload['response_message'] ?? 'Payment confirmed via Kazang callback.'),
                    rawPayload: ['kazang_callback' => $payload],
                ));
            }

            return $this->legacyResponse('202', 'transaction updated as success');
        }

        if (! $attempt->isTerminal()) {
            $integrationService->handleStatusResult($attempt->fresh(), new GatewayStatusResult(
                normalizedStatus: 'failed',
                providerTransactionId: isset($payload['supplier_transaction_id'])
                    ? (string) $payload['supplier_transaction_id']
                    : $attempt->provider_transaction_id,
                responseCode: is_numeric($transactionStatus) ? (int) $transactionStatus : null,
                responseMessage: (string) ($payload['response_message'] ?? 'Payment failed via Kazang callback.'),
                rawPayload: ['kazang_callback' => $payload],
            ));
        }

        return $this->legacyResponse('202', 'transaction updated as failure');
    }

    private function legacyResponse(string $statusCode, string $statusMessage): JsonResponse
    {
        return response()->json([
            'status_code' => $statusCode,
            'status_message' => $statusMessage,
        ]);
    }
}
