<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmecSubmissionItem extends Model
{
    protected $fillable = [
        'pmec_submission_id',
        'loan_id',
        'customer_id',
        'pernr',
        'nrc',
        'first_name',
        'surname',
        'begda',
        'endda',
        'betrg',
        'lgart',
        'emfsl',
        'zlsch',
        'status',
        'failure_reason',
        'previous_submission_item_id',
    ];

    protected function casts(): array
    {
        return [
            'begda' => 'date',
            'endda' => 'date',
            'betrg' => 'decimal:2',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(PmecSubmission::class, 'pmec_submission_id');
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function previousSubmissionItem(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_submission_item_id');
    }
}
