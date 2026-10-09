<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
	            $this->call([
	                SectorSeeder::class,
	                CompanySeeder::class,
	                CollateralCategorySeeder::class,
	                LoanProductSeeder::class,
	                EmployeeLoanProductSeeder::class,
	                GroupLoansProductSeeder::class,
	                GroupMemberTitleSeeder::class,
	                PermissionSeeder::class,
	                SuperAdminSeeder::class,
	                AuditorSeeder::class,
	                ProvinceSeeder::class,
	                DistrictSeeder::class,
	                MinistrySeeder::class,
	                LoanPurposeSeeder::class,
	                ChannelSeeder::class,
	                CashRegisterSeeder::class,
	                FinancialInstitutionSeeder::class,
	                WalletProviderSeeder::class,
	                FinancialCategorySeeder::class,
	                SecurityQuestionSeeder::class,
	                RateTableSeeder::class,
					TreasuryWalletSeeder::class,
					CGratePaymentGatewaySeeder::class,
					KazangPaymentGatewaySeeder::class,
					PaymentGatewayRouteSeeder::class,
					PaymentGatewayProductRuleSeeder::class,
					SmsTemplateSeeder::class,
	            ]);
    }
}
