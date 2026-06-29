<?php

namespace App\Support;

/**
 * Turns raw browser user-agent strings into short, human labels.
 */
class UserAgentFriendly
{
    /**
     * @return array{label: string, browser: ?string, os: ?string, device: ?string}
     */
    public static function details(?string $userAgent): array
    {
        if ($userAgent === null || trim($userAgent) === '') {
            return [
                'label' => __('Unknown device'),
                'browser' => null,
                'os' => null,
                'device' => null,
            ];
        }

        $ua = strtolower($userAgent);

        return [
            'label' => self::label($userAgent),
            'browser' => self::detectBrowser($ua),
            'os' => self::detectOs($ua),
            'device' => self::detectDevice($ua),
        ];
    }

    public static function label(?string $userAgent): string
    {
        if ($userAgent === null || trim($userAgent) === '') {
            return __('Unknown device');
        }

        $ua = strtolower($userAgent);

        $os = self::detectOs($ua);
        $browser = self::detectBrowser($ua);
        $device = self::detectDevice($ua);

        if ($device !== null && $browser !== null) {
            return sprintf('%s · %s', $device, $browser);
        }

        if ($browser !== null && $os !== null) {
            return sprintf('%s on %s', $browser, $os);
        }

        return $browser ?? $os ?? __('Web browser');
    }

    protected static function detectDevice(string $ua): ?string
    {
        if (str_contains($ua, 'iphone')) {
            return __('iPhone');
        }
        if (str_contains($ua, 'ipad') || (str_contains($ua, 'macintosh') && str_contains($ua, 'mobile'))) {
            return __('iPad');
        }
        if (str_contains($ua, 'android') && str_contains($ua, 'mobile')) {
            return __('Android phone');
        }
        if (str_contains($ua, 'android')) {
            return __('Android tablet');
        }

        return null;
    }

    protected static function detectOs(string $ua): ?string
    {
        if (str_contains($ua, 'windows nt')) {
            return __('Windows');
        }
        if (str_contains($ua, 'mac os x') || str_contains($ua, 'macintosh')) {
            return __('macOS');
        }
        if (str_contains($ua, 'android')) {
            return __('Android');
        }
        if (str_contains($ua, 'iphone') || str_contains($ua, 'ipad') || str_contains($ua, 'ios')) {
            return __('iOS');
        }
        if (str_contains($ua, 'linux')) {
            return __('Linux');
        }

        return null;
    }

    protected static function detectBrowser(string $ua): ?string
    {
        if (str_contains($ua, 'edg/')) {
            return __('Edge');
        }
        if (str_contains($ua, 'opr/') || str_contains($ua, 'opera')) {
            return __('Opera');
        }
        if (str_contains($ua, 'chrome/') && ! str_contains($ua, 'edg')) {
            return __('Chrome');
        }
        if (str_contains($ua, 'safari/') && ! str_contains($ua, 'chrome')) {
            return __('Safari');
        }
        if (str_contains($ua, 'firefox/')) {
            return __('Firefox');
        }
        if (str_contains($ua, 'samsungbrowser')) {
            return __('Samsung Internet');
        }

        return null;
    }
}
