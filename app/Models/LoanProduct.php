<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
            'rules' => 'array',
        ];
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
}
