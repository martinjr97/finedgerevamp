<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Services\DisbursementDestinationService;
use App\Services\LoanPricingService;
use Carbon\Carbon;

class Loan extends Model
{
    use HasFactory, SoftDeletes;

    public const INTEREST_BEHAVIOR_DAILY_ACCRUAL = 'daily_accrual';

    public const INTEREST_BEHAVIOR_UPFRONT_FLAT = 'upfront_flat';

    public const INTEREST_BEHAVIOR_AMORTIZED = 'amortized';

    public const SCHEDULE_BASIS_BOOKED = 'booked_total';

    public const SCHEDULE_BASIS_PROJECTED = 'projected_total';

    protected $fillable = [
        'customer_id',
        'loan_product_id',
        'customer_group_id',
        'loan_rate_id',
        'channel_id',
        'loan_number',
        'principal_amount',
        'processing_fee',
        'processing_fee_percentage',
        'daily_rate',
        'weekly_rate',
        'accrual_period',
        'interest_accrued',
        'total_amount',
        'amount_paid',
        'outstanding_balance',
        'tenure_months',
        'loan_start_date',
        'loan_end_date',
        'first_payment_date',
        'last_payment_date',
        'accrual_type',
        'quoted_term_rate',
        'interest_behavior',
        'last_accrual_date',
        'loan_settled_date',
        'settlement_amount',
        'settlement_date',
        'rebate_amount',
        'status',
        'approved_by',
        'approved_at',
        'approval_notes',
        'disbursement_phone_number',
        'disbursement_channel_type',
        'disbursement_financial_institution_id',
        'disbursement_financial_institution_branch_id',
        'disbursement_account_holder_name',
        'disbursement_account_number',
        'disbursement_destination_snapshot',
        'disbursement_status',
        'disbursed_at',
        'disbursement_notes',
        'disbursement_reference',
        'metadata',
        'disbursed_via_type',
        'disbursed_via_id',
    ];

