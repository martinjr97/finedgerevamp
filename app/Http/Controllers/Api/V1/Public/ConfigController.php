<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\GeneralSetting;
use App\Support\PublicRegistrationPaths;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class ConfigController extends Controller
{
    /**
     * Get public application configuration
     */
    public function index(): JsonResponse
    {
        $setting = GeneralSetting::query()->first();
        $defaultLogoPath = 'img/logo.png';
        $defaultFaviconPath = 'img/favicon_io/favicon.ico';

        return response()->json([
            'success' => true,
            'data' => [
                'system_name' => config('app.system_name'),
                'system_tagline' => config('app.system_tagline', 'Loan Management System'),
                'logo_url' => $this->assetFromConfig('app.system_logo_path', $defaultLogoPath),
                'website_url' => config('app.website_url', config('app.url')),
                'favicon_url' => $this->assetFromConfig('app.favicon_path', $defaultFaviconPath),
                'support_email' => config('app.support_email'),
                'support_phone' => config('app.support_phone'),
                'support_address' => [
                    'line1' => config('app.support_address_line1'),
                    'city' => config('app.support_city'),
                    'country' => config('app.support_country'),
                ],
                'customer_registration' => [
                    'enabled' => $setting?->allow_customer_registration ?? false,
                    'paths' => PublicRegistrationPaths::fromSetting($setting),
                ],
            ],
        ]);
    }

    private function assetFromConfig(string $configKey, string $defaultPath): string
    {
        $path = (string) config($configKey, $defaultPath);

        if (Str::startsWith($path, ['http://', 'https://', '//'])) {
            return $path;
        }

        return asset(ltrim($path, '/'));
    }

    /**
     * Check if customer registration is enabled
     */
    public function registrationStatus(): JsonResponse
    {
        $setting = GeneralSetting::query()->first();

        $isEnabled = $setting?->allow_customer_registration ?? false;

        return response()->json([
            'success' => true,
            'data' => [
                'enabled' => $isEnabled,
                'paths' => $isEnabled ? PublicRegistrationPaths::fromSetting($setting) : PublicRegistrationPaths::normalize(null),
            ],
        ]);
    }
}
