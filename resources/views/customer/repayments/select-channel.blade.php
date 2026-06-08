@php
    use App\Models\Channel;
@endphp

@extends('layouts.customer')

@section('title', 'Select Repayment Channel')

@section('content')
    <style>
        input[name="channel_id"]:checked + .repayment-channel-option-card .repayment-channel-option-indicator {
            border-color: #2563eb !important;
            background-color: #2563eb !important;
        }

        input[name="channel_id"]:checked + .repayment-channel-option-card .repayment-channel-option-check {
            opacity: 1 !important;
            color: #f8f9fa !important;
        }
    </style>

    <div class="space-y-6 max-w-2xl mx-auto">
        <div class="bg-primary rounded-2xl p-6 shadow-xl border border-muted">
            <h1 class="text-3xl font-bold mb-2 text-white">Select Repayment Channel</h1>
            <p class="text-slate-200">Choose how you want to make your repayment</p>
        </div>

        <div class="card rounded-2xl p-6 shadow-lg">
            <div class="text-center">
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Amount to Pay</p>
                <p class="text-4xl font-bold text-primary">ZMW {{ number_format($repaymentAmount, 2) }}</p>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                    @if($repaymentType == 'partial')
                        Partial Payment
                    @elseif($repaymentType == 'overdue')
                        Overdue Amount
                    @else
                        Full Payment
                    @endif
                </p>
            </div>
        </div>

        <form action="{{ route('customer.repayments.store-channel') }}" method="POST" class="space-y-4" id="repaymentChannelForm">
            @csrf

            @if($channels->isEmpty())
                <div class="bg-amber-50 dark:bg-amber-900/30 border-2 border-yellow-300 dark:border-yellow-600 rounded-2xl p-6 shadow-lg">
                    <p class="text-yellow-800 dark:text-yellow-200 font-medium">No repayment channels available at the moment. Please contact support.</p>
                </div>
            @else
                <div class="space-y-3">
                    @foreach($channels as $repaymentChannel)
                        @php
                            $typeLabel = match ($repaymentChannel->type) {
                                Channel::TYPE_BANK => 'Bank Transfer',
                                Channel::TYPE_CASH => 'Cash',
                                default => 'Mobile Money',
                            };
                        @endphp
                        <label class="block cursor-pointer">
                            <input type="radio"
                                   name="channel_id"
                                   value="{{ $repaymentChannel->id }}"
                                   data-channel-type="{{ $repaymentChannel->type ?? Channel::TYPE_MOBILE_WALLET }}"
                                   class="peer sr-only channel-radio"
                                   required
                                   @checked(old('channel_id') == $repaymentChannel->id)>
                            <div class="repayment-channel-option-card bg-white dark:bg-gray-800 border-2 border-gray-300 dark:border-gray-600 rounded-xl p-5 shadow-md hover:shadow-lg transition-all peer-checked:border-blue-500 peer-checked:bg-blue-50 dark:peer-checked:bg-blue-900/30 dark:peer-checked:border-blue-400">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1">
                                        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-1">
                                            {{ $repaymentChannel->name }}
                                        </h3>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $typeLabel }}</p>
                                        @if($repaymentChannel->description)
                                            <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">
                                                {{ $repaymentChannel->description }}
                                            </p>
                                        @endif
                                    </div>
                                    <div class="ml-4 flex-shrink-0">
                                        <div class="repayment-channel-option-indicator w-6 h-6 rounded-full border-2 border-gray-400 dark:border-gray-500 flex items-center justify-center transition-all">
                                            <svg class="repayment-channel-option-check w-4 h-4 opacity-0 transition-opacity" style="color: #F8F9FA !important;" fill="currentColor" viewBox="0 0 20 20">
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

                <div id="repaymentPhoneSection" class="bg-white dark:bg-gray-800 border-2 border-gray-300 dark:border-gray-600 rounded-xl p-5 shadow-md">
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2" id="repaymentPhoneLabel">
                        Mobile money number (optional)
                    </label>
                    <input type="text"
                           name="phone_number"
                           id="phone_number"
                           value="{{ old('phone_number', auth('customer')->user()->phone ?? '') }}"
                           maxlength="12"
                           inputmode="numeric"
                           pattern="260[0-9]{9}"
                           class="w-full px-4 py-3 rounded-xl bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white focus:border-blue-500 dark:focus:border-blue-400 focus:ring-2 focus:ring-blue-500/20 transition zambian-phone-input"
                           placeholder="260978232334">
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400" id="repaymentPhoneHelp">
                        For mobile money repayments, enter the number that will approve the payment prompt. Leave blank to use your profile number where applicable.
                    </p>
                    @error('phone_number')
                        <p class="mt-2 text-red-500 text-sm">{{ $message }}</p>
                    @enderror
                </div>
            @endif

            <div class="flex items-center justify-between gap-4 pt-4">
                <a href="{{ route('customer.repayments.select-type') }}"
                   class="inline-flex items-center gap-2 rounded-xl border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 px-6 py-3 font-semibold hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                    Back
                </a>
                @if($channels->isNotEmpty())
                    <button type="submit"
                            class="inline-flex items-center gap-2 rounded-xl bg-primary hover:opacity-90 text-white px-6 py-3 font-bold shadow-xl border border-primary transition transform hover:scale-[1.02] hover:shadow-2xl">
                        Continue
                    </button>
                @endif
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
        const phoneSection = document.getElementById('repaymentPhoneSection');
        const phoneLabel = document.getElementById('repaymentPhoneLabel');
        const phoneHelp = document.getElementById('repaymentPhoneHelp');
        const phoneInput = document.getElementById('phone_number');

        function syncRepaymentPhoneUi() {
            const selected = document.querySelector('.channel-radio:checked');
            const type = selected?.dataset.channelType || 'mobile_wallet';

            if (!phoneSection) {
                return;
            }

            if (type === 'cash') {
                phoneSection.classList.add('hidden');
                phoneInput?.removeAttribute('required');
            } else {
                phoneSection.classList.remove('hidden');
                phoneInput?.removeAttribute('required');
                if (type === 'bank') {
                    phoneLabel.textContent = 'Contact phone (optional)';
                    phoneHelp.textContent = 'Bank repayments may not use this number for posting. Provide a contact number if needed for follow-up.';
                } else {
                    phoneLabel.textContent = 'Mobile money number (optional)';
                    phoneHelp.textContent = 'Enter the number that will approve the payment prompt. Leave blank to use your profile number where applicable.';
                }
            }
        }

        document.querySelectorAll('.channel-radio').forEach((radio) => {
            radio.addEventListener('change', syncRepaymentPhoneUi);
        });

        phoneInput?.addEventListener('input', function (e) {
            e.target.value = e.target.value.replace(/\D/g, '');
        });

        syncRepaymentPhoneUi();
    </script>
    @endpush
@endsection
