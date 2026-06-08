<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\Channel;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\Repayment;
use App\Models\Wallet;
use App\Services\LoanRepaymentLedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BulkRepaymentController extends Controller
{
    /**
     * Get or create the Bulk Upload channel.
     */
    private function getBulkUploadChannel(): Channel
    {
        return Channel::firstOrCreate(
            ['code' => 'BULK_UPLOAD'],
            [
                'name' => 'Bulk Upload',
                'description' => 'Bulk repayment uploads via Excel file',
                'can_disburse' => false,
                'can_repay' => true,
                'is_active' => true,
            ]
        );
    }

    /**
     * Display the bulk repayment upload form.
     */
    public function index(): View
    {
        abort_unless(auth('admin')->user()?->can('bulk-repayments.view'), 403);
        $banks = Bank::where('is_active', true)->orderBy('name')->get();
        $wallets = Wallet::where('is_active', true)->orderBy('name')->get();
        return view('admin.bulk-repayments.index', compact('banks', 'wallets'));
    }

    /**
     * Download sample Excel file.
     */
    public function downloadSample()
    {
        $sampleData = collect([
            [
                'National ID' => '123456789',
                'Phone' => '260900936600',
                'Amount' => 500.00,
            ],
            [
                'National ID' => '987654321',
                'Phone' => '260907890123',
                'Amount' => 1000.50,
            ],
            [
                'National ID' => '456789123',
                'Phone' => '260900890123',
                'Amount' => 750.25,
            ],
        ]);

        $filename = 'bulk-repayment-sample-' . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(new class($sampleData) implements FromCollection, WithHeadings, WithColumnWidths, WithStyles {
            protected $data;

            public function __construct($data)
            {
                $this->data = $data;
            }

            public function collection()
            {
                return $this->data;
            }

            public function headings(): array
            {
                return [
                    'National ID',
                    'Phone',
                    'Amount',
                ];
            }

            public function columnWidths(): array
            {
                return [
                    'A' => 15,
                    'B' => 15,
                    'C' => 15,
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
     * Process bulk repayment upload.
     */
    public function process(Request $request): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('bulk-repayments.process'), 403);
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'], // 10MB max
            'received_via_type' => ['required', 'in:bank,wallet'],
            'received_via_id' => ['required', 'integer'],
        ]);

        // Validate that the received_via_id exists in the correct table
        if ($request->received_via_type === 'bank') {
            $request->validate([
                'received_via_id' => ['exists:banks,id'],
            ]);
        } else {
            $request->validate([
                'received_via_id' => ['exists:wallets,id'],
            ]);
        }

        // Get or create the Bulk Upload channel
        $channel = $this->getBulkUploadChannel();

        try {
            // Read Excel file - try with headings first, fallback to array
            try {
                // Try reading with headings (assumes first row is header)
                $data = Excel::toArray(new class implements \Maatwebsite\Excel\Concerns\WithHeadingRow {
                }, $request->file('file'));
                
                $rows = $data[0] ?? [];
            } catch (\Exception $e) {
                // Fallback: read as simple array
                $data = Excel::toArray($request->file('file'));
                $rows = $data[0] ?? [];
                
                // If first row looks like headers, skip it
                if (!empty($rows) && isset($rows[0]) && is_array($rows[0])) {
                    $firstRow = array_values($rows[0]);
                    if (!empty($firstRow) && is_string($firstRow[0] ?? null) && 
                        (stripos($firstRow[0], 'national') !== false || 
                         stripos($firstRow[0], 'phone') !== false ||
                         stripos($firstRow[0], 'amount') !== false)) {
                        array_shift($rows); // Remove header row
                    }
                }
            }

            if (empty($rows)) {
                return redirect()->back()
                    ->with('error', 'The Excel file is empty or invalid.');
            }
            $results = [
                'success' => [],
                'failed' => [],
                'total' => count($rows),
            ];

            DB::beginTransaction();

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // +2 because Excel row 1 is header, and array is 0-indexed

                try {
                    // Handle both array and associative array formats
                    // Check if row is numeric array (no headings) - assume order: National ID, Phone, Amount
                    $rowValues = array_values($row);
                    if (isset($rowValues[0]) && (is_numeric($rowValues[0]) || is_string($rowValues[0]))) {
                        // Check if it's a numeric array (indexed 0, 1, 2) or associative
                        $keys = array_keys($row);
                        if (isset($keys[0]) && $keys[0] === 0) {
                            // Numeric array - assume order: National ID, Phone, Amount
                            $nationalId = $rowValues[0] ?? null;
                            $phone = $rowValues[1] ?? null;
                            $amount = $rowValues[2] ?? null;
                        } else {
                            // Associative array with headings
                            $nationalId = $this->getValue($row, ['national_id', 'national id', 'nationalid', 'nrc', 'national id number', 'national_id_number']);
                            $phone = $this->getValue($row, ['phone', 'phone_number', 'phone number', 'mobile', 'mobile_number', 'phone_number']);
                            $amount = $this->getValue($row, ['amount', 'repayment_amount', 'repayment amount', 'payment_amount', 'repayment', 'repayment_amount']);
                        }
                    } else {
                        // Extract data from row (handle different column name variations)
                        $nationalId = $this->getValue($row, ['national_id', 'national id', 'nationalid', 'nrc', 'national id number', 'national_id_number']);
                        $phone = $this->getValue($row, ['phone', 'phone_number', 'phone number', 'mobile', 'mobile_number', 'phone_number']);
                        $amount = $this->getValue($row, ['amount', 'repayment_amount', 'repayment amount', 'payment_amount', 'repayment', 'repayment_amount']);
                    }

                    // Validate required fields
                    if (empty($nationalId) || empty($phone) || empty($amount)) {
                        $amountValue = is_numeric($amount) ? (float) $amount : 0;
                        $results['failed'][] = [
                            'row' => $rowNumber,
                            'national_id' => $nationalId ?? '—',
                            'phone' => $phone ?? '—',
                            'amount' => $amountValue,
                            'error' => 'Missing required fields (National ID, Phone, or Amount)',
                        ];
                        continue;
                    }

                    // Normalize phone number
                    $phone = preg_replace('/\D/', '', $phone);
                    $amount = (float) $amount;

                    if ($amount <= 0) {
                        $results['failed'][] = [
                            'row' => $rowNumber,
                            'national_id' => $nationalId,
                            'phone' => $phone,
                            'amount' => $amount,
                            'error' => 'Invalid amount (must be greater than 0)',
                        ];
                        continue;
                    }

                    // Find customer by National ID and Phone
                    $customer = Customer::where('national_id', $nationalId)
                        ->where(function($query) use ($phone) {
                            $query->where('phone', $phone)
                                  ->orWhereRaw('REPLACE(REPLACE(REPLACE(phone, "+", ""), "-", ""), " ", "") = ?', [$phone]);
                        })
                        ->first();

                    if (!$customer) {
                        $results['failed'][] = [
                            'row' => $rowNumber,
                            'national_id' => $nationalId,
                            'phone' => $phone,
                            'amount' => (float) $amount,
                            'error' => 'Customer not found',
                        ];
                        continue;
                    }

                    // Get customer's active loans ordered by oldest first
                    $loans = Loan::where('customer_id', $customer->id)
                        ->whereIn('status', ['approved', 'active'])
                        ->orderBy('loan_start_date', 'asc')
                        ->orderBy('created_at', 'asc')
                        ->get();

                    if ($loans->isEmpty()) {
                        $results['failed'][] = [
                            'row' => $rowNumber,
                            'national_id' => $nationalId,
                            'phone' => $phone,
                            'amount' => (float) $amount,
                            'error' => 'Customer has no active loans',
                        ];
                        continue;
                    }

                    // Process repayment
                    $repayment = $this->processRepayment(
                        $customer, 
                        $channel, 
                        $phone, 
                        $amount, 
                        $loans,
                        $request->received_via_type,
                        $request->received_via_id
                    );

                    $results['success'][] = [
                        'row' => $rowNumber,
                        'customer' => $customer->full_name,
                        'national_id' => $nationalId,
                        'phone' => $phone,
                        'amount' => $amount,
                        'repayment_number' => $repayment->repayment_number,
                    ];

                } catch (\Exception $e) {
                    Log::error('Bulk repayment processing error', [
                        'row' => $rowNumber,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);

                    $amountValue = isset($amount) && is_numeric($amount) ? (float) $amount : 0;
                    $results['failed'][] = [
                        'row' => $rowNumber,
                        'national_id' => $nationalId ?? '—',
                        'phone' => $phone ?? '—',
                        'amount' => $amountValue,
                        'error' => 'Processing error: ' . $e->getMessage(),
                    ];
                }
            }

            DB::commit();

            // Calculate total amounts
            $totalSuccessfulAmount = array_sum(array_column($results['success'], 'amount'));
            $totalFailedAmount = 0;
            foreach ($results['failed'] as $failed) {
                $amount = is_numeric($failed['amount']) ? (float) $failed['amount'] : 0;
                $totalFailedAmount += $amount;
            }

            // Add totals to results
            $results['total_successful_amount'] = $totalSuccessfulAmount;
            $results['total_failed_amount'] = $totalFailedAmount;

            // Store results in session for display
            session([
                'bulk_repayment_results' => $results,
            ]);

            return redirect()->route('admin.bulk-repayments.results')
                ->with('status', 'Bulk repayment processing completed.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk repayment upload error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to process bulk repayment: ' . $e->getMessage());
        }
    }

    /**
     * Display bulk repayment results.
     */
    public function results(): View
    {
        abort_unless(auth('admin')->user()?->can('bulk-repayments.view'), 403);
        $results = session('bulk_repayment_results', [
            'success' => [],
            'failed' => [],
            'total' => 0,
            'total_successful_amount' => 0,
            'total_failed_amount' => 0,
        ]);

        return view('admin.bulk-repayments.results', compact('results'));
    }

    /**
     * Process a single repayment for a customer.
     */
    private function processRepayment(Customer $customer, Channel $channel, string $phone, float $amount, $loans, string $receivedViaType, int $receivedViaId): Repayment
    {
        // Create repayment record
        $repayment = Repayment::create([
            'customer_id' => $customer->id,
            'channel_id' => $channel->id,
            'repayment_number' => Repayment::generateRepaymentNumber(),
            'total_amount' => $amount,
            'phone_number' => $phone,
            'status' => 'completed',
            'processed_at' => now(),
            'received_via_type' => $receivedViaType,
            'received_via_id' => $receivedViaId,
            'metadata' => [
                'bulk_upload' => true,
                'uploaded_by' => auth('admin')->user()->email ?? 'System',
                'uploaded_at' => now()->toIso8601String(),
            ],
        ]);

        // Update bank/wallet balance
        if ($receivedViaType === 'bank') {
            $bank = Bank::findOrFail($receivedViaId);
            $bank->updateBalance($amount, 'credit');
        } elseif ($receivedViaType === 'wallet') {
            $wallet = Wallet::findOrFail($receivedViaId);
            $wallet->updateBalance($amount, 'credit');
        }

        // Apply payment to loans starting with oldest first
        $remainingAmount = $amount;

        foreach ($loans as $loan) {
            if ($remainingAmount <= 0) {
                break;
            }

            if ($loan->outstanding_balance > 0) {
                $payAmount = min($loan->outstanding_balance, $remainingAmount);
                $this->applyPaymentToLoan($repayment, $loan, $payAmount);
                $remainingAmount -= $payAmount;
            }
        }

        // If there's remaining amount after all loans are paid, log it
        if ($remainingAmount > 0) {
            Log::warning('Bulk repayment excess amount', [
                'customer_id' => $customer->id,
                'repayment_id' => $repayment->id,
                'excess_amount' => $remainingAmount,
            ]);
        }

        return $repayment;
    }

    /**
     * Apply payment to a specific loan.
     */
    private function applyPaymentToLoan(Repayment $repayment, Loan $loan, float $amount): void
    {
        $ledgerService = app(LoanRepaymentLedgerService::class);
        $netPaidBefore = $ledgerService->calculateNetPaid($loan);
        $outstandingBalanceBefore = $ledgerService->calculateOutstandingBalance($loan, $netPaidBefore);

        $allocation = $loan->calculateRepaymentAllocation($amount);

        $principalAmount = $allocation['principal_amount'];
        $interestAmount = $allocation['interest_amount'];
        $processingFeeAmount = $allocation['processing_fee_amount'];

        $totalAllocated = $principalAmount + $interestAmount + $processingFeeAmount;
        if (abs($totalAllocated - $amount) > 0.01) {
            $principalAmount += ($amount - $totalAllocated);
            $principalAmount = max(0, $principalAmount);
        }

        if (method_exists($loan, 'updatePaymentSchedule') && $loan->paymentSchedules()->exists()) {
            $loan->updatePaymentSchedule($amount);
        }

        $netPaidAfter = round($netPaidBefore + $amount, 2);
        $outstandingBalanceAfter = $ledgerService->calculateOutstandingBalance($loan, $netPaidAfter);

        LoanRepayment::create([
            'repayment_id' => $repayment->id,
            'loan_id' => $loan->id,
            'transaction_type' => LoanRepayment::TRANSACTION_TYPE_PAYMENT,
            'amount' => $amount,
            'principal_amount' => round($principalAmount, 2),
            'interest_amount' => round($interestAmount, 2),
            'processing_fee_amount' => round($processingFeeAmount, 2),
            'outstanding_balance_before' => $outstandingBalanceBefore,
            'outstanding_balance_after' => $outstandingBalanceAfter,
            'notes' => "Bulk repayment applied to loan {$loan->loan_number}",
        ]);

        $ledgerService->syncLoanLedger($loan->fresh());

        // Recalculate credit score for the customer
        try {
            $customer = $loan->customer;
            \App\Support\CreditScoreService::updateCreditScore($customer);
        } catch (\Exception $e) {
            \Log::error('Failed to update credit score after bulk repayment', [
                'customer_id' => $loan->customer_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get value from row array, checking multiple possible column names.
     */
    private function getValue(array $row, array $possibleKeys): ?string
    {
        foreach ($possibleKeys as $key) {
            // Check exact match
            if (isset($row[$key])) {
                $value = $row[$key];
                return $value !== null ? (string) $value : null;
            }

            // Check case-insensitive match
            foreach ($row as $rowKey => $value) {
                if (strtolower(trim((string) $rowKey)) === strtolower(trim($key))) {
                    return $value !== null ? (string) $value : null;
                }
            }
        }

        return null;
    }
}

