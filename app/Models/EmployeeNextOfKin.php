<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeNextOfKin extends Model
{
    protected $table = 'employee_next_of_kin';

    protected $fillable = [
        'employee_id',
        'full_name',
        'relationship',
        'phone_number',
        'alternative_phone',
        'email',
        'address',
        'is_primary',
    ];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
