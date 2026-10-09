<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
        ]);

        $middleware->alias([
            'password.changed' => \App\Http\Middleware\EnsurePasswordIsChanged::class,
            'legacy.migration.dashboard' => \App\Http\Middleware\EnsureLegacyMigrationDashboardEnabled::class,
            'migration.dashboard.permission' => \App\Http\Middleware\EnsureMigrationDashboardPermission::class,
            'migration.manage' => \App\Http\Middleware\EnsureMigrationManagePermission::class,
            'api.admin' => \App\Http\Middleware\EnsureApiAdmin::class,
            'api.customer' => \App\Http\Middleware\EnsureApiCustomer::class,
            'website.api' => \App\Http\Middleware\VerifyWebsiteApiKey::class,
            'customer.self-service-loans' => \App\Http\Middleware\EnsureCustomerCanRequestSelfServiceLoan::class,
            'customer.security-question' => \App\Http\Middleware\EnsureCustomerHasSecurityQuestion::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $timezone = 'Africa/Lusaka';

        $schedule->command('loans:accrue-interest')
            ->dailyAt('02:00')
            ->timezone($timezone)
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('loans:refresh-schedule-aging')
            ->dailyAt('01:00')
            ->timezone($timezone)
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('loans:accrue-arrears')
            ->dailyAt('01:30')
            ->timezone($timezone)
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('loans:sync-active-status')
            ->dailyAt('00:30')
            ->timezone($timezone)
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('employee-loans:refresh-aging')
            ->dailyAt('01:05')
            ->timezone($timezone)
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('employee-loans:accrue-interest')
            ->dailyAt('02:05')
            ->timezone($timezone)
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('employee-loans:accrue-arrears')
            ->dailyAt('01:35')
            ->timezone($timezone)
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('repayments:send-reminders')
            ->dailyAt('09:00')
            ->timezone($timezone)
            ->withoutOverlapping()
            ->runInBackground();

        $pollInterval = max(5, min(59, (int) config('legacy-parallel-run.poll_interval_minutes', 30)));

        if (config('legacy-parallel-run.loan_polling_enabled')) {
            $schedule->command('migration:poll-legacy-loans')
                ->cron("*/{$pollInterval} * * * *")
                ->timezone($timezone)
                ->withoutOverlapping()
                ->runInBackground();
        }

        if (config('legacy-parallel-run.repayment_polling_enabled')) {
            $schedule->command('migration:poll-legacy-repayments')
                ->cron("*/{$pollInterval} * * * *")
                ->timezone($timezone)
                ->withoutOverlapping()
                ->runInBackground();
        }

        if (config('legacy-parallel-run.expense_polling_enabled')) {
            $schedule->command('migration:poll-legacy-expenses')
                ->cron("*/{$pollInterval} * * * *")
                ->timezone($timezone)
                ->withoutOverlapping()
                ->runInBackground();
        }

        if (config('legacy-parallel-run.customer_polling_enabled')) {
            $schedule->command('migration:poll-legacy-customers')
                ->cron("*/{$pollInterval} * * * *")
                ->timezone($timezone)
                ->withoutOverlapping()
                ->runInBackground();
        }

        $schedule->call(function () {
            app(\App\PaymentPlatform\Services\GatewayPollingService::class)->dispatchDueAttempts();
        })->everyMinute()->name('gateway-poll-due-attempts')->withoutOverlapping();

        $schedule->call(function () {
            \Illuminate\Support\Facades\Cache::put('operations:scheduler:last_heartbeat', now(), now()->addMinutes(5));
        })->everyMinute()->name('operations-scheduler-heartbeat');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Handle API exceptions with JSON responses
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated. Please login.',
                ], 401);
            }
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resource not found.',
                ], 404);
            }
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied.',
                ], 403);
            }
        });
    })->create();
