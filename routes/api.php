<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes (no authentication required)
Route::prefix('v1')->group(function () {
    // Health check
    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
            'version' => '1.0.0',
        ]);
    });

    // Public configuration endpoints
    Route::prefix('config')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\V1\Public\ConfigController::class, 'index'])->name('api.v1.config.index');
        Route::get('/registration-status', [\App\Http\Controllers\Api\V1\Public\ConfigController::class, 'registrationStatus'])->name('api.v1.config.registration-status');
    });

    // Admin authentication routes (rate limited: 5 requests per minute)
    Route::prefix('admin/auth')->middleware('throttle:5,1')->group(function () {
        Route::post('/login', [\App\Http\Controllers\Api\V1\Auth\AdminAuthController::class, 'login'])->name('api.v1.admin.auth.login');
        Route::post('/logout', [\App\Http\Controllers\Api\V1\Auth\AdminAuthController::class, 'logout'])->middleware('auth:sanctum')->name('api.v1.admin.auth.logout');
        Route::get('/me', [\App\Http\Controllers\Api\V1\Auth\AdminAuthController::class, 'me'])->middleware('auth:sanctum')->name('api.v1.admin.auth.me');
        Route::post('/refresh', [\App\Http\Controllers\Api\V1\Auth\AdminAuthController::class, 'refresh'])->middleware('auth:sanctum')->name('api.v1.admin.auth.refresh');
    });

    // Customer authentication routes (rate limited: 5 requests per minute)
    Route::prefix('customer/auth')->middleware('throttle:5,1')->group(function () {
        Route::post('/login', [\App\Http\Controllers\Api\V1\Auth\CustomerAuthController::class, 'login'])->name('api.v1.customer.auth.login');
        Route::post('/logout', [\App\Http\Controllers\Api\V1\Auth\CustomerAuthController::class, 'logout'])->middleware('auth:sanctum')->name('api.v1.customer.auth.logout');
        Route::get('/me', [\App\Http\Controllers\Api\V1\Auth\CustomerAuthController::class, 'me'])->middleware('auth:sanctum')->name('api.v1.customer.auth.me');
        Route::post('/refresh', [\App\Http\Controllers\Api\V1\Auth\CustomerAuthController::class, 'refresh'])->middleware('auth:sanctum')->name('api.v1.customer.auth.refresh');
    });

    // Protected Admin routes (rate limited: 60 requests per minute)
    Route::prefix('admin')->middleware(['auth:sanctum', 'api.admin', 'throttle:60,1'])->group(function () {
        // Dashboard
        Route::get('/dashboard', function () {
            return response()->json(['message' => 'Admin dashboard']);
        });

        // Customers
        Route::prefix('customers')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Admin\CustomerController::class, 'index'])->name('api.v1.admin.customers.index');
            Route::get('/find', [\App\Http\Controllers\Api\V1\Admin\CustomerController::class, 'findByPhoneOrNationalId'])->name('api.v1.admin.customers.find');
            Route::post('/', [\App\Http\Controllers\Api\V1\Admin\CustomerController::class, 'store'])->name('api.v1.admin.customers.store');
            Route::get('/{customer}', [\App\Http\Controllers\Api\V1\Admin\CustomerController::class, 'show'])->name('api.v1.admin.customers.show');
            Route::put('/{customer}', [\App\Http\Controllers\Api\V1\Admin\CustomerController::class, 'update'])->name('api.v1.admin.customers.update');
            Route::get('/{customer}/loans', [\App\Http\Controllers\Api\V1\Admin\CustomerController::class, 'loans'])->name('api.v1.admin.customers.loans');
            Route::get('/{customer}/repayments', [\App\Http\Controllers\Api\V1\Admin\CustomerController::class, 'repayments'])->name('api.v1.admin.customers.repayments');
        });

        // Customer Registration Requests
        Route::prefix('customer-requests')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Admin\CustomerRegistrationRequestController::class, 'index'])->name('api.v1.admin.customer-requests.index');
            Route::get('/{registrationRequest}', [\App\Http\Controllers\Api\V1\Admin\CustomerRegistrationRequestController::class, 'show'])->name('api.v1.admin.customer-requests.show');
            Route::post('/{registrationRequest}/approve', [\App\Http\Controllers\Api\V1\Admin\CustomerRegistrationRequestController::class, 'approve'])->name('api.v1.admin.customer-requests.approve');
        });

        // Companies
        Route::prefix('companies')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Admin\CompanyController::class, 'index'])->name('api.v1.admin.companies.index');
            Route::post('/', [\App\Http\Controllers\Api\V1\Admin\CompanyController::class, 'store'])->name('api.v1.admin.companies.store');
            Route::get('/{company}', [\App\Http\Controllers\Api\V1\Admin\CompanyController::class, 'show'])->name('api.v1.admin.companies.show');
            Route::put('/{company}', [\App\Http\Controllers\Api\V1\Admin\CompanyController::class, 'update'])->name('api.v1.admin.companies.update');
        });

        // Loan Products (view only)
        Route::prefix('loan-products')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Admin\LoanProductController::class, 'index'])->name('api.v1.admin.loan-products.index');
            Route::get('/{loanProduct}', [\App\Http\Controllers\Api\V1\Admin\LoanProductController::class, 'show'])->name('api.v1.admin.loan-products.show');
        });

        // Support Tickets
        Route::prefix('support-tickets')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Admin\SupportTicketController::class, 'index'])->name('api.v1.admin.support-tickets.index');
            Route::get('/{supportTicket}', [\App\Http\Controllers\Api\V1\Admin\SupportTicketController::class, 'show'])->name('api.v1.admin.support-tickets.show');
            Route::put('/{supportTicket}', [\App\Http\Controllers\Api\V1\Admin\SupportTicketController::class, 'update'])->name('api.v1.admin.support-tickets.update');
        });

        // Loans
        Route::prefix('loans')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Admin\LoanController::class, 'index'])->name('api.v1.admin.loans.index');
            Route::get('/{loan}', [\App\Http\Controllers\Api\V1\Admin\LoanController::class, 'show'])->name('api.v1.admin.loans.show');
            Route::post('/{loan}/approve', [\App\Http\Controllers\Api\V1\Admin\LoanController::class, 'approve'])->name('api.v1.admin.loans.approve');
            Route::post('/{loan}/reject', [\App\Http\Controllers\Api\V1\Admin\LoanController::class, 'reject'])->name('api.v1.admin.loans.reject');
        });

        // Repayments (view only)
        Route::prefix('repayments')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Admin\RepaymentController::class, 'index'])->name('api.v1.admin.repayments.index');
            Route::get('/{repayment}', [\App\Http\Controllers\Api\V1\Admin\RepaymentController::class, 'show'])->name('api.v1.admin.repayments.show');
        });

        // Password Reset (no auth required for these endpoints)
    });

    // Admin Password Reset (public endpoints)
    Route::prefix('admin/password')->group(function () {
        Route::post('/send-otp', [\App\Http\Controllers\Api\V1\Admin\PasswordResetController::class, 'sendOtp'])->name('api.v1.admin.password.send-otp');
        Route::post('/verify-otp', [\App\Http\Controllers\Api\V1\Admin\PasswordResetController::class, 'verifyOtp'])->name('api.v1.admin.password.verify-otp');
        Route::post('/reset', [\App\Http\Controllers\Api\V1\Admin\PasswordResetController::class, 'reset'])->name('api.v1.admin.password.reset');
    });

    // Protected Customer routes (rate limited: 60 requests per minute)
    Route::prefix('customer')->middleware(['auth:sanctum', 'api.customer', 'throttle:60,1'])->group(function () {
        // Dashboard
        Route::get('/dashboard', [\App\Http\Controllers\Api\V1\Customer\DashboardController::class, 'index'])->name('api.v1.customer.dashboard');
        
        // Profile
        Route::get('/profile', [\App\Http\Controllers\Api\V1\Customer\ProfileController::class, 'show'])->name('api.v1.customer.profile');
        
        // Change PIN
        Route::put('/pin/change', [\App\Http\Controllers\Api\V1\Customer\PinController::class, 'update'])->name('api.v1.customer.pin.update');
        
        // FAQs
        Route::get('/faqs', [\App\Http\Controllers\Api\V1\Customer\FaqController::class, 'index'])->name('api.v1.customer.faqs.index');
    });

    // Customer Password Reset (public endpoints)
    Route::prefix('customer/password')->group(function () {
        Route::post('/send-otp', [\App\Http\Controllers\Api\V1\Customer\PasswordResetController::class, 'sendOtp'])->name('api.v1.customer.password.send-otp');
        Route::post('/verify-otp', [\App\Http\Controllers\Api\V1\Customer\PasswordResetController::class, 'verifyOtp'])->name('api.v1.customer.password.verify-otp');
        Route::post('/verify-security-question', [\App\Http\Controllers\Api\V1\Customer\PasswordResetController::class, 'verifySecurityQuestion'])->name('api.v1.customer.password.verify-security-question');
        Route::post('/reset', [\App\Http\Controllers\Api\V1\Customer\PasswordResetController::class, 'reset'])->name('api.v1.customer.password.reset');
    });
});

