<?php

namespace Database\Seeders;

use App\Models\Sector;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SectorSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $sectors = [
            ['name' => 'Financial Services', 'code' => 'FIN', 'description' => 'Banking, insurance, and financial institutions'],
            ['name' => 'Technology', 'code' => 'TECH', 'description' => 'Software, hardware, and IT services'],
            ['name' => 'Healthcare', 'code' => 'HEALTH', 'description' => 'Hospitals, clinics, and medical services'],
            ['name' => 'Education', 'code' => 'EDU', 'description' => 'Schools, universities, and training institutions'],
            ['name' => 'Manufacturing', 'code' => 'MFG', 'description' => 'Production and manufacturing industries'],
            ['name' => 'Retail', 'code' => 'RETAIL', 'description' => 'Retail stores and commerce'],
            ['name' => 'Agriculture', 'code' => 'AGRI', 'description' => 'Farming and agricultural services'],
            ['name' => 'Real Estate', 'code' => 'REAL', 'description' => 'Property development and real estate services'],
            ['name' => 'Transportation', 'code' => 'TRANS', 'description' => 'Logistics and transportation services'],
            ['name' => 'Energy', 'code' => 'ENERGY', 'description' => 'Power and energy sector'],
            ['name' => 'Telecommunications', 'code' => 'TELCO', 'description' => 'Communication and telecom services'],
            ['name' => 'Government', 'code' => 'GOV', 'description' => 'Government institutions and agencies'],
            ['name' => 'Non-Profit', 'code' => 'NPO', 'description' => 'Non-profit organizations'],
            ['name' => 'Other', 'code' => 'OTHER', 'description' => 'Other sectors'],
        ];

        foreach ($sectors as $sector) {
            Sector::firstOrCreate(
                ['code' => $sector['code']],
                $sector + ['is_active' => true]
            );
        }
    }
}
