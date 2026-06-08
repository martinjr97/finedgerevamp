@extends('layouts.admin')

@section('title', 'View Admin | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_auto] lg:items-center">
            <div class="space-y-1 text-left">
                <p class="text-xs uppercase tracking-[0.4em] text-muted">Team Management</p>
                <h1 class="text-3xl font-bold text-primary">{{ $user->full_name }}</h1>
            </div>
            <div class="flex items-center justify-end gap-3">
                @can('admins.view')
                <a href="{{ route('admin.users.login-audit', $user) }}" class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-purple-500 to-purple-600 px-4 py-3 text-base font-semibold text-white shadow-lg shadow-purple-500/30 hover:from-purple-600 hover:to-purple-700 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Login Audit
                </a>
                @endcan
                @can('admins.update')
                <form action="{{ route('admin.users.send-password-reset', $user) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-blue-500 to-blue-600 px-4 py-3 text-base font-semibold text-white shadow-lg shadow-blue-500/30 hover:from-blue-600 hover:to-blue-700 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                        </svg>
                        Send Password Reset Link
                    </button>
                </form>
                @endcan
                @can('admins.update')
                <a href="{{ route('admin.users.edit', $user) }}" class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-blue-500 to-blue-600 px-4 py-3 text-base font-semibold text-white shadow-lg shadow-blue-500/30 hover:from-blue-600 hover:to-blue-700 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Edit
                </a>
                @endcan
                <a href="{{ route('admin.users.index') }}" class="inline-flex items-center gap-2 rounded-2xl border border-muted bg-white px-4 py-3 text-base font-medium text-primary hover:bg-gray-100 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back 
                </a>
            </div>
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <!-- Personal Information Card -->
            <div class="rounded-3xl border border-white/10 bg-gradient-to-br from-white/5 to-white/[0.02] p-6 shadow-lg hover:shadow-xl transition">
                <div class="mb-4 flex items-center gap-3">
                    <div class="rounded-xl bg-cyan-500/20 p-2">
                        <svg class="h-5 w-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </div>
                    <h2 class="text-lg font-semibold text-primary">Personal Information</h2>
                </div>
                <div class="space-y-4">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-muted mb-1">Full Name</p>
                        <p class="text-lg font-semibold text-primary">{{ $user->full_name }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-muted mb-1">Email Address</p>
                        <p class="text-base font-medium text-primary break-all">{{ $user->email }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-muted mb-1">Phone Number</p>
                        <p class="text-base font-medium text-primary">{{ $user->phone ?? '—' }}</p>
                    </div>
                    @if ($user->employee_number)
                        <div>
                            <p class="text-xs uppercase tracking-wide text-muted mb-1">Employee Number</p>
                            <p class="text-base font-medium text-primary">{{ $user->employee_number }}</p>
                        </div>
                    @endif
                    @if ($user->nrc)
                        <div>
                            <p class="text-xs uppercase tracking-wide text-muted mb-1">NRC Number</p>
                            <p class="text-base font-medium text-primary">{{ $user->nrc }}</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Company, Branch & Roles Card -->
            <div class="rounded-3xl border border-white/10 bg-gradient-to-br from-white/5 to-white/[0.02] p-6 shadow-lg hover:shadow-xl transition">
                <div class="mb-4 flex items-center gap-3">
                    <div class="rounded-xl bg-blue-500/20 p-2">
                        <svg class="h-5 w-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                    </div>
                    <h2 class="text-lg font-semibold text-primary">Company, Branch & Roles</h2>
                </div>
                <div class="space-y-4">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-muted mb-1">Company</p>
                        <p class="text-base font-semibold text-primary">{{ $user->company->name ?? '—' }}</p>
                        @if ($user->company)
                            <p class="mt-1 text-sm text-muted">{{ $user->company->code }} • {{ ucfirst($user->company->type) }}</p>
                        @endif
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-muted mb-1">Branch</p>
                        <p class="text-base font-semibold text-primary">{{ $user->branch->name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-muted mb-2">Assigned Roles</p>
                        <div class="flex flex-wrap gap-2">
                            @forelse ($user->roles as $role)
                                <span class="rounded-full bg-cyan-500/15 px-3 py-1 text-sm font-medium text-primary border border-cyan-500/40">
                                    {{ $role->name }}
                                </span>
                            @empty
                                <span class="text-base text-muted">No roles assigned</span>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <!-- Account Status Card -->
            <div class="rounded-3xl border border-white/10 bg-gradient-to-br from-white/5 to-white/[0.02] p-6 shadow-lg hover:shadow-xl transition">
                <div class="mb-4 flex items-center gap-3">
                    <div class="rounded-xl bg-emerald-500/20 p-2">
                        <svg class="h-5 w-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <h2 class="text-lg font-semibold text-primary">Account Status</h2>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-muted mb-1">Status</p>
                        <span class="inline-block rounded-full px-3 py-1 text-sm font-medium text-primary {{ $user->is_active ? 'bg-emerald-500/20 border border-emerald-600/40' : 'bg-rose-500/20 border border-rose-600/40' }}">
                            {{ $user->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-muted mb-1">Email Verified</p>
                        <p class="text-base font-medium text-primary">
                            {{ $user->email_verified_at ? $user->email_verified_at->format('d M Y') : 'Not verified' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-muted mb-1">Password Status</p>
                        <span class="inline-block rounded-full px-3 py-1 text-sm font-medium text-primary {{ $user->must_change_password ? 'bg-amber-500/20 border border-amber-600/40' : 'bg-emerald-500/20 border border-emerald-600/40' }}">
                            {{ $user->must_change_password ? 'Must Change' : 'Password Set' }}
                        </span>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-muted mb-1">Account Created</p>
                        <p class="text-base font-medium text-primary">{{ $user->created_at->format('d M Y') }}</p>
                    </div>
                </div>
            </div>

            <!-- Login Activity Card -->
            <div class="rounded-3xl border border-white/10 bg-gradient-to-br from-white/5 to-white/[0.02] p-6 shadow-lg hover:shadow-xl transition">
                <div class="mb-4 flex items-center gap-3">
                    <div class="rounded-xl bg-purple-500/20 p-2">
                        <svg class="h-5 w-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <h2 class="text-lg font-semibold text-primary">Login Activity</h2>
                </div>
                <div class="space-y-4">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-muted mb-1">Last Login</p>
                        @if ($user->last_login_at)
                            <p class="text-base font-semibold text-primary">{{ $user->last_login_at->format('d M Y H:i') }}</p>
                            <p class="mt-1 text-sm text-muted font-mono">IP: {{ $user->last_login_ip ?? 'N/A' }}</p>
                        @else
                            <p class="text-base text-muted italic">Never logged in</p>
                        @endif
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-muted mb-1">Last Updated</p>
                        <p class="text-base font-medium text-primary">{{ $user->updated_at->format('d M Y H:i') }}</p>
                    </div>
                </div>
            </div>
        </div>

        @if ($user->deleted_at)
            <div class="rounded-3xl border border-rose-500/30 bg-gradient-to-br from-rose-500/10 to-rose-500/5 p-6 shadow-lg">
                <div class="flex items-center gap-3">
                    <div class="rounded-xl bg-rose-500/20 p-2">
                        <svg class="h-5 w-5 text-rose-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-rose-300">Account Deleted</h2>
                        <p class="text-sm text-rose-200 mt-1">This account was deleted on {{ $user->deleted_at->format('d M Y H:i') }}</p>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection

