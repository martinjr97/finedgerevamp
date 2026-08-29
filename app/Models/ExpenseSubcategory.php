<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ExpenseSubcategory extends Model
{
    protected $fillable = [
        'legacy_id',
        'expense_category_id',
        'code',
        'name',
        'description',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function generateUniqueCode(int $categoryId, string $name, ?int $ignoreId = null): string
    {
        $base = Str::upper(Str::slug($name, '_'));
        $base = $base !== '' ? $base : 'SUBCATEGORY';
        $code = $base;
        $suffix = 2;

        while (self::query()
            ->where('expense_category_id', $categoryId)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where('code', $code)
            ->exists()) {
            $code = $base.'_'.$suffix;
            $suffix++;
        }

        return $code;
    }
}
