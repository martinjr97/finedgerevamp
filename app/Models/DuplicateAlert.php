<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DuplicateAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'duplicate_customer_id',
        'match_type',
        'match_value',
        'notes',
        'cleared_by',
        'cleared_at',
    ];

    protected $casts = [
        'cleared_at' => 'datetime',
    ];

    /**
     * Get the customer that this alert is for
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Get the duplicate customer
     */
    public function duplicateCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'duplicate_customer_id');
    }

    /**
     * Get the admin who cleared this alert
     */
    public function clearedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'cleared_by');
    }

    /**
     * Check if this alert is cleared
     */
    public function isCleared(): bool
    {
        return $this->cleared_at !== null;
    }

    /**
     * Scope to get only cleared alerts
     */
    public function scopeCleared($query)
    {
        return $query->whereNotNull('cleared_at');
    }

    /**
     * Scope to get only active (not cleared) alerts
     */
    public function scopeActive($query)
    {
        return $query->whereNull('cleared_at');
    }
}
