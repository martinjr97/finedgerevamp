<?php

use App\Http\Controllers\Admin\ApprovalController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Admin\Auth\PasswordController;
use App\Http\Controllers\Admin\Auth\PasswordResetController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\BankController;
use App\Http\Controllers\Admin\BulkRepaymentController;
use App\Http\Controllers\Admin\PmecSubmissionController;
use App\Http\Controllers\Admin\ChannelController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\CreditorController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\CustomerBulkUploadController;
use App\Http\Controllers\Admin\CustomerGroupController;
use App\Http\Controllers\Admin\CustomerRegistrationRequestController as AdminCustomerRegistrationRequestController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\FinancialInstitutionController;
use App\Http\Controllers\Admin\FinancialStatementController;
use App\Http\Controllers\Admin\FinancialTransactionController;
use App\Http\Controllers\Admin\KycController;
use App\Http\Controllers\Admin\LoanProductController;
use App\Http\Controllers\Admin\LoanRateTypeController;
use App\Http\Controllers\Admin\MarketController;
use App\Http\Controllers\Admin\MinistryController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\ProvinceController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\GeneralSettingController;
use App\Http\Controllers\Admin\GroupLoanApplicationController;
use App\Http\Controllers\Admin\CustomerRegistrationSettingController;
use App\Http\Controllers\Admin\RepaymentReminderSettingController;
use App\Http\Controllers\Admin\CreditScoreSettingController;
use App\Http\Controllers\Admin\FraudProtectionController;
use App\Http\Controllers\Admin\RepaymentController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SectorController;
use App\Http\Controllers\Admin\SecurityQuestionController;
use App\Http\Controllers\Admin\SupportTicketController;
use App\Http\Controllers\Admin\SystemBackupController;
use App\Http\Controllers\Admin\TransferController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WalletController;
use App\Http\Controllers\Admin\WalletProviderController;
use Illuminate\Support\Facades\Route;

// Redirect /admin to dashboard if authenticated, otherwise to login
Route::get('/', function () {
    if (auth('admin')->check()) {
        return redirect()->route('admin.dashboard');
    }
    return redirect()->route('admin.login');
})->name('index');

