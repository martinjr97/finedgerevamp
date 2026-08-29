<?php

use App\Http\Controllers\Admin\LegacyMigrationDashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:admin', 'password.changed', 'legacy.migration.dashboard', 'migration.dashboard.permission'])
    ->prefix('legacy/migration-dashboard')
    ->name('legacy.migration-dashboard.')
    ->group(function (): void {
        Route::get('/', [LegacyMigrationDashboardController::class, 'index'])->name('index');
        Route::get('/runs', [LegacyMigrationDashboardController::class, 'runs'])->name('runs.index');
        Route::get('/runs/{run}', [LegacyMigrationDashboardController::class, 'showRun'])->name('runs.show');
        Route::get('/customers', [LegacyMigrationDashboardController::class, 'customers'])->name('customers.index');
        Route::get('/customers/{legacyUserId}', [LegacyMigrationDashboardController::class, 'showCustomer'])->name('customers.show');
        Route::get('/companies', [LegacyMigrationDashboardController::class, 'companies'])->name('companies.index');
        Route::get('/marketeers', [LegacyMigrationDashboardController::class, 'marketeers'])->name('marketeers.index');
        Route::get('/identity', [LegacyMigrationDashboardController::class, 'identity'])->name('identity.index');
        Route::get('/loans', [LegacyMigrationDashboardController::class, 'loans'])->name('loans.index');
        Route::get('/loans/{legacyLoanId}', [LegacyMigrationDashboardController::class, 'showLoan'])->name('loans.show');
        Route::get('/repayments', [LegacyMigrationDashboardController::class, 'repayments'])->name('repayments.index');
        Route::get('/repayments/{legacyRepaymentId}', [LegacyMigrationDashboardController::class, 'showRepayment'])->name('repayments.show');
        Route::get('/exceptions', [LegacyMigrationDashboardController::class, 'exceptions'])->name('exceptions.index');
        Route::get('/reconciliation', [LegacyMigrationDashboardController::class, 'reconciliation'])->name('reconciliation.index');
        Route::get('/mappings', [LegacyMigrationDashboardController::class, 'mappings'])->name('mappings.index');
    });
