@extends('layouts.admin')

@section('title', 'Edit SMS Template | '.config('app.system_name'))

@section('content')
    <div class="space-y-8" x-data="{ body: @js(old('body', $template->body)), maxLength: {{ (int) old('max_length', $template->max_length) }} }">
        @include('partials.admin.page-header', [
            'title' => 'Edit SMS Template',
            'description' => $template->name,
            'buttons' => [
                ['text' => 'Back to Templates', 'href' => route('admin.sms-templates.index'), 'action' => 'secondary'],
            ],
        ])

        @if(session('warning'))
            <div class="rounded-2xl border border-amber-400/40 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">
                {{ session('warning') }}
            </div>
        @endif

        <div class="grid gap-6 xl:grid-cols-3">
            <form method="POST" action="{{ route('admin.sms-templates.update', $template) }}" class="xl:col-span-2 space-y-5 rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                @csrf
                @method('PUT')

                <div>
                    <label class="text-sm font-medium text-slate-200">Display name</label>
                    <input type="text" name="name" value="{{ old('name', $template->name) }}" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3" required>
                    @error('name')<p class="text-xs text-rose-300 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-200">Template key</label>
                    <input type="text" value="{{ $template->key }}" class="mt-2 w-full rounded-2xl bg-white/5 border border-white/10 text-slate-400 px-4 py-3" disabled>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium text-slate-200">Category</label>
                        <select name="category" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3">
                            @foreach($categories as $category)
                                <option value="{{ $category->value }}" @selected(old('category', $template->category->value) === $category->value)>{{ $category->value }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-200">Max length</label>
                        <input type="number" name="max_length" x-model.number="maxLength" value="{{ old('max_length', $template->max_length) }}" min="70" max="500" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3" required>
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between gap-4">
                        <label class="text-sm font-medium text-slate-200">Message body</label>
                        <span class="text-xs" :class="body.length > maxLength ? 'text-rose-300' : 'text-slate-400'">
                            <span x-text="body.length"></span> / <span x-text="maxLength"></span> characters
                        </span>
                    </div>
                    <textarea name="body" rows="4" x-model="body" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 font-mono text-sm" required>{{ old('body', $template->body) }}</textarea>
                    @error('body')<p class="text-xs text-rose-300 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-200">Placeholder help</label>
                    <textarea name="description" rows="2" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 text-sm">{{ old('description', $template->description) }}</textarea>
                </div>

                <label class="inline-flex items-center gap-2 text-sm text-slate-200">
                    <input type="checkbox" name="is_active" value="1" class="rounded border-white/20 bg-white/10 text-cyan-400" @checked(old('is_active', $template->is_active))>
                    Template is active
                </label>

                <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-cyan-500/20 border border-cyan-500/50 px-4 py-2.5 text-sm font-semibold text-cyan-200 hover:bg-cyan-500/30 transition">
                    Save Template
                </button>
            </form>

            <aside class="space-y-4 rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                <h2 class="text-lg font-semibold text-white">Sample preview</h2>
                <p class="text-sm text-slate-400">Rendered with example placeholder values (not sent).</p>
                <div class="rounded-2xl bg-slate-900/80 p-4 text-sm text-slate-200 font-mono whitespace-pre-wrap">{{ $samplePreview ?? 'Preview unavailable — message may exceed max length.' }}</div>
                <p class="text-xs text-slate-500">Use placeholders like {NAME}, {AMOUNT}, {PIN}. They are replaced at send time.</p>
            </aside>
        </div>
    </div>
@endsection
