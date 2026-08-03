<?php

namespace App\Support\Pdf;

use App\Models\Company;
use Illuminate\Support\Str;

class FinancialDocumentBranding
{
    /**
     * @return array{
     *     organization_name: string,
     *     tagline: string|null,
     *     logo_data_uri: string|null,
     *     address_lines: list<string>,
     *     support_phone: string|null,
     *     support_email: string|null,
     *     website_url: string|null,
     *     display_website: string|null,
     *     include_signature_blocks: bool
     * }
     */
    public static function resolve(?Company $company = null): array
    {
        $organizationName = (string) ($company?->name ?: config('app.system_name', 'Havencrest'));
        $tagline = self::nullableString(config('app.system_tagline'));

        $addressLines = collect([
            $company?->address_line1 ?: config('app.support_address_line1'),
            $company?->address_line2,
            collect([
                $company?->city ?: config('app.support_city'),
                $company?->state,
                $company?->postal_code,
            ])->filter(fn ($part) => filled(trim((string) $part)))->implode(', '),
            $company?->country ?: config('app.support_country'),
        ])
            ->map(fn ($line) => self::nullableString($line))
            ->filter()
            ->reject(fn (string $line) => self::isPlaceholderAddress($line))
            ->values()
            ->all();

        $supportPhone = self::nullableContact(
            $company?->contact_phone ?: config('app.support_phone')
        );
        $supportEmail = self::nullableContact(
            $company?->contact_email ?: config('app.support_email')
        );

        $websiteUrl = self::nullableString(config('app.website_url'));
        $displayWebsite = $websiteUrl
            ? preg_replace('#^https?://#', '', rtrim($websiteUrl, '/'))
            : null;

        return [
            'organization_name' => $organizationName,
            'tagline' => $tagline,
            'logo_data_uri' => self::resolveLogoDataUri(),
            'address_lines' => $addressLines,
            'support_phone' => $supportPhone,
            'support_email' => $supportEmail,
            'website_url' => $websiteUrl,
            'display_website' => $displayWebsite ? (string) $displayWebsite : null,
            // Default false in FineEdge so signatures only appear when explicitly enabled.
            'include_signature_blocks' => (bool) config('app.pdf_include_signature_blocks', false),
        ];
    }

    public static function formatMoney(float|int|string|null $amount): string
    {
        return 'ZMW '.number_format((float) $amount, 2);
    }

    public static function scheduleStatusLabel(?string $status): string
    {
        return match ($status) {
            'paid' => 'Paid',
            'paid_early' => 'Paid Early',
            'partial' => 'Partially Paid',
            'overdue' => 'Overdue',
            'upcoming' => 'Upcoming',
            'due' => 'Due',
            'waived' => 'Waived',
            'cancelled' => 'Cancelled',
            default => $status ? Str::title(str_replace('_', ' ', $status)) : '—',
        };
    }

    public static function scheduleStatusClass(?string $status): string
    {
        return match ($status) {
            'paid', 'paid_early' => 'status-paid',
            'partial' => 'status-partial',
            'overdue' => 'status-overdue',
            'upcoming', 'due' => 'status-upcoming',
            'waived' => 'status-waived',
            'cancelled' => 'status-cancelled',
            default => 'status-upcoming',
        };
    }

    public static function loanStatusLabel(?string $status): string
    {
        return match ($status) {
            'pending_approval' => 'Pending Approval',
            'approved' => 'Approved',
            'active' => 'Active',
            'completed' => 'Closed',
            'settled' => 'Closed',
            'defaulted' => 'Defaulted',
            'cancelled' => 'Cancelled',
            default => $status ? Str::title(str_replace('_', ' ', $status)) : '—',
        };
    }

    private static function resolveLogoDataUri(): ?string
    {
        $rawLogoPath = trim((string) config('app.system_logo_path', ''));
        $candidates = array_values(array_filter(array_unique([
            $rawLogoPath,
            'img/logo.png',
            'img/logo_only.png',
        ])));

        foreach ($candidates as $candidate) {
            if ($candidate === '' || Str::startsWith($candidate, ['http://', 'https://', '//'])) {
                continue;
            }

            $path = public_path(ltrim($candidate, '/'));
            if (! is_file($path)) {
                continue;
            }

            $contents = file_get_contents($path);
            if ($contents === false) {
                continue;
            }

            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $mime = match ($extension) {
                'jpg', 'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'svg' => 'image/svg+xml',
                'webp' => 'image/webp',
                default => 'image/png',
            };

            return 'data:'.$mime.';base64,'.base64_encode($contents);
        }

        return null;
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private static function nullableContact(mixed $value): ?string
    {
        $value = self::nullableString($value);
        if ($value === null || self::isPlaceholderContact($value)) {
            return null;
        }

        return $value;
    }

    private static function isPlaceholderContact(string $value): bool
    {
        $normalized = strtolower(preg_replace('/[\s\-\(\)]+/', '', $value) ?? '');

        if (in_array($normalized, ['na', 'n/a', 'none', 'null', 'undefined'], true)) {
            return true;
        }

        // Default config placeholder: +260 000 000 000
        if (preg_match('/^\+?2600+$/', $normalized) === 1) {
            return true;
        }

        if (str_contains(strtolower($value), '000 000 000')) {
            return true;
        }

        return false;
    }

    private static function isPlaceholderAddress(string $value): bool
    {
        return in_array(strtolower(trim($value)), [
            'customer support office',
            'n/a',
            'na',
            'none',
        ], true);
    }
}
