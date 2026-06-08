<?php

namespace Database\Seeders;

use App\Models\FinancialInstitution;
use Illuminate\Database\Seeder;

class FinancialInstitutionSeeder extends Seeder
{
    public function run(): void
    {
        $institutions = [
            ['name' => 'Zanaco', 'code' => 'ZANACO'],
            ['name' => 'FNB Zambia', 'code' => 'FNB'],
            ['name' => 'ABSA Zambia', 'code' => 'ABSA'],
            ['name' => 'Stanbic Bank Zambia', 'code' => 'STANBIC'],
            ['name' => 'Indo Zambia Bank', 'code' => 'INDO'],
            ['name' => 'NATSAVE', 'code' => 'NATSAVE'],
            ['name' => 'Access Bank Zambia', 'code' => 'ACCESS'],
            ['name' => 'Ecobank Zambia', 'code' => 'ECOBANK'],
            ['name' => 'UBA Zambia', 'code' => 'UBA'],
            ['name' => 'Atlas Mara Zambia', 'code' => 'ATLAS_MARA'],
        ];

        foreach ($institutions as $institutionData) {
            $institution = FinancialInstitution::updateOrCreate(
                ['code' => $institutionData['code']],
                [
                    'name' => $institutionData['name'],
                    'is_active' => true,
                ]
            );

            $institution->branches()->updateOrCreate(
                [
                    'financial_institution_id' => $institution->id,
                    'name' => 'Main Branch',
                ],
                [
                    'code' => 'MAIN',
                    'is_active' => true,
                ]
            );
        }
    }
}
