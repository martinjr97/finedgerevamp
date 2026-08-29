@php
    $sectionClass = $sectionClass ?? 'border-2 border-white/10 bg-white/5';
    $headingClass = $headingClass ?? 'text-white';
    $headingAccentClass = $headingAccentClass ?? 'bg-cyan-400';
    $inputClass = $inputClass ?? 'bg-white/10 border border-white/10';
    $inputFocusClass = $inputFocusClass ?? 'focus:border-cyan-400 focus:ring-cyan-400/40';
    $labelClass = $labelClass ?? 'text-slate-300';
    $errorClass = $errorClass ?? 'text-red-400';
    $helpClass = $helpClass ?? 'text-slate-400';
    $requiredClass = $requiredClass ?? 'text-red-400';
    $defaultBranchId = $selectedBranchId ?? old('branch_id', auth('admin')->user()?->branch_id);
@endphp

<div class="rounded-3xl {{ $sectionClass }} p-6 shadow-lg">
    <h2 class="mb-6 flex items-center gap-2 text-xl font-semibold {{ $headingClass }}">
        <span class="h-6 w-1 rounded-full {{ $headingAccentClass }}"></span>
        Branch Assignment
    </h2>
    <div class="grid gap-6 md:grid-cols-2">
        <div class="md:col-span-2">
            @include('partials.admin.customer-branch-select', [
                'branches' => $branches ?? collect(),
                'selectedBranchId' => $defaultBranchId,
                'inputClass' => $inputClass,
                'inputFocusClass' => $inputFocusClass,
                'labelClass' => $labelClass,
                'errorClass' => $errorClass,
                'helpClass' => $helpClass,
                'requiredClass' => $requiredClass,
            ])
        </div>
    </div>
</div>
