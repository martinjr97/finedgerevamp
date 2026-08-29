<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyRelationshipManagerHistory extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'relationship_manager_id',
        'started_at',
        'ended_at',
        'change_reason',
        'changed_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function relationshipManager(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'relationship_manager_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'changed_by');
    }
}
