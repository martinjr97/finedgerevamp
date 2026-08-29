<?php

namespace App\Migration\Phases\Support;

/**
 * Classifies legacy clients for company migration decisions.
 *
 * Only MOU_REAL_EMPLOYER clients should become target companies.
 */
class LegacyClientClassifier
{
    public const MOU_REAL_EMPLOYER = 'MOU_REAL_EMPLOYER';

    public const GOVERNMENT_PRODUCT_PLACEHOLDER = 'GOVERNMENT_PRODUCT_PLACEHOLDER';

    public const MARKETEER_PRODUCT_PLACEHOLDER = 'MARKETEER_PRODUCT_PLACEHOLDER';

    public const CHARACTER_PRODUCT_PLACEHOLDER = 'CHARACTER_PRODUCT_PLACEHOLDER';

    public const REAL_COMPANY_NON_MOU = 'REAL_COMPANY_NON_MOU';

    public const UNUSED = 'UNUSED';

    public const AMBIGUOUS_MANUAL_REVIEW = 'AMBIGUOUS_MANUAL_REVIEW';

    /**
     * Legacy clients that are product/agent buckets — must never become target companies.
     *
     * @var list<int>
     */
    public const PRODUCT_PLACEHOLDER_CLIENT_IDS = [2, 6, 7, 8, 36];

    public function __construct(
        private readonly CompanyMatcher $companyMatcher,
    ) {}

    /**
     * @param  array<string, mixed>  $legacyClient
     * @param  \Illuminate\Support\Collection<int, object>  $clientLoans
     */
    public function classify(array $legacyClient, $clientLoans, int $customerCount): string
    {
        if ($customerCount === 0) {
            return self::UNUSED;
        }

        $legacyClientId = (int) ($legacyClient['id'] ?? 0);
        if ($this->isProductPlaceholderLegacyClientId($legacyClientId)) {
            return match ($legacyClientId) {
                8 => self::GOVERNMENT_PRODUCT_PLACEHOLDER,
                36 => self::MARKETEER_PRODUCT_PLACEHOLDER,
                default => self::CHARACTER_PRODUCT_PLACEHOLDER,
            };
        }

        if ($this->companyMatcher->isMarketeerPlaceholderClient($legacyClient)) {
            return self::MARKETEER_PRODUCT_PLACEHOLDER;
        }

        if ($this->companyMatcher->isGovernmentPlaceholder($legacyClient, $clientLoans)) {
            return self::GOVERNMENT_PRODUCT_PLACEHOLDER;
        }

        if ($this->isCharacterPlaceholderClient($legacyClient, $clientLoans)) {
            return self::CHARACTER_PRODUCT_PLACEHOLDER;
        }

        if ($this->isTestOrInternalClient($legacyClient)) {
            return self::AMBIGUOUS_MANUAL_REVIEW;
        }

        if (($legacyClient['product_type'] ?? null) === 'salary_based'
            && $this->hasPredominantlySalaryLoans($clientLoans)) {
            return self::MOU_REAL_EMPLOYER;
        }

        return self::AMBIGUOUS_MANUAL_REVIEW;
    }

    public function shouldCreateOrMatchCompany(string $classification): bool
    {
        return $classification === self::MOU_REAL_EMPLOYER;
    }

    public function isProductPlaceholderLegacyClientId(int $legacyClientId): bool
    {
        return in_array($legacyClientId, self::PRODUCT_PLACEHOLDER_CLIENT_IDS, true);
    }

    public function shouldCreateOrMatchCompanyForClient(array $legacyClient, $clientLoans, int $customerCount): bool
    {
        return $this->shouldCreateOrMatchCompany(
            $this->classify($legacyClient, $clientLoans, $customerCount)
        );
    }

    public function companySkipReason(string $classification): ?string
    {
        return match ($classification) {
            self::GOVERNMENT_PRODUCT_PLACEHOLDER => 'SKIP_GOVERNMENT_PLACEHOLDER',
            self::MARKETEER_PRODUCT_PLACEHOLDER => 'SKIP_MARKETEER_PLACEHOLDER',
            self::CHARACTER_PRODUCT_PLACEHOLDER => 'SKIP_CHARACTER_PLACEHOLDER',
            self::UNUSED => 'SKIP_UNUSED',
            self::AMBIGUOUS_MANUAL_REVIEW => 'MANUAL_REVIEW',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $legacyClient
     * @param  \Illuminate\Support\Collection<int, object>  $clientLoans
     */
    public function isCharacterPlaceholderClient(array $legacyClient, $clientLoans): bool
    {
        if (($legacyClient['product_type'] ?? null) === 'character_based') {
            return true;
        }

        $name = strtolower((string) ($legacyClient['company_name'] ?? ''));
        if (str_contains($name, 'character')) {
            return true;
        }

        if ($clientLoans->isEmpty()) {
            return false;
        }

        $characterLike = $clientLoans->filter(
            fn ($l) => ! (bool) ($l->salary_based ?? false) && ! (bool) ($l->gvnt_loan ?? false)
        )->count();

        return $characterLike > ($clientLoans->count() * 0.5);
    }

    /**
     * @param  array<string, mixed>  $legacyClient
     */
    public function isTestOrInternalClient(array $legacyClient): bool
    {
        $name = strtolower((string) ($legacyClient['company_name'] ?? ''));

        return str_contains($name, 'finedge test')
            || str_contains($name, 'finedge stuff')
            || preg_match('/\btest\b/', $name) === 1;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $clientLoans
     */
    private function hasPredominantlySalaryLoans($clientLoans): bool
    {
        if ($clientLoans->isEmpty()) {
            return true;
        }

        $salaryCount = $clientLoans->filter(fn ($l) => (bool) ($l->salary_based ?? false))->count();

        return $salaryCount >= ($clientLoans->count() * 0.5);
    }

    /**
     * Customer-level company bucket from client classification.
     *
     * @param  array<string, mixed>|null  $legacyClient
     */
    public function customerCompanyBucket(
        string $clientClassification,
        bool $isMarketeerCustomer,
        bool $hasCompanyMap,
    ): string {
        if ($isMarketeerCustomer || $clientClassification === self::MARKETEER_PRODUCT_PLACEHOLDER) {
            return 'MARKETEER_INTENTIONAL_NO_COMPANY';
        }

        return match ($clientClassification) {
            self::GOVERNMENT_PRODUCT_PLACEHOLDER => 'GOVERNMENT_INTENTIONAL_NO_COMPANY',
            self::CHARACTER_PRODUCT_PLACEHOLDER => 'CHARACTER_INTENTIONAL_NO_COMPANY',
            self::MOU_REAL_EMPLOYER => $hasCompanyMap ? 'MOU_COMPANY_LINKED' : 'COMPANY_MAPPING_PENDING',
            self::AMBIGUOUS_MANUAL_REVIEW => 'MANUAL_REVIEW',
            self::UNUSED => 'OTHER_LEGITIMATE_NO_COMPANY',
            default => 'MANUAL_REVIEW',
        };
    }
}
