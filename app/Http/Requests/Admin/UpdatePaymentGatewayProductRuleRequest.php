<?php

namespace App\Http\Requests\Admin;

use App\Models\PaymentGateway;
use App\Models\PaymentGatewayProductRule;
use App\PaymentPlatform\Services\PaymentGatewayRouteService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdatePaymentGatewayProductRuleRequest extends FormRequest
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
            'notes' => $this->input('notes'),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var PaymentGatewayProductRule|null $rule */
            $rule = $this->route('paymentGatewayProductRule');

            if (! $rule) {
                return;
            }

            $enabled = (bool) $this->input('enabled');
            $gatewayId = $this->input('payment_gateway_id');
            $autoProcess = (bool) $this->input('auto_process');

            if ($enabled && ! $gatewayId) {
                $validator->errors()->add(
                    'payment_gateway_id',
                    'A payment gateway is required when this product rule is enabled.',
                );
            }

            if ($autoProcess && (! $enabled || ! $gatewayId)) {
                $validator->errors()->add(
                    'auto_process',
                    'Automatic processing requires the rule to be enabled with a gateway selected.',
                );
            }

            if (! $gatewayId) {
                return;
            }

            $gateway = PaymentGateway::query()->find($gatewayId);
            $routeKey = $rule->routeKey();

            if ($gateway && $routeKey && ! app(PaymentGatewayRouteService::class)->gatewayEligibleForRoute($routeKey, $gateway)) {
                $validator->errors()->add(
                    'payment_gateway_id',
                    'The selected gateway is not eligible for this product rule.',
                );
            }
        });
    }
}
