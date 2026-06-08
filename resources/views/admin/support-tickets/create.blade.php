@extends('layouts.admin')

@section('title', 'Create Support Ticket | ' . config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Create Support Ticket',
            'description' => 'Log a support request on behalf of a customer or guest.',
            'buttons' => [
                [
                    'action' => 'secondary',
                    'text' => 'Back to Tickets',
                    'href' => route('admin.support-tickets.index'),
                ],
            ],
        ])

        <form
            method="POST"
            action="{{ route('admin.support-tickets.store') }}"
            enctype="multipart/form-data"
            class="w-full space-y-6"
            x-data="{
                customerId: @js(old('customer_id', '')),
                name: @js(old('name', '')),
                email: @js(old('email', '')),
                phone: @js(old('phone', '')),
                applyCustomer(option) {
                    if (!option || !option.value) {
                        return;
                    }
                    this.name = option.dataset.name || this.name;
                    this.email = option.dataset.email || this.email;
                    this.phone = option.dataset.phone || this.phone;
                }
            }"
        >
            @csrf

            <div class="grid gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2 space-y-6">
                    <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-5">
                        <h2 class="text-lg font-semibold text-white">Requester</h2>

                        <div>
                            <label for="customer_id" class="block text-sm font-medium text-slate-200">Linked customer (optional)</label>
                            <select
                                id="customer_id"
                                name="customer_id"
                                x-model="customerId"
                                @change="applyCustomer($event.target.selectedOptions[0])"
                                class="mt-2 w-full rounded-2xl border border-white/15 bg-black/30 px-4 py-2.5 text-sm text-slate-100 focus:border-cyan-400 focus:ring-cyan-400/40 focus:outline-none"
                            >
                                <option value="">— Guest / manual entry —</option>
                                @foreach ($customers as $customer)
                                    <option
                                        value="{{ $customer->id }}"
                                        data-name="{{ $customer->full_name }}"
                                        data-email="{{ $customer->email ?? '' }}"
                                        data-phone="{{ $customer->phone ?? '' }}"
                                        @selected((string) old('customer_id') === (string) $customer->id)
                                    >
                                        {{ $customer->full_name }} @if($customer->phone)({{ $customer->phone }})@endif
                                    </option>
                                @endforeach
                            </select>
                            @error('customer_id')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="name" class="block text-sm font-medium text-slate-200">Name <span class="text-rose-400" x-show="!customerId">*</span></label>
                                <input type="text" id="name" name="name" x-model="name" :required="!customerId"
                                    class="mt-2 w-full rounded-2xl border border-white/15 bg-black/30 px-4 py-2.5 text-sm text-slate-100 focus:border-cyan-400 focus:outline-none">
                                @error('name')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="email" class="block text-sm font-medium text-slate-200">Email</label>
                                <input type="email" id="email" name="email" x-model="email"
                                    class="mt-2 w-full rounded-2xl border border-white/15 bg-black/30 px-4 py-2.5 text-sm text-slate-100 focus:border-cyan-400 focus:outline-none">
                                @error('email')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label for="phone" class="block text-sm font-medium text-slate-200">Phone</label>
                                <input type="text" id="phone" name="phone" x-model="phone" maxlength="12" placeholder="260978232334"
                                    class="mt-2 w-full rounded-2xl border border-white/15 bg-black/30 px-4 py-2.5 text-sm text-slate-100 focus:border-cyan-400 focus:outline-none zambian-phone-input">
                                @error('phone')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-5">
                        <h2 class="text-lg font-semibold text-white">Ticket details</h2>

                        <div>
                            <label for="subject" class="block text-sm font-medium text-slate-200">Subject <span class="text-rose-400">*</span></label>
                            <input type="text" id="subject" name="subject" value="{{ old('subject') }}" required
                                class="mt-2 w-full rounded-2xl border border-white/15 bg-black/30 px-4 py-2.5 text-sm text-slate-100 focus:border-cyan-400 focus:outline-none"
                                placeholder="Brief summary of the issue">
                            @error('subject')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="message" class="block text-sm font-medium text-slate-200">Message <span class="text-rose-400">*</span></label>
                            <textarea id="message" name="message" rows="6" required
                                class="mt-2 w-full rounded-2xl border border-white/15 bg-black/30 px-4 py-3 text-sm text-slate-100 focus:border-cyan-400 focus:outline-none resize-y min-h-[10rem]"
                                placeholder="Describe the issue in detail">{{ old('message') }}</textarea>
                            @error('message')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="attachment" class="block text-sm font-medium text-slate-200">Supporting file (optional)</label>
                            <input type="file" id="attachment" name="attachment" accept=".pdf,image/jpeg,image/png,image/jpg"
                                class="mt-2 w-full rounded-2xl border border-white/15 bg-black/30 px-4 py-2.5 text-sm text-slate-100 file:mr-4 file:rounded-xl file:border-0 file:bg-cyan-500/30 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-cyan-100 hover:file:bg-cyan-500/40">
                            <p class="mt-1 text-xs text-slate-400">{{ \App\Support\DocumentUploadRules::HINT_PDF_IMAGE }}</p>
                            @error('attachment')<p class="mt-1 text-sm text-rose-400">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-5 lg:sticky lg:top-6">
                        <h2 class="text-lg font-semibold text-white">Assignment</h2>
                        <p class="text-sm text-slate-400">Optionally assign this ticket when it is created.</p>
                        <div>
                            <label for="assigned_to_id" class="block text-sm font-medium text-slate-200">Assign to staff</label>
                            <select id="assigned_to_id" name="assigned_to_id"
                                class="mt-2 w-full rounded-2xl border border-white/15 bg-black/30 px-4 py-2.5 text-sm text-slate-100 focus:border-cyan-400 focus:outline-none">
                                <option value="">— Unassigned —</option>
                                @foreach ($staffMembers as $staff)
                                    <option value="{{ $staff->id }}" @selected((string) old('assigned_to_id') === (string) $staff->id)>
                                        {{ $staff->full_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="assignment_note" class="block text-sm font-medium text-slate-200">Assignment note</label>
                            <textarea id="assignment_note" name="assignment_note" rows="4"
                                class="mt-2 w-full rounded-2xl border border-white/15 bg-black/30 px-4 py-2 text-sm text-slate-100 focus:border-cyan-400 focus:outline-none resize-y"
                                placeholder="Optional note for the assignee">{{ old('assignment_note') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3 rounded-3xl border border-white/10 bg-white/5 px-6 py-4 shadow-lg">
                <button type="submit"
                    class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-cyan-500 to-blue-600 px-6 py-3 text-base font-semibold text-white shadow-lg hover:from-cyan-600 hover:to-blue-700 transition">
                    Create Ticket
                </button>
                <a href="{{ route('admin.support-tickets.index') }}"
                   class="inline-flex items-center gap-2 rounded-2xl border border-white/20 px-6 py-3 text-sm font-semibold text-slate-300 hover:bg-white/10 transition">
                    Cancel
                </a>
            </div>
        </form>
    </div>
@endsection
