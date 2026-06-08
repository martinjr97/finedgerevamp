<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeneralSetting extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'allow_customer_registration',
        'public_registration_product_ids',
        'public_registration_group_ids',
        'public_registration_paths',
        'repayment_reminders_enabled',
        'remind_1_week_before',
        'remind_2_days_before',
        'remind_1_day_before',
        'missed_payment_reminder_count',
        'auto_adjust_loan_limit_by_credit_score',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allow_customer_registration' => 'boolean',
            'public_registration_product_ids' => 'array',
            'public_registration_group_ids' => 'array',
            'public_registration_paths' => 'array',
            'repayment_reminders_enabled' => 'boolean',
            'remind_1_week_before' => 'boolean',
            'remind_2_days_before' => 'boolean',
            'remind_1_day_before' => 'boolean',
            'missed_payment_reminder_count' => 'integer',
            'auto_adjust_loan_limit_by_credit_score' => 'boolean',
        ];
    }
}


