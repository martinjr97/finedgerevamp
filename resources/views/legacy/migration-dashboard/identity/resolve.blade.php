@extends('legacy.migration-dashboard.layout')

@section('dashboard-content')
    @include('partials.admin.page-header', [
        'title' => 'Resolve Duplicate NRC',
        'description' => 'Choose how to handle legacy users sharing NRC '.$group['nrc_masked'].'.',
        'buttons' => [[
            'text' => 'Back to Identity',
            'href' => route('legacy.migration-dashboard.identity.index'),
            'action' => 'secondary',
        ]],
    ])

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-1 space-y-4">
            <div class="rounded-2xl border bg-white p-5 shadow-sm">
                <h2 class="font-semibold text-primary mb-3">Legacy users in this group</h2>
                <ul class="space-y-3">
                    @foreach ($group['members'] as $member)
                        <li class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm">
                            <p class="font-semibold text-slate-900">User #{{ $member['legacy_user_id'] }} — {{ $member['name'] }}</p>
                            <p class="text-xs text-slate-500 mt-1">{{ $member['loan_count'] }} loans · status {{ $member['status_code'] ?? '—' }}</p>
                            @if ($member['email'])
                                <p class="text-xs text-slate-500 truncate">{{ $member['email'] }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        <div class="lg:col-span-2">
            <form method="POST" action="{{ route('legacy.migration-dashboard.identity.store') }}" class="rounded-2xl border bg-white p-6 shadow-sm space-y-5">
                @csrf
                <input type="hidden" name="nrc_key" value="{{ $group['nrc_key'] }}">

                <div>
                    <label class="text-sm font-medium text-slate-700">Resolution <span class="text-rose-500">*</span></label>
                    <div class="mt-3 space-y-2">
                        @foreach ($classifications as $value => $label)
                            <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-3 cursor-pointer hover:bg-slate-50">
                                <input type="radio" name="classification" value="{{ $value }}" class="mt-1" @checked(old('classification', \App\Models\MigrationIdentityResolution::CLASS_SAME_PERSON_MAP_ONE) === $value) required>
                                <span class="text-sm text-slate-700">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('classification')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700">Primary legacy user (for merge)</label>
                    <select name="primary_legacy_user_id" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Auto-select (user with loans / first in group)</option>
                        @foreach ($group['members'] as $member)
                            <option value="{{ $member['legacy_user_id'] }}" @selected(old('primary_legacy_user_id') == $member['legacy_user_id'])>
                                #{{ $member['legacy_user_id'] }} — {{ $member['name'] }} ({{ $member['loan_count'] }} loans)
                            </option>
                        @endforeach
                    </select>
                    @error('primary_legacy_user_id')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700">Existing target customer ID (optional)</label>
                    <input type="number" name="target_customer_id" value="{{ old('target_customer_id') }}" min="1"
                           placeholder="Leave blank to create on customer migration"
                           class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                    <p class="mt-1 text-xs text-slate-500">Only for merge when a revamp customer already exists (e.g. pilot data).</p>
                    @error('target_customer_id')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700">Notes</label>
                    <textarea name="reason" rows="3" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">{{ old('reason') }}</textarea>
                    @error('reason')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div class="flex items-center gap-3">
                    <button type="submit" class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:opacity-90 transition">
                        Save resolution
                    </button>
                    <a href="{{ route('legacy.migration-dashboard.identity.index') }}" class="text-sm text-slate-600 hover:text-primary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
