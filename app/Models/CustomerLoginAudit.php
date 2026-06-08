<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerLoginAudit extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'phone',
        'ip_address',
        'user_agent',
        'device_type',
        'device_name',
        'browser',
        'browser_version',
        'os',
        'os_version',
        'location_country',
        'location_region',
        'location_city',
        'status',
        'failure_reason',
        'attempted_at',
    ];

    protected $casts = [
        'attempted_at' => 'datetime',
    ];

    /**
     * Get the customer that this login audit belongs to.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Scope to filter successful logins.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope to filter failed logins.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope to filter by customer.
     */
    public function scopeForCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * Scope to filter by phone.
     */
    public function scopeForPhone($query, $phone)
    {
        return $query->where('phone', $phone);
    }
}
