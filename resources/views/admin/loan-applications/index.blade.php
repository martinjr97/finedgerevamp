@extends('layouts.admin')

@section('title', 'Loan Application | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'New Loan Application',
            'description' => 'Select a loan product to begin the application process',
            'buttons' => [
                [
                    'action' => 'secondary',
                    'text' => 'Back to Loans',
                    'href' => route('admin.loans.index'),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>'
                ]
            ]
        ])

        {{-- Step Indicator --}}
        <div class="flex items-center justify-center">
            <div class="flex items-center space-x-4">
                <div class="flex items-center">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-cyan-500 text-white font-semibold">1</div>
                    <span class="ml-2 text-sm font-medium text-white">Select Product</span>
                </div>
                <div class="h-1 w-16 bg-slate-600"></div>
                <div class="flex items-center">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-600 text-slate-300 font-semibold">2</div>
                    <span class="ml-2 text-sm font-medium text-slate-400">Search Customer</span>
                </div>
                <div class="h-1 w-16 bg-slate-600"></div>
                <div class="flex items-center">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-600 text-slate-300 font-semibold">3</div>
                    <span class="ml-2 text-sm font-medium text-slate-400">Loan Details</span>
                </div>
                <div class="h-1 w-16 bg-slate-600"></div>
                <div class="flex items-center">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-600 text-slate-300 font-semibold">4</div>
                    <span class="ml-2 text-sm font-medium text-slate-400">Collateral / Review</span>
                </div>
            </div>
        </div>

        {{-- Collateral Products --}}
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <h2 class="mb-6 text-xl font-semibold text-white flex items-center gap-2">
                <span class="w-1 h-6 rounded-full bg-cyan-500"></span>Collateral-based Products
            </h2>
            
            @if($collateralProducts->isEmpty())
                <div class="text-center py-12">
                    <p class="text-slate-400 mb-4">No collateral-based loan products are available.</p>
                    <a href="{{ route('admin.loan-products.create') }}" class="inline-flex items-center gap-2 rounded-full bg-cyan-500 px-4 py-2 text-sm font-medium text-white hover:bg-cyan-600 transition">
                        Create Loan Product
                    </a>
                </div>
            @else
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    @foreach($collateralProducts as $product)
                        <a href="{{ route('admin.loan-applications.search-customer', $product) }}" 
                           class="group rounded-2xl border border-white/10 bg-white/5 p-6 hover:border-cyan-500/50 hover:bg-white/10 transition">
                            <div class="flex items-start justify-between mb-4">
                                <h3 class="text-lg font-semibold text-white group-hover:text-cyan-300 transition">{{ $product->name }}</h3>
                                <span class="rounded-full bg-cyan-500/20 px-2 py-1 text-xs text-cyan-300">
                                    {{ strtoupper($product->code) }}
                                </span>
                            </div>
                            <p class="text-sm text-slate-400 mb-4">{{ $product->description ?? 'No description available.' }}</p>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-slate-400">Max Amount:</span>
                                <span class="font-medium text-white">{{ number_format($product->max_amount ?? 0, 2) }}</span>
                            </div>
                            @if($product->company)
                                <div class="mt-2 flex items-center justify-between text-sm">
                                    <span class="text-slate-400">Company:</span>
                                    <span class="font-medium text-white">{{ $product->company->name }}</span>
                                </div>
                            @endif
                            <div class="mt-4 pt-4 border-t border-white/10">
                                <span class="inline-flex items-center gap-2 text-sm text-cyan-400 group-hover:text-cyan-300 transition">
                                    Continue
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Government Products --}}
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg mt-6">
            <h2 class="mb-6 text-xl font-semibold text-white flex items-center gap-2">
                <span class="w-1 h-6 rounded-full bg-indigo-500"></span>Government Products
            </h2>

            @if($governmentProducts->isEmpty())
                <div class="text-center py-12">
                    <p class="text-slate-400 mb-4">No government loan products are available.</p>
                    <a href="{{ route('admin.loan-products.create') }}" class="inline-flex items-center gap-2 rounded-full bg-indigo-500 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-600 transition">
                        Create Loan Product
                    </a>
                </div>
            @else
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    @foreach($governmentProducts as $product)
                        <a href="{{ route('admin.loan-applications.search-customer', $product) }}"
                           class="group rounded-2xl border border-white/10 bg-white/5 p-6 hover:border-indigo-500/50 hover:bg-white/10 transition">
                            <div class="flex items-start justify-between mb-4">
                                <h3 class="text-lg font-semibold text-white group-hover:text-indigo-300 transition">{{ $product->name }}</h3>
                                <span class="rounded-full bg-indigo-500/20 px-2 py-1 text-xs text-indigo-300">
                                    {{ strtoupper($product->code) }}
                                </span>
                            </div>
                            <p class="text-sm text-slate-400 mb-4">{{ $product->description ?? 'No description available.' }}</p>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-slate-400">Max Amount:</span>
                                <span class="font-medium text-white">{{ number_format($product->max_amount ?? 0, 2) }}</span>
                            </div>
                            @if($product->company)
                                <div class="mt-2 flex items-center justify-between text-sm">
                                    <span class="text-slate-400">Company:</span>
                                    <span class="font-medium text-white">{{ $product->company->name }}</span>
                                </div>
                            @endif
                            <div class="mt-4 pt-4 border-t border-white/10">
                                <span class="inline-flex items-center gap-2 text-sm text-indigo-400 group-hover:text-indigo-300 transition">
                                    Continue
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Character-based Products --}}
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg mt-6">
            <h2 class="mb-6 text-xl font-semibold text-white flex items-center gap-2">
                <span class="w-1 h-6 rounded-full bg-amber-500"></span>Character-based Products
            </h2>
            
            @if($characterProducts->isEmpty())
                <div class="text-center py-12">
                    <p class="text-slate-400 mb-4">No character-based loan products are available.</p>
                    <a href="{{ route('admin.loan-products.create') }}" class="inline-flex items-center gap-2 rounded-full bg-amber-500 px-4 py-2 text-sm font-medium text-white hover:bg-amber-600 transition">
                        Create Loan Product
                    </a>
                </div>
            @else
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    @foreach($characterProducts as $product)
                        <a href="{{ route('admin.loan-applications.search-customer', $product) }}" 
                           class="group rounded-2xl border border-white/10 bg-white/5 p-6 hover:border-amber-500/50 hover:bg-white/10 transition">
                            <div class="flex items-start justify-between mb-4">
                                <h3 class="text-lg font-semibold text-white group-hover:text-amber-300 transition">{{ $product->name }}</h3>
                                <span class="rounded-full bg-amber-500/20 px-2 py-1 text-xs text-amber-300">
                                    {{ strtoupper($product->code) }}
                                </span>
                            </div>
                            <p class="text-sm text-slate-400 mb-4">{{ $product->description ?? 'No description available.' }}</p>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-slate-400">Max Amount:</span>
                                <span class="font-medium text-white">{{ number_format($product->max_amount ?? 0, 2) }}</span>
                            </div>
                            @if($product->company)
                                <div class="mt-2 flex items-center justify-between text-sm">
                                    <span class="text-slate-400">Company:</span>
                                    <span class="font-medium text-white">{{ $product->company->name }}</span>
                                </div>
                            @endif
                            <div class="mt-4 pt-4 border-top border-white/10">
                                <span class="inline-flex items-center gap-2 text-sm text-amber-400 group-hover:text-amber-300 transition">
                                    Continue
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- MOU Products --}}
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <h2 class="mb-6 text-xl font-semibold text-white flex items-center gap-2">
                <span class="w-1 h-6 rounded-full bg-emerald-500"></span>MOU Products
            </h2>

            @if($mouProducts->isEmpty())
                <p class="text-slate-400 text-sm">No MOU-based loan products are available.</p>
            @else
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    @foreach($mouProducts as $product)
                        <a href="{{ route('admin.loan-applications.search-customer', $product) }}"
                           class="group rounded-2xl border border-white/10 bg-white/5 p-6 hover:border-emerald-500/50 hover:bg-white/10 transition">
                            <div class="flex items-start justify-between mb-4">
                                <h3 class="text-lg font-semibold text-white group-hover:text-emerald-300 transition">{{ $product->name }}</h3>
                                <span class="rounded-full bg-emerald-500/20 px-2 py-1 text-xs text-emerald-300">
                                    {{ strtoupper($product->code) }}
                                </span>
                            </div>
                            <p class="text-sm text-slate-400 mb-4">{{ $product->description ?? 'No description available.' }}</p>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-slate-400">Max Amount:</span>
                                <span class="font-medium text-white">{{ number_format($product->max_amount ?? 0, 2) }}</span>
                            </div>
                            @if($product->company)
                                <div class="mt-2 flex items-center justify-between text-sm">
                                    <span class="text-slate-400">Company:</span>
                                    <span class="font-medium text-white">{{ $product->company->name }}</span>
                                </div>
                            @endif
                            <div class="mt-4 pt-4 border-t border-white/10">
                                <span class="inline-flex items-center gap-2 text-sm text-emerald-400 group-hover:text-emerald-300 transition">
                                    Continue
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- SME Products --}}
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg mt-6">
            <h2 class="mb-6 text-xl font-semibold text-white flex items-center gap-2">
                <span class="w-1 h-6 rounded-full bg-blue-400"></span>SME Products
            </h2>

            @if($smeProducts->isEmpty())
                <p class="text-slate-400 text-sm">No SME loan products are available.</p>
            @else
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    @foreach($smeProducts as $product)
                        <a href="{{ route('admin.loan-applications.search-customer', $product) }}"
                           class="group rounded-2xl border border-white/10 bg-white/5 p-6 hover:border-blue-400/50 hover:bg-white/10 transition">
                            <div class="flex items-start justify-between mb-4">
                                <h3 class="text-lg font-semibold text-white group-hover:text-blue-200 transition">{{ $product->name }}</h3>
                                <span class="rounded-full bg-blue-400/20 px-2 py-1 text-xs text-blue-100">
                                    {{ strtoupper($product->code) }}
                                </span>
                            </div>
                            <p class="text-sm text-slate-400 mb-4">{{ $product->description ?? 'No description available.' }}</p>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-slate-400">Max Amount:</span>
                                <span class="font-medium text-white">{{ number_format($product->max_amount ?? 0, 2) }}</span>
                            </div>
                            @if($product->company)
                                <div class="mt-2 flex items-center justify-between text-sm">
                                    <span class="text-slate-400">Company:</span>
                                    <span class="font-medium text-white">{{ $product->company->name }}</span>
                                </div>
                            @endif
                            <div class="mt-4 pt-4 border-t border-white/10">
                                <span class="inline-flex items-center gap-2 text-sm text-blue-100 group-hover:text-white transition">
                                    Continue
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Group Loan Products --}}
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg mt-6">
            <h2 class="mb-6 text-xl font-semibold text-white flex items-center gap-2">
                <span class="w-1 h-6 rounded-full bg-emerald-400"></span>Group Loan Products
            </h2>

            @if($groupLoanProducts->isEmpty())
                <p class="text-slate-400 text-sm">No Group Loan products are available.</p>
            @else
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    @foreach($groupLoanProducts as $product)
                        <a href="{{ route('admin.loan-applications.group-loans.members', $product) }}"
                           class="group rounded-2xl border border-white/10 bg-white/5 p-6 hover:border-emerald-400/50 hover:bg-white/10 transition">
                            <div class="flex items-start justify-between mb-4">
                                <h3 class="text-lg font-semibold text-white group-hover:text-emerald-200 transition">{{ $product->name }}</h3>
                                <span class="rounded-full bg-emerald-400/20 px-2 py-1 text-xs text-emerald-100">
                                    {{ strtoupper($product->code) }}
                                </span>
                            </div>
                            <p class="text-sm text-slate-400 mb-4">{{ $product->description ?? 'No description available.' }}</p>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-slate-400">Max Amount:</span>
                                <span class="font-medium text-white">{{ number_format($product->max_amount ?? 0, 2) }}</span>
                            </div>
                            @if($product->company)
                                <div class="mt-2 flex items-center justify-between text-sm">
                                    <span class="text-slate-400">Company:</span>
                                    <span class="font-medium text-white">{{ $product->company->name }}</span>
                                </div>
                            @endif
                            <div class="mt-4 pt-4 border-t border-white/10">
                                <span class="inline-flex items-center gap-2 text-sm text-emerald-100 group-hover:text-white transition">
                                    Continue
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endsection
