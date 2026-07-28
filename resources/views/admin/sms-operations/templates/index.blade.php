@extends('layouts.admin')

@section('title', 'SMS Templates | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'SMS Templates',
            'description' => 'Transactional SMS message templates. Keep messages within GSM single-segment limits (typically 159 characters).',
            'buttons' => [
                ['text' => 'Back to SMS Operations', 'href' => route('admin.sms-operations.index'), 'action' => 'secondary'],
            ],
        ])

        @if(session('status'))
            <div class="rounded-2xl border border-emerald-400/40 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">
                {{ session('status') }}
            </div>
        @endif

        <div class="rounded-3xl border border-white/10 bg-white/5 shadow-lg overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-white/5 text-left text-slate-400">
                    <tr>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Key</th>
                        <th class="px-4 py-3">Category</th>
                        <th class="px-4 py-3">Length</th>
                        <th class="px-4 py-3">Status</th>
                        @can('sms-operations.manage')
                            <th class="px-4 py-3"></th>
                        @endcan
                    </tr>
                </thead>
                <tbody class="text-slate-200 divide-y divide-white/5">
                    @foreach($templates as $template)
                        <tr>
                            <td class="px-4 py-3 font-medium">{{ $template->name }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-400">{{ $template->key }}</td>
                            <td class="px-4 py-3">{{ $template->category->value }}</td>
                            <td class="px-4 py-3">{{ mb_strlen($template->body) }} / {{ $template->max_length }}</td>
                            <td class="px-4 py-3">
                                <span class="{{ $template->is_active ? 'text-emerald-300' : 'text-amber-300' }}">
                                    {{ $template->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            @can('sms-operations.manage')
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.sms-templates.edit', $template) }}" class="text-cyan-300 hover:text-cyan-200">Edit</a>
                                </td>
                            @endcan
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
