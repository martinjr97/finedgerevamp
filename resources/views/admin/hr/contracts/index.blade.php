@extends('layouts.admin')

@section('title', 'Employee Contracts | HR | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Employee Contracts',
            'description' => 'Active and historical employment contracts.',
            'buttons' => [[
                'action' => 'create',
                'text' => 'New Contract',
                'href' => route('admin.hr.contracts.create'),
                'can' => auth('admin')->user()?->can('hr.contracts.manage'),
            ]],
        ])

        <form method="GET" class="grid gap-3 rounded-3xl border border-white/10 bg-white/5 p-4 md:grid-cols-2 lg:grid-cols-5 lg:items-end">
            <div>
                <label for="status" class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-400">Status</label>
                <select id="status" name="status" class="w-full rounded-xl border border-white/10 bg-white/10 px-3 py-2 text-sm text-white">
                    <option value="">All statuses</option>
                    @foreach (['draft', 'active', 'expired', 'terminated', 'renewed'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="contract_type_id" class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-400">Contract type</label>
                <select id="contract_type_id" name="contract_type_id" class="w-full rounded-xl border border-white/10 bg-white/10 px-3 py-2 text-sm text-white">
                    <option value="">All types</option>
                    @foreach ($contractTypes as $type)
                        <option value="{{ $type->id }}" @selected((string) request('contract_type_id') === (string) $type->id)>{{ $type->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-2 pt-6 lg:pt-0">
                <input
                    type="checkbox"
                    id="expiring_soon"
                    name="expiring_soon"
                    value="1"
                    class="rounded border-white/20 bg-white/10 text-cyan-500 focus:ring-cyan-500/40"
                    @checked(request()->boolean('expiring_soon'))
                >
                <label for="expiring_soon" class="text-sm text-slate-300">Expiring within 90 days</label>
            </div>
            <div class="flex flex-wrap gap-2 md:col-span-2 lg:col-span-2">
                <button type="submit" class="rounded-xl border border-cyan-500/40 bg-cyan-500/20 px-4 py-2 text-sm font-medium text-cyan-200 hover:bg-cyan-500/30">
                    Apply filters
                </button>
                @if (request()->hasAny(['status', 'contract_type_id', 'expiring_soon']))
                    <a href="{{ route('admin.hr.contracts.index') }}" class="rounded-xl border border-white/10 px-4 py-2 text-sm text-slate-300 hover:bg-white/10">
                        Clear
                    </a>
                @endif
            </div>
        </form>

        <div class="admin-data-table">
            <table class="min-w-full w-full">
                    <thead>
                        <tr>
                            <th scope="col">Contract</th>
                            <th scope="col">Employee</th>
                            <th scope="col">Type</th>
                            <th scope="col">Start</th>
                            <th scope="col">End</th>
                            <th scope="col">Status</th>
                            <th data-sortable="false" scope="col" class="admin-data-table__actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($contracts as $contract)
                            @php
                                $statusClass = match ($contract->status) {
                                    'active' => 'text-emerald-400',
                                    'draft' => 'text-amber-300',
                                    'renewed' => 'text-cyan-300',
                                    'expired' => 'text-rose-400',
                                    'terminated' => 'text-rose-400',
                                    default => 'text-slate-300',
                                };
                            @endphp
                            <tr>
                                <td class="font-medium text-white">{{ $contract->contract_number ?? '—' }}</td>
                                <td>
                                    @if ($contract->employee)
                                        <a href="{{ route('admin.hr.employees.show', $contract->employee) }}" class="font-medium text-white hover:text-cyan-200">
                                            {{ $contract->employee->full_name }}
                                        </a>
                                        <div class="text-xs text-slate-400">{{ $contract->employee->employee_number }}</div>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ $contract->contractType?->name ?? '—' }}</td>
                                <td>{{ $contract->start_date?->format('d M Y') ?? '—' }}</td>
                                <td>{{ $contract->end_date?->format('d M Y') ?? '—' }}</td>
                                <td>
                                    <span class="text-sm font-medium capitalize {{ $statusClass }}">{{ $contract->status }}</span>
                                </td>
                                <td>
                                    <div class="inline-flex items-center gap-3">
                                        <a
                                            href="{{ route('admin.hr.contracts.show', $contract) }}"
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-blue-400/50 bg-blue-500/10 px-2.5 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-500/20 transition"
                                        >
                                            View
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-8 text-center text-slate-400">No contracts match your filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
            </table>

            @if ($contracts->hasPages())
                <div class="admin-table-footer">
                    {{ $contracts->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
