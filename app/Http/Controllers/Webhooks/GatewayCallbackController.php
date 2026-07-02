<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\PaymentPlatform\Services\GatewayIntegrationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GatewayCallbackController extends Controller
{
    public function handle(Request $request, string $gatewayCode, GatewayIntegrationService $integrationService): Response
    {
        if ($gatewayCode === 'cgrate') {
            return $this->handleCGrateCallback($request, $integrationService);
        }

        return response('Gateway not supported', 404);
    }

    private function handleCGrateCallback(Request $request, GatewayIntegrationService $integrationService): Response
    {
        if (! (bool) config('cgrate.callback.enabled')) {
            return response('Callbacks disabled', 404);
        }

        $expectedToken = (string) config('cgrate.callback.token', '');
        if ($expectedToken === '') {
            return response('Callback token not configured', 503);
        }

        $token = (string) ($request->header('X-CGrate-Callback-Token') ?? $request->input('token', ''));
        if (! hash_equals($expectedToken, $token)) {
            return response('Unauthorized', 401);
        }

        $allowedIps = (array) config('cgrate.callback.allowed_ips', []);
        if ($allowedIps !== [] && ! in_array($request->ip(), $allowedIps, true)) {
            return response('Forbidden', 403);
        }

        $reference = trim((string) (
            $request->input('payment_reference')
            ?? $request->input('paymentReference')
            ?? $request->input('ref')
            ?? ''
        ));

        if ($reference === '') {
            return response('Missing payment reference', 422);
        }

        $processed = $integrationService->processCallback('cgrate', $reference, $request->all());

        return $processed
            ? response('OK', 200)
            : response('Not found', 404);
    }
}
