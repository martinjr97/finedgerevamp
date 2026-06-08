@extends('layouts.auth')

@section('title', 'Terms of Service | ' . config('app.system_name'))
@section('heading', 'Terms of Service')
@section('subheading', config('app.system_tagline', 'Loan Management System'))

@section('content')
    <div class="space-y-4 text-left text-slate-700 text-sm sm:text-base md:text-lg leading-relaxed overflow-y-auto md:overflow-visible pr-1 md:pr-0">
        <p>
            These Terms of Service (“Terms”) govern your use of <strong>{{ config('app.system_name') }}</strong>
            (the “System”). By accessing or using the System, you agree to be bound by these Terms.
        </p>

        <h2 class="mt-4 text-base sm:text-lg font-semibold text-slate-900">1. Use of the System</h2>
        <p>
            The System is provided to help you view and manage your loans, repayments, and related information.
            You agree to use the System only for lawful purposes and in accordance with the rules of the
            institution operating this platform.
        </p>

        <h2 class="mt-4 text-base sm:text-lg font-semibold text-slate-900">2. Access Credentials</h2>
        <p>
            You are responsible for maintaining the confidentiality of your login details (such as your
            mobile number and PIN) and for all activities that occur under your account. The institution
            may block or suspend access if misuse or suspicious activity is detected.
        </p>

        <h2 class="mt-4 text-base sm:text-lg font-semibold text-slate-900">3. Loan Information</h2>
        <p>
            Loan balances, schedules, and statements shown in the System are provided for convenience.
            In case of discrepancies, the official records of the institution operating the System will prevail.
        </p>

        <h2 class="mt-4 text-base sm:text-lg font-semibold text-slate-900">4. Availability and Changes</h2>
        <p>
            The System may be temporarily unavailable due to maintenance, upgrades, or technical issues.
            Features and content may change or be updated without prior notice.
        </p>

        <h2 class="mt-4 text-base sm:text-lg font-semibold text-slate-900">5. Limitation of Liability</h2>
        <p>
            The System is provided “as is” without any warranty. To the extent permitted by law, the institution
            operating the System is not liable for any losses arising from interruptions, inaccuracies, or
            unauthorized access related to your use of the platform.
        </p>

        <h2 class="mt-4 text-base sm:text-lg font-semibold text-slate-900">6. Changes to These Terms</h2>
        <p>
            These Terms may be updated periodically. Continued use of the System after changes are published
            will be treated as acceptance of the updated Terms.
        </p>

        <h2 class="mt-4 text-base sm:text-lg font-semibold text-slate-900">7. Contact</h2>
        <p>
            For questions about these Terms of Service, please contact the system owner or administrator
            using the support details provided by your institution.
        </p>
    </div>
@endsection


