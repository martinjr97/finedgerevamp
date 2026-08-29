<div>
    @php
        $labelClass = $labelClass ?? 'text-slate-300';
        $requiredClass = $requiredClass ?? 'text-red-400';
        $inputClass = $inputClass ?? 'bg-white/10 border border-white/10';
        $inputFocusClass = $inputFocusClass ?? 'focus:border-cyan-400 focus:ring-cyan-400/40';
        $errorClass = $errorClass ?? 'text-red-400';
        $helpClass = $helpClass ?? 'text-slate-400';
    @endphp
    <label class="text-sm font-medium {{ $labelClass }}">Branch <span class="{{ $requiredClass }}">*</span></label>
    <select name="branch_id" required class="mt-2 w-full rounded-2xl {{ $inputClass }} {{ $inputFocusClass }} text-white px-4 py-3">
        <option value="">Select branch</option>
        @foreach ($branches ?? [] as $branch)
            <option value="{{ $branch->id }}" @selected((string) old('branch_id', $selectedBranchId ?? '') === (string) $branch->id)>
                {{ $branch->name }}@if($branch->code) ({{ $branch->code }})@endif
            </option>
        @endforeach
    </select>
    @error('branch_id')
        <p class="mt-1 text-xs {{ $errorClass }}">{{ $message }}</p>
    @enderror
    <p class="mt-1 text-xs {{ $helpClass }}">Select the branch this customer is linked to</p>
</div>
