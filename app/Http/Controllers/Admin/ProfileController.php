<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\DocumentUploadRules;
use App\Models\Admin;
use App\Models\AdminAccountAudit;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        $admin = $request->user('admin')->loadMissing(['company:id,name,code,type', 'branch:id,name', 'roles:id,name']);
        $recentLoginAudits = $admin->loginAudits()
            ->latest('attempted_at')
            ->limit(10)
            ->get();
        $recentActivityLogs = $this->loadRecentActivityLogs($admin);

        return view('admin.profile.show', compact('admin', 'recentLoginAudits', 'recentActivityLogs'));
    }

    public function updateName(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('profileName', [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
        ]);

        $admin = $request->user('admin');
        $admin->forceFill([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
        ])->save();

        $this->logAccountAudit(
            request: $request,
            accountAdmin: $admin,
            action: 'profile_name_updated',
            description: 'Updated profile name.',
            metadata: [
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
            ],
        );

        return redirect()
            ->route('admin.profile.show')
            ->with('status', 'Profile name updated successfully.');
    }

    public function updateAvatar(Request $request): RedirectResponse
    {
        $request->validateWithBag('profileAvatar', [
            'avatar' => DocumentUploadRules::avatarRule(),
        ]);

        $admin = $request->user('admin');
        $previousAvatar = $admin->avatar_path;
        $avatarPath = $request->file('avatar')->store('admin-avatars', 'public');

        $admin->forceFill([
            'avatar_path' => $avatarPath,
        ])->save();

        if ($previousAvatar && $previousAvatar !== $avatarPath) {
            Storage::disk('public')->delete($previousAvatar);
        }

        $this->logAccountAudit(
            request: $request,
            accountAdmin: $admin,
            action: 'profile_avatar_updated',
            description: 'Updated profile photo.',
            metadata: [
                'had_previous_avatar' => (bool) $previousAvatar,
                'avatar_path' => $avatarPath,
            ],
        );

        return redirect()
            ->route('admin.profile.show')
            ->with('status', 'Profile photo updated successfully.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('profilePassword', [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ]);

        $admin = $request->user('admin');

        if (! Hash::check($validated['current_password'], $admin->password)) {
            return back()->withErrors([
                'current_password' => 'The current password is incorrect.',
            ], 'profilePassword');
        }

        if (Hash::check($validated['password'], $admin->password)) {
            return back()->withErrors([
                'password' => 'The new password must be different from your current password.',
            ], 'profilePassword');
        }

        $admin->forceFill([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ])->save();

        $this->logAccountAudit(
            request: $request,
            accountAdmin: $admin,
            action: 'profile_password_updated',
            description: 'Updated account password.',
            metadata: null,
        );

        return redirect()
            ->route('admin.profile.show')
            ->with('status', 'Password updated successfully.');
    }

    private function logAccountAudit(
        Request $request,
        Admin $accountAdmin,
        string $action,
        string $description,
        ?array $metadata = null
    ): void {
        if (! Schema::hasTable('admin_account_audits')) {
            return;
        }

        AdminAccountAudit::create([
            'admin_id' => $accountAdmin->id,
            'actor_admin_id' => $request->user('admin')?->id,
            'action' => $action,
            'description' => $description,
            'metadata' => $metadata,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    private function loadRecentActivityLogs(Admin $admin): Collection
    {
        if (Schema::hasTable('audit_logs')) {
            $rawLogs = AuditLog::query()
                ->with('actor')
                ->where(function ($query) use ($admin): void {
                    $query->where(function ($sub) use ($admin): void {
                        $sub->where('auditable_type', Admin::class)
                            ->where('auditable_id', (string) $admin->id);
                    })->orWhere(function ($sub) use ($admin): void {
                        $sub->where('actor_type', Admin::class)
                            ->where('actor_id', (string) $admin->id);
                    });
                })
                ->latest()
                ->limit(50)
                ->get();

            return $rawLogs
                // Keep this section focused on account activity logs, not login-audit records.
                ->reject(function (AuditLog $log): bool {
                    $changed = collect($log->changed_fields ?? [])
                        ->filter(fn ($field): bool => is_string($field))
                        ->sort()
                        ->values()
                        ->all();

                    return $log->auditable_type === Admin::class
                        && $log->event === 'updated'
                        && !empty($changed)
                        && empty(array_diff($changed, ['last_login_at', 'last_login_ip']));
                })
                ->take(20)
                ->values()
                ->map(function (AuditLog $log): object {
                    $auditableLabel = class_basename((string) $log->auditable_type);
                    $eventLabel = ucfirst((string) $log->event);
                    $changedFields = collect($log->changed_fields ?? [])
                        ->filter(fn ($field): bool => is_string($field))
                        ->take(3)
                        ->map(fn (string $field): string => str_replace('_', ' ', $field))
                        ->implode(', ');

                    $description = $changedFields !== ''
                        ? "Updated fields: {$changedFields}"
                        : ($log->url ? "Route activity on {$log->url}" : 'Activity log recorded.');

                    return (object) [
                        'created_at' => $log->created_at,
                        'action' => trim("{$eventLabel} {$auditableLabel}"),
                        'description' => $description,
                        'performed_by_name' => $log->actor_name ?: 'System',
                        'performed_by_email' => data_get($log->actor, 'email', '—'),
                        'ip_address' => $log->ip_address,
                    ];
                });
        }

        if (Schema::hasTable('admin_account_audits')) {
            return $admin->accountAudits()
                ->with('actor:id,first_name,last_name,email')
                ->latest()
                ->limit(20)
                ->get()
                ->map(function (AdminAccountAudit $audit): object {
                    return (object) [
                        'created_at' => $audit->created_at,
                        'action' => ucfirst(str_replace('_', ' ', $audit->action)),
                        'description' => $audit->description ?: 'Activity log recorded.',
                        'performed_by_name' => $audit->actor?->full_name ?: 'System',
                        'performed_by_email' => $audit->actor?->email ?: '—',
                        'ip_address' => $audit->ip_address,
                    ];
                });
        }

        return collect();
    }
}
