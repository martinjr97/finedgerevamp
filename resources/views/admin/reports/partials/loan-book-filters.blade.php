@props([
    'formAction',
    'loanProducts',
    'customerGroups',
])

<form method="GET" action="{{ $formAction }}" class="space-y-4">
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <div>
            <label class="block text-sm font-medium text-slate-300 mb-2">Search</label>
            <input type="text" name="search" value="{{ request('search') }}"
                   placeholder="Loan number, customer name..."
                   class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-300 mb-2">Status</label>
            <select name="status" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                <option value="">All Statuses</option>
                <option value="pending_approval" @selected(request('status') === 'pending_approval')>Pending Approval</option>
                <option value="approved" @selected(request('status') === 'approved')>Approved</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="settled" @selected(request('status') === 'settled')>Settled</option>
                <option value="defaulted" @selected(request('status') === 'defaulted')>Defaulted</option>
                <option value="cancelled" @selected(request('status') === 'cancelled')>Cancelled</option>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-300 mb-2">Product</label>
            <select name="loan_product_id" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                <option value="">All Products</option>
                @foreach($loanProducts as $product)
                    <option value="{{ $product->id }}" @selected(request('loan_product_id') == $product->id)>
                        {{ $product->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-300 mb-2">Customer Group</label>
            <select name="customer_group_id" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                <option value="">All Groups</option>
                @foreach($customerGroups as $group)
                    <option value="{{ $group->id }}" @selected(request('customer_group_id') == $group->id)>
                        {{ $group->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-300 mb-2">Start Date From</label>
            <input type="date" name="date_from" value="{{ request('date_from') }}"
                   class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-300 mb-2">Start Date To</label>
            <input type="date" name="date_to" value="{{ request('date_to') }}"
                   class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-300 mb-2">Disbursement</label>
            <select name="disbursement_status" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                <option value="">Any</option>
                <option value="pending" @selected(request('disbursement_status') === 'pending')>Pending</option>
                <option value="completed" @selected(request('disbursement_status') === 'completed')>Completed</option>
            </select>
        </div>
    </div>

    <label class="inline-flex items-center gap-2 text-sm text-slate-300 cursor-pointer">
        <input type="checkbox" name="show_all" value="1" @checked(request()->boolean('show_all'))
               class="rounded border-white/20 bg-white/10 text-cyan-500 focus:ring-cyan-400/40">
        Show all loans (include approved awaiting disbursement)
    </label>

    <div class="flex flex-wrap items-center gap-3">
        <button type="submit" class="rounded-2xl bg-cyan-500/20 border border-cyan-500/50 px-6 py-2 text-sm font-medium text-cyan-300 hover:bg-cyan-500/30 transition">
            Apply Filters
        </button>
        <a href="{{ $formAction }}" class="rounded-2xl border border-white/10 px-6 py-2 text-sm font-medium text-white/80 hover:bg-white/10 transition">
            Clear Filters
        </a>
        {{ $slot ?? '' }}
    </div>
</form>
