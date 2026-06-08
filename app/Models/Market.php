<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Market extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'address_line1',
        'address_line2',
        'city',
        'province_id',
        'district_id',
        'branch_id',
        'contact_person_name',
        'contact_person_phone',
        'contact_person_email',
        'portfolio_manager_id',
        'loan_rate_type_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function portfolioManager(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'portfolio_manager_id');
    }

    public function marketeerCustomerDetails(): HasMany
    {
        return $this->hasMany(MarketeerCustomerDetail::class);
    }

    public function loanRateType(): BelongsTo
    {
        return $this->belongsTo(LoanRateType::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
