<?php

namespace Tests;

use App\Models\Customer;
use App\Models\SecurityQuestion;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Attach an active security question so customer portal middleware allows access.
     */
    protected function withCustomerSecurityQuestion(Customer $customer, string $answer = 'test-answer'): Customer
    {
        $question = SecurityQuestion::query()->where('is_active', true)->first()
            ?? SecurityQuestion::query()->create([
                'question' => 'What is your favorite meal?',
                'is_active' => true,
                'sort_order' => 1,
            ]);

        $customer->forceFill([
            'security_question_id' => $question->id,
            'security_answer' => $answer,
        ])->save();

        return $customer->fresh();
    }

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
