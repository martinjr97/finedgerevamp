<?php

namespace App\Models;

use App\Sms\Enums\SmsCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SmsMessage extends Model
{
    protected $fillable = [
        'recipient_type',
        'recipient_id',
        'customer_id',
        'admin_id',
        'loan_id',
        'phone_number',
        'normalized_phone',
        'message_category',
        'message_type',
        'message_body',
        'message_preview',
        'message_length',
        'provider',
        'status',
        'skip_reason',
        'provider_reference',
        'http_status',
        'provider_response',
        'attempt_count',
        'sent_at',
        'failed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'message_category' => SmsCategory::class,
            'provider_response' => 'array',
            'metadata' => 'array',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
            'attempt_count' => 'integer',
            'http_status' => 'integer',
            'message_length' => 'integer',
        ];
    }

    public function recipient(): MorphTo
    {
        return $this->morphTo();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function isSensitive(): bool
    {
        return $this->message_category instanceof SmsCategory
            && $this->message_category->isSensitive();
    }
}
