<?php

namespace Tests\Unit\Migration;

use App\Migration\Phases\Support\ReferenceMatcher;
use App\Models\Bank;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TreasuryAccountMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_treasury_bank_attributes_use_zero_balances(): void
    {
        $matcher = new ReferenceMatcher;

        $attributes = $matcher->treasuryBankAttributes([
            'id' => 1,
            'name' => 'Zambia Industrial Commercial Bank',
            'code' => 'ZICB',
            'is_active' => true,
        ]);

        $this->assertSame(0.0, (float) $attributes['opening_balance']);
        $this->assertSame(0.0, (float) $attributes['current_balance']);
        $this->assertSame('LEG-BANK-1', $attributes['account_number']);
        $this->assertStringContainsString('Enter opening balance separately', $attributes['notes']);
    }

    public function test_treasury_wallet_attributes_use_zero_balances(): void
    {
        $matcher = new ReferenceMatcher;

        $attributes = $matcher->treasuryWalletAttributes([
            'id' => 3,
            'name' => 'MTN Money',
            'code' => 'MTN',
            'is_active' => true,
        ]);

        $this->assertSame(0.0, (float) $attributes['opening_balance']);
        $this->assertSame(0.0, (float) $attributes['current_balance']);
        $this->assertSame('mtn', $attributes['provider']);
        $this->assertStringContainsString('Enter opening balance separately', $attributes['notes']);
    }

    public function test_promoted_treasury_bank_record_keeps_zero_balances(): void
    {
        $matcher = new ReferenceMatcher;

        $bank = Bank::create($matcher->treasuryBankAttributes([
            'id' => 99,
            'name' => 'Legacy Treasury Bank',
            'code' => 'LTB',
        ]));

        $bank->refresh();

        $this->assertSame(0.0, (float) $bank->opening_balance);
        $this->assertSame(0.0, (float) $bank->current_balance);
    }

    public function test_promoted_treasury_wallet_record_keeps_zero_balances(): void
    {
        $matcher = new ReferenceMatcher;

        $wallet = Wallet::create($matcher->treasuryWalletAttributes([
            'id' => 7,
            'name' => 'Kazang',
            'code' => 'KAZANG',
        ]));

        $wallet->refresh();

        $this->assertSame(0.0, (float) $wallet->opening_balance);
        $this->assertSame(0.0, (float) $wallet->current_balance);
        $this->assertSame('other', $wallet->provider);
    }

    public function test_match_treasury_wallet_record_by_placeholder_number(): void
    {
        $matcher = new ReferenceMatcher;

        $wallet = Wallet::create($matcher->treasuryWalletAttributes([
            'id' => 5,
            'name' => 'Kazang Float',
            'code' => 'KAZANG',
        ]));

        $matched = $matcher->matchTreasuryWalletRecord(['id' => 5, 'name' => 'Kazang']);

        $this->assertSame($wallet->id, $matched?->id);
    }
}
