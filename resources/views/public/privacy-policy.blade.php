@extends('layouts.auth')

@section('title', 'Privacy Policy | ' . config('app.system_name'))
@section('heading', 'Privacy Policy')
@section('subheading', config('app.system_tagline', 'Loan Management System'))

@section('content')
    <div class="space-y-4 text-left text-slate-700 text-sm sm:text-base md:text-lg leading-relaxed overflow-y-auto md:overflow-visible pr-1 md:pr-0">
        <p>
            This Privacy Policy explains how <strong>{{ config('app.system_name') }}</strong> (the “System”) collects,
            uses, and protects information related to your use of our loan management services.
            This document is for informational purposes and may be updated from time to time.
        </p>

        <h2 class="mt-4 text-base sm:text-lg font-semibold text-slate-900">1. Information We Collect</h2>
        <p>
            When you use this System, we may collect basic information such as your name, contact details,
            identification numbers, account numbers, and transaction history necessary to process and manage loans.
        </p>

        <h2 class="mt-4 text-base sm:text-lg font-semibold text-slate-900">2. How We Use Your Information</h2>
        <p>
            Your information is used to verify your identity, assess loan applications, process repayments,
            provide customer support, and meet legal and regulatory requirements that apply to the institution
            operating this System.
        </p>

        <h2 class="mt-4 text-base sm:text-lg font-semibold text-slate-900">3. Sharing of Information</h2>
        <p>
            Information may be shared with authorized staff, service providers, regulators, and other parties
            only where necessary for the operation of the System, to comply with the law, or with your consent.
            We do not sell your personal information to third parties.
        </p>

        <h2 class="mt-4 text-base sm:text-lg font-semibold text-slate-900">4. Data Security</h2>
        <p>
            Reasonable technical and organizational safeguards are used to protect information against
            loss, misuse, or unauthorized access. However, no system can be guaranteed to be completely secure.
        </p>

        <h2 class="mt-4 text-base sm:text-lg font-semibold text-slate-900">5. Your Responsibilities</h2>
        <p>
            You are responsible for keeping your login credentials (such as your mobile number and PIN)
            confidential and for notifying the institution operating this System if you suspect any
            unauthorized access to your account.
        </p>

        <h2 class="mt-4 text-base sm:text-lg font-semibold text-slate-900">6. Updates to this Policy</h2>
        <p>
            This Privacy Policy may be updated periodically. Continued use of the System after changes
            are published will be treated as acceptance of the updated policy.
        </p>

        <h2 class="mt-4 text-base sm:text-lg font-semibold text-slate-900">7. Contact</h2>
        <p>
            For questions about this Privacy Policy, please contact the system owner or administrator
            using the support details provided by your institution.
        </p>
    </div>
@endsection


