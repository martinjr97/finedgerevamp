<?php

namespace App\Migration\Phases\Support;

use App\Migration\Phases\Support\LegacyClientClassifier;
use App\Models\Company;

class CompanyMatcher
{
    public function isMarketeerPlaceholderClient(array $legacyClient): bool
    {
        if (($legacyClient['product_type'] ?? null) !== 'marketize_based') {
            return false;
        }

        $name = strtolower(trim((string) ($legacyClient['company_name'] ?? '')));

        return str_contains($name, 'marketize')
            || str_contains($name, 'marketeer')
            || preg_match('/^mkt-\d+/i', (string) ($legacyClient['reg_number'] ?? '')) === 1;
    }

    /** @var list<string> */
    private const GOVERNMENT_PLACEHOLDER_PATTERNS = [
        'government',
        'grz',
        'ministry',
        'public service',
        'civil service',
    ];

    /**
     * @param  array<string, mixed>  $legacyClient
     * @param  \Illuminate\Support\Collection<int, object>  $clientLoans
     */
    public function isGovernmentPlaceholder(array $legacyClient, $clientLoans): bool
    {
        if (($legacyClient['product_type'] ?? null) !== 'salary_based') {
            return false;
        }

        $name = strtolower((string) ($legacyClient['company_name'] ?? ''));
        foreach (self::GOVERNMENT_PLACEHOLDER_PATTERNS as $pattern) {
            if (str_contains($name, $pattern)) {
                return true;
            }
        }

        if ($clientLoans->isEmpty()) {
            return false;
        }

        $govCount = $clientLoans->filter(fn ($l) => (bool) ($l->gvnt_loan ?? false))->count();

        return $govCount > 0 && $govCount === $clientLoans->count();
    }

    /**
     * @param  array<string, mixed>  $legacyClient
     * @return array{company: ?Company, method: ?string, confidence: string}
     */
    public function matchExisting(array $legacyClient): array
    {
        $legacyId = (int) ($legacyClient['id'] ?? 0);

        if (in_array($legacyId, LegacyClientClassifier::PRODUCT_PLACEHOLDER_CLIENT_IDS, true)) {
            return ['company' => null, 'method' => null, 'confidence' => 'LOW'];
        }

        $bySettings = Company::query()
            ->whereJsonContains('settings->legacy_client_id', $legacyId)
            ->first();
        if ($bySettings) {
            return ['company' => $bySettings, 'method' => 'explicit_migration_mapping', 'confidence' => 'HIGH'];
        }

        $reg = trim((string) ($legacyClient['reg_number'] ?? ''));
        if ($reg !== '') {
            $byReg = Company::query()->where('registration_number', $reg)->first();
            if ($byReg) {
                return ['company' => $byReg, 'method' => 'registration_number', 'confidence' => 'HIGH'];
            }
        }

        $name = $this->normalizeName((string) ($legacyClient['company_name'] ?? ''));
        if ($name !== '') {
            $candidates = Company::query()->get()->filter(
                fn (Company $c) => $this->normalizeName($c->name) === $name
            );
            if ($candidates->count() === 1) {
                return ['company' => $candidates->first(), 'method' => 'normalized_name', 'confidence' => 'MEDIUM'];
            }
        }

        $byCode = Company::query()->where('code', 'LEG-'.$legacyId)->first();
        if ($byCode) {
            return ['company' => $byCode, 'method' => 'legacy_code', 'confidence' => 'HIGH'];
        }

        return ['company' => null, 'method' => null, 'confidence' => 'LOW'];
    }

    public function normalizeName(string $name): string
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/\s+/', ' ', $name) ?? $name;
        $name = preg_replace('/[^a-z0-9 ]/', '', $name) ?? $name;

        return trim($name);
    }
}
