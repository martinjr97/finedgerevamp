@extends('layouts.admin')

@section('title', 'Financial Institutions | '.config('app.system_name'))

@section('content')
    @php
        $canUpdateInstitutions = auth('admin')->user()?->can('financial-institutions.update');
    @endphp
    <div class="space-y-6">
        @include('partials.admin.page-header', [
            'title' => 'Financial Institutions',
            'description' => 'Customer disbursement banks (not company treasury accounts)',
            'buttons' => [
                [
                    'action' => 'create',
                    'text' => 'Add Institution',
                    'href' => route('admin.financial-institutions.create'),
                    'can' => auth('admin')->user()?->can('financial-institutions.create'),
                    'icon' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>',
                ],
            ],
        ])

        @if(session('status'))
            <div class="rounded-2xl border border-emerald-400/60 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">
                {{ session('status') }}
            </div>
        @endif
        @if(session('error'))
            <div class="rounded-2xl border border-rose-400/60 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
                {{ session('error') }}
            </div>
        @endif

        <form
            id="financialInstitutionsBulkForm"
            method="POST"
            action="{{ route('admin.financial-institutions.bulk-status') }}"
            class="admin-data-table"
        >
            @csrf

            @if ($canUpdateInstitutions && $institutions->isNotEmpty())
                <div
                    class="admin-data-table__bulk admin-table-bulk-actions"
                    data-admin-table-bulk
                    hidden
                    aria-hidden="true"
                >
                    <label for="bulk_action" class="sr-only">Bulk action</label>
                    <select
                        id="bulk_action"
                        name="action"
                        class="rounded-lg border border-[var(--brand-border)] bg-[var(--color-surface)] px-2.5 py-1.5 text-sm text-[var(--color-primary)]"
                    >
                        <option value="">Bulk action…</option>
                        <option value="activate">Activate selected</option>
                        <option value="deactivate">Deactivate selected</option>
                    </select>
                    <button
                        type="submit"
                        id="bulk_apply_button"
                        disabled
                        class="inline-flex items-center rounded-lg bg-[var(--color-brand)] px-3 py-1.5 text-sm font-semibold text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        Apply
                    </button>
                    <span id="bulk_selection_count" class="text-xs text-[var(--color-muted)]" aria-live="polite"></span>
                </div>
            @endif

            <table
                data-datatable="true"
                data-datatable-per-page="10"
                data-datatable-search-placeholder="Search institutions…"
                class="min-w-full w-full"
            >
                <thead>
                    <tr>
                        @if ($canUpdateInstitutions)
                            <th scope="col" class="admin-data-table__check" data-sortable="false">
                                <input
                                    type="checkbox"
                                    id="select_all_institutions"
                                    class="rounded border-[var(--brand-border)] text-[var(--color-brand)] focus:ring-[var(--color-brand)]"
                                    aria-label="Select all institutions on this page"
                                    @disabled($institutions->isEmpty())
                                >
                            </th>
                        @endif
                        <th scope="col">Name</th>
                        <th scope="col">Code</th>
                        <th scope="col">Branches</th>
                        <th scope="col">Status</th>
                        <th scope="col" data-sortable="false">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($institutions as $institution)
                        <tr>
                            @if ($canUpdateInstitutions)
                                <td class="admin-data-table__check text-center">
                                    <input
                                        type="checkbox"
                                        name="ids[]"
                                        value="{{ $institution->id }}"
                                        class="institution-checkbox rounded border-[var(--brand-border)] text-[var(--color-brand)] focus:ring-[var(--color-brand)]"
                                        aria-label="Select {{ $institution->name }}"
                                    >
                                </td>
                            @endif
                            <td class="font-medium text-left">{{ $institution->name }}</td>
                            <td class="text-left">
                                <span class="font-mono text-sm text-[var(--color-brand)]">{{ $institution->code ?? '—' }}</span>
                            </td>
                            <td>{{ $institution->branches_count }}</td>
                            <td>
                                <span class="{{ $institution->is_active ? 'status-pill status-pill-active' : 'status-pill status-pill-inactive' }}">
                                    {{ $institution->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="admin-data-table__actions text-center">
                                <div class="inline-flex items-center justify-center gap-1.5">
                                    <a
                                        href="{{ route('admin.financial-institutions.branches', $institution) }}"
                                        class="inline-flex items-center rounded-lg border border-blue-400/50 bg-blue-500/10 px-2.5 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-500/20 transition"
                                    >
                                        Branches
                                    </a>
                                    <a
                                        href="{{ route('admin.financial-institutions.edit', $institution) }}"
                                        class="inline-flex items-center rounded-lg border border-purple-400/50 bg-purple-500/10 px-2.5 py-1 text-xs font-semibold text-purple-700 hover:bg-purple-500/20 transition"
                                    >
                                        Edit
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canUpdateInstitutions ? 6 : 5 }}" class="px-4 py-8 text-center text-[var(--color-muted)]">
                                No financial institutions found. Run the seeder or add one manually.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </form>
    </div>
