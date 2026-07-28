<?php

namespace App\Models;

use App\Sms\Enums\SmsCategory;
use Illuminate\Database\Eloquent\Model;

class SmsTemplate extends Model
{
    protected $fillable = [
        'key',
        'name',
        'category',
        'body',
        'max_length',
        'is_active',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'category' => SmsCategory::class,
            'max_length' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
