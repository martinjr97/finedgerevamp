<?php

use App\Http\Controllers\Admin\Hr\ContractTypeController;
use App\Http\Controllers\Admin\Hr\DashboardController;
use App\Http\Controllers\Admin\Hr\DepartmentController;
use App\Http\Controllers\Admin\Hr\EmployeeContractController;
use App\Http\Controllers\Admin\Hr\EmployeeController;
use App\Http\Controllers\Admin\Hr\HrSettingsController;
use App\Http\Controllers\Admin\Hr\LeaveApplicationController;
use App\Http\Controllers\Admin\Hr\LeaveBalanceController;
use App\Http\Controllers\Admin\Hr\LeaveHistoryController;
use App\Http\Controllers\Admin\Hr\PositionController;
use Illuminate\Support\Facades\Route;

Route::prefix('hr')->name('hr.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('employees', EmployeeController::class);
    Route::post('employees/{employee}/compensation', [EmployeeController::class, 'storeCompensation'])->name('employees.compensation.store');
    Route::post('employees/{employee}/bank-accounts', [EmployeeController::class, 'storeBankAccount'])->name('employees.bank.store');
    Route::post('employees/{employee}/next-of-kin', [EmployeeController::class, 'storeNextOfKin'])->name('employees.next-of-kin.store');
    Route::post('employees/{employee}/dependants', [EmployeeController::class, 'storeDependant'])->name('employees.dependants.store');

    Route::resource('departments', DepartmentController::class)->except(['destroy']);
    Route::resource('positions', PositionController::class)->except(['destroy', 'show']);
    Route::resource('contract-types', ContractTypeController::class)->except(['destroy']);
    Route::resource('contracts', EmployeeContractController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('contracts/{contract}/activate', [EmployeeContractController::class, 'activate'])->name('contracts.activate');

    Route::get('leave/applications', [LeaveApplicationController::class, 'index'])->name('leave.applications.index');
    Route::get('leave/applications/create', [LeaveApplicationController::class, 'create'])->name('leave.applications.create');
    Route::get('leave/applications/balance-preview', [LeaveApplicationController::class, 'balancePreview'])->name('leave.applications.balance-preview');
    Route::post('leave/applications', [LeaveApplicationController::class, 'store'])->name('leave.applications.store');
    Route::get('leave/applications/{leaveApplication}', [LeaveApplicationController::class, 'show'])->name('leave.applications.show');
    Route::post('leave/applications/{leaveApplication}/approve', [LeaveApplicationController::class, 'approve'])->name('leave.applications.approve');
    Route::post('leave/applications/{leaveApplication}/reject', [LeaveApplicationController::class, 'reject'])->name('leave.applications.reject');
    Route::post('leave/applications/{leaveApplication}/cancel', [LeaveApplicationController::class, 'cancel'])->name('leave.applications.cancel');

    Route::get('leave/history', [LeaveHistoryController::class, 'index'])->name('leave.history.index');
    Route::get('leave/balances', [LeaveBalanceController::class, 'index'])->name('leave.balances.index');
    Route::post('leave/balances/adjust', [LeaveBalanceController::class, 'adjust'])->name('leave.balances.adjust');

    Route::get('settings', [HrSettingsController::class, 'index'])->name('settings.index');
});
