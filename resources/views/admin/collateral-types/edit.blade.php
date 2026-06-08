@extends('layouts.admin')

@section('title', 'Edit Collateral Type | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Edit Collateral Type',
            'description' => 'Update collateral type for ' . $loanProduct->name,
            'buttons' => [
                [
                    'action' => 'secondary',
                    'text' => 'Back to Collateral Types',
                    'href' => route('admin.loan-products.collateral-types.index', $loanProduct),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>'
                ]
            ]
        ])

        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <form method="POST" action="{{ route('admin.loan-products.collateral-types.update', [$loanProduct, $collateralType]) }}" class="space-y-6">
                @csrf
                @method('PUT')

                <div class="grid gap-6 md:grid-cols-2">
                    {{-- Name --}}
                    <div>
                        <label for="name" class="block text-sm font-medium text-slate-300 mb-2">
                            Name <span class="text-rose-400">*</span>
                        </label>
                        <input type="text" 
                               id="name" 
                               name="name" 
                               value="{{ old('name', $collateralType->name) }}" 
                               required
                               class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                        @error('name')
                            <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Code --}}
                    <div>
                        <label for="code" class="block text-sm font-medium text-slate-300 mb-2">
                            Code <span class="text-rose-400">*</span>
                        </label>
                        <input type="text" 
                               id="code" 
                               name="code" 
                               value="{{ old('code', $collateralType->code) }}" 
                               required
                               class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                        @error('code')
                            <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Category --}}
                    <div>
                        <label for="category" class="block text-sm font-medium text-muted mb-2">
                            Category <span class="text-rose-400">*</span>
                        </label>
                        <select id="category"
                                name="category"
                                required
                                class="w-full rounded-2xl border border-muted bg-white text-primary px-4 py-2 focus:border-primary focus:ring-primary/20">
                            <option value="">Select category</option>
                            @foreach($categories as $value => $label)
                                <option value="{{ $value }}" {{ old('category', $collateralType->category) === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('category')
                            <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Loan to Value Ratio --}}
                    <div>
                        <label for="loan_to_value_ratio" class="block text-sm font-medium text-slate-300 mb-2">
                            Loan to Value Ratio (%)
                        </label>
                        <input type="number" 
                               id="loan_to_value_ratio" 
                               name="loan_to_value_ratio" 
                               value="{{ old('loan_to_value_ratio', $collateralType->loan_to_value_ratio) }}" 
                               step="0.01"
                               min="0"
                               max="100"
                               class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                        <p class="mt-1 text-xs text-slate-400">Percentage of collateral value that can be loaned (e.g., 70 = 70%)</p>
                        @error('loan_to_value_ratio')
                            <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Min Value --}}
                    <div>
                        <label for="min_value" class="block text-sm font-medium text-slate-300 mb-2">
                            Minimum Value (ZMW)
                        </label>
                        <input type="number" 
                               id="min_value" 
                               name="min_value" 
                               value="{{ old('min_value', $collateralType->min_value) }}" 
                               step="0.01"
                               min="0"
                               class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                        @error('min_value')
                            <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Max Value --}}
                    <div>
                        <label for="max_value" class="block text-sm font-medium text-slate-300 mb-2">
                            Maximum Value (ZMW)
                        </label>
                        <input type="number" 
                               id="max_value" 
                               name="max_value" 
                               value="{{ old('max_value', $collateralType->max_value) }}" 
                               step="0.01"
                               min="0"
                               class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                        @error('max_value')
                            <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Description --}}
                <div>
                    <label for="description" class="block text-sm font-medium text-slate-300 mb-2">
                        Description
                    </label>
                    <textarea id="description" 
                              name="description" 
                              rows="4"
                              class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">{{ old('description', $collateralType->description) }}</textarea>
                    @error('description')
                        <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Is Active --}}
                <div class="flex items-center gap-3">
                    <input type="checkbox" 
                           id="is_active" 
                           name="is_active" 
                           value="1"
                           {{ old('is_active', $collateralType->is_active) ? 'checked' : '' }}
                           class="rounded bg-white/10 border-white/10 text-cyan-400 focus:ring-cyan-400/40">
                    <label for="is_active" class="text-sm font-medium text-slate-300">
                        Active
                    </label>
                </div>

                <div class="flex items-center gap-3 pt-4">
                    <button type="submit" class="rounded-2xl bg-cyan-500/20 border border-cyan-500/50 px-6 py-2 text-sm font-medium text-cyan-300 hover:bg-cyan-500/30 transition">
                        Update Collateral Type
                    </button>
                    <a href="{{ route('admin.loan-products.collateral-types.index', $loanProduct) }}" class="rounded-2xl border border-white/10 px-6 py-2 text-sm font-medium text-white/80 hover:bg-white/10 transition">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
@endsection

