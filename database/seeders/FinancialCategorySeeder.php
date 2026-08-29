<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use App\Models\ExpenseSubcategory;
use App\Models\IncomeCategory;
use Illuminate\Database\Seeder;

class FinancialCategorySeeder extends Seeder
{
    public function run(): void
    {
        $expenseCategories = [
            ['name' => 'Operational', 'code' => 'operational', 'description' => 'General operational expenses'],
            ['name' => 'Administrative', 'code' => 'administrative', 'description' => 'Administrative and office expenses'],
            ['name' => 'Marketing', 'code' => 'marketing', 'description' => 'Marketing and advertising'],
            ['name' => 'Salaries', 'code' => 'salaries', 'description' => 'Staff salaries and wages'],
            ['name' => 'Utilities', 'code' => 'utilities', 'description' => 'Electricity, water, internet, and utilities'],
            ['name' => 'Rent', 'code' => 'rent', 'description' => 'Office or property rent'],
            ['name' => 'Other Expense', 'code' => 'other_expense', 'description' => 'Miscellaneous expenses'],
        ];

        foreach ($expenseCategories as $index => $definition) {
            $category = ExpenseCategory::query()->updateOrCreate(
                ['code' => $definition['code']],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ],
            );

            ExpenseSubcategory::query()->firstOrCreate(
                [
                    'expense_category_id' => $category->id,
                    'name' => 'Unclassified',
                ],
                [
                    'code' => 'UNCLASSIFIED',
                    'description' => 'Default subcategory for unclassified expenses',
                    'is_active' => true,
                ],
            );
        }

        $incomeCategories = [
            ['name' => 'Loan Interest', 'code' => 'loan_interest', 'is_system' => true],
            ['name' => 'Loan Processing Fee', 'code' => 'loan_processing_fee', 'is_system' => true],
            ['name' => 'Shareholder Contribution', 'code' => 'shareholder_contribution', 'is_system' => false],
            ['name' => 'Investment Income', 'code' => 'investment_income', 'is_system' => false],
            ['name' => 'Donation', 'code' => 'donation', 'is_system' => false],
            ['name' => 'Grant', 'code' => 'grant', 'is_system' => false],
            ['name' => 'Other Income', 'code' => 'other_income', 'is_system' => false],
        ];

        foreach ($incomeCategories as $index => $definition) {
            IncomeCategory::query()->updateOrCreate(
                ['code' => $definition['code']],
                [
                    'name' => $definition['name'],
                    'description' => null,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                    'is_system' => $definition['is_system'],
                ],
            );
        }
    }
}
