<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_number',
        'first_name',
        'last_name',
        'phone',
        'email',
        'department',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => trim("{$this->first_name} {$this->last_name}")
        );
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
