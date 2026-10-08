@extends('layouts.admin')

@section('title', 'Add Position | HR | '.config('app.system_name'))

@section('content')
    <div class="mx-auto max-w-xl space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Add Position',
            'buttons' => [[
                'action' => 'back',
                'text' => 'Back to positions',
                'href' => route('admin.hr.positions.index'),
            ]],
        ])

        <form method="POST" action="{{ route('admin.hr.positions.store') }}" class="space-y-4 rounded-3xl border border-white/10 bg-white/5 p-6">
            @csrf
            @include('admin.hr.positions.partials.form')
            <button type="submit" class="rounded-xl bg-cyan-600 px-4 py-2 text-sm font-semibold text-white">Save position</button>
        </form>
    </div>
@endsection
