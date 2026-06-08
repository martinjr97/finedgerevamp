@extends('layouts.admin')

@section('title', 'Change Customer Group | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        <div class="flex items-center justify-between">
            <div class="space-y-1">
                <p class="text-xs uppercase tracking-[0.4em] text-cyan-300">Customer Management</p>
                <h1 class="text-3xl font-bold">{{ $customer->full_name }}</h1>
                <p class="text-sm text-slate-400">Product: <span class="font-semibold text-white">{{ $customer->loanProduct->name }} ({{ $customer->loanProduct->code }})</span></p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.customers.show', $customer) }}" class="inline-flex items-center gap-2 rounded-2xl border border-white/10 px-4 py-3 text-sm text-white hover:bg-white/10 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back to Customer
                </a>
            </div>
        </div>

        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <h2 class="mb-6 text-xl font-semibold text-white flex items-center gap-2">
                <span class="w-1 h-6 rounded-full bg-purple-500"></span>
                {{ $customer->customerGroup ? 'Change Customer Group' : 'Link Customer to Group' }}
            </h2>

            @if ($customer->customerGroup)
                <div class="mb-6 rounded-2xl border border-blue-500/30 bg-blue-500/10 p-4">
                    <p class="text-sm font-semibold text-blue-300 mb-1">Current Group</p>
                    <p class="text-white font-medium">{{ $customer->customerGroup->name }} ({{ $customer->customerGroup->code }})</p>
                    @if ($customer->customerGroup->description)
                        <p class="text-xs text-blue-200 mt-1">{{ $customer->customerGroup->description }}</p>
                    @endif
                </div>
            @else
                <div class="mb-6 rounded-2xl border border-amber-500/30 bg-amber-500/10 p-4">
                    <p class="text-sm font-semibold text-amber-300">This customer is not currently assigned to any group.</p>
                </div>
            @endif

            <form action="{{ route('admin.customers.update-group', $customer) }}" method="POST" class="space-y-6">
                @csrf

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">
                        Select Customer Group <span class="text-red-400">*</span>
                    </label>
                    <select name="customer_group_id" required class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-purple-500 focus:ring-purple-500/40 focus:outline-none transition">
                        <option value="">Select a group...</option>
                        @foreach ($customerGroups as $group)
                            <option value="{{ $group->id }}" @selected(old('customer_group_id', $customer->customer_group_id) == $group->id)>
                                {{ $group->name }} ({{ $group->code }}) - {{ ucfirst($group->risk_level) }} Risk
                            </option>
                        @endforeach
                    </select>
                    @error('customer_group_id')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                    <p class="mt-2 text-xs text-slate-400">
                        Select the appropriate group based on the customer's profile and risk assessment.
                    </p>
                </div>

                @if ($customerGroups->count() > 0)
                    <div class="rounded-2xl border border-white/5 bg-white/5 p-4">
                        <p class="text-xs font-semibold text-slate-300 mb-3 uppercase tracking-wider">Available Groups</p>
                        <div class="space-y-2">
                            @foreach ($customerGroups as $group)
                                <div class="flex items-start justify-between p-3 rounded-xl bg-white/5 border border-white/5">
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-white">{{ $group->name }}</p>
                                        <p class="text-xs text-slate-400 mt-1">{{ $group->code }}</p>
                                        @if ($group->description)
                                            <p class="text-xs text-slate-500 mt-1">{{ $group->description }}</p>
                                        @endif
                                    </div>
                                    <div class="ml-4 text-right">
                                        <span class="inline-block rounded-full px-2 py-1 text-xs font-medium
                                            {{ $group->risk_level === 'low' ? 'bg-emerald-500/20 text-emerald-300' : ($group->risk_level === 'medium' ? 'bg-amber-500/20 text-amber-300' : 'bg-rose-500/20 text-rose-300') }}">
                                            {{ ucfirst($group->risk_level) }}
                                        </span>
                                        @if ($group->max_loan_amount)
                                            <p class="text-xs text-slate-400 mt-1">Max: ZMW {{ number_format($group->max_loan_amount, 2) }}</p>
                                        @endif
                                        @if ($group->max_loan_tenure_months)
                                            <p class="text-xs text-slate-400">Tenure: {{ $group->max_loan_tenure_months }} months</p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    <div class="rounded-2xl border border-amber-500/30 bg-amber-500/10 p-4">
                        <p class="text-sm text-amber-300">No active customer groups available for this product. Please create a group first.</p>
                    </div>
                @endif

                <div class="flex items-center gap-3 pt-4">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-purple-500 to-indigo-500 px-6 py-3 font-semibold text-white shadow-lg shadow-purple-500/30 hover:shadow-purple-500/50 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        {{ $customer->customerGroup ? 'Update Group' : 'Link to Group' }}
                    </button>
                    <a href="{{ route('admin.customers.show', $customer) }}" class="inline-flex items-center gap-2 rounded-2xl border border-white/10 px-6 py-3 text-sm text-white hover:bg-white/10 transition">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
@endsection
