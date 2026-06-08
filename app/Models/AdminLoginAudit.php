<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminLoginAudit extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_id',
        'email',
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
     * Get the admin that this login audit belongs to.
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
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
     * Scope to filter by admin.
     */
    public function scopeForAdmin($query, $adminId)
    {
        return $query->where('admin_id', $adminId);
    }

    /**
     * Scope to filter by email.
     */
    public function scopeForEmail($query, $email)
    {
        return $query->where('email', $email);
    }
}
