@php
    /** @var \App\Models\Faq $faq */
    $isEdit = $faq->exists ?? false;
@endphp

<form method="POST" action="{{ $isEdit ? route('admin.faqs.update', $faq) : route('admin.faqs.store') }}" class="space-y-6">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="space-y-2">
        <label for="question" class="block text-sm font-medium text-slate-300">Question</label>
        <input
            type="text"
            id="question"
            name="question"
            value="{{ old('question', $faq->question) }}"
            required
            class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 text-base placeholder:text-slate-300 focus:border-blue-500 focus:ring-blue-500/40 focus:outline-none"
            placeholder="e.g. How do I view my loan statement?"
        >
        @error('question')
            <p class="text-sm text-rose-400 font-medium">{{ $message }}</p>
        @enderror
    </div>

    <div class="space-y-2">
        <label for="answer" class="block text-sm font-medium text-slate-300">Answer</label>
        <textarea
            id="answer"
            name="answer"
            rows="5"
            required
            class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 text-base placeholder:text-slate-300 focus:border-blue-500 focus:ring-blue-500/40 focus:outline-none resize-y"
            placeholder="Provide a clear and helpful answer to the question."
        >{{ old('answer', $faq->answer) }}</textarea>
        @error('answer')
            <p class="text-sm text-rose-400 font-medium">{{ $message }}</p>
        @enderror
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <div class="space-y-2">
            <label for="visibility" class="block text-sm font-medium text-slate-300">Visibility</label>
            <select
                id="visibility"
                name="visibility"
                class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-2.5 text-sm focus:border-blue-500 focus:ring-blue-500/40 focus:outline-none"
            >
                @php
                    $currentVisibility = old('visibility', $faq->visibility ?? \App\Models\Faq::VISIBILITY_PUBLIC);
                @endphp
                <option value="{{ \App\Models\Faq::VISIBILITY_PUBLIC }}" @selected($currentVisibility === \App\Models\Faq::VISIBILITY_PUBLIC)>
                    Public (visible on login page)
                </option>
                <option value="{{ \App\Models\Faq::VISIBILITY_AUTHENTICATED }}" @selected($currentVisibility === \App\Models\Faq::VISIBILITY_AUTHENTICATED)>
                    Customers only (logged in)
                </option>
                <option value="{{ \App\Models\Faq::VISIBILITY_BOTH }}" @selected($currentVisibility === \App\Models\Faq::VISIBILITY_BOTH)>
                    Public & Customers
                </option>
            </select>
            @error('visibility')
                <p class="text-sm text-rose-400 font-medium">{{ $message }}</p>
            @enderror
        </div>

        <div class="space-y-2">
            <label class="block text-sm font-medium text-slate-300">Status</label>
            <label class="inline-flex items-center gap-2 text-sm text-slate-200">
                <input
                    type="checkbox"
                    name="is_active"
                    value="1"
                    class="rounded border-white/20 bg-black/40 text-emerald-400 focus:ring-emerald-500/40"
                    @checked(old('is_active', $faq->is_active ?? true))
                >
                <span>Active (show this FAQ to users)</span>
            </label>
        </div>
    </div>

    <div class="flex items-center justify-end gap-3 pt-2">
        <a href="{{ route('admin.faqs.index') }}"
           class="inline-flex items-center gap-2 rounded-2xl border border-white/20 bg-white/5 px-4 py-3 text-base font-medium text-slate-200 hover:bg-white/10 hover:border-white/30 transition">
            Cancel
        </a>
        <button
            type="submit"
            class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-blue-500 to-blue-600 px-5 py-3 text-base font-semibold text-white shadow-lg shadow-blue-500/30 hover:from-blue-600 hover:to-blue-700 transition"
        >
            {{ $isEdit ? 'Update FAQ' : 'Create FAQ' }}
        </button>
    </div>
</form>


