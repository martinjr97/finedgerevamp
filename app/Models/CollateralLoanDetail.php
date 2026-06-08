<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollateralLoanDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_id',
        'collateral_type_id',
        'collateral_value',
        'loan_to_value_amount',
        'loan_to_value_ratio',
        'collateral_description',
        'serial_number',
        'item_quantity',
        'item_condition',
        'is_inspected',
        'inspected_by',
        'inspected_at',
        'location',
        'images',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'collateral_value' => 'decimal:2',
            'loan_to_value_amount' => 'decimal:2',
            'loan_to_value_ratio' => 'decimal:2',
            'item_quantity' => 'integer',
            'is_inspected' => 'boolean',
            'inspected_at' => 'datetime',
            'images' => 'array',
            'metadata' => 'array',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function collateralType(): BelongsTo
    {
        return $this->belongsTo(CollateralType::class);
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'inspected_by');
    }
}
