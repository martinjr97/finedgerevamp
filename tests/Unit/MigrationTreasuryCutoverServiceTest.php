<?php

namespace Tests\Unit;

use App\Migration\Dashboard\MigrationTreasuryCutoverService;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MigrationTreasuryCutoverServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_current_balance_to_opening(): void
    {
        $wallet = Wallet::create([
            'name' => 'Kazang Cutover',
            'wallet_number' => 'W-'.Str::upper(Str::random(6)),
            'opening_balance' => 50000,
            'current_balance' => 0,
            'is_active' => true,
        ]);

        $result = app(MigrationTreasuryCutoverService::class)->syncCurrentToOpening(1);

        $this->assertSame(1, $result['updated']);
        $wallet->refresh();
        $this->assertEqualsWithDelta(50000.0, (float) $wallet->current_balance, 0.01);
    }

    public function test_summary_counts_mismatched_accounts(): void
    {
        Wallet::create([
            'name' => 'Matched',
            'wallet_number' => 'W-'.Str::upper(Str::random(6)),
            'opening_balance' => 1000,
            'current_balance' => 1000,
            'is_active' => true,
        ]);

        Wallet::create([
            'name' => 'Drift',
            'wallet_number' => 'W-'.Str::upper(Str::random(6)),
            'opening_balance' => 2000,
            'current_balance' => 500,
            'is_active' => true,
        ]);

        $summary = app(MigrationTreasuryCutoverService::class)->summary();

        $this->assertSame(1, $summary['mismatched_count']);
        $this->assertCount(2, $summary['accounts']);
    }
}
