<?php

namespace App\Models;

use App\Support\PermissionMatrix;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class Admin extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, HasRoles;

    /**
     * Spatie guard to use for role/permission checks.
     */
    protected string $guard_name = 'admin';

    /**
     * Get the login audits for this admin.
     */
    public function loginAudits(): HasMany
    {
        return $this->hasMany(AdminLoginAudit::class);
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'branch_id',
        'employee_number',
        'nrc',
        'first_name',
        'last_name',
        'email',
        'password',
        'phone',
        'avatar_path',
        'is_active',
        'is_relationship_manager',
        'approval_status',
        'approved_by',
        'approved_at',
        'approval_notes',
        'must_change_password',
        'email_verified_at',
        'last_login_at',
        'last_login_ip',
        'preferences',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'approved_at' => 'datetime',
            'is_active' => 'boolean',
            'is_relationship_manager' => 'boolean',
            'preferences' => 'array',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }

    /**
     * Compute a convenient full name attribute.
     */
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => trim("{$this->first_name} {$this->last_name}")
        );
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }

    public function managedCompanies(): HasMany
    {
        return $this->hasMany(Company::class, 'relationship_manager_id');
    }

    /**
     * Get account audit activity records for this admin account.
     */
    public function accountAudits(): HasMany
    {
        return $this->hasMany(AdminAccountAudit::class, 'admin_id');
    }

    /**
     * Check if the admin's company is primary.
     * Primary company admins can see all data, non-primary admins are limited to their company.
     */
    public function isPrimaryCompanyAdmin(): bool
    {
        return $this->company && $this->company->is_primary;
    }

    /**
     * Get the company ID for filtering. Returns null if primary (no filter needed).
     */
    public function getCompanyFilterId(): ?int
    {
        if ($this->hasRole(PermissionMatrix::SUPER_ADMIN_ROLE)) {
            return null;
        }

        return $this->isPrimaryCompanyAdmin() ? null : $this->company_id;
    }
}
