<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLoanRefundRequest;
use App\Models\Admin;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\Channel;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\FinancialInstitution;
use App\Models\Loan;
use App\Models\LoanExtension;
use App\Models\LoanPaymentSchedule;
use App\Models\LoanProduct;
use App\Models\LoanRepayment;
use App\Models\PaymentGateway;
use App\Models\PaymentGatewayAttempt;
use App\Models\Repayment;
use App\Models\Wallet;
use App\PaymentPlatform\Enums\GatewayDirection;
use App\PaymentPlatform\Services\GatewayIntegrationService;
use App\PaymentPlatform\Services\PaymentGatewayDestinationMappingResolver;
use App\PaymentPlatform\Support\CGrateIssuerNameResolver;
use App\Services\CustomerNotificationService;
use App\Services\LoanExtensionService;
use App\Services\LoanPaymentDetailsService;
use App\Services\LoanRepaymentLedgerService;
use App\Services\LoanRepaymentRefundService;
use App\Services\Loans\AutomaticLoanDisbursementService;
use App\Services\Loans\DTOs\ManualDisbursementDTO;
use App\Services\Loans\LoanCancellationService;
use App\Services\Loans\LoanDisbursementService;
use App\Services\SharedPaymentDetailsDetectionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LoanController extends Controller
{
    public function __construct(
        private readonly CustomerNotificationService $customerNotificationService,
        private readonly LoanExtensionService $loanExtensionService,
        private readonly LoanPaymentDetailsService $loanPaymentDetailsService,
        private readonly LoanDisbursementService $loanDisbursementService,
    ) {}

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
            $query->forCustomerGroupMembership((int) $request->customer_group_id);
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
        $loanProductsQuery = LoanProduct::where('is_active', '=', true, 'and');
        $customerGroupsQuery = CustomerGroup::where('is_active', '=', true, 'and')->with('loanProduct');
        $customersQuery = Customer::query();

        if ($companyFilterId !== null) {
            $loanProductsQuery->where('company_id', $companyFilterId);
            $customerGroupsQuery->whereHas('loanProduct', function ($q) use ($companyFilterId) {
                $q->where('company_id', $companyFilterId);
            });
            $customersQuery->where('company_id', $companyFilterId);
        }

        $loanProducts = $loanProductsQuery->orderBy('name', 'asc')->get();
        $customerGroups = $customerGroupsQuery->orderBy('name', 'asc')->get();
        $customers = $customersQuery->orderBy('first_name', 'asc')->orderBy('last_name', 'asc')->get();

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
            $query->forCustomerGroupMembership((int) $request->customer_group_id);
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

        $filename = 'loans-export-'.now()->format('Y-m-d_His').'.xlsx';

        return Excel::download(new class($exportData) implements FromCollection, WithColumnWidths, WithHeadings, WithStyles
        {
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
        $today = Carbon::today();
        $query = $this->buildTodaysPaymentsQuery($request, $today);

        $loans = $query
            ->with([
                'customer',
                'loanProduct',
                'customerGroup',
                'channel',
                'paymentSchedules' => fn ($q) => $q->whereDate('due_date', $today),
            ])
            ->paginate(20)
            ->withQueryString();

        $loans->getCollection()->transform(function ($loan) {
            $loan->todays_schedule = $loan->paymentSchedules->first();

            return $loan;
        });

        $branches = Branch::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $relationshipManagers = Admin::query()
            ->where('is_relationship_manager', true)
            ->where('is_active', true)
            ->when($request->filled('branch_id'), fn (Builder $q) => $q->where('branch_id', $request->integer('branch_id')))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name']);

        return view('admin.loans.todays-payments', compact('loans', 'today', 'branches', 'relationshipManagers'));
    }

    public function exportTodaysPayments(Request $request)
    {
        $today = Carbon::today();
        $query = $this->buildTodaysPaymentsQuery($request, $today);

        $loans = $query
            ->with([
                'customer',
                'loanProduct',
                'customerGroup',
                'channel',
                'paymentSchedules' => fn ($q) => $q->whereDate('due_date', $today),
            ])
            ->get();

        $exportData = $loans->map(function ($loan) use ($today) {
            $todaysSchedule = $loan->paymentSchedules->first();

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

        $filename = 'todays-payments-'.$today->format('Y-m-d').'.xlsx';

        return Excel::download(new class($exportData) implements FromCollection, WithColumnWidths, WithHeadings, WithStyles
        {
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

    public function missedPayments(Request $request): View
    {
        $today = Carbon::today();
        [$windowStart, $windowEnd] = $this->missedPaymentsWindow($today);
        $query = $this->buildMissedPaymentsQuery($request, $today);

        $loans = $query
            ->with([
                'customer.company.relationshipManager',
                'customer.customerGroup.relationshipManager',
                'customer.branch',
                'loanProduct',
                'customerGroup.relationshipManager',
                'channel',
                'paymentSchedules' => fn ($q) => $q
                    ->where('remaining_amount', '>', 0)
                    ->whereDate('due_date', '>=', $windowStart)
                    ->whereDate('due_date', '<=', $windowEnd)
                    ->orderBy('due_date'),
            ])
            ->paginate(20)
            ->withQueryString();

        $loans->getCollection()->transform(fn (Loan $loan) => $this->attachMissedPaymentSummary($loan, $today));

        $branches = Branch::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $relationshipManagers = Admin::query()
            ->where('is_relationship_manager', true)
            ->where('is_active', true)
            ->when($request->filled('branch_id'), fn (Builder $q) => $q->where('branch_id', $request->integer('branch_id')))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name']);

        return view('admin.loans.missed-payments', compact(
            'loans',
            'today',
            'windowStart',
            'windowEnd',
            'branches',
            'relationshipManagers'
        ));
    }

    public function exportMissedPayments(Request $request)
    {
        abort_unless(auth('admin')->user()?->can('loans.export'), 403);

        $today = Carbon::today();
        [$windowStart, $windowEnd] = $this->missedPaymentsWindow($today);
        $query = $this->buildMissedPaymentsQuery($request, $today);

        $loans = $query
            ->with([
                'customer.company.relationshipManager',
                'customer.customerGroup.relationshipManager',
                'customer.branch',
                'loanProduct',
                'customerGroup',
                'channel',
                'paymentSchedules' => fn ($q) => $q
                    ->where('remaining_amount', '>', 0)
                    ->whereDate('due_date', '>=', $windowStart)
                    ->whereDate('due_date', '<=', $windowEnd)
                    ->orderBy('due_date'),
            ])
            ->get()
            ->map(fn (Loan $loan) => $this->attachMissedPaymentSummary($loan, $today));

        $exportData = $loans->map(function (Loan $loan) {
            $schedule = $loan->primary_missed_schedule;
            $relationshipManager = $loan->customerGroup?->relationshipManager
                ?? $loan->customer?->company?->relationshipManager
                ?? $loan->customer?->customerGroup?->relationshipManager;

            return [
                'Loan Number' => $loan->loan_number,
                'Customer Name' => $loan->customer->full_name ?? 'N/A',
                'Customer Email' => $loan->customer->email ?? 'N/A',
                'Customer Phone' => $loan->customer->phone ?? 'N/A',
                'Branch' => $loan->customer?->branch?->name ?? 'N/A',
                'Relationship Manager' => $relationshipManager
                    ? trim($relationshipManager->first_name.' '.$relationshipManager->last_name)
                    : 'N/A',
                'Product' => $loan->loanProduct->name ?? 'N/A',
                'Customer Group' => $loan->customerGroup->name ?? 'N/A',
                'Oldest Missed Due Date' => $schedule?->due_date?->format('Y-m-d') ?? 'N/A',
                'Days Overdue' => $loan->missed_days_overdue ?? 0,
                'Missed Installments' => $loan->missed_installments_count ?? 0,
                'Missed Amount (ZMW)' => number_format($loan->missed_amount_total ?? 0, 2),
                'Expected Amount (Oldest) (ZMW)' => $schedule ? number_format($schedule->expected_amount, 2) : '0.00',
                'Amount Paid (Oldest) (ZMW)' => $schedule ? number_format($schedule->amount_paid, 2) : '0.00',
                'Remaining (Oldest) (ZMW)' => $schedule ? number_format($schedule->remaining_amount, 2) : '0.00',
                'Payment Status (Oldest)' => $schedule ? ucfirst(str_replace('_', ' ', $schedule->status)) : 'N/A',
                'Period Number (Oldest)' => $schedule?->period_number ?? 'N/A',
                'Booked Outstanding Balance (ZMW)' => number_format($loan->outstanding_balance, 2),
                'Loan Status' => ucfirst(str_replace('_', ' ', $loan->status)),
            ];
        });

        $filename = 'missed-payments-'.$windowStart->format('Y-m-d').'-to-'.$windowEnd->format('Y-m-d').'.xlsx';

        return Excel::download(new class($exportData) implements FromCollection, WithColumnWidths, WithHeadings, WithStyles
        {
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
                    'F' => 24, 'G' => 20, 'H' => 20, 'I' => 20, 'J' => 14,
                    'K' => 14, 'L' => 20, 'M' => 24, 'N' => 24, 'O' => 24,
                    'P' => 22, 'Q' => 18, 'R' => 24, 'S' => 15,
                ];
            }

            public function styles(Worksheet $sheet)
            {
                return [
                    1 => [
                        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'f97316'],
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
        $banks = Bank::where('is_active', '=', true, 'and')->orderBy('name', 'asc')->get();
        $wallets = Wallet::where('is_active', '=', true, 'and')->orderBy('name', 'asc')->get();
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

        $activeDisbursementGateway = PaymentGateway::query()
            ->where('code', 'cgrate')
            ->first();

        $disbursementGatewayAvailable = $activeDisbursementGateway
            && $activeDisbursementGateway->isAvailableForDisbursement()
            && $activeDisbursementGateway->hasLinkedFinancialAccount();

        $disbursementDestinationPreview = null;
        $disbursementDestinationMappingWarning = null;
        if ($disbursementGatewayAvailable) {
            try {
                $disbursementDestinationPreview = app(CGrateIssuerNameResolver::class)->resolveForLoan($loan);

                $destinationPaymentMethod = $disbursementDestinationPreview['payment_method'] ?? null;
                if ($destinationPaymentMethod === 'bank') {
                    $mapping = app(PaymentGatewayDestinationMappingResolver::class)->resolve(
                        $activeDisbursementGateway,
                        'bank',
                        (int) $loan->disbursement_financial_institution_id,
                        null,
                        'issuerName'
                    )['mapping'];

                    $loan->loadMissing(['disbursementFinancialInstitution']);
                    $bankName = (string) ($loan->disbursementFinancialInstitution?->name ?? 'selected bank');

                    if (! $mapping) {
                        $disbursementDestinationPreview = null;
                        $disbursementDestinationMappingWarning = 'No cGrate issuerName mapping has been configured for '.$bankName.'. Configure the bank mapping before using cGrate disbursement.';
                    } elseif ($mapping->isVerificationRequired()) {
                        $disbursementDestinationPreview = null;
                        $disbursementDestinationMappingWarning = 'The cGrate issuerName mapping for '.$bankName.' requires verification before use.';
                    } else {
                        $disbursementDestinationPreview['issuer_name'] = (string) $mapping->gateway_value;
                    }
                }

                if ($destinationPaymentMethod === 'mobile_money') {
                    $channelId = (int) ($loan->channel_id ?? 0);
                    $mapping = app(PaymentGatewayDestinationMappingResolver::class)->resolve(
                        $activeDisbursementGateway,
                        'mobile_money',
                        null,
                        $channelId,
                        'issuerName'
                    )['mapping'];

                    if ($mapping) {
                        if ($mapping->isVerificationRequired()) {
                            $disbursementDestinationPreview = null;
                            $disbursementDestinationMappingWarning = 'The cGrate issuerName mapping requires verification before use.';
                        } else {
                            $disbursementDestinationPreview['issuer_name'] = (string) $mapping->gateway_value;
                        }
                    }
                }
            } catch (\Throwable) {
                $disbursementDestinationPreview = null;
            }
        }

        $disbursementAttempts = PaymentGatewayAttempt::query()
            ->where('attemptable_type', Loan::class)
            ->where('attemptable_id', $loan->id)
            ->where('direction', GatewayDirection::Disbursement)
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $approvalAutoDisbursementPreview = $loan->status === 'pending_approval'
            ? app(AutomaticLoanDisbursementService::class)->previewForApproval($loan)
            : null;

        $canCancelLoan = app(LoanCancellationService::class)->canCancel($loan);

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
            'activeDisbursementGateway',
            'disbursementGatewayAvailable',
            'disbursementDestinationPreview',
            'disbursementDestinationMappingWarning',
            'disbursementAttempts',
            'canRefundRepayments',
            'loanLedger',
            'sharedPaymentDetails',
            'approvalAutoDisbursementPreview',
            'canCancelLoan',
        ));
    }

    public function cancel(Request $request, Loan $loan): RedirectResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin?->can('loans.cancel'), 403);

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            app(LoanCancellationService::class)->cancel(
                $loan,
                $admin,
                $validated['notes'] ?? null
            );
        } catch (ValidationException $e) {
            return redirect()
                ->route('admin.loans.show', $loan)
                ->withErrors($e->errors())
                ->with('error', collect($e->errors())->flatten()->first() ?? 'Unable to cancel this loan.');
        }

        return redirect()
            ->route('admin.loans.show', $loan)
            ->with('status', 'Loan cancelled successfully.');
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
        ], true) && ! isset($validated['interest_value'])) {
            return redirect()
                ->route('admin.loans.show', $loan)
                ->with('error', 'Interest value is required for the selected interest mode.')
                ->withInput();
        }

        if ($extensionType === LoanExtension::TYPE_RESTRUCTURE && ! isset($validated['new_installment_count'])) {
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
        abort_unless(auth('admin')->user()?->can('loans.view'), 403);

        $loan->load([
            'customer.company',
            'loanProduct',
            'customerGroup',
            'paymentSchedules',
        ]);

        if (! $loan->first_payment_date || $loan->tenure_months <= 0) {
            return redirect()
                ->route('admin.loans.show', $loan)
                ->with('error', 'Loan schedule is not available for this loan.');
        }

        $repaymentSchedule = $loan->getRepaymentSchedule();
        $company = $loan->customer->company ?? \App\Models\Company::where('is_primary', true)->first();
        $branding = \App\Support\Pdf\FinancialDocumentBranding::resolve($company);

        // FineEdge has no default-interest ledger; keep the PDF section omitted cleanly.
        $pdf = Pdf::loadView('pdf.loan-repayment-schedule', [
            'loan' => $loan,
            'repaymentSchedule' => $repaymentSchedule,
            'defaultInterestEntries' => collect(),
            'defaultInterestTotal' => 0.0,
            'company' => $company,
            'branding' => $branding,
        ])->setPaper('a4', 'portrait');

        $safeLoanNumber = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) ($loan->loan_number ?: 'loan-'.$loan->id)) ?: 'loan';
        $filename = 'repayment-schedule-'.$safeLoanNumber.'.pdf';

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
                $channel = Channel::where('is_active', '=', true, 'and')->where('can_repay', '=', true, 'and')->first();
            }

            if (! $channel) {
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
                ->with('error', 'Failed to backfill repayment records: '.$e->getMessage());
        }
    }

    /**
     * Manually record loan disbursement.
     */
    public function disburse(Request $request, Loan $loan): RedirectResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin?->can('loans.disburse'), 403);

        if ($loan->status !== 'approved' || ! in_array($loan->disbursement_status, ['pending', 'failed'], true)) {
            return redirect()
                ->route('admin.loans.show', $loan)
                ->with('error', 'Only approved loans with pending or failed disbursement can be disbursed manually.');
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

        try {
            $this->loanDisbursementService->completeManualDisbursement(
                $loan,
                new ManualDisbursementDTO(
                    sourceType: $validated['source_type'],
                    sourceId: (int) $validated['source_id'],
                    referenceNumber: $validated['reference_number'],
                    disbursementDate: Carbon::parse($validated['disbursement_date']),
                    description: $validated['description'] ?? null,
                ),
                $admin,
                $paymentDetailsChange
            );
        } catch (ValidationException $e) {
            return redirect()
                ->route('admin.loans.show', $loan)
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Throwable $e) {
            return redirect()
                ->route('admin.loans.show', $loan)
                ->with('error', 'Failed to record disbursement: '.$e->getMessage())
                ->withInput();
        }

        return redirect()
            ->route('admin.loans.show', $loan)
            ->with('status', 'Loan disbursement recorded successfully.');
    }

    public function disburseGateway(Loan $loan): RedirectResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin?->can('loans.disburse'), 403);

        $result = app(GatewayIntegrationService::class)->initiateDisbursement($loan);

        if (! ($result['success'] ?? false)) {
            return redirect()
                ->route('admin.loans.show', $loan)
                ->with('error', $result['message'] ?? 'Failed to initiate gateway disbursement.');
        }

        return redirect()
            ->route('admin.loans.show', $loan)
            ->with('status', $result['message'] ?? 'Gateway disbursement initiated.');
    }

    public function retryDisburseGateway(Loan $loan): RedirectResponse
    {
        return $this->disburseGateway($loan);
    }

    private function buildTodaysPaymentsQuery(Request $request, Carbon $today): Builder
    {
        $query = Loan::query()
            ->whereHas('paymentSchedules', fn (Builder $q) => $q->whereDate('due_date', $today));

        $this->applyLoanFollowUpFilters($request, $query);

        $sortBy = $request->string('sort_by')->toString() ?: 'loan_number';
        $sortDir = strtolower($request->string('sort_dir')->toString() ?: 'asc') === 'desc' ? 'desc' : 'asc';

        $scheduleSubquery = LoanPaymentSchedule::query()
            ->select([
                'loan_id',
                'expected_amount',
                'amount_paid',
                'remaining_amount',
                'status',
            ])
            ->whereDate('due_date', $today);

        $query->leftJoinSub($scheduleSubquery, 'todays_schedule', 'todays_schedule.loan_id', '=', 'loans.id')
            ->select('loans.*');

        match ($sortBy) {
            'customer_name' => $query
                ->leftJoin('customers as sort_customers', 'sort_customers.id', '=', 'loans.customer_id')
                ->orderBy('sort_customers.first_name', $sortDir)
                ->orderBy('sort_customers.last_name', $sortDir)
                ->orderBy('sort_customers.registered_name', $sortDir),
            'expected_amount' => $query->orderBy('todays_schedule.expected_amount', $sortDir),
            'amount_paid' => $query->orderBy('todays_schedule.amount_paid', $sortDir),
            'remaining_amount' => $query->orderBy('todays_schedule.remaining_amount', $sortDir),
            'payment_status' => $query->orderBy('todays_schedule.status', $sortDir),
            'outstanding_balance' => $query->orderBy('loans.outstanding_balance', $sortDir),
            default => $query->orderBy('loans.loan_number', $sortDir),
        };

        return $query;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function missedPaymentsWindow(Carbon $today, int $days = 14): array
    {
        $windowEnd = $today->copy()->subDay();
        $windowStart = $today->copy()->subDays($days);

        return [$windowStart, $windowEnd];
    }

    private function buildMissedPaymentsQuery(Request $request, Carbon $today): Builder
    {
        [$windowStart, $windowEnd] = $this->missedPaymentsWindow($today);

        $query = Loan::query()
            ->activePortfolio()
            ->whereHas('paymentSchedules', function (Builder $q) use ($windowStart, $windowEnd) {
                $q->where('remaining_amount', '>', 0)
                    ->whereDate('due_date', '>=', $windowStart)
                    ->whereDate('due_date', '<=', $windowEnd);
            });

        $this->applyLoanFollowUpFilters($request, $query);

        $sortBy = $request->string('sort_by')->toString() ?: 'days_overdue';
        $sortDir = strtolower($request->string('sort_dir')->toString() ?: 'desc') === 'desc' ? 'desc' : 'asc';

        $scheduleSubquery = LoanPaymentSchedule::query()
            ->selectRaw('loan_id, MIN(due_date) as oldest_due_date, SUM(remaining_amount) as missed_amount, COUNT(*) as missed_installments')
            ->where('remaining_amount', '>', 0)
            ->whereDate('due_date', '>=', $windowStart)
            ->whereDate('due_date', '<=', $windowEnd)
            ->groupBy('loan_id');

        $query->leftJoinSub($scheduleSubquery, 'missed_schedule', 'missed_schedule.loan_id', '=', 'loans.id')
            ->select('loans.*');

        match ($sortBy) {
            'customer_name' => $query
                ->leftJoin('customers as sort_customers', 'sort_customers.id', '=', 'loans.customer_id')
                ->orderBy('sort_customers.first_name', $sortDir)
                ->orderBy('sort_customers.last_name', $sortDir)
                ->orderBy('sort_customers.registered_name', $sortDir),
            'due_date' => $query->orderBy('missed_schedule.oldest_due_date', $sortDir),
            'days_overdue' => $query->orderBy('missed_schedule.oldest_due_date', $sortDir === 'desc' ? 'asc' : 'desc'),
            'missed_amount' => $query->orderBy('missed_schedule.missed_amount', $sortDir),
            'missed_installments' => $query->orderBy('missed_schedule.missed_installments', $sortDir),
            'outstanding_balance' => $query->orderBy('loans.outstanding_balance', $sortDir),
            default => $query->orderBy('loans.loan_number', $sortDir),
        };

        return $query;
    }

    private function attachMissedPaymentSummary(Loan $loan, Carbon $today): Loan
    {
        $schedules = $loan->paymentSchedules;
        $primary = $schedules->first();
        $loan->primary_missed_schedule = $primary;
        $loan->missed_amount_total = (float) $schedules->sum('remaining_amount');
        $loan->missed_installments_count = $schedules->count();
        $loan->missed_days_overdue = $primary
            ? max(0, $primary->due_date->startOfDay()->diffInDays($today))
            : 0;

        return $loan;
    }

    private function applyLoanFollowUpFilters(Request $request, Builder $query): void
    {
        $admin = auth('admin')->user();
        $companyFilterId = $admin?->getCompanyFilterId();

        if ($companyFilterId !== null) {
            $query->whereHas('customer', fn (Builder $q) => $q->where('company_id', $companyFilterId));
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function (Builder $q) use ($search) {
                $q->where('loan_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', function (Builder $customerQuery) use ($search) {
                        $customerQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('registered_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('branch_id')) {
            $branchId = $request->integer('branch_id');
            $query->whereHas('customer', fn (Builder $q) => $q->where('branch_id', $branchId));
        }

        if ($request->filled('relationship_manager_id')) {
            $relationshipManagerId = $request->integer('relationship_manager_id');
            $query->where(function (Builder $q) use ($relationshipManagerId) {
                $q->whereHas('customerGroup', fn (Builder $groupQuery) => $groupQuery->where('relationship_manager_id', $relationshipManagerId))
                    ->orWhereHas('customer.company', fn (Builder $companyQuery) => $companyQuery->where('relationship_manager_id', $relationshipManagerId))
                    ->orWhereHas('customer.customerGroup', fn (Builder $groupQuery) => $groupQuery->where('relationship_manager_id', $relationshipManagerId));
            });
        }
    }
}
