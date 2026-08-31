<?php

namespace Tests\Unit\Migration;

use App\Migration\Phases\FinancialDataMigrator;
use ReflectionMethod;
use Tests\TestCase;

class FinancialDataMigratorTest extends TestCase
{
    public function test_prepare_imported_text_preserves_short_descriptions(): void
    {
        [$description, $notes] = $this->prepareImportedText('Office supplies', 'Imported from legacy expense #1');

        $this->assertSame('Office supplies', $description);
        $this->assertSame('Imported from legacy expense #1', $notes);
    }

    public function test_prepare_imported_text_truncates_long_descriptions_and_keeps_full_text_in_notes(): void
    {
        $long = str_repeat('A', 600);

        [$description, $notes] = $this->prepareImportedText($long, 'Imported from legacy expense #612');

        $this->assertSame(500, mb_strlen($description));
        $this->assertStringContainsString($long, $notes);
        $this->assertStringStartsWith('Imported from legacy expense #612', $notes);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function prepareImportedText(string $description, string $notesPrefix): array
    {
        $method = new ReflectionMethod(FinancialDataMigrator::class, 'prepareImportedText');

        return $method->invoke(new FinancialDataMigrator(
            app(\App\Migration\Phases\MigrationRunManager::class),
            app(\App\Migration\Phases\MigrationEntityMapRepository::class),
        ), $description, $notesPrefix);
    }
}
