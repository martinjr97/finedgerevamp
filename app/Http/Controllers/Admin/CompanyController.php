<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CompanyRequest;
use App\Models\Admin;
use App\Models\Company;
use App\Models\LoanProduct;
use App\Models\LoanRateType;
use App\Models\LoanPaymentSchedule;
use App\Models\Sector;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CompanyController extends Controller
{
    public function index(): View
    {
        $admin = auth('admin')->user();
        $companyFilterId = $admin->getCompanyFilterId();
        
        $query = Company::with(['sector', 'relationshipManager'])
            ->withCount(['admins', 'customers']);
        
        // If not primary company admin, show only their company
        if ($companyFilterId !== null) {
            $query->where('id', $companyFilterId);
        }
        
        $companies = $query->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();

        return view('admin.companies.index', compact('companies'));
    }

    public function create(): View
    {
        abort_unless(auth('admin')->user()?->can('companies.create'), 403);

        // Get loan rate types for MOU products
        $mouProduct = LoanProduct::where('category', 'mou')->first();
        $loanRateTypes = $mouProduct 
            ? LoanRateType::where('loan_product_id', $mouProduct->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
            : collect();

        return view('admin.companies.create', [
            'sectors' => Sector::orderBy('name')->get(),
            'relationshipManagers' => Admin::where('is_relationship_manager', true)->orderBy('first_name')->get(),
            'loanRateTypes' => $loanRateTypes,
        ]);
    }

    public function show(Company $company): View
    {
        abort_unless(auth('admin')->user()?->can('companies.view'), 403);

        $company->load([
            'sector',
            'relationshipManager',
            'approver',
            'loanRateType.loanProduct',
        ])->loadCount(['admins', 'customers']);

        $mouProduct = LoanProduct::where('category', 'mou')->first();
        $loanRateTypes = $mouProduct 
            ? LoanRateType::where('loan_product_id', $mouProduct->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
            : collect();

        return view('admin.companies.show', compact('company', 'loanRateTypes'));
    }

    public function store(CompanyRequest $request): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('companies.create'), 403);

        try {
            $data = $request->validated();
            $requiresApproval = config('approval.companies.create', false);

            // Force default type/primary flags: new companies are partners by default
            unset($data['type'], $data['is_primary']);

            $company = Company::create(array_merge($data, [
                'type' => 'partner',
                'is_primary' => false,
                'approval_status' => $requiresApproval ? 'pending' : 'approved',
                'status' => $requiresApproval ? 'pending' : ($data['status'] ?? 'active'),
            ]));

            $message = $requiresApproval
                ? 'Company created and is pending approval.'
                : 'Company created successfully.';

            return redirect()
                ->route('admin.companies.index')
                ->with('status', $message);
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.companies.create')
                ->withInput()
                ->with('error', 'Failed to create company: '.$e->getMessage());
        }
    }

    public function edit(Company $company): View
    {
        abort_unless(auth('admin')->user()?->can('companies.update'), 403);

        // Get loan rate types for MOU products
        $mouProduct = LoanProduct::where('category', 'mou')->first();
        $loanRateTypes = $mouProduct 
            ? LoanRateType::where('loan_product_id', $mouProduct->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
            : collect();

        return view('admin.companies.edit', [
            'company' => $company,
            'sectors' => Sector::orderBy('name')->get(),
            'relationshipManagers' => Admin::where('is_relationship_manager', true)->orderBy('first_name')->get(),
            'loanRateTypes' => $loanRateTypes,
        ]);
    }

    public function update(CompanyRequest $request, Company $company): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('companies.update'), 403);

        try {
            $data = $request->validated();
            unset($data['type'], $data['is_primary']);

            $company->fill($data);
            // Ensure type is consistent with primary flag
            $company->type = $company->is_primary ? 'operator' : 'partner';
            $company->save();

            return redirect()
                ->route('admin.companies.edit', $company)
                ->with('status', 'Company updated successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.companies.edit', $company)
                ->withInput()
                ->with('error', 'Failed to update company: '.$e->getMessage());
        }
    }

    public function destroy(Company $company): RedirectResponse
    {
        try {
            abort_if($company->is_primary, 403, 'Cannot delete primary company.');

            $company->delete();

            return redirect()
                ->route('admin.companies.index')
                ->with('status', 'Company deleted successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.companies.index')
                ->with('error', 'Failed to delete company: '.$e->getMessage());
        }
    }

    public function export()
    {
        $admin = auth('admin')->user();
        $companyFilterId = $admin->getCompanyFilterId();
        
        $query = Company::with(['sector', 'relationshipManager', 'loanRateType'])
            ->withCount(['admins', 'customers']);
        
        // If not primary company admin, show only their company
        if ($companyFilterId !== null) {
            $query->where('id', $companyFilterId);
        }
        
        $companies = $query->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();

        $exportData = $companies->map(function ($company) {
            return [
                'Name' => $company->name,
                'Code' => $company->code,
                'Slug' => $company->slug,
                'Type' => ucfirst($company->type),
                'Is Primary' => $company->is_primary ? 'Yes' : 'No',
                'Registration Number' => $company->registration_number ?? '—',
                'TPIN' => $company->tpin ?? '—',
                'Date of Incorporation' => $company->date_of_incorporation ? $company->date_of_incorporation->format('Y-m-d') : '—',
                'MOU Expiry Date' => $company->mou_expiry_date ? $company->mou_expiry_date->format('Y-m-d') : '—',
                'Sector' => $company->sector->name ?? '—',
                'Relationship Manager' => $company->relationshipManager ? $company->relationshipManager->full_name : '—',
                'Loan Rate Type' => $company->loanRateType->name ?? '—',
                'Contact Email' => $company->contact_email ?? '—',
                'Contact Phone' => $company->contact_phone ?? '—',
                'Address Line 1' => $company->address_line1 ?? '—',
                'Address Line 2' => $company->address_line2 ?? '—',
                'City' => $company->city ?? '—',
                'State' => $company->state ?? '—',
                'Postal Code' => $company->postal_code ?? '—',
                'Country' => $company->country ?? '—',
                'Status' => ucfirst($company->status),
                'Approval Status' => ucfirst($company->approval_status),
                'Approved By' => $company->approver ? $company->approver->full_name : '—',
                'Approved At' => $company->approved_at ? $company->approved_at->format('Y-m-d H:i:s') : '—',
                'Maximum Loan Tenure (Months)' => $company->maximum_loan_tenure_months ?? '—',
                'Monthly Cut Off Day' => $company->monthly_cut_off_day ?? '—',
                'Pay Day' => $company->pay_day ?? '—',
                'Maximum Debit Ratio (%)' => $company->maximum_debit_ratio ? number_format($company->maximum_debit_ratio, 2) : '—',
                'Instalment Cross Over (%)' => $company->instalment_cross_over_percentage ? number_format($company->instalment_cross_over_percentage, 2) : '—',
                'Arrangement Fee (%)' => $company->arrangement_fee_percentage ? number_format($company->arrangement_fee_percentage, 2) : '—',
                'Admins Count' => $company->admins_count,
                'Customers Count' => $company->customers_count,
                'Created At' => $company->created_at->format('Y-m-d H:i:s'),
            ];
        });

        $filename = 'companies-export-' . now()->format('Y-m-d_His') . '.xlsx';

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
                    'Name',
                    'Code',
                    'Slug',
                    'Type',
                    'Is Primary',
                    'Registration Number',
                    'TPIN',
                    'Date of Incorporation',
                    'MOU Expiry Date',
                    'Sector',
                    'Relationship Manager',
                    'Loan Rate Type',
                    'Contact Email',
                    'Contact Phone',
                    'Address Line 1',
                    'Address Line 2',
                    'City',
                    'State',
                    'Postal Code',
                    'Country',
                    'Status',
                    'Approval Status',
                    'Approved By',
                    'Approved At',
                    'Maximum Loan Tenure (Months)',
                    'Monthly Cut Off Day',
                    'Pay Day',
                    'Maximum Debit Ratio (%)',
                    'Instalment Cross Over (%)',
                    'Arrangement Fee (%)',
                    'Admins Count',
                    'Customers Count',
                    'Created At',
                ];
            }

            public function columnWidths(): array
            {
                return [
                    'A' => 25, 'B' => 12, 'C' => 20, 'D' => 12, 'E' => 12,
                    'F' => 20, 'G' => 15, 'H' => 20, 'I' => 18, 'J' => 20,
                    'K' => 25, 'L' => 20, 'M' => 25, 'N' => 18, 'O' => 25,
                    'P' => 25, 'Q' => 15, 'R' => 15, 'S' => 12, 'T' => 15,
                    'U' => 12, 'V' => 15, 'W' => 20, 'X' => 20, 'Y' => 25,
                    'Z' => 18, 'AA' => 10, 'AB' => 10, 'AC' => 20, 'AD' => 22,
                    'AE' => 15, 'AF' => 15, 'AG' => 20,
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

    public function updateLoanRateType(Request $request, Company $company): JsonResponse
    {
        abort_unless(auth('admin')->user()?->can('companies.update'), 403);

        $validated = $request->validate([
            'loan_rate_type_id' => ['nullable', 'exists:loan_rate_types,id'],
        ]);

        $company->update([
            'loan_rate_type_id' => $validated['loan_rate_type_id'] ?? null,
        ]);

        $company->load('loanRateType');

        return response()->json([
            'message' => $validated['loan_rate_type_id']
                ? 'Loan rate type updated successfully.'
                : 'Loan rate type removed from company.',
            'loan_rate_type' => $company->loanRateType
                ? [
                    'id' => $company->loanRateType->id,
                    'name' => $company->loanRateType->name,
                    'code' => $company->loanRateType->code,
                    'accrual_period' => $company->loanRateType->accrual_period,
                ]
                : null,
        ]);
    }

    /**
     * Show company and month selection form for payment due report.
     */
    public function selectPaymentDueReport(): View
    {
        abort_unless(auth('admin')->user()?->can('loans.view'), 403);

        $admin = auth('admin')->user();
        $companyFilterId = $admin->getCompanyFilterId();
        
        // Get companies based on admin's access level
        $query = Company::orderBy('name');
        
        // If not primary company admin, show only their company
        if ($companyFilterId !== null) {
            $query->where('id', $companyFilterId);
        }
        
        $companies = $query->get();

        return view('admin.payment-due-report.select', compact('companies'));
    }

    /**
     * Show payment due report month selection form.
     */
    public function showPaymentDueReport(Company $company): View|RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loans.view'), 403);

        $admin = auth('admin')->user();
        $companyFilterId = $admin->getCompanyFilterId();

        // Ensure non-primary admins can only view reports for their company
        if ($companyFilterId !== null && $company->id != $companyFilterId) {
            abort(403, 'You can only view payment due reports for your company.');
        }

        if (!$company->pay_day) {
            return redirect()
                ->route('admin.companies.show', $company)
                ->with('error', 'This company does not have a pay day configured. Please set it in the company settings.');
        }

        return view('admin.companies.payment-due-report', compact('company'));
    }

    /**
     * Generate payment due report from company/month selection form.
     */
    public function generatePaymentDueReportFromSelect(Request $request): View|RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('loans.view'), 403);

        $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $company = Company::findOrFail($request->company_id);
        
        $admin = auth('admin')->user();
        $companyFilterId = $admin->getCompanyFilterId();

        // Ensure non-primary admins can only view reports for their company
        if ($companyFilterId !== null && $company->id != $companyFilterId) {
            return redirect()
                ->route('admin.payment-due-report.select')
                ->with('error', 'You can only view payment due reports for your company.')
                ->withInput();
        }

        if (!$company->pay_day) {
            return redirect()
                ->route('admin.payment-due-report.select')
                ->with('error', 'This company does not have a pay day configured. Please set it in the company settings.')
                ->withInput();
        }

        // Generate the report directly (reuse the logic from generatePaymentDueReport)
        $selectedMonth = Carbon::createFromFormat('Y-m', $request->month);
        $payDay = $company->pay_day;
        
        // Calculate the actual due date for the selected month
        $daysInMonth = $selectedMonth->daysInMonth;
        $actualPayDay = min($payDay, $daysInMonth);
        $dueDate = $selectedMonth->copy()->day($actualPayDay);

        // Get all payment schedules for loans belonging to this company's customers
        $paymentSchedules = LoanPaymentSchedule::with([
            'loan.customer.kycDocuments',
            'loan.loanProduct',
        ])
            ->whereHas('loan.customer', function ($query) use ($company) {
                $query->where('company_id', $company->id);
            })
            ->whereYear('due_date', $selectedMonth->year)
            ->whereMonth('due_date', $selectedMonth->month)
            ->whereDay('due_date', $actualPayDay)
            ->where('status', '!=', 'paid')
            ->orderBy('due_date')
            ->get();

        // Calculate totals
        $totalExpected = $paymentSchedules->sum('expected_amount');
        $totalPaid = $paymentSchedules->sum('amount_paid');
        $totalRemaining = $paymentSchedules->sum('remaining_amount');
        $totalLoans = $paymentSchedules->unique('loan_id')->count();
        $totalCustomers = $paymentSchedules->unique(function ($schedule) {
            return $schedule->loan->customer_id;
        })->count();

        return view('admin.companies.payment-due-report-results', [
            'company' => $company,
            'selectedMonth' => $selectedMonth,
            'dueDate' => $dueDate,
            'paymentSchedules' => $paymentSchedules,
            'totalExpected' => $totalExpected,
            'totalPaid' => $totalPaid,
            'totalRemaining' => $totalRemaining,
            'totalLoans' => $totalLoans,
            'totalCustomers' => $totalCustomers,
        ]);
    }

    /**
     * Generate payment due report for selected month.
     */
    public function generatePaymentDueReport(Request $request, Company $company): View
    {
        abort_unless(auth('admin')->user()?->can('loans.view'), 403);

        $admin = auth('admin')->user();
        $companyFilterId = $admin->getCompanyFilterId();

        // Ensure non-primary admins can only view reports for their company
        if ($companyFilterId !== null && $company->id != $companyFilterId) {
            abort(403, 'You can only view payment due reports for your company.');
        }

        // Get month from request (can come from POST or GET query parameter)
        $month = $request->input('month') ?? $request->query('month');
        
        // Merge month into request for validation
        $request->merge(['month' => $month]);
        
        $request->validate([
            'month' => ['required', 'date_format:Y-m'],
        ], [], [
            'month' => 'month',
        ]);

        if (!$company->pay_day) {
            return redirect()
                ->route('admin.companies.show', $company)
                ->with('error', 'This company does not have a pay day configured.');
        }

        $selectedMonth = Carbon::createFromFormat('Y-m', $month);
        $payDay = $company->pay_day;
        
        // Calculate the actual due date for the selected month
        // Handle edge case where pay_day might be greater than days in month (e.g., 31st in February)
        $daysInMonth = $selectedMonth->daysInMonth;
        $actualPayDay = min($payDay, $daysInMonth);
        $dueDate = $selectedMonth->copy()->day($actualPayDay);

        // Get all payment schedules for loans belonging to this company's customers
        // where the due_date matches the company's pay_day in the selected month
        $paymentSchedules = LoanPaymentSchedule::with([
            'loan.customer.kycDocuments',
            'loan.loanProduct',
        ])
            ->whereHas('loan.customer', function ($query) use ($company) {
                $query->where('company_id', $company->id);
            })
            ->whereYear('due_date', $selectedMonth->year)
            ->whereMonth('due_date', $selectedMonth->month)
            ->whereDay('due_date', $actualPayDay)
            ->where('status', '!=', 'paid')
            ->orderBy('due_date')
            ->get();

        // Calculate totals
        $totalExpected = $paymentSchedules->sum('expected_amount');
        $totalPaid = $paymentSchedules->sum('amount_paid');
        $totalRemaining = $paymentSchedules->sum('remaining_amount');
        $totalLoans = $paymentSchedules->unique('loan_id')->count();
        $totalCustomers = $paymentSchedules->unique(function ($schedule) {
            return $schedule->loan->customer_id;
        })->count();

        return view('admin.companies.payment-due-report-results', [
            'company' => $company,
            'selectedMonth' => $selectedMonth,
            'dueDate' => $dueDate,
            'paymentSchedules' => $paymentSchedules,
            'totalExpected' => $totalExpected,
            'totalPaid' => $totalPaid,
            'totalRemaining' => $totalRemaining,
            'totalLoans' => $totalLoans,
            'totalCustomers' => $totalCustomers,
        ]);
    }

    /**
     * Export payment due report to Excel.
     */
    public function exportPaymentDueReport(Request $request, Company $company)
    {
        abort_unless(auth('admin')->user()?->can('loans.export'), 403);

        $admin = auth('admin')->user();
        $companyFilterId = $admin->getCompanyFilterId();

        // Ensure non-primary admins can only export reports for their company
        if ($companyFilterId !== null && $company->id != $companyFilterId) {
            abort(403, 'You can only export payment due reports for your company.');
        }

        $request->validate([
            'month' => ['required', 'date_format:Y-m'],
        ]);

        if (!$company->pay_day) {
            return redirect()
                ->route('admin.companies.show', $company)
                ->with('error', 'This company does not have a pay day configured.');
        }

        $selectedMonth = Carbon::createFromFormat('Y-m', $request->month);
        $payDay = $company->pay_day;
        
        // Calculate the actual due date
        $daysInMonth = $selectedMonth->daysInMonth;
        $actualPayDay = min($payDay, $daysInMonth);
        $dueDate = $selectedMonth->copy()->day($actualPayDay);

        // Get payment schedules with customer KYC documents
        $paymentSchedules = LoanPaymentSchedule::with([
            'loan.customer.kycDocuments',
            'loan.loanProduct',
        ])
            ->whereHas('loan.customer', function ($query) use ($company) {
                $query->where('company_id', $company->id);
            })
            ->whereYear('due_date', $selectedMonth->year)
            ->whereMonth('due_date', $selectedMonth->month)
            ->whereDay('due_date', $actualPayDay)
            ->where('status', '!=', 'paid')
            ->orderBy('due_date')
            ->get();

        // Prepare export data
        $exportData = $paymentSchedules->map(function ($schedule) {
            $customer = $schedule->loan->customer;
            
            // Get passport number from KYC documents if available
            $passportNumber = 'N/A';
            if ($customer) {
                $passportDoc = $customer->kycDocuments
                    ->where('document_type', 'passport')
                    ->first();
                // If passport document exists, try to get number from metadata or notes
                if ($passportDoc) {
                    $passportNumber = $passportDoc->notes ?? 'N/A';
                    // If notes is empty, check if there's a passport number in customer metadata
                    if ($passportNumber === 'N/A' && $customer->metadata) {
                        $passportNumber = $customer->metadata['passport_number'] ?? 'N/A';
                    }
                }
            }
            
            return [
                'Customer Name' => $customer->full_name ?? 'N/A',
                'Customer Email' => $customer->email ?? 'N/A',
                'Customer Phone' => $customer->phone ?? 'N/A',
                'National ID' => $customer->national_id ?? 'N/A',
                'Passport Number' => $passportNumber,
                'Loan Number' => $schedule->loan->loan_number ?? 'N/A',
                'Loan Product' => $schedule->loan->loanProduct->name ?? 'N/A',
                'Period' => $schedule->period_number,
                'Due Date' => $schedule->due_date->format('Y-m-d'),
                'Expected Amount' => number_format($schedule->expected_amount, 2),
                'Amount Paid' => number_format($schedule->amount_paid, 2),
                'Remaining Amount' => number_format($schedule->remaining_amount, 2),
                'Status' => ucfirst(str_replace('_', ' ', $schedule->status)),
            ];
        });

        // Add totals row
        $totals = [
            'Customer Name' => 'TOTALS',
            'Customer Email' => '',
            'Customer Phone' => '',
            'National ID' => '',
            'Passport Number' => '',
            'Loan Number' => '',
            'Loan Product' => '',
            'Period' => '',
            'Due Date' => '',
            'Expected Amount' => number_format($paymentSchedules->sum('expected_amount'), 2),
            'Amount Paid' => number_format($paymentSchedules->sum('amount_paid'), 2),
            'Remaining Amount' => number_format($paymentSchedules->sum('remaining_amount'), 2),
            'Status' => '',
        ];

        $exportDataCollection = $exportData->push($totals);

        $filename = 'payment-due-report-' . $company->code . '-' . $selectedMonth->format('Y-m') . '-' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new class($exportDataCollection) implements FromCollection, WithHeadings, WithColumnWidths, WithStyles {
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
                    'Customer Name',
                    'Customer Email',
                    'Customer Phone',
                    'National ID',
                    'Passport Number',
                    'Loan Number',
                    'Loan Product',
                    'Period',
                    'Due Date',
                    'Expected Amount',
                    'Amount Paid',
                    'Remaining Amount',
                    'Status',
                ];
            }

            public function columnWidths(): array
            {
                return [
                    'A' => 20,
                    'B' => 25,
                    'C' => 15,
                    'D' => 15,
                    'E' => 18,
                    'F' => 15,
                    'G' => 20,
                    'H' => 10,
                    'I' => 12,
                    'J' => 15,
                    'K' => 15,
                    'L' => 15,
                    'M' => 15,
                ];
            }

            public function styles(Worksheet $sheet)
            {
                $highestRow = $sheet->getHighestRow();
                return [
                    1 => ['font' => ['bold' => true]],
                    $highestRow => ['font' => ['bold' => true]],
                ];
            }
        }, $filename);
    }
}