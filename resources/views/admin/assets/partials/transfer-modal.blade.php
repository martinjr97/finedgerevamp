@php
    $transferAssets = ($assets ?? collect())->map(function ($asset) {
        return [
            'id' => $asset->id,
            'name' => $asset->name,
            'transferUrl' => route('admin.assets.transfer', $asset),
            'ownerId' => $asset->employee_id,
            'ownerName' => $asset->employee?->full_name ?? 'Unassigned',
        ];
    })->values();

    $openTransferAssetId = session('open_asset_transfer_id');
    $shouldOpenTransferModal = $openTransferAssetId !== null;
    $transferSuccessMessage = session('asset_transfer_success');
@endphp

<div id="assetTransferModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm js-close-asset-transfer-modal"></div>
    <div class="relative z-10 flex min-h-full w-full items-center justify-center p-4">
        <div class="w-full max-w-lg rounded-3xl border border-white/10 bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 p-6 shadow-2xl">
            <div class="flex items-start justify-between gap-4 mb-4">
                <div>
                    <h3 class="text-xl font-semibold text-white">Transfer Asset</h3>
                    <p class="text-sm text-slate-400 mt-1">Reassign ownership to another employee. The change will be recorded in the transfer history.</p>
                </div>
                <button type="button" class="text-slate-400 hover:text-white js-close-asset-transfer-modal" aria-label="Close">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <form id="assetTransferForm" method="POST" action="#" class="space-y-4">
                @csrf

                <div class="rounded-2xl border border-cyan-500/20 bg-cyan-500/5 px-4 py-3 text-sm">
                    <p class="text-slate-400">Asset</p>
                    <p id="assetTransferAssetName" class="font-semibold text-white mt-1">—</p>
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-300">Current Owner</label>
                    <p id="assetTransferCurrentOwner" class="mt-2 rounded-2xl bg-white/5 border border-white/10 px-4 py-3 text-white">—</p>
                </div>

                <div>
                    <label for="assetTransferEmployeeSelect" class="text-sm font-medium text-slate-300">Transfer To <span class="text-rose-400">*</span></label>
                    <select name="to_employee_id" id="assetTransferEmployeeSelect" required
                            class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                        <option value="">Select employee…</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}" @selected(old('to_employee_id') == $employee->id)>
                                {{ $employee->full_name }}@if($employee->employee_number) ({{ $employee->employee_number }})@endif
                            </option>
                        @endforeach
                    </select>
                    @error('to_employee_id')
                        <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
                    @enderror
                    @if ($employees->isEmpty())
                        <p class="mt-1 text-xs text-amber-300">No active employees available. <a href="{{ route('admin.employees.create') }}" class="text-cyan-400 hover:text-cyan-300">Add an employee</a> first.</p>
                    @endif
                </div>

                <div>
                    <label for="assetTransferReason" class="text-sm font-medium text-slate-300">Reason / Notes</label>
                    <textarea name="reason" id="assetTransferReason" rows="3" placeholder="e.g. Employee relocated to another branch"
                              class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">{{ old('reason') }}</textarea>
                    @error('reason')
                        <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" class="inline-flex items-center gap-2 rounded-2xl border border-white/10 px-4 py-2 text-sm text-white js-close-asset-transfer-modal">
                        Cancel
                    </button>
                    <button type="submit" id="assetTransferSubmit" class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-cyan-500 to-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-cyan-500/30 hover:from-cyan-600 hover:to-blue-700 transition"
                            @disabled($employees->isEmpty())>
                        Transfer Asset
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('assetTransferModal');
        const form = document.getElementById('assetTransferForm');
        const assetNameEl = document.getElementById('assetTransferAssetName');
        const currentOwnerEl = document.getElementById('assetTransferCurrentOwner');
        const employeeSelect = document.getElementById('assetTransferEmployeeSelect');
        const submitButton = document.getElementById('assetTransferSubmit');
        const openButtons = document.querySelectorAll('.js-open-asset-transfer-modal');
        const closeButtons = document.querySelectorAll('.js-close-asset-transfer-modal');
        const assets = @json($transferAssets);
        const assetsById = Object.fromEntries(assets.map((asset) => [String(asset.id), asset]));

        if (!modal || !form) {
            return;
        }

        function setModalOpen(isOpen) {
            modal.classList.toggle('hidden', !isOpen);
            modal.classList.toggle('flex', isOpen);
            document.body.classList.toggle('overflow-hidden', isOpen);
        }

        function configureEmployeeOptions(ownerId) {
            Array.from(employeeSelect.options).forEach((option) => {
                if (!option.value) {
                    option.hidden = false;
                    option.disabled = false;
                    return;
                }

                const isCurrentOwner = ownerId !== null && Number(option.value) === Number(ownerId);
                option.hidden = isCurrentOwner;
                option.disabled = isCurrentOwner;

                if (isCurrentOwner && employeeSelect.value === option.value) {
                    employeeSelect.value = '';
                }
            });
        }

        function openTransferModal(assetId) {
            const asset = assetsById[String(assetId)];

            if (!asset) {
                return;
            }

            form.action = asset.transferUrl;
            assetNameEl.textContent = asset.name;
            currentOwnerEl.textContent = asset.ownerName;
            configureEmployeeOptions(asset.ownerId);

            if (submitButton) {
                submitButton.disabled = employeeSelect.options.length <= 1;
            }

            setModalOpen(true);
        }

        openButtons.forEach((button) => {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                openTransferModal(button.dataset.assetId);
            });
        });

        closeButtons.forEach((button) => {
            button.addEventListener('click', function () {
                setModalOpen(false);
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                setModalOpen(false);
            }
        });

        @if ($shouldOpenTransferModal)
            openTransferModal(@json($openTransferAssetId));
        @endif

        @if ($transferSuccessMessage)
            if (window.Swal) {
                Swal.fire({
                    icon: 'success',
                    title: 'Transfer complete',
                    text: @json($transferSuccessMessage),
                    confirmButtonColor: '#06b6d4',
                });
            }
        @endif
    });
</script>
@endpush
