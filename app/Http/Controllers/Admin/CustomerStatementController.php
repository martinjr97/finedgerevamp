<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Loan;
use App\Services\CustomerLifetimeStatementService;
use App\Support\Pdf\FinancialDocumentBranding;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerStatementController extends Controller
{
    public function __construct(
        private readonly CustomerLifetimeStatementService $statementService
    ) {}

    public function show(Request $request, Customer $customer): View
    {
        abort_unless(auth('admin')->user()?->can('customers.view'), 403);

        $statement = $this->buildStatement($request, $customer);
        $isPrint = $request->boolean('print');

        return view($isPrint ? 'admin.customers.statement-print' : 'admin.customers.statement', [
            'customer' => $customer,
            'statement' => $statement,
            'isPrint' => $isPrint,
        ]);
    }

    public function downloadPdf(Request $request, Customer $customer): Response|StreamedResponse|BinaryFileResponse
    {
        abort_unless(auth('admin')->user()?->can('customers.view'), 403);

        $statement = $this->buildStatement($request, $customer, includeSchedules: false);
        $company = $customer->company ?? Company::query()->where('is_primary', true)->first();
        $branding = FinancialDocumentBranding::resolve($company);

        $pdf = Pdf::loadView('pdf.customer-statement', [
            'customer' => $customer,
            'statement' => $statement,
            'company' => $company,
            'branding' => $branding,
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('isPhpEnabled', true);

        return $pdf->download($this->buildPdfFilename($customer, $statement));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildStatement(Request $request, Customer $customer, bool $includeSchedules = true): array
    {
        $validated = $request->validate([
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'loan_id' => ['nullable', 'integer'],
        ]);

        $fromDate = ! empty($validated['from_date'])
            ? Carbon::parse($validated['from_date'])->startOfDay()
            : null;
        $toDate = ! empty($validated['to_date'])
            ? Carbon::parse($validated['to_date'])->endOfDay()
            : null;
        $loanId = isset($validated['loan_id']) ? (int) $validated['loan_id'] : null;

        if ($loanId) {
            $belongsToCustomer = Loan::query()
                ->where('customer_id', $customer->id)
                ->whereKey($loanId)
                ->exists();

            if (! $belongsToCustomer) {
                throw ValidationException::withMessages([
                    'loan_id' => 'The selected loan does not belong to this customer.',
                ]);
            }
        }

        return $this->statementService->build(
            $customer,
            $fromDate,
            $toDate,
            $loanId,
            includeSchedules: $includeSchedules,
        );
    }

    /**
     * @param  array<string, mixed>  $statement
     */
    private function buildPdfFilename(Customer $customer, array $statement): string
    {
        $customerNumber = $customer->customer_number
            ?? $customer->account_number
            ?? ('CUST-'.str_pad((string) $customer->id, 4, '0', STR_PAD_LEFT));

        $safeCustomer = $this->safeFilenamePart((string) $customerNumber);
        $filters = $statement['filters'];
        $from = $filters['from_date'] ?? null;
        $to = $filters['to_date'] ?? now()->toDateString();
        $loanId = $filters['loan_id'] ?? null;

        if ($loanId) {
            /** @var Loan|null $loan */
            $loan = collect($statement['loans'])->firstWhere('id', (int) $loanId);
            $safeLoan = $this->safeFilenamePart((string) ($loan?->loan_number ?: 'loan-'.$loanId));

            return "customer-statement-{$safeCustomer}-{$safeLoan}-{$from}-{$to}.pdf";
        }

        return "customer-statement-{$safeCustomer}-all-loans-{$from}-{$to}.pdf";
    }

    private function safeFilenamePart(string $value): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', $value) ?? 'x';

        return trim($safe, '-') ?: 'x';
    }
}
