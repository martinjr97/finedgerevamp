<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends Model
{
    use HasFactory;

    protected $fillable = [
        'legacy_id',
        'asset_type',
        'name',
        'description',
        'acquisition_date',
        'value',
        'image_path',
        'is_active',
        'employee_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'acquisition_date' => 'date',
            'value' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'updated_by');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(AssetTransfer::class)->latest();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
