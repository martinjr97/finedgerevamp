<template x-teleport="body">
    <div
        x-show="open"
        x-transition.opacity
        class="fixed inset-0 z-[80] flex items-center justify-center p-4"
        style="display: none;"
        @keydown.escape.window="close()"
    >
        <div class="absolute inset-0 bg-slate-900/60" @click="close()"></div>

        <div class="relative z-10 flex max-h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
            <div class="border-b border-slate-200 px-6 py-4">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-primary">Map legacy user to existing customer</p>
                        <h3 class="mt-1 text-lg font-semibold text-slate-900">
                            Legacy User <span x-text="legacyUserId"></span>
                            <span class="text-slate-400">→</span>
                            <span x-text="candidate ? ('#' + candidate.id + ' — ' + candidate.full_name) : ''"></span>
                        </h3>
                        <p class="mt-2 text-sm text-slate-600">
                            Review fields below. Check <strong>Use legacy</strong> to copy a legacy value into the final customer record, or edit the final value directly.
                            Mapping creates the entity link loan migration needs.
                        </p>
                    </div>
                    <button type="button" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700" @click="close()">
                        <span class="sr-only">Close</span>
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>

            <form method="POST" :action="mapUrl" class="flex min-h-0 flex-1 flex-col">
                @csrf
                <input type="hidden" name="target_customer_id" :value="candidate ? candidate.id : ''">
                <template x-if="runId">
                    <input type="hidden" name="run_id" :value="runId">
                </template>
                <template x-if="statusFilter">
                    <input type="hidden" name="status" :value="statusFilter">
                </template>

                <div class="min-h-0 flex-1 overflow-y-auto px-6 py-4">
                    <template x-if="candidate">
                        <div class="mb-4 grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm sm:grid-cols-2">
                            <div>
                                <p class="text-xs uppercase tracking-wide text-slate-500">Legacy person</p>
                                <p class="mt-1 font-semibold text-slate-900" x-text="legacyName"></p>
                            </div>
                            <div>
                                <p class="text-xs uppercase tracking-wide text-slate-500">Target customer</p>
                                <p class="mt-1 font-semibold text-slate-900" x-text="'#' + candidate.id + ' — ' + candidate.full_name"></p>
                                <p class="text-xs text-slate-500" x-text="candidate.company || 'No company'"></p>
                            </div>
                        </div>
                    </template>

                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b bg-slate-100 text-left text-xs uppercase tracking-wide text-slate-700">
                                    <th class="px-3 py-2">Field</th>
                                    <th class="px-3 py-2">Legacy value</th>
                                    <th class="px-3 py-2">Current revamp</th>
                                    <th class="px-3 py-2">Use legacy</th>
                                    <th class="px-3 py-2">Final value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="row in (candidate ? candidate.map_fields : [])" :key="row.key">
                                    <tr class="border-b align-top" :class="row.differs ? 'bg-amber-50/60' : ''">
                                        <td class="px-3 py-3 font-medium text-slate-800">
                                            <span x-text="row.label"></span>
                                            <span x-show="row.differs" class="ml-1 rounded bg-amber-200 px-1.5 py-0.5 text-[10px] font-bold uppercase text-amber-900">differs</span>
                                        </td>
                                        <td class="px-3 py-3 text-slate-600">
                                            <span x-text="row.legacy || '—'" class="break-all"></span>
                                        </td>
                                        <td class="px-3 py-3 text-slate-600">
                                            <span x-text="row.current || '—'" class="break-all"></span>
                                        </td>
                                        <td class="px-3 py-3">
                                            <label class="inline-flex items-center gap-2 text-xs text-slate-700">
                                                <input
                                                    type="checkbox"
                                                    class="rounded border-slate-300 text-primary focus:ring-primary"
                                                    :checked="useLegacy[row.key]"
                                                    :disabled="!row.legacy_has_value"
                                                    @change="toggleLegacy(row.key, $event.target.checked)"
                                                >
                                                <span x-show="row.legacy_has_value">Apply</span>
                                                <span x-show="!row.legacy_has_value" class="text-slate-400">N/A</span>
                                            </label>
                                        </td>
                                        <td class="px-3 py-3 min-w-[12rem]">
                                            <input
                                                type="text"
                                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-primary focus:ring-primary"
                                                :name="'fields[' + row.key + ']'"
                                                x-model="fields[row.key]"
                                            >
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        <label class="text-sm font-medium text-slate-700">Notes (optional)</label>
                        <textarea name="reason" rows="2" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="Why this mapping is correct, e.g. same person confirmed by payroll"></textarea>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-3 border-t border-slate-200 px-6 py-4">
                    <button type="button" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" @click="close()">
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:opacity-90 transition"
                        @click="return confirm('Map legacy user ' + legacyUserId + ' to customer #' + (candidate ? candidate.id : '') + ' with the field values shown?')"
                    >
                        Confirm map
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

@push('scripts')
    <script>
        function customerMapModal(config) {
            return {
                open: false,
                candidate: null,
                candidates: config.candidates || [],
                legacyUserId: config.legacyUserId,
                legacyName: config.legacyName,
                mapUrl: config.mapUrl,
                runId: config.runId || null,
                statusFilter: config.statusFilter || null,
                fields: {},
                useLegacy: {},
                openCandidate(candidateId) {
                    this.candidate = this.candidates.find((item) => item.id === candidateId) || null;
                    if (!this.candidate) {
                        return;
                    }

                    this.fields = {};
                    this.useLegacy = {};

                    for (const row of this.candidate.map_fields) {
                        this.fields[row.key] = row.final || '';
                        this.useLegacy[row.key] = !!row.suggest_legacy;
                    }

                    this.open = true;
                    document.body.classList.add('overflow-hidden');
                },
                close() {
                    this.open = false;
                    document.body.classList.remove('overflow-hidden');
                },
                toggleLegacy(key, checked) {
                    this.useLegacy[key] = checked;
                    const row = (this.candidate?.map_fields || []).find((item) => item.key === key);
                    if (!row) {
                        return;
                    }

                    this.fields[key] = checked ? (row.legacy || '') : (row.current || '');
                },
            };
        }
    </script>
@endpush
