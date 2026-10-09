<?php

namespace App\Models;

use App\Models\LoanRate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanProduct extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'name',
        'code',
        'category',
        'description',
        'tenure_months',
        'max_amount',
        'requires_collateral',
        'requires_reference',
        'rules',
        'is_active',
        'is_public_on_website',
        'public_website_loan_rate_type_id',
        'accrual_type',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_amount' => 'decimal:2',
            'requires_collateral' => 'boolean',
            'requires_reference' => 'boolean',
            'is_active' => 'boolean',
            'is_public_on_website' => 'boolean',
            'rules' => 'array',
        ];
    }

    public function publicWebsiteRateType(): BelongsTo
    {
        return $this->belongsTo(LoanRateType::class, 'public_website_loan_rate_type_id');
    }

    public function publicWebsiteRates(): BelongsToMany
    {
        return $this->belongsToMany(
            LoanRate::class,
            'loan_product_public_website_rate',
            'loan_product_id',
            'loan_rate_id',
        )->withTimestamps();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function customerGroups(): HasMany
    {
        return $this->hasMany(CustomerGroup::class);
    }

    public function loanRateTypes(): HasMany
    {
        return $this->hasMany(LoanRateType::class);
    }

    public function collateralTypes(): HasMany
    {
        return $this->hasMany(CollateralType::class);
    }

    public function groupLoanApplications(): HasMany
    {
        return $this->hasMany(GroupLoanApplication::class);
    }

    public function paymentGatewayProductRules(): HasMany
    {
        return $this->hasMany(PaymentGatewayProductRule::class);
    }

    public function isEmployeeLoanProduct(): bool
    {
        return $this->category === 'employee'
            || (bool) ($this->rules['employee_loan_only'] ?? false);
    }

    /**
     * Products offered on customer loan application flows.
     */
    public function scopeForCustomerLoanApplication($query)
    {
        return $query->where('category', '!=', 'employee');
    }
}
