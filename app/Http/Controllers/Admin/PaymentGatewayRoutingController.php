<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePaymentGatewayRouteRequest;
use App\Models\PaymentGatewayRoute;
use App\PaymentPlatform\Enums\GatewayRouteKey;
use App\PaymentPlatform\Services\PaymentGatewayRouteProvisioner;
use App\PaymentPlatform\Services\PaymentGatewayRouteService;
use App\Support\PaymentGatewayRoutingAdminUi;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PaymentGatewayRoutingController extends Controller
{
    public function index(): View
    {
        abort_unless(
            auth('admin')->user()?->can('payment-gateways.view')
            || auth('admin')->user()?->can('payment-gateways.manage'),
            403
        );

        app(PaymentGatewayRouteProvisioner::class)->sync();

        return view('admin.payment-gateway-routing.index', $this->viewData());
    }

    public function update(
        UpdatePaymentGatewayRouteRequest $request,
        PaymentGatewayRoute $paymentGatewayRoute,
    ): RedirectResponse {
        $data = $request->validated();

        if (! $data['enabled'] || ! $data['payment_gateway_id']) {
            $data['auto_process'] = false;
        }

        if (! $data['enabled']) {
            $data['auto_process'] = false;
        }

        $paymentGatewayRoute->update([
            'payment_gateway_id' => $data['payment_gateway_id'],
            'enabled' => $data['enabled'],
            'auto_process' => $data['auto_process'],
            'fallback_to_manual' => $data['fallback_to_manual'],
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()
            ->route('admin.payment-gateway-routing.index')
            ->with('status', $paymentGatewayRoute->route_key->displayLabel().' routing saved successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function viewData(): array
    {
        /** @var PaymentGatewayRouteService $routeService */
        $routeService = app(PaymentGatewayRouteService::class);

        $routes = PaymentGatewayRoute::query()
            ->with('paymentGateway')
            ->get()
            ->keyBy(fn (PaymentGatewayRoute $route) => $route->route_key->value);

        $tableRows = [];

        foreach (GatewayRouteKey::adminTableRoutes() as $routeKey) {
            $route = $routes->get($routeKey->value);

            if (! $route) {
                $route = PaymentGatewayRoute::query()
                    ->with('paymentGateway')
                    ->where('route_key', $routeKey->value)
                    ->first();
            }

            if (! $route) {
                continue;
            }

            $eligibleGateways = $routeService->eligibleGateways($routeKey);
            $assignedGateway = $route->paymentGateway;

            if ($assignedGateway && ! $eligibleGateways->contains('id', $assignedGateway->id)) {
                $eligibleGateways = $eligibleGateways->prepend($assignedGateway)->values();
            }

            $tableRows[] = [
                'route' => $route,
                'routeKey' => $routeKey,
                'status' => PaymentGatewayRoutingAdminUi::routeStatus($route),
                'eligibleGateways' => $eligibleGateways,
                'gatewayName' => $assignedGateway?->name,
                'linkedAccount' => $assignedGateway?->linkedAccountLabel(),
            ];
        }

        return [
            'canManage' => auth('admin')->user()?->can('payment-gateways.manage') ?? false,
            'tableRows' => $tableRows,
        ];
    }
}
