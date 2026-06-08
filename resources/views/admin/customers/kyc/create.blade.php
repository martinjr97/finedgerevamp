@extends('layouts.admin')

@section('title', 'Upload KYC Documents | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        <div class="space-y-2 text-left">
            <h1 class="text-3xl font-bold">Upload KYC Documents</h1>
            <p class="text-sm text-slate-400">Customer: <span class="font-semibold text-white">{{ $customer->full_name }}</span></p>
        </div>

        @if ($latestKyc ?? null)
            <div class="rounded-3xl border border-amber-500/30 bg-amber-500/10 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-amber-300 font-medium">KYC documents already uploaded</p>
                        <p class="text-amber-200 text-sm mt-1">Status: <span class="capitalize">{{ $latestKyc->status }}</span> | Uploaded: {{ $latestKyc->created_at->format('d M Y H:i') }}</p>
                    </div>
                    <a href="{{ route('admin.customers.kyc.show', $customer) }}" class="inline-flex items-center gap-2 rounded-xl bg-amber-500/20 border border-amber-500/40 px-4 py-2 text-sm text-amber-200 hover:bg-amber-500/30 transition">
                        View Existing KYC
                    </a>
                </div>
            </div>
        @endif

        @php
            $isMarketeer = $customer->loanProduct && $customer->loanProduct->category === 'marketeer';
            $isGovernment = $customer->loanProduct && $customer->loanProduct->category === 'government';
            $isSmeCompany = $customer->loanProduct && $customer->loanProduct->category === 'sme' && $customer->customer_type === 'company';
        @endphp

        <form action="{{ route('admin.customers.kyc.store', $customer) }}" method="POST" enctype="multipart/form-data" class="space-y-8" data-kyc-upload-form>
            @csrf

            {{-- Document Type --}}
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                <h2 class="mb-6 text-xl font-semibold text-white">Document Type</h2>
                <div>
                    <label class="text-sm font-medium text-slate-300">Document Type <span class="text-rose-400">*</span></label>
                    <select name="document_type" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40" {{ $isSmeCompany ? 'readonly disabled' : '' }}>
                        <option value="">Select Document Type</option>
                        @if($isSmeCompany)
                            <option value="company_documents" selected>Company documents (PACRA/COI + Tax + Bank)</option>
                        @else
                            <option value="nrc" @selected(old('document_type') === 'nrc')>NRC (National Registration Card)</option>
                            <option value="passport" @selected(old('document_type') === 'passport')>Passport</option>
                            <option value="drivers_license" @selected(old('document_type') === 'drivers_license')>Driver's License</option>
                            <option value="voters_card" @selected(old('document_type') === 'voters_card')>Voter's Card</option>
                            <option value="other" @selected(old('document_type') === 'other')>Other</option>
                        @endif
                    </select>
                    @if($isSmeCompany)
                        <input type="hidden" name="document_type" value="company_documents">
                    @endif
                    @error('document_type')
                        <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            @if($isSmeCompany)
                {{-- SME Company Documents --}}
                <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                    <h2 class="mb-6 text-xl font-semibold text-white">Required Company Documents</h2>
                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <label class="text-sm font-medium text-slate-300">PACRA / Certificate of Incorporation <span class="text-rose-400">*</span></label>
                            <input type="file" name="front_image" accept="application/pdf,image/jpeg,image/png,image/jpg" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-cyan-500 file:text-white hover:file:bg-cyan-600">
                            <p class="mt-1 text-xs text-slate-400">PDF, JPEG, PNG, JPG (Max: 15MB)</p>
                            @error('front_image')
                                <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-300">Board Resolution / Authorization (Optional)</label>
                            <input type="file" name="back_image" accept="application/pdf,image/jpeg,image/png,image/jpg" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-cyan-500 file:text-white hover:file:bg-cyan-600">
                            <p class="mt-1 text-xs text-slate-400">PDF, JPEG, PNG, JPG (Max: 15MB)</p>
                            @error('back_image')
                                <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-300">Tax Clearance / TPIN Certificate <span class="text-rose-400">*</span></label>
                            <input type="file" name="payslip" accept="application/pdf,image/jpeg,image/png,image/jpg" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-cyan-500 file:text-white hover:file:bg-cyan-600">
                            <p class="mt-1 text-xs text-slate-400">PDF, JPEG, PNG, JPG (Max: 15MB)</p>
                            @error('payslip')
                                <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-300">Bank Statement (last 3 months) <span class="text-rose-400">*</span></label>
                            <input type="file" name="bank_statement" accept="application/pdf,image/jpeg,image/png,image/jpg" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-cyan-500 file:text-white hover:file:bg-cyan-600">
                            <p class="mt-1 text-xs text-slate-400">PDF, JPEG, PNG, JPG (Max: 15MB)</p>
                            @error('bank_statement')
                                <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            @else
                {{-- Required Documents --}}
                <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                    <h2 class="mb-6 text-xl font-semibold text-white">Required Documents</h2>
                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <label class="text-sm font-medium text-slate-300">Front of Document <span class="text-rose-400">*</span></label>
                            <input type="file" name="front_image" accept="image/jpeg,image/png,image/jpg" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-cyan-500 file:text-white hover:file:bg-cyan-600">
                            <p class="mt-1 text-xs text-slate-400">Accepted formats: JPEG, PNG, JPG (Max: 15MB)</p>
                            @error('front_image')
                                <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-300">Back of Document</label>
                            <input type="file" name="back_image" accept="image/jpeg,image/png,image/jpg" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-cyan-500 file:text-white hover:file:bg-cyan-600">
                            <p class="mt-1 text-xs text-slate-400">Accepted formats: JPEG, PNG, JPG (Max: 15MB)</p>
                            @error('back_image')
                                <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-300">Profile Picture</label>
                            <input type="file" name="profile_picture" accept="image/jpeg,image/png,image/jpg" class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-cyan-500 file:text-white hover:file:bg-cyan-600">
                            <p class="mt-1 text-xs text-slate-400">Accepted formats: JPEG, PNG, JPG (Max: 15MB)</p>
                            @error('profile_picture')
                                <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>
                        @if($isMarketeer)
                            <div>
                                <label class="text-sm font-medium text-slate-300">Stand Picture <span class="text-rose-400">*</span></label>
                                <input type="file" name="stand_picture" accept="image/jpeg,image/png,image/jpg" required class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-cyan-500 file:text-white hover:file:bg-cyan-600">
                                <p class="mt-1 text-xs text-slate-400">Accepted formats: JPEG, PNG, JPG (Max: 15MB)</p>
                                @error('stand_picture')
                                    <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Optional Documents --}}
                <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg">
                    <h2 class="mb-2 text-xl font-semibold text-white">
                        {{ $isGovernment ? 'Employment Documents' : 'Optional Documents' }}
                    </h2>
                    @if ($isGovernment)
                        <p class="mb-6 text-sm text-slate-400">Bank statement and payslip are required for government employees.</p>
                    @else
                        <p class="mb-6 text-sm text-slate-400">Upload supporting documents if available.</p>
                    @endif
                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <label class="text-sm font-medium text-slate-300">
                                Bank Statement
                                @if ($isGovernment)
                                    <span class="text-rose-400">*</span>
                                @endif
                            </label>
                            <input
                                type="file"
                                name="bank_statement"
                                accept=".pdf,image/jpeg,image/png,image/jpg"
                                @if ($isGovernment) required @endif
                                class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-cyan-500 file:text-white hover:file:bg-cyan-600"
                            >
                            <p class="mt-1 text-xs text-slate-400">Accepted formats: PDF, JPEG, PNG, JPG (Max: 15MB)</p>
                            @error('bank_statement')
                                <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-300">
                                Payslip
                                @if ($isGovernment)
                                    <span class="text-rose-400">*</span>
                                @endif
                            </label>
                            <input
                                type="file"
                                name="payslip"
                                accept=".pdf,image/jpeg,image/png,image/jpg"
                                @if ($isGovernment) required @endif
                                class="mt-2 w-full rounded-2xl bg-white/10 border border-white/10 text-white px-4 py-3 focus:border-cyan-400 focus:ring-cyan-400/40 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-cyan-500 file:text-white hover:file:bg-cyan-600"
                            >
                            <p class="mt-1 text-xs text-slate-400">Accepted formats: PDF, JPEG, PNG, JPG (Max: 15MB)</p>
                            @error('payslip')
                                <p class="mt-1 text-xs text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            @endif

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('admin.customers.show', $customer) }}" class="inline-flex items-center gap-2 rounded-2xl border border-white/20 bg-white/5 px-4 py-3 text-base font-medium text-slate-300 hover:bg-white/10 hover:border-white/30 transition">
                    Cancel
                </a>
                <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-blue-500 to-blue-600 px-4 py-3 text-base font-semibold text-white shadow-lg shadow-blue-500/30 hover:from-blue-600 hover:to-blue-700 transition">
                    Upload KYC Documents
                </button>
            </div>
        </form>
    </div>

    @include('partials.kyc-file-preview')
@endsection
