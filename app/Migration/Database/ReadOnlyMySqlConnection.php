<?php

namespace App\Migration\Database;

use App\Migration\LegacyConnection;
use Illuminate\Database\MySqlConnection;
use RuntimeException;

/**
 * MySQL connection that rejects all write operations against the legacy database.
 */
class ReadOnlyMySqlConnection extends MySqlConnection
{
    public function insert($query, $bindings = [], $sequence = null)
    {
        $this->guardWrite('insert');
    }

    public function update($query, $bindings = [])
    {
        $this->guardWrite('update');
    }

    public function delete($query, $bindings = [])
    {
        $this->guardWrite('delete');
    }

    public function statement($query, $bindings = [])
    {
        if (is_string($query)) {
            LegacyConnection::assertReadOnly($query);
        }

        return parent::statement($query, $bindings);
    }

    public function affectingStatement($query, $bindings = [])
    {
        if (is_string($query)) {
            LegacyConnection::assertReadOnly($query);
        }

        return parent::affectingStatement($query, $bindings);
    }

    public function unprepared($query)
    {
        if (is_string($query)) {
            LegacyConnection::assertReadOnly($query);
        }

        return parent::unprepared($query);
    }

    protected function run($query, $bindings, \Closure $callback)
    {
        if (is_string($query)) {
            LegacyConnection::assertReadOnly($query);
        }

        return parent::run($query, $bindings, $callback);
    }

    private function guardWrite(string $method): never
    {
        throw new RuntimeException("Legacy database access is read-only (attempted {$method}).");
    }
}
