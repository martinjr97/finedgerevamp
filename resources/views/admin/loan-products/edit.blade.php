@extends('layouts.admin')

@section('title', 'Edit Loan Product | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        <div class="space-y-2 text-left">
            <p class="text-xs uppercase tracking-[0.4em] text-cyan-300">Configurations</p>
            <h1 class="text-3xl font-bold">Edit Loan Product: {{ $product->name }}</h1>
        </div>

        @include('admin.loan-products.form')
    </div>
@endsection

