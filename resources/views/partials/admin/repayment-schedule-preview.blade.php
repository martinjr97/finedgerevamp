@php
    $scheduleRows = $repaymentSchedule ?? [];
    $showBreakdown = $showBreakdown ?? false;
@endphp

<div class="rounded-3xl border border-white/10 bg-white/5 shadow-lg overflow-hidden">
    <div class="px-5 py-4 border-b border-white/10 flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-xl font-semibold text-white">Repayment Schedule</h2>
        <span class="text-xs uppercase tracking-wide text-slate-400">{{ count($scheduleRows) }} installments</span>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm text-slate-300">
            <thead class="bg-white/[0.03] text-xs uppercase tracking-[0.2em] text-slate-400">
                <tr>
                    <th class="px-4 py-3 text-left">Installment #</th>
                    <th class="px-4 py-3 text-left">Due Date</th>
                    <th class="px-4 py-3 text-left">Amount Due</th>
                    @if ($showBreakdown)
                        <th class="px-4 py-3 text-left">Principal</th>
                        <th class="px-4 py-3 text-left">Interest</th>
                        <th class="px-4 py-3 text-left">Fee</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($scheduleRows as $scheduleItem)
                    @php
                        $dueDate = $scheduleItem['due_date'] ?? null;
                        $dueDateLabel = $dueDate instanceof \Carbon\CarbonInterface
                            ? $dueDate->format('d M Y')
                            : ($dueDate ? \Carbon\Carbon::parse($dueDate)->format('d M Y') : '—');
                    @endphp
                    <tr class="border-t border-white/5">
                        <td class="px-4 py-3">{{ $scheduleItem['period_number'] ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $dueDateLabel }}</td>
                        <td class="px-4 py-3 font-medium text-white">
                            ZMW {{ number_format((float) ($scheduleItem['expected_amount'] ?? 0), 2) }}
                        </td>
                        @if ($showBreakdown)
                            <td class="px-4 py-3">ZMW {{ number_format((float) ($scheduleItem['principal_component'] ?? 0), 2) }}</td>
                            <td class="px-4 py-3">ZMW {{ number_format((float) ($scheduleItem['interest_component'] ?? 0), 2) }}</td>
                            <td class="px-4 py-3">ZMW {{ number_format((float) ($scheduleItem['fee_component'] ?? 0), 2) }}</td>
                        @endif
                    </tr>
                @empty
                    <tr class="border-t border-white/5">
                        <td colspan="{{ $showBreakdown ? 6 : 3 }}" class="px-4 py-4 text-slate-400">
                            Repayment schedule is not available. Go back to loan details and recalculate.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
