@extends('layouts.admin')

@section('title', 'Upload Batch Details | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Upload Batch Details',
            'description' => 'View and manage failed customer upload records',
        ])

        {{-- Batch Summary --}}
        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <h2 class="text-xl font-semibold text-white mb-4">Batch Information</h2>
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <div>
                    <p class="text-xs text-slate-400 mb-1">File Name</p>
                    <p class="font-medium text-white">{{ $batch->file_name }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-400 mb-1">Product</p>
                    <p class="font-medium text-white">{{ $batch->loanProduct->name }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-400 mb-1">Uploaded By</p>
                    <p class="font-medium text-white">{{ $batch->uploadedBy->full_name }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-400 mb-1">Uploaded At</p>
                    <p class="font-medium text-white">{{ $batch->created_at->format('Y-m-d H:i:s') }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-400 mb-1">Total Records</p>
                    <p class="font-medium text-white">{{ number_format($batch->total_records) }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-400 mb-1">Successful</p>
                    <p class="font-medium text-emerald-400">{{ number_format($batch->successful_records) }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-400 mb-1">Failed</p>
                    <p class="font-medium text-rose-400">{{ number_format($batch->failed_records) }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-400 mb-1">Status</p>
                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $batch->status === 'completed' ? 'bg-emerald-500/20 text-emerald-300' : ($batch->status === 'failed' ? 'bg-rose-500/20 text-rose-300' : 'bg-amber-500/20 text-amber-300') }}">
                        {{ ucfirst($batch->status) }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Failed Records Table --}}
        @if($failedRecords->isEmpty())
            <div class="rounded-3xl border border-emerald-500/50 bg-emerald-500/10 p-6 shadow-lg text-center">
                <svg class="w-16 h-16 text-emerald-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                <p class="text-emerald-300 text-lg font-semibold">No failed records!</p>
                <p class="text-emerald-200 text-sm mt-2">All records were processed successfully.</p>
            </div>
        @else
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                <h2 class="text-xl font-semibold text-white mb-4">Failed Records</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full w-full text-sm text-slate-300">
                        <thead>
                            <tr class="text-xs font-semibold uppercase tracking-[0.25em] text-white/80 text-left border-b border-white/10">
                                <th class="px-4 py-3">Row #</th>
                                <th class="px-4 py-3">Customer Data</th>
                                <th class="px-4 py-3">Error Message</th>
                                <th class="px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($failedRecords as $record)
                                <tr class="border-t border-white/10 hover:bg-white/5 transition {{ $record->isDiscarded() ? 'opacity-60' : '' }}">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono text-slate-400">{{ $record->row_number }}</span>
                                            @if($record->isDiscarded())
                                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold bg-slate-500/20 text-slate-400 border border-slate-500/50">
                                                    Discarded
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="space-y-1">
                                            <div class="font-medium {{ $record->isDiscarded() ? 'text-slate-500' : 'text-white' }}">
                                                {{ $record->data['first name'] ?? $record->data['first_name'] ?? 'N/A' }} 
                                                {{ $record->data['last name'] ?? $record->data['last_name'] ?? '' }}
                                            </div>
                                            <div class="text-xs {{ $record->isDiscarded() ? 'text-slate-500' : 'text-slate-400' }}">
                                                {{ $record->data['email'] ?? 'N/A' }}
                                            </div>
                                            @if(isset($record->data['phone']))
                                                <div class="text-xs {{ $record->isDiscarded() ? 'text-slate-500' : 'text-slate-400' }}">
                                                    {{ $record->data['phone'] }}
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="max-w-md">
                                            <p class="text-xs {{ $record->isDiscarded() ? 'text-slate-500' : 'text-rose-300' }}">{{ $record->error_message }}</p>
                                            @if($record->isDiscarded() && $record->discardedBy)
                                                <p class="text-xs text-slate-500 mt-1">
                                                    Discarded by {{ $record->discardedBy->full_name }} on {{ $record->discarded_at->format('Y-m-d H:i') }}
                                                </p>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($record->isDiscarded())
                                            <span class="text-xs text-slate-500 italic">No actions available</span>
                                        @else
                                            <div class="flex items-center gap-2">
                                                <a href="{{ route('admin.customers.upload-record.edit', $record) }}" 
                                                   class="inline-flex items-center gap-1.5 rounded-xl bg-blue-500/20 border border-blue-500/50 px-3 py-1.5 text-xs font-semibold text-blue-300 hover:bg-blue-500/30 transition"
                                                >
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                    </svg>
                                                    Edit
                                                </a>
                                                <form method="POST" action="{{ route('admin.customers.upload-record.retry', $record) }}" class="inline">
                                                    @csrf
                                                    <button 
                                                        type="submit"
                                                        class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-500/20 border border-emerald-500/50 px-3 py-1.5 text-xs font-semibold text-emerald-300 hover:bg-emerald-500/30 transition"
                                                    >
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                                        </svg>
                                                        Retry
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.customers.upload-record.discard', $record) }}" class="inline" 
                                                      onsubmit="return confirm('Are you sure you want to discard this record? It will no longer be editable or retriable, but the error message will be preserved for audit purposes.');">
                                                    @csrf
                                                    <button 
                                                        type="submit"
                                                        class="inline-flex items-center gap-1.5 rounded-xl bg-slate-500/20 border border-slate-500/50 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-slate-500/30 transition"
                                                    >
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                        </svg>
                                                        Discard
                                                    </button>
                                                </form>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-4">
                    {{ $failedRecords->links() }}
                </div>
            </div>
        @endif
    </div>
@endsection

