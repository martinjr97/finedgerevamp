<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\ZambianPhoneRules;
use App\Models\Bank;
use App\Models\Loan;
use App\Models\Customer;
use App\Models\LoanProduct;
use App\Models\CustomerGroup;
use App\Models\Repayment;
use App\Models\LoanRepayment;
use App\Models\LoanExtension;
use App\Models\Channel;
use App\Models\FinancialInstitution;
use App\Models\Wallet;
use App\Services\CustomerNotificationService;
use App\Http\Requests\Admin\StoreLoanRefundRequest;
use App\Services\LoanExtensionService;
use App\Services\LoanPaymentDetailsService;
use App\Services\LoanRepaymentLedgerService;
use App\Services\SharedPaymentDetailsDetectionService;
use App\Services\LoanRepaymentRefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Barryvdh\DomPDF\Facade\Pdf;

class LoanController extends Controller
{
    public function __construct(
        private readonly CustomerNotificationService $customerNotificationService,
        private readonly LoanExtensionService $loanExtensionService,
        private readonly LoanPaymentDetailsService $loanPaymentDetailsService
    )
    {
    }

    public function index(Request $request): View
    {
        $admin = auth('admin')->user();
        $companyFilterId = $admin->getCompanyFilterId();
        
        $query = Loan::with(['customer', 'loanProduct', 'customerGroup', 'channel', 'approver']);

        // Filter by company if not primary company admin
        if ($companyFilterId !== null) {
            $query->whereHas('customer', function ($q) use ($companyFilterId) {
                $q->where('company_id', $companyFilterId);
            });
        }

        // Filters
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('disbursement_status') && $request->disbursement_status) {
            $query->where('disbursement_status', $request->disbursement_status);
        }

        if ($request->has('loan_product_id') && $request->loan_product_id) {
            $query->where('loan_product_id', $request->loan_product_id);
        }

        if ($request->has('customer_group_id') && $request->customer_group_id) {
            $query->where('customer_group_id', $request->customer_group_id);
        }

