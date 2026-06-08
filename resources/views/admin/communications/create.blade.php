@extends('layouts.admin')

@section('title', 'Send Communication | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        <div class="space-y-2 text-left">
            <p class="text-xs uppercase tracking-[0.4em] text-cyan-300">Communication</p>
            <h1 class="text-3xl font-bold">Send Communication</h1>
        </div>

        <form action="{{ route('admin.communications.store') }}" method="POST" class="space-y-6">
            @csrf

            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-6">
                <!-- Communication Type -->
                <div>
                    <label class="text-sm font-medium text-slate-300 mb-2 block">Communication Type <span class="text-rose-400">*</span></label>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <label class="flex items-center p-4 rounded-2xl bg-white/5 border border-white/10 cursor-pointer hover:bg-white/10 transition {{ old('type') === 'email' ? 'border-cyan-400 bg-cyan-500/10' : '' }}">
                            <input type="radio" name="type" value="email" {{ old('type', 'email') === 'email' ? 'checked' : '' }} class="mr-3">
                            <div>
                                <div class="font-medium text-white">Email</div>
                                <div class="text-xs text-slate-400">Send via email only</div>
                            </div>
                        </label>
                        <label class="flex items-center p-4 rounded-2xl bg-white/5 border border-white/10 cursor-pointer hover:bg-white/10 transition {{ old('type') === 'sms' ? 'border-cyan-400 bg-cyan-500/10' : '' }}">
                            <input type="radio" name="type" value="sms" {{ old('type') === 'sms' ? 'checked' : '' }} class="mr-3">
                            <div>
                                <div class="font-medium text-white">SMS</div>
                                <div class="text-xs text-slate-400">Send via SMS only</div>
                            </div>
                        </label>
                        <label class="flex items-center p-4 rounded-2xl bg-white/5 border border-white/10 cursor-pointer hover:bg-white/10 transition {{ old('type') === 'both' ? 'border-cyan-400 bg-cyan-500/10' : '' }}">
                            <input type="radio" name="type" value="both" {{ old('type') === 'both' ? 'checked' : '' }} class="mr-3">
                            <div>
                                <div class="font-medium text-white">Both</div>
                                <div class="text-xs text-slate-400">Send via email and SMS</div>
                            </div>
                        </label>
                    </div>
                    @error('type')
                        <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Subject (for email) -->
                <div id="subject-field" style="display: {{ in_array(old('type', 'email'), ['email', 'both']) ? 'block' : 'none' }};">
                    <label class="text-sm font-medium text-slate-300">Subject <span class="text-rose-400">*</span></label>
                    <input type="text" name="subject" value="{{ old('subject') }}" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                    @error('subject')
                        <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Message -->
                <div>
                    <label class="text-sm font-medium text-slate-300">Message <span class="text-rose-400">*</span></label>
                    <textarea name="message" rows="6" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">{{ old('message') }}</textarea>
                    <p class="mt-1 text-xs text-slate-400">Maximum 5000 characters</p>
                    @error('message')
                        <p class="mt-1 text-sm text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Filters Section -->
                <div class="border-t border-white/10 pt-6">
                    <h3 class="text-lg font-semibold text-white mb-4">Filter Recipients (Optional)</h3>
                    <p class="text-sm text-slate-400 mb-4">Select one or more filters to target specific customer groups. Leave all filters empty to send to all active customers.</p>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Product Type -->
                        <div>
                            <label class="text-sm font-medium text-slate-300 mb-2 block">Product Type</label>
                            <select name="filters[product_id]" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                                <option value="">All Products</option>
                                @foreach($products as $product)
                                    <option value="{{ $product->id }}" @selected(old('filters.product_id') == $product->id)>{{ $product->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Province -->
                        <div>
                            <label class="text-sm font-medium text-slate-300 mb-2 block">Province</label>
                            <select name="filters[province_id]" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                                <option value="">All Provinces</option>
                                @foreach($provinces as $province)
                                    <option value="{{ $province->id }}" @selected(old('filters.province_id') == $province->id)>{{ $province->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Age Group -->
                        <div>
                            <label class="text-sm font-medium text-slate-300 mb-2 block">Age Group</label>
                            <select name="filters[age_group]" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                                <option value="">All Ages</option>
                                <option value="18-25" @selected(old('filters.age_group') === '18-25')>18-25 years</option>
                                <option value="26-35" @selected(old('filters.age_group') === '26-35')>26-35 years</option>
                                <option value="36-45" @selected(old('filters.age_group') === '36-45')>36-45 years</option>
                                <option value="46-55" @selected(old('filters.age_group') === '46-55')>46-55 years</option>
                                <option value="56-65" @selected(old('filters.age_group') === '56-65')>56-65 years</option>
                                <option value="65+" @selected(old('filters.age_group') === '65+')>65+ years</option>
                            </select>
                        </div>

                        <!-- Active Loans -->
                        <div>
                            <label class="text-sm font-medium text-slate-300 mb-2 block">Loan Status</label>
                            <select name="filters[has_active_loans]" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                                <option value="">All Customers</option>
                                <option value="with" @selected(old('filters.has_active_loans') === 'with')>With Active Loans</option>
                                <option value="without" @selected(old('filters.has_active_loans') === 'without')>Without Active Loans</option>
                            </select>
                        </div>

                        <!-- Gender -->
                        <div>
                            <label class="text-sm font-medium text-slate-300 mb-2 block">Gender</label>
                            <select name="filters[gender]" class="w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40">
                                <option value="">All Genders</option>
                                <option value="male" @selected(old('filters.gender') === 'male')>Male</option>
                                <option value="female" @selected(old('filters.gender') === 'female')>Female</option>
                                <option value="other" @selected(old('filters.gender') === 'other')>Other</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3">
                @can('communications.send')
                <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-blue-500 to-blue-600 px-4 py-3 text-base font-semibold text-white shadow-lg shadow-blue-500/30 hover:from-blue-600 hover:to-blue-700 transition">
                    Send Communication
                </button>
                @else
                <button type="button" disabled class="inline-flex items-center gap-2 rounded-2xl bg-slate-600 px-4 py-3 text-base font-semibold text-white/50 cursor-not-allowed">
                    Send Communication (No Permission)
                </button>
                @endcan
                <a href="{{ route('admin.communications.index') }}" class="rounded-2xl border border-white/10 px-6 py-3 text-sm font-medium text-white/80 hover:bg-white/10 transition">
                    Cancel
                </a>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const typeInputs = document.querySelectorAll('input[name="type"]');
            const subjectField = document.getElementById('subject-field');

            typeInputs.forEach(input => {
                input.addEventListener('change', function() {
                    if (this.value === 'email' || this.value === 'both') {
                        subjectField.style.display = 'block';
                        subjectField.querySelector('input').required = true;
                    } else {
                        subjectField.style.display = 'none';
                        subjectField.querySelector('input').required = false;
                    }
                });
            });
        });
    </script>
@endsection

