@can('loan-products.update')
    <div
        x-show="openPublicWebsiteModal"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
        x-on:keydown.escape.window="openPublicWebsiteModal = false"
    >
        <div
            class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-3xl border border-sky-500/30 bg-slate-900 p-6 shadow-2xl"
            x-on:click.outside="openPublicWebsiteModal = false"
        >
            <div class="mb-6 flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold text-white">Public marketing website</h2>
                    <p class="mt-1 text-sm text-slate-400">
                        Control whether <strong class="text-slate-200">{{ $product->name }}</strong> appears on the
                        Finedge marketing site and which rate rows power the calculator.
                    </p>
                </div>
                <button
                    type="button"
                    class="rounded-lg p-2 text-slate-400 hover:bg-white/10 hover:text-white"
                    x-on:click="openPublicWebsiteModal = false"
                >
                    <span class="sr-only">Close</span>
                    ✕
                </button>
            </div>

            <form
                method="POST"
                action="{{ route('admin.loan-products.public-website.update', $product) }}"
                class="space-y-6"
            >
                @csrf
                @method('PUT')

                <label class="flex items-start gap-3 rounded-2xl border border-white/10 bg-white/5 p-4">
                    <input
                        type="checkbox"
                        name="is_public_on_website"
                        value="1"
                        class="mt-1 rounded border-white/20"
                        x-model="websitePublic"
                        @checked(old('is_public_on_website', $product->is_public_on_website))
                    />
                    <span>
                        <span class="block text-sm font-semibold text-white">Show this loan product on the public website</span>
                        <span class="mt-1 block text-xs text-slate-400">Visitors can select it on loan options and the repayment calculator.</span>
                    </span>
                </label>

                <div x-show="websitePublic" x-cloak class="space-y-4">
                    <div>
                        <label class="text-sm font-medium text-slate-200">Product type (rate type)</label>
                        <select
                            name="public_website_loan_rate_type_id"
                            class="mt-2 w-full rounded-2xl border border-white/10 bg-slate-950 px-4 py-3 text-white"
                            x-model="selectedRateTypeId"
                        >
                            <option value="">Select rate type…</option>
                            <template x-for="type in rateTypes" :key="type.id">
                                <option :value="type.id" x-text="`${type.name} (${type.code})`"></option>
                            </template>
                        </select>
                        @error('public_website_loan_rate_type_id')
                            <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div x-show="selectedRateTypeId" x-cloak>
                        <p class="text-sm font-medium text-slate-200">Rates used for calculator quotes</p>
                        <p class="mt-1 text-xs text-slate-400">Only selected rows are offered as terms on the website.</p>
                        <div class="mt-3 max-h-64 space-y-2 overflow-y-auto rounded-2xl border border-white/10 p-3">
                            <template x-for="rate in activeRates" :key="rate.id">
                                <label class="flex items-start gap-3 rounded-xl bg-white/5 p-3 text-sm text-slate-200">
                                    <input
                                        type="checkbox"
                                        name="public_website_loan_rate_ids[]"
                                        :value="rate.id"
                                        class="mt-1 rounded border-white/20"
                                        x-model="selectedRateIds"
                                    />
                                    <span>
                                        <span class="font-semibold text-white" x-text="`${rate.tenure_months} months`"></span>
                                        <span class="mt-1 block text-xs text-slate-400">
                                            <span x-show="rate.term_interest_percentage !== null" x-text="`Term interest: ${rate.term_interest_percentage}% · `"></span>
                                            <span x-text="`Processing fee: ${rate.processing_fee_percentage}%`"></span>
                                            <span x-show="rate.min_principal !== null || rate.max_principal !== null"
                                                x-text="` · Principal band: ${rate.min_principal ?? '—'} – ${rate.max_principal ?? '—'}`"></span>
                                        </span>
                                    </span>
                                </label>
                            </template>
                            <p x-show="activeRates.length === 0" class="text-sm text-amber-300">No active rate rows for this type.</p>
                        </div>
                        @error('public_website_loan_rate_ids')
                            <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="flex justify-end gap-3 border-t border-white/10 pt-4">
                    <button
                        type="button"
                        class="rounded-2xl border border-white/10 px-4 py-2 text-sm font-semibold text-slate-200 hover:bg-white/10"
                        x-on:click="openPublicWebsiteModal = false"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="rounded-2xl bg-gradient-to-r from-sky-500 to-cyan-400 px-4 py-2 text-sm font-semibold text-slate-900"
                    >
                        Save website settings
                    </button>
                </div>
            </form>
        </div>
    </div>

@endcan
