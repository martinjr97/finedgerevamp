@extends('layouts.auth')

@section('title', 'Register | ' . config('app.system_name'))
@section('heading', 'Create your account')
@section('subheading', 'Tell us how you would like to register.')

@section('content')
    @include('customer.registration.partials.retrieve-modal', ['triggerClass' => 'mb-8'])

    <p class="mb-6 text-center text-lg font-semibold text-slate-900">
        Are you a Government Worker?
    </p>

    <div class="grid gap-4 md:grid-cols-2">
        @if($enabledPaths->contains(\App\Support\PublicRegistrationPaths::GOVERNMENT_WORKER))
            <a
                href="{{ route('customer.register-request.government-worker.create') }}"
                class="group flex flex-col rounded-3xl border-2 border-emerald-200 bg-emerald-50/80 p-6 shadow-md transition hover:border-emerald-400 hover:shadow-lg"
            >
                <span class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Yes</span>
                <span class="mt-2 text-lg font-bold text-slate-900">I am a Government Worker</span>
                <span class="mt-2 text-sm text-slate-600">
                    For formally employed government and public-sector payroll applicants.
                </span>
            </a>
        @endif

        @if($enabledPaths->contains(\App\Support\PublicRegistrationPaths::COLLATERAL_BASED))
            <a
                href="{{ route('customer.register-request.collateral-based.create') }}"
                class="group flex flex-col rounded-3xl border-2 border-blue-200 bg-blue-50/80 p-6 shadow-md transition hover:border-blue-400 hover:shadow-lg"
            >
                <span class="text-xs font-semibold uppercase tracking-wide text-blue-700">No</span>
                <span class="mt-2 text-lg font-bold text-slate-900">Collateral-Based Registration</span>
                <span class="mt-2 text-sm text-slate-600">
                    For applicants applying with pledged collateral such as property or vehicles.
                </span>
            </a>
        @endif
    </div>

    <div class="mt-6 text-center">
        <a href="{{ route('customer.login') }}" class="text-sm text-slate-600 hover:text-slate-800">Back to login</a>
    </div>
@endsection
