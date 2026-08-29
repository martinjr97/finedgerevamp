<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class IncomeCategory extends Model
{
    protected $fillable = [
        'legacy_id',
        'name',
        'code',
        'description',
        'sort_order',
        'is_active',
        'is_system',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_system' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public static function generateUniqueCode(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name, '_');
        $base = $base !== '' ? $base : 'income';
        $code = strtoupper($base);
        $suffix = 2;

        while (self::query()
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where('code', $code)
            ->exists()) {
            $code = strtoupper($base).'_'.$suffix;
            $suffix++;
        }

        return $code;
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'updated_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
