<?php

namespace App\Http\Requests\Admin;

use App\Models\PaymentGateway;
use App\Models\PaymentGatewayRoute;
use App\PaymentPlatform\Services\PaymentGatewayRouteService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdatePaymentGatewayRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->can('payment-gateways.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'payment_gateway_id' => ['nullable', 'integer', 'exists:payment_gateways,id'],
            'enabled' => ['boolean'],
            'auto_process' => ['boolean'],
            'fallback_to_manual' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'payment_gateway_id' => filled($this->input('payment_gateway_id'))
                ? (int) $this->input('payment_gateway_id')
                : null,
            'enabled' => $this->boolean('enabled'),
            'auto_process' => $this->boolean('auto_process'),
            'fallback_to_manual' => $this->boolean('fallback_to_manual'),
            'notes' => $this->input('notes'),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var PaymentGatewayRoute $route */
            $route = $this->route('paymentGatewayRoute');

            if (! $route) {
                return;
            }

            $enabled = (bool) $this->input('enabled');
            $gatewayId = $this->input('payment_gateway_id');
            $autoProcess = (bool) $this->input('auto_process');

            if ($enabled && ! $gatewayId) {
                $validator->errors()->add(
                    'payment_gateway_id',
                    "A payment gateway is required when {$route->route_key->displayLabel()} is enabled.",
                );
            }

            if ($autoProcess && (! $enabled || ! $gatewayId)) {
                $validator->errors()->add(
                    'auto_process',
                    'Automatic processing requires the route to be enabled with a gateway selected.',
                );
            }

            if (! $gatewayId) {
                return;
            }

            $gateway = PaymentGateway::query()->find($gatewayId);

            if ($gateway && ! app(PaymentGatewayRouteService::class)->gatewayEligibleForRoute($route->route_key, $gateway)) {
                $validator->errors()->add(
                    'payment_gateway_id',
                    "The selected gateway is not eligible for {$route->route_key->displayLabel()}.",
                );
            }
        });
    }
}
