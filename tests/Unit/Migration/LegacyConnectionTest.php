<?php

namespace Tests\Unit\Migration;

use App\Migration\LegacyConnection;
use RuntimeException;
use Tests\TestCase;

class LegacyConnectionTest extends TestCase
{
    public function test_assert_read_only_allows_select_statements(): void
    {
        LegacyConnection::assertReadOnly('SELECT * FROM banks WHERE id = 1');
        LegacyConnection::assertReadOnly('  show tables');
        LegacyConnection::assertReadOnly('DESCRIBE payment_wallets');
        LegacyConnection::assertReadOnly('EXPLAIN SELECT id FROM loans');

        $this->assertTrue(true);
    }

    public function test_assert_read_only_blocks_mutating_statements(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Legacy database access is read-only.');

        LegacyConnection::assertReadOnly('UPDATE banks SET name = "x" WHERE id = 1');
    }

    public function test_assert_read_only_blocks_insert_statements(): void
    {
        $this->expectException(RuntimeException::class);

        LegacyConnection::assertReadOnly('INSERT INTO banks (name) VALUES ("x")');
    }

    public function test_assert_read_only_blocks_delete_statements(): void
    {
        $this->expectException(RuntimeException::class);

        LegacyConnection::assertReadOnly('DELETE FROM banks WHERE id = 1');
    }
}
