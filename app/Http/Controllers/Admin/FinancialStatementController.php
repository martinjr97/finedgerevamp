<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\CashRegister;
use App\Models\Creditor;
use App\Models\FinancialTransaction;
use App\Models\Loan;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FinancialStatementController extends Controller
{
    /**
     * Display balance sheet.
     */
    public function balanceSheet(Request $request): View
    {
        abort_unless(auth('admin')->user()?->can('financial-statements.view'), 403);
        $asOfDate = $request->get('as_of_date', now()->toDateString());
        $loansFromDate = $request->get('loans_from_date');
        $asOfDateCarbon = Carbon::parse($asOfDate);

        // Assets
        $banks = Bank::where('is_active', true)->orderBy('name')->get();
        $wallets = Wallet::where('is_active', true)->orderBy('name')->get();
        $cashRegisters = CashRegister::where('is_active', true)->orderBy('name')->get();

        $totalCashInBanks = $banks->sum('current_balance');
        $totalCashInWallets = $wallets->sum('current_balance');
        $totalCashOnHand = $cashRegisters->sum('current_balance');
        $cashAndCashEquivalents = $totalCashInBanks + $totalCashInWallets + $totalCashOnHand;

        // Loans Receivable (Outstanding balances) - filter by date if provided
        $loansQuery = Loan::whereIn('status', ['approved', 'active']);
        
        if ($loansFromDate) {
            $loansQuery->where('loan_start_date', '>=', $loansFromDate);
        }
        
        $loansReceivable = $loansQuery->sum('outstanding_balance');
        $loansCount = $loansQuery->count();
        
        // Get loans breakdown by status
        $loansBreakdown = Loan::selectRaw('status, COUNT(*) as count, SUM(outstanding_balance) as outstanding')
            ->whereIn('status', ['approved', 'active'])
            ->when($loansFromDate, function($query) use ($loansFromDate) {
                return $query->where('loan_start_date', '>=', $loansFromDate);
            })
            ->groupBy('status')
            ->get();

        $totalAssets = $cashAndCashEquivalents + $loansReceivable;

        // Liabilities - Creditors
        $creditors = Creditor::where('is_active', true)->orderBy('due_date')->get();
        $totalLiabilities = $creditors->sum('amount');

        // Equity
        $equity = $totalAssets - $totalLiabilities;

        return view('admin.financial-statements.balance-sheet', [
            'asOfDate' => $asOfDate,
            'loansFromDate' => $loansFromDate,
            'banks' => $banks,
            'wallets' => $wallets,
            'cashRegisters' => $cashRegisters,
            'totalCashInBanks' => $totalCashInBanks,
            'totalCashInWallets' => $totalCashInWallets,
            'totalCashOnHand' => $totalCashOnHand,
            'cashAndCashEquivalents' => $cashAndCashEquivalents,
            'loansReceivable' => $loansReceivable,
            'loansCount' => $loansCount,
            'loansBreakdown' => $loansBreakdown,
            'totalAssets' => $totalAssets,
            'creditors' => $creditors,
            'totalLiabilities' => $totalLiabilities,
            'equity' => $equity,
        ]);
    }

    /**
     * Display cash flow statement.
     */
    public function cashFlow(Request $request): View
    {
        abort_unless(auth('admin')->user()?->can('financial-statements.view'), 403);
        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());

        $startDateCarbon = Carbon::parse($startDate)->startOfDay();
        $endDateCarbon = Carbon::parse($endDate)->endOfDay();

        // CASH INFLOWS
        // Cash from loan repayments
        $cashFromLoanRepayments = \App\Models\Repayment::whereBetween('processed_at', [$startDateCarbon, $endDateCarbon])
            ->where('status', 'completed')
            ->sum('total_amount');

        // Cash from stakeholder contributions (income transactions that are not loan-related)
        $cashFromStakeholderContributions = FinancialTransaction::where('type', 'income')
            ->where('category', 'other_income')
            ->whereBetween('transaction_date', [$startDateCarbon, $endDateCarbon])
            ->sum('amount');

        $totalInflow = $cashFromLoanRepayments + $cashFromStakeholderContributions;

        // CASH OUTFLOWS
        // Operating expenses (all expense transactions)
        $operatingExpenses = FinancialTransaction::where('type', 'expense')
            ->whereBetween('transaction_date', [$startDateCarbon, $endDateCarbon])
            ->sum('amount');

        // Loans disbursed
        $loansDisbursed = Loan::whereBetween('disbursed_at', [$startDateCarbon, $endDateCarbon])
            ->where('disbursement_status', 'completed')
            ->sum('principal_amount');

        $totalOutflow = $operatingExpenses + $loansDisbursed;

        // NET CASH FLOW
        $netCashFlow = $totalInflow - $totalOutflow;

        // Cash Flow by Bank
        $banks = Bank::where('is_active', true)->orderBy('name')->get();
        $bankCashFlows = [];
        foreach ($banks as $bank) {
            $inflow = FinancialTransaction::where('type', 'income')
                ->where('destination_type', 'bank')
                ->where('destination_id', $bank->id)
                ->whereBetween('transaction_date', [$startDateCarbon, $endDateCarbon])
                ->sum('amount');
            
            // Add repayments received into this bank
            $repaymentsInflow = \App\Models\Repayment::where('received_via_type', 'bank')
                ->where('received_via_id', $bank->id)
                ->whereBetween('processed_at', [$startDateCarbon, $endDateCarbon])
                ->where('status', 'completed')
                ->sum('total_amount');
            
            $inflow += $repaymentsInflow;

            $outflow = FinancialTransaction::where('type', 'expense')
                ->where('source_type', 'bank')
                ->where('source_id', $bank->id)
                ->whereBetween('transaction_date', [$startDateCarbon, $endDateCarbon])
                ->sum('amount');
            
            // Add loan disbursements from this bank
            $disbursementsOutflow = Loan::where('disbursed_via_type', 'bank')
                ->where('disbursed_via_id', $bank->id)
                ->whereBetween('disbursed_at', [$startDateCarbon, $endDateCarbon])
                ->where('disbursement_status', 'completed')
                ->sum('principal_amount');
            
            $outflow += $disbursementsOutflow;

            $net = $inflow - $outflow;

            $bankCashFlows[] = [
                'bank' => $bank,
                'inflow' => $inflow,
                'outflow' => $outflow,
                'net' => $net,
            ];
        }

        // Cash Flow by Mobile Wallet
        $wallets = Wallet::where('is_active', true)->orderBy('name')->get();
        $walletCashFlows = [];
        foreach ($wallets as $wallet) {
            $inflow = FinancialTransaction::where('type', 'income')
                ->where('destination_type', 'wallet')
                ->where('destination_id', $wallet->id)
                ->whereBetween('transaction_date', [$startDateCarbon, $endDateCarbon])
                ->sum('amount');
            
            // Add repayments received into this wallet
            $repaymentsInflow = \App\Models\Repayment::where('received_via_type', 'wallet')
                ->where('received_via_id', $wallet->id)
                ->whereBetween('processed_at', [$startDateCarbon, $endDateCarbon])
                ->where('status', 'completed')
                ->sum('total_amount');
            
            $inflow += $repaymentsInflow;

            $outflow = FinancialTransaction::where('type', 'expense')
                ->where('source_type', 'wallet')
                ->where('source_id', $wallet->id)
                ->whereBetween('transaction_date', [$startDateCarbon, $endDateCarbon])
                ->sum('amount');
            
            // Add loan disbursements from this wallet
            $disbursementsOutflow = Loan::where('disbursed_via_type', 'wallet')
                ->where('disbursed_via_id', $wallet->id)
                ->whereBetween('disbursed_at', [$startDateCarbon, $endDateCarbon])
                ->where('disbursement_status', 'completed')
                ->sum('principal_amount');
            
            $outflow += $disbursementsOutflow;

            $net = $inflow - $outflow;

            $walletCashFlows[] = [
                'wallet' => $wallet,
                'inflow' => $inflow,
                'outflow' => $outflow,
                'net' => $net,
            ];
        }

        // Inhouse Transfers (transfers between banks/wallets - don't affect net cash flow)
        $inhouseTransfers = FinancialTransaction::where('type', 'transfer')
            ->whereBetween('transaction_date', [$startDateCarbon, $endDateCarbon])
            ->with(['sourceBank', 'sourceWallet', 'destinationBank', 'destinationWallet'])
            ->get();

        return view('admin.financial-statements.cash-flow', [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'cashFromLoanRepayments' => $cashFromLoanRepayments,
            'cashFromStakeholderContributions' => $cashFromStakeholderContributions,
            'totalInflow' => $totalInflow,
            'operatingExpenses' => $operatingExpenses,
            'loansDisbursed' => $loansDisbursed,
            'totalOutflow' => $totalOutflow,
            'netCashFlow' => $netCashFlow,
            'bankCashFlows' => $bankCashFlows,
            'walletCashFlows' => $walletCashFlows,
            'inhouseTransfers' => $inhouseTransfers,
        ]);
    }

    /**
     * Display income statement.
     */
    public function incomeStatement(Request $request)
    {
        abort_unless(auth('admin')->user()?->can('financial-statements.view'), 403);
        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());

        $startDateCarbon = Carbon::parse($startDate);
        $endDateCarbon = Carbon::parse($endDate);

        // Income Sources - Group by category/description
        $incomeSources = [];
        
        // Loan Interest
        $loanInterest = FinancialTransaction::where('type', 'income')
            ->where('category', 'loan_interest')
            ->whereBetween('transaction_date', [$startDateCarbon, $endDateCarbon])
            ->sum('amount');

        // Also include interest from loan repayments
        $interestFromRepayments = \App\Models\LoanRepayment::whereHas('repayment', function($query) use ($startDateCarbon, $endDateCarbon) {
            $query->whereBetween('processed_at', [$startDateCarbon, $endDateCarbon])
                  ->where('status', 'completed');
        })->sum('interest_amount');

        $totalLoanInterest = $loanInterest + $interestFromRepayments;
        if ($totalLoanInterest > 0) {
            $incomeSources['Loan Interest'] = $totalLoanInterest;
        }

        // Loan Processing Fees
        $loanProcessingFees = FinancialTransaction::where('type', 'income')
            ->where('category', 'loan_processing_fee')
            ->whereBetween('transaction_date', [$startDateCarbon, $endDateCarbon])
            ->sum('amount');

        $processingFeesFromRepayments = \App\Models\LoanRepayment::whereHas('repayment', function($query) use ($startDateCarbon, $endDateCarbon) {
            $query->whereBetween('processed_at', [$startDateCarbon, $endDateCarbon])
                  ->where('status', 'completed');
        })->sum('processing_fee_amount');

        $totalProcessingFees = $loanProcessingFees + $processingFeesFromRepayments;
        if ($totalProcessingFees > 0) {
            $incomeSources['Loan Processing Fees'] = $totalProcessingFees;
        }

        // Shareholder Contributions
        $shareholderContributions = FinancialTransaction::where('type', 'income')
            ->where('category', 'shareholder_contribution')
            ->whereBetween('transaction_date', [$startDateCarbon, $endDateCarbon])
            ->sum('amount');

        if ($shareholderContributions > 0) {
            $incomeSources['Shareholder Contribution'] = $shareholderContributions;
        }

        // Investment Income
        $investmentIncome = FinancialTransaction::where('type', 'income')
            ->where('category', 'investment_income')
            ->whereBetween('transaction_date', [$startDateCarbon, $endDateCarbon])
            ->sum('amount');

        if ($investmentIncome > 0) {
            $incomeSources['Investment Income'] = $investmentIncome;
        }

        // Donations
        $donations = FinancialTransaction::where('type', 'income')
            ->where('category', 'donation')
            ->whereBetween('transaction_date', [$startDateCarbon, $endDateCarbon])
            ->sum('amount');

        if ($donations > 0) {
            $incomeSources['Donation'] = $donations;
        }

        // Grants
        $grants = FinancialTransaction::where('type', 'income')
            ->where('category', 'grant')
            ->whereBetween('transaction_date', [$startDateCarbon, $endDateCarbon])
            ->sum('amount');

        if ($grants > 0) {
            $incomeSources['Grant'] = $grants;
        }

        // Other Income
        $otherIncome = FinancialTransaction::where('type', 'income')
            ->where('category', 'other_income')
            ->whereBetween('transaction_date', [$startDateCarbon, $endDateCarbon])
            ->sum('amount');

        if ($otherIncome > 0) {
            $incomeSources['Other Income'] = $otherIncome;
        }

        $totalRevenue = array_sum($incomeSources);

        // Expenses - Group by description (manual expense transactions only)
        $expenses = FinancialTransaction::where('type', 'expense')
            ->whereBetween('transaction_date', [$startDateCarbon, $endDateCarbon])
            ->selectRaw('description, SUM(amount) as total_amount')
            ->groupBy('description')
            ->orderBy('description')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->description => $item->total_amount];
            })
            ->toArray();

        $totalExpenses = array_sum($expenses);

        // Net Income
        $netIncome = $totalRevenue - $totalExpenses;
        $profitMargin = $totalRevenue > 0 ? ($netIncome / $totalRevenue) * 100 : 0;

        // Handle Excel export
        if ($request->has('export') && $request->get('export') === 'excel') {
            return $this->exportIncomeStatementToExcel($incomeSources, $expenses, $totalRevenue, $totalExpenses, $netIncome, $profitMargin, $startDate, $endDate);
        }

        return view('admin.financial-statements.income-statement', [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'incomeSources' => $incomeSources,
            'expenses' => $expenses,
            'totalRevenue' => $totalRevenue,
            'totalExpenses' => $totalExpenses,
            'netIncome' => $netIncome,
            'profitMargin' => $profitMargin,
        ]);
    }

    /**
     * Export income statement to Excel
     */
    private function exportIncomeStatementToExcel($incomeSources, $expenses, $totalRevenue, $totalExpenses, $netIncome, $profitMargin, $startDate, $endDate)
    {
        $exportData = [];
        
        // Add header
        $exportData[] = ['INCOME STATEMENT'];
        $exportData[] = [];
        $exportData[] = ['Report Period:', Carbon::parse($startDate)->format('d M Y') . ' to ' . Carbon::parse($endDate)->format('d M Y')];
        $exportData[] = [];
        
        // Income section
        $exportData[] = ['INCOME'];
        $exportData[] = ['Source', 'Amount (ZMW)'];
        foreach ($incomeSources as $source => $amount) {
            $exportData[] = [$source, $amount];
        }
        $exportData[] = ['TOTAL INCOME', $totalRevenue];
        $exportData[] = [];
        
        // Expenses section
        $exportData[] = ['EXPENSES'];
        $exportData[] = ['Category', 'Amount (ZMW)'];
        foreach ($expenses as $category => $amount) {
            $exportData[] = [$category, $amount];
        }
        $exportData[] = ['TOTAL EXPENSES', $totalExpenses];
        $exportData[] = [];
        
        // Net Income
        $exportData[] = ['Net Income', $netIncome];
        $exportData[] = ['Profit Margin (%)', number_format($profitMargin, 2)];

        $filename = 'income_statement_' . Carbon::parse($startDate)->format('Y-m-d') . '_to_' . Carbon::parse($endDate)->format('Y-m-d') . '.xlsx';

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
                return ['A' => 30, 'B' => 20];
            }

            public function styles(Worksheet $sheet)
            {
                $styles = [];
                foreach ($this->data as $index => $row) {
                    $rowNum = $index + 1;
                    if (is_array($row) && count($row) > 0) {
                        if ($row[0] === 'INCOME STATEMENT') {
                            $styles[$rowNum] = ['font' => ['bold' => true, 'size' => 16]];
                        } elseif (in_array($row[0] ?? '', ['INCOME', 'EXPENSES'])) {
                            $styles[$rowNum] = ['font' => ['bold' => true, 'size' => 12]];
                        } elseif (in_array($row[0] ?? '', ['Source', 'Category', 'TOTAL INCOME', 'TOTAL EXPENSES', 'Net Income', 'Profit Margin (%)'])) {
                            $styles[$rowNum] = ['font' => ['bold' => true]];
                        }
                    }
                }
                return $styles;
            }
        }, $filename);
    }

    /**
     * Get opening balance for a given date.
     */
    private function getOpeningBalance(Carbon $date): float
    {
        // Sum of opening balances of all banks and wallets
        $bankOpeningBalances = Bank::where('is_active', true)->sum('opening_balance');
        $walletOpeningBalances = Wallet::where('is_active', true)->sum('opening_balance');

        // Add transactions before the start date
        $transactionsBefore = FinancialTransaction::where('transaction_date', '<', $date)
            ->get();

        $netTransactions = 0;
        foreach ($transactionsBefore as $transaction) {
            if ($transaction->type === 'income') {
                $netTransactions += $transaction->amount;
            } elseif ($transaction->type === 'expense') {
                $netTransactions -= $transaction->amount;
            }
            // Transfers don't affect total cash
        }

        // Add repayments before start date
        $repaymentsBefore = \App\Models\Repayment::where('processed_at', '<', $date)
            ->where('status', 'completed')
            ->sum('total_amount');

        // Subtract disbursements before start date
        $disbursementsBefore = Loan::where('disbursed_at', '<', $date)
            ->where('disbursement_status', 'disbursed')
            ->sum('principal_amount');

        return $bankOpeningBalances + $walletOpeningBalances + $netTransactions + $repaymentsBefore - $disbursementsBefore;
    }
}
