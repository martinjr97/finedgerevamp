@extends('layouts.admin')

@section('title', 'New PMEC Submission | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        <div class="space-y-2 text-left">
            <p class="text-xs uppercase tracking-[0.4em] text-cyan-300">Loan Management</p>
            <h1 class="text-3xl font-bold">New PMEC Submission</h1>
            <p class="text-sm text-slate-400">Generate a payroll deduction file for government loans submitted to PMEC.</p>
        </div>

        <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
            <form action="{{ route('admin.pmec-submissions.preview') }}" method="POST" class="space-y-6">
                @csrf

                <div>
                    <label class="text-sm font-medium text-slate-300 mb-2 block">Government loan product <span class="text-rose-400">*</span></label>
                    <select name="loan_product_id" required class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40"
                        onchange="window.location='{{ route('admin.pmec-submissions.create') }}?loan_product_id='+this.value">
                        <option value="">Select product</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}" @selected(old('loan_product_id', $selectedProductId) == $product->id)>{{ $product->name }}</option>
                        @endforeach
                    </select>
                    @error('loan_product_id')<p class="text-sm text-rose-400 mt-1">{{ $message }}</p>@enderror
                </div>

                @if ($groups->isNotEmpty())
                    <div>
                        <label class="text-sm font-medium text-slate-300 mb-2 block">Customer groups (optional)</label>
                        <p class="text-xs text-slate-400 mb-2">Leave unchecked to include all groups for this product.</p>
                        <div class="grid gap-2 sm:grid-cols-2 max-h-48 overflow-y-auto rounded-2xl border border-white/10 p-4">
                            @foreach ($groups as $group)
                                <label class="flex items-center gap-2 text-sm text-slate-300">
                                    <input type="checkbox" name="customer_group_ids[]" value="{{ $group->id }}" class="rounded border-white/20 bg-white/10 text-cyan-500"
                                        @checked(in_array($group->id, old('customer_group_ids', [])))>
                                    {{ $group->name }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div>
                    <label class="text-sm font-medium text-slate-300 mb-2 block">Submission month <span class="text-rose-400">*</span></label>
                    <input type="month" name="submission_month" value="{{ old('submission_month', now()->format('Y-m')) }}" required
                        class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                    @error('submission_month')<p class="text-sm text-rose-400 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-300 mb-2 block">Generation mode <span class="text-rose-400">*</span></label>
                    <select name="mode" id="pmec-mode" required class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                        @foreach ($modes as $value => $label)
                            <option value="{{ $value }}" @selected(old('mode') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('mode')<p class="text-sm text-rose-400 mt-1">{{ $message }}</p>@enderror
                </div>

                <div id="manual-loans-panel" class="hidden space-y-2">
                    <label class="text-sm font-medium text-slate-300 block">Loan numbers (manual selection)</label>
                    <textarea name="manual_loan_numbers" rows="3" placeholder="Enter loan numbers separated by commas or new lines"
                        class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">{{ old('manual_loan_numbers') }}</textarea>
                    <p class="text-xs text-slate-400">You can refine selection on the preview screen.</p>
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-300 mb-2 block">Notes</label>
                    <textarea name="notes" rows="2" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">{{ old('notes') }}</textarea>
                </div>

                <div class="flex justify-end gap-3">
                    <a href="{{ route('admin.pmec-submissions.index') }}" class="inline-flex items-center rounded-2xl border border-white/20 px-4 py-3 text-slate-300 hover:bg-white/10">Cancel</a>
                    <button type="submit" class="inline-flex items-center rounded-2xl bg-gradient-to-r from-cyan-500 to-blue-600 px-4 py-3 font-semibold text-white shadow-lg">Preview loans</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modeSelect = document.getElementById('pmec-mode');
        const manualPanel = document.getElementById('manual-loans-panel');
        function toggleManual() {
            manualPanel.classList.toggle('hidden', modeSelect.value !== '{{ \App\Support\PmecSubmissionDefaults::MODE_MANUAL }}');
        }
        modeSelect.addEventListener('change', toggleManual);
        toggleManual();
    </script>
@endsection
