<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\Repayment;
use App\Models\LoanRepayment;
use App\Models\LoanProduct;
use App\Models\CustomerGroup;
use App\Models\Channel;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Branch;
use App\Models\Province;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    /**
     * Reports landing page.
     */
    public function index(): View
    {
        abort_unless(auth('admin')->user()?->can('reports.view'), 403);

        return view('admin.reports.index');
    }

    /**
     * Relationship Manager portfolio report (summary/detailed).
     */
    public function relationshipManagerReport(Request $request): View
    {
        abort_unless(auth('admin')->user()?->can('reports.view'), 403);

        return view('admin.reports.relationship-manager', $this->buildRelationshipManagerReportPayload($request));
    }

    /**
     * Export relationship manager report in Excel, CSV, or PDF.
     */
    public function exportRelationshipManagerReport(Request $request, string $format)
    {
        abort_unless(auth('admin')->user()?->can('reports.view'), 403);

        $exportDataset = strtolower((string) $request->query('export_dataset', 'all'));
        if (!in_array($exportDataset, ['all', 'summary', 'customers', 'loans', 'repayments'], true)) {
            $exportDataset = 'all';
        }

        $excelTabs = $this->normalizeRelationshipManagerExcelTabs(
            is_array($request->query('excel_tabs')) ? $request->query('excel_tabs') : [],
            $exportDataset
        );

        // Detailed data is required when exporting customer/loan/repayment tabs.
        $requiresDetailedData = in_array($exportDataset, ['all', 'customers', 'loans', 'repayments'], true)
            || !empty(array_intersect($excelTabs, ['customers', 'loans', 'repayments']));

        if ($requiresDetailedData) {
            $request->merge(['mode' => 'detailed']);
        }
        $request->merge([
            'export_dataset' => $exportDataset,
            'excel_tabs' => $excelTabs,
        ]);

        $payload = $this->buildRelationshipManagerReportPayload($request);
        $normalizedFormat = strtolower($format);

        return match ($normalizedFormat) {
            'excel' => $this->exportRelationshipManagerExcel($payload),
            'csv' => $this->exportRelationshipManagerCsv($payload),
            'pdf' => $this->exportRelationshipManagerPdf($payload),
            default => abort(404),
        };
    }

    /**
     * Build report rows and totals for relationship manager performance.
     *
     * This uses the system linkages:
     * - RM -> Customer Groups
     * - RM -> Companies
     * - Customers in those groups/companies -> Loans
     * - Loans -> Repayment allocations
     */
    private function buildRelationshipManagerReportPayload(Request $request): array
    {
        $validated = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'relationship_manager_id' => ['nullable', 'integer', 'exists:admins,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'customer_type' => ['nullable', 'in:all,individual,group'],
            'par_bucket' => ['nullable', 'in:all,current,at_risk,par30,par60,par90'],
            'mode' => ['nullable', 'in:summary,detailed'],
            'export_dataset' => ['nullable', 'in:all,summary,customers,loans,repayments'],
            'excel_tabs' => ['nullable', 'array'],
            'excel_tabs.*' => ['nullable', 'in:summary,customers,loans,repayments'],
        ]);

        $filters = array_merge([
            'branch_id' => null,
            'relationship_manager_id' => null,
            'date_from' => null,
            'date_to' => null,
            'customer_type' => 'all',
            'par_bucket' => 'all',
            'mode' => 'summary',
            'export_dataset' => 'all',
            'excel_tabs' => [],
        ], $validated);

        $filters['excel_tabs'] = $this->normalizeRelationshipManagerExcelTabs(
            is_array($filters['excel_tabs']) ? $filters['excel_tabs'] : [],
            (string) $filters['export_dataset']
        );

        $rangeStart = $filters['date_from'] ? Carbon::parse($filters['date_from'])->startOfDay() : null;
        $rangeEnd = $filters['date_to'] ? Carbon::parse($filters['date_to'])->endOfDay() : null;

        if ($rangeStart && $rangeEnd && $rangeStart->gt($rangeEnd)) {
            [$rangeStart, $rangeEnd] = [$rangeEnd->copy()->startOfDay(), $rangeStart->copy()->endOfDay()];
            $filters['date_from'] = $rangeStart->toDateString();
            $filters['date_to'] = $rangeEnd->toDateString();
        }

        $branchOptions = Branch::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $relationshipManagersBaseQuery = Admin::query()
            ->where('is_relationship_manager', true)
            ->where('is_active', true)
            ->with('branch:id,name')
            ->when($filters['branch_id'], fn (Builder $query) => $query->where('branch_id', $filters['branch_id']));

        $relationshipManagers = (clone $relationshipManagersBaseQuery)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'branch_id']);

        $managerQuery = (clone $relationshipManagersBaseQuery)
            ->orderBy('first_name')
            ->orderBy('last_name');

        if ($filters['relationship_manager_id']) {
            $managerQuery->where('id', $filters['relationship_manager_id']);
        }

        $reportRows = $managerQuery
            ->get(['id', 'first_name', 'last_name', 'branch_id'])
            ->map(function (Admin $manager) use ($filters, $rangeStart, $rangeEnd) {
                $groups = CustomerGroup::query()
                    ->where('relationship_manager_id', $manager->id)
                    ->withCount('customers')
                    ->orderBy('name')
                    ->get(['id', 'name', 'code', 'relationship_manager_id']);

                $groupIds = $groups->pluck('id')->all();
                $companyIds = Company::query()
                    ->where('relationship_manager_id', $manager->id)
                    ->pluck('id')
                    ->all();

                $customersQuery = Customer::query()
                    ->with([
                        'customerGroup:id,name,relationship_manager_id',
                        'company:id,name,relationship_manager_id',
                    ])
                    ->where(function (Builder $query) use ($groupIds, $companyIds) {
                        if (!empty($groupIds)) {
                            $query->whereIn('customer_group_id', $groupIds);
                        }

                        if (!empty($companyIds)) {
                            if (!empty($groupIds)) {
                                $query->orWhereIn('company_id', $companyIds);
                            } else {
                                $query->whereIn('company_id', $companyIds);
                            }
                        }

                        if (empty($groupIds) && empty($companyIds)) {
                            $query->whereRaw('1 = 0');
                        }
                    });

                $this->applyRelationshipManagerCustomerTypeFilter($customersQuery, $filters['customer_type']);

                $customers = $customersQuery
                    ->orderBy('registered_name')
                    ->orderBy('first_name')
                    ->orderBy('last_name')
                    ->get([
                        'id',
                        'first_name',
                        'last_name',
                        'registered_name',
                        'phone',
                        'status',
                        'customer_group_id',
                        'company_id',
                        'customer_type',
                    ]);

                $customerIds = $customers->pluck('id')->unique()->values();
                $loans = collect();

                if ($customerIds->isNotEmpty()) {
                    $loansQuery = Loan::query()
                        ->whereIn('customer_id', $customerIds->all())
                        ->with([
                            'customer:id,first_name,last_name,registered_name,phone,customer_group_id,company_id,customer_type',
                            'customer.customerGroup:id,name,relationship_manager_id',
                            'customer.company:id,name',
                            'customerGroup:id,name,relationship_manager_id',
                            'loanProduct:id,name',
                            'channel:id,name,type',
                            'disbursementFinancialInstitution:id,name',
                            'disbursementFinancialInstitutionBranch:id,name',
                            'paymentSchedules:id,loan_id,due_date,remaining_amount,status,days_overdue',
                        ]);

                    $this->applyRelationshipManagerLoanTypeFilter($loansQuery, $filters['customer_type']);
                    $loans = $loansQuery->get();
                }

                $portfolioLoans = $loans
                    ->whereIn('status', ['approved', 'active', 'defaulted'])
                    ->values();

                $portfolioLoans = $this->filterRelationshipManagerLoansByParBucket($portfolioLoans, $filters['par_bucket'])
                    ->values();

                $parMetrics = $this->calculateRelationshipManagerParMetrics($portfolioLoans);
                $scopedCustomerIds = $filters['par_bucket'] === 'all'
                    ? $customerIds
                    : $portfolioLoans->pluck('customer_id')->unique()->values();
                $scopedCustomers = $customers->whereIn('id', $scopedCustomerIds->all())->values();
                $scopedGroupIds = $scopedCustomers->pluck('customer_group_id')->filter()->unique()->values();
                $scopedGroups = $filters['customer_type'] === 'individual'
                    ? collect()
                    : $groups->whereIn('id', $scopedGroupIds->all())->values();
                $scopedLoanIds = $portfolioLoans->pluck('id')->unique()->values();

                $disbursementCount = 0;
                $disbursementAmount = 0.0;
                $pendingLoanBalances = 0.0;
                $repaymentCount = 0;
                $collectionsAmount = 0.0;
                $repaymentRows = collect();

                if ($scopedCustomerIds->isNotEmpty()) {
                    $disbursementQuery = Loan::query()
                        ->whereIn('customer_id', $scopedCustomerIds->all())
                        ->where('disbursement_status', 'completed')
                        ->whereNotNull('disbursed_at');

                    $this->applyRelationshipManagerLoanTypeFilter($disbursementQuery, $filters['customer_type']);
                    if ($filters['par_bucket'] !== 'all') {
                        if ($scopedLoanIds->isNotEmpty()) {
                            $disbursementQuery->whereIn('id', $scopedLoanIds->all());
                        } else {
                            $disbursementQuery->whereRaw('1 = 0');
                        }
                    }

                    if ($rangeStart) {
                        $disbursementQuery->where('disbursed_at', '>=', $rangeStart);
                    }
                    if ($rangeEnd) {
                        $disbursementQuery->where('disbursed_at', '<=', $rangeEnd);
                    }

                    $disbursementCount = (int) (clone $disbursementQuery)->count();
                    $disbursementAmount = (float) (clone $disbursementQuery)->sum('principal_amount');

                    $pendingLoansQuery = Loan::query()
                        ->whereIn('customer_id', $scopedCustomerIds->all())
                        ->where(function (Builder $query) {
                            $query->where('status', 'pending_approval')
                                ->orWhereIn('disbursement_status', ['pending', 'processing']);
                        });

                    $this->applyRelationshipManagerLoanTypeFilter($pendingLoansQuery, $filters['customer_type']);

                    $pendingLoanBalances = (float) $pendingLoansQuery
                        ->get(['id', 'total_amount', 'outstanding_balance'])
                        ->sum(function (Loan $loan) {
                            $outstandingBalance = (float) $loan->outstanding_balance;
                            return $outstandingBalance > 0 ? $outstandingBalance : (float) $loan->total_amount;
                        });

                    $collectionsQuery = LoanRepayment::query()
                        ->join('repayments', 'repayments.id', '=', 'loan_repayments.repayment_id')
                        ->join('loans', 'loans.id', '=', 'loan_repayments.loan_id')
                        ->whereIn('loans.customer_id', $scopedCustomerIds->all())
                        ->where('repayments.status', 'completed');

                    $this->applyRelationshipManagerLoanTypeFilter($collectionsQuery, $filters['customer_type'], 'loans.customer_group_id');
                    if ($filters['par_bucket'] !== 'all') {
                        if ($scopedLoanIds->isNotEmpty()) {
                            $collectionsQuery->whereIn('loan_repayments.loan_id', $scopedLoanIds->all());
                        } else {
                            $collectionsQuery->whereRaw('1 = 0');
                        }
                    }

                    if ($rangeStart) {
                        $collectionsQuery->where('repayments.processed_at', '>=', $rangeStart);
                    }
                    if ($rangeEnd) {
                        $collectionsQuery->where('repayments.processed_at', '<=', $rangeEnd);
                    }

                    $collectionsAmount = (float) (clone $collectionsQuery)->sum('loan_repayments.amount');
                    $repaymentCount = (int) (clone $collectionsQuery)
                        ->distinct('loan_repayments.repayment_id')
                        ->count('loan_repayments.repayment_id');

                    if ($filters['mode'] === 'detailed') {
                        $repaymentRows = (clone $collectionsQuery)
                            ->join('customers', 'customers.id', '=', 'loans.customer_id')
                            ->leftJoin('channels', 'channels.id', '=', 'repayments.channel_id')
                            ->select([
                                'repayments.repayment_number',
                                'repayments.processed_at',
                                'loan_repayments.amount',
                                'loan_repayments.principal_amount',
                                'loan_repayments.interest_amount',
                                'loan_repayments.processing_fee_amount',
                                'loans.loan_number',
                                'customers.first_name',
                                'customers.last_name',
                                'customers.registered_name',
                                'channels.name as channel_name',
                            ])
                            ->orderByDesc('repayments.processed_at')
                            ->get()
                            ->map(function ($row) {
                                $name = trim((string) (($row->registered_name ?: '') !== '' ? $row->registered_name : "{$row->first_name} {$row->last_name}"));

                                return [
                                    'repayment_number' => $row->repayment_number,
                                    'processed_at' => $row->processed_at ? Carbon::parse($row->processed_at) : null,
                                    'loan_number' => $row->loan_number,
                                    'customer_name' => $name !== '' ? $name : 'N/A',
                                    'channel_name' => $row->channel_name ?? 'N/A',
                                    'amount' => (float) $row->amount,
                                    'principal_amount' => (float) $row->principal_amount,
                                    'interest_amount' => (float) $row->interest_amount,
                                    'processing_fee_amount' => (float) $row->processing_fee_amount,
                                ];
                            })
                            ->values();
                    }
                }

                $outstandingBalance = (float) $portfolioLoans->sum('outstanding_balance');
                $portfolioValue = (float) $portfolioLoans->sum('total_amount');

                $details = [
                    'customers' => collect(),
                    'groups' => collect(),
                    'loans' => collect(),
                    'repayments' => collect(),
                ];

                if ($filters['mode'] === 'detailed') {
                    $details = $this->getRelationshipManagerDetailRows(
                        $scopedCustomers,
                        $scopedGroups,
                        $filters['par_bucket'] === 'all' ? $loans : $portfolioLoans,
                        $repaymentRows,
                        $filters['customer_type']
                    );
                }

                return [
                    'include_row' => $filters['par_bucket'] === 'all' || $portfolioLoans->isNotEmpty(),
                    'manager' => $manager,
                    'total_portfolio_value' => $portfolioValue,
                    'total_outstanding_balance' => $outstandingBalance,
                    'par_amount' => $parMetrics['par_amount'],
                    'par_ratio' => $outstandingBalance > 0 ? ($parMetrics['par_amount'] / $outstandingBalance) * 100 : 0.0,
                    'par_status' => $parMetrics['par_status'],
                    'par30_amount' => $parMetrics['par30_amount'],
                    'par60_amount' => $parMetrics['par60_amount'],
                    'par90_amount' => $parMetrics['par90_amount'],
                    'individual_customers_count' => (int) $scopedCustomers->whereNull('customer_group_id')->count(),
                    'groups_count' => $filters['customer_type'] === 'individual' ? 0 : (int) $scopedGroups->count(),
                    'loans_disbursed_count' => $disbursementCount,
                    'loans_disbursed_amount' => $disbursementAmount,
                    'pending_loan_balances' => $pendingLoanBalances,
                    'repayments_count' => $repaymentCount,
                    'collections_amount' => $collectionsAmount,
                    'details' => $details,
                ];
            })
            ->filter(fn (array $row) => $row['include_row'])
            ->map(function (array $row) {
                unset($row['include_row']);
                return $row;
            })
            ->values();

        $summary = [
            'total_portfolio_value' => (float) $reportRows->sum('total_portfolio_value'),
            'total_outstanding_balance' => (float) $reportRows->sum('total_outstanding_balance'),
            'total_par_amount' => (float) $reportRows->sum('par_amount'),
            'total_disbursed_amount' => (float) $reportRows->sum('loans_disbursed_amount'),
            'total_collections_amount' => (float) $reportRows->sum('collections_amount'),
            'total_repayments_count' => (int) $reportRows->sum('repayments_count'),
        ];

        $summary['par_ratio'] = $summary['total_outstanding_balance'] > 0
            ? ($summary['total_par_amount'] / $summary['total_outstanding_balance']) * 100
            : 0.0;

        return [
            'branchOptions' => $branchOptions,
            'relationshipManagers' => $relationshipManagers,
            'reportRows' => $reportRows,
            'filters' => $filters,
            'summary' => $summary,
            'generatedAt' => now(),
        ];
    }

    private function applyRelationshipManagerCustomerTypeFilter(Builder $query, string $customerType): void
    {
        if ($customerType === 'individual') {
            $query->whereNull('customer_group_id');
            return;
        }

        if ($customerType === 'group') {
            $query->whereNotNull('customer_group_id');
        }
    }

    private function applyRelationshipManagerLoanTypeFilter(Builder $query, string $customerType, string $column = 'customer_group_id'): void
    {
        if ($customerType === 'individual') {
            $query->whereNull($column);
            return;
        }

        if ($customerType === 'group') {
            $query->whereNotNull($column);
        }
    }

    private function filterRelationshipManagerLoansByParBucket(Collection $loans, string $parBucket): Collection
    {
        $normalized = strtolower($parBucket);

        if ($normalized === 'all') {
            return $loans->values();
        }

        return $loans->filter(function ($loan) use ($normalized) {
            if (!($loan instanceof Loan)) {
                return false;
            }

            $parStatus = strtolower($this->extractLoanParData($loan)['par_status']);

            if ($normalized === 'at_risk') {
                return in_array($parStatus, ['par30', 'par60', 'par90'], true);
            }

            return $parStatus === $normalized;
        })->values();
    }

    private function calculateRelationshipManagerParMetrics(Collection $loans): array
    {
        $metrics = [
            'par_amount' => 0.0,
            'par30_amount' => 0.0,
            'par60_amount' => 0.0,
            'par90_amount' => 0.0,
            'par30_count' => 0,
            'par60_count' => 0,
            'par90_count' => 0,
            'par_status' => 'Current',
        ];

        foreach ($loans as $loan) {
            if (!($loan instanceof Loan)) {
                continue;
            }

            $parData = $this->extractLoanParData($loan);
            if ($parData['overdue_amount'] <= 0 || $parData['par_status'] === 'Current') {
                continue;
            }

            $metrics['par_amount'] += $parData['overdue_amount'];

            if ($parData['par_status'] === 'PAR30') {
                $metrics['par30_amount'] += $parData['overdue_amount'];
                $metrics['par30_count']++;
            } elseif ($parData['par_status'] === 'PAR60') {
                $metrics['par60_amount'] += $parData['overdue_amount'];
                $metrics['par60_count']++;
            } elseif ($parData['par_status'] === 'PAR90') {
                $metrics['par90_amount'] += $parData['overdue_amount'];
                $metrics['par90_count']++;
            }
        }

        if ($metrics['par90_count'] > 0) {
            $metrics['par_status'] = 'PAR90';
        } elseif ($metrics['par60_count'] > 0) {
            $metrics['par_status'] = 'PAR60';
        } elseif ($metrics['par30_count'] > 0) {
            $metrics['par_status'] = 'PAR30';
        }

        return $metrics;
    }

    /**
     * @return array{overdue_amount: float, days_overdue: int, par_status: string}
     */
    private function extractLoanParData(Loan $loan): array
    {
        $today = Carbon::today();
        $schedules = $loan->relationLoaded('paymentSchedules')
            ? $loan->paymentSchedules
            : $loan->paymentSchedules()->get();

        $overdueSchedules = $schedules->filter(function ($schedule) use ($today) {
            $dueDate = $schedule->due_date instanceof Carbon
                ? $schedule->due_date
                : ($schedule->due_date ? Carbon::parse($schedule->due_date) : null);

            return (string) $schedule->status === 'overdue'
                || ($dueDate && $dueDate->lt($today) && (float) $schedule->remaining_amount > 0);
        });

        $overdueAmount = (float) $overdueSchedules->sum(function ($schedule) {
            return (float) $schedule->remaining_amount;
        });

        $daysOverdue = (int) $overdueSchedules->max(function ($schedule) use ($today) {
            if (!empty($schedule->days_overdue)) {
                return (int) $schedule->days_overdue;
            }

            $dueDate = $schedule->due_date instanceof Carbon
                ? $schedule->due_date
                : ($schedule->due_date ? Carbon::parse($schedule->due_date) : null);

            return $dueDate ? max(0, $dueDate->diffInDays($today, false)) : 0;
        });

        $parStatus = 'Current';
        if ($daysOverdue >= 90) {
            $parStatus = 'PAR90';
        } elseif ($daysOverdue >= 60) {
            $parStatus = 'PAR60';
        } elseif ($daysOverdue >= 30) {
            $parStatus = 'PAR30';
        }

        return [
            'overdue_amount' => $overdueAmount,
            'days_overdue' => $daysOverdue,
            'par_status' => $parStatus,
        ];
    }

    private function getRelationshipManagerDetailRows(
        Collection $customers,
        Collection $groups,
        Collection $loans,
        Collection $repayments,
        string $customerType
    ): array {
        $customerRows = $customers
            ->map(function (Customer $customer) {
                $name = trim((string) ($customer->registered_name ?: $customer->full_name));
                if ($name === '') {
                    $name = 'Customer #'.$customer->id;
                }

                return [
                    'id' => $customer->id,
                    'name' => $name,
                    'phone' => $customer->phone,
                    'status' => $customer->status,
                    'portfolio_type' => $customer->customer_group_id ? 'group' : 'individual',
                    'group_name' => $customer->customerGroup?->name,
                    'company_name' => $customer->company?->name,
                ];
            })
            ->values();

        $groupRows = $customerType === 'individual'
            ? collect()
            : $groups->map(function (CustomerGroup $group) {
                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'code' => $group->code,
                    'customers_count' => (int) ($group->customers_count ?? 0),
                ];
            })->values();

        $loanRows = $loans
            ->map(function (Loan $loan) {
                $parData = $this->extractLoanParData($loan);
                $customerName = trim((string) ($loan->customer?->registered_name ?: $loan->customer?->full_name));
                if ($customerName === '') {
                    $customerName = 'N/A';
                }

                return [
                    'id' => $loan->id,
                    'loan_number' => $loan->loan_number,
                    'customer_name' => $customerName,
                    'loan_product' => $loan->loanProduct?->name ?? 'N/A',
                    'status' => $loan->status,
                    'loan_start_date' => $loan->loan_start_date,
                    'principal_amount' => (float) $loan->principal_amount,
                    'total_amount' => (float) $loan->total_amount,
                    'outstanding_balance' => (float) $loan->outstanding_balance,
                    'overdue_amount' => $parData['overdue_amount'],
                    'par_status' => $parData['par_status'],
                    'disbursement_channel' => $loan->channel?->name ?? 'N/A',
                    'channel_type' => $loan->disbursementChannelTypeLabel(),
                    'disbursement_destination' => $loan->disbursementDestinationSummary() ?: 'N/A',
                ];
            })
            ->sortByDesc(function (array $row) {
                return $row['loan_start_date'] instanceof Carbon
                    ? $row['loan_start_date']->timestamp
                    : 0;
            })
            ->values();

        return [
            'customers' => $customerRows,
            'groups' => $groupRows,
            'loans' => $loanRows,
            'repayments' => $repayments->values(),
        ];
    }

    private function relationshipManagerExportDataset(array $payload): string
    {
        $dataset = strtolower((string) ($payload['filters']['export_dataset'] ?? 'all'));
        return in_array($dataset, ['all', 'summary', 'customers', 'loans', 'repayments'], true)
            ? $dataset
            : 'all';
    }

    private function normalizeRelationshipManagerExcelTabs(array $tabs, string $fallbackDataset = 'all'): array
    {
        $allowedTabs = ['summary', 'customers', 'loans', 'repayments'];

        $normalizedTabs = collect($tabs)
            ->map(fn ($tab) => strtolower((string) $tab))
            ->filter(fn (string $tab) => in_array($tab, $allowedTabs, true))
            ->unique()
            ->values()
            ->all();

        if (empty($normalizedTabs)) {
            $normalizedTabs = in_array($fallbackDataset, $allowedTabs, true)
                ? [$fallbackDataset]
                : $allowedTabs;
        }

        return array_values(array_intersect($allowedTabs, $normalizedTabs));
    }

    private function relationshipManagerSheetTitle(string $dataset): string
    {
        return match ($dataset) {
            'summary' => 'Summary',
            'customers' => 'Customers',
            'loans' => 'Loans',
            'repayments' => 'Repayments',
            default => 'Report',
        };
    }

    private function relationshipManagerExportHeadings(string $dataset): array
    {
        return match ($dataset) {
            'customers' => [
                'Relationship Manager',
                'Branch',
                'Customer Name',
                'Customer Type',
                'Phone',
                'Status',
                'Group',
                'Company',
            ],
            'loans' => [
                'Relationship Manager',
                'Branch',
                'Loan Number',
                'Customer',
                'Product',
                'Status',
                'Start Date',
                'Principal Amount',
                'Booked Loan Total',
                'Projected Repayment Total',
                'Booked Outstanding Balance',
                'Overdue Amount',
                'PAR Status',
                'Disbursement Channel',
                'Channel Type',
                'Disbursement Destination',
            ],
            'repayments' => [
                'Relationship Manager',
                'Branch',
                'Repayment Number',
                'Loan Number',
                'Customer',
                'Processed At',
                'Amount',
                'Principal',
                'Interest',
                'Processing Fee',
                'Channel',
            ],
            default => [
                'Relationship Manager',
                'Branch',
                'Portfolio Value',
                'Booked Outstanding Balance',
                'PAR Amount',
                'PAR Ratio %',
                'PAR Status',
                'PAR30 Amount',
                'PAR60 Amount',
                'PAR90 Amount',
                'Individual Customers',
                'Groups Assigned',
                'Loans Disbursed',
                'Disbursed Amount',
                'Pending Loan Balances',
                'Repayments Count',
                'Collections Amount',
            ],
        };
    }

    private function relationshipManagerExportRows(array $payload, string $dataset): array
    {
        $rows = [];

        foreach ($payload['reportRows'] as $row) {
            $managerName = $row['manager']->full_name;
            $branchName = $row['manager']->branch?->name ?? 'N/A';

            if ($dataset === 'summary') {
                $rows[] = [
                    $managerName,
                    $branchName,
                    round((float) $row['total_portfolio_value'], 2),
                    round((float) $row['total_outstanding_balance'], 2),
                    round((float) $row['par_amount'], 2),
                    round((float) $row['par_ratio'], 2),
                    $row['par_status'],
                    round((float) $row['par30_amount'], 2),
                    round((float) $row['par60_amount'], 2),
                    round((float) $row['par90_amount'], 2),
                    (int) $row['individual_customers_count'],
                    (int) $row['groups_count'],
                    (int) $row['loans_disbursed_count'],
                    round((float) $row['loans_disbursed_amount'], 2),
                    round((float) $row['pending_loan_balances'], 2),
                    (int) $row['repayments_count'],
                    round((float) $row['collections_amount'], 2),
                ];
                continue;
            }

            if ($dataset === 'customers') {
                foreach ($row['details']['customers'] as $customer) {
                    $rows[] = [
                        $managerName,
                        $branchName,
                        $customer['name'],
                        ucfirst((string) $customer['portfolio_type']),
                        (string) ($customer['phone'] ?? ''),
                        ucfirst((string) $customer['status']),
                        $customer['group_name'] ?? '',
                        $customer['company_name'] ?? '',
                    ];
                }
                continue;
            }

            if ($dataset === 'loans') {
                foreach ($row['details']['loans'] as $loan) {
                    $rows[] = [
                        $managerName,
                        $branchName,
                        $loan['loan_number'],
                        $loan['customer_name'],
                        $loan['loan_product'] ?? '',
                        ucfirst(str_replace('_', ' ', (string) $loan['status'])),
                        $loan['loan_start_date'] instanceof Carbon ? $loan['loan_start_date']->toDateString() : '',
                        round((float) $loan['principal_amount'], 2),
                        round((float) $loan['total_amount'], 2),
                        round((float) $loan['outstanding_balance'], 2),
                        round((float) $loan['overdue_amount'], 2),
                        $loan['par_status'],
                        $loan['disbursement_channel'] ?? '',
                        $loan['channel_type'] ?? '',
                        $loan['disbursement_destination'] ?? '',
                    ];
                }
                continue;
            }

            if ($dataset === 'repayments') {
                foreach ($row['details']['repayments'] as $repayment) {
                    $rows[] = [
                        $managerName,
                        $branchName,
                        $repayment['repayment_number'],
                        $repayment['loan_number'],
                        $repayment['customer_name'],
                        $repayment['processed_at'] instanceof Carbon ? $repayment['processed_at']->format('Y-m-d H:i:s') : '',
                        round((float) $repayment['amount'], 2),
                        round((float) $repayment['principal_amount'], 2),
                        round((float) $repayment['interest_amount'], 2),
                        round((float) $repayment['processing_fee_amount'], 2),
                        $repayment['channel_name'],
                    ];
                }
            }
        }

        return $rows;
    }

    private function exportRelationshipManagerExcel(array $payload)
    {
        $datasets = $this->normalizeRelationshipManagerExcelTabs(
            is_array($payload['filters']['excel_tabs'] ?? null) ? $payload['filters']['excel_tabs'] : [],
            $this->relationshipManagerExportDataset($payload)
        );

        $sheets = collect($datasets)->map(function (string $sheetDataset) use ($payload) {
            return [
                'title' => $this->relationshipManagerSheetTitle($sheetDataset),
                'headings' => $this->relationshipManagerExportHeadings($sheetDataset),
                'rows' => $this->relationshipManagerExportRows($payload, $sheetDataset),
            ];
        })->all();

        $filename = 'relationship_manager_report_'.now()->format('Y-m-d_His').'.xlsx';

        return Excel::download(new class($sheets) implements WithMultipleSheets {
            public function __construct(private readonly array $sheets)
            {
            }

            public function sheets(): array
            {
                return array_map(function (array $sheet) {
                    return new class($sheet) implements FromCollection, WithHeadings, WithTitle, WithStyles {
                        public function __construct(private readonly array $sheet)
                        {
                        }

                        public function collection()
                        {
                            return collect($this->sheet['rows']);
                        }

                        public function headings(): array
                        {
                            return $this->sheet['headings'];
                        }

                        public function title(): string
                        {
                            return $this->sheet['title'];
                        }

                        public function styles(Worksheet $sheet)
                        {
                            return [
                                1 => ['font' => ['bold' => true, 'size' => 11]],
                            ];
                        }
                    };
                }, $this->sheets);
            }
        }, $filename);
    }

    private function exportRelationshipManagerCsv(array $payload)
    {
        $dataset = $this->relationshipManagerExportDataset($payload);
        $dataset = $dataset === 'all' ? 'summary' : $dataset;

        $rows = $this->relationshipManagerExportRows($payload, $dataset);
        $headings = $this->relationshipManagerExportHeadings($dataset);
        $filename = 'relationship_manager_'.$dataset.'_report_'.now()->format('Y-m-d_His').'.csv';

        return Response::streamDownload(function () use ($rows, $headings) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headings);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function exportRelationshipManagerPdf(array $payload)
    {
        $dataset = $this->relationshipManagerExportDataset($payload);
        $dataset = $dataset === 'all' ? 'summary' : $dataset;
        $payload['filters']['export_dataset'] = $dataset;
        $filename = 'relationship_manager_'.$dataset.'_report_'.now()->format('Y-m-d_His').'.pdf';

        return Pdf::loadView('admin.reports.relationship-manager-pdf', $payload)
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }

    /**
     * Arrears Report - Loans with overdue installments
     */
    public function arrears(Request $request): View
    {
        // Only get loans that have overdue payment schedule items (installments)
        $query = Loan::with([
            'customer.company.relationshipManager',
            'loanProduct',
            'customerGroup',
            'paymentSchedules'
        ])
            ->whereIn('status', ['approved', 'active'])
            ->whereHas('paymentSchedules', function ($q) {
                // Only loans with overdue installments (due date passed and remaining amount > 0)
                $q->where('due_date', '<', Carbon::today())
                  ->where('remaining_amount', '>', 0);
            });

        // Filters
        if ($request->has('loan_product_id') && $request->loan_product_id) {
            $query->where('loan_product_id', $request->loan_product_id);
        }

        if ($request->has('customer_group_id') && $request->customer_group_id) {
            $query->where('customer_group_id', $request->customer_group_id);
        }

        if ($request->has('days_overdue_min') && $request->days_overdue_min) {
            $minDate = Carbon::today()->subDays($request->days_overdue_min);
            $query->whereHas('paymentSchedules', function ($q) use ($minDate) {
                $q->where('due_date', '<=', $minDate)
                  ->where('remaining_amount', '>', 0);
            });
        }

        if ($request->has('days_overdue_max') && $request->days_overdue_max) {
            $maxDate = Carbon::today()->subDays($request->days_overdue_max);
            $query->whereHas('paymentSchedules', function ($q) use ($maxDate) {
                $q->where('due_date', '>=', $maxDate)
                  ->where('remaining_amount', '>', 0);
            });
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

        // Get loans with overdue installments
        $loans = $query->get();

        // Calculate arrears data - only for loans with overdue installments
        $arrearsData = $loans->map(function ($loan) {
            // Get overdue installments (payment schedules with due date passed and remaining amount)
            $overdueSchedules = $loan->paymentSchedules()
                ->where('due_date', '<', Carbon::today())
                ->where('remaining_amount', '>', 0)
                ->get();
            
            if ($overdueSchedules->isEmpty()) {
                return null; // Skip loans without overdue installments
            }
            
            // Calculate total overdue amount from installments
            $overdueAmount = $overdueSchedules->sum('remaining_amount');
            
            // Get the most overdue installment
            $mostOverdue = $overdueSchedules
                ->map(function ($schedule) {
                    // Update status and days overdue
                    $schedule->updateStatus();
                    return $schedule;
                })
                ->sortByDesc('days_overdue')
                ->first();
            
            return [
                'loan' => $loan,
                'overdue_amount' => $overdueAmount,
                'days_overdue' => $mostOverdue ? $mostOverdue->days_overdue : 0,
                'par_status' => $loan->getPARStatus(),
                'overdue_installments_count' => $overdueSchedules->count(),
            ];
        })->filter()->sortByDesc('days_overdue')->values();

        // Pagination
        $perPage = 20;
        $currentPage = $request->get('page', 1);
        $items = $arrearsData->slice(($currentPage - 1) * $perPage, $perPage)->values();
        $total = $arrearsData->count();
        $arrearsData = new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // Get filter options
        $loanProducts = LoanProduct::where('is_active', true)->orderBy('name')->get();
        $customerGroups = CustomerGroup::where('is_active', true)->orderBy('name')->get();

        return view('admin.reports.arrears', compact('arrearsData', 'loanProducts', 'customerGroups'));
    }

    /**
     * Export Arrears Report
     */
    public function exportArrears(Request $request)
    {
        // Only get loans that have overdue payment schedule items (installments)
        $query = Loan::with(['customer', 'loanProduct', 'customerGroup', 'paymentSchedules'])
            ->whereIn('status', ['approved', 'active'])
            ->whereHas('paymentSchedules', function ($q) {
                // Only loans with overdue installments
                $q->where('due_date', '<', Carbon::today())
                  ->where('remaining_amount', '>', 0);
            });

        // Apply same filters as index
        if ($request->has('loan_product_id') && $request->loan_product_id) {
            $query->where('loan_product_id', $request->loan_product_id);
        }

        if ($request->has('customer_group_id') && $request->customer_group_id) {
            $query->where('customer_group_id', $request->customer_group_id);
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('loan_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $loans = $query->get();

        // Prepare data for export
        $exportData = [];
        foreach ($loans as $loan) {
            // Get overdue installments
            $overdueSchedules = $loan->paymentSchedules()
                ->where('due_date', '<', Carbon::today())
                ->where('remaining_amount', '>', 0)
                ->get();
            
            if ($overdueSchedules->isEmpty()) {
                continue; // Skip loans without overdue installments
            }
            
            $overdueAmount = $overdueSchedules->sum('remaining_amount');
            
            // Update status for all schedules
            foreach ($overdueSchedules as $schedule) {
                $schedule->updateStatus();
            }
            
            $mostOverdue = $overdueSchedules
                ->sortByDesc('days_overdue')
                ->first();
            
            $nextDue = $loan->paymentSchedules()
                ->where('remaining_amount', '>', 0)
                ->orderBy('due_date')
                ->first();

            $relationshipManager = $loan->customer->company->relationshipManager ?? null;
            
            $exportData[] = [
                'Loan Number' => $loan->loan_number,
                'Customer Name' => $loan->customer->full_name ?? 'N/A',
                'Customer Email' => $loan->customer->email ?? 'N/A',
                'Customer Phone' => $loan->customer->phone ?? 'N/A',
                'Product' => $loan->loanProduct->name ?? 'N/A',
                'Group' => $loan->customerGroup->name ?? 'N/A',
                'Company' => $loan->customer->company->name ?? 'N/A',
                'Relationship Manager' => $relationshipManager ? ($relationshipManager->first_name . ' ' . $relationshipManager->last_name) : 'N/A',
                'Principal Amount' => number_format($loan->principal_amount, 2),
                'Booked Loan Total' => number_format($loan->total_amount, 2),
                'Projected Repayment Total' => number_format($loan->getProjectedTotalAmount(), 2),
                'Booked Outstanding Balance' => number_format($loan->outstanding_balance, 2),
                'Overdue Amount' => number_format($overdueAmount, 2),
                'Days Overdue' => $mostOverdue ? $mostOverdue->days_overdue : 0,
                'Overdue Installments' => $overdueSchedules->count(),
                'PAR Status' => $loan->getPARStatus() ?? 'N/A',
                'Last Payment Date' => $loan->loanRepayments()->latest('created_at')->first()?->created_at->format('Y-m-d') ?? 'N/A',
                'Next Due Date' => $nextDue ? $nextDue->due_date->format('Y-m-d') : 'N/A',
                'Loan Start Date' => $loan->loan_start_date->format('Y-m-d'),
            ];
        }

        $filename = 'arrears_report_' . now()->format('Y-m-d_His') . '.xlsx';
        
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
                return [
                    'Loan Number',
                    'Customer Name',
                    'Customer Email',
                    'Customer Phone',
                    'Product',
                    'Group',
                    'Company',
                    'Relationship Manager',
                    'Principal Amount',
                    'Booked Loan Total',
                    'Projected Repayment Total',
                    'Booked Outstanding Balance',
                    'Overdue Amount',
                    'Days Overdue',
                    'Overdue Installments',
                    'PAR Status',
                    'Last Payment Date',
                    'Next Due Date',
                    'Loan Start Date',
                ];
            }

            public function columnWidths(): array
            {
                return [
                    'A' => 15, 'B' => 20, 'C' => 25, 'D' => 15, 'E' => 20,
                    'F' => 15, 'G' => 20, 'H' => 20, 'I' => 15, 'J' => 15,
                    'K' => 18, 'L' => 15, 'M' => 12, 'N' => 18, 'O' => 12,
                    'P' => 18, 'Q' => 15, 'R' => 15,
                ];
            }

            public function styles(Worksheet $sheet)
            {
                return [
                    1 => ['font' => ['bold' => true, 'size' => 12]],
                ];
            }
        }, $filename);
    }

    /**
     * Disbursement Report - Loans that have been disbursed
     */
    public function disbursements(Request $request): View
    {
        $query = Loan::with([
            'customer',
            'loanProduct',
            'customerGroup',
            'channel',
            'disbursementFinancialInstitution',
            'disbursementFinancialInstitutionBranch',
        ])
            ->where('disbursement_status', 'completed');

        $this->applyDisbursementFilters($request, $query);

        $disbursements = $query->latest('disbursed_at')
            ->paginate(20)
            ->withQueryString();

        // Get filter options
        $loanProducts = LoanProduct::where('is_active', true)->orderBy('name')->get();
        $channels = Channel::where('is_active', true)->orderBy('name')->get();

        return view('admin.reports.disbursements', compact('disbursements', 'loanProducts', 'channels'));
    }

    /**
     * Export Disbursement Report
     */
    public function exportDisbursements(Request $request)
    {
        $query = Loan::with([
            'customer',
            'loanProduct',
            'customerGroup',
            'channel',
            'disbursementFinancialInstitution',
            'disbursementFinancialInstitutionBranch',
        ])
            ->where('disbursement_status', 'completed');

        $this->applyDisbursementFilters($request, $query);

        $disbursements = $query->latest('disbursed_at')->get();

        $exportData = [];
        foreach ($disbursements as $loan) {
            $exportData[] = array_merge([
                'Loan Number' => $loan->loan_number,
                'Customer Name' => $loan->customer->full_name ?? 'N/A',
                'Customer Email' => $loan->customer->email ?? 'N/A',
                'Customer Phone' => $loan->customer->phone ?? 'N/A',
                'Product' => $loan->loanProduct->name ?? 'N/A',
                'Group' => $loan->customerGroup->name ?? 'N/A',
                'Principal Amount' => number_format($loan->principal_amount, 2),
                'Processing Fee' => number_format($loan->processing_fee, 2),
                'Interest Accrued' => number_format($loan->interest_accrued, 2),
                'Booked Loan Total' => number_format($loan->total_amount, 2),
                'Projected Repayment Total' => number_format($loan->getProjectedTotalAmount(), 2),
                'Disbursed At' => $loan->disbursed_at ? $loan->disbursed_at->format('Y-m-d H:i:s') : 'N/A',
                'Loan Start Date' => $loan->loan_start_date->format('Y-m-d'),
                'Tenure (Months)' => $loan->tenure_months,
                'Status' => ucfirst(str_replace('_', ' ', $loan->status)),
            ], $loan->disbursementDestinationExportColumns());
        }

        $filename = 'disbursements_report_' . now()->format('Y-m-d_His') . '.xlsx';
        
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
                $first = $this->data[0] ?? null;

                return $first ? array_keys($first) : [];
            }

            public function columnWidths(): array
            {
                return [];
            }

            public function styles(Worksheet $sheet)
            {
                return [
                    1 => ['font' => ['bold' => true, 'size' => 12]],
                ];
            }
        }, $filename);
    }

    /**
     * Collections Report - Repayments made
     */
    public function collections(Request $request): View
    {
        $query = Repayment::with(['customer', 'channel', 'loanRepayments.loan'])
            ->where('status', 'completed');

        // Filters
        if ($request->has('channel_id') && $request->channel_id) {
            $query->where('channel_id', $request->channel_id);
        }

        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('processed_at', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('processed_at', '<=', $request->date_to);
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('repayment_number', 'like', "%{$search}%")
                    ->orWhere('external_reference', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $collections = $query->latest('processed_at')->paginate(20);

        // Calculate summary statistics
        $summary = [
            'total_collections' => $collections->sum('total_amount'),
            'total_count' => $collections->total(),
            'by_channel' => $collections->groupBy('channel_id')->map(function ($group) {
                return [
                    'channel' => $group->first()->channel->name ?? 'N/A',
                    'count' => $group->count(),
                    'total' => $group->sum('total_amount'),
                ];
            }),
        ];

        // Get filter options
        $channels = Channel::where('is_active', true)->where('can_repay', true)->orderBy('name')->get();

        return view('admin.reports.collections', compact('collections', 'summary', 'channels'));
    }

    /**
     * Export Collections Report
     */
    public function exportCollections(Request $request)
    {
        $query = Repayment::with(['customer', 'channel', 'loanRepayments.loan'])
            ->where('status', 'completed');

        // Apply same filters
        if ($request->has('channel_id') && $request->channel_id) {
            $query->where('channel_id', $request->channel_id);
        }

        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('processed_at', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('processed_at', '<=', $request->date_to);
        }

        $collections = $query->latest('processed_at')->get();

        $exportData = [];
        foreach ($collections as $repayment) {
            $totalPrincipal = $repayment->loanRepayments->sum('principal_amount');
            $totalInterest = $repayment->loanRepayments->sum('interest_amount');
            $totalFee = $repayment->loanRepayments->sum('processing_fee_amount');

            $exportData[] = [
                'Repayment Number' => $repayment->repayment_number,
                'Date' => $repayment->created_at->format('Y-m-d'),
                'Customer Name' => $repayment->customer->full_name ?? 'N/A',
                'Customer Email' => $repayment->customer->email ?? 'N/A',
                'Customer Phone' => $repayment->customer->phone ?? 'N/A',
                'Channel' => $repayment->channel->name ?? 'N/A',
                'Total Amount' => number_format($repayment->total_amount, 2),
                'Principal' => number_format($totalPrincipal, 2),
                'Interest' => number_format($totalInterest, 2),
                'Processing Fee' => number_format($totalFee, 2),
                'External Reference' => $repayment->external_reference ?? 'N/A',
                'Processed At' => $repayment->processed_at ? $repayment->processed_at->format('Y-m-d H:i:s') : 'N/A',
            ];
        }

        $filename = 'collections_report_' . now()->format('Y-m-d_His') . '.xlsx';
        
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
                return [
                    'Repayment Number',
                    'Date',
                    'Customer Name',
                    'Customer Email',
                    'Customer Phone',
                    'Channel',
                    'Total Amount',
                    'Principal',
                    'Interest',
                    'Processing Fee',
                    'External Reference',
                    'Processed At',
                ];
            }

            public function columnWidths(): array
            {
                return [
                    'A' => 18, 'B' => 12, 'C' => 20, 'D' => 25, 'E' => 15,
                    'F' => 15, 'G' => 15, 'H' => 15, 'I' => 15, 'J' => 15,
                    'K' => 20, 'L' => 20,
                ];
            }

            public function styles(Worksheet $sheet)
            {
                return [
                    1 => ['font' => ['bold' => true, 'size' => 12]],
                ];
            }
        }, $filename);
    }

    /**
     * @return list<string>
     */
    protected function loanBookRelations(): array
    {
        return [
            'customer.company.relationshipManager',
            'loanProduct',
            'customerGroup',
            'channel',
            'disbursementFinancialInstitution',
            'disbursementFinancialInstitutionBranch',
        ];
    }

    protected function applyLoanBookScopeFilters(Builder $query, Request $request): Builder
    {
        if ($request->filled('loan_product_id')) {
            $query->where('loan_product_id', $request->loan_product_id);
        }

        if ($request->filled('customer_group_id')) {
            $query->where('customer_group_id', $request->customer_group_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('loan_start_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('loan_start_date', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('loan_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        return $query;
    }

    protected function loanBookQuery(Request $request): Builder
    {
        $query = Loan::with($this->loanBookRelations());
        $this->applyLoanBookScopeFilters($query, $request);

        $hasExplicitStatusFilter = $request->filled('status') || $request->filled('disbursement_status');

        if (! $hasExplicitStatusFilter && ! $request->boolean('show_all')) {
            $query->activePortfolio();
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('disbursement_status')) {
            $query->where('disbursement_status', $request->disbursement_status);
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildLoanBookStats(Request $request): array
    {
        $scopedQuery = Loan::query();
        $this->applyLoanBookScopeFilters($scopedQuery, $request);

        $portfolioQuery = (clone $scopedQuery)->activePortfolio();

        return [
            'total_loans' => (clone $scopedQuery)->count(),
            'active_loans' => (clone $portfolioQuery)->count(),
            'total_principal' => (clone $scopedQuery)->sum('principal_amount'),
            'active_principal' => (clone $portfolioQuery)->sum('principal_amount'),
            'total_outstanding' => (clone $portfolioQuery)->sum('outstanding_balance'),
            'total_disbursed' => (clone $scopedQuery)->disbursed()->sum('principal_amount'),
            'by_status' => (clone $scopedQuery)
                ->selectRaw('status, COUNT(*) as count, SUM(principal_amount) as total_principal, SUM(outstanding_balance) as total_outstanding')
                ->groupBy('status')
                ->get(),
            'by_product' => (clone $scopedQuery)
                ->selectRaw('loan_product_id, COUNT(*) as count, SUM(principal_amount) as total_principal')
                ->with('loanProduct')
                ->groupBy('loan_product_id')
                ->get(),
        ];
    }

    /**
     * Loan Book Report - Overall loan portfolio
     */
    public function loanBook(Request $request): View
    {
        Loan::syncActiveStatusForDisbursedLoans();

        $query = $this->loanBookQuery($request);
        $loans = $query->latest('loan_start_date')->paginate(20)->withQueryString();
        $stats = $this->buildLoanBookStats($request);

        $loanProducts = LoanProduct::where('is_active', true)->orderBy('name')->get();
        $customerGroups = CustomerGroup::where('is_active', true)->orderBy('name')->get();

        return view('admin.reports.loan-book', compact('loans', 'stats', 'loanProducts', 'customerGroups'));
    }

    /**
     * Export Loan Book Report
     */
    public function exportLoanBook(Request $request)
    {
        Loan::syncActiveStatusForDisbursedLoans();

        $loans = $this->loanBookQuery($request)->latest('loan_start_date')->get();

        $exportData = [];
        foreach ($loans as $loan) {
            $relationshipManager = $loan->customer->company->relationshipManager ?? null;
            
            $exportData[] = array_merge([
                'Loan Number' => $loan->loan_number,
                'Customer Name' => $loan->customer->full_name ?? 'N/A',
                'Customer Email' => $loan->customer->email ?? 'N/A',
                'Customer Phone' => $loan->customer->phone ?? 'N/A',
                'Product' => $loan->loanProduct->name ?? 'N/A',
                'Group' => $loan->customerGroup->name ?? 'N/A',
                'Company' => $loan->customer->company->name ?? 'N/A',
                'Relationship Manager' => $relationshipManager ? ($relationshipManager->first_name . ' ' . $relationshipManager->last_name) : 'N/A',
                'Principal Amount' => number_format($loan->principal_amount, 2),
                'Processing Fee' => number_format($loan->processing_fee, 2),
                'Interest Accrued' => number_format($loan->interest_accrued, 2),
                'Booked Loan Total' => number_format($loan->total_amount, 2),
                'Projected Repayment Total' => number_format($loan->getProjectedTotalAmount(), 2),
                'Amount Paid' => number_format($loan->amount_paid, 2),
                'Booked Outstanding Balance' => number_format($loan->outstanding_balance, 2),
                'Tenure (Months)' => $loan->tenure_months,
                'Start Date' => $loan->loan_start_date->format('Y-m-d'),
                'End Date' => $loan->loan_end_date->format('Y-m-d'),
                'Status' => ucfirst(str_replace('_', ' ', $loan->status)),
                'Disbursement Status' => ucfirst($loan->disbursement_status ?? 'N/A'),
                'Disbursed At' => $loan->disbursed_at ? $loan->disbursed_at->format('Y-m-d H:i:s') : 'N/A',
                'Accrual Type' => ucfirst(str_replace('_', ' ', $loan->accrual_type ?? 'N/A')),
            ], $loan->disbursementDestinationExportColumns(), [
                'Created At' => $loan->created_at->format('Y-m-d H:i:s'),
            ]);
        }

        $filename = 'loan_book_report_' . now()->format('Y-m-d_His') . '.xlsx';
        
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
                $first = $this->data[0] ?? null;

                return $first ? array_keys($first) : [];
            }

            public function columnWidths(): array
            {
                return [
                    'A' => 15, 'B' => 20, 'C' => 25, 'D' => 15, 'E' => 20,
                    'F' => 15, 'G' => 20, 'H' => 20, 'I' => 15, 'J' => 15,
                    'K' => 15, 'L' => 15, 'M' => 15, 'N' => 18, 'O' => 15,
                    'P' => 12, 'Q' => 12, 'R' => 15, 'S' => 18, 'T' => 20,
                    'U' => 15, 'V' => 20,
                ];
            }

            public function styles(Worksheet $sheet)
            {
                return [
                    1 => ['font' => ['bold' => true, 'size' => 12]],
                ];
            }
        }, $filename);
    }

    /**
     * Collection Split Report - Shows how repayments were split
     */
    public function collectionSplit(Request $request): View
    {
        $query = Repayment::with(['customer', 'channel', 'loanRepayments.loan'])
            ->where('status', 'completed');

        // Filters
        if ($request->has('channel_id') && $request->channel_id) {
            $query->where('channel_id', $request->channel_id);
        }

        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('processed_at', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('processed_at', '<=', $request->date_to);
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('repayment_number', 'like', "%{$search}%")
                    ->orWhere('external_reference', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $repayments = $query->latest('processed_at')->paginate(20);

        // Calculate summary statistics
        $summary = [
            'total_repayments' => $repayments->total(),
            'total_amount' => $repayments->sum('total_amount'),
            'total_principal' => $repayments->sum(function ($repayment) {
                return $repayment->loanRepayments->sum('principal_amount');
            }),
            'total_interest' => $repayments->sum(function ($repayment) {
                return $repayment->loanRepayments->sum('interest_amount');
            }),
            'total_processing_fee' => $repayments->sum(function ($repayment) {
                return $repayment->loanRepayments->sum('processing_fee_amount');
            }),
            'by_channel' => $repayments->groupBy('channel_id')->map(function ($group) {
                return [
                    'channel' => $group->first()->channel->name ?? 'N/A',
                    'count' => $group->count(),
                    'total' => $group->sum('total_amount'),
                    'principal' => $group->sum(function ($r) {
                        return $r->loanRepayments->sum('principal_amount');
                    }),
                    'interest' => $group->sum(function ($r) {
                        return $r->loanRepayments->sum('interest_amount');
                    }),
                    'fee' => $group->sum(function ($r) {
                        return $r->loanRepayments->sum('processing_fee_amount');
                    }),
                ];
            }),
        ];

        // Get filter options
        $channels = Channel::where('is_active', true)->where('can_repay', true)->orderBy('name')->get();

        return view('admin.reports.collection-split', compact('repayments', 'summary', 'channels'));
    }

    /**
     * Export Collection Split Report
     */
    public function exportCollectionSplit(Request $request)
    {
        $query = Repayment::with(['customer', 'channel', 'loanRepayments.loan'])
            ->where('status', 'completed');

        // Apply same filters
        if ($request->has('channel_id') && $request->channel_id) {
            $query->where('channel_id', $request->channel_id);
        }

        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('processed_at', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('processed_at', '<=', $request->date_to);
        }

        $repayments = $query->latest('processed_at')->get();

        $exportData = [];
        foreach ($repayments as $repayment) {
            $totalPrincipal = $repayment->loanRepayments->sum('principal_amount');
            $totalInterest = $repayment->loanRepayments->sum('interest_amount');
            $totalFee = $repayment->loanRepayments->sum('processing_fee_amount');
            $total = $repayment->total_amount;

            $principalPercent = $total > 0 ? ($totalPrincipal / $total) * 100 : 0;
            $interestPercent = $total > 0 ? ($totalInterest / $total) * 100 : 0;
            $feePercent = $total > 0 ? ($totalFee / $total) * 100 : 0;

            $exportData[] = [
                'Repayment Number' => $repayment->repayment_number,
                'Date' => $repayment->created_at->format('Y-m-d'),
                'Customer Name' => $repayment->customer->full_name ?? 'N/A',
                'Customer Email' => $repayment->customer->email ?? 'N/A',
                'Channel' => $repayment->channel->name ?? 'N/A',
                'Total Amount' => number_format($repayment->total_amount, 2),
                'Principal Amount' => number_format($totalPrincipal, 2),
                'Interest Amount' => number_format($totalInterest, 2),
                'Processing Fee Amount' => number_format($totalFee, 2),
                'Principal %' => number_format($principalPercent, 2) . '%',
                'Interest %' => number_format($interestPercent, 2) . '%',
                'Fee %' => number_format($feePercent, 2) . '%',
                'External Reference' => $repayment->external_reference ?? 'N/A',
                'Processed At' => $repayment->processed_at ? $repayment->processed_at->format('Y-m-d H:i:s') : 'N/A',
            ];
        }

        $filename = 'collection_split_report_' . now()->format('Y-m-d_His') . '.xlsx';
        
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
                return [
                    'Repayment Number',
                    'Date',
                    'Customer Name',
                    'Customer Email',
                    'Channel',
                    'Total Amount',
                    'Principal Amount',
                    'Interest Amount',
                    'Processing Fee Amount',
                    'Principal %',
                    'Interest %',
                    'Fee %',
                    'External Reference',
                    'Processed At',
                ];
            }

            public function columnWidths(): array
            {
                return [
                    'A' => 18, 'B' => 12, 'C' => 20, 'D' => 25, 'E' => 15,
                    'F' => 15, 'G' => 15, 'H' => 15, 'I' => 18, 'J' => 12,
                    'K' => 12, 'L' => 10, 'M' => 20, 'N' => 20,
                ];
            }

            public function styles(Worksheet $sheet)
            {
                return [
                    1 => ['font' => ['bold' => true, 'size' => 12]],
                ];
            }
        }, $filename);
    }

    /**
     * Loan Performance Report - Performance by Products, Groups, Companies
     */
    public function loanPerformance(Request $request): View
    {
        $query = Loan::with(['customer.company', 'customer.company.relationshipManager', 'loanProduct', 'customerGroup']);

        // Filters
        if ($request->has('loan_product_id') && $request->loan_product_id) {
            $query->where('loan_product_id', $request->loan_product_id);
        }

        if ($request->has('customer_group_id') && $request->customer_group_id) {
            $query->where('customer_group_id', $request->customer_group_id);
        }

        if ($request->has('company_id') && $request->company_id) {
            $query->whereHas('customer', function ($q) use ($request) {
                $q->where('company_id', $request->company_id);
            });
        }

        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('loan_start_date', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('loan_start_date', '<=', $request->date_to);
        }

        // Performance by Product
        $performanceByProduct = Loan::selectRaw('
                loan_product_id,
                COUNT(*) as total_loans,
                SUM(principal_amount) as total_principal,
                SUM(total_amount) as total_disbursed,
                SUM(amount_paid) as total_collected,
                SUM(outstanding_balance) as total_outstanding,
                AVG(outstanding_balance) as avg_outstanding,
                COUNT(CASE WHEN status = "active" THEN 1 END) as active_loans,
                COUNT(CASE WHEN status = "settled" THEN 1 END) as settled_loans,
                COUNT(CASE WHEN status = "defaulted" THEN 1 END) as defaulted_loans
            ')
            ->when($request->has('date_from') && $request->date_from, function ($q) use ($request) {
                $q->whereDate('loan_start_date', '>=', $request->date_from);
            })
            ->when($request->has('date_to') && $request->date_to, function ($q) use ($request) {
                $q->whereDate('loan_start_date', '<=', $request->date_to);
            })
            ->groupBy('loan_product_id')
            ->with('loanProduct')
            ->get()
            ->map(function ($item) {
                $item->collection_rate = $item->total_disbursed > 0 
                    ? ($item->total_collected / $item->total_disbursed) * 100 
                    : 0;
                return $item;
            });

        // Performance by Group
        $performanceByGroup = Loan::selectRaw('
                customer_group_id,
                COUNT(*) as total_loans,
                SUM(principal_amount) as total_principal,
                SUM(total_amount) as total_disbursed,
                SUM(amount_paid) as total_collected,
                SUM(outstanding_balance) as total_outstanding,
                COUNT(CASE WHEN status = "active" THEN 1 END) as active_loans,
                COUNT(CASE WHEN status = "settled" THEN 1 END) as settled_loans
            ')
            ->whereNotNull('customer_group_id')
            ->when($request->has('date_from') && $request->date_from, function ($q) use ($request) {
                $q->whereDate('loan_start_date', '>=', $request->date_from);
            })
            ->when($request->has('date_to') && $request->date_to, function ($q) use ($request) {
                $q->whereDate('loan_start_date', '<=', $request->date_to);
            })
            ->groupBy('customer_group_id')
            ->with('customerGroup')
            ->get()
            ->map(function ($item) {
                $item->collection_rate = $item->total_disbursed > 0 
                    ? ($item->total_collected / $item->total_disbursed) * 100 
                    : 0;
                return $item;
            });

        // Performance by Company
        $performanceByCompany = Loan::selectRaw('
                customers.company_id,
                COUNT(*) as total_loans,
                SUM(loans.principal_amount) as total_principal,
                SUM(loans.total_amount) as total_disbursed,
                SUM(loans.amount_paid) as total_collected,
                SUM(loans.outstanding_balance) as total_outstanding,
                COUNT(CASE WHEN loans.status = "active" THEN 1 END) as active_loans,
                COUNT(CASE WHEN loans.status = "settled" THEN 1 END) as settled_loans
            ')
            ->join('customers', 'loans.customer_id', '=', 'customers.id')
            ->whereNotNull('customers.company_id')
            ->when($request->has('date_from') && $request->date_from, function ($q) use ($request) {
                $q->whereDate('loans.loan_start_date', '>=', $request->date_from);
            })
            ->when($request->has('date_to') && $request->date_to, function ($q) use ($request) {
                $q->whereDate('loans.loan_start_date', '<=', $request->date_to);
            })
            ->groupBy('customers.company_id')
            ->get()
            ->map(function ($item) {
                $item->company = Company::with('relationshipManager')->find($item->company_id);
                $item->collection_rate = $item->total_disbursed > 0 
                    ? ($item->total_collected / $item->total_disbursed) * 100 
                    : 0;
                return $item;
            });

        // Get filter options
        $loanProducts = LoanProduct::where('is_active', true)->orderBy('name')->get();
        $customerGroups = CustomerGroup::where('is_active', true)->orderBy('name')->get();
        $companies = Company::where('status', 'active')->orderBy('name')->get();

        return view('admin.reports.loan-performance', compact(
            'performanceByProduct',
            'performanceByGroup',
            'performanceByCompany',
            'loanProducts',
            'customerGroups',
            'companies'
        ));
    }

    /**
     * Export Loan Performance Report
     */
    public function exportLoanPerformance(Request $request)
    {
        // Performance by Product
        $performanceByProduct = Loan::selectRaw('
                loan_product_id,
                COUNT(*) as total_loans,
                SUM(principal_amount) as total_principal,
                SUM(total_amount) as total_disbursed,
                SUM(amount_paid) as total_collected,
                SUM(outstanding_balance) as total_outstanding,
                COUNT(CASE WHEN status = "active" THEN 1 END) as active_loans,
                COUNT(CASE WHEN status = "settled" THEN 1 END) as settled_loans
            ')
            ->when($request->has('date_from') && $request->date_from, function ($q) use ($request) {
                $q->whereDate('loan_start_date', '>=', $request->date_from);
            })
            ->when($request->has('date_to') && $request->date_to, function ($q) use ($request) {
                $q->whereDate('loan_start_date', '<=', $request->date_to);
            })
            ->groupBy('loan_product_id')
            ->with('loanProduct')
            ->get();

        // Get performance by group and company
        $performanceByGroup = Loan::selectRaw('
                customer_group_id,
                COUNT(*) as total_loans,
                SUM(principal_amount) as total_principal,
                SUM(total_amount) as total_disbursed,
                SUM(amount_paid) as total_collected,
                SUM(outstanding_balance) as total_outstanding,
                COUNT(CASE WHEN status = "active" THEN 1 END) as active_loans,
                COUNT(CASE WHEN status = "settled" THEN 1 END) as settled_loans
            ')
            ->whereNotNull('customer_group_id')
            ->groupBy('customer_group_id')
            ->with('customerGroup')
            ->get();

        $performanceByCompany = Loan::selectRaw('
                customers.company_id,
                COUNT(*) as total_loans,
                SUM(loans.principal_amount) as total_principal,
                SUM(loans.total_amount) as total_disbursed,
                SUM(loans.amount_paid) as total_collected,
                SUM(loans.outstanding_balance) as total_outstanding,
                COUNT(CASE WHEN loans.status = "active" THEN 1 END) as active_loans,
                COUNT(CASE WHEN loans.status = "settled" THEN 1 END) as settled_loans
            ')
            ->join('customers', 'loans.customer_id', '=', 'customers.id')
            ->whereNotNull('customers.company_id')
            ->groupBy('customers.company_id')
            ->get();

        $exportData = [];
        
        // By Product
        $exportData[] = ['LOAN PERFORMANCE BY PRODUCT'];
        $exportData[] = [
            'Product', 'Total Loans', 'Total Principal', 'Total Disbursed',
            'Total Collected', 'Total Outstanding', 'Active Loans', 'Settled Loans', 'Collection Rate %'
        ];
        foreach ($performanceByProduct as $item) {
            $collectionRate = $item->total_disbursed > 0 
                ? ($item->total_collected / $item->total_disbursed) * 100 
                : 0;
            $exportData[] = [
                $item->loanProduct->name ?? 'N/A',
                $item->total_loans,
                number_format($item->total_principal, 2),
                number_format($item->total_disbursed, 2),
                number_format($item->total_collected, 2),
                number_format($item->total_outstanding, 2),
                $item->active_loans,
                $item->settled_loans,
                number_format($collectionRate, 2),
            ];
        }
        $exportData[] = []; // Empty row

        // By Group
        $exportData[] = ['LOAN PERFORMANCE BY CUSTOMER GROUP'];
        $exportData[] = [
            'Group', 'Total Loans', 'Total Principal', 'Total Disbursed',
            'Total Collected', 'Total Outstanding', 'Active Loans', 'Settled Loans', 'Collection Rate %'
        ];
        foreach ($performanceByGroup as $item) {
            $collectionRate = $item->total_disbursed > 0 
                ? ($item->total_collected / $item->total_disbursed) * 100 
                : 0;
            $exportData[] = [
                $item->customerGroup->name ?? 'N/A',
                $item->total_loans,
                number_format($item->total_principal, 2),
                number_format($item->total_disbursed, 2),
                number_format($item->total_collected, 2),
                number_format($item->total_outstanding, 2),
                $item->active_loans,
                $item->settled_loans,
                number_format($collectionRate, 2),
            ];
        }
        $exportData[] = []; // Empty row

        // By Company
        $exportData[] = ['LOAN PERFORMANCE BY COMPANY'];
        $exportData[] = [
            'Company', 'Total Loans', 'Total Principal', 'Total Disbursed',
            'Total Collected', 'Total Outstanding', 'Active Loans', 'Settled Loans', 'Collection Rate %'
        ];
        foreach ($performanceByCompany as $item) {
            $company = Company::find($item->company_id);
            $collectionRate = $item->total_disbursed > 0 
                ? ($item->total_collected / $item->total_disbursed) * 100 
                : 0;
            $exportData[] = [
                $company->name ?? 'N/A',
                $item->total_loans,
                number_format($item->total_principal, 2),
                number_format($item->total_disbursed, 2),
                number_format($item->total_collected, 2),
                number_format($item->total_outstanding, 2),
                $item->active_loans,
                $item->settled_loans,
                number_format($collectionRate, 2),
            ];
        }

        $filename = 'loan_performance_report_' . now()->format('Y-m-d_His') . '.xlsx';
        
        return Excel::download(new class($exportData) implements FromCollection, WithColumnWidths, WithStyles {
            protected $data;

            public function __construct($data)
            {
                $this->data = $data;
            }

            public function collection()
            {
                return collect($this->data);
            }

            public function columnWidths(): array
            {
                return ['A' => 25, 'B' => 12, 'C' => 15, 'D' => 15, 'E' => 15, 'F' => 18, 'G' => 12, 'H' => 12, 'I' => 15];
            }

            public function styles(Worksheet $sheet)
            {
                $styles = [];
                foreach ($this->data as $index => $row) {
                    $rowNum = $index + 1;
                    if (is_array($row) && count($row) > 0 && is_string($row[0]) && str_contains($row[0], 'LOAN PERFORMANCE')) {
                        $styles[$rowNum] = ['font' => ['bold' => true, 'size' => 14]];
                    } elseif (is_array($row) && count($row) > 0 && in_array('Product', $row) || in_array('Group', $row) || in_array('Company', $row)) {
                        $styles[$rowNum] = ['font' => ['bold' => true, 'size' => 12]];
                    }
                }
                return $styles;
            }
        }, $filename);
    }

    /**
     * Export Arrears Summary Report
     */
    public function exportArrearsSummary(Request $request)
    {
        $query = Loan::with(['customer.company.relationshipManager', 'loanProduct', 'customerGroup', 'paymentSchedules'])
            ->whereIn('status', ['approved', 'active'])
            ->whereHas('paymentSchedules', function ($q) {
                $q->where('due_date', '<', Carbon::today())
                  ->where('remaining_amount', '>', 0);
            });

        // Apply filters
        if ($request->has('loan_product_id') && $request->loan_product_id) {
            $query->where('loan_product_id', $request->loan_product_id);
        }

        if ($request->has('customer_group_id') && $request->customer_group_id) {
            $query->where('customer_group_id', $request->customer_group_id);
        }

        $loans = $query->get();
        
        // Calculate summary
        $summary = [
            'total_loans' => $loans->count(),
            'total_overdue_amount' => 0,
            'by_product' => [],
            'by_group' => [],
            'by_par_status' => ['PAR30' => 0, 'PAR60' => 0, 'PAR90' => 0],
        ];

        foreach ($loans as $loan) {
            $overdueAmount = $loan->getOverdueAmount();
            $summary['total_overdue_amount'] += $overdueAmount;
            
            $parStatus = $loan->getPARStatus();
            if ($parStatus) {
                $summary['by_par_status'][$parStatus] = ($summary['by_par_status'][$parStatus] ?? 0) + 1;
            }
        }

        $exportData = [
            ['ARREARS SUMMARY REPORT'],
            ['Generated: ' . now()->format('Y-m-d H:i:s')],
            [],
            ['Total Overdue Loans', $summary['total_loans']],
            ['Total Overdue Amount', number_format($summary['total_overdue_amount'], 2)],
            [],
            ['PAR Status Breakdown'],
            ['PAR30', $summary['by_par_status']['PAR30'] ?? 0],
            ['PAR60', $summary['by_par_status']['PAR60'] ?? 0],
            ['PAR90', $summary['by_par_status']['PAR90'] ?? 0],
        ];

        $filename = 'arrears_summary_' . now()->format('Y-m-d_His') . '.xlsx';
        
        return Excel::download(new class($exportData) implements FromCollection, WithColumnWidths, WithStyles {
            protected $data;

            public function __construct($data)
            {
                $this->data = $data;
            }

            public function collection()
            {
                return collect($this->data);
            }

            public function columnWidths(): array
            {
                return ['A' => 25, 'B' => 20];
            }

            public function styles(Worksheet $sheet)
            {
                return [
                    1 => ['font' => ['bold' => true, 'size' => 14]],
                    4 => ['font' => ['bold' => true]],
                    7 => ['font' => ['bold' => true]],
                ];
            }
        }, $filename);
    }

    /**
     * Export Disbursements Summary Report
     */
    public function exportDisbursementsSummary(Request $request)
    {
        $query = Loan::where('disbursement_status', 'completed');

        $this->applyDisbursementFilters($request, $query);

        $summary = [
            'total_disbursements' => (clone $query)->count(),
            'total_amount' => (clone $query)->sum('principal_amount'),
            'this_month' => (clone $query)->whereMonth('disbursed_at', now()->month)
                ->whereYear('disbursed_at', now()->year)
                ->count(),
            'this_month_amount' => (clone $query)->whereMonth('disbursed_at', now()->month)
                ->whereYear('disbursed_at', now()->year)
                ->sum('principal_amount'),
        ];

        $exportData = [
            ['DISBURSEMENTS SUMMARY REPORT'],
            ['Generated: ' . now()->format('Y-m-d H:i:s')],
            [],
            ['Total Disbursements', $summary['total_disbursements']],
            ['Total Disbursed Amount', number_format($summary['total_amount'], 2)],
            ['This Month Disbursements', $summary['this_month']],
            ['This Month Amount', number_format($summary['this_month_amount'], 2)],
        ];

        $filename = 'disbursements_summary_' . now()->format('Y-m-d_His') . '.xlsx';
        
        return Excel::download(new class($exportData) implements FromCollection, WithColumnWidths, WithStyles {
            protected $data;

            public function __construct($data)
            {
                $this->data = $data;
            }

            public function collection()
            {
                return collect($this->data);
            }

            public function columnWidths(): array
            {
                return ['A' => 25, 'B' => 20];
            }

            public function styles(Worksheet $sheet)
            {
                return [
                    1 => ['font' => ['bold' => true, 'size' => 14]],
                    4 => ['font' => ['bold' => true]],
                ];
            }
        }, $filename);
    }

    private function applyDisbursementFilters(Request $request, Builder $query): void
    {
        if ($request->filled('loan_product_id')) {
            $query->where('loan_product_id', $request->loan_product_id);
        }

        if ($request->filled('channel_id')) {
            $query->where('channel_id', $request->channel_id);
        }

        [$rangeStart, $rangeEnd] = $this->resolveDateTimeRange(
            $request,
            'date_from',
            'date_to',
            'time_from',
            'time_to'
        );

        if ($rangeStart) {
            $query->where('disbursed_at', '>=', $rangeStart);
        }

        if ($rangeEnd) {
            $query->where('disbursed_at', '<=', $rangeEnd);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('loan_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }
    }

    /**
     * @return array{0: Carbon|null, 1: Carbon|null}
     */
    private function resolveDateTimeRange(
        Request $request,
        string $fromDateKey,
        string $toDateKey,
        string $fromTimeKey,
        string $toTimeKey
    ): array {
        $fromDate = trim((string) $request->input($fromDateKey, ''));
        $toDate = trim((string) $request->input($toDateKey, ''));
        $fromTime = trim((string) $request->input($fromTimeKey, ''));
        $toTime = trim((string) $request->input($toTimeKey, ''));

        return [
            $this->parseDateTimeForQuery($fromDate, $fromTime, false),
            $this->parseDateTimeForQuery($toDate, $toTime, true),
        ];
    }

    private function parseDateTimeForQuery(string $date, string $time, bool $isEnd): ?Carbon
    {
        if ($date === '') {
            return null;
        }

        $timezone = config('app.timezone');

        try {
            if ($time !== '') {
                foreach (['Y-m-d H:i:s', 'Y-m-d H:i'] as $format) {
                    try {
                        return Carbon::createFromFormat($format, "{$date} {$time}", $timezone);
                    } catch (\Throwable) {
                    }
                }
            }

            $parsedDate = Carbon::createFromFormat('Y-m-d', $date, $timezone);

            return $isEnd ? $parsedDate->endOfDay() : $parsedDate->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Export Collections Summary Report
     */
    public function exportCollectionsSummary(Request $request)
    {
        $query = Repayment::where('status', 'completed');

        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('processed_at', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('processed_at', '<=', $request->date_to);
        }

        $repayments = $query->with('loanRepayments')->get();

        $summary = [
            'total_collections' => $repayments->count(),
            'total_amount' => $repayments->sum('total_amount'),
            'total_principal' => $repayments->sum(function ($r) {
                return $r->loanRepayments->sum('principal_amount');
            }),
            'total_interest' => $repayments->sum(function ($r) {
                return $r->loanRepayments->sum('interest_amount');
            }),
            'total_fee' => $repayments->sum(function ($r) {
                return $r->loanRepayments->sum('processing_fee_amount');
            }),
            'this_month' => $repayments->filter(fn($r) => $r->processed_at && $r->processed_at->isCurrentMonth())->count(),
            'this_month_amount' => $repayments->filter(fn($r) => $r->processed_at && $r->processed_at->isCurrentMonth())->sum('total_amount'),
        ];

        $exportData = [
            ['COLLECTIONS SUMMARY REPORT'],
            ['Generated: ' . now()->format('Y-m-d H:i:s')],
            [],
            ['Total Collections', $summary['total_collections']],
            ['Total Amount Collected', number_format($summary['total_amount'], 2)],
            ['Total Principal', number_format($summary['total_principal'], 2)],
            ['Total Interest', number_format($summary['total_interest'], 2)],
            ['Total Processing Fee', number_format($summary['total_fee'], 2)],
            ['This Month Collections', $summary['this_month']],
            ['This Month Amount', number_format($summary['this_month_amount'], 2)],
        ];

        $filename = 'collections_summary_' . now()->format('Y-m-d_His') . '.xlsx';
        
        return Excel::download(new class($exportData) implements FromCollection, WithColumnWidths, WithStyles {
            protected $data;

            public function __construct($data)
            {
                $this->data = $data;
            }

            public function collection()
            {
                return collect($this->data);
            }

            public function columnWidths(): array
            {
                return ['A' => 25, 'B' => 20];
            }

            public function styles(Worksheet $sheet)
            {
                return [
                    1 => ['font' => ['bold' => true, 'size' => 14]],
                    4 => ['font' => ['bold' => true]],
                ];
            }
        }, $filename);
    }

    /**
     * Export Loan Book Summary Report
     */
    public function exportLoanBookSummary(Request $request)
    {
        Loan::syncActiveStatusForDisbursedLoans();

        $summary = $this->buildLoanBookStats($request);

        $exportData = [
            ['LOAN BOOK SUMMARY REPORT'],
            ['Generated: ' . now()->format('Y-m-d H:i:s')],
            [],
            ['Total Loans (all statuses)', $summary['total_loans']],
            ['Active Loans (disbursed)', $summary['active_loans']],
            ['Total Principal (all)', number_format($summary['total_principal'], 2)],
            ['Active Portfolio Principal', number_format($summary['active_principal'], 2)],
            ['Active Portfolio Outstanding', number_format($summary['total_outstanding'], 2)],
            ['Total Disbursed', number_format($summary['total_disbursed'], 2)],
            [],
            ['Breakdown by Status'],
            ['Status', 'Count', 'Total Principal', 'Total Outstanding'],
        ];

        foreach ($summary['by_status'] as $item) {
            $exportData[] = [
                ucfirst(str_replace('_', ' ', $item->status)),
                $item->count,
                number_format($item->total_principal, 2),
                number_format($item->total_outstanding, 2),
            ];
        }

        $filename = 'loan_book_summary_' . now()->format('Y-m-d_His') . '.xlsx';
        
        return Excel::download(new class($exportData) implements FromCollection, WithColumnWidths, WithStyles {
            protected $data;

            public function __construct($data)
            {
                $this->data = $data;
            }

            public function collection()
            {
                return collect($this->data);
            }

            public function columnWidths(): array
            {
                return ['A' => 25, 'B' => 15, 'C' => 18, 'D' => 18];
            }

            public function styles(Worksheet $sheet)
            {
                return [
                    1 => ['font' => ['bold' => true, 'size' => 14]],
                    4 => ['font' => ['bold' => true]],
                    9 => ['font' => ['bold' => true]],
                    10 => ['font' => ['bold' => true, 'size' => 12]],
                ];
            }
        }, $filename);
    }

    /**
     * Branch-level portfolio & PAR report
     */
    public function branchReport(Request $request): View
    {
        Loan::syncActiveStatusForDisbursedLoans();

        $selectedBranchId = $request->integer('branch_id');
        $today = Carbon::today();

        // Period filter (day, week, month, custom)
        $period = $request->get('period', 'month');
        $customStart = $request->date('date_from');
        $customEnd = $request->date('date_to');

        $rangeEnd = $customEnd ?? $today;
        $rangeStart = match ($period) {
            'day' => $rangeEnd->copy(),
            'week' => $rangeEnd->copy()->subDays(6),
            'month' => $rangeEnd->copy()->subDays(29),
            'custom' => $customStart ?? $rangeEnd->copy()->subDays(29),
            default => $rangeEnd->copy()->subDays(29),
        };

        $rangeStart = $rangeStart->startOfDay();
        $rangeEnd = $rangeEnd->endOfDay();

        $branchOptions = Branch::orderBy('name')->get();

        $branches = Branch::with([
            'province',
            'manager',
            'admins' => fn ($q) => $q->where('is_active', true),
            'customerGroups' => fn ($q) => $q->withCount('customers'),
        ])
            ->when($selectedBranchId, fn ($q) => $q->where('id', $selectedBranchId))
            ->orderBy('name')
            ->get();

        $loans = Loan::with([
                'customer:id,first_name,last_name,registered_name,customer_group_id',
                'loanProduct:id,name',
            'customerGroup:id,name,branch_id',
            'paymentSchedules' => function ($q) use ($today) {
                $q->where(function ($query) use ($today) {
                    $query->where('status', 'overdue')
                        ->orWhere(function ($inner) use ($today) {
                                $inner->where('due_date', '<', $today)
                                    ->where('remaining_amount', '>', 0);
                            });
                    });
                },
            ])
            ->activePortfolio()
            ->when($selectedBranchId, function ($q) use ($selectedBranchId) {
                $q->whereHas('customerGroup', function ($branchQuery) use ($selectedBranchId) {
                    $branchQuery->where('branch_id', $selectedBranchId);
                });
            })
            ->get()
            ->map(function ($loan) {
                $loan->arrears_amount = $loan->paymentSchedules->sum('remaining_amount');
                $loan->par_bucket = $loan->getPARStatus();

                return $loan;
            })
            ->sortBy(function ($loan) {
                return ($loan->customerGroup?->branch?->name ?? '') . '|' . $loan->loan_number;
            })
            ->values();

        // Time-filtered disbursements (principal amounts)
        $disbursementQuery = Loan::query()
            ->where('disbursement_status', 'completed')
            ->whereNotNull('disbursed_at')
            ->whereBetween('disbursed_at', [$rangeStart, $rangeEnd])
            ->whereHas('customerGroup', function ($q) use ($selectedBranchId) {
                if ($selectedBranchId) {
                    $q->where('branch_id', $selectedBranchId);
                }
            });

        $disbursementTotals = [
            'count' => (clone $disbursementQuery)->count(),
            'amount' => (clone $disbursementQuery)->sum('principal_amount'),
        ];

        $disbursementSeries = $disbursementQuery
            ->selectRaw('DATE(disbursed_at) as day, SUM(principal_amount) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day');

        // Time-filtered repayments (collections)
        $repaymentQuery = \App\Models\LoanRepayment::query()
            ->join('repayments', 'repayments.id', '=', 'loan_repayments.repayment_id')
            ->join('loans', 'loans.id', '=', 'loan_repayments.loan_id')
            ->join('customer_groups', 'customer_groups.id', '=', 'loans.customer_group_id')
            ->whereBetween('repayments.processed_at', [$rangeStart, $rangeEnd]);

        if ($selectedBranchId) {
            $repaymentQuery->where('customer_groups.branch_id', $selectedBranchId);
        }

        $repaymentTotals = [
            'count' => (clone $repaymentQuery)->count(),
            'amount' => (clone $repaymentQuery)->sum('loan_repayments.amount'),
        ];

        $repaymentSeries = $repaymentQuery
            ->selectRaw('DATE(repayments.processed_at) as day, SUM(loan_repayments.amount) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day');

        // Build aligned chart data
        $chartLabels = [];
        $chartDisb = [];
        $chartRepay = [];
        $cursor = $rangeStart->copy();
        while ($cursor->lte($rangeEnd)) {
            $dayKey = $cursor->toDateString();
            $chartLabels[] = $dayKey;
            $chartDisb[] = (float) ($disbursementSeries[$dayKey] ?? 0);
            $chartRepay[] = (float) ($repaymentSeries[$dayKey] ?? 0);
            $cursor->addDay();
        }

        $branchRows = $branches->map(function ($branch) use ($loans) {
            $branchLoans = $loans->filter(function ($loan) use ($branch) {
                return $loan->customerGroup?->branch_id === $branch->id;
            });

            $totalPortfolio = $branchLoans->sum('outstanding_balance');
            $totalArrears = $branchLoans->sum('arrears_amount');
            $par = $totalPortfolio > 0 ? ($totalArrears / $totalPortfolio) * 100 : 0;

            return [
                'branch' => $branch,
                'loan_count' => $branchLoans->count(),
                'group_count' => $branch->customerGroups->count(),
                'customer_count' => $branch->customerGroups->sum('customers_count'),
                'staff_count' => $branch->admins->count(),
                'total_portfolio' => $totalPortfolio,
                'total_arrears' => $totalArrears,
                'par' => $par,
            ];
        })
            ->sortBy(fn ($row) => $row['branch']->name)
            ->values();

        $totals = [
            'portfolio' => $branchRows->sum('total_portfolio'),
            'arrears' => $branchRows->sum('total_arrears'),
            'loans' => $branchRows->sum('loan_count'),
            'customers' => $branchRows->sum('customer_count'),
            'staff' => $branchRows->sum('staff_count'),
        ];
        $totals['par'] = $totals['portfolio'] > 0 ? ($totals['arrears'] / $totals['portfolio']) * 100 : 0;

        return view('admin.reports.branch', [
            'branchRows' => $branchRows,
            'branchOptions' => $branchOptions,
            'loans' => $loans,
            'selectedBranchId' => $selectedBranchId,
            'totals' => $totals,
            'period' => $period,
            'rangeStart' => $rangeStart,
            'rangeEnd' => $rangeEnd,
            'disbursementTotals' => $disbursementTotals,
            'repaymentTotals' => $repaymentTotals,
            'chartLabels' => $chartLabels,
            'chartDisb' => $chartDisb,
            'chartRepay' => $chartRepay,
        ]);
    }

    /**
     * Risk Heatmap Dashboard
     */
    public function riskHeatmap(Request $request): View
    {
        // High-risk borrowers (credit score < 40, or overdue amount > 30% of total loan amount)
        $highRiskBorrowers = Customer::with(['company', 'loanProduct', 'customerGroup'])
            ->whereHas('loans', function ($q) {
                $q->whereIn('status', ['approved', 'active']);
            })
            ->get()
            ->map(function ($customer) {
                $activeLoans = $customer->loans()->whereIn('status', ['approved', 'active'])->get();
                $totalLoanAmount = $activeLoans->sum('total_amount');
                $overdueAmount = $activeLoans->sum(function ($loan) {
                    return $loan->getOverdueAmount();
                });
                $overduePercentage = $totalLoanAmount > 0 ? ($overdueAmount / $totalLoanAmount) * 100 : 0;
                
                $riskScore = 0;
                if ($customer->credit_score !== null && $customer->credit_score < 40) {
                    $riskScore += 50;
                }
                if ($overduePercentage > 30) {
                    $riskScore += 30;
                }
                if ($overdueAmount > 0) {
                    $riskScore += 20;
                }
                
                return [
                    'customer' => $customer,
                    'total_loans' => $activeLoans->count(),
                    'total_loan_amount' => $totalLoanAmount,
                    'overdue_amount' => $overdueAmount,
                    'overdue_percentage' => $overduePercentage,
                    'credit_score' => $customer->credit_score,
                    'risk_score' => min(100, $riskScore),
                ];
            })
            ->filter(function ($item) {
                return $item['risk_score'] >= 30; // Only show borrowers with risk score >= 30
            })
            ->sortByDesc('risk_score')
            ->take(50)
            ->values();

        // High-risk branches (delinquency rate > 20% or default rate > 10%)
        $highRiskBranches = Branch::with(['province', 'district', 'manager'])
            ->where('is_active', true)
            ->get()
            ->map(function ($branch) {
                $customerGroups = $branch->customerGroups()->with('customers.loans')->get();
                $totalLoans = 0;
                $totalLoanAmount = 0;
                $overdueAmount = 0;
                $defaultedLoans = 0;
                
                foreach ($customerGroups as $group) {
                    foreach ($group->customers as $customer) {
                        $loans = $customer->loans()->whereIn('status', ['approved', 'active', 'defaulted'])->get();
                        foreach ($loans as $loan) {
                            $totalLoans++;
                            $totalLoanAmount += $loan->total_amount;
                            $overdueAmount += $loan->getOverdueAmount();
                            if ($loan->status === 'defaulted') {
                                $defaultedLoans++;
                            }
                        }
                    }
                }
                
                $delinquencyRate = $totalLoanAmount > 0 ? ($overdueAmount / $totalLoanAmount) * 100 : 0;
                $defaultRate = $totalLoans > 0 ? ($defaultedLoans / $totalLoans) * 100 : 0;
                
                $riskScore = 0;
                if ($delinquencyRate > 20) {
                    $riskScore += 50;
                }
                if ($defaultRate > 10) {
                    $riskScore += 30;
                }
                if ($overdueAmount > 0) {
                    $riskScore += 20;
                }
                
                return [
                    'branch' => $branch,
                    'total_loans' => $totalLoans,
                    'total_loan_amount' => $totalLoanAmount,
                    'overdue_amount' => $overdueAmount,
                    'delinquency_rate' => $delinquencyRate,
                    'default_rate' => $defaultRate,
                    'risk_score' => min(100, $riskScore),
                ];
            })
            ->filter(function ($item) {
                return $item['total_loans'] > 0 && $item['risk_score'] >= 20;
            })
            ->sortByDesc('risk_score')
            ->values();

        // Delinquency by region (province)
        // Get all customers and group them by province through multiple paths:
        // 1. Direct work_province_id
        // 2. Customer group -> branch -> province
        // 3. Customer group -> market -> province (for marketeer customers)
        $allCustomers = Customer::with(['customerGroup.branch.province', 'customerGroup.loanProduct', 'marketeerCustomerDetail.market.province'])
            ->whereNotNull('customer_group_id')
            ->get();
        
        // Group customers by province
        $customersByProvince = [];
        
        foreach ($allCustomers as $customer) {
            $provinceId = null;
            
            // Try direct work_province_id first
            if ($customer->work_province_id) {
                $provinceId = $customer->work_province_id;
            }
            // Try through customer group -> branch -> province
            elseif ($customer->customerGroup?->branch?->province_id) {
                $provinceId = $customer->customerGroup->branch->province_id;
            }
            // Try through marketeer customer detail -> market -> province
            elseif ($customer->marketeerCustomerDetail?->market?->province_id) {
                $provinceId = $customer->marketeerCustomerDetail->market->province_id;
            }
            
            if ($provinceId) {
                if (!isset($customersByProvince[$provinceId])) {
                    $customersByProvince[$provinceId] = [];
                }
                $customersByProvince[$provinceId][] = $customer;
            }
        }
        
        // Calculate delinquency by province
        $delinquencyByRegion = Province::where('is_active', true)
            ->get()
            ->map(function ($province) use ($customersByProvince) {
                $customers = $customersByProvince[$province->id] ?? [];
                
                $totalLoans = 0;
                $totalLoanAmount = 0;
                $overdueAmount = 0;
                $defaultedLoans = 0;
                
                foreach ($customers as $customer) {
                    $loans = $customer->loans()->whereIn('status', ['approved', 'active', 'defaulted'])->get();
                    foreach ($loans as $loan) {
                        $totalLoans++;
                        $totalLoanAmount += $loan->total_amount;
                        $overdueAmount += $loan->getOverdueAmount();
                        if ($loan->status === 'defaulted') {
                            $defaultedLoans++;
                        }
                    }
                }
                
                $delinquencyRate = $totalLoanAmount > 0 ? ($overdueAmount / $totalLoanAmount) * 100 : 0;
                $defaultRate = $totalLoans > 0 ? ($defaultedLoans / $totalLoans) * 100 : 0;
                
                return [
                    'province' => $province,
                    'total_loans' => $totalLoans,
                    'total_loan_amount' => $totalLoanAmount,
                    'overdue_amount' => $overdueAmount,
                    'delinquency_rate' => $delinquencyRate,
                    'default_rate' => $defaultRate,
                ];
            })
            ->filter(function ($item) {
                return $item['total_loans'] > 0;
            })
            ->sortByDesc('delinquency_rate')
            ->values();

        // Loan officer performance risk (relationship managers)
        $loanOfficerRisk = Admin::where('is_relationship_manager', true)
            ->where('is_active', true)
            ->get()
            ->map(function ($officer) {
                $customerGroups = \App\Models\CustomerGroup::where('relationship_manager_id', $officer->id)->get();
                $totalLoans = 0;
                $totalLoanAmount = 0;
                $overdueAmount = 0;
                $defaultedLoans = 0;
                $avgCreditScore = 0;
                $customerCount = 0;
                
                foreach ($customerGroups as $group) {
                    $customers = $group->customers;
                    foreach ($customers as $customer) {
                        $customerCount++;
                        if ($customer->credit_score !== null) {
                            $avgCreditScore += $customer->credit_score;
                        }
                        
                        $loans = $customer->loans()->whereIn('status', ['approved', 'active', 'defaulted'])->get();
                        foreach ($loans as $loan) {
                            $totalLoans++;
                            $totalLoanAmount += $loan->total_amount;
                            $overdueAmount += $loan->getOverdueAmount();
                            if ($loan->status === 'defaulted') {
                                $defaultedLoans++;
                            }
                        }
                    }
                }
                
                $avgCreditScore = $customerCount > 0 ? $avgCreditScore / $customerCount : 0;
                $delinquencyRate = $totalLoanAmount > 0 ? ($overdueAmount / $totalLoanAmount) * 100 : 0;
                $defaultRate = $totalLoans > 0 ? ($defaultedLoans / $totalLoans) * 100 : 0;
                
                $riskScore = 0;
                if ($delinquencyRate > 25) {
                    $riskScore += 40;
                }
                if ($defaultRate > 15) {
                    $riskScore += 30;
                }
                if ($avgCreditScore < 40) {
                    $riskScore += 30;
                }
                
                return [
                    'officer' => $officer,
                    'customer_count' => $customerCount,
                    'total_loans' => $totalLoans,
                    'total_loan_amount' => $totalLoanAmount,
                    'overdue_amount' => $overdueAmount,
                    'delinquency_rate' => $delinquencyRate,
                    'default_rate' => $defaultRate,
                    'avg_credit_score' => round($avgCreditScore, 2),
                    'risk_score' => min(100, $riskScore),
                ];
            })
            ->filter(function ($item) {
                return $item['total_loans'] > 0;
            })
            ->sortByDesc('risk_score')
            ->values();

        return view('admin.reports.risk-heatmap', compact(
            'highRiskBorrowers',
            'highRiskBranches',
            'delinquencyByRegion',
            'loanOfficerRisk'
        ));
    }
}
