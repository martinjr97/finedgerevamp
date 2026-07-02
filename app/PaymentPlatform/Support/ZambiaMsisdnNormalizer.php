<?php

namespace App\PaymentPlatform\Support;

final class ZambiaMsisdnNormalizer
{
    public static function normalizeForCGrate(string $input, string $format = 'local'): string
    {
        $raw = trim($input);
        $raw = preg_replace('/[\s\-()]/', '', $raw) ?? $raw;

        if ($raw === '') {
            throw new \InvalidArgumentException('Mobile number is required.');
        }

        if (str_starts_with($raw, '+')) {
            $raw = substr($raw, 1);
        }

        if (! preg_match('/^\d+$/', $raw)) {
            throw new \InvalidArgumentException('Mobile number must contain digits only.');
        }

        $nsn = null;
        if (str_starts_with($raw, '260') && strlen($raw) === 12) {
            $nsn = substr($raw, 3);
        } elseif (str_starts_with($raw, '0') && strlen($raw) === 10) {
            $nsn = substr($raw, 1);
        } elseif (strlen($raw) === 9) {
            $nsn = $raw;
        }

        if (! is_string($nsn) || strlen($nsn) !== 9) {
            throw new \InvalidArgumentException('Invalid mobile number length.');
        }

        $prefix2 = substr($nsn, 0, 2);
        $allowed = ['95', '96', '97', '98', '75', '76', '77', '55', '56', '57'];
        if (! in_array($prefix2, $allowed, true)) {
            throw new \InvalidArgumentException('Invalid mobile number prefix.');
        }

        return match ($format) {
            'local' => '0'.$nsn,
            'international_without_plus' => '260'.$nsn,
            default => throw new \InvalidArgumentException('Invalid MSISDN format configuration.'),
        };
    }
}
