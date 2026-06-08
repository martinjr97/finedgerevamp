@extends('layouts.admin')

@section('title', 'Group Loan Documents | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Group Loan Application',
            'description' => 'Step 4: add optional supporting documents',
            'buttons' => [
                [
                    'action' => 'secondary',
                    'text' => 'Back to Principal Amounts',
                    'href' => route('admin.loan-applications.group-loans.principals', $loanProduct),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>'
                ]
            ]
        ])

        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <h2 class="mb-4 text-xl font-semibold text-white">Add Supporting Document</h2>
            <p class="text-sm text-slate-400 mb-6">You can upload multiple files. Allowed formats: JPG, PNG, PDF, DOC, DOCX. Maximum size: 15MB.</p>

            <form method="POST" action="{{ route('admin.loan-applications.group-loans.store-documents', $loanProduct) }}" enctype="multipart/form-data" class="grid gap-6 md:grid-cols-2">
                @csrf

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Document Name <span class="text-red-400">*</span></label>
                    <input type="text" name="document_name" value="{{ old('document_name') }}" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                    @error('document_name')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Document File <span class="text-red-400">*</span></label>
                    <input type="file" name="document_file" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 file:mr-3 file:rounded-xl file:border-0 file:bg-cyan-500 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white">
                    @error('document_file')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-300 mb-2">Description</label>
                    <textarea name="description" rows="3" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">{{ old('description') }}</textarea>
                    @error('description')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>

                <div class="md:col-span-2 flex justify-end">
                    <button type="submit" class="inline-flex items-center rounded-2xl bg-cyan-500 px-4 py-3 text-sm font-semibold text-white hover:bg-cyan-600 transition">Add Document</button>
                </div>
            </form>
        </div>

        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <h2 class="mb-4 text-xl font-semibold text-white">Uploaded Documents</h2>
            @if (empty($documents))
                <p class="text-sm text-slate-400">No supporting documents uploaded yet.</p>
            @else
                <div class="space-y-3">
                    @foreach ($documents as $index => $document)
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                            <p class="font-semibold text-white">{{ data_get($document, 'document_name', 'Document #'.($index + 1)) }}</p>
                            <p class="text-xs text-slate-400 break-all">{{ data_get($document, 'file_path') }}</p>
                            @if (data_get($document, 'description'))
                                <p class="text-sm text-slate-300 mt-2">{{ data_get($document, 'description') }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <form method="POST" action="{{ route('admin.loan-applications.group-loans.store-documents', $loanProduct) }}" class="flex justify-end gap-3">
            @csrf
            <input type="hidden" name="action" value="continue">
            <a href="{{ route('admin.loan-applications.group-loans.principals', $loanProduct) }}" class="inline-flex items-center rounded-2xl border border-white/20 px-4 py-3 text-sm font-medium text-slate-300 hover:bg-white/10 transition">Back</a>
            <button type="submit" class="inline-flex items-center rounded-2xl bg-cyan-500 px-4 py-3 text-sm font-semibold text-white hover:bg-cyan-600 transition">Continue to Review</button>
        </form>
    </div>
@endsection
