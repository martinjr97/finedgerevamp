@extends('layouts.admin')

@section('title', 'My Profile | '.config('app.system_name'))

@section('content')
    @php
        $avatarUrl = $admin->avatar_path ? asset('storage/'.$admin->avatar_path) : null;
        $nameModalOpen = $errors->getBag('profileName')->any();
        $avatarModalOpen = $errors->getBag('profileAvatar')->any();
        $passwordModalOpen = $errors->getBag('profilePassword')->any();
        $initials = strtoupper(substr($admin->first_name ?? 'A', 0, 1).substr($admin->last_name ?? 'D', 0, 1));
    @endphp

    <style>
        .profile-activity-grid {
            display: grid;
            gap: 2rem;
            grid-template-columns: minmax(0, 1fr);
        }

        .profile-avatar-button {
            width: 7.5rem;
            height: 7.5rem;
            border-radius: 9999px;
            overflow: hidden;
            border: 2px solid rgba(10, 37, 64, 0.2);
            background: #f8f9fa;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: zoom-in;
        }

        .profile-avatar-thumb {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-avatar-initials {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(10, 37, 64, 0.08);
            color: #0A2540;
            font-size: 2rem;
            font-weight: 700;
        }

        .profile-avatar-preview {
            max-width: min(90vw, 560px);
            max-height: 78vh;
            border-radius: 1.25rem;
            border: 2px solid rgba(10, 37, 64, 0.2);
            background: #f8f9fa;
            object-fit: contain;
        }

        @media (min-width: 1024px) {
            .profile-activity-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                align-items: start;
            }
        }
    </style>

    <div
        x-data="{
            nameModal: @js($nameModalOpen),
            avatarModal: @js($avatarModalOpen),
            passwordModal: @js($passwordModalOpen),
            imagePreviewModal: false
        }"
        class="space-y-8"
    >
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_auto] lg:items-center">
            <div class="space-y-1 text-left">
                <p class="text-xs uppercase tracking-[0.4em] text-muted">Account</p>
                <h1 class="text-3xl font-bold text-primary">My Profile</h1>
            </div>
            <div class="flex items-center justify-end gap-2">
                <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center gap-2 rounded-xl border border-muted bg-white px-3 py-2 text-sm font-medium text-primary hover:bg-gray-100 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Dashboard
                </a>
            </div>
        </div>

        <div class="rounded-3xl border border-white/10 bg-white p-6 shadow-lg">
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-[auto_1fr_auto] lg:items-center">
                <div class="flex justify-center lg:justify-start">
                    <button type="button" class="profile-avatar-button shadow" @click="imagePreviewModal = true" aria-label="Preview profile image">
                        @if ($avatarUrl)
                            <img src="{{ $avatarUrl }}" alt="Profile photo" class="profile-avatar-thumb">
                        @else
                            <span class="profile-avatar-initials">{{ $initials }}</span>
                        @endif
                    </button>
                </div>

                <div class="space-y-3">
                    <div>
                        <p class="text-xs uppercase tracking-[0.25em] text-muted">Full Name</p>
                        <p class="text-2xl font-semibold text-primary">{{ $admin->full_name ?: 'Admin User' }}</p>
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <p class="text-xs uppercase tracking-[0.25em] text-muted">Email Address</p>
                            <p class="text-base font-medium text-primary break-all">{{ $admin->email }}</p>
                            <p class="text-xs text-muted mt-1">Email cannot be edited from profile.</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-[0.25em] text-muted">Phone Number</p>
                            <p class="text-base font-medium text-primary">{{ $admin->phone ?: '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-[0.25em] text-muted">Company</p>
                            <p class="text-base font-medium text-primary">{{ $admin->company->name ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-[0.25em] text-muted">Branch</p>
                            <p class="text-base font-medium text-primary">{{ $admin->branch->name ?? '—' }}</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-1">
                    <button type="button" @click="nameModal = true" class="btn-primary inline-flex items-center justify-center gap-2 rounded-xl px-3 py-2 text-xs sm:text-sm font-semibold transition whitespace-nowrap">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Edit Name
                    </button>
                    <button type="button" @click="avatarModal = true" class="inline-flex items-center justify-center gap-2 rounded-xl border border-muted bg-white px-3 py-2 text-xs sm:text-sm font-semibold text-primary hover:bg-gray-100 transition whitespace-nowrap">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h4l2-2h6l2 2h4v12H3V7z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 16a3 3 0 100-6 3 3 0 000 6z"/>
                        </svg>
                        Photo
                    </button>
                    <button type="button" @click="passwordModal = true" class="inline-flex items-center justify-center gap-2 rounded-xl border border-muted bg-white px-3 py-2 text-xs sm:text-sm font-semibold text-primary hover:bg-gray-100 transition whitespace-nowrap">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2h-1V9a5 5 0 00-10 0v2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/>
                        </svg>
                        Password
                    </button>
                </div>
            </div>
        </div>

        <div class="rounded-3xl border border-white/10 bg-white p-6 shadow-lg">
            <h2 class="text-lg font-semibold text-primary mb-4">Roles</h2>
            <div class="flex flex-wrap gap-2">
                @forelse ($admin->roles as $role)
                    <span class="rounded-full border border-primary/20 bg-primary/5 px-3 py-1.5 text-sm font-medium text-primary">
                        {{ $role->name }}
                    </span>
                @empty
                    <p class="text-sm text-muted">No roles assigned.</p>
                @endforelse
            </div>
        </div>

        <div class="profile-activity-grid">
            <div class="rounded-3xl border border-white/10 bg-white p-6 shadow-lg">
                <div class="flex items-center justify-between gap-4 mb-4">
                    <h2 class="text-lg font-semibold text-primary">Recent Login Activities</h2>
                    <span class="text-xs text-muted">Last {{ $recentLoginAudits->count() }} records</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full w-full text-sm">
                        <thead>
                            <tr class="bg-slate-100 border-b border-slate-300 text-left">
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.2em] text-slate-800">Date & Time</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.2em] text-slate-800">Status</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.2em] text-slate-800">Device / Browser</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.2em] text-slate-800">IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentLoginAudits as $loginAudit)
                                <tr class="border-t border-slate-200">
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-primary">{{ $loginAudit->attempted_at?->format('d M Y H:i:s') ?? '—' }}</p>
                                        @if($loginAudit->attempted_at)
                                            <p class="text-xs text-muted">{{ $loginAudit->attempted_at->diffForHumans() }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($loginAudit->status === 'success')
                                            <span class="inline-flex rounded-full border border-emerald-500/40 bg-emerald-500/10 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                                Success
                                            </span>
                                        @else
                                            <span class="inline-flex rounded-full border border-rose-500/40 bg-rose-500/10 px-2.5 py-1 text-xs font-semibold text-rose-700">
                                                Failed
                                            </span>
                                        @endif
                                        @if($loginAudit->failure_reason)
                                            <p class="mt-1 text-xs text-muted">{{ ucfirst(str_replace('_', ' ', $loginAudit->failure_reason)) }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-primary">
                                            {{ $loginAudit->device_name ?: ucfirst($loginAudit->device_type ?? 'Unknown device') }}
                                        </p>
                                        <p class="text-xs text-muted">
                                            @if($loginAudit->browser)
                                                {{ $loginAudit->browser }}{{ $loginAudit->browser_version ? ' '.$loginAudit->browser_version : '' }}
                                            @else
                                                —
                                            @endif
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 font-mono text-xs text-primary">{{ $loginAudit->ip_address ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-center text-sm text-muted">No login activity found for this account yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-3xl border border-white/10 bg-white p-6 shadow-lg">
                <div class="flex items-center justify-between gap-4 mb-4">
                    <h2 class="text-lg font-semibold text-primary">Recent Account Audit Activities</h2>
                    <span class="text-xs text-muted">Latest {{ $recentActivityLogs->count() }} records</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full w-full text-sm">
                        <thead>
                            <tr class="bg-slate-100 border-b border-slate-300 text-left">
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.2em] text-slate-800">Date & Time</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.2em] text-slate-800">Action</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.2em] text-slate-800">Description</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.2em] text-slate-800">Performed By</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.2em] text-slate-800">IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentActivityLogs as $accountAudit)
                                <tr class="border-t border-slate-200">
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-primary">{{ $accountAudit->created_at?->format('d M Y H:i:s') ?? '—' }}</p>
                                        @if($accountAudit->created_at)
                                            <p class="text-xs text-muted">{{ $accountAudit->created_at->diffForHumans() }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded-full border border-primary/25 bg-primary/5 px-2.5 py-1 text-xs font-semibold text-primary">
                                            {{ $accountAudit->action ?: 'Activity' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-primary">
                                        {{ $accountAudit->description ?: '—' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-primary">{{ $accountAudit->performed_by_name ?: 'System' }}</p>
                                        <p class="text-xs text-muted">{{ $accountAudit->performed_by_email ?: '—' }}</p>
                                    </td>
                                    <td class="px-4 py-3 font-mono text-xs text-primary">{{ $accountAudit->ip_address ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-sm text-muted">No account audit activity found yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div x-show="nameModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4 py-6" @click.self="nameModal = false">
            <div class="w-full max-w-lg rounded-3xl border border-muted bg-white p-6 shadow-2xl">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-semibold text-primary">Edit Name</h2>
                    <button type="button" @click="nameModal = false" class="rounded-lg border border-muted px-3 py-1.5 text-sm text-primary hover:bg-gray-100 transition">Close</button>
                </div>

                <form method="POST" action="{{ route('admin.profile.update-name') }}" class="space-y-4">
                    @csrf
                    @method('PATCH')
                    <div>
                        <label for="first_name" class="text-sm font-medium text-primary">First Name</label>
                        <input id="first_name" name="first_name" type="text" required value="{{ old('first_name', $admin->first_name) }}" class="mt-2 w-full rounded-2xl border border-muted bg-white px-4 py-3 text-primary focus:border-primary focus:ring-primary/20">
                        @if ($errors->getBag('profileName')->has('first_name'))
                            <p class="mt-1 text-sm text-red-600">{{ $errors->getBag('profileName')->first('first_name') }}</p>
                        @endif
                    </div>
                    <div>
                        <label for="last_name" class="text-sm font-medium text-primary">Last Name</label>
                        <input id="last_name" name="last_name" type="text" required value="{{ old('last_name', $admin->last_name) }}" class="mt-2 w-full rounded-2xl border border-muted bg-white px-4 py-3 text-primary focus:border-primary focus:ring-primary/20">
                        @if ($errors->getBag('profileName')->has('last_name'))
                            <p class="mt-1 text-sm text-red-600">{{ $errors->getBag('profileName')->first('last_name') }}</p>
                        @endif
                    </div>
                    <div>
                        <label class="text-sm font-medium text-primary">Email Address</label>
                        <input type="text" value="{{ $admin->email }}" readonly class="mt-2 w-full rounded-2xl border border-muted bg-gray-100 px-4 py-3 text-muted cursor-not-allowed">
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-primary px-5 py-3 text-sm font-semibold text-white hover:opacity-90 transition">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div x-show="imagePreviewModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 px-4 py-6" @click.self="imagePreviewModal = false" @keydown.escape.window="imagePreviewModal = false">
            <div class="flex flex-col items-end gap-3">
                <button type="button" @click="imagePreviewModal = false" class="rounded-lg border border-white/40 bg-black/30 px-3 py-1.5 text-sm font-semibold text-white hover:bg-black/45 transition">Close</button>
                @if ($avatarUrl)
                    <img src="{{ $avatarUrl }}" alt="Profile photo preview" class="profile-avatar-preview shadow-2xl">
                @else
                    <div class="profile-avatar-preview shadow-2xl aspect-square flex items-center justify-center">
                        <span class="profile-avatar-initials" style="border-radius: 1rem;">{{ $initials }}</span>
                    </div>
                @endif
            </div>
        </div>

        <div x-show="avatarModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4 py-6" @click.self="avatarModal = false">
            <div class="w-full max-w-lg rounded-3xl border border-muted bg-white p-6 shadow-2xl">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-semibold text-primary">Update Profile Photo</h2>
                    <button type="button" @click="avatarModal = false" class="rounded-lg border border-muted px-3 py-1.5 text-sm text-primary hover:bg-gray-100 transition">Close</button>
                </div>

                <form method="POST" action="{{ route('admin.profile.update-avatar') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div>
                        <label for="avatar" class="text-sm font-medium text-primary">Choose Photo</label>
                        <input id="avatar" name="avatar" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required class="mt-2 w-full rounded-2xl border border-muted bg-white px-4 py-3 text-primary focus:border-primary focus:ring-primary/20">
                        <p class="mt-1 text-xs text-muted">Accepted: JPG, PNG, WEBP. Max 15MB.</p>
                        @if ($errors->getBag('profileAvatar')->has('avatar'))
                            <p class="mt-1 text-sm text-red-600">{{ $errors->getBag('profileAvatar')->first('avatar') }}</p>
                        @endif
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-primary px-5 py-3 text-sm font-semibold text-white hover:opacity-90 transition">
                            Upload Photo
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div x-show="passwordModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4 py-6" @click.self="passwordModal = false">
            <div class="w-full max-w-lg rounded-3xl border border-muted bg-white p-6 shadow-2xl">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-semibold text-primary">Change Password</h2>
                    <button type="button" @click="passwordModal = false" class="rounded-lg border border-muted px-3 py-1.5 text-sm text-primary hover:bg-gray-100 transition">Close</button>
                </div>

                <form method="POST" action="{{ route('admin.profile.update-password') }}" class="space-y-4">
                    @csrf
                    @method('PATCH')
                    <div>
                        <label for="current_password" class="text-sm font-medium text-primary">Current Password</label>
                        <input id="current_password" name="current_password" type="password" required class="mt-2 w-full rounded-2xl border border-muted bg-white px-4 py-3 text-primary focus:border-primary focus:ring-primary/20">
                        @if ($errors->getBag('profilePassword')->has('current_password'))
                            <p class="mt-1 text-sm text-red-600">{{ $errors->getBag('profilePassword')->first('current_password') }}</p>
                        @endif
                    </div>
                    <div>
                        <label for="password" class="text-sm font-medium text-primary">New Password</label>
                        <input id="password" name="password" type="password" required class="mt-2 w-full rounded-2xl border border-muted bg-white px-4 py-3 text-primary focus:border-primary focus:ring-primary/20">
                        @if ($errors->getBag('profilePassword')->has('password'))
                            <p class="mt-1 text-sm text-red-600">{{ $errors->getBag('profilePassword')->first('password') }}</p>
                        @endif
                    </div>
                    <div>
                        <label for="password_confirmation" class="text-sm font-medium text-primary">Confirm New Password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" required class="mt-2 w-full rounded-2xl border border-muted bg-white px-4 py-3 text-primary focus:border-primary focus:ring-primary/20">
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-primary px-5 py-3 text-sm font-semibold text-white hover:opacity-90 transition">
                            Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
