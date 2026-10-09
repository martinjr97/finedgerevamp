<?php

use App\Http\Controllers\Admin\Hr\ContractTypeController;
use App\Http\Controllers\Admin\Hr\DashboardController;
use App\Http\Controllers\Admin\Hr\DepartmentController;
use App\Http\Controllers\Admin\Hr\EmployeeContractController;
use App\Http\Controllers\Admin\Hr\EmployeeController;
use App\Http\Controllers\Admin\Hr\EmployeeLoanController;
use App\Http\Controllers\Admin\Hr\EmployeeLoanReportController;
use App\Http\Controllers\Admin\Hr\HrSettingsController;
use App\Http\Controllers\Admin\Hr\LeaveApplicationController;
use App\Http\Controllers\Admin\Hr\LeaveBalanceController;
use App\Http\Controllers\Admin\Hr\LeaveHistoryController;
use App\Http\Controllers\Admin\Hr\PositionController;
use Illuminate\Support\Facades\Route;

Route::prefix('hr')->name('hr.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('employees', EmployeeController::class);
    Route::get('employee-loans/pricing-preview', [EmployeeLoanController::class, 'pricingPreview'])->name('employee-loans.pricing-preview');
    Route::get('employee-loans/employee-payment-accounts', [EmployeeLoanController::class, 'employeePaymentAccounts'])->name('employee-loans.employee-payment-accounts');
    Route::post('employee-loans/review', [EmployeeLoanController::class, 'review'])->name('employee-loans.review.store');
    Route::get('employee-loans/review', [EmployeeLoanController::class, 'showReview'])->name('employee-loans.review');
    Route::post('employee-loans/review/cancel', [EmployeeLoanController::class, 'cancelReview'])->name('employee-loans.review.cancel');
    Route::post('employee-loans/confirm', [EmployeeLoanController::class, 'confirm'])->name('employee-loans.confirm');
    Route::get('employee-loans/reports', [EmployeeLoanReportController::class, 'index'])->name('employee-loans.reports.index');
    Route::get('employee-loans/reports/portfolio', [EmployeeLoanReportController::class, 'portfolio'])->name('employee-loans.reports.portfolio');
    Route::get('employee-loans/reports/repayments', [EmployeeLoanReportController::class, 'repayments'])->name('employee-loans.reports.repayments');
    Route::get('employee-loans/reports/arrears', [EmployeeLoanReportController::class, 'arrears'])->name('employee-loans.reports.arrears');
    Route::get('employee-loans/reports/aging', [EmployeeLoanReportController::class, 'aging'])->name('employee-loans.reports.aging');
    Route::get('employee-loans/reports/by-department', [EmployeeLoanReportController::class, 'byDepartment'])->name('employee-loans.reports.by-department');
    Route::get('employee-loans/reports/active', [EmployeeLoanReportController::class, 'active'])->name('employee-loans.reports.active');
    Route::get('employee-loans/reports/settled', [EmployeeLoanReportController::class, 'settled'])->name('employee-loans.reports.settled');
    Route::get('employee-loans/reports/by-employee', [EmployeeLoanReportController::class, 'byEmployee'])->name('employee-loans.reports.by-employee');
    Route::get('employee-loans/reports/by-rate', [EmployeeLoanReportController::class, 'byRate'])->name('employee-loans.reports.by-rate');
    Route::get('employee-loans/reports/by-term', [EmployeeLoanReportController::class, 'byTerm'])->name('employee-loans.reports.by-term');
    Route::resource('employee-loans', EmployeeLoanController::class)->only(['index', 'create', 'show', 'destroy']);
    Route::get('employee-loans/{employeeLoan}/edit', [EmployeeLoanController::class, 'edit'])->name('employee-loans.edit');
    Route::post('employee-loans/{employeeLoan}/review', [EmployeeLoanController::class, 'reviewDraft'])->name('employee-loans.review.update');
    Route::get('employee-loans/{employeeLoan}/verify', [EmployeeLoanController::class, 'verify'])->name('employee-loans.verify');
    Route::post('employee-loans/{employeeLoan}/submit', [EmployeeLoanController::class, 'submit'])->name('employee-loans.submit');
    Route::post('employee-loans/{employeeLoan}/approve', [EmployeeLoanController::class, 'approve'])->name('employee-loans.approve');
    Route::post('employee-loans/{employeeLoan}/reject', [EmployeeLoanController::class, 'reject'])->name('employee-loans.reject');
    Route::post('employee-loans/{employeeLoan}/cancel', [EmployeeLoanController::class, 'cancel'])->name('employee-loans.cancel');
    Route::post('employee-loans/{employeeLoan}/disburse', [EmployeeLoanController::class, 'disburse'])->name('employee-loans.disburse');
    Route::post('employee-loans/{employeeLoan}/disburse/gateway', [EmployeeLoanController::class, 'disburseGateway'])->name('employee-loans.disburse.gateway');
    Route::post('employee-loans/{employeeLoan}/repay', [EmployeeLoanController::class, 'repay'])->name('employee-loans.repay');
    Route::post('employee-loans/{employeeLoan}/settle', [EmployeeLoanController::class, 'settle'])->name('employee-loans.settle');
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
