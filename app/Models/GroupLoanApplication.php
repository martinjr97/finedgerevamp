<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GroupLoanApplication extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'loan_product_id',
        'customer_group_id',
        'relationship_manager_id',
        'reference',
        'group_name',
        'loan_name',
        'terms_and_conditions',
        'repayment_structure',
        'start_date',
        'due_date',
        'processing_fee_percentage',
        'monthly_interest_rate',
        'arrears_rate',
        'total_principal_amount',
        'total_processing_fee_amount',
        'total_interest_amount',
        'total_repayment_amount',
        'total_disbursement_amount',
        'status',
        'approved_by',
        'approved_at',
        'approval_notes',
        'created_by',
        'submitted_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'due_date' => 'date',
            'processing_fee_percentage' => 'decimal:4',
            'monthly_interest_rate' => 'decimal:4',
            'arrears_rate' => 'decimal:4',
            'total_principal_amount' => 'decimal:2',
            'total_processing_fee_amount' => 'decimal:2',
            'total_interest_amount' => 'decimal:2',
            'total_repayment_amount' => 'decimal:2',
            'total_disbursement_amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'submitted_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function loanProduct(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class);
    }

    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class);
    }

    public function relationshipManager(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'relationship_manager_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function members(): HasMany
    {
        return $this->hasMany(GroupLoanApplicationMember::class)->orderBy('id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(GroupLoanApplicationDocument::class)->orderByDesc('created_at');
    }

    public function syncDisbursementStatusFromMembers(): void
    {
        $this->loadMissing('members.loan');

        if ($this->status !== 'awaiting_disbursement' && $this->status !== 'partially_disbursed') {
            return;
        }

        $members = $this->members;
        if ($members->isEmpty()) {
            return;
        }

        $completedCount = 0;

        foreach ($members as $member) {
            $loan = $member->loan;
            if (! $loan) {
                continue;
            }

            $memberStatus = $loan->disbursement_status;
            if ($member->disbursement_status !== $memberStatus
                || $member->disbursed_at?->toDateTimeString() !== $loan->disbursed_at?->toDateTimeString()
                || $member->disbursement_reference !== $loan->disbursement_reference
            ) {
                $member->update([
                    'disbursement_status' => $memberStatus,
                    'disbursed_at' => $loan->disbursed_at,
                    'disbursement_reference' => $loan->disbursement_reference,
                    'disbursement_notes' => $loan->disbursement_notes,
                    'disbursement_account_reference' => $loan->disbursement_phone_number,
                ]);
            }

            if ($memberStatus === 'completed') {
                $completedCount++;
            }
        }

        $newStatus = $completedCount === 0
            ? 'awaiting_disbursement'
            : ($completedCount === $members->count() ? 'disbursed' : 'partially_disbursed');

        if ($this->status !== $newStatus) {
            $this->status = $newStatus;
            $this->save();
        }
    }
}
