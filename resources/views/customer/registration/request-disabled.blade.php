@extends('layouts.auth')

@section('title', 'Customer Registration | ' . config('app.system_name'))
@section('heading', 'Customer Registration')
@section('subheading', 'Self-service registration is coming soon.')

@section('content')
    <div class="space-y-4 text-center">
        <p class="text-base text-slate-700">
            Public customer registration is currently enabled, but the registration flow has not been completed yet.
        </p>
        <p class="text-sm text-slate-500">
            Please contact support or your relationship manager if you need help creating an account.
        </p>
    </div>
@endsection


