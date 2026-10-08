<div id="editCategoryModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60" role="dialog" aria-modal="true" aria-labelledby="editCategoryModalTitle">
    <div class="w-full max-w-lg rounded-3xl border border-white/10 bg-slate-900 p-6 shadow-2xl">
        <div class="flex items-start justify-between gap-4 mb-6">
            <div>
                <h2 id="editCategoryModalTitle" class="text-lg font-semibold text-white">Edit category</h2>
                <p class="text-sm text-slate-400 mt-1">Update how this transaction is classified for reporting. Amounts and accounts are not changed.</p>
            </div>
            <button type="button" class="text-slate-400 hover:text-white transition" onclick="closeEditCategoryModal()" aria-label="Close">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form action="{{ route('admin.financial-transactions.category.update', $financialTransaction) }}" method="POST" class="space-y-4">
            @csrf
            @method('PATCH')

            @if ($financialTransaction->type === 'income')
                <div>
                    <label class="text-sm font-medium text-slate-300">Category <span class="text-rose-400">*</span></label>
                    <select name="category" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                        @foreach ($incomeCategories as $category)
                            <option value="{{ $category->code }}" @selected(old('category', $financialTransaction->category) === $category->code)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('category')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                </div>
            @else
                <div>
                    <label class="text-sm font-medium text-slate-300">Category <span class="text-rose-400">*</span></label>
                    <select name="category" id="edit_expense_category" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                        @foreach ($expenseCategories as $category)
                            <option value="{{ $category->code }}" @selected(old('category', $financialTransaction->category) === $category->code)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('category')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-300">Subcategory</label>
                    <select name="expense_subcategory_id" id="edit_expense_subcategory_id" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                        <option value="">Unclassified</option>
                    </select>
                    @error('expense_subcategory_id')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                </div>
            @endif

            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="button" onclick="closeEditCategoryModal()" class="rounded-2xl border border-white/10 px-5 py-2.5 text-sm font-medium text-white/80 hover:bg-white/10 transition">
                    Cancel
                </button>
                <button type="submit" class="rounded-2xl bg-gradient-to-r from-cyan-500 to-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-cyan-500/30 transition hover:scale-[1.01]">
                    Save category
                </button>
            </div>
        </form>
    </div>
</div>

@if ($financialTransaction->type === 'expense')
    @push('scripts')
        <script>
            const editExpenseSubcategories = @json($expenseCategories->mapWithKeys(fn ($category) => [
                $category->code => $category->subcategories->map(fn ($sub) => ['id' => $sub->id, 'name' => $sub->name])->values(),
            ]));
            const editSelectedSubcategoryId = @json(old('expense_subcategory_id', $financialTransaction->expense_subcategory_id));

            function refreshEditExpenseSubcategories() {
                const categoryCode = document.getElementById('edit_expense_category')?.value;
                const select = document.getElementById('edit_expense_subcategory_id');
                if (!select) return;

                const options = editExpenseSubcategories[categoryCode] || [];
                select.innerHTML = '<option value="">Unclassified</option>';
                options.forEach((subcategory) => {
                    const option = document.createElement('option');
                    option.value = subcategory.id;
                    option.textContent = subcategory.name;
                    if (String(editSelectedSubcategoryId) === String(subcategory.id)) {
                        option.selected = true;
                    }
                    select.appendChild(option);
                });
            }

            document.getElementById('edit_expense_category')?.addEventListener('change', refreshEditExpenseSubcategories);
            refreshEditExpenseSubcategories();
        </script>
    @endpush
@endif

@push('scripts')
    <script>
        function openEditCategoryModal() {
            const modal = document.getElementById('editCategoryModal');
            if (modal) modal.classList.remove('hidden');
        }

        function closeEditCategoryModal() {
            const modal = document.getElementById('editCategoryModal');
            if (modal) modal.classList.add('hidden');
        }

        document.getElementById('editCategoryModal')?.addEventListener('click', function (event) {
            if (event.target === this) closeEditCategoryModal();
        });

        @if ($errors->has('category') || $errors->has('expense_subcategory_id'))
            openEditCategoryModal();
        @endif
    </script>
@endpush
