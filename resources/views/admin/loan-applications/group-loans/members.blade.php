@extends('layouts.admin')

@section('title', 'Group Loan Members | '.config('app.system_name'))

@section('content')
    @php
        $selectedMembers = collect(old('member_ids', $wizard['member_ids'] ?? []))->map(fn ($id) => (int) $id)->all();
        $selectedTitles = old('member_titles', $wizard['member_titles'] ?? []);
    @endphp

    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Group Loan Application',
            'description' => 'Step 1: select group members and assign titles',
            'buttons' => [
                [
                    'action' => 'secondary',
                    'text' => 'Back to Products',
                    'href' => route('admin.loan-applications.index'),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>'
                ]
            ]
        ])

        <div class="flex items-center justify-center">
            <div class="flex items-center space-x-4">
                <div class="flex items-center">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-cyan-500 text-white font-semibold">1</div>
                    <span class="ml-2 text-sm font-medium text-white">Select Members</span>
                </div>
                <div class="h-1 w-16 bg-slate-600"></div>
                <div class="flex items-center">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-600 text-slate-300 font-semibold">2</div>
                    <span class="ml-2 text-sm font-medium text-slate-400">Loan Details</span>
                </div>
                <div class="h-1 w-16 bg-slate-600"></div>
                <div class="flex items-center">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-600 text-slate-300 font-semibold">3</div>
                    <span class="ml-2 text-sm font-medium text-slate-400">Principal Amounts</span>
                </div>
                <div class="h-1 w-16 bg-slate-600"></div>
                <div class="flex items-center">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-600 text-slate-300 font-semibold">4</div>
                    <span class="ml-2 text-sm font-medium text-slate-400">Documents & Review</span>
                </div>
            </div>
        </div>

        <div class="rounded-3xl border border-cyan-500/30 bg-cyan-950/30 p-4 shadow-lg">
            <p class="text-xs uppercase tracking-wide text-cyan-300 mb-1">Product</p>
            <p class="text-lg font-semibold text-white">{{ $loanProduct->name }} ({{ $loanProduct->code }})</p>
            <p class="text-xs text-cyan-100 mt-2">Select 3 to 10 members. Every selected member must have a title.</p>
        </div>

        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <form method="GET" action="{{ route('admin.loan-applications.group-loans.members', $loanProduct) }}" class="grid gap-4 md:grid-cols-4 items-end">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-300 mb-2">Filter by Group</label>
                    <select name="customer_group_id" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2.5 focus:border-cyan-400 focus:ring-cyan-400/40">
                        <option value="">Select a group</option>
                        @foreach ($groups as $group)
                            <option value="{{ $group->id }}" @selected((int) $selectedGroupId === $group->id)>{{ $group->name }} ({{ $group->code }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-cyan-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-cyan-600 transition">Load Members</button>
                </div>
                <div>
                    <a href="{{ route('admin.loan-applications.group-loans.members', $loanProduct) }}" class="inline-flex items-center justify-center rounded-2xl border border-white/20 px-4 py-2.5 text-sm font-medium text-slate-300 hover:bg-white/10 transition">Reset</a>
                </div>
            </form>
        </div>

        <form method="POST" action="{{ route('admin.loan-applications.group-loans.store-members', $loanProduct) }}" class="space-y-6">
            @csrf
            <input type="hidden" name="customer_group_id" value="{{ $selectedGroupId }}">

            <div class="rounded-3xl border border-white/10 bg-white/5 shadow-lg overflow-hidden">
                <div class="px-5 py-4 border-b border-white/10 flex items-center justify-between">
                    <h2 class="text-xl font-semibold text-white">Eligible Group Members</h2>
                    <p class="text-xs text-slate-400">Selected: <span id="selectedCount">{{ count($selectedMembers) }}</span></p>
                </div>

                @if ($selectedGroupId <= 0)
                    <div class="p-8 text-center text-slate-400">Select a group to load eligible customers.</div>
                @elseif ($customers->isEmpty())
                    <div class="p-8 text-center text-slate-400">No active approved customers were found in this group.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm text-slate-300">
                            <thead class="bg-white/[0.03] text-xs uppercase tracking-[0.2em] text-slate-400">
                                <tr>
                                    <th class="px-4 py-3 text-left">Select</th>
                                    <th class="px-4 py-3 text-left">Customer</th>
                                    <th class="px-4 py-3 text-left">Phone</th>
                                    <th class="px-4 py-3 text-left">National ID</th>
                                    <th class="px-4 py-3 text-left">Member Title</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($customers as $customer)
                                    <tr class="border-t border-white/5">
                                        <td class="px-4 py-3">
                                            <input type="checkbox" name="member_ids[]" value="{{ $customer->id }}" class="member-checkbox rounded border-white/30 bg-white/10 text-cyan-500 focus:ring-cyan-500/40" @checked(in_array($customer->id, $selectedMembers, true))>
                                        </td>
                                        <td class="px-4 py-3">
                                            <p class="font-semibold text-white">{{ $customer->full_name }}</p>
                                            <p class="text-xs text-slate-400">{{ $customer->email }}</p>
                                        </td>
                                        <td class="px-4 py-3">{{ $customer->phone ?: '—' }}</td>
                                        <td class="px-4 py-3">{{ $customer->national_id ?: '—' }}</td>
                                        <td class="px-4 py-3">
                                            <select name="member_titles[{{ $customer->id }}]" class="w-full rounded-xl bg-white/10 border border-white/10 text-white px-3 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                                                <option value="">Select title</option>
                                                @foreach ($titles as $title)
                                                    <option value="{{ $title->id }}" @selected((int) ($selectedTitles[$customer->id] ?? 0) === $title->id)>{{ $title->name }}</option>
                                                @endforeach
                                            </select>
                                            @error("member_titles.$customer->id")
                                                <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                                            @enderror
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            @error('customer_group_id')
                <p class="text-sm text-red-400">{{ $message }}</p>
            @enderror
            @error('member_ids')
                <p class="text-sm text-red-400">{{ $message }}</p>
            @enderror
            @error('member_titles')
                <p class="text-sm text-red-400">{{ $message }}</p>
            @enderror

            <div class="flex justify-end gap-3">
                <a href="{{ route('admin.loan-applications.index') }}" class="inline-flex items-center rounded-2xl border border-white/20 px-4 py-3 text-sm font-medium text-slate-300 hover:bg-white/10 transition">Cancel</a>
                <button type="submit" class="inline-flex items-center rounded-2xl bg-cyan-500 px-4 py-3 text-sm font-semibold text-white hover:bg-cyan-600 transition">
                    Continue to Loan Details
                </button>
            </div>
        </form>
    </div>

    @push('scripts')
        <script>
            const checkboxes = document.querySelectorAll('.member-checkbox');
            const selectedCount = document.getElementById('selectedCount');

            function updateSelectedCount() {
                selectedCount.textContent = Array.from(checkboxes).filter(cb => cb.checked).length;
            }

            checkboxes.forEach((checkbox) => {
                checkbox.addEventListener('change', updateSelectedCount);
            });
        </script>
    @endpush
@endsection
