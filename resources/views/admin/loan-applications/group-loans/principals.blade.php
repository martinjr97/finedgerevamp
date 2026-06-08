@extends('layouts.admin')

@section('title', 'Group Loan Principals | '.config('app.system_name'))

@section('content')
    @php
        $memberIds = collect($wizard['member_ids'] ?? [])->map(fn ($id) => (int) $id);
    @endphp

    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Group Loan Application',
            'description' => 'Step 3: capture principal amount for each selected member',
            'buttons' => [
                [
                    'action' => 'secondary',
                    'text' => 'Back to Loan Details',
                    'href' => route('admin.loan-applications.group-loans.details', $loanProduct),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>'
                ]
            ]
        ])

        <div class="rounded-3xl border border-cyan-500/30 bg-cyan-950/30 p-4 shadow-lg">
            <div class="grid gap-4 md:grid-cols-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-cyan-300 mb-1">Repayment</p>
                    <p class="text-white font-semibold">{{ ucfirst($wizard['repayment_structure'] ?? 'monthly') }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-cyan-300 mb-1">Processing Fee</p>
                    <p class="text-white font-semibold">{{ number_format((float) ($wizard['processing_fee_percentage'] ?? 0), 4) }}%</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-cyan-300 mb-1">Interest (Full Period)</p>
                    <p class="text-white font-semibold">{{ number_format((float) ($wizard['monthly_interest_rate'] ?? 0), 4) }}%</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-cyan-300 mb-1">Arrears Rate</p>
                    <p class="text-white font-semibold">{{ number_format((float) ($wizard['arrears_rate'] ?? 0), 4) }}%</p>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.loan-applications.group-loans.store-principals', $loanProduct) }}" class="space-y-6">
            @csrf

            <div class="rounded-3xl border border-emerald-500/30 bg-emerald-950/20 p-4 shadow-lg">
                <div class="grid gap-4 md:grid-cols-5">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-emerald-300 mb-1">Members With Amount</p>
                        <p id="liveMembersWithAmount" class="text-white font-semibold">0 / {{ $memberIds->count() }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-emerald-300 mb-1">Total Principal</p>
                        <p id="liveTotalPrincipal" class="text-white font-semibold">ZMW 0.00</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-emerald-300 mb-1">Est. Processing Fees</p>
                        <p id="liveTotalProcessingFee" class="text-white font-semibold">ZMW 0.00</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-emerald-300 mb-1">Est. Interest</p>
                        <p id="liveTotalInterest" class="text-white font-semibold">ZMW 0.00</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-emerald-300 mb-1">Est. Projected Repayment</p>
                        <p id="liveTotalRepayment" class="text-emerald-200 font-semibold">ZMW 0.00</p>
                    </div>
                </div>
            </div>

            <div class="rounded-3xl border border-white/10 bg-white/5 shadow-lg overflow-hidden">
                <div class="px-5 py-4 border-b border-white/10">
                    <h2 class="text-xl font-semibold text-white">Member Principal Amounts</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm text-slate-300">
                        <thead class="bg-white/[0.03] text-xs uppercase tracking-[0.2em] text-slate-400">
                            <tr>
                                <th class="px-4 py-3 text-left">Customer</th>
                                <th class="px-4 py-3 text-left">Title</th>
                                <th class="px-4 py-3 text-left">Saved Income / Revenue (ZMW)</th>
                                <th class="px-4 py-3 text-left">Principal and Member Total (ZMW)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($memberIds as $memberId)
                                @php
                                    $member = $members->get($memberId);
                                    $titleId = (int) data_get($wizard, "member_titles.$memberId");
                                    $title = $titles->get($titleId);
                                @endphp
                                <tr class="border-t border-white/5">
                                    <td class="px-4 py-3">
                                        <p class="font-semibold text-white">{{ $member?->full_name ?? 'Unknown Customer' }}</p>
                                        <p class="text-xs text-slate-400">{{ $member?->phone ?: 'No phone' }}</p>
                                    </td>
                                    <td class="px-4 py-3">{{ $title?->name ?? 'N/A' }}</td>
                                    <td class="px-4 py-3">
                                        @if ($member && $member->net_salary !== null)
                                            ZMW {{ number_format((float) $member->net_salary, 2) }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="grid gap-2 md:grid-cols-2">
                                            <div>
                                                <label class="mb-1 block text-[10px] uppercase tracking-wide text-slate-400">Principal</label>
                                                <input type="number" min="0.01" step="0.01" name="principals[{{ $memberId }}]"
                                                       value="{{ old("principals.$memberId", data_get($wizard, "principals.$memberId")) }}"
                                                       required
                                                       class="w-full rounded-xl bg-white/10 border border-white/10 text-white px-3 py-2 focus:border-cyan-400 focus:ring-cyan-400/40">
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-[10px] uppercase tracking-wide text-slate-400">Est. Member Total</label>
                                                <input type="text" id="memberTotalDisplay{{ $memberId }}" value="ZMW 0.00" readonly
                                                       class="w-full rounded-xl bg-white/5 border border-white/10 text-emerald-200 px-3 py-2 cursor-not-allowed">
                                            </div>
                                        </div>
                                        @error("principals.$memberId")
                                            <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                                        @enderror
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @error('principals')
                <p class="text-sm text-red-400">{{ $message }}</p>
            @enderror

            <div class="flex justify-end gap-3">
                <a href="{{ route('admin.loan-applications.group-loans.details', $loanProduct) }}" class="inline-flex items-center rounded-2xl border border-white/20 px-4 py-3 text-sm font-medium text-slate-300 hover:bg-white/10 transition">Back</a>
                <button type="submit" class="inline-flex items-center rounded-2xl bg-cyan-500 px-4 py-3 text-sm font-semibold text-white hover:bg-cyan-600 transition">Calculate and Continue</button>
            </div>
        </form>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const principalInputs = Array.from(document.querySelectorAll('input[name^="principals["]'));
                const memberCount = {{ (int) $memberIds->count() }};
                const processingFeePercentage = {{ (float) ($wizard['processing_fee_percentage'] ?? 0) }};
                const periodInterestRate = {{ (float) ($wizard['monthly_interest_rate'] ?? 0) }};

                const membersWithAmountEl = document.getElementById('liveMembersWithAmount');
                const totalPrincipalEl = document.getElementById('liveTotalPrincipal');
                const totalProcessingFeeEl = document.getElementById('liveTotalProcessingFee');
                const totalInterestEl = document.getElementById('liveTotalInterest');
                const totalRepaymentEl = document.getElementById('liveTotalRepayment');

                if (!principalInputs.length || !membersWithAmountEl || !totalPrincipalEl || !totalProcessingFeeEl || !totalInterestEl || !totalRepaymentEl) {
                    return;
                }

                const round2 = (value) => Math.round((value + Number.EPSILON) * 100) / 100;
                const formatZmw = (value) => `ZMW ${round2(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

                const updateLiveTotals = () => {
                    let membersWithAmount = 0;
                    let totalPrincipal = 0;
                    let totalProcessingFee = 0;
                    let totalInterest = 0;
                    let totalRepayment = 0;

                    principalInputs.forEach((input) => {
                        const principalRaw = Number.parseFloat(input.value);
                        if (!Number.isFinite(principalRaw) || principalRaw <= 0) {
                            return;
                        }

                        membersWithAmount += 1;
                        const principal = round2(principalRaw);
                        const processingFeeAmount = round2((principal * processingFeePercentage) / 100);
                        const interestAmount = round2(principal * (periodInterestRate / 100));
                        const repaymentAmount = round2(principal + processingFeeAmount + interestAmount);

                        totalPrincipal += principal;
                        totalProcessingFee += processingFeeAmount;
                        totalInterest += interestAmount;
                        totalRepayment += repaymentAmount;

                        const totalDisplayId = input.name.match(/^principals\[(\d+)\]$/)?.[1];
                        if (totalDisplayId) {
                            const rowTotalInput = document.getElementById(`memberTotalDisplay${totalDisplayId}`);
                            if (rowTotalInput) {
                                rowTotalInput.value = formatZmw(repaymentAmount);
                            }
                        }
                    });

                    principalInputs.forEach((input) => {
                        const totalDisplayId = input.name.match(/^principals\[(\d+)\]$/)?.[1];
                        if (!totalDisplayId) {
                            return;
                        }

                        const rowTotalInput = document.getElementById(`memberTotalDisplay${totalDisplayId}`);
                        if (!rowTotalInput) {
                            return;
                        }

                        const principalRaw = Number.parseFloat(input.value);
                        if (!Number.isFinite(principalRaw) || principalRaw <= 0) {
                            rowTotalInput.value = formatZmw(0);
                        }
                    });

                    totalPrincipal = round2(totalPrincipal);
                    totalProcessingFee = round2(totalProcessingFee);
                    totalInterest = round2(totalInterest);
                    totalRepayment = round2(totalRepayment);

                    membersWithAmountEl.textContent = `${membersWithAmount} / ${memberCount}`;
                    totalPrincipalEl.textContent = formatZmw(totalPrincipal);
                    totalProcessingFeeEl.textContent = formatZmw(totalProcessingFee);
                    totalInterestEl.textContent = formatZmw(totalInterest);
                    totalRepaymentEl.textContent = formatZmw(totalRepayment);
                };

                principalInputs.forEach((input) => {
                    input.addEventListener('input', updateLiveTotals);
                    input.addEventListener('change', updateLiveTotals);
                });

                updateLiveTotals();
            });
        </script>
    @endpush
@endsection
