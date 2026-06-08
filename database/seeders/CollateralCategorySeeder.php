<?php

namespace Database\Seeders;

use App\Models\CollateralCategory;
use Illuminate\Database\Seeder;

class CollateralCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Vehicle', 'sort_order' => 10],
            ['name' => 'Property', 'sort_order' => 20],
            ['name' => 'Equipment', 'sort_order' => 30],
            ['name' => 'Inventory', 'sort_order' => 40],
            ['name' => 'Receivables', 'sort_order' => 50],
            ['name' => 'Securities', 'sort_order' => 60],
            ['name' => 'Other', 'sort_order' => 100],
        ];

        foreach ($categories as $category) {
            CollateralCategory::firstOrCreate(
                ['name' => $category['name']],
                ['sort_order' => $category['sort_order']]
            );
        }
    }
}
