<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        $this->assertUsesDedicatedTestDatabaseFromEnv();
        parent::setUp();
        $this->assertUsesDedicatedTestDatabaseFromConfig();
    }

    protected function assertUsesDedicatedTestDatabaseFromEnv(): void
    {
        if (! $this->isTestingEnvironment()) {
            return;
        }

        $database = $this->resolveEnvDatabaseName();

        if ($database === '' || $this->isProtectedDatabase($database)) {
            throw new RuntimeException(
                'Refusing to run tests against protected database ['.$database.']. '
                .'Use the dedicated test database finedgerevamp_testing (see .env.testing and phpunit.xml).'
            );
        }
    }

    protected function assertUsesDedicatedTestDatabaseFromConfig(): void
    {
        if (! app()->environment('testing')) {
            return;
        }

        $connection = (string) config('database.default', 'mysql');
        $database = (string) config("database.connections.{$connection}.database");

        if ($database === '' || $this->isProtectedDatabase($database)) {
            throw new RuntimeException(
                'Tests are configured to use protected database ['.$database.']. '
                .'Clear cached config (`php artisan config:clear`) and ensure DB_DATABASE=finedgerevamp_testing for tests.'
            );
        }
    }

    protected function isTestingEnvironment(): bool
    {
        return (getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? '')) === 'testing';
    }

    protected function resolveEnvDatabaseName(): string
    {
        return (string) (getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? ''));
    }

    protected function isProtectedDatabase(string $database): bool
    {
        return in_array($database, $this->protectedDatabaseNames(), true);
    }

    /**
     * @return list<string>
     */
    protected function protectedDatabaseNames(): array
    {
        $configured = getenv('TESTING_PROTECTED_DATABASES') ?: ($_ENV['TESTING_PROTECTED_DATABASES'] ?? 'finedgerevamp');

        return array_values(array_filter(array_map('trim', explode(',', $configured))));
    }
}
