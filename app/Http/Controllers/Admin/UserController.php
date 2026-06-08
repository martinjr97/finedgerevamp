<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\Admin;
use App\Models\Company;
use App\Models\Branch;
use App\Notifications\AdminUserInvited;
use App\Notifications\AdminPasswordResetLink;
use App\Support\PermissionMatrix;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(): View
    {
        $admin = auth('admin')->user();
        $companyFilterId = $admin->getCompanyFilterId();

        $query = Admin::with(['company', 'branch', 'roles']);

        // Filter by company if not primary company admin
        if ($companyFilterId !== null) {
            $query->where('company_id', $companyFilterId);
        }

        $users = $query->latest()->get();

        return view('admin.users.index', compact('users'));
    }

    public function create(): View
    {
        $admin = auth('admin')->user();
        $companyFilterId = $admin->getCompanyFilterId();

        $companiesQuery = Company::orderBy('name');
        $branchesQuery = Branch::orderBy('name');

        // Filter by company if not primary company admin
        if ($companyFilterId !== null) {
            $companiesQuery->where('id', $companyFilterId);
            $branchesQuery->where('company_id', $companyFilterId);
        }

        return view('admin.users.create', [
            'companies' => $companiesQuery->get(),
            'branches' => $branchesQuery->get(),
            'roles' => $this->getAvailableRoles(),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        try {
            $data = $request->validated();
            
            $currentUser = auth('admin')->user();
            $companyFilterId = $currentUser->getCompanyFilterId();

            // Prevent non-super-admins from assigning super-admin role
            if (!$currentUser || !$currentUser->hasRole(PermissionMatrix::SUPER_ADMIN_ROLE)) {
                $roleNames = $data['role_names'] ?? [];
                if (in_array(PermissionMatrix::SUPER_ADMIN_ROLE, $roleNames)) {
                    return redirect()
                        ->route('admin.users.create')
                        ->withInput()
                        ->with('error', 'You do not have permission to assign the super-admin role.');
                }
            }

            // Ensure non-primary admins can only create admins for their company
            if ($companyFilterId !== null) {
                if ($data['company_id'] != $companyFilterId) {
                    return redirect()
                        ->route('admin.users.create')
                        ->withInput()
                        ->with('error', 'You can only create admins for your company.');
                }
            }
            
            $temporaryPassword = Str::password(12);
            $requiresApproval = config('approval.admins.create', false);

            $user = Admin::create([
                'company_id' => $data['company_id'],
                'branch_id' => $data['branch_id'],
                'employee_number' => $data['employee_number'] ?? null,
                'nrc' => $data['nrc'] ?? null,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($temporaryPassword),
                'is_active' => $requiresApproval ? false : $request->boolean('is_active', true),
                'is_relationship_manager' => $request->boolean('is_relationship_manager', false),
                'approval_status' => $requiresApproval ? 'pending' : 'approved',
                'must_change_password' => true,
            ]);

            $user->syncRoles($data['role_names'] ?? []);

            if (! $requiresApproval) {
                $user->notify(new AdminUserInvited(
                    $temporaryPassword,
                    $user,
                    route('admin.login')
                ));

                return redirect()
                    ->route('admin.users.index')
                    ->with('status', 'Admin created successfully. An invitation email has been sent.');
            }

            return redirect()
                ->route('admin.users.index')
                ->with('status', 'Admin created and is pending approval. An email will be sent once approved.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.users.create')
                ->withInput()
                ->with('error', 'Failed to create admin: '.$e->getMessage());
        }
    }

    public function show(Admin $user): View
    {
        abort_unless(auth('admin')->user()?->can('admins.view'), 403);

        $admin = auth('admin')->user();
        $companyFilterId = $admin->getCompanyFilterId();

        // Ensure non-primary admins can only view admins from their company
        if ($companyFilterId !== null && $user->company_id != $companyFilterId) {
            abort(403, 'You can only view admins from your company.');
        }

        $user->load(['company', 'roles']);

        return view('admin.users.show', compact('user'));
    }

    public function loginAudit(Admin $user, Request $request): View
    {
        abort_unless(auth('admin')->user()?->can('admins.view'), 403);

        $admin = auth('admin')->user();
        $companyFilterId = $admin->getCompanyFilterId();

        // Ensure non-primary admins can only view admins from their company
        if ($companyFilterId !== null && $user->company_id != $companyFilterId) {
            abort(403, 'You can only view admins from your company.');
        }

        $query = \App\Models\AdminLoginAudit::where('admin_id', $user->id)
            ->orWhere('email', $user->email)
            ->orderBy('attempted_at', 'desc');

        // Filter by status if provided
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // Filter by date range if provided
        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('attempted_at', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('attempted_at', '<=', $request->date_to);
        }

        $loginAudits = $query->paginate(50)->withQueryString();

        return view('admin.users.login-audit', compact('user', 'loginAudits'));
    }

    public function edit(Admin $user): View
    {
        $admin = auth('admin')->user();
        $companyFilterId = $admin->getCompanyFilterId();

        // Ensure non-primary admins can only edit admins from their company
        if ($companyFilterId !== null && $user->company_id != $companyFilterId) {
            abort(403, 'You can only edit admins from your company.');
        }

        $companiesQuery = Company::orderBy('name');
        $branchesQuery = Branch::orderBy('name');

        // Filter by company if not primary company admin
        if ($companyFilterId !== null) {
            $companiesQuery->where('id', $companyFilterId);
            $branchesQuery->where('company_id', $companyFilterId);
        }

        return view('admin.users.edit', [
            'user' => $user->load('roles'),
            'companies' => $companiesQuery->get(),
            'branches' => $branchesQuery->get(),
            'roles' => $this->getAvailableRoles(),
        ]);
    }

    public function update(UserRequest $request, Admin $user): RedirectResponse
    {
        try {
            $data = $request->validated();
            
            $currentUser = auth('admin')->user();
            $companyFilterId = $currentUser->getCompanyFilterId();

            // Ensure non-primary admins can only update admins from their company
            if ($companyFilterId !== null && $user->company_id != $companyFilterId) {
                abort(403, 'You can only update admins from your company.');
            }

            // Prevent non-super-admins from assigning super-admin role
            if (!$currentUser || !$currentUser->hasRole(PermissionMatrix::SUPER_ADMIN_ROLE)) {
                $roleNames = $data['role_names'] ?? [];
                if (in_array(PermissionMatrix::SUPER_ADMIN_ROLE, $roleNames)) {
                    return redirect()
                        ->route('admin.users.edit', $user)
                        ->withInput()
                        ->with('error', 'You do not have permission to assign the super-admin role.');
                }
            }

            // Ensure non-primary admins can only change company to their own company
            if ($companyFilterId !== null) {
                if ($data['company_id'] != $companyFilterId) {
                    return redirect()
                        ->route('admin.users.edit', $user)
                        ->withInput()
                        ->with('error', 'You can only assign admins to your company.');
                }
            }

            $user->update([
                'company_id' => $data['company_id'],
                'branch_id' => $data['branch_id'],
                'employee_number' => $data['employee_number'] ?? null,
                'nrc' => $data['nrc'] ?? null,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'is_active' => $request->boolean('is_active'),
                'is_relationship_manager' => $request->boolean('is_relationship_manager'),
            ]);

            if (! empty($data['password'])) {
                $user->update(['password' => Hash::make($data['password'])]);
                $user->forceFill(['must_change_password' => false])->save();
            }

            $user->syncRoles($data['role_names'] ?? []);

            return redirect()
                ->route('admin.users.edit', $user)
                ->with('status', 'Admin updated successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.users.edit', $user)
                ->withInput()
                ->with('error', 'Failed to update admin: '.$e->getMessage());
        }
    }

    public function destroy(Admin $user): RedirectResponse
    {
        try {
            abort_if($user->hasRole(PermissionMatrix::SUPER_ADMIN_ROLE), 403, 'Cannot delete super admin user.');

            $admin = auth('admin')->user();
            $companyFilterId = $admin->getCompanyFilterId();

            // Ensure non-primary admins can only delete admins from their company
            if ($companyFilterId !== null && $user->company_id != $companyFilterId) {
                abort(403, 'You can only delete admins from your company.');
            }

            $user->delete();

            return redirect()
                ->route('admin.users.index')
                ->with('status', 'Admin deleted successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.users.index')
                ->with('error', 'Failed to delete admin: '.$e->getMessage());
        }
    }

    /**
     * Get available roles based on current user's permissions.
     * Super-admin role is only available to users who have the super-admin role themselves.
     */
    private function getAvailableRoles()
    {
        $currentUser = auth('admin')->user();
        $roles = Role::orderBy('name')->get();
        
        // If current user is not a super-admin, exclude super-admin role
        if (!$currentUser || !$currentUser->hasRole(PermissionMatrix::SUPER_ADMIN_ROLE)) {
            $roles = $roles->reject(function ($role) {
                return $role->name === PermissionMatrix::SUPER_ADMIN_ROLE;
            });
        }
        
        return $roles;
    }

    /**
     * Send password reset link to admin user.
     */
    public function sendPasswordResetLink(Admin $user): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('admins.update'), 403);

        $admin = auth('admin')->user();
        $companyFilterId = $admin->getCompanyFilterId();

        // Ensure non-primary admins can only reset passwords for admins from their company
        if ($companyFilterId !== null && $user->company_id != $companyFilterId) {
            abort(403, 'You can only reset passwords for admins from your company.');
        }

        try {
            // Generate reset token
            $token = Str::random(64);
            
            // Store reset token in cache for 1 hour
            $resetTokenKey = 'admin_password_reset_token_' . $user->id;
            Cache::put($resetTokenKey, [
                'token' => $token,
                'email' => $user->email,
                'expires_at' => now()->addHour(),
            ], now()->addHour());

            // Send password reset link to email
            $resetUrl = route('admin.password.reset', [
                'token' => $token,
                'email' => $user->email,
            ]);
            
            // Log communication BEFORE sending (so it's logged immediately, not in queue)
            try {
                $subject = 'Password Reset Instructions - ' . config('app.name');
                $messageContent = "Hello {$user->full_name}!\n\n";
                $messageContent .= "An administrator has initiated a password reset for your account.\n";
                $messageContent .= "Click the link below to reset your password. This link will expire in 1 hour.\n\n";
                $messageContent .= "Reset Password: {$resetUrl}\n\n";
                $messageContent .= "If you did not request a password reset, please ignore this email or contact support if you have concerns.\n\n";
                $messageContent .= "Security Note: This link can only be used once.";

                \App\Support\CommunicationLogger::log(
                    subject: $subject,
                    message: $messageContent,
                    type: 'email',
                    isSensitive: true, // Contains reset token/URL
                    recipient: $user,
                    createdBy: auth('admin')->user(),
                    metadata: [
                        'notification_type' => 'password_reset',
                        'is_admin_initiated' => true,
                    ]
                );
            } catch (\Exception $e) {
                \Log::error('Failed to log password reset communication', [
                    'error' => $e->getMessage(),
                    'admin_id' => $user->id ?? null,
                ]);
            }
            
            $user->notify(new AdminPasswordResetLink($resetUrl, true));

            return redirect()
                ->route('admin.users.show', $user)
                ->with('status', 'Password reset link has been sent to ' . $user->email . '. The link will expire in 1 hour.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.users.show', $user)
                ->with('error', 'Failed to send password reset link: ' . $e->getMessage());
        }
    }

    public function export()
    {
        $admin = auth('admin')->user();
        $companyFilterId = $admin->getCompanyFilterId();

        $filename = 'admins-export-'.now()->format('Ymd_His').'.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($companyFilterId): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['First Name', 'Last Name', 'Email', 'Employee Number', 'NRC Number', 'Company', 'Roles', 'Status']);

            $query = Admin::with(['company', 'roles']);
            
            // Filter by company if not primary company admin
            if ($companyFilterId !== null) {
                $query->where('company_id', $companyFilterId);
            }

            $query->chunk(200, function ($chunk) use ($handle): void {
                foreach ($chunk as $admin) {
                    fputcsv($handle, [
                        $admin->first_name,
                        $admin->last_name,
                        $admin->email,
                        $admin->employee_number ?? '—',
                        $admin->nrc ?? '—',
                        $admin->company->name ?? '—',
                        $admin->roles->pluck('name')->join(', '),
                        $admin->is_active ? 'Active' : 'Inactive',
                    ]);
                }
            });

            fclose($handle);
        };

        return response()->streamDownload($callback, $filename, $headers);
    }
}
