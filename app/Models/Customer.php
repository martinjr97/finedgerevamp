<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Sanctum\HasApiTokens;

class Customer extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'loan_product_id',
        'customer_group_id',
        'group_member_title_id',
        'reference_company_id',
        'customer_type',
        'parent_customer_id',
        'referred_by',
        'first_name',
        'last_name',
        'registered_name',
        'email',
        'password',
        'phone',
        'date_of_birth',
        'gender',
        'national_id',
        'national_id_type',
        'tpin',
        'status',
        'kyc_status',
        'employment_status',
        'annual_income',
        'address_line1',
        'address_line2',
        'city',
        'province_id',
        'state',
        'postal_code',
        'country',
        'avatar_path',
        'preferred_language',
        'email_verified_at',
        'last_login_at',
        'last_login_ip',
        'metadata',
        'must_change_password',
        'must_change_pin',
        'security_question_id',
        'security_answer',
        // Government-specific fields
        'ministry_id',
        'employee_number',
        'date_of_employment',
        'contract_end_date',
        'gross_salary',
        'net_salary',
        'deductions',
        'verified_by',
        // MOU-specific fields
        'position',
        'unit',
        'department',
        // Work address fields
        'work_address_line1',
        'work_address_line2',
        'work_city',
        'work_province_id',
        'work_district_id',
        'work_postal_code',
        'work_country',
        // Loan calculation
        'maximum_loan_take',
        'credit_score',
        'credit_score_updated_at',
        // Approval fields
        'approval_status',
        'approved_by',
        'approved_at',
        'approval_notes',
        // Character-based loan fields
        'next_of_kin_name',
        'next_of_kin_phone',
        'next_of_kin_relationship',
        'next_of_kin_address_line1',
        'next_of_kin_address_line2',
        'next_of_kin_city',
        'next_of_kin_country',
        'is_employed',
        'payday',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'approved_at' => 'datetime',
            'date_of_birth' => 'date',
            'date_of_employment' => 'date',
            'contract_end_date' => 'date',
            'is_employed' => 'boolean',
            'annual_income' => 'decimal:2',
            'gross_salary' => 'decimal:2',
            'net_salary' => 'decimal:2',
            'deductions' => 'decimal:2',
            'maximum_loan_take' => 'decimal:2',
            'credit_score' => 'decimal:2',
            'credit_score_updated_at' => 'datetime',
            'metadata' => 'array',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'must_change_pin' => 'boolean',
        ];
    }

    /**
     * Compute the customer's full display name.
     */
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->registered_name
                ? $this->registered_name
                : trim("{$this->first_name} {$this->last_name}")
        );
    }

    public function parentCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'parent_customer_id');
    }

    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'referred_by');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Customer::class, 'referred_by');
    }

    public function representatives(): HasMany
    {
        return $this->hasMany(Customer::class, 'parent_customer_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function loanProduct(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class);
    }

    public function referenceCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'reference_company_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'verified_by');
    }

    public function ministry(): BelongsTo
    {
        return $this->belongsTo(Ministry::class);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class, 'province_id');
    }

    public function workProvince(): BelongsTo
    {
        return $this->belongsTo(Province::class, 'work_province_id');
    }

    public function workDistrict(): BelongsTo
    {
        return $this->belongsTo(District::class, 'work_district_id');
    }

    public function kycDocuments(): HasMany
    {
        return $this->hasMany(KycDocument::class);
    }

    public function latestKycDocument()
    {
        return $this->hasOne(KycDocument::class)->latestOfMany();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }

    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class);
    }

    public function groupMemberTitle(): BelongsTo
    {
        return $this->belongsTo(GroupMemberTitle::class);
    }

    public function securityQuestion(): BelongsTo
    {
        return $this->belongsTo(SecurityQuestion::class);
    }

    public function marketeerCustomerDetail()
    {
        return $this->hasOne(MarketeerCustomerDetail::class);
    }

    public function paymentDetail(): HasOne
    {
        return $this->hasOne(CustomerPaymentDetail::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class)->orderBy('loan_start_date', 'desc');
    }

    public function loginAudits(): HasMany
    {
        return $this->hasMany(CustomerLoginAudit::class);
    }

    /**
     * Get active loans (approved, active status)
     */
    public function activeLoans()
    {
        return $this->loans()->whereIn('status', ['approved', 'active'])->get();
    }

    /**
     * Calculate total outstanding balance across all active loans
     */
    public function getTotalOutstandingBalance(): float
    {
        return $this->loans()
            ->whereIn('status', ['approved', 'active'])
            ->sum('outstanding_balance') ?? 0.00;
    }

    /**
     * Get available loan amount (maximum loan take minus outstanding balance)
     */
    public function getAvailableLoanAmount(): float
    {
        $maximumLoanTake = $this->maximum_loan_take ?? 0;
        $outstandingBalance = $this->getTotalOutstandingBalance();
        
        return max(0, $maximumLoanTake - $outstandingBalance);
    }

    /**
     * Check if customer can take another loan
     */
    public function canTakeAnotherLoan(): bool
    {
        $customerGroup = $this->customerGroup;
        
        // If no group, cannot take multiple loans
        if (!$customerGroup) {
            return false;
        }

        // If multiple loans not allowed and customer has active loans, cannot take another
        if (!$customerGroup->allow_multiple_loans) {
            $activeLoansCount = $this->loans()
                ->whereIn('status', ['approved', 'active', 'pending_approval'])
                ->count();
            
            return $activeLoansCount === 0;
        }

        // If multiple loans allowed, check if they have available loan amount
        return $this->getAvailableLoanAmount() > 0;
    }

    /**
     * Exposure and concurrency context for UI and eligibility checks.
     *
     * @return array{
     *     maximum_loan_take: float,
     *     outstanding_balance: float,
     *     available_loan_amount: float,
     *     allow_multiple_loans: bool,
     *     can_take_another_loan: bool,
     *     loan_eligibility_blocking_message: string|null,
     * }
     */
    public function getLoanExposureSummary(): array
    {
        $customerGroup = $this->customerGroup;
        $canTakeAnotherLoan = $this->canTakeAnotherLoan();

        return [
            'maximum_loan_take' => (float) ($this->maximum_loan_take ?? 0),
            'outstanding_balance' => $this->getTotalOutstandingBalance(),
            'available_loan_amount' => $this->getAvailableLoanAmount(),
            'allow_multiple_loans' => (bool) ($customerGroup?->allow_multiple_loans ?? false),
            'can_take_another_loan' => $canTakeAnotherLoan,
            'loan_eligibility_blocking_message' => $canTakeAnotherLoan ? null : $this->loanEligibilityBlockingMessage(),
        ];
    }

    /**
     * Human-readable reason when {@see canTakeAnotherLoan()} is false.
     */
    public function loanEligibilityBlockingMessage(): string
    {
        return 'This customer cannot take another loan because their group does not allow multiple active loans, or they have no available exposure.';
    }

    /**
     * Get the next payment date from active loans
     */
    public function getNextPaymentDate(): ?\Carbon\Carbon
    {
        $nextPayment = $this->loans()
            ->whereIn('status', ['approved', 'active'])
            ->whereNotNull('first_payment_date')
            ->where('first_payment_date', '>=', now()->toDateString())
            ->orderBy('first_payment_date', 'asc')
            ->first();

        return $nextPayment ? $nextPayment->first_payment_date : null;
    }

    /**
     * Calculate total overdue amount across all active loans
     */
    public function getTotalOverdueAmount(): float
    {
        $totalOverdue = 0.00;
        
        foreach ($this->activeLoans() as $loan) {
            $totalOverdue += $loan->getOverdueAmount();
        }

        return $totalOverdue;
    }

    /**
     * Check if customer has any overdue loans
     */
    public function hasOverdueLoans(): bool
    {
        foreach ($this->activeLoans() as $loan) {
            if ($loan->hasOverdue()) {
                return true;
            }
        }

        return false;
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(Repayment::class)->orderBy('created_at', 'desc');
    }
}
