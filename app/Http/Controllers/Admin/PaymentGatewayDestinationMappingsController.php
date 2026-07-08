<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentGateway;
use App\Models\PaymentGatewayDestinationMapping;
use App\Services\PaymentGatewayDestinationMappingAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaymentGatewayDestinationMappingsController extends Controller
{
    public function __construct(
        private readonly PaymentGatewayDestinationMappingAdminService $adminService,
    ) {}

    public function index(Request $request): View
    {
        abort_unless(
            auth('admin')->user()?->can('payment-gateways.view')
            || auth('admin')->user()?->can('payment-gateways.manage'),
            403
        );

        return view('admin.payment-gateway-destination-mappings.index', $this->adminService->indexData($request));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('payment-gateways.manage'), 403);

        $validated = $this->validateMapping($request);

        $this->adminService->createMapping($validated, auth('admin')->user());

        return redirect()
            ->route('admin.payment-gateway-destination-mappings.index', $this->redirectFilters($validated))
            ->with('status', 'Destination mapping created successfully.');
    }

    public function update(Request $request, PaymentGatewayDestinationMapping $paymentGatewayDestinationMapping): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('payment-gateways.manage'), 403);

        $validated = $this->validateMapping($request, $paymentGatewayDestinationMapping);

        $mapping = $this->adminService->updateMapping(
            $paymentGatewayDestinationMapping,
            $validated,
            auth('admin')->user()
        );

        return redirect()
            ->route('admin.payment-gateway-destination-mappings.index', $this->redirectFilters([
                'payment_gateway_id' => $mapping->payment_gateway_id,
            ]))
            ->with('status', 'Destination mapping updated successfully.');
    }

    public function destroy(PaymentGatewayDestinationMapping $paymentGatewayDestinationMapping): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('payment-gateways.manage'), 403);

        $gatewayId = $paymentGatewayDestinationMapping->payment_gateway_id;
        $paymentGatewayDestinationMapping->load(['paymentGateway', 'financialInstitution', 'channel']);

        $this->adminService->deleteMapping($paymentGatewayDestinationMapping, auth('admin')->user());

        return redirect()
            ->route('admin.payment-gateway-destination-mappings.index', ['gateway_id' => $gatewayId])
            ->with('status', 'Destination mapping deleted successfully.');
    }

    public function syncCgrateIssuers(): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('payment-gateways.manage'), 403);

        $result = $this->adminService->syncCGrateIssuers(auth('admin')->user());

        return redirect()
            ->route('admin.payment-gateway-destination-mappings.index', ['gateway_id' => PaymentGateway::query()->where('code', 'cgrate')->value('id')])
            ->with('status', 'Synced '.count($result['issuers']).' cGrate issuer(s) successfully.');
    }

    /**
     * Legacy nested route — redirect to standalone page with gateway filter.
     */
    public function legacyIndex(PaymentGateway $paymentGateway): RedirectResponse
    {
        abort_unless(
            auth('admin')->user()?->can('payment-gateways.view')
            || auth('admin')->user()?->can('payment-gateways.manage'),
            403
        );

        return redirect()->route('admin.payment-gateway-destination-mappings.index', [
            'gateway_id' => $paymentGateway->id,
        ]);
    }

    /**
     * Legacy nested store — delegate to standalone store.
     */
    public function legacyStore(Request $request, PaymentGateway $paymentGateway): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('payment-gateways.manage'), 403);

        $request->merge(['payment_gateway_id' => $paymentGateway->id]);

        return $this->store($request);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateMapping(Request $request, ?PaymentGatewayDestinationMapping $existing = null): array
    {
        $validated = $request->validate([
            'payment_gateway_id' => [
                $existing ? 'sometimes' : 'required',
                'integer',
                'exists:payment_gateways,id',
            ],
            'destination_type' => ['required', Rule::in(['bank', 'mobile_money'])],
            'gateway_key' => ['required', 'string', 'max:50'],
            'gateway_value' => ['required', 'string', 'max:255'],
            'environment' => ['nullable', 'string', Rule::in(['local', 'uat', 'production'])],
            'status' => ['required', Rule::in(['active', 'inactive', 'verification_required'])],
            'notes' => ['nullable', 'string', 'max:1000'],
            'financial_institution_id' => ['nullable', 'integer', 'min:1', 'exists:financial_institutions,id'],
            'channel_id' => ['nullable', 'integer', 'min:1', 'exists:channels,id'],
        ]);

        if ($existing) {
            $validated['payment_gateway_id'] = $existing->payment_gateway_id;
        }

        if ($validated['destination_type'] === 'bank') {
            if (empty($validated['financial_institution_id'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'financial_institution_id' => 'Please select the FineEdge bank this mapping applies to.',
                ]);
            }
            $validated['channel_id'] = null;
        } else {
            if (empty($validated['channel_id'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'channel_id' => 'Please select the mobile money channel this mapping applies to.',
                ]);
            }
            $validated['financial_institution_id'] = null;
        }

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, int|string|null>
     */
    private function redirectFilters(array $validated): array
    {
        return array_filter([
            'gateway_id' => $validated['payment_gateway_id'] ?? null,
            'environment' => $validated['environment'] ?? null,
            'destination_type' => $validated['destination_type'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }
}
