@extends('legacy.migration-dashboard.layout')

@section('dashboard-content')
    @include('partials.admin.page-header', ['title' => 'Company Mapping', 'description' => 'Legacy client classification and target company decisions.'])

    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Legacy client ID or name" class="rounded-xl border px-3 py-2 text-sm">
        <select name="classification" class="rounded-xl border px-3 py-2 text-sm">
            <option value="">All classifications</option>
            @foreach(['MOU_REAL_EMPLOYER', 'GOVERNMENT_PRODUCT_PLACEHOLDER', 'MARKETEER_PRODUCT_PLACEHOLDER', 'CHARACTER_PRODUCT_PLACEHOLDER', 'REAL_COMPANY_NON_MOU', 'UNUSED', 'AMBIGUOUS_MANUAL_REVIEW'] as $class)
                <option value="{{ $class }}" @selected(($filters['classification'] ?? '') === $class)>{{ $class }}</option>
            @endforeach
        </select>
        <button class="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white">Filter</button>
    </form>

    <div class="rounded-2xl border bg-white p-6 shadow-sm overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b bg-slate-100 text-left text-xs uppercase tracking-wide text-slate-700">
                    <th class="px-3 py-2">Legacy Client</th>
                    <th class="px-3 py-2">Name</th>
                    <th class="px-3 py-2">Product Type</th>
                    <th class="px-3 py-2">Customers</th>
                    <th class="px-3 py-2">Active Cust.</th>
                    <th class="px-3 py-2">Loans</th>
                    <th class="px-3 py-2">Active Loans</th>
                    <th class="px-3 py-2">Classification</th>
                    <th class="px-3 py-2">Action</th>
                    <th class="px-3 py-2">Target Company</th>
                </tr>
            </thead>
            <tbody>
                @forelse($companies as $company)
                    <tr class="border-b hover:bg-slate-50">
                        <td class="px-3 py-2">{{ $company->legacy_client_id }}</td>
                        <td class="px-3 py-2">{{ $company->legacy_name }}</td>
                        <td class="px-3 py-2">{{ $company->product_type }}</td>
                        <td class="px-3 py-2">{{ $company->customers }}</td>
                        <td class="px-3 py-2">{{ $company->active_customers }}</td>
                        <td class="px-3 py-2">{{ $company->loans }}</td>
                        <td class="px-3 py-2">{{ $company->active_loans }}</td>
                        <td class="px-3 py-2">@include('legacy.migration-dashboard.partials.company-classification', ['classification' => $company->classification])</td>
                        <td class="px-3 py-2">{{ $company->migration_action }}</td>
                        <td class="px-3 py-2">
                            @if($company->target_company_id)
                                #{{ $company->target_company_id }} {{ $company->target_company_name }}
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="px-3 py-6 text-center text-slate-500">No legacy clients found (legacy DB may be unavailable).</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $companies->withQueryString()->links() }}</div>
    </div>
@endsection
