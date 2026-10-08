<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeContract extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_TERMINATED = 'terminated';

    public const STATUS_RENEWED = 'renewed';

    protected $fillable = [
        'employee_id',
        'contract_type_id',
        'contract_number',
        'start_date',
        'end_date',
        'basic_pay_snapshot',
        'status',
        'probation_end_date',
        'signed_date',
        'termination_date',
        'termination_reason',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'probation_end_date' => 'date',
            'signed_date' => 'date',
            'termination_date' => 'date',
            'basic_pay_snapshot' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function contractType(): BelongsTo
    {
        return $this->belongsTo(ContractType::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeExpiringWithin($query, int $days)
    {
        $until = now()->addDays($days)->toDateString();

        return $query->where('status', self::STATUS_ACTIVE)
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<=', $until)
            ->whereDate('end_date', '>=', now()->toDateString());
    }
}
