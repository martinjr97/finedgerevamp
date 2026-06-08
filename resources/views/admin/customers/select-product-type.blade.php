@extends('layouts.admin')

@section('title', 'Select Product Type | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        <div class="space-y-2 text-left">
            <h1 class="text-3xl font-bold">Select Product Type</h1>
            <p class="text-sm text-slate-400">Choose a loan product type to register a new customer</p>
        </div>

        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
            @foreach ($products as $category => $categoryProducts)
                <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                    <h3 class="mb-4 text-lg font-semibold text-white">{{ ucwords(str_replace('_', ' ', $category)) }}</h3>
                    <div class="space-y-3">
                        @foreach ($categoryProducts as $product)
                            <a 
                                href="{{ route('admin.customers.create', ['product_id' => $product->id]) }}" 
                                class="block rounded-2xl border border-white/10 bg-white/5 p-4 hover:bg-white/10 transition"
                            >
                                <div class="font-medium text-white">{{ $product->name }}</div>
                                <div class="mt-1 text-xs text-slate-400">{{ $product->code }}</div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endsection