        if ($request->has('customer_id') && $request->customer_id) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->has('accrual_type') && $request->accrual_type) {
            $query->where('accrual_type', $request->accrual_type);
        }

        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('loan_start_date', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('loan_start_date', '<=', $request->date_to);
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('loan_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        $loans = $query->latest('loan_start_date')->paginate(20);

        // Get filter options (also filtered by company if needed)
        $loanProductsQuery = LoanProduct::where('is_active', true);
        $customerGroupsQuery = CustomerGroup::where('is_active', true)->with('loanProduct');
        $customersQuery = Customer::query();
        
        if ($companyFilterId !== null) {
            $loanProductsQuery->where('company_id', $companyFilterId);
            $customerGroupsQuery->whereHas('loanProduct', function ($q) use ($companyFilterId) {
                $q->where('company_id', $companyFilterId);
            });
            $customersQuery->where('company_id', $companyFilterId);
        }
        
        $loanProducts = $loanProductsQuery->orderBy('name')->get();
        $customerGroups = $customerGroupsQuery->orderBy('name')->get();
        $customers = $customersQuery->orderBy('first_name')->orderBy('last_name')->get();

        return view('admin.loans.index', compact('loans', 'loanProducts', 'customerGroups', 'customers'));
    }

    public function export(Request $request)
    {
        $admin = auth('admin')->user();
        $companyFilterId = $admin->getCompanyFilterId();
        
        $query = Loan::with([
            'customer',
            'loanProduct',
            'customerGroup',
            'channel',
            'disbursementFinancialInstitution',
            'disbursementFinancialInstitutionBranch',
        ]);

        // Filter by company if not primary company admin
        if ($companyFilterId !== null) {
            $query->whereHas('customer', function ($q) use ($companyFilterId) {
                $q->where('company_id', $companyFilterId);
            });
        }

        // Apply same filters as index
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('disbursement_status') && $request->disbursement_status) {
            $query->where('disbursement_status', $request->disbursement_status);
        }

        if ($request->has('loan_product_id') && $request->loan_product_id) {
            $query->where('loan_product_id', $request->loan_product_id);
        }

        if ($request->has('customer_group_id') && $request->customer_group_id) {
            $query->where('customer_group_id', $request->customer_group_id);
        }

        if ($request->has('customer_id') && $request->customer_id) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->has('accrual_type') && $request->accrual_type) {
            $query->where('accrual_type', $request->accrual_type);
        }

        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('loan_start_date', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('loan_start_date', '<=', $request->date_to);
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('loan_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        $loans = $query->latest('loan_start_date')->get();

        $exportData = $loans->map(function ($loan) {
            return array_merge([
                'Loan Number' => $loan->loan_number,
                'Customer Name' => $loan->customer->full_name ?? 'N/A',
                'Customer Email' => $loan->customer->email ?? 'N/A',
                'Customer Phone' => $loan->customer->phone ?? 'N/A',
                'Product' => $loan->loanProduct->name ?? 'N/A',
                'Customer Group' => $loan->customerGroup->name ?? 'N/A',
                'Principal Amount (ZMW)' => number_format($loan->principal_amount, 2),
                'Processing Fee (ZMW)' => number_format($loan->processing_fee, 2),
                'Interest Accrued (ZMW)' => number_format($loan->interest_accrued, 2),
                'Booked Loan Total (ZMW)' => number_format($loan->total_amount, 2),
                'Projected Repayment Total (ZMW)' => number_format($loan->getProjectedTotalAmount(), 2),
                'Amount Paid (ZMW)' => number_format($loan->amount_paid, 2),
                'Booked Outstanding Balance (ZMW)' => number_format($loan->outstanding_balance, 2),
                'Tenure (Months)' => $loan->tenure_months,
                'Start Date' => $loan->loan_start_date->format('Y-m-d'),
                'End Date' => $loan->loan_end_date->format('Y-m-d'),
                'Status' => ucfirst(str_replace('_', ' ', $loan->status)),
                'Accrual Type' => ucfirst(str_replace('_', ' ', $loan->accrual_type)),
                'Disbursement Status' => ucfirst($loan->disbursement_status),
            ], $loan->disbursementDestinationExportColumns(), [
                'Created At' => $loan->created_at->format('Y-m-d H:i:s'),
            ]);
        });

        $filename = 'loans-export-' . now()->format('Y-m-d_His') . '.xlsx';

        return Excel::download(new class($exportData) implements FromCollection, WithHeadings, WithColumnWidths, WithStyles {
            protected $data;

            public function __construct($data)
            {
                $this->data = $data;
            }

            public function collection()
            {
                return collect($this->data)->map(function ($row) {
                    return array_values($row);
                });
            }

            public function headings(): array
            {
                $first = $this->data->first();

                return $first ? array_keys($first) : [];
            }

            public function columnWidths(): array
            {
                return [
                    'A' => 18, 'B' => 25, 'C' => 30, 'D' => 18, 'E' => 20,
                    'F' => 20, 'G' => 20, 'H' => 18, 'I' => 20, 'J' => 18,
                    'K' => 18, 'L' => 22, 'M' => 15, 'N' => 12, 'O' => 12,
                    'P' => 18, 'Q' => 15, 'R' => 20, 'S' => 15, 'T' => 20,
                ];
            }

            public function styles(Worksheet $sheet)
            {
                return [
                    1 => [
                        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => '0ea5e9'], // cyan-500
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER,
                        ],
                    ],
                ];
            }
        }, $filename);
    }

    public function todaysPayments(Request $request): View
    {
        $admin = auth('admin')->user();
        $companyFilterId = $admin->getCompanyFilterId();
        $today = Carbon::today();
        
        // Get loans that have payment schedules due today
        $query = Loan::with(['customer', 'loanProduct', 'customerGroup', 'channel'])
            ->whereHas('paymentSchedules', function ($q) use ($today) {
                $q->whereDate('due_date', $today);
            });

        // Filter by company if not primary company admin
        if ($companyFilterId !== null) {
            $query->whereHas('customer', function ($q) use ($companyFilterId) {
                $q->where('company_id', $companyFilterId);
            });
        }

        // Apply search filter if provided
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('loan_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        $loans = $query->latest('loan_start_date')->paginate(20);

        // Load today's payment schedules for each loan
        $loans->getCollection()->transform(function ($loan) use ($today) {
            $loan->todays_schedule = $loan->paymentSchedules()
                ->whereDate('due_date', $today)
                ->first();
            return $loan;
        });

        return view('admin.loans.todays-payments', compact('loans', 'today'));
    }

    public function exportTodaysPayments(Request $request)
    {
        $admin = auth('admin')->user();
        $companyFilterId = $admin->getCompanyFilterId();
        $today = Carbon::today();
        
        // Get loans that have payment schedules due today
        $query = Loan::with(['customer', 'loanProduct', 'customerGroup', 'channel', 'paymentSchedules'])
            ->whereHas('paymentSchedules', function ($q) use ($today) {
                $q->whereDate('due_date', $today);
            });

        // Filter by company if not primary company admin
        if ($companyFilterId !== null) {
            $query->whereHas('customer', function ($q) use ($companyFilterId) {
                $q->where('company_id', $companyFilterId);
            });
        }

        // Apply search filter if provided
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('loan_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        $loans = $query->latest('loan_start_date')->get();

        $exportData = $loans->map(function ($loan) use ($today) {
            $todaysSchedule = $loan->paymentSchedules()
                ->whereDate('due_date', $today)
                ->first();
            
            return [
                'Loan Number' => $loan->loan_number,
                'Customer Name' => $loan->customer->full_name ?? 'N/A',
                'Customer Email' => $loan->customer->email ?? 'N/A',
                'Customer Phone' => $loan->customer->phone ?? 'N/A',
                'Product' => $loan->loanProduct->name ?? 'N/A',
                'Customer Group' => $loan->customerGroup->name ?? 'N/A',
                'Principal Amount (ZMW)' => number_format($loan->principal_amount, 2),
                'Booked Loan Total (ZMW)' => number_format($loan->total_amount, 2),
                'Projected Repayment Total (ZMW)' => number_format($loan->getProjectedTotalAmount(), 2),
                'Booked Outstanding Balance (ZMW)' => number_format($loan->outstanding_balance, 2),
                'Due Date' => $today->format('Y-m-d'),
                'Expected Amount (ZMW)' => $todaysSchedule ? number_format($todaysSchedule->expected_amount, 2) : '0.00',
                'Amount Paid (ZMW)' => $todaysSchedule ? number_format($todaysSchedule->amount_paid, 2) : '0.00',
                'Remaining Amount (ZMW)' => $todaysSchedule ? number_format($todaysSchedule->remaining_amount, 2) : '0.00',
                'Payment Status' => $todaysSchedule ? ucfirst(str_replace('_', ' ', $todaysSchedule->status)) : 'N/A',
                'Period Number' => $todaysSchedule ? $todaysSchedule->period_number : 'N/A',
                'Tenure (Months)' => $loan->tenure_months,
                'Loan Status' => ucfirst(str_replace('_', ' ', $loan->status)),
            ];
        });

        $filename = 'todays-payments-' . $today->format('Y-m-d') . '.xlsx';

        return Excel::download(new class($exportData) implements FromCollection, WithHeadings, WithColumnWidths, WithStyles {
            protected $data;

            public function __construct($data)
            {
                $this->data = $data;
            }

            public function collection()
            {
                return collect($this->data)->map(function ($row) {
                    return array_values($row);
                });
            }

            public function headings(): array
            {
                $first = $this->data->first();

                return $first ? array_keys($first) : [
                    'Loan Number',
                    'Customer Name',
                    'Customer Email',
                    'Customer Phone',
                    'Product',
                    'Customer Group',
                    'Principal Amount (ZMW)',
                    'Booked Loan Total (ZMW)',
                    'Projected Repayment Total (ZMW)',
                    'Booked Outstanding Balance (ZMW)',
                    'Due Date',
                    'Expected Amount (ZMW)',
                    'Amount Paid (ZMW)',
                    'Remaining Amount (ZMW)',
                    'Payment Status',
                    'Period Number',
                    'Tenure (Months)',
                    'Loan Status',
                ];
            }

            public function columnWidths(): array
            {
                return [
                    'A' => 18, 'B' => 25, 'C' => 30, 'D' => 18, 'E' => 20,
                    'F' => 20, 'G' => 20, 'H' => 18, 'I' => 22, 'J' => 12,
                    'K' => 20, 'L' => 18, 'M' => 22, 'N' => 18, 'O' => 15,
                    'P' => 15, 'Q' => 18,
                ];
            }

            public function styles(Worksheet $sheet)
            {
                return [
                    1 => [
                        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => '0ea5e9'], // cyan-500
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER,
                        ],
                    ],
                ];
            }
        }, $filename);
    }

    public function show(Loan $loan): View
    {
        $loan->load([
            'customer',
            'loanProduct',
            'customerGroup',
            'channel',
            'disbursementFinancialInstitution',
            'disbursementFinancialInstitutionBranch',
            'loanRate.loanRateType',
            'paymentSchedules',
            'approver',
            'accruals',
            'loanRepayments.repayment',
            'loanRepayments.refundOf.repayment',
            'loanRepayments.refunds',
            'loanExtensions.creator',
            'collateralLoanDetail.collateralType',
            'collateralLoanDetail.inspector',
        ]);

        // For manual disbursement flow, load available banks & wallets
        $disbursementType = config('app.disbursement_type', 'manual');
        $banks = Bank::where('is_active', true)->orderBy('name')->get();
        $wallets = Wallet::where('is_active', true)->orderBy('name')->get();
        $paymentChannels = Channel::query()
            ->where('is_active', true)
            ->where('can_disburse', true)
            ->orderBy('name')
            ->get();

        if ($loan->channel && ! $paymentChannels->contains('id', $loan->channel->id)) {
            $paymentChannels = $paymentChannels->prepend($loan->channel);
        }

        $financialInstitutions = FinancialInstitution::query()
            ->active()
            ->with(['branches' => fn ($query) => $query->active()->orderBy('name')])
            ->orderBy('name')
            ->get();

        $paymentDetailChangeTrail = collect(data_get($loan->metadata, 'payment_details_change_trail', []))
            ->filter(fn ($entry) => is_array($entry))
            ->sortByDesc(fn ($entry) => data_get($entry, 'changed_at') ?? data_get($entry, 'at'))
            ->values();

        $extensionTypeOptions = LoanExtension::typeOptions();
        $interestModeOptions = LoanExtension::interestModeOptions();

        $refundableLoanRepayments = $loan->loanRepayments
            ->filter(fn (LoanRepayment $loanRepayment) => $loanRepayment->isPayment() && $loanRepayment->refundableAmountRemaining() > 0)
            ->values();

        $canRefundRepayments = auth('admin')->user()?->can('repayments.refund') ?? false;

        $ledgerService = app(LoanRepaymentLedgerService::class);
        $loanLedger = [
            'expected_settlement' => $ledgerService->getExpectedSettlementAmount($loan),
            'net_paid' => $ledgerService->calculateNetPaid($loan),
            'outstanding' => $ledgerService->calculateOutstandingBalance($loan),
            'suspense' => $ledgerService->calculateSuspenseAmount($loan),
        ];

        $sharedPaymentDetails = app(SharedPaymentDetailsDetectionService::class)->forLoan($loan);

        return view('admin.loans.show', compact(
            'loan',
            'disbursementType',
            'banks',
            'wallets',
            'paymentChannels',
            'financialInstitutions',
            'paymentDetailChangeTrail',
            'extensionTypeOptions',
            'interestModeOptions',
            'refundableLoanRepayments',
            'canRefundRepayments',
            'loanLedger',
            'sharedPaymentDetails',
        ));
    }

    public function updatePaymentDetails(Request $request, Loan $loan): RedirectResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin?->can('loans.update-payment-details'), 403);

        $isEditableStage = $loan->status === 'pending_approval'
            || ($loan->status === 'approved' && $loan->disbursement_status === 'pending');

        if (! $isEditableStage) {
            return redirect()
                ->route('admin.loans.show', $loan)
                ->with('error', 'Payment details can only be changed before approval or before disbursement.');
        }

        $request->validate([
            'channel_id' => ['required', 'integer'],
            'payment_change_reason' => ['nullable', 'string', 'max:1000'],
            'form_action' => ['nullable', 'string', 'max:50'],
        ]);

        $stage = $loan->status === 'pending_approval' ? 'approval' : 'disbursement';

        try {
            $paymentDetailsChange = $this->loanPaymentDetailsService->stageChange(
                $loan,
                $request->only([
                    'channel_id',
                    'disbursement_phone_number',
                    'disbursement_financial_institution_id',
                    'disbursement_financial_institution_branch_id',
                    'disbursement_account_holder_name',
                    'disbursement_account_number',
                    'disbursement_notes',
                    'payment_change_reason',
                ]),
                $admin,
                $stage
            );

            if (! $paymentDetailsChange) {
                return redirect()
                    ->route('admin.loans.show', $loan)
                    ->with('status', 'Payment details already match the current values.');
            }

            DB::transaction(function () use ($loan, $paymentDetailsChange, $admin): void {
                $loan->save();
                $this->loanPaymentDetailsService->recordAudit($loan, $paymentDetailsChange, $admin);
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return redirect()
                ->route('admin.loans.show', $loan)
                ->with('error', 'Failed to update payment details: '.$e->getMessage())
                ->withInput();
        }

        try {
            $this->loanPaymentDetailsService->sendChangeNotification(
                $loan->fresh(['customer', 'loanProduct', 'channel']),
                $paymentDetailsChange
            );
        } catch (\Throwable $notificationError) {
            Log::error('Failed to send loan payment details change notifications', [
                'loan_id' => $loan->id,
                'error' => $notificationError->getMessage(),
            ]);
        }

        return redirect()
            ->route('admin.loans.show', $loan)
            ->with('status', 'Payment details updated successfully.');
    }

    public function previewExtension(Request $request, Loan $loan): JsonResponse
    {
        abort_unless(auth('admin')->user()?->can('loan.extend'), 403);

        $validated = $request->validate([
            'extension_type' => ['required', 'integer', 'in:1,2,3'],
            'extension_period_value' => ['required', 'integer', 'min:1', 'max:120'],
            'extension_period_unit' => ['required', 'string', 'in:days,months'],
            'interest_mode' => ['required', 'integer', 'in:1,2,3'],
            'interest_value' => ['nullable', 'numeric', 'min:0'],
            'new_installment_count' => ['nullable', 'integer', 'min:1', 'max:120'],
        ]);

        $interestMode = (int) $validated['interest_mode'];
        if (in_array($interestMode, [
            LoanExtension::INTEREST_MODE_CUSTOM_RATE,
            LoanExtension::INTEREST_MODE_FIXED_AMOUNT,
        ], true) && ! isset($validated['interest_value'])) {
            return response()->json([
                'eligible' => false,
                'message' => 'Interest value is required for the selected interest mode.',
            ], 422);
        }

        return response()->json(
            $this->loanExtensionService->preview($loan, $validated)
        );
    }

    public function extend(Request $request, Loan $loan): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loan.extend'), 403);

        $validated = $request->validate([
            'extension_type' => ['required', 'integer', 'in:1,2,3'],
            'extension_period_value' => ['required', 'integer', 'min:1', 'max:120'],
            'extension_period_unit' => ['required', 'string', 'in:days,months'],
            'interest_mode' => ['required', 'integer', 'in:1,2,3'],
            'interest_value' => ['nullable', 'numeric', 'min:0'],
            'new_installment_count' => ['nullable', 'integer', 'min:1', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $interestMode = (int) $validated['interest_mode'];
        $extensionType = (int) $validated['extension_type'];

        if (in_array($interestMode, [
            LoanExtension::INTEREST_MODE_CUSTOM_RATE,
            LoanExtension::INTEREST_MODE_FIXED_AMOUNT,
        ], true) && !isset($validated['interest_value'])) {
            return redirect()
                ->route('admin.loans.show', $loan)
                ->with('error', 'Interest value is required for the selected interest mode.')
                ->withInput();
        }

        if ($extensionType === LoanExtension::TYPE_RESTRUCTURE && !isset($validated['new_installment_count'])) {
            return redirect()
                ->route('admin.loans.show', $loan)
                ->with('error', 'New installment count is required for restructure extensions.')
                ->withInput();
        }

        try {
            $extension = $this->loanExtensionService->extend(
                $loan,
                $validated,
                (int) auth('admin')->id()
            );

            return redirect()
                ->route('admin.loans.show', $loan)
                ->with('status', 'Loan extension saved successfully ('.$extension->type_label.').');
        } catch (\Throwable $e) {
            return redirect()
                ->route('admin.loans.show', $loan)
                ->with('error', $e->getMessage())
                ->withInput();
        }
    }

    public function exportSchedulePdf(Loan $loan)
    {
        $loan->load(['customer.company', 'loanProduct', 'customerGroup']);
        
        if (!$loan->first_payment_date || $loan->tenure_months <= 0) {
            return redirect()
                ->route('admin.loans.show', $loan)
                ->with('error', 'Loan schedule is not available for this loan.');
        }

        $repaymentSchedule = $loan->getRepaymentSchedule();
        $company = $loan->customer->company ?? \App\Models\Company::where('is_primary', true)->first();
        
        $pdf = Pdf::loadView('admin.loans.schedule-pdf', [
            'loan' => $loan,
            'repaymentSchedule' => $repaymentSchedule,
            'company' => $company,
        ])->setPaper('a4', 'portrait');

        $filename = 'loan-schedule-' . $loan->loan_number . '-' . now()->format('Y-m-d') . '.pdf';
        
        return $pdf->download($filename);
    }

    public function storeRefund(StoreLoanRefundRequest $request, Loan $loan, LoanRepaymentRefundService $refundService): RedirectResponse
    {
        $originalLoanRepayment = LoanRepayment::query()->findOrFail($request->integer('loan_repayment_id'));

        try {
            $refundService->applyRefund(
                $loan,
                $originalLoanRepayment,
                (float) $request->input('amount'),
                (string) $request->input('reason'),
                auth('admin')->id()
            );
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.loans.show', $loan)
            ->with('success', 'Refund recorded successfully.');
    }

    public function backfillRepayment(Loan $loan, Request $request)
    {
        try {
            // Check if loan already has repayment records
            if ($loan->loanRepayments()->count() > 0) {
                return redirect()
                    ->route('admin.loans.show', $loan)
                    ->with('error', 'This loan already has repayment records.');
            }

            // Check if loan has any payments
            if ($loan->amount_paid <= 0) {
                return redirect()
                    ->route('admin.loans.show', $loan)
                    ->with('error', 'This loan has no payments to backfill.');
            }

            DB::beginTransaction();

            // Get default repayment channel or use loan's channel if it supports repayment
            $channel = null;
            if ($loan->channel && $loan->channel->can_repay) {
                $channel = $loan->channel;
            } else {
                $channel = Channel::where('is_active', true)->where('can_repay', true)->first();
            }
            
            if (!$channel) {
                throw new \Exception('No repayment channel available. Please configure channels first.');
            }

            // Create repayment record
            $repayment = Repayment::create([
                'customer_id' => $loan->customer_id,
                'channel_id' => $channel->id,
                'repayment_number' => Repayment::generateRepaymentNumber(),
                'total_amount' => $loan->amount_paid,
                'phone_number' => $loan->disbursement_phone_number ?? $loan->customer->phone,
                'status' => 'completed',
                'processed_at' => $loan->loan_settled_date 
                    ? Carbon::parse($loan->loan_settled_date) 
                    : ($loan->updated_at ?? now()),
                'metadata' => [
                    'backfilled' => true,
                    'backfilled_at' => now()->toIso8601String(),
                    'backfilled_by' => auth('admin')->user()->email ?? 'System',
                    'original_loan_settled_date' => $loan->loan_settled_date?->toDateString(),
                ],
            ]);

            // Use the Loan model's helper method to calculate repayment allocation
            // This ensures principal + interest + processing_fee = paymentAmount
            $paymentAmount = $loan->amount_paid;
            $allocation = $loan->calculateRepaymentAllocation($paymentAmount);
            
            $principalPaid = $allocation['principal_amount'];
            $interestPaid = $allocation['interest_amount'];
            $processingFeePaid = $allocation['processing_fee_amount'];
            
            // Verify the allocation sums correctly (should always be true)
            $totalAllocated = $principalPaid + $interestPaid + $processingFeePaid;
            if (abs($totalAllocated - $paymentAmount) > 0.01) {
                // If there's a rounding discrepancy, adjust principal
                $principalPaid += ($paymentAmount - $totalAllocated);
                $principalPaid = max(0, $principalPaid);
            }
            
            // Get balance before payment (estimated)
            $outstandingBefore = $loan->outstanding_balance + $paymentAmount;
            $outstandingAfter = $loan->outstanding_balance;

            // Create loan repayment record
            LoanRepayment::create([
                'repayment_id' => $repayment->id,
                'loan_id' => $loan->id,
                'amount' => $paymentAmount,
                'principal_amount' => round($principalPaid, 2),
                'interest_amount' => round($interestPaid, 2),
                'processing_fee_amount' => round($processingFeePaid, 2),
                'outstanding_balance_before' => $outstandingBefore,
                'outstanding_balance_after' => $outstandingAfter,
                'notes' => 'Backfilled repayment record - splits calculated based on loan structure',
            ]);

            // Update loan status to 'settled' if fully paid
            if ($loan->outstanding_balance <= 0 && in_array($loan->status, ['completed', 'active', 'approved'])) {
                $loan->update(['status' => 'settled']);
            }

            DB::commit();

            return redirect()
                ->route('admin.loans.show', $loan)
                ->with('status', 'Repayment records successfully backfilled for this loan.');

        } catch (\Exception $e) {
            DB::rollBack();
            
            return redirect()
                ->route('admin.loans.show', $loan)
                ->with('error', 'Failed to backfill repayment records: ' . $e->getMessage());
        }
    }

    /**
     * Manually record loan disbursement (for manual disbursement type).
     */
    public function disburse(Request $request, Loan $loan): RedirectResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin?->can('loans.disburse'), 403);

        // Only allow manual flow when configured and when loan is approved and pending disbursement
        if (config('app.disbursement_type', 'manual') !== 'manual') {
            return redirect()
                ->route('admin.loans.show', $loan)
                ->with('error', 'Manual disbursement is disabled by configuration.');
        }

        if ($loan->status !== 'approved' || $loan->disbursement_status !== 'pending') {
            return redirect()
                ->route('admin.loans.show', $loan)
                ->with('error', 'Only approved loans with pending disbursement can be disbursed manually.');
        }

        $validated = $request->validate([
            'source_type' => ['required', 'in:bank,wallet'],
            'source_id' => ['required', 'integer'],
            'reference_number' => ['required', 'string', 'max:100'],
            'disbursement_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:500'],
            'form_action' => ['nullable', 'string', 'max:50'],
            'channel_id' => ['sometimes', 'required', 'integer'],
            'payment_change_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $paymentDetailsChange = $this->loanPaymentDetailsService->stageChange(
            $loan,
            array_merge($validated, $request->only([
                'disbursement_phone_number',
                'disbursement_financial_institution_id',
                'disbursement_financial_institution_branch_id',
                'disbursement_account_holder_name',
                'disbursement_account_number',
                'disbursement_notes',
            ])),
            $admin,
            'disbursement'
        );
        $amount = (float) $loan->principal_amount;

        try {
            DB::beginTransaction();

            if ($validated['source_type'] === 'bank') {
                /** @var Bank $source */
                $source = Bank::query()
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->findOrFail($validated['source_id']);
            } else {
                /** @var Wallet $source */
                $source = Wallet::query()
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->findOrFail($validated['source_id']);
            }

            if ((float) $source->current_balance < $amount) {
                DB::rollBack();

                return redirect()
                    ->route('admin.loans.show', $loan)
                    ->with('error', 'Insufficient balance on the selected account. Available: '.number_format((float) $source->current_balance, 2))
                    ->withInput();
            }

            // Debit the source account
            $source->updateBalance($amount, 'debit');

            // Update loan disbursement details and activate for portfolio reporting
            $loan->disbursed_via_type = $validated['source_type'];
            $loan->disbursed_via_id = $validated['source_id'];
            $loan->applyDisbursementCompleted(Carbon::parse($validated['disbursement_date']));
            $loan->disbursement_reference = $validated['reference_number'];
            $loan->disbursement_notes = $validated['description'] ?? null;
            $loan->metadata = array_merge($loan->metadata ?? [], [
                'disbursement_reference' => $validated['reference_number'] ?? null,
                'disbursed_manually_by' => $admin?->id,
            ]);
            $loan->save();

            if ($paymentDetailsChange) {
                $this->loanPaymentDetailsService->recordAudit($loan, $paymentDetailsChange, $admin);
            }

            DB::commit();
        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            return redirect()
                ->route('admin.loans.show', $loan)
                ->with('error', 'Failed to record disbursement: '.$e->getMessage())
                ->withInput();
        }

        $freshLoan = $loan->fresh(['customer', 'loanProduct', 'channel']);

        if ($paymentDetailsChange) {
            try {
                $this->loanPaymentDetailsService->sendChangeNotification($freshLoan, $paymentDetailsChange);
            } catch (\Throwable $notificationError) {
                Log::error('Failed to send loan payment details change notifications', [
                    'loan_id' => $loan->id,
                    'error' => $notificationError->getMessage(),
                ]);
            }
        }

        try {
            $this->customerNotificationService->sendLoanDisbursed($freshLoan);
        } catch (\Throwable $notificationError) {
            Log::error('Failed to send loan disbursement notifications', [
                'loan_id' => $loan->id,
                'error' => $notificationError->getMessage(),
            ]);
        }

        return redirect()
            ->route('admin.loans.show', $loan)
            ->with('status', 'Loan disbursement recorded successfully.');
    }
}
