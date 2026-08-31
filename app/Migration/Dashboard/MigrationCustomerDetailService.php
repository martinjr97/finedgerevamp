<?php

namespace App\Migration\Dashboard;

use App\Migration\LegacyConnection;
use App\Migration\Phases\Support\CustomerIdentityResolutionRegistry;
use App\Migration\Phases\Support\IdentityResolutionCatalog;
use App\Models\Customer;
use Illuminate\Support\Collection;

class MigrationCustomerDetailService
{
    /**
     * @return array<string, mixed>
     */
    public function build(int $legacyUserId, ?object $staging): array
    {
        $legacy = $this->legacySnapshot($legacyUserId);
        $exception = $staging->exception ?? null;
        $candidates = $this->candidateMatches($exception, $legacy, $staging);
        $exceptionMeta = MigrationDashboardSupport::customerExceptionMeta($exception);

        $nrc = $legacy['national_id'] ?? null;
        $duplicateLegacyUsers = $this->duplicateLegacyUsersByNrc($nrc, $legacyUserId);
        $identityResolution = $nrc ? IdentityResolutionCatalog::forNrc($nrc) : null;
        $identityResolveUrl = null;
        if ($nrc && $duplicateLegacyUsers !== [] && $identityResolution === null) {
            $identityResolveUrl = route(
                'legacy.migration-dashboard.identity.resolve',
                IdentityResolutionCatalog::encodeNrcKey($nrc),
            );
        }

        $mapEligibleExceptions = [
            'national_id', 'uncertain_national_id', 'email', 'uncertain_email',
        ];

        return [
            'legacy' => $legacy,
            'review' => [
                'is_manual_review' => ($staging->migration_status ?? '') === 'manual_review',
                'exception_code' => $exception,
                'title' => $exceptionMeta['title'],
                'description' => $exceptionMeta['description'],
                'guidance' => $exceptionMeta['guidance'],
                'confidence' => $staging->confidence ?? null,
                'migration_run_id' => $staging->migration_run_id ?? null,
                'candidate_matches' => $candidates,
                'duplicate_legacy_users' => $duplicateLegacyUsers,
                'identity_resolution' => $identityResolution,
                'identity_resolve_url' => $identityResolveUrl,
                'can_map_to_existing' => ($staging->migration_status ?? '') === 'manual_review'
                    && in_array($exception, $mapEligibleExceptions, true)
                    && $candidates !== [],
                'identity_registry' => CustomerIdentityResolutionRegistry::forUser($legacyUserId),
            ],
        ];
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array<int, string>
     */
    public function legacyNamesForRows(Collection $rows): array
    {
        $userIds = $rows->pluck('legacy_user_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        if ($userIds === []) {
            return [];
        }

        try {
            LegacyConnection::configureFromLegacyEnvFile();
            $legacy = LegacyConnection::connection();
            $users = $legacy->table('users')->whereIn('id', $userIds)->get(['id', 'fname', 'lname', 'oname']);

            $names = [];
            foreach ($users as $user) {
                $names[(int) $user->id] = $this->formatLegacyName((array) $user);
            }

            return $names;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function legacySnapshot(int $legacyUserId): array
    {
        $snapshot = [
            'available' => false,
            'legacy_user_id' => $legacyUserId,
            'legacy_customer_id' => null,
            'legacy_client_id' => null,
            'first_name' => null,
            'last_name' => null,
            'full_name' => null,
            'email' => null,
            'phone' => null,
            'national_id' => null,
            'employee_number' => null,
            'position' => null,
            'employer_name' => null,
            'product_type' => null,
            'department' => null,
            'address_line1' => null,
            'city' => null,
            'gross_salary' => null,
            'net_salary' => null,
            'active_loan_count' => 0,
            'total_loan_count' => 0,
        ];

        try {
            LegacyConnection::configureFromLegacyEnvFile();
            $legacy = LegacyConnection::connection();

            $user = (array) $legacy->table('users')->where('id', $legacyUserId)->first();
            if ($user === []) {
                return $snapshot;
            }

            $customer = (array) $legacy->table('customers')->where('user_id', $legacyUserId)->first();
            $clientId = (int) ($customer['client_id'] ?? $user['client_id'] ?? 0);
            $client = $clientId
                ? (array) $legacy->table('clients')->where('id', $clientId)->first()
                : [];

            $loanQuery = $legacy->table('loans')->where('user_id', $legacyUserId);
            $totalLoans = (int) (clone $loanQuery)->count();
            $activeLoans = (int) (clone $loanQuery)->where('status_code', '301')->count();

            $snapshot = [
                'available' => true,
                'legacy_user_id' => $legacyUserId,
                'legacy_customer_id' => $customer['id'] ?? null,
                'legacy_client_id' => $clientId ?: null,
                'first_name' => trim((string) ($user['fname'] ?? '')) ?: null,
                'last_name' => trim((string) ($user['lname'] ?? '')) ?: null,
                'full_name' => $this->formatLegacyName($user),
                'email' => $user['email'] ?? null,
                'phone' => $user['phone_number'] ?? null,
                'national_id' => $customer['nrc'] ?? $user['nrc'] ?? null,
                'employee_number' => $user['emp_number'] ?? null,
                'position' => $customer['position'] ?? $user['position'] ?? null,
                'employer_name' => $client['company_name'] ?? null,
                'product_type' => $client['product_type'] ?? null,
                'department' => $customer['department'] ?? $customer['ministry'] ?? null,
                'address_line1' => $customer['physical_address'] ?? $customer['address'] ?? $user['address'] ?? null,
                'city' => $customer['town'] ?? $customer['city'] ?? null,
                'gross_salary' => is_numeric($customer['gross_salary'] ?? null) ? (float) $customer['gross_salary'] : null,
                'net_salary' => is_numeric($customer['net_pay'] ?? null) ? (float) $customer['net_pay'] : null,
                'active_loan_count' => $activeLoans,
                'total_loan_count' => $totalLoans,
            ];
        } catch (\Throwable) {
            // Legacy DB unavailable — caller may fall back to staging raw_context.
        }

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $legacy
     * @return list<array<string, mixed>>
     */
    private function candidateMatches(?string $exception, array $legacy, ?object $staging = null): array
    {
        if ($exception === null || $exception === '') {
            return [];
        }

        $ctx = [];
        if ($staging !== null) {
            $ctx = json_decode($staging->raw_context ?? '{}', true) ?: [];
        }

        $customers = collect();

        if (in_array($exception, ['national_id', 'uncertain_national_id'], true)) {
            $nrc = trim((string) ($legacy['national_id'] ?? $ctx['national_id'] ?? ''));
            if ($nrc !== '') {
                $customers = Customer::query()->where('national_id', $nrc)->with('company')->get();
            }
        } elseif (in_array($exception, ['email', 'uncertain_email'], true)) {
            $email = trim((string) ($legacy['email'] ?? $ctx['email'] ?? ''));
            if ($email !== '' && ! str_contains($email, '@migration.local')) {
                $customers = Customer::query()->where('email', $email)->with('company')->get();
            }
        }

        return $customers->map(function (Customer $customer) use ($legacy) {
            return [
                'id' => $customer->id,
                'full_name' => $customer->full_name,
                'email' => $customer->email,
                'national_id' => $customer->national_id,
                'employee_number' => $customer->employee_number,
                'company' => $customer->company?->name,
                'admin_url' => route('admin.customers.show', $customer->id),
                'map_fields' => MigrationCustomerMapFieldCatalog::comparisonRows($legacy, $customer),
            ];
        })->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function duplicateLegacyUsersByNrc(?string $nrc, int $excludeUserId): array
    {
        $nrc = trim((string) $nrc);
        if ($nrc === '') {
            return [];
        }

        try {
            LegacyConnection::configureFromLegacyEnvFile();
            $legacy = LegacyConnection::connection();

            $rows = $legacy->table('customers as c')
                ->join('users as u', 'u.id', '=', 'c.user_id')
                ->where('c.nrc', $nrc)
                ->where('c.user_id', '!=', $excludeUserId)
                ->get(['c.user_id', 'u.fname', 'u.lname', 'u.oname', 'u.email']);

            return $rows->map(fn ($row) => [
                'legacy_user_id' => (int) $row->user_id,
                'full_name' => $this->formatLegacyName((array) $row),
                'email' => $row->email,
                'dashboard_url' => route('legacy.migration-dashboard.customers.show', $row->user_id),
            ])->values()->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $user
     */
    private function formatLegacyName(array $user): string
    {
        $parts = array_filter([
            trim((string) ($user['fname'] ?? '')),
            trim((string) ($user['oname'] ?? '')),
            trim((string) ($user['lname'] ?? '')),
        ]);

        return $parts !== [] ? implode(' ', $parts) : 'Unknown';
    }
}
