<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    use HasFactory;

    protected $fillable = [
        'question',
        'answer',
        'visibility',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public const VISIBILITY_PUBLIC = 'public';
    public const VISIBILITY_AUTHENTICATED = 'authenticated';
    public const VISIBILITY_BOTH = 'both';

    public static function visibilities(): array
    {
        return [
            self::VISIBILITY_PUBLIC,
            self::VISIBILITY_AUTHENTICATED,
            self::VISIBILITY_BOTH,
        ];
    }
}


