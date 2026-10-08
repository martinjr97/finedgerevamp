<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeCompensation extends Model
{
    protected $table = 'employee_compensations';

    protected $fillable = [
        'employee_id',
        'basic_pay',
        'effective_from',
        'effective_to',
        'is_current',
        'currency',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'basic_pay' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_current' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
