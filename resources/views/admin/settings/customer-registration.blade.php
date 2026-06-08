@extends('layouts.admin')

@section('title', 'Customer Registration Settings | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Customer Registration',
            'description' => 'Configure public registration paths. Customers only see Government Worker or Collateral-Based options—not internal products or groups.',
        ])

        @php
            use App\Support\PublicRegistrationPaths;
            $paths = $paths ?? PublicRegistrationPaths::normalize(null);
            $gov = $paths[PublicRegistrationPaths::GOVERNMENT_WORKER] ?? ['enabled' => false, 'loan_product_id' => null];
            $col = $paths[PublicRegistrationPaths::COLLATERAL_BASED] ?? ['enabled' => false, 'loan_product_id' => null];
        @endphp

        <form action="{{ route('admin.settings.customer-registration.update') }}" method="POST" class="space-y-8">
            @csrf
            @method('PUT')

            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-6">
                <h2 class="text-xl font-semibold text-white flex items-center gap-2">
                    <span class="w-1 h-6 rounded-full bg-cyan-500"></span>
                    Public registration
                </h2>

                <label class="inline-flex items-center gap-3 text-sm text-slate-200">
                    <input type="checkbox" name="allow_customer_registration" value="1" class="rounded border-white/20 bg-white/10 text-cyan-400 focus:ring-cyan-500/30" @checked(old('allow_customer_registration', $setting->allow_customer_registration ?? false))>
                    <span>
                        <span class="font-semibold">Allow public customer registration requests</span>
                        <span class="block text-xs text-slate-400">When enabled, customers can request to register from the login page.</span>
                    </span>
                </label>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                {{-- Government Worker --}}
                <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                    <h3 class="text-lg font-semibold text-white">Government Worker Registration</h3>
                    <p class="text-xs text-slate-400">Customers will see this as <strong class="text-slate-200">Government Worker Registration</strong>. Recommended product category: <span class="text-cyan-300">government</span>.</p>

                    <label class="inline-flex items-center gap-3 text-sm text-slate-200">
                        <input type="checkbox" name="government_worker_enabled" value="1" class="rounded border-white/20 bg-white/10 text-cyan-400" @checked(old('government_worker_enabled', $gov['enabled'] ?? false))>
                        <span class="font-semibold">Enable this path</span>
                    </label>

                    <div>
                        <label class="text-sm font-medium text-slate-200">Internal loan product</label>
                        <select name="government_worker_loan_product_id" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 text-sm">
                            <option value="">Select product</option>
                            @foreach($allProducts as $product)
                                <option value="{{ $product->id }}" @selected((int) old('government_worker_loan_product_id', $gov['loan_product_id'] ?? 0) === $product->id)>
                                    {{ $product->name }} ({{ $product->code }}) — {{ $product->category }}
                                </option>
                            @endforeach
                        </select>
                        @error('government_worker_loan_product_id')
                            <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                        @enderror
                        @if($governmentProducts->isNotEmpty())
                            <p class="mt-1 text-xs text-slate-500">Government products: {{ $governmentProducts->pluck('name')->join(', ') }}</p>
                        @endif
                    </div>
                </div>

                {{-- Collateral Based --}}
                <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                    <h3 class="text-lg font-semibold text-white">Collateral-Based Registration</h3>
                    <p class="text-xs text-slate-400">Customers will see this as <strong class="text-slate-200">Collateral-Based Registration</strong>. Recommended product category: <span class="text-cyan-300">collateral</span>.</p>

                    <label class="inline-flex items-center gap-3 text-sm text-slate-200">
                        <input type="checkbox" name="collateral_based_enabled" value="1" class="rounded border-white/20 bg-white/10 text-cyan-400" @checked(old('collateral_based_enabled', $col['enabled'] ?? false))>
                        <span class="font-semibold">Enable this path</span>
                    </label>

                    <div>
                        <label class="text-sm font-medium text-slate-200">Internal loan product</label>
                        <select name="collateral_based_loan_product_id" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 text-sm">
                            <option value="">Select product</option>
                            @foreach($allProducts as $product)
                                <option value="{{ $product->id }}" @selected((int) old('collateral_based_loan_product_id', $col['loan_product_id'] ?? 0) === $product->id)>
                                    {{ $product->name }} ({{ $product->code }}) — {{ $product->category }}
                                </option>
                            @endforeach
                        </select>
                        @error('collateral_based_loan_product_id')
                            <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                        @enderror
                        @if($collateralProducts->isNotEmpty())
                            <p class="mt-1 text-xs text-slate-500">Collateral products: {{ $collateralProducts->pluck('name')->join(', ') }}</p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-amber-400/30 bg-amber-500/10 px-4 py-3 text-xs text-amber-100">
                Customer groups are not shown publicly. Admins assign groups when creating the customer from an approved request.
            </div>

            <div class="flex items-center justify-end">
                <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-blue-500 to-blue-600 px-4 py-3 text-base font-semibold text-white shadow-lg">
                    Save Settings
                </button>
            </div>
        </form>
    </div>
@endsection
