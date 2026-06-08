<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\ZambianPhoneRules;
use App\Models\Admin;
use App\Models\Company;
use App\Models\Customer;
use App\Models\FinancialTransaction;
use App\Models\GroupLoanApplication;
use App\Models\Loan;
use App\Models\Repayment;
use App\Notifications\AdminUserInvited;
use App\Notifications\CustomerApprovalNotification;
use App\Services\LoanPaymentDetailsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ApprovalController extends Controller
{
    public function index(): View
    {
        abort_unless(auth('admin')->user()?->can('approvals.view'), 403);

        $admin = auth('admin')->user();
        $companyFilterId = $admin->getCompanyFilterId();

        $pendingAdminsQuery = Admin::where('approval_status', 'pending')
            ->with(['company', 'approver'])
            ->latest();

        $pendingCompaniesQuery = Company::where('approval_status', 'pending')
            ->with(['approver'])
            ->latest();

        $pendingCustomersQuery = Customer::where('approval_status', 'pending')
            ->with(['company', 'loanProduct', 'approver', 'latestKycDocument'])
            ->latest();

        $pendingLoansQuery = Loan::where('status', 'pending_approval')
            ->with(['customer', 'loanProduct', 'customerGroup', 'channel'])
            ->latest();

        $pendingGroupLoanApplicationsQuery = GroupLoanApplication::where('status', 'pending_approval')
            ->with(['loanProduct', 'customerGroup', 'creator'])
            ->withCount('members')
            ->latest();

        $pendingTransfersQuery = FinancialTransaction::where('type', 'transfer')
            ->where('approval_status', 'pending')
            ->with(['sourceBank', 'sourceWallet', 'destinationBank', 'destinationWallet', 'creator'])
            ->latest();

        $pendingRepaymentsQuery = Repayment::where('status', 'pending')
            ->with(['customer', 'channel', 'loanRepayments.loan'])
            ->latest();

        if ($companyFilterId !== null) {
            $pendingAdminsQuery->where('company_id', $companyFilterId);
            $pendingCompaniesQuery->where('id', $companyFilterId);
            $pendingCustomersQuery->where(function ($query) use ($companyFilterId) {
                $query->where('company_id', $companyFilterId)
                    ->orWhere(function ($subQuery) use ($companyFilterId) {
                        $subQuery->whereNull('company_id')
                            ->whereHas('loanProduct', function ($loanProductQuery) use ($companyFilterId) {
                                $loanProductQuery->where('company_id', $companyFilterId);
                            });
                    });
            });
            $pendingLoansQuery->whereHas('customer', function ($q) use ($companyFilterId) {
                $q->where('company_id', $companyFilterId);
            });
            $pendingGroupLoanApplicationsQuery->whereHas('loanProduct', function ($q) use ($companyFilterId) {
                $q->where('company_id', $companyFilterId);
            });
            $pendingTransfersQuery->whereHas('creator', function ($q) use ($companyFilterId) {
                $q->where('company_id', $companyFilterId);
            });
            $pendingRepaymentsQuery->whereHas('customer', function ($q) use ($companyFilterId) {
                $q->where('company_id', $companyFilterId);
            });
        }

        $pendingAdmins = $pendingAdminsQuery->get();
        $pendingCompanies = $pendingCompaniesQuery->get();
        $pendingCustomers = $pendingCustomersQuery->get();
        $pendingLoans = $pendingLoansQuery->get();
        $pendingGroupLoanApplications = $pendingGroupLoanApplicationsQuery->get();
        $pendingTransfers = $pendingTransfersQuery->get();
        $pendingRepayments = $pendingRepaymentsQuery->get();

        return view('admin.approvals.index', compact(
            'pendingAdmins',
            'pendingCompanies',
            'pendingCustomers',
            'pendingLoans',
            'pendingGroupLoanApplications',
            'pendingTransfers',
            'pendingRepayments'
        ));
    }

    public function approveAdmin(Admin $admin, Request $request): RedirectResponse
    {
        try {
            abort_unless(auth('admin')->user()?->can('approvals.approve'), 403);
            abort_unless($admin->approval_status === 'pending', 400, 'This admin is not pending approval.');

            // Generate temporary password if not already set
            if (! $admin->password || Hash::needsRehash($admin->password)) {
                $temporaryPassword = Str::password(12);
                $admin->password = Hash::make($temporaryPassword);
                $admin->must_change_password = true;
            } else {
                $temporaryPassword = null;
            }

            $admin->update([
                'approval_status' => 'approved',
                'approved_by' => auth('admin')->id(),
                'approved_at' => now(),
                'approval_notes' => $request->input('notes'),
                'is_active' => true,
            ]);

            // Send invitation email if password was generated
            if ($temporaryPassword) {
                $admin->notify(new AdminUserInvited(
                    $temporaryPassword,
                    $admin,
                    route('admin.login')
                ));
            }

            return redirect()
                ->route('admin.approvals.index')
                ->with('status', 'Admin approved successfully. An invitation email has been sent.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.approvals.index')
                ->with('error', 'Failed to approve admin: '.$e->getMessage());
        }
    }

    public function rejectAdmin(Admin $admin, Request $request): RedirectResponse
    {
        try {
            abort_unless(auth('admin')->user()?->can('approvals.reject'), 403);
            abort_unless($admin->approval_status === 'pending', 400, 'This admin is not pending approval.');

            $admin->update([
                'approval_status' => 'rejected',
                'approved_by' => auth('admin')->id(),
                'approved_at' => now(),
                'approval_notes' => $request->input('notes'),
                'is_active' => false,
            ]);

            return redirect()
                ->route('admin.approvals.index')
                ->with('status', 'Admin rejected successfully. You can edit or delete it from the users list.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.approvals.index')
                ->with('error', 'Failed to reject admin: '.$e->getMessage());
        }
    }

    public function approveCompany(Company $company, Request $request): RedirectResponse
    {
        try {
            abort_unless(auth('admin')->user()?->can('approvals.approve'), 403);
            abort_unless($company->approval_status === 'pending', 400, 'This company is not pending approval.');

            $company->update([
                'approval_status' => 'approved',
                'approved_by' => auth('admin')->id(),
                'approved_at' => now(),
                'approval_notes' => $request->input('notes'),
                'status' => 'active',
            ]);

            return redirect()
                ->route('admin.approvals.index')
                ->with('status', 'Company approved successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.approvals.index')
                ->with('error', 'Failed to approve company: '.$e->getMessage());
        }
    }

    public function rejectCompany(Company $company, Request $request): RedirectResponse
    {
        try {
            abort_unless(auth('admin')->user()?->can('approvals.reject'), 403);
            abort_unless($company->approval_status === 'pending', 400, 'This company is not pending approval.');

            $company->update([
                'approval_status' => 'rejected',
                'approved_by' => auth('admin')->id(),
                'approved_at' => now(),
                'approval_notes' => $request->input('notes'),
            ]);

            return redirect()
                ->route('admin.approvals.index')
                ->with('status', 'Company rejected successfully. You can edit or delete it from the companies list.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.approvals.index')
                ->with('error', 'Failed to reject company: '.$e->getMessage());
        }
    }

    public function approveCustomer(Customer $customer, Request $request): RedirectResponse
    {
        try {
            abort_unless(auth('admin')->user()?->can('approvals.approve'), 403);
            abort_unless($customer->approval_status === 'pending', 400, 'This customer is not pending approval.');

            $latestKyc = $customer->latestKycDocument;
            if (! $latestKyc && $customer->kyc_status !== 'verified') {
                return redirect()
                    ->route('admin.approvals.index')
                    ->with('error', 'Customer cannot be approved before KYC documents are uploaded.');
            }

            $updateData = [
                'approval_status' => 'approved',
                'approved_by' => auth('admin')->id(),
                'approved_at' => now(),
                'approval_notes' => $request->input('notes'),
            ];

            // Check if customer has KYC documents and verify them
            $kycVerified = false;
            
            if ($latestKyc) {
                // Verify the KYC document if not already verified
                if ($latestKyc->status !== 'verified') {
                    $latestKyc->update([
                        'status' => 'verified',
                        'verified_by' => auth('admin')->id(),
                        'verified_at' => now(),
                    ]);
                }
                $kycVerified = true;
                $updateData['kyc_status'] = 'verified';
                $updateData['status'] = 'active';
            } elseif ($customer->kyc_status === 'verified') {
                // KYC was already verified
                $kycVerified = true;
                $updateData['status'] = 'active';
            }

            // Generate a fresh PIN at approval and require reset on first login.
            $pin = str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
            $updateData['password'] = Hash::make($pin);
            $updateData['must_change_pin'] = true;

            $customer->update($updateData);

            // For development/debugging only: log generated PIN for quick sign-in testing.
            if (!app()->environment('production')) {
                Log::info('Customer approval PIN generated', [
                    'customer_id' => $customer->id,
                    'customer_email' => $customer->email,
                    'customer_phone' => $customer->phone,
                    'customer_name' => $customer->full_name,
                    'new_pin' => $pin,
                    'approved_by' => auth('admin')->user()?->email,
                    'approved_at' => now()->toDateTimeString(),
                    'account_active' => $kycVerified,
                ]);
            }

            $emailSent = true;
            try {
                $customer->notify(new CustomerApprovalNotification(
                    pin: $pin,
                    phone: $customer->phone ?? $customer->email,
                    isActive: $kycVerified
                ));
            } catch (\Throwable $notificationError) {
                $emailSent = false;
                Log::error('Failed to send customer approval notification', [
                    'customer_id' => $customer->id,
                    'error' => $notificationError->getMessage(),
                ]);
            }

            $message = 'Customer approved successfully.';
            if ($kycVerified) {
                $message .= ' KYC documents have been verified and customer account is now active.';
            } else {
                $message .= ' Customer will be activated after KYC documents are uploaded and verified.';
            }
            $message .= $emailSent
                ? ' Approval details and a temporary PIN have been sent via email.'
                : ' Customer approved, but approval email could not be sent.';

            return redirect()
                ->route('admin.approvals.index')
                ->with('status', $message);
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.approvals.index')
                ->with('error', 'Failed to approve customer: '.$e->getMessage());
        }
    }

    public function rejectCustomer(Customer $customer, Request $request): RedirectResponse
    {
        try {
            abort_unless(auth('admin')->user()?->can('approvals.reject'), 403);
            abort_unless($customer->approval_status === 'pending', 400, 'This customer is not pending approval.');

            $customer->update([
                'approval_status' => 'rejected',
                'approved_by' => auth('admin')->id(),
                'approved_at' => now(),
                'approval_notes' => $request->input('notes'),
            ]);

            return redirect()
                ->route('admin.approvals.index')
                ->with('status', 'Customer rejected successfully. You can edit or delete it from the customers list.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.approvals.index')
                ->with('error', 'Failed to reject customer: '.$e->getMessage());
        }
    }

    public function approveLoan(Loan $loan, Request $request): RedirectResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin?->can('loans.approve'), 403);
        abort_unless($loan->status === 'pending_approval', 400, 'This loan is not pending approval.');

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
            'redirect_to_loan' => ['nullable', 'boolean'],
            'form_action' => ['nullable', 'string', 'max:50'],
        ]);

        $redirectToLoan = $request->boolean('redirect_to_loan');

        try {
            $loan->update([
                'status' => 'approved',
                'approved_by' => $admin?->id,
                'approved_at' => now(),
                'approval_notes' => $validated['notes'] ?? null,
            ]);
        } catch (\Throwable $e) {
            if ($redirectToLoan) {
                return redirect()
                    ->route('admin.loans.show', $loan)
                    ->with('error', 'Failed to approve loan: '.$e->getMessage())
                    ->withInput();
            }

            return redirect()
                ->route('admin.approvals.index')
                ->with('error', 'Failed to approve loan: '.$e->getMessage())
                ->withInput();
        }

        if ($redirectToLoan) {
            return redirect()
                ->route('admin.loans.show', $loan)
                ->with('status', 'Loan approved successfully.');
        }

        return redirect()
            ->route('admin.approvals.index')
            ->with('status', 'Loan approved successfully.');
    }

    public function rejectLoan(Loan $loan, Request $request): RedirectResponse
    {
        try {
            abort_unless(auth('admin')->user()?->can('loans.reject'), 403);
            abort_unless($loan->status === 'pending_approval', 400, 'This loan is not pending approval.');

            $loan->update([
                'status' => 'cancelled',
                'approved_by' => auth('admin')->id(),
                'approved_at' => now(),
                'approval_notes' => $request->input('notes'),
            ]);

            return redirect()
                ->route('admin.approvals.index')
                ->with('status', 'Loan rejected successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.approvals.index')
                ->with('error', 'Failed to reject loan: '.$e->getMessage());
        }
    }
}
