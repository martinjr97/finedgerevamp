<?php

namespace App\Support;

use App\Models\GeneralSetting;
use App\Models\LoanProduct;
use Illuminate\Support\Collection;

class PublicRegistrationPaths
{
    public const GOVERNMENT_WORKER = 'government_worker';

    public const COLLATERAL_BASED = 'collateral_based';

    /** Ministry dropdown sentinel when the employer is not in the list. */
    public const MINISTRY_OTHER = 'other';

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return [
            self::GOVERNMENT_WORKER,
            self::COLLATERAL_BASED,
        ];
    }

    public static function label(string $path): string
    {
        return match ($path) {
            self::GOVERNMENT_WORKER => 'Government Worker',
            self::COLLATERAL_BASED => 'Collateral Based',
            default => ucfirst(str_replace('_', ' ', $path)),
        };
    }

    public static function recommendedCategory(string $path): string
    {
        return match ($path) {
            self::GOVERNMENT_WORKER => 'government',
            self::COLLATERAL_BASED => 'collateral',
            default => '',
        };
    }

    /**
     * @return array<string, array{enabled: bool, loan_product_id: int|null}>
     */
    public static function normalize(?array $paths): array
    {
        $defaults = [
            self::GOVERNMENT_WORKER => ['enabled' => false, 'loan_product_id' => null],
            self::COLLATERAL_BASED => ['enabled' => false, 'loan_product_id' => null],
        ];

        if (! is_array($paths)) {
            return $defaults;
        }

        foreach (self::keys() as $key) {
            $entry = $paths[$key] ?? [];
            $defaults[$key] = [
                'enabled' => (bool) ($entry['enabled'] ?? false),
                'loan_product_id' => isset($entry['loan_product_id']) ? (int) $entry['loan_product_id'] : null,
            ];
        }

        return $defaults;
    }

    public static function fromSetting(?GeneralSetting $setting): array
    {
        return self::normalize($setting?->public_registration_paths);
    }

    public static function isPathEnabled(?GeneralSetting $setting, string $path): bool
    {
        $paths = self::fromSetting($setting);

        return ($paths[$path]['enabled'] ?? false) === true;
    }

    public static function hasAnyEnabledPath(?GeneralSetting $setting): bool
    {
        foreach (self::fromSetting($setting) as $entry) {
            if ($entry['enabled'] && $entry['loan_product_id']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Collection<int, string>
     */
    public static function enabledPathKeys(?GeneralSetting $setting): Collection
    {
        return collect(self::fromSetting($setting))
            ->filter(fn (array $entry) => $entry['enabled'] && $entry['loan_product_id'])
            ->keys();
    }

    public static function resolveProduct(?GeneralSetting $setting, string $path): ?LoanProduct
    {
        if (! self::isPathEnabled($setting, $path)) {
            return null;
        }

        $paths = self::fromSetting($setting);
        $productId = $paths[$path]['loan_product_id'] ?? null;

        if (! $productId) {
            return null;
        }

        return LoanProduct::query()
            ->where('id', $productId)
            ->where('is_active', true)
            ->first();
    }
}
