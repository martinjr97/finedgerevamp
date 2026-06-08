@extends('layouts.customer')

@section('title', 'Disbursement Details')

@section('content')
    <div class="content-area space-y-6 max-w-2xl mx-auto">
        <div class="bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 rounded-2xl p-6 shadow-xl border-2 border-blue-500">
            <h1 class="text-3xl font-bold mb-2 text-white">Disbursement Details</h1>
            <p class="text-blue-100">Tell us where to send your loan funds</p>
        </div>

        <form action="{{ route('customer.loans.store-destination') }}" method="POST" class="space-y-6">
            @csrf

            <div class="bg-gradient-to-br from-white to-gray-50 dark:from-gray-800 dark:to-gray-900 border-2 border-gray-300 dark:border-gray-600 rounded-2xl p-6 shadow-lg">
                @include('partials.customer.disbursement-destination-fields', [
                    'channel' => $channel,
                    'financialInstitutions' => $financialInstitutions,
                    'customerPhone' => $customerPhone,
                    'disbursementPhoneNumber' => $disbursementPhoneNumber,
                    'disbursementFinancialInstitutionId' => $disbursementFinancialInstitutionId,
                    'disbursementFinancialInstitutionBranchId' => $disbursementFinancialInstitutionBranchId,
                    'disbursementAccountHolderName' => $disbursementAccountHolderName,
                    'disbursementAccountNumber' => $disbursementAccountNumber,
                    'disbursementNotes' => $disbursementNotes,
                ])
            </div>

            <div class="flex items-center justify-between gap-4 pt-4">
                <a href="{{ route('customer.loans.enter-amount') }}"
                   class="inline-flex items-center gap-2 rounded-xl border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 px-6 py-3 font-semibold hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                    Back
                </a>
                <button type="submit"
                        class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-green-500 via-emerald-500 to-teal-600 hover:from-green-600 hover:via-emerald-600 hover:to-teal-700 text-white px-6 py-3 font-bold shadow-xl border-2 border-green-400 transition">
                    Continue
                </button>
            </div>
        </form>
    </div>
@endsection
