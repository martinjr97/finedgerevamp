@php
    use App\Models\Channel;
@endphp

@extends('layouts.customer')

@section('title', 'Select Payment Channel')

@section('content')
    <style>
        input[name="channel_id"]:checked + .channel-option-card .channel-option-indicator {
            border-color: #2563eb !important;
            background-color: #2563eb !important;
        }

        input[name="channel_id"]:checked + .channel-option-card .channel-option-check {
            opacity: 1 !important;
            color: #f8f9fa !important;
        }
    </style>

    <div class="content-area space-y-6 max-w-2xl mx-auto">
        {{-- Header --}}
        <div class="bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 rounded-2xl p-6 shadow-xl border-2 border-blue-500">
            <h1 class="text-3xl font-bold mb-2 text-white">Select Payment Channel</h1>
            <p class="text-blue-100">Choose how you want to receive your loan disbursement</p>
        </div>

        {{-- Channels List --}}
        <form action="{{ route('customer.loans.store-channel') }}" method="POST" class="space-y-4">
            @csrf
            
            @if($channels->isEmpty())
                <div class="bg-gradient-to-r from-yellow-50 to-amber-50 dark:from-yellow-900 dark:to-amber-900 border-2 border-yellow-300 dark:border-yellow-600 rounded-2xl p-6 shadow-lg">
                    <p class="text-yellow-800 dark:text-yellow-200 font-medium">No payment channels available at the moment. Please contact support.</p>
                </div>
            @else
                <div class="space-y-3">
                    @foreach($channels as $channel)
                        <label class="block cursor-pointer">
                            <input type="radio" name="channel_id" value="{{ $channel->id }}" 
                                   class="peer sr-only" 
                                   required
                                   @if(old('channel_id') == $channel->id) checked @endif>
                            <div class="channel-option-card bg-gradient-to-br from-white to-gray-50 dark:from-gray-800 dark:to-gray-900 border-2 border-gray-300 dark:border-gray-600 rounded-xl p-5 shadow-md hover:shadow-lg transition-all peer-checked:border-blue-500 peer-checked:bg-gradient-to-br peer-checked:from-blue-50 peer-checked:to-indigo-50 dark:peer-checked:from-blue-900 dark:peer-checked:to-indigo-900 dark:peer-checked:border-blue-400">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1">
                                        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-1">
                                            {{ $channel->name }}
                                        </h3>
                                        @php
                                            $typeLabel = match ($channel->type) {
                                                Channel::TYPE_BANK => 'Bank Transfer',
                                                Channel::TYPE_CASH => 'Cash',
                                                default => 'Mobile Money',
                                            };
                                        @endphp
                                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $typeLabel }}</p>
                                        @if($channel->description)
                                            <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">
                                                {{ $channel->description }}
                                            </p>
                                        @endif
                                        @if($channel->can_disburse)
                                            <div class="mt-2">
                                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300 font-semibold text-xs">
                                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                    </svg>
                                                    Disbursement
                                                </span>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="ml-4 flex-shrink-0">
                                        <div class="channel-option-indicator w-6 h-6 rounded-full border-2 border-gray-400 dark:border-gray-500 flex items-center justify-center transition-all">
                                            <svg class="channel-option-check w-4 h-4 opacity-0 transition-opacity" style="color: #F8F9FA !important;" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </label>
                    @endforeach
                </div>

                @error('channel_id')
                    <p class="text-red-500 text-sm mt-2">{{ $message }}</p>
                @enderror
            @endif

            {{-- Action Buttons --}}
            <div class="flex items-center justify-between gap-4 pt-4">
                <a href="{{ route('customer.dashboard') }}" 
                   class="inline-flex items-center gap-2 rounded-xl border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 px-6 py-3 font-semibold hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back
                </a>
                @if($channels->isNotEmpty())
                    <button type="submit" 
                            class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-green-500 via-emerald-500 to-teal-600 hover:from-green-600 hover:via-emerald-600 hover:to-teal-700 text-white px-6 py-3 font-bold shadow-xl border-2 border-green-400 transition transform hover:scale-[1.02] hover:shadow-2xl">
                        <span>Continue</span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                @endif
            </div>
        </form>
    </div>
@endsection
