<?php

namespace Database\Seeders;

use App\Models\Province;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProvinceSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $provinces = [
            ['name' => 'Central', 'code' => 'CENT', 'country' => 'Zambia'],
            ['name' => 'Copperbelt', 'code' => 'COPP', 'country' => 'Zambia'],
            ['name' => 'Eastern', 'code' => 'EAST', 'country' => 'Zambia'],
            ['name' => 'Luapula', 'code' => 'LUAP', 'country' => 'Zambia'],
            ['name' => 'Lusaka', 'code' => 'LUSA', 'country' => 'Zambia'],
            ['name' => 'Muchinga', 'code' => 'MUCH', 'country' => 'Zambia'],
            ['name' => 'Northern', 'code' => 'NORT', 'country' => 'Zambia'],
            ['name' => 'North-Western', 'code' => 'NW', 'country' => 'Zambia'],
            ['name' => 'Southern', 'code' => 'SOUT', 'country' => 'Zambia'],
            ['name' => 'Western', 'code' => 'WEST', 'country' => 'Zambia'],
        ];

        foreach ($provinces as $province) {
            Province::firstOrCreate(
                ['code' => $province['code']],
                $province + ['is_active' => true]
            );
        }
    }
}
