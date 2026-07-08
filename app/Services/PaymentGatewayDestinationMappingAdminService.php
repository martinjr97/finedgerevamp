<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Channel;
use App\Models\FinancialInstitution;
use App\Models\Loan;
use App\Models\PaymentGateway;
use App\Models\PaymentGatewayDestinationMapping;
use App\PaymentPlatform\Providers\CGrate\CGrateClient;
use App\PaymentPlatform\Providers\CGrate\CGrateException;
use App\PaymentPlatform\Services\PaymentGatewayDestinationMappingResolver;
use App\Support\CGrateIssuerDiscoveryCache;
use App\Support\PaymentGatewayDestinationMappingAdminUi;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PaymentGatewayDestinationMappingAdminService
{
    public function __construct(
        private readonly PaymentGatewayDestinationMappingResolver $resolver,
        private readonly CGrateIssuerDiscoveryCache $issuerCache,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function indexData(Request $request): array
    {
        $gateways = PaymentGateway::query()
            ->where('supports_disbursements', true)
            ->orderBy('name')
            ->get();

        $filters = $this->normalizeFilters($request, $gateways);
        $mappings = $this->filteredMappingsQuery($filters)
            ->with(['paymentGateway', 'financialInstitution', 'channel'])
            ->orderByDesc('updated_at')
            ->paginate(25)
            ->withQueryString();

        $coverageGateway = $gateways->firstWhere('id', (int) ($filters['gateway_id'] ?? 0))
            ?? $gateways->firstWhere('code', 'cgrate')
            ?? $gateways->first();

        $loanCountsByBank = $this->loanCountsByBank();
        $discoveredIssuers = $this->issuerCache->latest();

        return [
            'gateways' => $gateways,
            'filters' => $filters,
            'mappings' => $mappings,
            'summary' => $this->buildSummary($gateways, $coverageGateway, $discoveredIssuers),
            'coverageRows' => $this->buildCoverageRows($coverageGateway, $filters['environment'] ?? null, $loanCountsByBank, $discoveredIssuers),
            'discoveredIssuers' => $discoveredIssuers,
            'financialInstitutions' => FinancialInstitution::query()->active()->orderBy('name')->get(),
            'channels' => Channel::query()->where('is_active', true)->orderBy('name')->get(),
            'loanCountsByBank' => $loanCountsByBank,
            'canManage' => (bool) $request->user('admin')?->can('payment-gateways.manage'),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function createMapping(array $validated, ?Admin $admin): PaymentGatewayDestinationMapping
    {
        $this->assertNoDuplicateActiveMapping($validated);

        $mapping = PaymentGatewayDestinationMapping::query()->create([
            ...$this->mappingAttributes($validated),
            'last_verified_at' => ($validated['status'] ?? null) === 'active' ? now() : null,
        ]);

        $mapping->load(['paymentGateway', 'financialInstitution', 'channel']);

        $this->recordAudit('pg_dest_mapping.created', $mapping, null, $mapping->only([
            'payment_gateway_id',
            'destination_type',
            'financial_institution_id',
            'channel_id',
            'gateway_key',
            'gateway_value',
            'environment',
            'status',
        ]), $admin);

        return $mapping;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateMapping(PaymentGatewayDestinationMapping $mapping, array $validated, ?Admin $admin): PaymentGatewayDestinationMapping
    {
        $this->assertNoDuplicateActiveMapping($validated, $mapping->id);

        $oldValues = $mapping->only([
            'gateway_key',
            'gateway_value',
            'environment',
            'status',
            'notes',
        ]);

        $attributes = $this->mappingAttributes($validated);
        if (($validated['status'] ?? $mapping->status) === 'active' && $mapping->status !== 'active') {
            $attributes['last_verified_at'] = now();
        }

        $mapping->update($attributes);

        $this->recordAudit('pg_dest_mapping.updated', $mapping, $oldValues, $mapping->only([
            'gateway_key',
            'gateway_value',
            'environment',
            'status',
            'notes',
        ]), $admin);

        return $mapping->fresh(['paymentGateway', 'financialInstitution', 'channel']);
    }

    public function deleteMapping(PaymentGatewayDestinationMapping $mapping, ?Admin $admin): void
    {
        $oldValues = $mapping->only([
            'payment_gateway_id',
            'destination_type',
            'financial_institution_id',
            'channel_id',
            'gateway_key',
            'gateway_value',
            'environment',
            'status',
        ]);

        $mapping->delete();

        $this->recordAudit('pg_dest_mapping.deleted', $mapping, $oldValues, null, $admin);
    }

    /**
     * @return array{issuers: list<string>, discovered_at: string}
     */
    public function syncCGrateIssuers(?Admin $admin): array
    {
        $gateway = PaymentGateway::query()->where('code', 'cgrate')->firstOrFail();

        try {
            $result = app(CGrateClient::class)->getAvailableCashDepositIssuers();
        } catch (CGrateException $e) {
            throw ValidationException::withMessages([
                'sync' => 'Issuer discovery failed: '.$e->getMessage(),
            ]);
        }

        $issuers = array_values($result['issuers'] ?? []);
        $this->issuerCache->store($issuers, $result['raw'] ?? []);

        $this->recordAudit('pg_dest_mapping.synced', $gateway, null, [
            'issuer_count' => count($issuers),
            'issuers' => $issuers,
        ], $admin);

        return [
            'issuers' => $issuers,
            'discovered_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  Collection<int, PaymentGateway>  $gateways
     * @return array<string, mixed>
     */
    private function normalizeFilters(Request $request, Collection $gateways): array
    {
        return [
            'gateway_id' => $request->integer('gateway_id') ?: null,
            'environment' => $request->string('environment')->toString() ?: null,
            'destination_type' => $request->string('destination_type')->toString() ?: null,
            'status' => $request->string('status')->toString() ?: null,
            'search' => trim($request->string('search')->toString()),
            'default_gateway_id' => $gateways->firstWhere('code', 'cgrate')?->id ?? $gateways->first()?->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredMappingsQuery(array $filters): Builder
    {
        $query = PaymentGatewayDestinationMapping::query();

        if (! empty($filters['gateway_id'])) {
            $query->where('payment_gateway_id', (int) $filters['gateway_id']);
        }

        if (! empty($filters['environment'])) {
            if ($filters['environment'] === 'global') {
                $query->whereNull('environment');
            } else {
                $query->where('environment', $filters['environment']);
            }
        }

        if (! empty($filters['destination_type'])) {
            $query->where('destination_type', $filters['destination_type']);
        }

        if (! empty($filters['status']) && $filters['status'] !== 'missing') {
            $query->where('status', $filters['status']);
        }

        if (($filters['search'] ?? '') !== '') {
            $search = '%'.$filters['search'].'%';
            $query->where(function (Builder $sub) use ($search): void {
                $sub->where('gateway_key', 'like', $search)
                    ->orWhere('gateway_value', 'like', $search)
                    ->orWhereHas('financialInstitution', function (Builder $fi) use ($search): void {
                        $fi->where('name', 'like', $search)
                            ->orWhere('code', 'like', $search);
                    })
                    ->orWhereHas('channel', function (Builder $channel) use ($search): void {
                        $channel->where('name', 'like', $search)
                            ->orWhere('code', 'like', $search);
                    });
            });
        }

        return $query;
    }

    /**
     * @param  Collection<int, PaymentGateway>  $gateways
     * @param  array{issuers: list<string>, raw: array<string, mixed>, discovered_at: ?string}|null  $discoveredIssuers
     * @return array<string, int>
     */
    private function buildSummary(Collection $gateways, ?PaymentGateway $coverageGateway, ?array $discoveredIssuers): array
    {
        $mappingQuery = PaymentGatewayDestinationMapping::query();

        if ($coverageGateway) {
            $mappingQuery->where('payment_gateway_id', $coverageGateway->id);
        }

        $allMappings = (clone $mappingQuery)->get();
        $banks = FinancialInstitution::query()->active()->get();
        $environment = $coverageGateway
            ? $this->resolver->environmentForGateway($coverageGateway)
            : null;

        $missingBanks = 0;
        if ($coverageGateway) {
            foreach ($banks as $bank) {
                $resolved = $this->resolver->resolve(
                    $coverageGateway,
                    'bank',
                    $bank->id,
                    null,
                    PaymentGatewayDestinationMappingAdminUi::defaultGatewayKey($coverageGateway),
                    $environment
                );

                if (! $resolved['mapping'] || $resolved['mapping']->status !== 'active') {
                    $missingBanks++;
                }
            }
        }

        return [
            'total_gateways' => $gateways->count(),
            'configured_mappings' => $allMappings->count(),
            'missing_mappings' => $missingBanks,
            'verification_required' => $allMappings->where('status', 'verification_required')->count(),
            'active_mappings' => $allMappings->where('status', 'active')->count(),
            'discovered_issuers' => count($discoveredIssuers['issuers'] ?? []),
        ];
    }

    /**
     * @param  array<int, int>  $loanCountsByBank
     * @param  array{issuers: list<string>, raw: array<string, mixed>, discovered_at: ?string}|null  $discoveredIssuers
     * @return list<array<string, mixed>>
     */
    private function buildCoverageRows(
        ?PaymentGateway $gateway,
        ?string $filterEnvironment,
        array $loanCountsByBank,
        ?array $discoveredIssuers,
    ): array {
        if (! $gateway) {
            return [];
        }

        $banks = FinancialInstitution::query()->active()->orderBy('name')->get();
        $environment = $filterEnvironment && $filterEnvironment !== 'global'
            ? $filterEnvironment
            : $this->resolver->environmentForGateway($gateway);
        $gatewayKey = PaymentGatewayDestinationMappingAdminUi::defaultGatewayKey($gateway);
        $discovered = collect($discoveredIssuers['issuers'] ?? []);

        $rows = [];

        foreach ($banks as $bank) {
            $resolved = $this->resolver->resolve($gateway, 'bank', $bank->id, null, $gatewayKey, $environment);
            $mapping = $resolved['mapping'];
            $matchEnvironment = $resolved['matchEnvironment'];

            $status = 'missing';
            if ($mapping) {
                $status = $mapping->status;
                if (
                    $gateway->code === 'cgrate'
                    && $discovered->isNotEmpty()
                    && $mapping->status === 'active'
                    && ! $discovered->contains($mapping->gateway_value)
                ) {
                    $status = 'outdated';
                }
            }

            $rows[] = [
                'bank_id' => $bank->id,
                'bank_name' => $bank->name,
                'bank_code' => $bank->code,
                'mapping_exists' => $mapping !== null,
                'gateway_value' => $mapping?->gateway_value,
                'status' => $status,
                'environment' => $mapping?->environment ?? $matchEnvironment ?? '—',
                'last_verified_at' => $mapping?->last_verified_at,
                'loan_count' => $loanCountsByBank[$bank->id] ?? 0,
                'mapping_id' => $mapping?->id,
                'mapping_payload' => $mapping ? [
                    'id' => $mapping->id,
                    'payment_gateway_id' => $mapping->payment_gateway_id,
                    'destination_type' => $mapping->destination_type,
                    'financial_institution_id' => $mapping->financial_institution_id,
                    'channel_id' => $mapping->channel_id,
                    'gateway_key' => $mapping->gateway_key,
                    'gateway_value' => $mapping->gateway_value,
                    'environment' => $mapping->environment,
                    'status' => $mapping->status,
                    'notes' => $mapping->notes,
                    'gateway_code' => $gateway->code,
                ] : null,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, int>
     */
    private function loanCountsByBank(): array
    {
        return Loan::query()
            ->selectRaw('disbursement_financial_institution_id, COUNT(*) as aggregate')
            ->whereNotNull('disbursement_financial_institution_id')
            ->whereIn('status', ['approved', 'active', 'pending_disbursement', 'pending_approval'])
            ->groupBy('disbursement_financial_institution_id')
            ->pluck('aggregate', 'disbursement_financial_institution_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function mappingAttributes(array $validated): array
    {
        $attributes = [
            'payment_gateway_id' => (int) $validated['payment_gateway_id'],
            'destination_type' => $validated['destination_type'],
            'gateway_key' => $validated['gateway_key'],
            'gateway_value' => $validated['gateway_value'],
            'environment' => $validated['environment'] ?? null,
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? null,
            'financial_institution_id' => null,
            'channel_id' => null,
        ];

        if ($validated['destination_type'] === 'bank') {
            $attributes['financial_institution_id'] = (int) $validated['financial_institution_id'];
        } else {
            $attributes['channel_id'] = (int) $validated['channel_id'];
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function assertNoDuplicateActiveMapping(array $validated, ?int $ignoreId = null): void
    {
        if (($validated['status'] ?? '') !== 'active') {
            return;
        }

        $query = PaymentGatewayDestinationMapping::query()
            ->where('payment_gateway_id', (int) $validated['payment_gateway_id'])
            ->where('destination_type', $validated['destination_type'])
            ->where('gateway_key', $validated['gateway_key'])
            ->where('status', 'active');

        $environment = $validated['environment'] ?? null;
        if ($environment === null) {
            $query->whereNull('environment');
        } else {
            $query->where('environment', $environment);
        }

        if ($validated['destination_type'] === 'bank') {
            $query->where('financial_institution_id', (int) $validated['financial_institution_id']);
        } else {
            $query->where('channel_id', (int) $validated['channel_id']);
        }

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'status' => 'An active mapping already exists for this gateway, destination, environment, and gateway field. Deactivate the existing mapping or choose a different environment.',
            ]);
        }

        $duplicateQuery = PaymentGatewayDestinationMapping::query()
            ->where('payment_gateway_id', (int) $validated['payment_gateway_id'])
            ->where('destination_type', $validated['destination_type'])
            ->where('gateway_key', $validated['gateway_key'])
            ->where('financial_institution_id', $validated['destination_type'] === 'bank' ? (int) $validated['financial_institution_id'] : null)
            ->where('channel_id', $validated['destination_type'] === 'mobile_money' ? (int) $validated['channel_id'] : null);

        if ($environment === null) {
            $duplicateQuery->whereNull('environment');
        } else {
            $duplicateQuery->where('environment', $environment);
        }

        if ($ignoreId) {
            $duplicateQuery->where('id', '!=', $ignoreId);
        }

        if ($duplicateQuery->exists()) {
            throw ValidationException::withMessages([
                'financial_institution_id' => 'A mapping already exists for this gateway destination and environment.',
                'channel_id' => 'A mapping already exists for this gateway destination and environment.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function recordAudit(
        string $event,
        mixed $auditable,
        ?array $oldValues,
        ?array $newValues,
        ?Admin $admin,
    ): void {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        $request = request();
        $actorName = $admin?->full_name ?? $admin?->name ?? $admin?->email;
        $gateway = null;
        $destination = null;
        $environment = $newValues['environment'] ?? $oldValues['environment'] ?? null;

        if ($auditable instanceof PaymentGatewayDestinationMapping) {
            $gateway = $auditable->paymentGateway?->name ?? 'Gateway #'.$auditable->payment_gateway_id;
            $destination = PaymentGatewayDestinationMappingAdminUi::fineEdgeDestinationLabel($auditable);
        } elseif ($auditable instanceof PaymentGateway) {
            $gateway = $auditable->name;
        }

        AuditLog::withoutEvents(function () use (
            $event,
            $auditable,
            $oldValues,
            $newValues,
            $admin,
            $actorName,
            $request,
            $gateway,
            $destination,
            $environment,
        ): void {
            AuditLog::query()->create([
                'event' => $event,
                'auditable_type' => is_object($auditable) ? $auditable::class : PaymentGatewayDestinationMapping::class,
                'auditable_id' => is_object($auditable) && method_exists($auditable, 'getKey')
                    ? (string) $auditable->getKey()
                    : '0',
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'changed_fields' => array_values(array_unique(array_merge(
                    array_keys($oldValues ?? []),
                    array_keys($newValues ?? [])
                ))),
                'actor_type' => $admin ? $admin::class : null,
                'actor_id' => $admin ? (string) $admin->getKey() : null,
                'actor_name' => $actorName,
                'actor_guard' => 'admin',
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'url' => $request?->fullUrl(),
                'http_method' => $request?->method(),
                'metadata' => [
                    'route_name' => $request?->route()?->getName(),
                    'action_label' => match ($event) {
                        'pg_dest_mapping.created' => 'Created destination mapping',
                        'pg_dest_mapping.updated' => 'Updated destination mapping',
                        'pg_dest_mapping.deleted' => 'Deleted destination mapping',
                        'pg_dest_mapping.synced' => 'Synced cGrate issuers',
                        default => $event,
                    },
                    'gateway' => $gateway,
                    'destination' => $destination,
                    'environment' => $environment,
                ],
            ]);
        });
    }
}