    protected function casts(): array
    {
        return [
            'principal_amount' => 'decimal:2',
            'processing_fee' => 'decimal:2',
            'processing_fee_percentage' => 'decimal:2',
            'daily_rate' => 'decimal:8',
            'weekly_rate' => 'decimal:8',
            'quoted_term_rate' => 'decimal:4',
            'interest_accrued' => 'decimal:2',
            'settlement_amount' => 'decimal:2',
            'settlement_date' => 'date',
            'rebate_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'outstanding_balance' => 'decimal:2',
            'loan_start_date' => 'date',
            'loan_end_date' => 'date',
            'first_payment_date' => 'date',
            'last_payment_date' => 'date',
            'loan_settled_date' => 'date',
            'last_accrual_date' => 'date',
            'approved_at' => 'datetime',
            'disbursed_at' => 'datetime',
            'metadata' => 'array',
            'disbursement_destination_snapshot' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function loanProduct(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class);
    }

    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class);
    }

    public function loanRate(): BelongsTo
    {
        return $this->belongsTo(LoanRate::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function disbursementFinancialInstitution(): BelongsTo
    {
        return $this->belongsTo(FinancialInstitution::class, 'disbursement_financial_institution_id');
    }

    public function disbursementFinancialInstitutionBranch(): BelongsTo
    {
        return $this->belongsTo(FinancialInstitutionBranch::class, 'disbursement_financial_institution_branch_id');
    }

    public function disbursementChannelType(): string
    {
        if (filled($this->disbursement_channel_type)) {
            return (string) $this->disbursement_channel_type;
        }

        $channel = $this->relationLoaded('channel') ? $this->channel : null;
        if ($channel === null && $this->channel_id) {
            $channel = $this->channel()->first(['id', 'type', 'name', 'code']);
        }

        if ($channel?->type) {
            return $channel->type;
        }

        if (filled($this->disbursement_phone_number)) {
            return Channel::TYPE_MOBILE_WALLET;
        }

        return Channel::TYPE_MOBILE_WALLET;
    }

    public function hasMobileWalletDestination(): bool
    {
        return $this->disbursementChannelType() === Channel::TYPE_MOBILE_WALLET;
    }

    public function hasBankDestination(): bool
    {
        return $this->disbursementChannelType() === Channel::TYPE_BANK;
    }

    public function hasCashDestination(): bool
    {
        return $this->disbursementChannelType() === Channel::TYPE_CASH;
    }

    public function disbursementDestinationLabel(): string
    {
        $snapshot = $this->disbursement_destination_snapshot ?? [];

        if ($this->hasBankDestination()) {
            $institution = $snapshot['financial_institution_name']
                ?? $this->disbursementFinancialInstitution?->name
                ?? 'Bank';

            $branch = $snapshot['branch_name']
                ?? $this->disbursementFinancialInstitutionBranch?->name;

            return $branch ? "{$institution} · {$branch}" : $institution;
        }

        if ($this->hasCashDestination()) {
            return $snapshot['channel_name'] ?? $this->channel?->name ?? 'Cash';
        }

        $channelName = $snapshot['channel_name'] ?? $this->channel?->name ?? 'Mobile Wallet';
        $phone = $snapshot['disbursement_phone_number'] ?? $this->disbursement_phone_number;

        return $phone ? "{$channelName} · {$phone}" : $channelName;
    }

    public function disbursementChannelTypeLabel(): string
    {
        return match ($this->disbursementChannelType()) {
            Channel::TYPE_BANK => 'Bank Transfer',
            Channel::TYPE_CASH => 'Cash',
            default => 'Mobile Money',
        };
    }

    /**
     * Spreadsheet/report columns for disbursement destination (masked account; no raw account number).
     *
     * @return array<string, string>
     */
    public function disbursementDestinationExportColumns(): array
    {
        $snapshot = $this->disbursement_destination_snapshot ?? [];

        $columns = [
            'Disbursement Channel' => $this->channel?->name ?? ($snapshot['channel_name'] ?? 'N/A'),
            'Channel Type' => $this->disbursementChannelTypeLabel(),
            'Disbursement Destination' => $this->disbursementDestinationSummary() ?: 'N/A',
            'Mobile Wallet Number' => '',
            'Bank Name' => '',
            'Branch' => '',
            'Account Holder' => '',
            'Masked Account Number' => '',
            'Cash Notes' => '',
        ];

        if ($this->hasMobileWalletDestination()) {
            $columns['Mobile Wallet Number'] = $snapshot['disbursement_phone_number']
                ?? $this->disbursement_phone_number
                ?? 'N/A';
        } elseif ($this->hasBankDestination()) {
            $columns['Bank Name'] = $snapshot['financial_institution_name']
                ?? $this->disbursementFinancialInstitution?->name
                ?? 'N/A';
            $columns['Branch'] = $snapshot['branch_name']
                ?? $this->disbursementFinancialInstitutionBranch?->name
                ?? 'N/A';
            $columns['Account Holder'] = $snapshot['account_holder_name']
                ?? $this->disbursement_account_holder_name
                ?? 'N/A';
            $columns['Masked Account Number'] = $snapshot['masked_account_number']
                ?? DisbursementDestinationService::maskAccountNumber($this->disbursement_account_number)
                ?? 'N/A';
        } elseif ($this->hasCashDestination()) {
            $columns['Cash Notes'] = $snapshot['notes'] ?? $this->disbursement_notes ?? '';
        } elseif (filled($this->disbursement_phone_number)) {
            $columns['Mobile Wallet Number'] = $this->disbursement_phone_number;
        }

        return $columns;
    }

    public function disbursementDestinationSummary(): string
    {
        $snapshot = $this->disbursement_destination_snapshot ?? [];

        if ($this->hasBankDestination()) {
            $institution = $snapshot['financial_institution_name']
                ?? $this->disbursementFinancialInstitution?->name
                ?? 'Bank';
            $branch = $snapshot['branch_name']
                ?? $this->disbursementFinancialInstitutionBranch?->name
                ?? 'Branch';
            $holder = $snapshot['account_holder_name'] ?? $this->disbursement_account_holder_name;
            $masked = $snapshot['masked_account_number']
                ?? DisbursementDestinationService::maskAccountNumber($this->disbursement_account_number);

            $parts = array_filter([$institution, $branch, $holder, $masked]);

            return implode(' · ', $parts);
        }

        if ($this->hasCashDestination()) {
            $channelName = $snapshot['channel_name'] ?? $this->channel?->name ?? 'Cash';
            $notes = $snapshot['notes'] ?? $this->disbursement_notes;

            return $notes ? "{$channelName} · {$notes}" : $channelName;
        }

        $channelName = $snapshot['channel_name'] ?? $this->channel?->name ?? 'Mobile Wallet';
        $phone = $snapshot['disbursement_phone_number'] ?? $this->disbursement_phone_number;

        return $phone ? "{$channelName} · {$phone}" : $channelName;
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }

    /**
     * Get the bank or wallet where loan was disbursed
     */
    public function disbursedVia()
    {
        if ($this->disbursed_via_type === 'bank') {
            return $this->belongsTo(Bank::class, 'disbursed_via_id');
        } elseif ($this->disbursed_via_type === 'wallet') {
            return $this->belongsTo(Wallet::class, 'disbursed_via_id');
        }
        return null;
    }

    public function accruals(): HasMany
    {
        return $this->hasMany(LoanAccrual::class)->orderBy('accrual_date');
    }

    public function collateralLoanDetail(): HasOne
    {
        return $this->hasOne(CollateralLoanDetail::class);
    }

    public function groupLoanApplicationMember(): HasOne
    {
        return $this->hasOne(GroupLoanApplicationMember::class);
    }

    // TODO: Create LoanPayment model and migration
    // public function payments(): HasMany
    // {
    //     return $this->hasMany(LoanPayment::class);
    // }

    /**
     * Generate a unique loan number
     */
    public static function generateLoanNumber(?LoanProduct $loanProduct = null): string
    {
        $productCode = $loanProduct ? strtoupper($loanProduct->code) : 'DEF';
        $prefix = 'LN-'.$productCode;
        $date = now()->format('Ymd');
        
        // Generate unique loan number
        do {
            $random = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $loanNumber = $prefix .'-'. $date .'-'. $random;
        } while (self::where('loan_number', $loanNumber)->exists());
        
        return $loanNumber;
    }

    /**
     * Check if loan needs approval
     */
    public function needsApproval(): bool
    {
        return $this->status === 'pending_approval';
    }

    /**
     * Live portfolio loans: disbursed and currently active (in repayment).
     */
    public function scopeActivePortfolio($query)
    {
        return $query
            ->where('status', 'active')
            ->where('disbursement_status', 'completed');
    }

    /**
     * Loans that have completed disbursement (any lifecycle status).
     */
    public function scopeDisbursed($query)
    {
        return $query->where('disbursement_status', 'completed');
    }

    /**
     * Check if loan is active (disbursed and in repayment).
     */
    public function isActive(): bool
    {
        return $this->status === 'active'
            && $this->disbursement_status === 'completed';
    }

    /**
     * Mark disbursement complete and move the loan into active repayment status.
     */
    public function applyDisbursementCompleted(?Carbon $disbursedAt = null): void
    {
        $this->disbursement_status = 'completed';
        $this->disbursed_at = $disbursedAt ?? $this->disbursed_at ?? now();

        if (! in_array($this->status, ['settled', 'completed', 'cancelled', 'defaulted'], true)) {
            $this->status = 'active';
        }
    }

    /**
     * Backfill loans that were disbursed but still marked as approved only.
     */
    public static function syncActiveStatusForDisbursedLoans(): int
    {
        return static::query()
            ->where('disbursement_status', 'completed')
            ->where('status', 'approved')
            ->update(['status' => 'active']);
    }

    /**
     * Calculate daily interest for the loan using stored rates
     */
    public function calculateDailyInterest(): float
    {
        // Use stored daily_rate if available (for historical accuracy)
        if ($this->daily_rate) {
            return $this->principal_amount * $this->daily_rate;
        }

        // Fallback to loan rate relationship if stored rate not available
        if ($this->loanRate && $this->loanRate->daily_rate) {
            return $this->principal_amount * $this->loanRate->daily_rate;
        }

        return 0;
    }

    /**
     * Calculate weekly interest for the loan using stored rates
     */
    public function calculateWeeklyInterest(): float
    {
        // Use stored weekly_rate if available (for historical accuracy)
        if ($this->weekly_rate) {
            return $this->principal_amount * $this->weekly_rate;
        }

        // Fallback to loan rate relationship if stored rate not available
        if ($this->loanRate && $this->loanRate->weekly_rate) {
            return $this->principal_amount * $this->loanRate->weekly_rate;
        }

        return 0;
    }

    /**
     * Accrue interest for a specific date using stored rates and create accrual record
     */
    public function accrueInterestForDate(Carbon $date): void
    {
        if ($this->accrual_type !== 'daily') {
            return;
        }

        // Upfront / flat loans must never accrue additional interest via cron.
        if ($this->interest_behavior === self::INTEREST_BEHAVIOR_UPFRONT_FLAT) {
            return;
        }

        if ($date->isBefore($this->loan_start_date) || $date->isAfter($this->loan_end_date)) {
            return;
        }

        // Check if accrual already exists for this date
        $existingAccrual = $this->accruals()
            ->whereDate('accrual_date', $date)
            ->first();

        if ($existingAccrual) {
            return; // Already accrued for this date
        }

        // Use stored accrual_period or fallback to rate type
        $accrualPeriod = $this->accrual_period ?? ($this->loanRate?->loanRateType?->accrual_period ?? 'daily');
        
        $interest = 0;
        $rateUsed = 0;

        if ($accrualPeriod === 'daily') {
            $interest = $this->calculateDailyInterest();
            $rateUsed = $this->daily_rate ?? 0;
        } elseif ($accrualPeriod === 'weekly' && $date->isSameDay($date->copy()->startOfWeek())) {
            // Only accrue weekly interest on the first day of the week
            $interest = $this->calculateWeeklyInterest();
            $rateUsed = $this->weekly_rate ?? 0;
        } else {
            return; // Not a valid accrual date for weekly
        }

        if ($interest > 0) {
            // Get current cumulative interest before adding this accrual
            $lastAccrual = $this->accruals()->latest('accrual_date')->first();
            $cumulativeInterest = ($lastAccrual ? $lastAccrual->cumulative_interest : $this->interest_accrued) + $interest;
            
            // Update loan totals
            $this->interest_accrued += $interest;
            $this->total_amount += $interest;
            $this->outstanding_balance += $interest;
            $this->last_accrual_date = $date;
            $this->save();

            // Create accrual record
            LoanAccrual::create([
                'loan_id' => $this->id,
                'accrual_date' => $date,
                'principal_balance' => $this->principal_amount,
                'interest_amount' => $interest,
                'cumulative_interest' => $cumulativeInterest,
                'total_balance' => $this->principal_amount + $this->processing_fee + $cumulativeInterest,
                'accrual_period' => $accrualPeriod,
                'rate_used' => $rateUsed,
            ]);
        }
    }

    /**
     * Create accrual records for at_beginning loans (all at once)
     */
    public function createAtBeginningAccruals(): void
    {
        if ($this->accrual_type !== 'at_beginning') {
            return;
        }

        // Check if accruals already exist
        if ($this->accruals()->count() > 0) {
            return;
        }

        $accrualPeriod = $this->accrual_period ?? ($this->loanRate?->loanRateType?->accrual_period ?? 'daily');

        if ((float) $this->interest_accrued > 0) {
            $totalInterest = (float) $this->interest_accrued;
            $rateUsed = (float) ($this->daily_rate ?? $this->weekly_rate ?? 0);
        } else {
            $days = $this->loan_start_date->diffInDays($this->loan_end_date);

            $totalInterest = 0;
            $rateUsed = 0;

            if ($accrualPeriod === 'daily' && $this->daily_rate) {
                $totalInterest = $this->principal_amount * $this->daily_rate * $days;
                $rateUsed = $this->daily_rate;
            } elseif ($accrualPeriod === 'weekly' && $this->weekly_rate) {
                $weeks = ceil($days / 7);
                $totalInterest = $this->principal_amount * $this->weekly_rate * $weeks;
                $rateUsed = $this->weekly_rate;
            }
        }

        if ($totalInterest > 0) {
            // Create a single accrual record for the full interest amount
            LoanAccrual::create([
                'loan_id' => $this->id,
                'accrual_date' => $this->loan_start_date,
                'principal_balance' => $this->principal_amount,
                'interest_amount' => $totalInterest,
                'cumulative_interest' => $totalInterest,
                'total_balance' => $this->principal_amount + $this->processing_fee + $totalInterest,
                'accrual_period' => $accrualPeriod,
                'rate_used' => $rateUsed,
                'notes' => "Full interest calculated at loan creation (at_beginning accrual type)",
            ]);
        }
    }

    /**
     * Booked balance at origination (principal + fee + interest already recognized on the loan).
     */
    public function getBookedBalance(): float
    {
        return (float) $this->outstanding_balance;
    }

    /**
     * Interest recognized on the loan ledger (earned/booked), not projected future interest.
     */
    public function getEarnedInterest(): float
    {
        return (float) $this->interest_accrued;
    }

    /**
     * Booked loan total at origination (principal + fee + any interest booked upfront).
     */
    public function getBookedLoanTotal(): float
    {
        return (float) $this->total_amount;
    }

    public function getInterestBehaviorLabel(): string
    {
        return match ($this->interest_behavior) {
            self::INTEREST_BEHAVIOR_UPFRONT_FLAT => 'Upfront flat',
            self::INTEREST_BEHAVIOR_DAILY_ACCRUAL => 'Daily accrual',
            self::INTEREST_BEHAVIOR_AMORTIZED => 'Amortized',
            default => 'Legacy / '.($this->accrual_type ?? 'standard'),
        };
    }

    public function getRateInputModeLabel(): ?string
    {
        $mode = $this->loanRate?->loanRateType?->rate_input_mode
            ?? data_get($this->metadata, 'rate_input_mode');

        return match ($mode) {
            'term_percentage' => 'Term percentage',
            'daily_multiplier' => 'Daily multiplier',
            'weekly_multiplier' => 'Weekly multiplier',
            default => $mode ? str_replace('_', ' ', (string) $mode) : null,
        };
    }

    public function showsDailyAccrualDisclosure(): bool
    {
        return $this->interest_behavior === self::INTEREST_BEHAVIOR_DAILY_ACCRUAL
            || ($this->interest_behavior === null && $this->accrual_type === 'daily');
    }

    public function isSettled(): bool
    {
        return $this->loan_settled_date !== null
            || in_array($this->status, ['settled', 'completed'], true);
    }

    /**
     * Projected full-term interest from pricing metadata (disclosure / schedule for daily_accrual).
     */
    public function getProjectedInterest(): float
    {
        $fromMeta = data_get($this->metadata, 'projected_interest');

        if ($fromMeta !== null) {
            return (float) $fromMeta;
        }

        if ($this->interest_behavior === self::INTEREST_BEHAVIOR_UPFRONT_FLAT) {
            return (float) $this->interest_accrued;
        }

        return 0.0;
    }

    /**
     * Projected total repayment (principal + fee + projected interest) for planning schedules.
     */
    public function getProjectedTotalAmount(): float
    {
        $fromMeta = data_get($this->metadata, 'projected_total_amount');

        if ($fromMeta !== null) {
            return (float) $fromMeta;
        }

        return (float) $this->total_amount;
    }

    /**
     * Total expected repayment per persisted schedule (may use projected total for daily_accrual).
     */
    public function getScheduleExpectedTotal(): float
    {
        if ($this->relationLoaded('paymentSchedules') && $this->paymentSchedules->isNotEmpty()) {
            return (float) $this->paymentSchedules->sum('expected_amount');
        }

        if ($this->paymentSchedules()->exists()) {
            return (float) $this->paymentSchedules()->sum('expected_amount');
        }

        return $this->getSchedulePlan()['schedule_total'];
    }

    /**
     * Whether repayment schedule rows use projected (not yet earned) interest.
     */
    public function scheduleUsesProjectedInterest(): bool
    {
        if ($this->interest_behavior === self::INTEREST_BEHAVIOR_DAILY_ACCRUAL) {
            return true;
        }

        return (bool) data_get($this->metadata, 'schedule_uses_projected_interest', false);
    }

    /**
     * Resolve amounts and basis used when generating repayment schedule rows.
     *
     * upfront_flat: schedule_total = booked total (principal + fee + full interest).
     * daily_accrual: schedule_total = projected total; booked balance stays principal + fee.
     * legacy (no interest_behavior): schedule_total = loan.total_amount (historical behavior).
     *
     * @return array{
     *     schedule_total: float,
     *     principal: float,
     *     processing_fee: float,
     *     interest: float,
     *     schedule_basis: string|null,
     *     is_projected_interest: bool
     * }
     */
    public function getSchedulePlan(): array
    {
        $metadata = $this->metadata ?? [];
        $principal = (float) $this->principal_amount;
        $processingFee = (float) $this->processing_fee;

        if ($this->interest_behavior === self::INTEREST_BEHAVIOR_DAILY_ACCRUAL) {
            return [
                'schedule_total' => (float) ($metadata['projected_total_amount'] ?? $this->getProjectedTotalAmount()),
                'principal' => $principal,
                'processing_fee' => $processingFee,
                'interest' => (float) ($metadata['projected_interest'] ?? $this->getProjectedInterest()),
                'schedule_basis' => self::SCHEDULE_BASIS_PROJECTED,
                'is_projected_interest' => true,
            ];
        }

        if ($this->interest_behavior === self::INTEREST_BEHAVIOR_UPFRONT_FLAT) {
            $interest = (float) $this->interest_accrued;

            return [
                'schedule_total' => (float) $this->total_amount,
                'principal' => $principal,
                'processing_fee' => $processingFee,
                'interest' => $interest,
                'schedule_basis' => self::SCHEDULE_BASIS_BOOKED,
                'is_projected_interest' => false,
            ];
        }

        // Legacy loans: no pricing behavior snapshot — keep schedule aligned with booked total_amount.
        $scheduleTotal = (float) $this->total_amount;
        $interest = max(0, round($scheduleTotal - $principal - $processingFee, 2));

        return [
            'schedule_total' => $scheduleTotal,
            'principal' => $principal,
            'processing_fee' => $processingFee,
            'interest' => $interest,
            'schedule_basis' => self::SCHEDULE_BASIS_BOOKED,
            'is_projected_interest' => false,
        ];
    }

    /**
     * Get the monthly payment amount (based on schedule expected total, not booked balance).
     */
    public function getMonthlyPayment(): float
    {
        if ($this->tenure_months <= 0) {
            return 0;
        }

        return $this->getSchedulePlan()['schedule_total'] / $this->tenure_months;
    }

    /**
     * Calculate repayment allocation splits (principal, interest, processing fee)
     * Priority: Principal and Interest are allocated proportionally (they make up the total)
     * Processing fee only applies if outstanding and applicable (MOU/Government customers)
     * 
     * IMPORTANT: principal_amount + interest_amount + processing_fee_amount MUST equal paymentAmount
     * 
     * @param float $paymentAmount The amount being paid
     * @return array{principal_amount: float, interest_amount: float, processing_fee_amount: float}
     */
    public function calculateRepaymentAllocation(float $paymentAmount): array
    {
        if ($paymentAmount <= 0) {
            return [
                'principal_amount' => 0,
                'interest_amount' => 0,
                'processing_fee_amount' => 0,
            ];
        }

        // Check if processing fee should be considered (only for MOU/Government customers)
        $hasProcessingFee = $this->processing_fee > 0 && 
            $this->loanProduct && 
            in_array($this->loanProduct->category, ['mou', 'government', 'group_loans'], true);

        // Calculate outstanding amounts
        $totalOwed = $this->principal_amount + $this->interest_accrued;
        if ($hasProcessingFee) {
            $totalOwed += $this->processing_fee;
        }

        // Calculate how much is still unpaid for each component
        $totalPaid = $this->amount_paid;
        $outstandingBalance = $this->outstanding_balance;

        if ($outstandingBalance <= 0 || $totalOwed <= 0) {
            // Everything is paid or nothing owed, all payment goes to principal
            return [
                'principal_amount' => $paymentAmount,
                'interest_amount' => 0,
                'processing_fee_amount' => 0,
            ];
        }

        // Calculate unpaid portions based on outstanding balance proportion
        $principalRatio = $this->principal_amount / $totalOwed;
        $interestRatio = $this->interest_accrued / $totalOwed;
        $processingFeeRatio = $hasProcessingFee ? ($this->processing_fee / $totalOwed) : 0;

        // Estimate unpaid amounts based on outstanding balance
        $unpaidPrincipal = min($this->principal_amount, $outstandingBalance * $principalRatio);
        $unpaidInterest = min($this->interest_accrued, $outstandingBalance * $interestRatio);
        $unpaidProcessingFee = $hasProcessingFee ? min($this->processing_fee, $outstandingBalance * $processingFeeRatio) : 0;

        $principalAmount = 0;
        $interestAmount = 0;
        $processingFeeAmount = 0;
        $remainingPayment = $paymentAmount;

        // First, allocate to processing fee if applicable and outstanding
        if ($hasProcessingFee && $unpaidProcessingFee > 0 && $remainingPayment > 0) {
            $processingFeeAmount = min($unpaidProcessingFee, $remainingPayment);
            $remainingPayment -= $processingFeeAmount;
        }

        // Allocate remaining payment proportionally between principal and interest
        if ($remainingPayment > 0) {
            $totalUnpaidPrincipalInterest = $unpaidPrincipal + $unpaidInterest;
            
            if ($totalUnpaidPrincipalInterest > 0) {
                // Calculate proportions for principal and interest
                $principalProportion = $unpaidPrincipal / $totalUnpaidPrincipalInterest;
                $interestProportion = $unpaidInterest / $totalUnpaidPrincipalInterest;

                // Allocate based on proportions
                $principalAmount = $remainingPayment * $principalProportion;
                $interestAmount = $remainingPayment * $interestProportion;

                // Ensure we don't exceed unpaid amounts
                $principalAmount = min($principalAmount, $unpaidPrincipal);
                $interestAmount = min($interestAmount, $unpaidInterest);

                // Handle rounding - any remainder goes to principal
                $allocated = $principalAmount + $interestAmount;
                if ($allocated < $remainingPayment) {
                    $diff = $remainingPayment - $allocated;
                    $principalAmount = min($principalAmount + $diff, $unpaidPrincipal);
                    // Recalculate interest if principal took more
                    $interestAmount = min($remainingPayment - $principalAmount, $unpaidInterest);
                }
            } else {
                // If no principal/interest outstanding, all goes to principal
                $principalAmount = $remainingPayment;
            }
        }

        // CRITICAL: Ensure the sum equals paymentAmount (handle any rounding errors)
        $totalAllocated = $principalAmount + $interestAmount + $processingFeeAmount;
        if (abs($totalAllocated - $paymentAmount) > 0.01) {
            // Adjust principal to make up the difference
            $difference = $paymentAmount - $totalAllocated;
            $principalAmount += $difference;
            // Ensure principal doesn't go negative
            if ($principalAmount < 0) {
                $interestAmount += $principalAmount;
                $principalAmount = 0;
            }
        }

        return [
            'principal_amount' => round(max(0, $principalAmount), 2),
            'interest_amount' => round(max(0, $interestAmount), 2),
            'processing_fee_amount' => round(max(0, $processingFeeAmount), 2),
        ];
    }

    public function pmecSubmissionItems(): HasMany
    {
        return $this->hasMany(PmecSubmissionItem::class);
    }

    public function paymentSchedules(): HasMany
    {
        return $this->hasMany(LoanPaymentSchedule::class)->orderBy('period_number');
    }

    public function allPaymentSchedules(): HasMany
    {
        return $this->hasMany(LoanPaymentSchedule::class)
            ->withoutGlobalScope('non_restructured')
            ->orderBy('period_number');
    }

    public function loanRepayments(): HasMany
    {
        return $this->hasMany(LoanRepayment::class);
    }

    public function loanExtensions(): HasMany
    {
        return $this->hasMany(LoanExtension::class)->orderByDesc('created_at');
    }

    public function repayments()
    {
        return $this->belongsToMany(Repayment::class, 'loan_repayments')
            ->withPivot(['amount', 'principal_amount', 'interest_amount', 'processing_fee_amount', 'outstanding_balance_before', 'outstanding_balance_after'])
            ->withTimestamps();
    }

    /**
     * Explicit pay-day due dates (MOU/government), stored on loan metadata at creation.
     *
     * @return list<Carbon>|null
     */
    public function resolvePaymentDueDates(): ?array
    {
        $stored = data_get($this->metadata, 'payment_due_dates');

        if (! is_array($stored) || count($stored) !== (int) $this->tenure_months) {
            return null;
        }

        return array_map(static fn ($date) => Carbon::parse($date), $stored);
    }

    /**
     * Create and persist payment schedule to database.
     *
     * Schedule totals use booked_total for upfront_flat and projected_total for daily_accrual.
     * Booked outstanding_balance is never inflated by projected interest at origination.
     */
    public function createPaymentSchedule(): void
    {
        if (! $this->first_payment_date || $this->tenure_months <= 0) {
            return;
        }

        if ($this->paymentSchedules()->count() > 0) {
            return;
        }

        $plan = $this->getSchedulePlan();
        $pricing = app(LoanPricingService::class);

        $componentInstallments = $pricing->calculateComponentInstallments(
            $plan['principal'],
            $plan['processing_fee'],
            $plan['interest'],
            (int) $this->tenure_months,
        )['installments'];

        $explicitDueDates = $this->resolvePaymentDueDates();
        $repaymentStructure = $this->getRepaymentStructure();
        $today = Carbon::today();

        foreach ($componentInstallments as $row) {
            $period = (int) $row['period'];
            $periodIndex = $period - 1;

            if ($explicitDueDates !== null) {
                $dueDate = $explicitDueDates[$periodIndex];
            } elseif ($repaymentStructure === 'weekly') {
                $dueDate = $this->first_payment_date->copy()->addWeeks($periodIndex);
            } else {
                $dueDate = $this->first_payment_date->copy()->addMonths($periodIndex);
            }

            $expectedAmount = (float) $row['expected_amount'];
            $status = 'upcoming';
            $daysOverdue = 0;

            if ($dueDate->isPast()) {
                $status = 'overdue';
                $daysOverdue = max(0, $today->diffInDays($dueDate));
            }

            LoanPaymentSchedule::create([
                'loan_id' => $this->id,
                'period_number' => $period,
                'due_date' => $dueDate,
                'expected_amount' => $expectedAmount,
                'principal_component' => (float) $row['principal_component'],
                'interest_component' => (float) $row['interest_component'],
                'fee_component' => (float) $row['fee_component'],
                'schedule_basis' => $plan['schedule_basis'],
                'is_projected_interest' => $plan['is_projected_interest'],
                'amount_paid' => 0,
                'remaining_amount' => $expectedAmount,
                'status' => $status,
                'days_overdue' => $daysOverdue,
            ]);
        }
    }

    /**
     * Update payment schedule when a payment is made
     */
    public function updatePaymentSchedule(float $paymentAmount): void
    {
        if ($paymentAmount <= 0) {
            return;
        }

        $remainingPayment = $paymentAmount;

        // Get schedules ordered by due date (oldest first)
        $schedules = $this->paymentSchedules()
            ->where('remaining_amount', '>', 0)
            ->orderBy('due_date')
            ->orderBy('period_number')
            ->orderBy('id')
            ->get();

        foreach ($schedules as $schedule) {
            if ($remainingPayment <= 0) {
                break;
            }

            $amountToApply = min((float) $schedule->remaining_amount, $remainingPayment);
            $schedule->amount_paid += $amountToApply;
            $schedule->remaining_amount -= $amountToApply;
            $remainingPayment -= $amountToApply;

            $schedule->updateStatus();
            $schedule->save();
        }
    }

    /**
     * Reverse schedule allocations from the latest paid installments first.
     */
    public function reversePaymentSchedule(float $refundAmount): void
    {
        if ($refundAmount <= 0) {
            return;
        }

        $remainingRefund = $refundAmount;

        $schedules = $this->paymentSchedules()
            ->where('amount_paid', '>', 0)
            ->orderByDesc('due_date')
            ->orderByDesc('period_number')
            ->orderByDesc('id')
            ->get();

        foreach ($schedules as $schedule) {
            if ($remainingRefund <= 0) {
                break;
            }

            $amountToReverse = min((float) $schedule->amount_paid, $remainingRefund);
            $schedule->amount_paid = max(0, (float) $schedule->amount_paid - $amountToReverse);
            $schedule->remaining_amount = (float) $schedule->remaining_amount + $amountToReverse;
            $remainingRefund -= $amountToReverse;

            if ($schedule->amount_paid < (float) $schedule->expected_amount) {
                $schedule->paid_at = null;
            }

            $schedule->updateStatus();
            $schedule->save();
        }
    }

    /**
     * Sync outstanding balance from payment schedules
     */
    public function syncOutstandingBalanceFromSchedule(): void
    {
        $totalRemaining = $this->paymentSchedules()->sum('remaining_amount');
        $this->outstanding_balance = round($totalRemaining, 2);
        $this->save();
    }

    /**
     * Get repayment schedule as array for display
     * Returns data from persisted payment schedules if available, otherwise generates dynamically
     */
    public function getRepaymentSchedule(): array
    {
        $repaymentStructure = $this->getRepaymentStructure();

        // Check if payment schedules exist in database
        $schedules = $this->paymentSchedules;
        
        if ($schedules->isNotEmpty()) {
            // Return persisted schedules
            return $schedules->values()->map(function ($schedule, int $index) {
                // Update status before returning
                $schedule->updateStatus();
                
                return [
                    'period' => $index + 1,
                    'period_number' => $schedule->period_number,
                    'payment_date' => $schedule->due_date,
                    'expected_amount' => $schedule->expected_amount,
                    'principal_component' => $schedule->principal_component,
                    'fee_component' => $schedule->fee_component,
                    'interest_component' => $schedule->interest_component,
                    'schedule_basis' => $schedule->schedule_basis,
                    'is_projected_interest' => (bool) $schedule->is_projected_interest,
                    'amount_paid' => $schedule->amount_paid,
                    'remaining_amount' => $schedule->remaining_amount,
                    'status' => $schedule->status,
                    'is_overdue' => $schedule->isOverdue(),
                ];
            })->toArray();
        }

        // Fallback: Generate schedule dynamically if not persisted
        // This is for backward compatibility with loans created before payment schedules were persisted
        if (!$this->first_payment_date || $this->tenure_months <= 0) {
            return [];
        }

        $plan = $this->getSchedulePlan();
        $schedule = [];
        $monthlyPayment = $this->getMonthlyPayment();
        $remainingTotal = $plan['schedule_total'];
        $explicitDueDates = $this->resolvePaymentDueDates();
        
        for ($period = 1; $period <= $this->tenure_months; $period++) {
            $periodIndex = $period - 1;
            if ($explicitDueDates !== null) {
                $paymentDate = $explicitDueDates[$periodIndex];
            } elseif ($repaymentStructure === 'weekly') {
                $paymentDate = $this->first_payment_date->copy()->addWeeks($periodIndex);
            } else {
                $paymentDate = $this->first_payment_date->copy()->addMonths($periodIndex);
            }
            
            // For the last period, use the full remaining amount to avoid rounding errors
            $expectedAmount = ($period === $this->tenure_months) 
                ? $remainingTotal 
                : $monthlyPayment;
            
            // Calculate amount paid for this period (estimate based on total paid)
            $amountPaid = 0;
            if ($this->amount_paid > 0) {
                // Estimate: distribute payments proportionally across periods
                $paidPerPeriod = $this->amount_paid / $this->tenure_months;
                $amountPaid = min($expectedAmount, $paidPerPeriod * $period);
            }
            
            $remainingAmount = max(0, $expectedAmount - $amountPaid);
            
            // Determine status
            $today = Carbon::today();
            $status = 'upcoming';
            $isOverdue = false;
            
            if ($amountPaid >= $expectedAmount) {
                $status = $paymentDate->isFuture() ? 'paid_early' : 'paid';
            } elseif ($amountPaid > 0) {
                $status = 'partial';
                if ($paymentDate->isPast()) {
                    $isOverdue = true;
                    $status = 'partial';
                }
            } elseif ($paymentDate->isPast()) {
                $status = 'overdue';
                $isOverdue = true;
            }
            
            $schedule[] = [
                'period' => $period,
                'payment_date' => $paymentDate,
                'expected_amount' => round($expectedAmount, 2),
                'amount_paid' => round($amountPaid, 2),
                'remaining_amount' => round($remainingAmount, 2),
                'status' => $status,
                'is_overdue' => $isOverdue,
            ];
            
            $remainingTotal -= $expectedAmount;
        }
        
        return $schedule;
    }

    private function getRepaymentStructure(): string
    {
        $repaymentStructure = data_get($this->metadata ?? [], 'repayment_structure');

        return in_array($repaymentStructure, ['weekly', 'monthly'], true)
            ? $repaymentStructure
            : 'monthly';
    }

    /**
     * Get total overdue amount for this loan
     */
    public function getOverdueAmount(): float
    {
        $overdueSchedules = $this->paymentSchedules()
            ->where('status', 'overdue')
            ->orWhere(function ($q) {
                $q->where('due_date', '<', Carbon::today())
                  ->where('remaining_amount', '>', 0);
            })
            ->get();

        return $overdueSchedules->sum('remaining_amount');
    }

    /**
     * Check if loan has overdue payments
     */
    public function hasOverdue(): bool
    {
        return $this->paymentSchedules()
            ->where(function ($q) {
                $q->where('status', 'overdue')
                  ->orWhere(function ($query) {
                      $query->where('due_date', '<', Carbon::today())
                            ->where('remaining_amount', '>', 0);
                  });
            })
            ->exists();
    }

    /**
     * Get overdue payment periods
     */
    public function getOverduePeriods(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->paymentSchedules()
            ->where(function ($q) {
                $q->where('status', 'overdue')
                  ->orWhere(function ($query) {
                      $query->where('due_date', '<', Carbon::today())
                            ->where('remaining_amount', '>', 0);
                  });
            })
            ->orderBy('due_date')
            ->get();
    }

    /**
     * Get PAR 30, 60, 90 status
     */
    public function getPARStatus(): ?string
    {
        if (!in_array($this->status, ['approved', 'active'])) {
            return null;
        }

        // Get the most overdue schedule item
        $mostOverdue = $this->paymentSchedules()
            ->where(function ($q) {
                $q->where('status', 'overdue')
                  ->orWhere(function ($query) {
                      $query->where('due_date', '<', Carbon::today())
                            ->where('remaining_amount', '>', 0);
                  });
            })
            ->orderBy('days_overdue', 'desc')
            ->first();

        if (!$mostOverdue) {
            return null;
        }

        $daysOverdue = $mostOverdue->days_overdue;

        if ($daysOverdue >= 90) {
            return 'PAR90';
        } elseif ($daysOverdue >= 60) {
            return 'PAR60';
        } elseif ($daysOverdue >= 30) {
            return 'PAR30';
        }

        return null;
    }
}
