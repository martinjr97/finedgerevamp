<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PmecSubmission extends Model
{
    protected $fillable = [
        'batch_number',
        'loan_product_id',
        'customer_group_id',
        'submission_month',
        'mode',
        'status',
        'generated_by',
        'generated_at',
        'notes',
        'file_path',
    ];

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
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

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'generated_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PmecSubmissionItem::class);
    }
}
