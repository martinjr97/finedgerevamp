@php
    use App\Models\Channel;

    $selectedType = old('type', $channel->type ?? Channel::TYPE_MOBILE_WALLET);
    $labelClass = $labelClass ?? 'text-sm font-medium text-slate-200';
    $helpClass = $helpClass ?? 'text-xs text-slate-400';
    $errorClass = $errorClass ?? 'text-xs text-rose-400';
    $requiredClass = $requiredClass ?? 'text-rose-400';
    $inputClass = $inputClass ?? 'mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40';
@endphp

<div>
    <label class="{{ $labelClass }}">Channel type <span class="{{ $requiredClass }}">*</span></label>
    <select name="type" required class="{{ $inputClass }}">
        @foreach(Channel::typeOptions() as $value => $label)
            <option value="{{ $value }}" @selected($selectedType === $value)>{{ $label }}</option>
        @endforeach
    </select>
    <p class="mt-1 {{ $helpClass }}">Channel type controls which disbursement details are required later.</p>
    @error('type')
        <p class="mt-1 {{ $errorClass }} font-medium">{{ $message }}</p>
    @enderror
</div>
