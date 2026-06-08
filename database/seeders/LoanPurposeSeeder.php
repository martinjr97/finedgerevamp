<?php

namespace Database\Seeders;

use App\Models\LoanPurpose;
use Illuminate\Database\Seeder;

class LoanPurposeSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    private const DEFAULT_PURPOSES = [
        'Personal Use',
        'Business Capital',
        'Business Expansion',
        'School Fees',
        'Medical Expenses',
        'Home Improvement',
        'Land Purchase',
        'Motor Vehicle Purchase',
        'Agriculture',
        'Debt Consolidation',
        'Emergency Expenses',
        'Other',
    ];

    public function run(): void
    {
        foreach (self::DEFAULT_PURPOSES as $index => $name) {
            LoanPurpose::query()->updateOrCreate(
                ['name' => $name],
                [
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );
        }
    }
}
