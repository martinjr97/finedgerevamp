<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'code',
        'type',
        'registration_number',
        'tpin',
        'date_of_incorporation',
        'mou_expiry_date',
        'sector_id',
        'relationship_manager_id',
        'loan_rate_type_id',
        'maximum_loan_tenure_months',
        'monthly_cut_off_day',
        'pay_day',
        'maximum_debit_ratio',
        'instalment_cross_over_percentage',
        'arrangement_fee_percentage',
        'contact_email',
        'contact_phone',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postal_code',
        'country',
        'is_primary',
        'status',
        'approval_status',
        'approved_by',
        'approved_at',
        'approval_notes',
        'settings',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'date_of_incorporation' => 'date',
            'mou_expiry_date' => 'date',
            'maximum_loan_tenure_months' => 'integer',
            'monthly_cut_off_day' => 'integer',
            'pay_day' => 'integer',
            'maximum_debit_ratio' => 'decimal:2',
            'instalment_cross_over_percentage' => 'decimal:2',
            'arrangement_fee_percentage' => 'decimal:2',
            'approved_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    public function admins(): HasMany
    {
        return $this->hasMany(Admin::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function loanProducts(): HasMany
    {
        return $this->hasMany(LoanProduct::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function relationshipManager(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'relationship_manager_id');
    }

    public function loanRateType(): BelongsTo
    {
        return $this->belongsTo(LoanRateType::class);
    }
}
