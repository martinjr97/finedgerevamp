<?php

use App\Http\Controllers\FaqController;
use App\Http\Controllers\HelpCenterController;
use App\Http\Controllers\SupportController;
use App\Support\SampleDataGenerator;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth('admin')->check()) {
        return redirect()->route('admin.dashboard');
    }

    if (auth('customer')->check()) {
        return redirect()->route('customer.dashboard');
    }

    return redirect()->route('customer.login');
});

Route::get('login', function () {
    return redirect()->route('customer.login');
})->name('login');

Route::prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        require __DIR__.'/admin.php';
    });

Route::prefix('customer')
    ->name('customer.')
    ->group(function (): void {
        require __DIR__.'/customer.php';
    });

Route::middleware('auth:admin,customer')->group(function (): void {
    Route::get('/help', [HelpCenterController::class, 'index'])->name('help.index');
    Route::get('/help/docs/{path?}', [HelpCenterController::class, 'document'])
        ->where('path', '.*')
        ->name('help.docs');
});

// Public legal & support pages for footer links
Route::view('/privacy-policy', 'public.privacy-policy')->name('privacy');
Route::view('/terms-of-service', 'public.terms-of-service')->name('terms');
Route::get('/support', [SupportController::class, 'create'])->name('support');
Route::post('/support', [SupportController::class, 'store'])->name('support.store');
Route::get('/faq', [FaqController::class, 'public'])->name('faq');

// Account deletion (public page for Google Play: login here, then confirm deletion)
Route::get('/account/delete', [\App\Http\Controllers\Customer\AccountDeletionController::class, 'show'])
    ->name('customer.account.delete');
Route::post('/account/delete/login', [\App\Http\Controllers\Customer\AccountDeletionController::class, 'login'])
    ->name('customer.account.delete.login');
Route::post('/account/delete/logout', [\App\Http\Controllers\Customer\AccountDeletionController::class, 'logout'])
    ->name('customer.account.delete.logout');
Route::get('/account/deleted', fn () => view('customer.account-deleted'))->name('customer.account.deleted');

if (app()->environment('local')) {
    Route::get('/dev/sample-data', function () {
        abort_unless(auth('admin')->check(), 403);

        set_time_limit(0);
        ini_set('max_execution_time', 0);

        try {
            $counts = SampleDataGenerator::run();

            return response()->json([
                'status' => 'ok',
                'message' => 'Sample data generated successfully.',
                'counts' => $counts,
                'note' => 'For production, use: php artisan sample-data:generate',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate sample data: ' . $e->getMessage(),
                'note' => 'For production, use: php artisan sample-data:generate',
            ], 500);
        }
    })->name('dev.sample-data');

    Route::get('/dev/test-customer', function () {
        $customer = \App\Models\Customer::firstOrCreate(
            ['email' => 'test.customer@demo.test'],
            [
                'first_name' => 'Test',
                'last_name' => 'Customer',
                'phone' => '260970000001',
                'password' => \Illuminate\Support\Facades\Hash::make('1234'), // PIN stored in password field
                'must_change_pin' => false,
                'status' => 'active',
                'kyc_status' => 'verified',
                'email_verified_at' => now(),
                'national_id' => '123456/78/1',
                'tpin' => '123456789',
                'address_line1' => '123 Test Street',
                'city' => 'Lusaka',
                'country' => 'Zambia',
            ]
        );

        return response()->json([
            'status' => 'ok',
            'message' => 'Test customer created/updated.',
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->full_name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'pin' => '1234',
                'login_url' => route('customer.login'),
            ],
        ]);
    })->name('dev.test-customer');
}
