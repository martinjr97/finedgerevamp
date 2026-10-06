<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePaymentGatewayProductRuleRequest;
use App\Models\LoanProduct;
use App\Models\PaymentGatewayProductRule;
use App\PaymentPlatform\Enums\GatewayRouteKey;
use App\PaymentPlatform\Services\PaymentGatewayRouteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PaymentGatewayProductRuleController extends Controller
{
    public function index(): View
    {
        abort_unless(
            auth('admin')->user()?->can('payment-gateways.view')
            || auth('admin')->user()?->can('payment-gateways.manage'),
            403
        );

        /** @var PaymentGatewayRouteService $routeService */
        $routeService = app(PaymentGatewayRouteService::class);

        $products = LoanProduct::query()
            ->with(['paymentGatewayProductRules.paymentGateway'])
            ->orderBy('name')
            ->get();

        $gatewaysByRoute = [];

        foreach (GatewayRouteKey::adminTableRoutes() as $routeKey) {
            $gatewaysByRoute[$routeKey->value] = $routeService->eligibleGateways($routeKey);
        }

        return view('admin.payment-gateway-product-rules.index', [
            'canManage' => auth('admin')->user()?->can('payment-gateways.manage') ?? false,
            'products' => $products,
            'gatewaysByRoute' => $gatewaysByRoute,
        ]);
    }

    public function update(
        UpdatePaymentGatewayProductRuleRequest $request,
        PaymentGatewayProductRule $paymentGatewayProductRule,
    ): RedirectResponse {
        $data = $request->validated();

        if (! $data['enabled'] || ! $data['payment_gateway_id']) {
            $data['auto_process'] = false;
        }

        if (! $data['enabled']) {
            $data['auto_process'] = false;
        }

        $paymentGatewayProductRule->update([
            'payment_gateway_id' => $data['payment_gateway_id'],
            'enabled' => $data['enabled'],
            'auto_process' => $data['auto_process'],
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()
            ->route('admin.payment-gateway-product-rules.index')
            ->with('status', $paymentGatewayProductRule->loanProduct?->name.' · '.$paymentGatewayProductRule->displayLabel().' saved.');
    }
}
