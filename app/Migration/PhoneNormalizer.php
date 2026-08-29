<?php

namespace App\Migration;

class PhoneNormalizer
{
    public function normalize(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === null || $digits === '') {
            return null;
        }

        if (str_starts_with($digits, '260') && strlen($digits) === 12) {
            return $digits;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '260'.substr($digits, 1);
        }

        if (strlen($digits) === 9) {
            return '260'.$digits;
        }

        return $digits;
    }

    public function inferProvider(?string $normalized): ?string
    {
        if ($normalized === null || strlen($normalized) < 5) {
            return null;
        }

        $prefix = substr($normalized, 0, 5);
        if (in_array($prefix, ['26097', '26077'], true)) {
            return 'AIRTEL_MONEY';
        }
        if (in_array($prefix, ['26096', '26076'], true)) {
            return 'MTN_MONEY';
        }
        if (in_array($prefix, ['26095', '26075'], true)) {
            return 'ZAMTEL_MONEY';
        }

        return null;
    }
}
