@extends('layouts.auth')

@section('title', 'Collateral-Based Registration | ' . config('app.system_name'))
@section('heading', 'Collateral-Based Registration')
@section('subheading', 'Complete your registration request using pledged collateral.')

@section('content')
    @php
        $isEditing = isset($editingReference);
        $formAction = $isEditing
            ? route('customer.register-request.collateral-based.update', $editingReference)
            : route('customer.register-request.collateral-based.store');
    @endphp

    @if(!$isEditing)
        @include('customer.registration.partials.retrieve-modal', ['triggerClass' => 'mb-6'])
    @endif

    <form method="POST" action="{{ $formAction }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @if($isEditing)
            @method('PUT')
        @endif

        @if($errors->any())
            <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                <p class="font-semibold mb-1">Please fix the errors below and try again.</p>
                <ul class="list-disc list-inside space-y-0.5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @include('customer.registration.partials.common-fields')

        @include('customer.registration.partials.customer-address', [
            'provinces' => $provinces,
            'districts' => $districts,
            'heading' => 'Your address',
            'description' => 'Your residential address where we can reach you.',
            'pairId' => 'home',
        ])

        <div class="space-y-4 rounded-3xl border border-slate-200 bg-white/90 px-5 py-4 shadow-md">
            <h2 class="text-lg font-semibold text-slate-900">Collateral information</h2>

            <div>
                <label for="collateral_type_id" class="block text-sm font-medium text-slate-800">Collateral type <span class="text-red-500">*</span></label>
                <select id="collateral_type_id" name="collateral_type_id" required
                    class="mt-2 w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 focus:border-blue-500 focus:ring-blue-500/25">
                    <option value="">Select collateral type</option>
                    @foreach($collateralTypes as $type)
                        <option value="{{ $type->id }}" @selected((int) old('collateral_type_id') === $type->id)>{{ $type->name }}</option>
                    @endforeach
                </select>
                @error('collateral_type_id')<p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
                @if($collateralTypes->isEmpty())
                    <p class="mt-2 text-xs text-amber-700">No collateral types are configured yet. Please contact support.</p>
                @endif
            </div>

            <div>
                <label for="estimated_collateral_value" class="block text-sm font-medium text-slate-800">Estimated collateral value <span class="text-red-500">*</span></label>
                <input id="estimated_collateral_value" name="estimated_collateral_value" type="number" step="0.01" min="1" value="{{ old('estimated_collateral_value') }}" required
                    class="mt-2 w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 focus:border-blue-500 focus:ring-blue-500/25">
                @error('estimated_collateral_value')<p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="collateral_description" class="block text-sm font-medium text-slate-800">Collateral description <span class="text-red-500">*</span></label>
                <textarea id="collateral_description" name="collateral_description" rows="3" required
                    class="mt-2 w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 focus:border-blue-500 focus:ring-blue-500/25"
                    placeholder="Describe the collateral (e.g. vehicle make/model, property location)">{{ old('collateral_description') }}</textarea>
                @error('collateral_description')<p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
            </div>
        </div>

        @include('customer.registration.partials.kyc-uploads')

        <p class="text-xs text-slate-500">
            By submitting, you are sending a request only. Your account will be created after review and approval.
        </p>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('customer.register-request.create') }}" class="text-sm text-slate-600 hover:text-slate-800">Back</a>
            <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-blue-500 to-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg">
                {{ $isEditing ? 'Update request' : 'Submit registration request' }}
            </button>
        </div>
    </form>
@endsection

@push('scripts')
    @include('partials.province-district-cascade')
@endpush