@endsection

@if ($canUpdateInstitutions && $institutions->isNotEmpty())
@push('scripts')
<script>
    (function () {
        const form = document.getElementById('financialInstitutionsBulkForm');
        const selectAll = document.getElementById('select_all_institutions');
        const actionSelect = document.getElementById('bulk_action');
        const applyButton = document.getElementById('bulk_apply_button');
        const countLabel = document.getElementById('bulk_selection_count');
        const bulkGroup = form ? form.querySelector('[data-admin-table-bulk]') : null;

        if (!form || !selectAll || !actionSelect || !applyButton || !bulkGroup) {
            return;
        }

        function visibleCheckboxes() {
            return Array.from(form.querySelectorAll('tbody .institution-checkbox')).filter((box) => {
                const row = box.closest('tr');
                return row && row.offsetParent !== null;
            });
        }

        function updateBulkActionVisibility() {
            const boxes = visibleCheckboxes();
            const selected = boxes.filter((box) => box.checked);
            const count = selected.length;

            if (count > 0) {
                bulkGroup.hidden = false;
                bulkGroup.setAttribute('aria-hidden', 'false');
                if (countLabel) {
                    countLabel.textContent = count + ' selected';
                }
            } else {
                bulkGroup.hidden = true;
                bulkGroup.setAttribute('aria-hidden', 'true');
                actionSelect.value = '';
                if (countLabel) {
                    countLabel.textContent = '';
                }
            }

            applyButton.disabled = count === 0 || !actionSelect.value;
            selectAll.checked = boxes.length > 0 && selected.length === boxes.length;
            selectAll.indeterminate = selected.length > 0 && selected.length < boxes.length;
        }

        selectAll.addEventListener('change', function () {
            visibleCheckboxes().forEach((box) => {
                box.checked = selectAll.checked;
            });
            updateBulkActionVisibility();
        });

        form.addEventListener('change', function (event) {
            if (event.target.classList.contains('institution-checkbox') || event.target === actionSelect) {
                updateBulkActionVisibility();
            }
        });

        form.addEventListener('click', function (event) {
            if (event.target.classList.contains('institution-checkbox') || event.target === selectAll) {
                setTimeout(updateBulkActionVisibility, 0);
            }
        });

        form.addEventListener('submit', function (event) {
            const selected = Array.from(form.querySelectorAll('.institution-checkbox:checked'));
            if (!actionSelect.value) {
                event.preventDefault();
                alert('Please choose Activate selected or Deactivate selected.');
                return;
            }
            if (selected.length === 0) {
                event.preventDefault();
                alert('Please select at least one financial institution.');
                return;
            }

            const verb = actionSelect.value === 'activate' ? 'activate' : 'deactivate';
            if (!confirm('Are you sure you want to ' + verb + ' ' + selected.length + ' selected institution(s)?')) {
                event.preventDefault();
            }
        });

        let syncQueued = false;
        const queueSelectionSync = () => {
            if (syncQueued) {
                return;
            }
            syncQueued = true;
            requestAnimationFrame(() => {
                syncQueued = false;
                updateBulkActionVisibility();
            });
        };

        const tbody = form.querySelector('tbody');
        if (tbody) {
            const observer = new MutationObserver(queueSelectionSync);
            observer.observe(tbody, { childList: true, subtree: true });
        }

        form.addEventListener('keyup', function (event) {
            if (event.target && (event.target.classList.contains('datatable-input') || event.target.classList.contains('dataTable-input'))) {
                queueSelectionSync();
            }
        });

        form.addEventListener('change', function (event) {
            if (event.target && (event.target.classList.contains('datatable-selector') || event.target.classList.contains('dataTable-selector'))) {
                queueSelectionSync();
            }
        });

        updateBulkActionVisibility();
        setTimeout(updateBulkActionVisibility, 300);
    })();
</script>
@endpush
@endif
