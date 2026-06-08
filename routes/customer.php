<?php

use App\Http\Controllers\Customer\AccountDeletionController;
use App\Http\Controllers\Customer\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Customer\Auth\PinController;
use App\Http\Controllers\Customer\Auth\PasswordResetController;
use App\Http\Controllers\Customer\Auth\SecurityQuestionController;
use App\Http\Controllers\Customer\CollateralLoanController;
use App\Http\Controllers\Customer\DashboardController;
use App\Http\Controllers\Customer\LoanController;
use App\Http\Controllers\Customer\NotificationController;
use App\Http\Controllers\Customer\PaymentDetailsController;
use App\Http\Controllers\Customer\RepaymentController;
use App\Http\Controllers\Customer\StatementController;
use App\Http\Controllers\Customer\ThemeController;
use App\Http\Controllers\FaqController as PublicFaqController;
use App\Http\Controllers\Customer\RegistrationRequestController;
use App\Http\Controllers\SupportController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest:customer')->group(function (): void {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::get('register-request', [RegistrationRequestController::class, 'choosePath'])->name('register-request.create');
    Route::get('register-request/government-worker', [RegistrationRequestController::class, 'createGovernmentWorker'])->name('register-request.government-worker.create');
    Route::post('register-request/government-worker', [RegistrationRequestController::class, 'storeGovernmentWorker'])->name('register-request.government-worker.store');
    Route::get('register-request/collateral-based', [RegistrationRequestController::class, 'createCollateralBased'])->name('register-request.collateral-based.create');
    Route::post('register-request/collateral-based', [RegistrationRequestController::class, 'storeCollateralBased'])->name('register-request.collateral-based.store');
    Route::post('register-request/retrieve', [RegistrationRequestController::class, 'retrieve'])->name('register-request.retrieve');
    Route::get('register-request/{reference}/government-worker', [RegistrationRequestController::class, 'editGovernmentWorker'])->name('register-request.government-worker.edit');
    Route::put('register-request/{reference}/government-worker', [RegistrationRequestController::class, 'updateGovernmentWorker'])->name('register-request.government-worker.update');
    Route::get('register-request/{reference}/collateral-based', [RegistrationRequestController::class, 'editCollateralBased'])->name('register-request.collateral-based.edit');
    Route::put('register-request/{reference}/collateral-based', [RegistrationRequestController::class, 'updateCollateralBased'])->name('register-request.collateral-based.update');
    Route::get('register-request/thank-you', [RegistrationRequestController::class, 'thankYou'])->name('register-request.thank-you');
    
    // Password Reset Routes
    Route::get('password/forgot', [PasswordResetController::class, 'showForgotPasswordForm'])->name('password.forgot');
    Route::post('password/email', [PasswordResetController::class, 'sendOtp'])->name('password.email');
    Route::get('password/verify-otp', [PasswordResetController::class, 'showVerifyOtpForm'])->name('password.verify-otp');
    Route::post('password/verify-otp', [PasswordResetController::class, 'verifyOtp'])->name('password.verify-otp.store');
    Route::get('password/security-question', [PasswordResetController::class, 'showSecurityQuestionForm'])->name('password.security-question');
    Route::post('password/security-question', [PasswordResetController::class, 'verifySecurityQuestion'])->name('password.security-question.store');
    Route::get('password/reset/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('password/reset', [PasswordResetController::class, 'reset'])->name('password.update');
});

	Route::middleware('auth:customer')->group(function (): void {
	    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
	    Route::get('statement', [StatementController::class, 'index'])->name('statement');
	    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications');
	    Route::get('profile', fn () => view('customer.profile'))->name('profile');
	    Route::get('payment-details/edit', [PaymentDetailsController::class, 'edit'])->name('payment-details.edit');
	    Route::put('payment-details', [PaymentDetailsController::class, 'update'])->name('payment-details.update');
	    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
	    Route::get('pin/change', [PinController::class, 'edit'])->name('pin.edit');
    Route::put('pin/change', [PinController::class, 'update'])->name('pin.update');
    Route::post('theme/toggle', [ThemeController::class, 'toggle'])->name('theme.toggle');
    
    // Security Questions Setup
    Route::get('security-questions/setup', [SecurityQuestionController::class, 'create'])->name('security-questions.setup');
    Route::post('security-questions', [SecurityQuestionController::class, 'store'])->name('security-questions.store');
    
    // Loan take-out flow
    Route::prefix('loans')->name('loans.')->middleware('customer.self-service-loans')->group(function (): void {
        Route::get('select-channel', [LoanController::class, 'selectChannel'])->name('select-channel');
        Route::post('select-channel', [LoanController::class, 'storeChannel'])->name('store-channel');
        Route::get('enter-amount', [LoanController::class, 'enterAmount'])->name('enter-amount');
        Route::post('enter-amount', [LoanController::class, 'storeAmount'])->name('store-amount');
        Route::get('enter-destination', [LoanController::class, 'enterDestination'])->name('enter-destination');
        Route::post('enter-destination', [LoanController::class, 'storeDestination'])->name('store-destination');
        Route::get('calculate', [LoanController::class, 'calculate'])->name('calculate');
        Route::post('calculate', [LoanController::class, 'calculate'])->name('calculate.store');
        Route::post('store', [LoanController::class, 'store'])->name('store');
    });

    // Collateral loan application flow
    Route::prefix('collateral-loans')->name('collateral-loans.')->middleware('customer.self-service-loans')->group(function (): void {
        Route::get('loan-details', [CollateralLoanController::class, 'loanDetails'])->name('loan-details');
        Route::post('calculate-repayment', [CollateralLoanController::class, 'calculateRepayment'])->name('calculate-repayment');
        Route::post('store-calculation', [CollateralLoanController::class, 'storeCalculation'])->name('store-calculation');
        Route::get('collateral', [CollateralLoanController::class, 'collateral'])->name('collateral');
        Route::post('calculate-ltv', [CollateralLoanController::class, 'calculateLTV'])->name('calculate-ltv');
        Route::post('store', [CollateralLoanController::class, 'store'])->name('store');
    });

    // Repayment flow
    Route::prefix('repayments')->name('repayments.')->group(function (): void {
        Route::get('select-type', [RepaymentController::class, 'selectType'])->name('select-type');
        Route::post('select-type', [RepaymentController::class, 'storeType'])->name('store-type');
        Route::get('select-channel', [RepaymentController::class, 'selectChannel'])->name('select-channel');
        Route::post('select-channel', [RepaymentController::class, 'storeChannel'])->name('store-channel');
        Route::get('confirm', [RepaymentController::class, 'confirm'])->name('confirm');
        Route::post('process', [RepaymentController::class, 'process'])->name('process');
        Route::get('success', [RepaymentController::class, 'success'])->name('success');
    });

    // Customer FAQ page
    Route::get('faq', [PublicFaqController::class, 'customer'])->name('faq');

    // Customer Support (linked to customer account)
    Route::get('support', [SupportController::class, 'create'])->name('support');
    Route::post('support', [SupportController::class, 'store'])->name('support.store');
    Route::get('support-tickets/{supportTicket}/attachments/{attachment}', [\App\Http\Controllers\Customer\SupportTicketController::class, 'downloadAttachment'])->name('support-tickets.attachments.download');
    Route::get('support-tickets/{supportTicket}', [\App\Http\Controllers\Customer\SupportTicketController::class, 'show'])->name('support-tickets.show');
    Route::post('support-tickets/{supportTicket}/comments', [\App\Http\Controllers\Customer\SupportTicketController::class, 'storeComment'])->name('support-tickets.comments.store');

    // Account deletion – protected by auth:customer above; only the logged-in customer can delete their own account (controller uses auth()->user())
    Route::post('account/delete', [AccountDeletionController::class, 'destroy'])->name('account.delete.store');
});
