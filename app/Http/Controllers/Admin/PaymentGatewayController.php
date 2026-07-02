<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\PaymentGateway;
use App\Models\PaymentGatewayAttempt;
use App\Models\PaymentGatewayLog;
use App\Models\Wallet;
use App\PaymentPlatform\Enums\FinancialAccountType;
use App\PaymentPlatform\Enums\GatewayAttemptStatus;
use App\PaymentPlatform\Enums\PaymentGatewayStatus;
use App\Support\PaymentGatewayAdminUi;
use App\Support\PaymentGatewayRoutingAdminUi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaymentGatewayController extends Controller
{
    public function index(): View
    {
        abort_unless(
            auth('admin')->user()?->can('payment-gateways.view')
            || auth('admin')->user()?->can('payment-gateways.manage'),
            403
        );

        $gateways = PaymentGateway::query()
            ->orderBy('priority')
            ->orderBy('name')
            ->get();

        return view('admin.payment-gateways.index', compact('gateways'));
    }

    public function show(PaymentGateway $paymentGateway): View
    {
        abort_unless(
            auth('admin')->user()?->can('payment-gateways.view')
            || auth('admin')->user()?->can('payment-gateways.manage'),
            403
        );

        $linkedAccount = $paymentGateway->linkedFinancialAccount();
        $recentAttempts = PaymentGatewayAttempt::query()
            ->where('payment_gateway_id', $paymentGateway->id)
            ->with('attemptable')
            ->latest()
            ->limit(10)
            ->get();

        $recentFailures = PaymentGatewayAttempt::query()
            ->where('payment_gateway_id', $paymentGateway->id)
            ->whereIn('status', [GatewayAttemptStatus::Failed, GatewayAttemptStatus::Cancelled])
            ->latest()
            ->limit(5)
            ->get();

        $recentSuccesses = PaymentGatewayAttempt::query()
            ->where('payment_gateway_id', $paymentGateway->id)
            ->whereIn('status', [GatewayAttemptStatus::Confirmed, GatewayAttemptStatus::Completed])
            ->latest('confirmed_at')
            ->limit(5)
            ->get();

        $recentLogs = PaymentGatewayLog::query()
            ->where('payment_gateway_id', $paymentGateway->id)
            ->latest()
            ->limit(10)
            ->get();

        return view('admin.payment-gateways.show', [
            'gateway' => $paymentGateway,
            'recentAttempts' => $recentAttempts,
            'recentFailures' => $recentFailures,
            'recentSuccesses' => $recentSuccesses,
            'recentLogs' => $recentLogs,
            'linkedAccount' => $linkedAccount,
            'linkedBalance' => $paymentGateway->linkedAccountBalance(),
            'linkedLabel' => $paymentGateway->linkedAccountLabel(),
            'financialAccountUrl' => PaymentGatewayAdminUi::financialAccountShowUrl($paymentGateway),
            'financialAccountTypeLabel' => PaymentGatewayAdminUi::financialAccountTypeLabel($paymentGateway),
            'capabilityBadges' => PaymentGatewayRoutingAdminUi::capabilityBadges($paymentGateway),
            'operationalChecks' => PaymentGatewayRoutingAdminUi::operationalChecks($paymentGateway),
            'healthIndicator' => PaymentGatewayRoutingAdminUi::gatewayHealth($paymentGateway),
        ]);
    }

    public function edit(PaymentGateway $paymentGateway): View
    {
        abort_unless(auth('admin')->user()?->can('payment-gateways.manage'), 403);

        $banks = Bank::query()->where('is_active', true)->orderBy('name')->get();
        $wallets = Wallet::query()->where('is_active', true)->orderBy('name')->get();

        return view('admin.payment-gateways.edit', [
            'gateway' => $paymentGateway,
            'banks' => $banks,
            'wallets' => $wallets,
            'linkedBalance' => $paymentGateway->linkedAccountBalance(),
            'statusDescriptions' => PaymentGatewayAdminUi::statusDescriptions(),
        ]);
    }

    public function update(Request $request, PaymentGateway $paymentGateway): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('payment-gateways.manage'), 403);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(PaymentGatewayStatus::class)],
            'priority' => ['required', 'integer', 'min:1', 'max:9999'],
            'is_default' => ['boolean'],
            'supports_collections' => ['boolean'],
            'supports_disbursements' => ['boolean'],
            'financial_account_type' => ['nullable', Rule::enum(FinancialAccountType::class)],
            'financial_account_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $accountType = $validated['financial_account_type'] ?? null;
        $accountId = $validated['financial_account_id'] ?? null;

        if ($accountType && $accountId) {
            $exists = match ($accountType) {
                FinancialAccountType::Bank->value => Bank::query()->where('id', $accountId)->exists(),
                FinancialAccountType::Wallet->value => Wallet::query()->where('id', $accountId)->exists(),
                default => false,
            };

            if (! $exists) {
                return back()->withInput()->withErrors([
                    'financial_account_id' => 'Selected financial account does not exist.',
                ]);
            }
        } else {
            $accountType = null;
            $accountId = null;
        }

        if ($request->boolean('is_default')) {
            PaymentGateway::query()
                ->where('id', '!=', $paymentGateway->id)
                ->update(['is_default' => false]);
        }

        $paymentGateway->update([
            'status' => $validated['status'],
            'priority' => $validated['priority'],
            'is_default' => $request->boolean('is_default'),
            'supports_collections' => $request->boolean('supports_collections'),
            'supports_disbursements' => $request->boolean('supports_disbursements'),
            'financial_account_type' => $accountType,
            'financial_account_id' => $accountId,
        ]);

        return redirect()
            ->route('admin.payment-gateways.show', $paymentGateway)
            ->with('status', 'Payment gateway updated successfully.');
    }
}
