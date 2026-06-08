@extends('layouts.admin')

@section('title', 'View KYC Documents | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        <div class="flex items-center justify-between">
            <div class="space-y-1">
                <p class="text-xs uppercase tracking-[0.4em] text-cyan-300">Customer Management</p>
                <h1 class="text-3xl font-bold">KYC Documents - {{ $customer->full_name }}</h1>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.customers.show', $customer) }}" class="inline-flex items-center gap-2 rounded-2xl border border-white/10 px-4 py-3 text-sm text-white hover:bg-white/10 transition">
                    Back to Customer
                </a>
                @can('kyc.create')
                <a href="{{ route('admin.customers.kyc.create', $customer) }}" class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-emerald-500 to-teal-500 px-4 py-3 font-semibold text-white shadow-lg shadow-emerald-500/30 hover:shadow-emerald-500/50 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    Upload New KYC
                </a>
                @endcan
            </div>
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            {{-- KYC Information --}}
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                <h2 class="text-xl font-semibold text-white">KYC Information</h2>
                <div class="space-y-3 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Document Type:</span>
                        <span class="font-medium text-white capitalize">{{ str_replace('_', ' ', $kycDocument->document_type) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Status:</span>
                        <span class="inline-block rounded-full px-2 py-1 text-xs {{ $kycDocument->status === 'verified' ? 'bg-emerald-500/20 text-emerald-300' : ($kycDocument->status === 'pending' ? 'bg-amber-500/20 text-amber-300' : 'bg-rose-500/20 text-rose-300') }}">
                            {{ ucfirst($kycDocument->status) }}
                        </span>
                    </div>
                    @if ($kycDocument->verified_by)
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Verified By:</span>
                            <span class="font-medium text-white">{{ $kycDocument->verifier->full_name ?? '—' }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Verified At:</span>
                            <span class="font-medium text-white">{{ $kycDocument->verified_at?->format('d M Y H:i') ?? '—' }}</span>
                        </div>
                    @endif
                    @if ($kycDocument->notes)
                        <div>
                            <span class="text-slate-400">Notes:</span>
                            <p class="mt-1 font-medium text-white">{{ $kycDocument->notes }}</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Documents --}}
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-lg space-y-4">
                <h2 class="text-xl font-semibold text-white">Documents</h2>
                <div class="space-y-4">
                    @if ($kycDocument->front_image_path)
                        <div>
                            <p class="text-sm font-medium text-slate-300 mb-2">Front of Document</p>
                            <a href="{{ asset('storage/'.$kycDocument->front_image_path) }}" target="_blank" class="block rounded-xl overflow-hidden border border-white/10 hover:border-cyan-400/50 transition">
                                <img src="{{ asset('storage/'.$kycDocument->front_image_path) }}" alt="Front of Document" class="w-full h-48 object-cover">
                            </a>
                        </div>
                    @endif

                    @if ($kycDocument->back_image_path)
                        <div>
                            <p class="text-sm font-medium text-slate-300 mb-2">Back of Document</p>
                            <a href="{{ asset('storage/'.$kycDocument->back_image_path) }}" target="_blank" class="block rounded-xl overflow-hidden border border-white/10 hover:border-cyan-400/50 transition">
                                <img src="{{ asset('storage/'.$kycDocument->back_image_path) }}" alt="Back of Document" class="w-full h-48 object-cover">
                            </a>
                        </div>
                    @endif

                    @if ($kycDocument->profile_picture_path)
                        <div>
                            <p class="text-sm font-medium text-slate-300 mb-2">Profile Picture</p>
                            <a href="{{ asset('storage/'.$kycDocument->profile_picture_path) }}" target="_blank" class="block rounded-xl overflow-hidden border border-white/10 hover:border-cyan-400/50 transition w-48">
                                <img src="{{ asset('storage/'.$kycDocument->profile_picture_path) }}" alt="Profile Picture" class="w-full h-48 object-cover">
                            </a>
                        </div>
                    @endif

                    @if ($kycDocument->bank_statement_path)
                        <div>
                            <p class="text-sm font-medium text-slate-300 mb-2">Bank Statement</p>
                            <a href="{{ asset('storage/'.$kycDocument->bank_statement_path) }}" target="_blank" class="inline-flex items-center gap-2 rounded-xl border border-white/10 px-4 py-2 text-sm text-white hover:bg-white/10 transition">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                </svg>
                                View Bank Statement
                            </a>
                        </div>
                    @endif

                    @if ($kycDocument->payslip_path)
                        <div>
                            <p class="text-sm font-medium text-slate-300 mb-2">Payslip</p>
                            <a href="{{ asset('storage/'.$kycDocument->payslip_path) }}" target="_blank" class="inline-flex items-center gap-2 rounded-xl border border-white/10 px-4 py-2 text-sm text-white hover:bg-white/10 transition">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                </svg>
                                View Payslip
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

