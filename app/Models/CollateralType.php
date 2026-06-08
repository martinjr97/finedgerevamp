<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CollateralType extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'loan_product_id',
        'name',
        'code',
        'category',
        'description',
        'min_value',
        'max_value',
        'loan_to_value_ratio',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'min_value' => 'decimal:2',
            'max_value' => 'decimal:2',
            'loan_to_value_ratio' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Relationship to LoanProduct
     */
    public function loanProduct(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class);
    }
}
