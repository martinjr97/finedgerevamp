<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CustomerRegistrationRequest extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reference',
        'registration_path',
        'loan_product_id',
        'customer_group_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'national_id',
        'national_id_type',
        'tpin',
        'requested_loan_amount',
        'status',
        'created_customer_id',
        'created_by_admin_id',
        'created_customer_at',
        'payload',
        'employment_details',
        'collateral_details',
        'approval_metadata',
        'ip_address',
        'user_agent',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'employment_details' => 'array',
            'collateral_details' => 'array',
            'approval_metadata' => 'array',
            'requested_loan_amount' => 'decimal:2',
            'created_customer_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class, 'loan_product_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class, 'customer_group_id');
    }

    public function createdCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'created_customer_id');
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }
}


