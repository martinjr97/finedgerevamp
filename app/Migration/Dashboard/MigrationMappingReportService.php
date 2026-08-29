<?php

namespace App\Migration\Dashboard;

use App\Migration\LegacyConnection;
use App\Migration\Phases\Support\LegacyClientClassifier;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\LoanProduct;
use App\Models\Market;
use App\Migration\Phases\MigrationEntityMapRepository;
use App\Migration\Phases\Support\CompanyMatcher;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class MigrationMappingReportService
{
    public function __construct(
        private readonly LegacyClientClassifier $clientClassifier,
        private readonly CompanyMatcher $companyMatcher,
    ) {}

    public function paginateEntityMaps(string $entityType, array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $query = DB::table('migration_entity_maps')->where('entity_type', $entityType)->orderByDesc('id');

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('legacy_identifier', $search)
                    ->orWhere('target_id', $search)
                    ->orWhere('mapping_method', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage)->through(function ($map) use ($entityType) {
            return (object) [
                'legacy_id' => $map->legacy_identifier,
                'legacy_secondary' => $map->legacy_secondary,
                'target_id' => $map->target_id,
                'target_name' => $this->resolveTargetName($entityType, (int) $map->target_id),
                'mapping_method' => $map->mapping_method,
                'confidence' => $map->mapping_confidence,
                'status' => $this->mapStatusLabel($map->mapping_method),
                'metadata' => json_decode($map->metadata ?? '{}', true),
            ];
        });
    }

    public function paginateCompanies(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        try {
            LegacyConnection::configureFromLegacyEnvFile();
            $legacy = LegacyConnection::connection();
            $clients = $legacy->table('clients')->orderBy('id')->get();
        } catch (\Throwable) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }

        $rows = collect();
        foreach ($clients as $client) {
            $clientArr = (array) $client;
            $legacyId = (int) $clientArr['id'];
            $customerCount = (int) $legacy->table('customers')->where('client_id', $legacyId)->count();
            $clientLoans = $legacy->table('loans')->where('client_id', $legacyId)->get();
            $activeCustomers = (int) $legacy->table('customers as c')
                ->join('users as u', 'u.id', '=', 'c.user_id')
                ->where('c.client_id', $legacyId)
                ->whereExists(function ($q) {
                    $q->selectRaw('1')->from('loans as l')->whereColumn('l.user_id', 'c.user_id')->where('l.status_code', '301');
                })
                ->count();
            $activeLoans = (int) $legacy->table('loans')->where('client_id', $legacyId)->where('status_code', '301')->count();

            $classification = $this->clientClassifier->classify($clientArr, $clientLoans, $customerCount);
            $map = DB::table('migration_entity_maps')
                ->where('entity_type', MigrationEntityMapRepository::TYPE_COMPANY)
                ->where('legacy_identifier', (string) $legacyId)
                ->first();
            $staging = DB::table('migration_companies')->where('legacy_client_id', $legacyId)->orderByDesc('id')->first();

            $rows->push((object) [
                'legacy_client_id' => $legacyId,
                'legacy_name' => $clientArr['company_name'] ?? '—',
                'product_type' => $clientArr['product_type'] ?? '—',
                'customers' => $customerCount,
                'active_customers' => $activeCustomers,
                'loans' => $clientLoans->count(),
                'active_loans' => $activeLoans,
                'classification' => $classification,
                'migration_action' => $staging->migration_status ?? ($map ? 'matched_existing' : 'pending'),
                'target_company_id' => $map->target_id ?? $staging->mapped_company_id ?? null,
                'target_company_name' => ($map || $staging?->mapped_company_id)
                    ? Company::find($map->target_id ?? $staging->mapped_company_id)?->name
                    : null,
            ]);
        }

        if (! empty($filters['classification'])) {
            $rows = $rows->filter(fn ($r) => $r->classification === $filters['classification']);
        }

        if (! empty($filters['search'])) {
            $search = strtolower($filters['search']);
            $rows = $rows->filter(fn ($r) => str_contains(strtolower((string) $r->legacy_name), $search)
                || (string) $r->legacy_client_id === $filters['search']);
        }

        $page = max(1, (int) ($filters['page'] ?? 1));
        $slice = $rows->slice(($page - 1) * $perPage, $perPage)->values();

        return new \Illuminate\Pagination\LengthAwarePaginator($slice, $rows->count(), $perPage, $page, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function marketeerSummary(): array
    {
        $markets = DB::table('migration_entity_maps')
            ->where('entity_type', MigrationEntityMapRepository::TYPE_MARKET)
            ->get()
            ->map(function ($map) {
                $market = Market::find($map->target_id);

                return [
                    'legacy_market_id' => $map->legacy_identifier,
                    'target_market_id' => $map->target_id,
                    'target_market_name' => $market?->name,
                    'mapping_method' => $map->mapping_method,
                ];
            });

        $marketeerCustomers = Customer::query()
            ->whereHas('loanProduct', fn ($q) => $q->where('code', 'MARK-001'))
            ->with(['company', 'customerGroup', 'marketeerCustomerDetail.market'])
            ->limit(500)
            ->get()
            ->map(function (Customer $customer) {
                $metadata = $customer->metadata ?? [];

                return [
                    'customer_id' => $customer->id,
                    'name' => $customer->full_name,
                    'company_id' => $customer->company_id,
                    'company_linked_incorrectly' => $customer->company_id !== null,
                    'market_mapped' => (bool) $customer->marketeerCustomerDetail?->market_id,
                    'group_mapped' => (bool) $customer->customer_group_id,
                    'legacy_market_id' => $metadata['legacy_market_id'] ?? null,
                ];
            });

        return [
            'product' => LoanProduct::where('code', 'MARK-001')->first(['id', 'code', 'name']),
            'group' => CustomerGroup::where('code', 'MRKT-LEGACY')->first(['id', 'code', 'name']),
            'markets' => $markets,
            'customers' => $marketeerCustomers,
            'exceptions' => $marketeerCustomers->filter(fn ($c) => $c['company_linked_incorrectly'] || ! $c['market_mapped']),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function identityResolutions(): array
    {
        $rows = [];
        foreach (\App\Migration\Phases\Support\CustomerIdentityResolutionRegistry::approved() as $nrc => $resolution) {
            $aliases = $resolution['alias_legacy_user_ids'] ?? [];
            $rows[] = [
                'nrc' => MigrationDashboardSupport::maskNrc($nrc),
                'nrc_full' => $nrc,
                'legacy_users' => array_merge([(int) $resolution['primary_legacy_user_id']], $aliases),
                'primary_legacy_user_id' => (int) $resolution['primary_legacy_user_id'],
                'alias_legacy_user_ids' => $aliases,
                'target_customer_id' => $resolution['target_customer_id'] ?? null,
                'classification' => $resolution['classification'],
                'reason' => $resolution['reason'] ?? '',
                'status' => 'approved',
            ];
        }

        return $rows;
    }

    private function resolveTargetName(string $entityType, int $targetId): ?string
    {
        return match ($entityType) {
            MigrationEntityMapRepository::TYPE_COMPANY => Company::find($targetId)?->name,
            MigrationEntityMapRepository::TYPE_PRODUCT => LoanProduct::find($targetId)?->name,
            MigrationEntityMapRepository::TYPE_CUSTOMER => Customer::find($targetId)?->full_name,
            MigrationEntityMapRepository::TYPE_MARKET => Market::find($targetId)?->name,
            MigrationEntityMapRepository::TYPE_CUSTOMER_GROUP => CustomerGroup::find($targetId)?->name,
            default => null,
        };
    }

    private function mapStatusLabel(string $method): string
    {
        return match (true) {
            str_contains($method, 'created') => 'CREATED',
            str_contains($method, 'matched') || str_contains($method, 'existing') => 'MATCHED_EXISTING',
            str_contains($method, 'manual') => 'MANUAL_REVIEW',
            str_contains($method, 'skip') => 'SKIPPED',
            default => strtoupper($method),
        };
    }
}
