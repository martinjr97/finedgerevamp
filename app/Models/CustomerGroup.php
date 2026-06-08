<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerGroup extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'loan_product_id',
        'loan_rate_type_id',
        'branch_id',
        'relationship_manager_id',
        'name',
        'code',
        'description',
        'risk_level',
        'max_loan_amount',
        'max_loan_tenure_months',
        'instalment_cross_over_percentage',
        'maximum_debit_ratio',
        'loan_cut_off_day',
        'loan_payment_date',
        'is_active',
        'allow_multiple_loans',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'allow_multiple_loans' => 'boolean',
            'instalment_cross_over_percentage' => 'decimal:2',
            'maximum_debit_ratio' => 'decimal:2',
        ];
    }

    public function loanProduct(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function loanRateType(): BelongsTo
    {
        return $this->belongsTo(LoanRateType::class);
    }

    public function relationshipManager(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'relationship_manager_id');
    }

    public function relationshipManagerHistories(): HasMany
    {
        return $this->hasMany(CustomerGroupRelationshipManagerHistory::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function groupLoanApplications(): HasMany
    {
        return $this->hasMany(GroupLoanApplication::class);
    }
}