Route::middleware('guest:admin')->group(function (): void {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    
    // Password Reset Routes
    Route::get('password/forgot', [PasswordResetController::class, 'showForgotPasswordForm'])->name('password.forgot');
    Route::post('password/email', [PasswordResetController::class, 'sendResetLink'])->name('password.email');
    Route::get('password/verify-otp', [PasswordResetController::class, 'showVerifyOtpForm'])->name('password.verify-otp');
    Route::post('password/verify-otp', [PasswordResetController::class, 'verifyOtp'])->name('password.verify-otp.store');
    Route::get('password/reset/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('password/reset', [PasswordResetController::class, 'reset'])->name('password.update');
});

Route::middleware('auth:admin')->group(function (): void {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('password/change', [PasswordController::class, 'edit'])->name('password.edit');
    Route::post('password/change', [PasswordController::class, 'update'])->name('password.change');

    Route::middleware('password.changed')->group(function (): void {
        Route::get('dashboard', [\App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');
        Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
        Route::patch('profile/name', [ProfileController::class, 'updateName'])->name('profile.update-name');
        Route::post('profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.update-avatar');
        Route::patch('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.update-password');
        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('users/export', [UserController::class, 'export'])->name('users.export');
        Route::post('users/{user}/send-password-reset', [UserController::class, 'sendPasswordResetLink'])->name('users.send-password-reset');
        Route::get('users/{user}/login-audit', [UserController::class, 'loginAudit'])->name('users.login-audit');
        Route::resource('users', UserController::class);
        Route::get('companies/export', [CompanyController::class, 'export'])->name('companies.export');
        Route::post('companies/{company}/loan-rate-type', [CompanyController::class, 'updateLoanRateType'])->name('companies.loan-rate-type');
        Route::get('companies/{company}/payment-due-report', [CompanyController::class, 'showPaymentDueReport'])->name('companies.payment-due-report');
        Route::post('companies/{company}/payment-due-report', [CompanyController::class, 'generatePaymentDueReport'])->name('companies.payment-due-report.generate');
        Route::get('companies/{company}/payment-due-report/export', [CompanyController::class, 'exportPaymentDueReport'])->name('companies.payment-due-report.export');
        
        // Payment Due Report (from menu)
        Route::get('payment-due-report/select', [CompanyController::class, 'selectPaymentDueReport'])->name('payment-due-report.select');
        Route::post('payment-due-report/generate', [CompanyController::class, 'generatePaymentDueReportFromSelect'])->name('payment-due-report.generate');
        
        Route::resource('companies', CompanyController::class);
        Route::get('customers/select-product-type', [CustomerController::class, 'selectProductType'])->name('customers.select-product-type');
        Route::get('customers/export', [CustomerController::class, 'export'])->name('customers.export');
        Route::get('customers/{customer}/statement', [\App\Http\Controllers\Admin\CustomerStatementController::class, 'show'])->name('customers.statement');
        Route::get('customers/{customer}/loans', [CustomerController::class, 'loans'])->name('customers.loans');
        Route::get('customers/{customer}/repayments', [CustomerController::class, 'repayments'])->name('customers.repayments');
        Route::get('customers/{customer}/repayments/create', [RepaymentController::class, 'createForCustomer'])->name('customers.repayments.create');
        Route::post('customers/{customer}/repayments', [RepaymentController::class, 'storeForCustomer'])->name('customers.repayments.store');
        Route::get('customers/{customer}/login-audit', [CustomerController::class, 'loginAudit'])->name('customers.login-audit');
        Route::resource('customers', CustomerController::class);
        
        // Bulk Upload Routes
        Route::get('customers/upload/template/{product}', [CustomerBulkUploadController::class, 'downloadTemplate'])->name('customers.upload.template');
        Route::post('customers/upload', [CustomerBulkUploadController::class, 'upload'])->name('customers.upload');
        Route::get('customers/upload-batch/{batch}', [CustomerBulkUploadController::class, 'showBatch'])->name('customers.upload-batch.show');
        Route::get('customers/upload-record/{record}/edit', [CustomerBulkUploadController::class, 'editRecord'])->name('customers.upload-record.edit');
        Route::post('customers/upload-record/{record}/update', [CustomerBulkUploadController::class, 'updateRecord'])->name('customers.upload-record.update');
        Route::post('customers/upload-record/{record}/retry', [CustomerBulkUploadController::class, 'retryRecord'])->name('customers.upload-record.retry');
        Route::post('customers/upload-record/{record}/discard', [CustomerBulkUploadController::class, 'discardRecord'])->name('customers.upload-record.discard');
        Route::get('loans/export', [\App\Http\Controllers\Admin\LoanController::class, 'export'])->name('loans.export');
        Route::get('loans/todays-payments', [\App\Http\Controllers\Admin\LoanController::class, 'todaysPayments'])->name('loans.todays-payments');
        Route::get('loans/todays-payments/export', [\App\Http\Controllers\Admin\LoanController::class, 'exportTodaysPayments'])->name('loans.todays-payments.export');
        Route::get('loans/{loan}/schedule-pdf', [\App\Http\Controllers\Admin\LoanController::class, 'exportSchedulePdf'])->name('loans.schedule-pdf');
        Route::post('loans/{loan}/backfill-repayment', [\App\Http\Controllers\Admin\LoanController::class, 'backfillRepayment'])->name('loans.backfill-repayment');
        Route::post('loans/{loan}/refund', [\App\Http\Controllers\Admin\LoanController::class, 'storeRefund'])->name('loans.refund');
        Route::post('loans/{loan}/payment-details', [\App\Http\Controllers\Admin\LoanController::class, 'updatePaymentDetails'])->name('loans.payment-details');
        Route::post('loans/{loan}/disburse', [\App\Http\Controllers\Admin\LoanController::class, 'disburse'])->name('loans.disburse');
        Route::post('loans/{loan}/extend/preview', [\App\Http\Controllers\Admin\LoanController::class, 'previewExtension'])->name('loans.extend.preview');
        Route::post('loans/{loan}/extend', [\App\Http\Controllers\Admin\LoanController::class, 'extend'])->name('loans.extend');
        Route::get('loans/{loan}/settlement/quote', [\App\Http\Controllers\Admin\LoanSettlementController::class, 'quote'])->name('loans.settlement.quote');
        Route::post('loans/{loan}/settlement', [\App\Http\Controllers\Admin\LoanSettlementController::class, 'apply'])->name('loans.settlement.apply');
        Route::resource('loans', \App\Http\Controllers\Admin\LoanController::class)->only(['index', 'show']);
        
        // Bulk Repayment
        Route::get('bulk-repayments', [BulkRepaymentController::class, 'index'])->name('bulk-repayments.index');
        Route::get('bulk-repayments/sample', [BulkRepaymentController::class, 'downloadSample'])->name('bulk-repayments.sample');
        Route::post('bulk-repayments/process', [BulkRepaymentController::class, 'process'])->name('bulk-repayments.process');
        Route::get('bulk-repayments/results', [BulkRepaymentController::class, 'results'])->name('bulk-repayments.results');

        Route::prefix('pmec-submissions')->name('pmec-submissions.')->group(function (): void {
            Route::get('/', [PmecSubmissionController::class, 'index'])->name('index');
            Route::get('create', [PmecSubmissionController::class, 'create'])->name('create');
            Route::post('preview', [PmecSubmissionController::class, 'preview'])->name('preview');
            Route::post('generate', [PmecSubmissionController::class, 'generate'])->name('generate');
            Route::get('{pmecSubmission}', [PmecSubmissionController::class, 'show'])->name('show');
            Route::get('{pmecSubmission}/download', [PmecSubmissionController::class, 'download'])->name('download');
            Route::post('items/{item}/mark-failed', [PmecSubmissionController::class, 'markItemFailed'])->name('items.mark-failed');
            Route::post('items/{item}/mark-submitted', [PmecSubmissionController::class, 'markItemSubmitted'])->name('items.mark-submitted');
        });
        
        // Financial Module
        Route::resource('banks', BankController::class);
        Route::resource('wallets', WalletController::class);
        Route::resource('creditors', CreditorController::class);
        
        // Financial Transactions
        Route::get('financial-transactions', [FinancialTransactionController::class, 'index'])->name('financial-transactions.index');
        Route::get('financial-transactions/income/create', [FinancialTransactionController::class, 'createIncome'])->name('financial-transactions.income.create');
        Route::post('financial-transactions/income', [FinancialTransactionController::class, 'storeIncome'])->name('financial-transactions.income.store');
        Route::get('financial-transactions/expense/create', [FinancialTransactionController::class, 'createExpense'])->name('financial-transactions.expense.create');
        Route::post('financial-transactions/expense', [FinancialTransactionController::class, 'storeExpense'])->name('financial-transactions.expense.store');
        Route::get('financial-transactions/{financialTransaction}', [FinancialTransactionController::class, 'show'])->name('financial-transactions.show');
        Route::delete('financial-transactions/{financialTransaction}', [FinancialTransactionController::class, 'destroy'])->name('financial-transactions.destroy');
        
        // Transfers
        Route::get('transfers', [TransferController::class, 'index'])->name('transfers.index');
        Route::get('transfers/create', [TransferController::class, 'create'])->name('transfers.create');
        Route::post('transfers', [TransferController::class, 'store'])->name('transfers.store');
        Route::get('transfers/{transfer}', [TransferController::class, 'show'])->name('transfers.show');
        Route::post('transfers/{transfer}/approve', [TransferController::class, 'approve'])->name('transfers.approve');
        Route::post('transfers/{transfer}/reject', [TransferController::class, 'reject'])->name('transfers.reject');
        
        // Financial Statements
        Route::prefix('financial-statements')->name('financial-statements.')->group(function () {
            Route::get('balance-sheet', [FinancialStatementController::class, 'balanceSheet'])->name('balance-sheet');
            Route::get('cash-flow', [FinancialStatementController::class, 'cashFlow'])->name('cash-flow');
            Route::get('income-statement', [FinancialStatementController::class, 'incomeStatement'])->name('income-statement');
        });
        
        // Communications
        Route::resource('communications', \App\Http\Controllers\Admin\CommunicationController::class)->only(['index', 'create', 'store', 'show']);
        Route::post('customers/{customer}/send-message', [\App\Http\Controllers\Admin\CommunicationController::class, 'sendToCustomer'])->name('customers.send-message');
        
        // Loan Applications
        Route::prefix('loan-applications')->name('loan-applications.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\LoanApplicationController::class, 'index'])->name('index');
            Route::get('group-loans', [GroupLoanApplicationController::class, 'index'])->name('group-loans.index');
            Route::get('group-loans/{groupLoanApplication}', [GroupLoanApplicationController::class, 'show'])->name('group-loans.show');
            Route::get('group-loans/{groupLoanApplication}/documents/{groupLoanApplicationDocument}/view', [GroupLoanApplicationController::class, 'viewDocument'])->name('group-loans.documents.view');
            Route::get('group-loans/{groupLoanApplication}/documents/{groupLoanApplicationDocument}/download', [GroupLoanApplicationController::class, 'downloadDocument'])->name('group-loans.documents.download');
            Route::post('group-loans/{groupLoanApplication}/revision-draft', [GroupLoanApplicationController::class, 'createRevisionDraft'])->name('group-loans.revision-draft');
            Route::post('group-loans/{groupLoanApplication}/modification-note', [GroupLoanApplicationController::class, 'storeModificationNote'])->name('group-loans.store-modification-note');
            Route::post('group-loans/{groupLoanApplication}/approve', [GroupLoanApplicationController::class, 'approve'])->name('group-loans.approve');
            Route::post('group-loans/{groupLoanApplication}/reject', [GroupLoanApplicationController::class, 'reject'])->name('group-loans.reject');
            Route::get('group-loans/{groupLoanApplication}/disbursement', [GroupLoanApplicationController::class, 'disbursement'])->name('group-loans.disbursement');
            Route::post('group-loans/{groupLoanApplication}/auto-disburse', [GroupLoanApplicationController::class, 'autoDisburse'])->name('group-loans.auto-disburse');
            Route::get('{loanProduct}/group-loans/members', [GroupLoanApplicationController::class, 'members'])->name('group-loans.members');
            Route::post('{loanProduct}/group-loans/members', [GroupLoanApplicationController::class, 'storeMembers'])->name('group-loans.store-members');
            Route::get('{loanProduct}/group-loans/details', [GroupLoanApplicationController::class, 'details'])->name('group-loans.details');
            Route::post('{loanProduct}/group-loans/details', [GroupLoanApplicationController::class, 'storeDetails'])->name('group-loans.store-details');
            Route::get('{loanProduct}/group-loans/principals', [GroupLoanApplicationController::class, 'principals'])->name('group-loans.principals');
            Route::post('{loanProduct}/group-loans/principals', [GroupLoanApplicationController::class, 'storePrincipals'])->name('group-loans.store-principals');
            Route::get('{loanProduct}/group-loans/documents', [GroupLoanApplicationController::class, 'documents'])->name('group-loans.documents');
            Route::post('{loanProduct}/group-loans/documents', [GroupLoanApplicationController::class, 'storeDocuments'])->name('group-loans.store-documents');
            Route::get('{loanProduct}/group-loans/review', [GroupLoanApplicationController::class, 'review'])->name('group-loans.review');
            Route::post('{loanProduct}/group-loans/review/relationship-manager', [GroupLoanApplicationController::class, 'updateReviewRelationshipManager'])->name('group-loans.update-review-relationship-manager');
            Route::get('{loanProduct}/group-loans/review/print', [GroupLoanApplicationController::class, 'reviewPrint'])->name('group-loans.review-print');
            Route::post('{loanProduct}/group-loans/submit', [GroupLoanApplicationController::class, 'submit'])->name('group-loans.submit');
            Route::get('{loanProduct}/search-customer', [\App\Http\Controllers\Admin\LoanApplicationController::class, 'searchCustomer'])->name('search-customer');
            Route::get('{loanProduct}/search-customer-ajax', [\App\Http\Controllers\Admin\LoanApplicationController::class, 'searchCustomerAjax'])->name('search-customer-ajax');
            Route::get('{loanProduct}/{customer}/loan-details', [\App\Http\Controllers\Admin\LoanApplicationController::class, 'loanDetails'])->name('loan-details');
            Route::post('{loanProduct}/{customer}/calculate-repayment', [\App\Http\Controllers\Admin\LoanApplicationController::class, 'calculateRepayment'])->name('calculate-repayment');
            Route::post('{loanProduct}/{customer}/store-calculation', [\App\Http\Controllers\Admin\LoanApplicationController::class, 'storeCalculation'])->name('store-calculation');
            Route::get('{loanProduct}/{customer}/collateral', [\App\Http\Controllers\Admin\LoanApplicationController::class, 'collateral'])->name('collateral');
            Route::post('calculate-ltv', [\App\Http\Controllers\Admin\LoanApplicationController::class, 'calculateLTV'])->name('calculate-ltv');
            Route::post('{loanProduct}/{customer}/store', [\App\Http\Controllers\Admin\LoanApplicationController::class, 'store'])->name('store');
            Route::get('{loanProduct}/{customer}/review', [\App\Http\Controllers\Admin\LoanApplicationController::class, 'review'])->name('review');
            Route::post('{loanProduct}/{customer}/store-mou', [\App\Http\Controllers\Admin\LoanApplicationController::class, 'storeMou'])->name('store-mou');
            Route::get('{loanProduct}/{customer}/review-character', [\App\Http\Controllers\Admin\LoanApplicationController::class, 'reviewCharacter'])->name('review-character');
            Route::post('{loanProduct}/{customer}/store-character', [\App\Http\Controllers\Admin\LoanApplicationController::class, 'storeCharacter'])->name('store-character');
            Route::get('{loanProduct}/{customer}/review-government', [\App\Http\Controllers\Admin\LoanApplicationController::class, 'reviewGovernment'])->name('review-government');
            Route::post('{loanProduct}/{customer}/store-government', [\App\Http\Controllers\Admin\LoanApplicationController::class, 'storeGovernment'])->name('store-government');
        });
        Route::get('repayments/export', [RepaymentController::class, 'export'])->name('repayments.export');
        Route::post('repayments/{repayment}/processing-status', [RepaymentController::class, 'updateProcessingStatus'])->name('repayments.processing-status');
	        Route::post('repayments/{repayment}/approve', [RepaymentController::class, 'approve'])->name('repayments.approve');
	        Route::post('repayments/{repayment}/reject', [RepaymentController::class, 'reject'])->name('repayments.reject');
	        Route::resource('repayments', RepaymentController::class)->only(['index', 'show']);
	        Route::get('customers/{customer}/change-group', [CustomerController::class, 'changeGroup'])->name('customers.change-group');
	        Route::post('customers/{customer}/change-group', [CustomerController::class, 'updateGroup'])->name('customers.update-group');
	        Route::post('customers/{customer}/reset-pin', [CustomerController::class, 'resetPin'])->name('customers.reset-pin');
	        Route::get('customers/{customer}/payment-details/edit', [CustomerController::class, 'editPaymentDetails'])->name('customers.payment-details.edit');
	        Route::put('customers/{customer}/payment-details', [CustomerController::class, 'updatePaymentDetails'])->name('customers.payment-details.update');
	        Route::post('customers/{customer}/recalculate-credit-score', [CustomerController::class, 'recalculateCreditScore'])->name('customers.recalculate-credit-score');
	        Route::get('customers/{customer}/kyc/create', [KycController::class, 'create'])->name('customers.kyc.create');
	        Route::get('customers/{customer}/kyc', [KycController::class, 'show'])->name('customers.kyc.show');
	        Route::post('customers/{customer}/kyc', [KycController::class, 'store'])->name('customers.kyc.store');

        // Loan Calculator
        Route::prefix('loan-calculator')->name('loan-calculator.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\LoanCalculatorController::class, 'index'])->name('index');
            Route::get('groups', [\App\Http\Controllers\Admin\LoanCalculatorController::class, 'groups'])->name('groups');
            Route::post('calculate', [\App\Http\Controllers\Admin\LoanCalculatorController::class, 'calculate'])->name('calculate');
        });
        Route::resource('roles', RoleController::class);
        
        // Customer self-registration requests
        Route::prefix('customer-requests')->name('customer-requests.')->group(function (): void {
            Route::get('/', [AdminCustomerRegistrationRequestController::class, 'index'])->name('index');
            Route::get('{registrationRequest}', [AdminCustomerRegistrationRequestController::class, 'show'])->name('show');
            Route::post('{registrationRequest}/approve', [AdminCustomerRegistrationRequestController::class, 'approve'])->name('approve');
            Route::post('{registrationRequest}/reject', [AdminCustomerRegistrationRequestController::class, 'reject'])->name('reject');
            Route::post('{registrationRequest}/revert', [AdminCustomerRegistrationRequestController::class, 'revert'])->name('revert');
        });
        
        // Configuration routes
        Route::resource('loan-products', LoanProductController::class);
        Route::prefix('loan-products/{loanProduct}')->name('loan-products.')->group(function () {
            Route::resource('collateral-types', \App\Http\Controllers\Admin\CollateralTypeController::class)->except(['show']);
            Route::get('collateral-types/{collateralType}', [\App\Http\Controllers\Admin\CollateralTypeController::class, 'show'])->name('collateral-types.show');
        });
        Route::get('customer-groups', [CustomerGroupController::class, 'index'])->name('customer-groups.index');
        Route::get('customer-groups/create', [CustomerGroupController::class, 'create'])->name('customer-groups.create');
        Route::post('customer-groups', [CustomerGroupController::class, 'store'])->name('customer-groups.store');
        Route::get('customer-groups/{customerGroup}', [CustomerGroupController::class, 'show'])->name('customer-groups.show');
        Route::get('customer-groups/{customerGroup}/manage-rate-type', [CustomerGroupController::class, 'manageRateType'])->name('customer-groups.manage-rate-type');
        Route::put('customer-groups/{customerGroup}/rate-type', [CustomerGroupController::class, 'updateRateType'])->name('customer-groups.update-rate-type');
        Route::put('customer-groups/{customerGroup}/relationship-manager', [CustomerGroupController::class, 'updateRelationshipManager'])->name('customer-groups.update-relationship-manager');
        Route::put('customer-groups/{customerGroup}/financial', [CustomerGroupController::class, 'updateFinancial'])->name('customer-groups.update-financial');
        Route::resource('markets', MarketController::class);
        Route::resource('loan-rate-types', LoanRateTypeController::class);
        Route::get('loan-rate-types/{loanRateType}/rates/create', [LoanRateTypeController::class, 'createRate'])->name('loan-rate-types.rates.create');
        Route::get('loan-rate-types/{loanRateType}/rates/template', [LoanRateTypeController::class, 'downloadRatesTemplate'])->name('loan-rate-types.rates.template');
        Route::post('loan-rate-types/{loanRateType}/rates', [LoanRateTypeController::class, 'storeRate'])->name('loan-rate-types.rates.store');
        Route::post('loan-rate-types/{loanRateType}/rates/import', [LoanRateTypeController::class, 'importRates'])->name('loan-rate-types.rates.import');
        Route::get('loan-rate-types/{loanRateType}/rates/{loanRate}/edit', [LoanRateTypeController::class, 'editRate'])->name('loan-rate-types.rates.edit');
        Route::put('loan-rate-types/{loanRateType}/rates/{loanRate}', [LoanRateTypeController::class, 'updateRate'])->name('loan-rate-types.rates.update');
        Route::delete('loan-rate-types/{loanRateType}/rates/{loanRate}', [LoanRateTypeController::class, 'destroyRate'])->name('loan-rate-types.rates.destroy');
        Route::post('loan-rate-types/{loanRateType}/copy-product', [LoanRateTypeController::class, 'copyToProduct'])->name('loan-rate-types.copy-product');
        Route::resource('sectors', SectorController::class)->except(['show']);
        Route::resource('ministries', MinistryController::class)->except(['show']);
        Route::resource('provinces', ProvinceController::class)->except(['show']);
        Route::resource('branches', BranchController::class)->except(['show']);
        Route::get('settings/general', [GeneralSettingController::class, 'edit'])->name('settings.general.edit');
        Route::get('settings/customer-registration', [CustomerRegistrationSettingController::class, 'edit'])->name('settings.customer-registration.edit');
        Route::put('settings/customer-registration', [CustomerRegistrationSettingController::class, 'update'])->name('settings.customer-registration.update');
        Route::get('settings/repayment-reminders', [RepaymentReminderSettingController::class, 'edit'])->name('settings.repayment-reminders.edit');
        Route::put('settings/repayment-reminders', [RepaymentReminderSettingController::class, 'update'])->name('settings.repayment-reminders.update');
        Route::get('settings/credit-score', [CreditScoreSettingController::class, 'edit'])->name('settings.credit-score.edit');
        Route::put('settings/credit-score', [CreditScoreSettingController::class, 'update'])->name('settings.credit-score.update');
        Route::get('fraud-protection', [FraudProtectionController::class, 'index'])->name('fraud-protection.index');
        Route::get('fraud-protection/customers/{customer}', [FraudProtectionController::class, 'show'])->name('fraud-protection.show');
        Route::post('fraud-protection/customers/{customer}/clear-duplicate', [FraudProtectionController::class, 'clearDuplicate'])->name('fraud-protection.clear-duplicate');
        Route::post('fraud-protection/customers/{customer}/clear-all', [FraudProtectionController::class, 'clearAllDuplicates'])->name('fraud-protection.clear-all');
	        Route::resource('security-questions', SecurityQuestionController::class)->except(['show']);
	        Route::resource('channels', ChannelController::class);
	        Route::resource('wallet-providers', WalletProviderController::class)->except(['show']);
	        Route::get('financial-institutions/{financial_institution}/branches', [FinancialInstitutionController::class, 'branches'])->name('financial-institutions.branches');
	        Route::post('financial-institutions/{financial_institution}/branches', [FinancialInstitutionController::class, 'storeBranch'])->name('financial-institutions.branches.store');
	        Route::get('financial-institutions/{financial_institution}/branches/{branch}/edit', [FinancialInstitutionController::class, 'editBranch'])->name('financial-institutions.branches.edit');
	        Route::put('financial-institutions/{financial_institution}/branches/{branch}', [FinancialInstitutionController::class, 'updateBranch'])->name('financial-institutions.branches.update');
        Route::resource('financial-institutions', FinancialInstitutionController::class)->except(['show', 'destroy']);
        Route::resource('faqs', FaqController::class)->except(['show', 'destroy']);
        Route::get('backups', [BackupController::class, 'index'])->name('backups.index');
        Route::post('backups', [BackupController::class, 'store'])->name('backups.store');
        Route::get('backups/{filename}/download', [BackupController::class, 'download'])
            ->where('filename', '[A-Za-z0-9._-]+')
            ->name('backups.download');
        Route::delete('backups/{filename}', [BackupController::class, 'destroy'])
            ->where('filename', '[A-Za-z0-9._-]+')
            ->name('backups.destroy');
        Route::get('system/backup/uploads', [SystemBackupController::class, 'downloadUploadsBackup'])
            ->name('system.backup.uploads');

        // Support tickets
        Route::get('support-tickets', [SupportTicketController::class, 'index'])->name('support-tickets.index');
        Route::get('support-tickets/create', [SupportTicketController::class, 'create'])->name('support-tickets.create');
        Route::post('support-tickets', [SupportTicketController::class, 'store'])->name('support-tickets.store');
        Route::get('support-tickets/{supportTicket}/attachments/{attachment}', [SupportTicketController::class, 'downloadAttachment'])->name('support-tickets.attachments.download');
        Route::get('support-tickets/{supportTicket}', [SupportTicketController::class, 'show'])->name('support-tickets.show');
        Route::post('support-tickets/{supportTicket}/assign', [SupportTicketController::class, 'assign'])->name('support-tickets.assign');
        Route::post('support-tickets/{supportTicket}/comments', [SupportTicketController::class, 'storeComment'])->name('support-tickets.comments.store');
        Route::patch('support-tickets/{supportTicket}/status', [SupportTicketController::class, 'updateStatus'])->name('support-tickets.status.update');
        Route::patch('support-tickets/{supportTicket}', [SupportTicketController::class, 'update'])->name('support-tickets.update');
        
        // Reports routes
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\ReportController::class, 'index'])->name('index');
            Route::get('arrears', [\App\Http\Controllers\Admin\ReportController::class, 'arrears'])->name('arrears');
            Route::get('arrears/export', [\App\Http\Controllers\Admin\ReportController::class, 'exportArrears'])->name('arrears.export');
            Route::get('arrears/export-summary', [\App\Http\Controllers\Admin\ReportController::class, 'exportArrearsSummary'])->name('arrears.export-summary');
            Route::get('branches', [\App\Http\Controllers\Admin\ReportController::class, 'branchReport'])->name('branches');
            Route::get('disbursements', [\App\Http\Controllers\Admin\ReportController::class, 'disbursements'])->name('disbursements');
            Route::get('disbursements/export', [\App\Http\Controllers\Admin\ReportController::class, 'exportDisbursements'])->name('disbursements.export');
            Route::get('disbursements/export-summary', [\App\Http\Controllers\Admin\ReportController::class, 'exportDisbursementsSummary'])->name('disbursements.export-summary');
            Route::get('collections', [\App\Http\Controllers\Admin\ReportController::class, 'collections'])->name('collections');
            Route::get('collections/export', [\App\Http\Controllers\Admin\ReportController::class, 'exportCollections'])->name('collections.export');
            Route::get('collections/export-summary', [\App\Http\Controllers\Admin\ReportController::class, 'exportCollectionsSummary'])->name('collections.export-summary');
            Route::get('collection-split', [\App\Http\Controllers\Admin\ReportController::class, 'collectionSplit'])->name('collection-split');
            Route::get('collection-split/export', [\App\Http\Controllers\Admin\ReportController::class, 'exportCollectionSplit'])->name('collection-split.export');
            Route::get('loan-book', [\App\Http\Controllers\Admin\ReportController::class, 'loanBook'])->name('loan-book');
            Route::get('loan-book/export', [\App\Http\Controllers\Admin\ReportController::class, 'exportLoanBook'])->name('loan-book.export');
            Route::get('loan-book/export-summary', [\App\Http\Controllers\Admin\ReportController::class, 'exportLoanBookSummary'])->name('loan-book.export-summary');
            Route::get('loan-performance', [\App\Http\Controllers\Admin\ReportController::class, 'loanPerformance'])->name('loan-performance');
            Route::get('loan-performance/export', [\App\Http\Controllers\Admin\ReportController::class, 'exportLoanPerformance'])->name('loan-performance.export');
            Route::get('risk-heatmap', [\App\Http\Controllers\Admin\ReportController::class, 'riskHeatmap'])->name('risk-heatmap');
            Route::get('relationship-manager', [\App\Http\Controllers\Admin\ReportController::class, 'relationshipManagerReport'])->name('relationship-manager');
            Route::get('relationship-manager/export/{format}', [\App\Http\Controllers\Admin\ReportController::class, 'exportRelationshipManagerReport'])
                ->whereIn('format', ['excel', 'csv', 'pdf'])
                ->name('relationship-manager.export');
        });
        
        // Approval routes
        Route::get('approvals', [ApprovalController::class, 'index'])->name('approvals.index');
        Route::post('approvals/admins/{admin}/approve', [ApprovalController::class, 'approveAdmin'])->name('approvals.admins.approve');
        Route::post('approvals/admins/{admin}/reject', [ApprovalController::class, 'rejectAdmin'])->name('approvals.admins.reject');
        Route::post('approvals/companies/{company}/approve', [ApprovalController::class, 'approveCompany'])->name('approvals.companies.approve');
        Route::post('approvals/companies/{company}/reject', [ApprovalController::class, 'rejectCompany'])->name('approvals.companies.reject');
        Route::post('approvals/customers/{customer}/approve', [ApprovalController::class, 'approveCustomer'])->name('approvals.customers.approve');
        Route::post('approvals/customers/{customer}/reject', [ApprovalController::class, 'rejectCustomer'])->name('approvals.customers.reject');
        Route::post('approvals/loans/{loan}/approve', [ApprovalController::class, 'approveLoan'])->name('approvals.loans.approve');
        Route::post('approvals/loans/{loan}/reject', [ApprovalController::class, 'rejectLoan'])->name('approvals.loans.reject');
    });
});
