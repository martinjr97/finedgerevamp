<?php

namespace Tests\Unit\Migration;

use App\Migration\Phases\Support\IdentityResolutionCatalog;
use App\Models\MigrationIdentityResolution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IdentityResolutionCatalogTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function nrc_key_round_trip(): void
    {
        $nrc = '730989/11/1';
        $key = IdentityResolutionCatalog::encodeNrcKey($nrc);

        $this->assertSame($nrc, IdentityResolutionCatalog::decodeNrcKey($key));
    }

    #[Test]
    public function database_resolution_overrides_bootstrap_for_same_nrc(): void
    {
        MigrationIdentityResolution::create([
            'nrc' => '999999/99/9',
            'primary_legacy_user_id' => 100,
            'alias_legacy_user_ids' => [101],
            'classification' => MigrationIdentityResolution::CLASS_KEEP_SEPARATE,
            'status' => MigrationIdentityResolution::STATUS_APPROVED,
            'reason' => 'Test keep separate',
        ]);

        $resolution = IdentityResolutionCatalog::forNrc('999999/99/9');

        $this->assertNotNull($resolution);
        $this->assertSame(MigrationIdentityResolution::CLASS_KEEP_SEPARATE, $resolution['classification']);
        $this->assertTrue(IdentityResolutionCatalog::shouldMigrateAsSeparateIdentity(100));
        $this->assertFalse(IdentityResolutionCatalog::isAlias(101));
    }

    #[Test]
    public function bootstrap_merge_resolution_is_available(): void
    {
        $resolution = IdentityResolutionCatalog::forNrc('730989/11/1');

        $this->assertNotNull($resolution);
        $this->assertTrue(IdentityResolutionCatalog::isMergeResolution($resolution));
        $this->assertTrue(IdentityResolutionCatalog::isAlias(19));
    }
}
