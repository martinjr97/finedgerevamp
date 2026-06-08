<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Channel;
use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\FinancialInstitutionBranch;
use App\Support\ChannelTypeResolver;
use Database\Seeders\ChannelSeeder;
use Database\Seeders\FinancialInstitutionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ChannelTypeAndFinancialInstitutionTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdminWithChannelPermissions(): Admin
    {
        $suffix = Str::lower(Str::random(6));

        $company = Company::create([
            'name' => 'Channel Type Co '.$suffix,
            'slug' => 'channel-type-co-'.$suffix,
            'code' => 'CTC'.$suffix,
            'type' => 'partner',
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $admin = Admin::create([
            'company_id' => $company->id,
            'first_name' => 'Channel',
            'last_name' => 'Admin',
            'email' => 'channel-admin-'.$suffix.'@example.com',
            'password' => 'password',
            'is_active' => true,
            'approval_status' => 'approved',
        ]);

        foreach (['channels.view', 'channels.create', 'channels.update'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
        }

        $admin->givePermissionTo(['channels.view', 'channels.create', 'channels.update']);

        return $admin;
    }

    public function test_channel_type_is_required_on_create(): void
    {
        $admin = $this->makeAdminWithChannelPermissions();

        $response = $this->actingAs($admin, 'admin')->post(route('admin.channels.store'), [
            'name' => 'Test Channel',
            'code' => 'TEST_'.Str::upper(Str::random(4)),
            'description' => 'Missing type',
        ]);

        $response->assertSessionHasErrors('type');
    }

    public function test_channel_type_must_be_allowed_value(): void
    {
        $admin = $this->makeAdminWithChannelPermissions();

        $response = $this->actingAs($admin, 'admin')->post(route('admin.channels.store'), [
            'name' => 'Invalid Type Channel',
            'code' => 'INV_'.Str::upper(Str::random(4)),
            'type' => 'crypto_wallet',
        ]);

        $response->assertSessionHasErrors('type');
    }

    public function test_seeded_mobile_wallet_channels_have_mobile_wallet_type(): void
    {
        $this->seed(ChannelSeeder::class);

        $mtn = Channel::where('code', 'MTN_MONEY')->first();
        $airtel = Channel::where('code', 'AIRTEL_MONEY')->first();
        $zamtel = Channel::where('code', 'ZAMTEL_MONEY')->first();

        $this->assertNotNull($mtn);
        $this->assertNotNull($airtel);
        $this->assertNotNull($zamtel);

        $this->assertTrue($mtn->isMobileWallet());
        $this->assertTrue($airtel->isMobileWallet());
        $this->assertTrue($zamtel->isMobileWallet());
        $this->assertSame(Channel::TYPE_MOBILE_WALLET, $mtn->type);
    }

    public function test_seeded_cash_channel_has_cash_type(): void
    {
        $this->seed(ChannelSeeder::class);

        $cash = Channel::where('code', 'CASH')->first();

        $this->assertNotNull($cash);
        $this->assertTrue($cash->isCash());
        $this->assertSame(Channel::TYPE_CASH, $cash->type);
        $this->assertSame('Cash', $cash->typeLabel());
    }

    public function test_bank_transfer_channel_can_be_created_as_bank(): void
    {
        $admin = $this->makeAdminWithChannelPermissions();

        $code = 'BANK_'.Str::upper(Str::random(4));

        $response = $this->actingAs($admin, 'admin')->post(route('admin.channels.store'), [
            'name' => 'Bank Transfer',
            'code' => $code,
            'type' => Channel::TYPE_BANK,
            'description' => 'Bank disbursement channel',
            'can_disburse' => true,
            'can_repay' => false,
        ]);

        $response->assertRedirect(route('admin.channels.index'));

        $channel = Channel::where('code', $code)->first();

        $this->assertNotNull($channel);
        $this->assertTrue($channel->isBank());
        $this->assertSame('Bank Transfer', $channel->typeLabel());
    }

    public function test_channel_helper_methods_return_correct_booleans(): void
    {
        $wallet = Channel::create([
            'name' => 'Wallet',
            'code' => 'WLT_'.Str::upper(Str::random(4)),
            'type' => Channel::TYPE_MOBILE_WALLET,
        ]);

        $bank = Channel::create([
            'name' => 'Zanaco Bank',
            'code' => 'ZAN_'.Str::upper(Str::random(4)),
            'type' => Channel::TYPE_BANK,
        ]);

        $cash = Channel::create([
            'name' => 'Cash Payout',
            'code' => 'CSH_'.Str::upper(Str::random(4)),
            'type' => Channel::TYPE_CASH,
        ]);

        $this->assertTrue($wallet->isMobileWallet());
        $this->assertFalse($wallet->isBank());
        $this->assertFalse($wallet->isCash());

        $this->assertTrue($bank->isBank());
        $this->assertFalse($bank->isMobileWallet());

        $this->assertTrue($cash->isCash());
        $this->assertFalse($cash->isMobileWallet());
    }

    public function test_channel_type_resolver_backfills_expected_types(): void
    {
        $this->assertSame(Channel::TYPE_MOBILE_WALLET, ChannelTypeResolver::infer('MTN Money', 'MTN_MONEY'));
        $this->assertSame(Channel::TYPE_MOBILE_WALLET, ChannelTypeResolver::infer('Airtel', 'AIRTEL'));
        $this->assertSame(Channel::TYPE_MOBILE_WALLET, ChannelTypeResolver::infer('Zamtel Pay', 'ZAMTEL'));
        $this->assertSame(Channel::TYPE_CASH, ChannelTypeResolver::infer('Cash', 'CASH'));
        $this->assertSame(Channel::TYPE_BANK, ChannelTypeResolver::infer('Zanaco Transfer', 'ZANACO'));
        $this->assertSame(Channel::TYPE_BANK, ChannelTypeResolver::infer('FNB Disbursement', 'FNB_PAY'));
        $this->assertSame(Channel::TYPE_MOBILE_WALLET, ChannelTypeResolver::infer('Unknown Channel', 'OTHER'));
    }

    public function test_financial_institution_seeder_creates_starter_zambian_banks(): void
    {
        $this->seed(FinancialInstitutionSeeder::class);

        $expectedCodes = [
            'ZANACO',
            'FNB',
            'ABSA',
            'STANBIC',
            'INDO',
            'NATSAVE',
            'ACCESS',
            'ECOBANK',
            'UBA',
            'ATLAS_MARA',
        ];

        foreach ($expectedCodes as $code) {
            $this->assertDatabaseHas('financial_institutions', [
                'code' => $code,
                'is_active' => true,
            ]);
        }

        $this->assertSame(10, FinancialInstitution::count());
    }

    public function test_financial_institution_has_branches_after_seeding(): void
    {
        $this->seed(FinancialInstitutionSeeder::class);

        $zanaco = FinancialInstitution::where('code', 'ZANACO')->first();

        $this->assertNotNull($zanaco);
        $this->assertGreaterThanOrEqual(1, $zanaco->branches()->count());
        $this->assertTrue(
            $zanaco->branches()->where('name', 'Main Branch')->exists()
        );
        $this->assertGreaterThanOrEqual(1, $zanaco->activeBranches()->count());
    }

    public function test_branch_belongs_to_financial_institution(): void
    {
        $institution = FinancialInstitution::create([
            'name' => 'Test Bank',
            'code' => 'TEST_BANK',
            'is_active' => true,
        ]);

        $branch = FinancialInstitutionBranch::create([
            'financial_institution_id' => $institution->id,
            'name' => 'Lusaka Main',
            'code' => 'LUS_MAIN',
            'is_active' => true,
        ]);

        $this->assertTrue($branch->financialInstitution->is($institution));
        $this->assertTrue($institution->branches->contains($branch));
    }

    public function test_existing_channel_update_preserves_disburse_and_repay_flags(): void
    {
        $admin = $this->makeAdminWithChannelPermissions();

        $channel = Channel::create([
            'name' => 'Legacy MTN',
            'code' => 'LEG_MTN_'.Str::upper(Str::random(4)),
            'type' => Channel::TYPE_MOBILE_WALLET,
            'can_disburse' => true,
            'can_repay' => false,
            'is_repayment_integrated' => false,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'admin')->put(route('admin.channels.update', $channel), [
            'name' => $channel->name,
            'code' => $channel->code,
            'type' => Channel::TYPE_BANK,
            'description' => $channel->description,
        ]);

        $response->assertRedirect(route('admin.channels.show', $channel));

        $channel->refresh();

        $this->assertTrue($channel->isBank());
        $this->assertTrue($channel->can_disburse);
        $this->assertFalse($channel->can_repay);
        $this->assertFalse($channel->is_repayment_integrated);
        $this->assertTrue($channel->is_active);
    }
}
