@php
    $isEdit = isset($branch) && $branch && $branch->exists;
    $provinces = $provinces ?? collect();
    $districts = $districts ?? collect();
    $managers = $managers ?? collect();
@endphp

<form action="{{ $isEdit ? route('admin.branches.update', $branch) : route('admin.branches.store') }}" method="POST" class="space-y-8">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4 max-w-3xl">
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="text-sm font-medium text-slate-300">Branch Name</label>
                <input type="text" name="name" value="{{ old('name', $isEdit ? $branch->name : '') }}" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
            </div>
            <div>
                <label class="text-sm font-medium text-slate-300">Code</label>
                <input type="text" name="code" value="{{ old('code', $isEdit ? $branch->code : '') }}" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40" placeholder="e.g. KITWE">
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="text-sm font-medium text-slate-300">Province</label>
                <select name="province_id" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                    <option value="">Select province</option>
                    @foreach ($provinces as $province)
                        <option value="{{ $province->id }}" @selected(old('province_id', $isEdit ? ($branch->province_id ?? '') : '') == $province->id)>
                            {{ $province->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-sm font-medium text-slate-300">District</label>
                <select name="district_id" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                    <option value="">Select district</option>
                    @foreach ($districts as $district)
                        <option value="{{ $district->id }}" data-province-id="{{ $district->province_id }}" @selected(old('district_id', $isEdit ? ($branch->district_id ?? '') : '') == $district->id)>
                            {{ $district->name }} ({{ $district->province->name ?? '—' }})
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="text-sm font-medium text-slate-300">Branch Manager</label>
            <select name="branch_manager_id" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                <option value="">Select manager (optional)</option>
                @foreach ($managers as $manager)
                    <option value="{{ $manager->id }}" @selected(old('branch_manager_id', $isEdit ? ($branch->branch_manager_id ?? '') : '') == $manager->id)>
                        {{ $manager->full_name }} ({{ $manager->email }})
                    </option>
                @endforeach
            </select>
        </div>

        <label class="inline-flex items-center gap-2 text-sm text-slate-300">
            <input type="checkbox" name="is_active" value="1" class="rounded border-white/20 bg-white/10 text-cyan-400 focus:ring-cyan-500/30" @checked(old('is_active', $isEdit ? $branch->is_active : true))>
            Active
        </label>
    </div>

    <div class="flex items-center justify-end gap-3">
        <a href="{{ route('admin.branches.index') }}" class="inline-flex items-center gap-2 rounded-2xl border border-white/20 bg-white/5 px-4 py-3 text-base font-medium text-slate-300 hover:bg-white/10 hover:border-white/30 transition">
            Cancel
        </a>
        <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-blue-500 to-blue-600 px-4 py-3 text-base font-semibold text-white shadow-lg shadow-blue-500/30 hover:from-blue-600 hover:to-blue-700 transition">
            {{ $isEdit ? 'Update Branch' : 'Create Branch' }}
        </button>
    </div>
</form>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const provinceSelect = document.querySelector('select[name="province_id"]');
            const districtSelect = document.querySelector('select[name="district_id"]');

            if (!provinceSelect || !districtSelect) {
                return;
            }

            const allDistrictOptions = Array.from(districtSelect.options).filter(option => option.value !== '');
            const currentDistrictId = districtSelect.value;

            const filterDistricts = () => {
                const selectedProvinceId = provinceSelect.value;
                const previousSelection = districtSelect.value;

                // Reset options
                districtSelect.innerHTML = '';
                const placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = 'Select district';
                districtSelect.appendChild(placeholder);

                allDistrictOptions.forEach(option => {
                    const optionProvinceId = option.getAttribute('data-province-id');
                    if (!selectedProvinceId || optionProvinceId === selectedProvinceId) {
                        districtSelect.appendChild(option);
                    }
                });

                // Restore selection if still valid under the new province
                if (previousSelection) {
                    const canKeep = Array.from(districtSelect.options).some(
                        option => option.value === previousSelection
                    );
                    if (canKeep) {
                        districtSelect.value = previousSelection;
                    } else {
                        districtSelect.value = '';
                    }
                } else if (currentDistrictId) {
                    const canKeepInitial = Array.from(districtSelect.options).some(
                        option => option.value === currentDistrictId
                    );
                    if (canKeepInitial) {
                        districtSelect.value = currentDistrictId;
                    }
                }
            };

            provinceSelect.addEventListener('change', filterDistricts);

            // Initial filter on page load
            filterDistricts();
        });
    </script>
@endpush

