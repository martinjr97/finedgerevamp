<?php

namespace Database\Seeders;

use App\Models\Ministry;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MinistrySeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $ministries = [
            ['name' => 'Ministry of Finance', 'code' => 'MOF', 'description' => 'Ministry responsible for financial and economic policy'],
            ['name' => 'Ministry of Education', 'code' => 'MOE', 'description' => 'Ministry responsible for education and training'],
            ['name' => 'Ministry of Health', 'code' => 'MOH', 'description' => 'Ministry responsible for health services'],
            ['name' => 'Ministry of Agriculture', 'code' => 'MOA', 'description' => 'Ministry responsible for agricultural development'],
            ['name' => 'Ministry of Transport', 'code' => 'MOT', 'description' => 'Ministry responsible for transport infrastructure'],
            ['name' => 'Ministry of Energy', 'code' => 'MOE', 'description' => 'Ministry responsible for energy and power'],
            ['name' => 'Ministry of Water', 'code' => 'MOW', 'description' => 'Ministry responsible for water resources'],
            ['name' => 'Ministry of Lands', 'code' => 'MOL', 'description' => 'Ministry responsible for land administration'],
            ['name' => 'Ministry of Interior', 'code' => 'MOI', 'description' => 'Ministry responsible for internal security'],
            ['name' => 'Ministry of Defence', 'code' => 'MOD', 'description' => 'Ministry responsible for national defence'],
        ];

        foreach ($ministries as $ministry) {
            Ministry::firstOrCreate(
                ['code' => $ministry['code']],
                $ministry + ['is_active' => true]
            );
        }
    }
}
