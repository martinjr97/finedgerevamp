@extends('layouts.admin')

@section('title', 'Manage Rate Type | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Manage Rate Type',
            'description' => 'Assign a rate type to the customer group: ' . $customerGroup->name,
            'buttons' => [
                [
                    'action' => 'secondary',
                    'text' => 'Back to Product',
                    'href' => route('admin.loan-products.show', $loanProduct),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>'
                ]
            ]
        ])

        {{-- Customer Group Info --}}
        <div class="rounded-3xl border-2 border-blue-500/30 bg-blue-950/30 p-6 shadow-lg">
            <h2 class="mb-4 text-xl font-semibold text-white flex items-center gap-2">
                <span class="w-1 h-6 rounded-full bg-blue-500"></span>Customer Group Information
            </h2>
            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Group Name</p>
                    <p class="text-sm font-medium text-white">{{ $customerGroup->name }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Code</p>
                    <p class="text-sm font-medium text-white">{{ $customerGroup->code }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Product</p>
                    <p class="text-sm font-medium text-white">{{ $loanProduct->name }}</p>
                </div>
            </div>
        </div>

        {{-- Rate Type Assignment Form --}}
        <form action="{{ route('admin.customer-groups.update-rate-type', $customerGroup) }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="rounded-3xl border-2 border-blue-500/30 bg-blue-950/30 p-6 shadow-lg">
                <h2 class="mb-6 text-xl font-semibold text-white flex items-center gap-2">
                    <span class="w-1 h-6 rounded-full bg-blue-500"></span>Select Rate Type
                </h2>

                @if($loanRateTypes->isEmpty())
                    <div class="rounded-2xl border border-amber-500/30 bg-amber-500/10 p-4">
                        <p class="text-amber-300 text-center">No active rate types found for this product. Please create rate types first.</p>
                    </div>
                @else
                    <div class="space-y-3">
                        <label class="block cursor-pointer">
                            <input type="radio" 
                                   name="loan_rate_type_id" 
                                   value="" 
                                   class="peer sr-only"
                                   @if(!$customerGroup->loan_rate_type_id) checked @endif>
                            <div class="rounded-xl border-2 border-gray-600 bg-gray-800/50 p-4 hover:border-gray-500 transition peer-checked:border-blue-500 peer-checked:bg-blue-900/30">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="font-semibold text-white">No Rate Type</p>
                                        <p class="text-sm text-slate-400 mt-1">Remove rate type assignment</p>
                                    </div>
                                    <div class="w-5 h-5 rounded-full border-2 border-gray-500 peer-checked:border-blue-500 peer-checked:bg-blue-500 flex items-center justify-center">
                                        <svg class="w-3 h-3 text-white opacity-0 peer-checked:opacity-100 transition-opacity" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </label>

                        @foreach($loanRateTypes as $rateType)
                            <label class="block cursor-pointer">
                                <input type="radio" 
                                       name="loan_rate_type_id" 
                                       value="{{ $rateType->id }}" 
                                       class="peer sr-only"
                                       @if($customerGroup->loan_rate_type_id == $rateType->id) checked @endif>
                                <div class="rounded-xl border-2 border-gray-600 bg-gray-800/50 p-4 hover:border-gray-500 transition peer-checked:border-indigo-500 peer-checked:bg-indigo-900/30">
                                    <div class="flex items-center justify-between">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-3 mb-2">
                                                <p class="font-semibold text-white">{{ $rateType->name }}</p>
                                                <span class="rounded-full bg-cyan-500/20 px-2 py-1 text-xs text-cyan-300 font-mono">{{ $rateType->code }}</span>
                                            </div>
                                            @if($rateType->description)
                                                <p class="text-sm text-slate-400 mb-2">{{ $rateType->description }}</p>
                                            @endif
                                            <div class="flex items-center gap-4 text-xs">
                                                <span class="text-slate-500">Accrual: <span class="text-slate-300 font-medium capitalize">{{ $rateType->accrual_period }}</span></span>
                                                @if($rateType->loanRates->count() > 0)
                                                    <span class="text-slate-500">{{ $rateType->loanRates->count() }} rate(s) configured</span>
                                                @else
                                                    <span class="text-amber-500">No rates configured</span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="ml-4 flex-shrink-0">
                                            <div class="w-5 h-5 rounded-full border-2 border-gray-500 peer-checked:border-indigo-500 peer-checked:bg-indigo-500 flex items-center justify-center">
                                                <svg class="w-3 h-3 text-white opacity-0 peer-checked:opacity-100 transition-opacity" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                </svg>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </label>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Action Buttons --}}
            <div class="flex items-center justify-end gap-4">
                <a href="{{ route('admin.loan-products.show', $loanProduct) }}" 
                   class="inline-flex items-center gap-2 rounded-xl border-2 border-gray-600 bg-gray-800 text-gray-300 px-6 py-3 font-semibold hover:bg-gray-700 transition">
                    Cancel
                </a>
                @if($loanRateTypes->isNotEmpty())
                    <button type="submit" 
                            class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-600 hover:to-purple-700 text-white px-6 py-3 font-bold shadow-xl border-2 border-indigo-400 transition transform hover:scale-[1.02] hover:shadow-2xl">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Save Rate Type Assignment
                    </button>
                @endif
            </div>
        </form>
    </div>
@endsection

