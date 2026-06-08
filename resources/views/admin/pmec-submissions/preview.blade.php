@extends('layouts.admin')

@section('title', 'Preview PMEC Submission | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        <div class="space-y-2 text-left">
            <p class="text-xs uppercase tracking-[0.4em] text-cyan-300">PMEC</p>
            <h1 class="text-3xl font-bold">Preview submission</h1>
            <p class="text-sm text-slate-400">{{ $product->name }} · {{ $form['submission_month'] }} · {{ $modes[$form['mode']] ?? $form['mode'] }}</p>
        </div>

        @if (session('warning'))
            <div class="rounded-2xl border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-amber-200">{{ session('warning') }}</div>
        @endif

        <form action="{{ route('admin.pmec-submissions.generate') }}" method="POST" class="space-y-6">
            @csrf
            <input type="hidden" name="loan_product_id" value="{{ $form['loan_product_id'] }}">
            <input type="hidden" name="submission_month" value="{{ $form['submission_month'] }}">
            <input type="hidden" name="mode" value="{{ $form['mode'] }}">
            @if (!empty($form['notes']))
                <input type="hidden" name="notes" value="{{ $form['notes'] }}">
            @endif
            @foreach ($form['customer_group_ids'] ?? [] as $groupId)
                <input type="hidden" name="customer_group_ids[]" value="{{ $groupId }}">
            @endforeach

            <div class="rounded-3xl border border-white/10 bg-white/5 overflow-hidden shadow-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm text-left">
                        <thead class="bg-white/5 text-slate-400 uppercase text-xs">
                            <tr>
                                <th class="px-3 py-3 w-10">
                                    <input type="checkbox" id="select-all-loans" checked class="rounded border-white/20 bg-white/10 text-cyan-500">
                                </th>
                                <th class="px-3 py-3">Customer</th>
                                <th class="px-3 py-3">PERNR</th>
                                <th class="px-3 py-3">NRC</th>
                                <th class="px-3 py-3">Loan</th>
                                <th class="px-3 py-3">Group</th>
                                <th class="px-3 py-3">BEGDA</th>
                                <th class="px-3 py-3">ENDDA</th>
                                <th class="px-3 py-3">BETRG</th>
                                <th class="px-3 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/10">
                            @foreach ($rows as $row)
                                <tr class="{{ $row['is_valid'] ? 'hover:bg-white/5' : 'bg-rose-500/10' }}">
                                    <td class="px-3 py-3">
                                        <input type="checkbox" name="loan_ids[]" value="{{ $row['loan_id'] }}" class="loan-checkbox rounded border-white/20 bg-white/10 text-cyan-500"
                                            @checked($row['is_valid']) @disabled(! $row['is_valid'])>
                                    </td>
                                    <td class="px-3 py-3 text-white">{{ $row['customer_name'] }}</td>
                                    <td class="px-3 py-3 text-slate-300">{{ $row['employee_number'] ?: '—' }}</td>
                                    <td class="px-3 py-3 text-slate-300">{{ $row['nrc'] ?: '—' }}</td>
                                    <td class="px-3 py-3 text-slate-300">{{ $row['loan_number'] }}</td>
                                    <td class="px-3 py-3 text-slate-400">{{ $row['group_name'] ?? '—' }}</td>
                                    <td class="px-3 py-3 text-slate-300">{{ $row['begda'] ?? '—' }}</td>
                                    <td class="px-3 py-3 text-slate-300">{{ $row['endda'] ?? '—' }}</td>
                                    <td class="px-3 py-3 text-slate-300">{{ $row['betrg'] !== null ? number_format($row['betrg'], 2) : '—' }}</td>
                                    <td class="px-3 py-3">
                                        <span class="block text-xs text-slate-400">{{ $row['submission_status'] }}</span>
                                        @if (! $row['is_valid'])
                                            <ul class="mt-1 text-xs text-rose-300 list-disc list-inside">
                                                @foreach ($row['validation_errors'] as $error)
                                                    <li>{{ $error }}</li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @error('export')<p class="text-sm text-rose-400">{{ $message }}</p>@enderror
            @error('loan_ids')<p class="text-sm text-rose-400">{{ $message }}</p>@enderror

            <div class="rounded-2xl border border-white/10 bg-white/5 p-4 space-y-3">
                <label class="flex items-start gap-3 text-sm text-slate-300">
                    <input type="checkbox" name="exclude_invalid" value="1" class="mt-1 rounded border-white/20 bg-white/10 text-cyan-500">
                    <span>Exclude rows with missing required fields and generate file for valid loans only.</span>
                </label>
                <p class="text-xs text-slate-400">If unchecked, export is blocked when any selected loan has validation errors.</p>
            </div>

            <div class="flex justify-between gap-3">
                <a href="{{ route('admin.pmec-submissions.create') }}" class="inline-flex items-center rounded-2xl border border-white/20 px-4 py-3 text-slate-300 hover:bg-white/10">Back</a>
                @can('pmec_submissions.export')
                    <button type="submit" class="inline-flex items-center rounded-2xl bg-gradient-to-r from-emerald-500 to-cyan-600 px-4 py-3 font-semibold text-white shadow-lg">Generate Excel file</button>
                @endcan
            </div>
        </form>
    </div>

    <script>
        document.getElementById('select-all-loans')?.addEventListener('change', function () {
            document.querySelectorAll('.loan-checkbox:not(:disabled)').forEach(cb => { cb.checked = this.checked; });
        });
    </script>
@endsection
