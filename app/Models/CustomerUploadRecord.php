<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerUploadRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'row_number',
        'data',
        'status',
        'error_message',
        'customer_id',
        'discarded_at',
        'discarded_by',
    ];

    protected $casts = [
        'data' => 'array',
        'row_number' => 'integer',
        'discarded_at' => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(CustomerUploadBatch::class, 'batch_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function discardedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'discarded_by');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    public function scopeNotDiscarded($query)
    {
        return $query->whereNull('discarded_at');
    }

    public function scopeDiscarded($query)
    {
        return $query->whereNotNull('discarded_at');
    }

    public function isDiscarded(): bool
    {
        return $this->discarded_at !== null;
    }
}
