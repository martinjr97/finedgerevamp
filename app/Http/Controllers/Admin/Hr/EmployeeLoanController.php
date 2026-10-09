<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\Employee;
use App\Models\EmployeeBankAccount;
use App\Models\EmployeeLoan;
use App\Models\FinancialInstitution;
use App\Models\LoanPurpose;
use App\Models\WalletProvider;
use App\Models\LoanRate;
use App\Models\Wallet;
use App\Models\PaymentGateway;
use App\Models\PaymentGatewayAttempt;
use App\PaymentPlatform\Enums\GatewayDirection;
use App\PaymentPlatform\Services\GatewayIntegrationService;
use App\PaymentPlatform\Support\CGrateIssuerNameResolver;
use App\Services\Hr\EmployeeLoans\AutomaticEmployeeLoanDisbursementService;
use App\Services\Hr\EmployeeLoans\EmployeeLoanDisbursementService;
use App\Services\Hr\EmployeeLoans\EmployeeLoanNotificationService;
use App\Services\Hr\EmployeeLoans\EmployeeLoanPayoutDestinationService;
use App\Services\Hr\EmployeeLoans\EmployeeLoanPricingService;
use App\Services\Hr\EmployeeLoans\EmployeeLoanRepaymentService;
use App\Services\Hr\EmployeeLoans\EmployeeLoanService;
use App\Services\Hr\EmployeeLoans\EmployeeLoanScheduleService;
use App\Services\Hr\EmployeeLoans\EmployeeLoanSettlementService;
use App\Services\Loans\DTOs\ManualDisbursementDTO;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmployeeLoanController extends Controller
{
    public const CREATE_REVIEW_SESSION = 'employee_loan_create_review';

    public function __construct(
        private readonly EmployeeLoanService $loanService,
        private readonly EmployeeLoanPricingService $pricingService,
        private readonly EmployeeLoanDisbursementService $disbursementService,
        private readonly EmployeeLoanRepaymentService $repaymentService,
        private readonly EmployeeLoanSettlementService $settlementService,
        private readonly EmployeeLoanPayoutDestinationService $payoutDestinationService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', EmployeeLoan::class);

        $query = EmployeeLoan::query()
            ->with(['employee.hrDepartment', 'loanRate.loanRateType', 'creator']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', (int) $request->employee_id);
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('loan_number', 'like', "%{$search}%")
                    ->orWhereHas('employee', function ($eq) use ($search) {
                        $eq->where('employee_number', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }

        $loans = $query->latest('id')->paginate(20)->withQueryString();

        return view('admin.hr.employee-loans.index', [
            'loans' => $loans,
            'employees' => Employee::query()->orderBy('first_name')->get(),
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', EmployeeLoan::class);

        return view('admin.hr.employee-loans.create', array_merge($this->employeeLoanFormOptions(), [
            'formAction' => route('admin.hr.employee-loans.review.store'),
            'cancelHref' => route('admin.hr.employee-loans.index'),
            'formDefaults' => $this->draftFormDefaults(),
        ]));
    }

    public function edit(EmployeeLoan $employeeLoan): View|RedirectResponse
    {
        $this->authorize('update', $employeeLoan);

        if ($employeeLoan->status !== EmployeeLoan::STATUS_DRAFT) {
            return redirect()
                ->route('admin.hr.employee-loans.show', $employeeLoan)
                ->with('status', 'Only draft employee loans can be edited.');
        }

        return view('admin.hr.employee-loans.edit', array_merge($this->employeeLoanFormOptions(), [
            'employeeLoan' => $employeeLoan,
            'formAction' => route('admin.hr.employee-loans.review.update', $employeeLoan),
            'cancelHref' => route('admin.hr.employee-loans.show', $employeeLoan),
            'formDefaults' => $this->draftFormDefaults($employeeLoan),
        ]));
    }

    public function reviewDraft(Request $request, EmployeeLoan $employeeLoan): RedirectResponse
    {
        $this->authorize('update', $employeeLoan);

        if ($employeeLoan->status !== EmployeeLoan::STATUS_DRAFT) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'loan' => 'Only draft employee loans can be edited.',
            ]);
        }

        $payload = $this->buildCreatePayload($request);
        $schedulePreview = app(EmployeeLoanScheduleService::class)->previewFromInput($payload);
        $loanRate = LoanRate::query()->with('loanRateType')->findOrFail($payload['loan_rate_id']);

        session([
            self::CREATE_REVIEW_SESSION => [
                'payload' => $payload,
                'schedule_preview' => $schedulePreview,
                'loan_rate_label' => $loanRate->term_interest_percentage,
                'processing_fee_percentage' => $loanRate->processing_fee_percentage,
                'arrear_rate' => $loanRate->arrear_rate,
                'editing_loan_id' => $employeeLoan->id,
                'expires_at' => now()->addHour()->timestamp,
            ],
        ]);

        return redirect()->route('admin.hr.employee-loans.review');
    }

    public function review(Request $request): RedirectResponse
    {
        $this->authorize('create', EmployeeLoan::class);

        $payload = $this->buildCreatePayload($request);
        $schedulePreview = app(EmployeeLoanScheduleService::class)->previewFromInput($payload);
        $loanRate = LoanRate::query()->with('loanRateType')->findOrFail($payload['loan_rate_id']);

        session([
            self::CREATE_REVIEW_SESSION => [
                'payload' => $payload,
                'schedule_preview' => $schedulePreview,
                'loan_rate_label' => $loanRate->term_interest_percentage,
                'processing_fee_percentage' => $loanRate->processing_fee_percentage,
                'arrear_rate' => $loanRate->arrear_rate,
                'expires_at' => now()->addHour()->timestamp,
            ],
        ]);

        return redirect()->route('admin.hr.employee-loans.review');
    }

    public function showReview(): View|RedirectResponse
    {
        $review = session(self::CREATE_REVIEW_SESSION);
        if (! is_array($review) || ($review['expires_at'] ?? 0) < now()->timestamp) {
            session()->forget(self::CREATE_REVIEW_SESSION);

            return redirect()
                ->route('admin.hr.employee-loans.create')
                ->with('status', 'Your review session expired. Please enter the loan details again.');
        }

        $editingLoan = null;
        if (! empty($review['editing_loan_id'])) {
            $editingLoan = EmployeeLoan::query()->findOrFail($review['editing_loan_id']);
            $this->authorize('update', $editingLoan);
        } else {
            $this->authorize('create', EmployeeLoan::class);
        }

        $payload = $review['payload'];
        $employee = Employee::query()
            ->with(['hrDepartment', 'position'])
            ->findOrFail($payload['employee_id']);

        $canViewPayoutNumbers = auth('admin')->user()?->can('hr.employee-loans.financials') ?? false;
        $payoutSummary = $this->payoutDestinationService->formatSnapshotSummary(
            $payload['disbursement_destination_snapshot'] ?? [],
            ! $canViewPayoutNumbers
        );

        return view('admin.hr.employee-loans.review', [
            'employee' => $employee,
            'payload' => $payload,
            'editingLoan' => $editingLoan,
            'schedulePreview' => $review['schedule_preview'],
            'loanRateLabel' => $review['loan_rate_label'],
            'processingFeePercentage' => $review['processing_fee_percentage'],
            'arrearRate' => $review['arrear_rate'],
            'payoutSummary' => $payoutSummary,
            'canViewPayoutNumbers' => $canViewPayoutNumbers,
        ]);
    }

    public function cancelReview(): RedirectResponse
    {
        session()->forget(self::CREATE_REVIEW_SESSION);

        return redirect()
            ->route('admin.hr.employee-loans.create')
            ->with('status', 'Loan review cancelled.');
    }

    public function confirm(Request $request): RedirectResponse
    {
        $review = session(self::CREATE_REVIEW_SESSION);
        if (! is_array($review) || ($review['expires_at'] ?? 0) < now()->timestamp) {
            session()->forget(self::CREATE_REVIEW_SESSION);

            return redirect()
                ->route('admin.hr.employee-loans.create')
                ->with('status', 'Your review session expired. Please enter the loan details again.');
        }

        $payload = $review['payload'];
        $editingLoanId = $review['editing_loan_id'] ?? null;

        if ($editingLoanId) {
            $loan = EmployeeLoan::query()->findOrFail($editingLoanId);
            $this->authorize('update', $loan);
            $loan = $this->loanService->updateDraft($loan, $payload);
            $statusDraft = 'Draft employee loan updated.';
            $statusSubmitted = 'Employee loan updated and submitted for approval.';
        } else {
            $this->authorize('create', EmployeeLoan::class);
            $loan = $this->loanService->createDraft($payload, auth('admin')->user());
            $statusDraft = 'Employee loan saved as draft.';
            $statusSubmitted = 'Employee loan created and submitted for approval.';
        }

        session()->forget(self::CREATE_REVIEW_SESSION);

        if ($request->boolean('save_as_draft')) {
            return redirect()
                ->route('admin.hr.employee-loans.show', $loan)
                ->with('status', $statusDraft);
        }

        $this->loanService->submitForApproval($loan);

        return redirect()
            ->route('admin.hr.employee-loans.show', $loan)
            ->with('status', $statusSubmitted);
    }

    public function verify(EmployeeLoan $employeeLoan): View|RedirectResponse
    {
        $this->authorize('view', $employeeLoan);

        if ($employeeLoan->status !== EmployeeLoan::STATUS_DRAFT) {
            return redirect()->route('admin.hr.employee-loans.show', $employeeLoan);
        }

        $employeeLoan->load([
            'employee.hrDepartment',
            'employee.position',
            'loanRate.loanRateType',
            'creator',
        ]);

        $schedulePreview = app(EmployeeLoanScheduleService::class)->previewForDraftLoan($employeeLoan);
        $canViewPayoutNumbers = auth('admin')->user()?->can('hr.employee-loans.financials') ?? false;

        return view('admin.hr.employee-loans.review', [
            'loan' => $employeeLoan,
            'employee' => $employeeLoan->employee,
            'payload' => null,
            'schedulePreview' => $schedulePreview,
            'loanRateLabel' => $employeeLoan->quoted_term_rate ?? $employeeLoan->loanRate?->term_interest_percentage,
            'processingFeePercentage' => $employeeLoan->processing_fee_percentage ?? $employeeLoan->loanRate?->processing_fee_percentage,
            'arrearRate' => $employeeLoan->arrear_rate ?? $employeeLoan->loanRate?->arrear_rate,
            'payoutSummary' => $employeeLoan->payoutDestinationSummary(! $canViewPayoutNumbers),
            'canViewPayoutNumbers' => $canViewPayoutNumbers,
        ]);
    }

    public function destroy(EmployeeLoan $employeeLoan): RedirectResponse
    {
        $this->authorize('delete', $employeeLoan);
        $employeeLoan->delete();

        return redirect()
            ->route('admin.hr.employee-loans.index')
            ->with('status', 'Draft employee loan deleted.');
    }

    public function show(EmployeeLoan $employeeLoan): View
    {
        $this->authorize('view', $employeeLoan);

        $employeeLoan->load([
            'employee.hrDepartment',
            'employee.position',
            'employee.primaryBankAccount.financialInstitution',
            'loanRate.loanRateType',
            'paymentSchedules',
            'repayments.processor',
            'creator',
            'approver',
            'disburser',
        ]);

        $settlementQuote = null;
        if (auth('admin')->user()?->can('hr.employee-loans.settle')) {
            $settlementQuote = $this->settlementService->quote($employeeLoan);
        }

        $activeDisbursementGateway = PaymentGateway::query()->where('code', 'cgrate')->first();
        $disbursementGatewayAvailable = $activeDisbursementGateway
            && $activeDisbursementGateway->isAvailableForDisbursement()
            && $activeDisbursementGateway->hasLinkedFinancialAccount();

        $disbursementDestinationPreview = null;
        if ($disbursementGatewayAvailable && $employeeLoan->canDisburseViaGateway()) {
            try {
                $disbursementDestinationPreview = app(CGrateIssuerNameResolver::class)->resolveForEmployeeLoan($employeeLoan);
            } catch (\Throwable) {
                $disbursementDestinationPreview = null;
            }
        }

        $disbursementAttempts = PaymentGatewayAttempt::query()
            ->where('attemptable_type', EmployeeLoan::class)
            ->where('attemptable_id', $employeeLoan->id)
            ->where('direction', GatewayDirection::Disbursement)
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $approvalAutoDisbursementPreview = $employeeLoan->status === EmployeeLoan::STATUS_PENDING_APPROVAL
            ? app(AutomaticEmployeeLoanDisbursementService::class)->previewForApproval($employeeLoan)
            : null;

        $approvalSchedulePreview = $employeeLoan->status === EmployeeLoan::STATUS_PENDING_APPROVAL
            ? app(EmployeeLoanScheduleService::class)->previewForDraftLoan($employeeLoan)
            : null;

        return view('admin.hr.employee-loans.show', [
            'loan' => $employeeLoan,
            'settlementQuote' => $settlementQuote,
            'banks' => Bank::query()->where('is_active', true)->orderBy('name')->get(),
            'wallets' => Wallet::query()->where('is_active', true)->orderBy('name')->get(),
            'canViewFinancials' => auth('admin')->user()?->can('hr.employee-loans.financials') ?? false,
            'activeDisbursementGateway' => $activeDisbursementGateway,
            'disbursementGatewayAvailable' => $disbursementGatewayAvailable,
            'disbursementDestinationPreview' => $disbursementDestinationPreview,
            'disbursementAttempts' => $disbursementAttempts,
            'approvalAutoDisbursementPreview' => $approvalAutoDisbursementPreview,
            'approvalSchedulePreview' => $approvalSchedulePreview,
        ]);
    }

    public function submit(EmployeeLoan $employeeLoan): RedirectResponse
    {
        $this->authorize('update', $employeeLoan);
        $this->loanService->submitForApproval($employeeLoan);

        return redirect()
            ->route('admin.hr.employee-loans.show', $employeeLoan)
            ->with('status', 'Employee loan submitted for approval.');
    }

    public function approve(Request $request, EmployeeLoan $employeeLoan): RedirectResponse
    {
        $this->authorize('approve', $employeeLoan);

        $request->validate([
            'skip_auto_disbursement' => ['nullable', 'boolean'],
        ]);

        $autoPreview = app(AutomaticEmployeeLoanDisbursementService::class)->previewForApproval($employeeLoan);

        $this->loanService->approve($employeeLoan, auth('admin')->user());

        try {
            app(EmployeeLoanNotificationService::class)->sendApproved($employeeLoan->fresh());
        } catch (\Throwable $e) {
            Log::error('Failed to send employee loan approval notification', [
                'employee_loan_id' => $employeeLoan->id,
                'error' => $e->getMessage(),
            ]);
        }

        if (! $autoPreview->autoDisbursementReady) {
            return back()->with(
                'status',
                'Employee loan approved and schedule generated. Record disbursement manually on this page when ready.'
            );
        }

        if ($request->boolean('skip_auto_disbursement')) {
            return back()->with(
                'status',
                'Employee loan approved. Automatic disbursement was skipped — record treasury or cGrate disbursement when ready.'
            );
        }

        $autoDisbursementResult = app(AutomaticEmployeeLoanDisbursementService::class)->handle($employeeLoan->fresh());

        return back()->with(
            $autoDisbursementResult->flashSessionKey(),
            str_replace('Loan ', 'Employee loan ', $autoDisbursementResult->userFlashMessage())
        );
    }

    public function reject(Request $request, EmployeeLoan $employeeLoan): RedirectResponse
    {
        $this->authorize('approve', $employeeLoan);
        $validated = $request->validate(['rejection_reason' => ['required', 'string', 'max:2000']]);
        $this->loanService->reject($employeeLoan, auth('admin')->user(), $validated['rejection_reason']);

        return back()->with('status', 'Employee loan rejected.');
    }

    public function cancel(Request $request, EmployeeLoan $employeeLoan): RedirectResponse
    {
        $this->authorize('update', $employeeLoan);
        $this->loanService->cancel($employeeLoan, auth('admin')->user(), $request->string('cancellation_reason')->toString() ?: null);

        return back()->with('status', 'Employee loan cancelled.');
    }

    public function disburse(Request $request, EmployeeLoan $employeeLoan): RedirectResponse
    {
        $this->authorize('disburse', $employeeLoan);

        $validated = $request->validate([
            'source_type' => ['required', 'in:bank,wallet'],
            'source_id' => ['required', 'integer'],
            'reference_number' => ['required', 'string', 'max:100'],
            'disbursement_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:500'],
            'external_already_paid' => ['nullable', 'boolean'],
        ]);

        $snapshot = $employeeLoan->disbursement_destination_snapshot;
        if (empty($snapshot)) {
            $employee = $employeeLoan->employee()->with('primaryBankAccount.financialInstitution')->first();
            $snapshot = $employee?->primaryBankAccount
                ? $this->payoutDestinationService->snapshotFromEmployeeAccount($employee->primaryBankAccount)
                : null;
        }

        try {
            $loan = $this->disbursementService->completeManualDisbursement(
                $employeeLoan,
                new ManualDisbursementDTO(
                    sourceType: $validated['source_type'],
                    sourceId: (int) $validated['source_id'],
                    referenceNumber: $validated['reference_number'],
                    disbursementDate: Carbon::parse($validated['disbursement_date']),
                    description: $validated['description'] ?? null,
                ),
                auth('admin')->user(),
                $request->boolean('external_already_paid'),
                $snapshot,
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        try {
            app(EmployeeLoanNotificationService::class)->sendDisbursed($loan);
        } catch (\Throwable $e) {
            Log::error('Failed to send employee loan disbursement notification', [
                'employee_loan_id' => $employeeLoan->id,
                'error' => $e->getMessage(),
            ]);
        }

        $message = $request->boolean('external_already_paid')
            ? 'Treasury disbursement recorded (payout was already made outside the system).'
            : 'Employee loan disbursement recorded successfully.';

        return back()->with('status', $message);
    }

    public function disburseGateway(EmployeeLoan $employeeLoan): RedirectResponse
    {
        $this->authorize('disburse', $employeeLoan);

        $result = app(GatewayIntegrationService::class)->initiateEmployeeLoanDisbursement($employeeLoan);

        if (! ($result['success'] ?? false)) {
            return back()->with('error', $result['message'] ?? 'Failed to initiate gateway disbursement.');
        }

        return back()->with('status', $result['message'] ?? 'Gateway disbursement initiated.');
    }

    public function repay(Request $request, EmployeeLoan $employeeLoan): RedirectResponse
    {
        $this->authorize('repay', $employeeLoan);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'effective_date' => ['required', 'date'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'repayment_source' => ['nullable', 'in:manual,payroll,bank,cash,adjustment'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'received_via_type' => ['nullable', 'in:bank,wallet'],
            'received_via_id' => ['nullable', 'integer'],
        ]);

        $this->repaymentService->recordRepayment($employeeLoan, $validated, auth('admin')->user());

        return back()->with('status', 'Repayment recorded.');
    }

    public function settle(Request $request, EmployeeLoan $employeeLoan): RedirectResponse
    {
        $this->authorize('settle', $employeeLoan);

        $validated = $request->validate([
            'effective_date' => ['required', 'date'],
            'received_via_type' => ['nullable', 'in:bank,wallet'],
            'received_via_id' => ['nullable', 'integer'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        $quote = $this->settlementService->quote($employeeLoan);
        $validated['amount'] = $quote['settlement_total'];

        $this->settlementService->settle($employeeLoan, $validated, auth('admin')->user());

        return back()->with('status', 'Employee loan settled.');
    }

    public function pricingPreview(Request $request): JsonResponse
    {
        $this->authorize('create', EmployeeLoan::class);

        $validated = $request->validate([
            'loan_rate_id' => ['nullable', 'exists:loan_rates,id'],
            'principal_amount' => ['required', 'numeric', 'min:1'],
            'tenure_months' => ['required', 'integer', 'min:1', 'max:120'],
            'first_payment_date' => ['nullable', 'date'],
        ]);

        $loanRate = $this->pricingService->resolveRateForTenureAndPrincipal(
            (int) $validated['tenure_months'],
            (float) $validated['principal_amount'],
        );

        $preview = $this->pricingService->preview([
            'loan_rate_id' => $loanRate->id,
            'principal' => (float) $validated['principal_amount'],
            'tenure_months' => (int) $validated['tenure_months'],
        ]);

        return response()->json(array_merge($preview, [
            'loan_rate_id' => $loanRate->id,
            'applied_rate' => [
                'id' => $loanRate->id,
                'tenure_months' => $loanRate->tenure_months,
                'term_interest_percentage' => $loanRate->term_interest_percentage,
                'processing_fee_percentage' => $loanRate->processing_fee_percentage,
                'arrear_rate' => $loanRate->arrear_rate,
            ],
        ]));
    }

    public function employeePaymentAccounts(Request $request): JsonResponse
    {
        $this->authorize('create', EmployeeLoan::class);

        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
        ]);

        $canViewFullNumbers = auth('admin')->user()?->can('hr.employee-loans.financials') ?? false;

        $accounts = EmployeeBankAccount::query()
            ->where('employee_id', $validated['employee_id'])
            ->where('is_active', true)
            ->with('financialInstitution')
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->get()
            ->map(function (EmployeeBankAccount $account) use ($canViewFullNumbers) {
                $typeLabel = str_replace('_', ' ', $account->account_type ?? 'bank');
                $bank = $account->financialInstitution?->name ?? $account->bank_name ?? 'Account';
                $number = $canViewFullNumbers ? $account->account_number : $account->maskedAccountNumber();
                $primary = $account->is_primary ? ' · Primary' : '';

                return [
                    'id' => $account->id,
                    'label' => ucfirst($typeLabel)." · {$account->account_name} · {$bank} · {$number}{$primary}",
                    'is_primary' => (bool) $account->is_primary,
                ];
            })
            ->values();

        return response()->json(['accounts' => $accounts]);
    }

    /**
     * @return array<int, string>
     */
    protected function mobileMoneyProviderNames(): array
    {
        $walletProviders = WalletProvider::query()->where('is_active', true)->orderBy('name')->pluck('name')->all();

        return $walletProviders !== []
            ? $walletProviders
            : ['MTN Mobile Money', 'Airtel Money', 'Zamtel Kwacha'];
    }

    /**
     * @return array<string, string>
     */
    protected function statusOptions(): array
    {
        return [
            EmployeeLoan::STATUS_DRAFT => 'Draft',
            EmployeeLoan::STATUS_PENDING_APPROVAL => 'Pending Approval',
            EmployeeLoan::STATUS_APPROVED => 'Approved',
            EmployeeLoan::STATUS_ACTIVE => 'Active',
            EmployeeLoan::STATUS_SETTLED => 'Settled',
            EmployeeLoan::STATUS_REJECTED => 'Rejected',
            EmployeeLoan::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    protected function employeeRates()
    {
        return LoanRate::query()
            ->whereHas('loanRateType', fn ($q) => $q->where('code', EmployeeLoanPricingService::EMPLOYEE_RATE_TYPE_CODE))
            ->where('is_active', true)
            ->orderBy('tenure_months')
            ->with('loanRateType')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>
     */
    protected function employeeLoanFormOptions(): array
    {
        return [
            'employees' => Employee::query()->with(['hrDepartment', 'position', 'currentCompensation', 'activeContract.contractType'])->orderBy('first_name')->get(),
            'rates' => $this->employeeRates(),
            'loanPurposes' => LoanPurpose::orderedActive(),
            'financialInstitutions' => FinancialInstitution::query()->active()->orderBy('name')->get(),
            'mobileMoneyProviders' => $this->mobileMoneyProviderNames(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function draftFormDefaults(?EmployeeLoan $loan = null): array
    {
        if ($loan === null) {
            return [];
        }

        $metadata = $loan->metadata ?? [];
        $snapshot = $loan->disbursement_destination_snapshot ?? [];

        $defaults = [
            'employee_id' => (string) $loan->employee_id,
            'principal_amount' => (string) $loan->principal_amount,
            'tenure_months' => (string) $loan->tenure_months,
            'repayment_frequency' => $loan->repayment_frequency ?? 'monthly',
            'first_payment_date' => $loan->first_payment_date?->format('Y-m-d') ?? '',
            'loan_purpose_id' => (string) ($metadata['loan_purpose_id'] ?? ''),
            'notes' => $loan->notes ?? '',
            'loan_rate_id' => (string) $loan->loan_rate_id,
            'payout_destination_mode' => 'alternative',
            'payout_method' => 'bank',
            'payout_employee_bank_account_id' => '',
            'payout_financial_institution_id' => '',
            'payout_bank_name' => '',
            'payout_branch_name' => '',
            'payout_account_name' => '',
            'payout_account_number' => '',
            'payout_mobile_money_provider' => '',
            'payout_mobile_account_name' => '',
            'payout_mobile_number' => '',
        ];

        if (($snapshot['source'] ?? '') === 'employee_account') {
            $defaults['payout_destination_mode'] = 'employee_account';
            $defaults['payout_employee_bank_account_id'] = (string) ($snapshot['employee_bank_account_id'] ?? '');
        } elseif (($snapshot['source'] ?? '') === 'alternative') {
            $defaults['payout_destination_mode'] = 'alternative';
            $defaults['payout_method'] = ($snapshot['payout_method'] ?? 'bank') === 'mobile_money' ? 'mobile_money' : 'bank';
            if ($defaults['payout_method'] === 'mobile_money') {
                $defaults['payout_mobile_money_provider'] = $snapshot['bank_name'] ?? '';
                $defaults['payout_mobile_account_name'] = $snapshot['account_name'] ?? '';
                $defaults['payout_mobile_number'] = $snapshot['account_number'] ?? '';
            } else {
                $defaults['payout_financial_institution_id'] = (string) ($snapshot['financial_institution_id'] ?? '');
                $defaults['payout_bank_name'] = $snapshot['bank_name'] ?? '';
                $defaults['payout_branch_name'] = $snapshot['branch_name'] ?? '';
                $defaults['payout_account_name'] = $snapshot['account_name'] ?? '';
                $defaults['payout_account_number'] = $snapshot['account_number'] ?? '';
            }
        }

        return $defaults;
    }

    protected function buildCreatePayload(Request $request): array
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'loan_rate_id' => ['nullable', 'exists:loan_rates,id'],
            'principal_amount' => ['required', 'numeric', 'min:1'],
            'tenure_months' => ['required', 'integer', 'min:1', 'max:120'],
            'repayment_frequency' => ['required', 'in:monthly,weekly'],
            'first_payment_date' => ['required', 'date'],
            'application_date' => ['nullable', 'date'],
            'loan_purpose_id' => ['required', Rule::exists('loan_purposes', 'id')->where('is_active', true)],
            'notes' => ['nullable', 'string'],
        ]);

        $loanRate = $this->pricingService->resolveRateForTenureAndPrincipal(
            (int) $validated['tenure_months'],
            (float) $validated['principal_amount'],
        );

        if (! empty($validated['loan_rate_id']) && (int) $validated['loan_rate_id'] !== $loanRate->id) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'tenure_months' => 'Selected tenure does not match an Employee Rate for this principal amount.',
            ]);
        }

        $validated['loan_rate_id'] = $loanRate->id;
        $validated['loan_purpose_id'] = (int) $validated['loan_purpose_id'];

        $loanPurpose = LoanPurpose::query()->active()->findOrFail($validated['loan_purpose_id']);
        $validated['purpose'] = $loanPurpose->name;

        $employee = Employee::query()->findOrFail($validated['employee_id']);
        $validated['disbursement_destination_snapshot'] = $this->payoutDestinationService->validateAndBuild(
            $request->all(),
            $employee
        );

        if (empty($validated['application_date'])) {
            $validated['application_date'] = now()->toDateString();
        }

        return $validated;
    }
}
