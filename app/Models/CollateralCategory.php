<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CollateralCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'sort_order',
    ];

    /**
     * Categories ordered for dropdown display.
     */
    public static function optionsForSelect(): \Illuminate\Support\Collection
    {
        return static::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'name');
    }
}
