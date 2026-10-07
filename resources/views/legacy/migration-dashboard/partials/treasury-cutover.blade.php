@php
    use App\Migration\Dashboard\MigrationDashboardSupport;

    $treasury = $summary['treasury_cutover'] ?? [];
    $accounts = $treasury['accounts'] ?? [];
    $mismatched = (int) ($treasury['mismatched_count'] ?? 0);
    $financeEnabled = (bool) ($treasury['finance_on_import_enabled'] ?? false);
@endphp

<div class="mt-6 rounded-2xl border bg-white p-6 shadow-sm">
    <div class="mb-4 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold text-primary">Treasury cutover balances</h2>
            <p class="mt-1 max-w-2xl text-sm text-slate-600">
                Set each wallet and bank <strong>opening balance</strong> under Admin → Wallets / Banks to match legacy as at cutover,
                then use the button below to copy opening → <strong>current balance</strong>.
                Parallel-run import finance is controlled by <code class="rounded bg-slate-100 px-1">LEGACY_PARALLEL_RUN_FINANCE_ENABLED</code> in <code class="rounded bg-slate-100 px-1">.env</code>
                (currently <strong>{{ $financeEnabled ? 'enabled' : 'disabled' }}</strong>).
            </p>
            @if($treasury['last_cutover_at'] ?? null)
                <p class="mt-2 text-xs text-slate-500">Last cutover: {{ $treasury['last_cutover_at'] }}</p>
            @endif
        </div>

        @if($canManage ?? false)
            <form
                method="POST"
                action="{{ route('legacy.migration-dashboard.treasury.sync-current-balances') }}"
                onsubmit="return confirm('Set current balance = opening balance on all wallets and banks? Run this after you have updated opening balances to match legacy.');"
            >
                @csrf
                <button
                    type="submit"
                    class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:opacity-90"
                >
                    Set current balances from opening
                </button>
            </form>
        @else
            <p class="text-xs text-slate-500">Requires <code>migration.manage</code> permission.</p>
        @endif
    </div>

    @if($mismatched > 0)
        <div class="mb-4 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950">
            <strong>{{ $mismatched }}</strong> account(s) have current balance different from opening balance.
        </div>
    @elseif($accounts !== [])
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
            All treasury accounts match opening balance.
        </div>
    @endif

    @if($accounts === [])
        <p class="text-sm text-slate-500">No wallets or banks configured yet.</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b bg-slate-100 text-left text-xs uppercase tracking-wide text-slate-700">
                        <th class="px-3 py-2">Account</th>
                        <th class="px-3 py-2">Type</th>
                        <th class="px-3 py-2 text-right">Opening</th>
                        <th class="px-3 py-2 text-right">Current</th>
                        <th class="px-3 py-2 text-right">Drift</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($accounts as $account)
                        @php
                            $drift = (float) $account['current_balance'] - (float) $account['opening_balance'];
                            $inSync = abs($drift) < 0.01;
                        @endphp
                        <tr class="border-b hover:bg-slate-50">
                            <td class="px-3 py-2 font-medium">{{ $account['name'] }}</td>
                            <td class="px-3 py-2 capitalize">{{ $account['type'] }}</td>
                            <td class="px-3 py-2 text-right">{{ MigrationDashboardSupport::formatZmw($account['opening_balance']) }}</td>
                            <td class="px-3 py-2 text-right">{{ MigrationDashboardSupport::formatZmw($account['current_balance']) }}</td>
                            <td class="px-3 py-2 text-right {{ $inSync ? 'text-emerald-700' : 'text-amber-800 font-semibold' }}">
                                {{ $inSync ? '—' : MigrationDashboardSupport::formatZmw($drift) }}
                            </td>
                            <td class="px-3 py-2 text-right">
                                @if($account['type'] === 'wallet')
                                    <a href="{{ route('admin.wallets.edit', $account['id']) }}" class="text-xs font-semibold text-primary hover:underline">Edit opening</a>
                                @else
                                    <a href="{{ route('admin.banks.edit', $account['id']) }}" class="text-xs font-semibold text-primary hover:underline">Edit opening</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
