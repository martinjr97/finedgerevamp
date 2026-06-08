@extends('layouts.admin')

@section('title', 'Reports | '.config('app.system_name'))

@section('content')
    <div class="space-y-8">
        @include('partials.admin.page-header', [
            'title' => 'Existing Reports',
            'description' => 'Access all portfolio, collections, risk, and operational reports from one place.'
        ])

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @php
                $reportLinks = [
                    ['label' => 'Arrears Report', 'description' => 'Overdue installments and PAR buckets.', 'route' => route('admin.reports.arrears')],
                    ['label' => 'Disbursements Report', 'description' => 'Loans disbursed by date, channel, and product.', 'route' => route('admin.reports.disbursements')],
                    ['label' => 'Collections Report', 'description' => 'Repayment transactions and totals.', 'route' => route('admin.reports.collections')],
                    ['label' => 'Collection Split', 'description' => 'Principal, interest, and fee allocation.', 'route' => route('admin.reports.collection-split')],
                    ['label' => 'Loan Book Report', 'description' => 'Portfolio composition and balances.', 'route' => route('admin.reports.loan-book')],
                    ['label' => 'Loan Performance', 'description' => 'Performance by product, group, and company.', 'route' => route('admin.reports.loan-performance')],
                    ['label' => 'Branch Report', 'description' => 'Branch-level portfolio, PAR, and activity.', 'route' => route('admin.reports.branches')],
                    ['label' => 'Risk Heatmap Dashboard', 'description' => 'Risk concentration by borrower, branch, and region.', 'route' => route('admin.reports.risk-heatmap')],
                ];
            @endphp

            @foreach ($reportLinks as $item)
                <a href="{{ $item['route'] }}" class="rounded-3xl border border-white/10 bg-white/5 p-5 shadow-lg transition hover:bg-white/10">
                    <h2 class="text-lg font-semibold text-white">{{ $item['label'] }}</h2>
                    <p class="mt-2 text-sm text-slate-300">{{ $item['description'] }}</p>
                </a>
            @endforeach
        </div>
    </div>
@endsection
