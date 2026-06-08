<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupLoanApplicationMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_loan_application_id',
        'customer_id',
        'customer_group_id',
        'group_member_title_id',
        'principal_amount',
        'calculated_processing_fee_amount',
        'calculated_interest_amount',
        'calculated_arrears_basis_amount',
        'calculated_total_repayment_amount',
        'disbursement_amount',
        'loan_id',
        'disbursement_account_reference',
        'disbursement_status',
        'disbursed_at',
        'disbursement_reference',
        'disbursement_notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'principal_amount' => 'decimal:2',
            'calculated_processing_fee_amount' => 'decimal:2',
            'calculated_interest_amount' => 'decimal:2',
            'calculated_arrears_basis_amount' => 'decimal:2',
            'calculated_total_repayment_amount' => 'decimal:2',
            'disbursement_amount' => 'decimal:2',
            'disbursed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function groupLoanApplication(): BelongsTo
    {
        return $this->belongsTo(GroupLoanApplication::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class);
    }

    public function groupMemberTitle(): BelongsTo
    {
        return $this->belongsTo(GroupMemberTitle::class);
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}
