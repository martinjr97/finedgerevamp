@php
    $isEdit = isset($product) && $product && $product->exists;
@endphp

<form action="{{ $isEdit ? route('admin.loan-products.update', $product) : route('admin.loan-products.store') }}" method="POST" class="space-y-8">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="grid gap-6 md:grid-cols-2">
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
            <div>
                <label class="text-sm font-medium text-slate-300">Name</label>
                <input type="text" name="name" value="{{ old('name', $isEdit ? $product->name : '') }}" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
            </div>
            <div>
                <label class="text-sm font-medium text-slate-300">Code</label>
                <input type="text" name="code" value="{{ old('code', $isEdit ? $product->code : '') }}" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
            </div>
            <div>
                <label class="text-sm font-medium text-slate-300">Category</label>
                <select name="category" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                    <option value="government" @selected(old('category', $isEdit ? $product->category : '') === 'government')>Government</option>
                    <option value="mou" @selected(old('category', $isEdit ? $product->category : '') === 'mou')>MOU</option>
                    <option value="character" @selected(old('category', $isEdit ? $product->category : '') === 'character')>Character</option>
                    <option value="collateral" @selected(old('category', $isEdit ? $product->category : '') === 'collateral')>Collateral</option>
                    <option value="marketeer" @selected(old('category', $isEdit ? $product->category : '') === 'marketeer')>Marketeer</option>
                    <option value="sme" @selected(old('category', $isEdit ? $product->category : '') === 'sme')>SME</option>
                    <option value="group_loans" @selected(old('category', $isEdit ? $product->category : '') === 'group_loans')>Group Loans</option>
                </select>
            </div>
            <div>
                <label class="text-sm font-medium text-slate-300">Description</label>
                <textarea name="description" rows="3" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">{{ old('description', $isEdit ? $product->description : '') }}</textarea>
            </div>
        </div>

        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
            <div>
                <label class="text-sm font-medium text-slate-300">Tenure (Months)</label>
                <input type="number" name="tenure_months" min="1" value="{{ old('tenure_months', $isEdit ? $product->tenure_months : '') }}" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
            </div>
            <div>
                <label class="text-sm font-medium text-slate-300">Max Amount</label>
                <input type="number" name="max_amount" step="0.01" min="0" value="{{ old('max_amount', $isEdit ? $product->max_amount : '') }}" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
            </div>
            <div class="space-y-3">
                <label class="inline-flex items-center gap-2 text-sm text-slate-300">
                    <input type="checkbox" name="requires_collateral" value="1" class="rounded border-white/20 bg-white/10 text-cyan-400 focus:ring-cyan-500/30" @checked(old('requires_collateral', $isEdit ? $product->requires_collateral : false))>
                    Requires Collateral
                </label>
                <label class="inline-flex items-center gap-2 text-sm text-slate-300">
                    <input type="checkbox" name="requires_reference" value="1" class="rounded border-white/20 bg-white/10 text-cyan-400 focus:ring-cyan-500/30" @checked(old('requires_reference', $isEdit ? $product->requires_reference : false))>
                    Requires Reference
                </label>
                <label class="inline-flex items-center gap-2 text-sm text-slate-300">
                    <input type="checkbox" name="is_active" value="1" class="rounded border-white/20 bg-white/10 text-cyan-400 focus:ring-cyan-500/30" @checked(old('is_active', $isEdit ? $product->is_active : true))>
                    Active
                </label>
            </div>
        </div>
    </div>

    <div class="flex items-center justify-end gap-3">
        <a href="{{ route('admin.loan-products.index') }}" class="inline-flex items-center gap-2 rounded-2xl border border-white/20 bg-white/5 px-4 py-3 text-base font-medium text-slate-300 hover:bg-white/10 hover:border-white/30 transition">
            Cancel
        </a>
        <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-blue-500 to-blue-600 px-4 py-3 text-base font-semibold text-white shadow-lg shadow-blue-500/30 hover:from-blue-600 hover:to-blue-700 transition">
            {{ $isEdit ? 'Update Product' : 'Create Product' }}
        </button>
    </div>
</form>
