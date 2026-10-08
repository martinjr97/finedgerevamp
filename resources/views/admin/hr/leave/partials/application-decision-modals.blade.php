{{-- Requires Alpine: selectedApplication, approveModalOpen, rejectModalOpen, approveComments, rejectComments --}}
<div
    x-show="approveModalOpen"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
    role="dialog"
    aria-modal="true"
    aria-labelledby="approveLeaveModalTitle"
    @keydown.escape.window="approveModalOpen = false"
>
    <div class="w-full max-w-lg rounded-3xl border border-white/10 bg-slate-900 p-6 shadow-2xl" @click.outside="approveModalOpen = false">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h3 id="approveLeaveModalTitle" class="text-lg font-semibold text-white">Approve leave</h3>
                <p class="mt-1 text-sm text-slate-400">Confirm approval and optionally add a note for the record.</p>
            </div>
            <button type="button" @click="approveModalOpen = false" class="shrink-0 rounded-lg border border-white/10 px-2 py-1 text-slate-400 hover:text-white" aria-label="Close">✕</button>
        </div>

        <template x-if="selectedApplication">
            <div class="mt-4 space-y-4">
                <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-slate-300 space-y-1">
                    <p><span class="text-slate-500">Employee:</span> <span class="font-medium text-white" x-text="selectedApplication.employee"></span></p>
                    <p><span class="text-slate-500">Leave:</span> <span x-text="selectedApplication.leaveType"></span> · <span x-text="selectedApplication.days"></span> day(s)</p>
                    <p><span class="text-slate-500">Period:</span> <span x-text="selectedApplication.period"></span></p>
                    <p x-show="selectedApplication.available !== null">
                        <span class="text-slate-500">Available balance:</span>
                        <span class="font-medium text-emerald-300" x-text="selectedApplication.available"></span>
                    </p>
                </div>

                <form method="POST" :action="selectedApplication.approveUrl">
                    @csrf
                    <label for="approve_comments_modal" class="text-sm font-medium text-slate-200">Approval comments</label>
                    <textarea
                        id="approve_comments_modal"
                        name="comments"
                        rows="3"
                        x-model="approveComments"
                        placeholder="Optional note for the employee file"
                        class="mt-2 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-white focus:border-cyan-400 focus:ring-cyan-400/40"
                    ></textarea>
                    <div class="mt-4 flex flex-wrap justify-end gap-2 border-t border-white/10 pt-4">
                        <button type="button" @click="approveModalOpen = false" class="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-medium text-slate-300 hover:bg-white/10">Cancel</button>
                        <button type="submit" class="btn-primary rounded-xl px-5 py-2.5 text-sm font-semibold">Confirm approval</button>
                    </div>
                </form>
            </div>
        </template>
    </div>
</div>

<div
    x-show="rejectModalOpen"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
    role="dialog"
    aria-modal="true"
    aria-labelledby="rejectLeaveModalTitle"
    @keydown.escape.window="rejectModalOpen = false"
>
    <div class="w-full max-w-lg rounded-3xl border border-white/10 bg-slate-900 p-6 shadow-2xl" @click.outside="rejectModalOpen = false">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h3 id="rejectLeaveModalTitle" class="text-lg font-semibold text-white">Reject leave</h3>
                <p class="mt-1 text-sm text-slate-400">The application will be closed. Add a reason if helpful.</p>
            </div>
            <button type="button" @click="rejectModalOpen = false" class="shrink-0 rounded-lg border border-white/10 px-2 py-1 text-slate-400 hover:text-white" aria-label="Close">✕</button>
        </div>

        <template x-if="selectedApplication">
            <div class="mt-4 space-y-4">
                <div class="rounded-2xl border border-rose-400/30 bg-rose-500/5 px-4 py-3 text-sm text-slate-300 space-y-1">
                    <p><span class="text-slate-500">Employee:</span> <span class="font-medium text-white" x-text="selectedApplication.employee"></span></p>
                    <p><span class="text-slate-500">Leave:</span> <span x-text="selectedApplication.leaveType"></span> · <span x-text="selectedApplication.days"></span> day(s)</p>
                    <p><span class="text-slate-500">Period:</span> <span x-text="selectedApplication.period"></span></p>
                </div>

                <form method="POST" :action="selectedApplication.rejectUrl">
                    @csrf
                    <label for="reject_comments_modal" class="text-sm font-medium text-slate-200">Rejection reason</label>
                    <textarea
                        id="reject_comments_modal"
                        name="comments"
                        rows="3"
                        x-model="rejectComments"
                        placeholder="Optional reason shown on the record"
                        class="mt-2 w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-white focus:border-cyan-400 focus:ring-cyan-400/40"
                    ></textarea>
                    <div class="mt-4 flex flex-wrap justify-end gap-2 border-t border-white/10 pt-4">
                        <button type="button" @click="rejectModalOpen = false" class="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-medium text-slate-300 hover:bg-white/10">Cancel</button>
                        <button type="submit" class="rounded-xl border border-rose-400/50 bg-rose-500/15 px-5 py-2.5 text-sm font-semibold text-rose-200 hover:bg-rose-500/25">Confirm rejection</button>
                    </div>
                </form>
            </div>
        </template>
    </div>
</div>
