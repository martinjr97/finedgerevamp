<?php

namespace App\Support;

class DeviceParser
{
    /**
     * Parse device information from user agent string
     */
    public static function parse(string $userAgent): array
    {
        $deviceType = self::getDeviceType($userAgent);
        $deviceName = self::getDeviceName($userAgent);
        $browser = self::getBrowser($userAgent);
        $browserVersion = self::getBrowserVersion($userAgent, $browser);
        $os = self::getOS($userAgent);
        $osVersion = self::getOSVersion($userAgent, $os);

        return [
            'device_type' => $deviceType,
            'device_name' => $deviceName,
            'browser' => $browser,
            'browser_version' => $browserVersion,
            'os' => $os,
            'os_version' => $osVersion,
        ];
    }

    /**
     * Get device type (mobile, tablet, desktop)
     */
    private static function getDeviceType(string $userAgent): ?string
    {
        $userAgent = strtolower($userAgent);

        // Check for mobile devices
        if (preg_match('/mobile|android|iphone|ipod|blackberry|iemobile|opera mini/i', $userAgent)) {
            // Check if it's a tablet
            if (preg_match('/tablet|ipad|playbook|silk/i', $userAgent)) {
                return 'tablet';
            }
            return 'mobile';
        }

        return 'desktop';
    }

    /**
     * Get device name
     */
    private static function getDeviceName(string $userAgent): ?string
    {
        $userAgent = strtolower($userAgent);

        // iPhone
        if (preg_match('/iphone\s*(\d+)/i', $userAgent, $matches)) {
            return 'iPhone ' . $matches[1];
        }
        if (preg_match('/iphone/i', $userAgent)) {
            return 'iPhone';
        }

        // iPad
        if (preg_match('/ipad/i', $userAgent)) {
            return 'iPad';
        }

        // Android devices
        if (preg_match('/android.*;\s*([^)]+)\)/i', $userAgent, $matches)) {
            $device = trim($matches[1]);
            // Clean up common device names
            $device = preg_replace('/\s+Build.*/i', '', $device);
            return $device ?: 'Android Device';
        }

        // Samsung
        if (preg_match('/samsung|galaxy/i', $userAgent)) {
            if (preg_match('/sm-([a-z0-9]+)/i', $userAgent, $matches)) {
                return 'Samsung Galaxy ' . strtoupper($matches[1]);
            }
            return 'Samsung Device';
        }

        // Generic mobile
        if (preg_match('/mobile/i', $userAgent)) {
            return 'Mobile Device';
        }

        return null;
    }

    /**
     * Get browser name
     */
    private static function getBrowser(string $userAgent): ?string
    {
        $userAgent = strtolower($userAgent);

        if (preg_match('/chrome/i', $userAgent) && !preg_match('/edg|opr|opera/i', $userAgent)) {
            return 'Chrome';
        }
        if (preg_match('/safari/i', $userAgent) && !preg_match('/chrome/i', $userAgent)) {
            return 'Safari';
        }
        if (preg_match('/firefox/i', $userAgent)) {
            return 'Firefox';
        }
        if (preg_match('/edg/i', $userAgent)) {
            return 'Edge';
        }
        if (preg_match('/opr|opera/i', $userAgent)) {
            return 'Opera';
        }
        if (preg_match('/msie|trident/i', $userAgent)) {
            return 'Internet Explorer';
        }

        return 'Unknown';
    }

    /**
     * Get browser version
     */
    private static function getBrowserVersion(string $userAgent, ?string $browser): ?string
    {
        if (!$browser) {
            return null;
        }

        $browserLower = strtolower($browser);
        $userAgent = strtolower($userAgent);

        if ($browserLower === 'chrome' && preg_match('/chrome\/([\d.]+)/i', $userAgent, $matches)) {
            return $matches[1];
        }
        if ($browserLower === 'safari' && preg_match('/version\/([\d.]+)/i', $userAgent, $matches)) {
            return $matches[1];
        }
        if ($browserLower === 'firefox' && preg_match('/firefox\/([\d.]+)/i', $userAgent, $matches)) {
            return $matches[1];
        }
        if ($browserLower === 'edge' && preg_match('/edg\/([\d.]+)/i', $userAgent, $matches)) {
            return $matches[1];
        }
        if ($browserLower === 'opera' && preg_match('/(?:opr|opera)\/([\d.]+)/i', $userAgent, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get operating system
     */
    private static function getOS(string $userAgent): ?string
    {
        $userAgent = strtolower($userAgent);

        if (preg_match('/iphone|ipad|ipod/i', $userAgent)) {
            return 'iOS';
        }
        if (preg_match('/android/i', $userAgent)) {
            return 'Android';
        }
        if (preg_match('/windows/i', $userAgent)) {
            return 'Windows';
        }
        if (preg_match('/macintosh|mac os x/i', $userAgent)) {
            return 'macOS';
        }
        if (preg_match('/linux/i', $userAgent)) {
            return 'Linux';
        }

        return 'Unknown';
    }

    /**
     * Get OS version
     */
    private static function getOSVersion(string $userAgent, ?string $os): ?string
    {
        if (!$os) {
            return null;
        }

        $osLower = strtolower($os);
        $userAgent = strtolower($userAgent);

        if ($osLower === 'ios' && preg_match('/os\s*([\d_]+)/i', $userAgent, $matches)) {
            return str_replace('_', '.', $matches[1]);
        }
        if ($osLower === 'android' && preg_match('/android\s*([\d.]+)/i', $userAgent, $matches)) {
            return $matches[1];
        }
        if ($osLower === 'windows' && preg_match('/windows\s*(?:nt\s*)?([\d.]+)/i', $userAgent, $matches)) {
            return $matches[1];
        }
        if ($osLower === 'macos' && preg_match('/mac\s*os\s*x\s*([\d_]+)/i', $userAgent, $matches)) {
            return str_replace('_', '.', $matches[1]);
        }

        return null;
    }

    /**
     * Get location from IP address (simplified - in production, use a service like ipapi.co or maxmind)
     */
    public static function getLocationFromIp(string $ip): array
    {
        // Skip private/local IPs
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return [
                'country' => null,
                'region' => null,
                'city' => null,
            ];
        }

        // For now, return null - in production, integrate with IP geolocation service
        // Example: https://ipapi.co/{ip}/json/ or MaxMind GeoIP2
        // You can use Laravel's Http facade to call an API
        
        try {
            // Using ipapi.co (free tier: 1000 requests/day)
            $response = \Illuminate\Support\Facades\Http::timeout(2)->get("https://ipapi.co/{$ip}/json/");
            
            if ($response->successful()) {
                $data = $response->json();
                return [
                    'country' => $data['country_name'] ?? null,
                    'region' => $data['region'] ?? null,
                    'city' => $data['city'] ?? null,
                ];
            }
        } catch (\Exception $e) {
            // Silently fail - location is optional
        }

        return [
            'country' => null,
            'region' => null,
            'city' => null,
        ];
    }
}

