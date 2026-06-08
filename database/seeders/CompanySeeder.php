<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {

        $domain = config('app.email_domain');
        $companyName = config('app.system_name');

        Company::firstOrCreate(
            ['slug' => 'main-operator'],
            [
                'name' => $companyName,
                'code' => 'FINE-001',
                'type' => 'operator',
                'registration_number' => 'FINE-001',
                'contact_email' => "operations@{$domain}",
                'contact_phone' => '+260700000000',
                'address_line1' => 'Plot 16520/M/R Off Kasama Road, Lilayi,',
                'city' => 'Lusaka',
                'country' => 'Zambia',
                'is_primary' => true,
                'status' => 'active',
            ]
        );
    }
}
