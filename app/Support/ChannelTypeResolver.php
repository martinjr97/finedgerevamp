<?php

namespace App\Support;

use App\Models\Channel;

class ChannelTypeResolver
{
    /**
     * @var list<string>
     */
    private const BANK_KEYWORDS = [
        'BANK',
        'ZANACO',
        'FNB',
        'ABSA',
        'STANBIC',
        'INDO',
        'NATSAVE',
        'ACCESS',
        'ECOBANK',
        'UBA',
    ];

    /**
     * @var list<string>
     */
    private const MOBILE_WALLET_KEYWORDS = [
        'MTN',
        'AIRTEL',
        'ZAMTEL',
    ];

    public static function infer(string $name, string $code): string
    {
        $haystack = strtoupper(trim($name.' '.$code));

        if ($haystack === '') {
            return Channel::TYPE_MOBILE_WALLET;
        }

        if (str_contains($haystack, 'CASH')) {
            return Channel::TYPE_CASH;
        }

        foreach (self::BANK_KEYWORDS as $keyword) {
            if (str_contains($haystack, $keyword)) {
                return Channel::TYPE_BANK;
            }
        }

        foreach (self::MOBILE_WALLET_KEYWORDS as $keyword) {
            if (str_contains($haystack, $keyword)) {
                return Channel::TYPE_MOBILE_WALLET;
            }
        }

        return Channel::TYPE_MOBILE_WALLET;
    }
}
