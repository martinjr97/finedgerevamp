@extends('layouts.auth')

@section('title', 'Registration Unavailable | ' . config('app.system_name'))
@section('heading', 'Registration Unavailable')
@section('subheading', 'Public customer registration is not available at this time.')

@section('content')
    <div class="rounded-3xl border border-slate-200 bg-white/90 px-5 py-6 shadow-md text-center space-y-4">
        <p class="text-sm text-slate-600">
            Registration is enabled, but no registration options are currently available. Please try again later or contact support.
        </p>
        <a href="{{ route('customer.login') }}" class="inline-flex items-center justify-center rounded-2xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
            Back to login
        </a>
    </div>
@endsection
