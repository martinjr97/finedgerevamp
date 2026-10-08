<div id="{{ $headingId ?? 'subcategories' }}" class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg max-w-3xl space-y-4 scroll-mt-8">
    <div class="flex items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-white">Subcategories</h2>
            <p class="text-xs text-slate-400 mt-1">{{ $expenseCategory->subcategories->count() }} total</p>
        </div>
        @can('financial-categories.create')
            <a href="{{ route('admin.financial-categories.expense.subcategory.create', $expenseCategory) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-400/50 bg-emerald-500/10 px-2.5 py-1 text-xs font-semibold text-emerald-200 hover:bg-emerald-500/20 transition">
                Add Subcategory
            </a>
        @endcan
    </div>
    <div class="admin-data-table">
        <table class="min-w-full w-full">
            <thead>
                <tr class="font-semibold uppercase text-white/80 text-center text-xs">
                    <th scope="col" class="text-left">Name</th>
                    <th scope="col">Code</th>
                    <th scope="col">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($expenseCategory->subcategories as $subcategory)
                    <tr class="text-center text-sm">
                        <td class="text-left font-medium text-white">{{ $subcategory->name }}</td>
                        <td class="font-mono text-xs text-slate-300">{{ $subcategory->code ?: '—' }}</td>
                        <td>
                            <span class="font-medium {{ $subcategory->is_active ? 'text-emerald-400' : 'text-rose-400' }}">
                                {{ $subcategory->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="py-8 text-center text-slate-400">No subcategories yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
