<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MigrationIdentityResolution extends Model
{
    public const CLASS_SAME_PERSON_MAP_ONE = 'SAME_PERSON_KEEP_SEPARATE_HISTORY_MAP_ONE_TARGET';

    public const CLASS_KEEP_SEPARATE = 'KEEP_SEPARATE_DISTINCT_PERSONS';

    public const CLASS_EXCLUDE = 'EXCLUDE_FROM_MIGRATION';

    public const STATUS_APPROVED = 'approved';

    protected $fillable = [
        'nrc',
        'primary_legacy_user_id',
        'alias_legacy_user_ids',
        'excluded_legacy_user_ids',
        'target_customer_id',
        'classification',
        'status',
        'reason',
        'legacy_context',
        'decided_by',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'alias_legacy_user_ids' => 'array',
            'excluded_legacy_user_ids' => 'array',
            'legacy_context' => 'array',
            'applied_at' => 'datetime',
        ];
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'decided_by');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * @return array<string, mixed>
     */
    public function toCatalogEntry(): array
    {
        return [
            'classification' => $this->classification,
            'primary_legacy_user_id' => (int) $this->primary_legacy_user_id,
            'alias_legacy_user_ids' => $this->alias_legacy_user_ids ?? [],
            'excluded_legacy_user_ids' => $this->excluded_legacy_user_ids ?? [],
            'target_customer_id' => $this->target_customer_id,
            'reason' => $this->reason,
            'source' => 'database',
            'status' => $this->status,
        ];
    }
}
