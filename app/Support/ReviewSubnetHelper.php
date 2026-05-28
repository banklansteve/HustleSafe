<?php

namespace App\Support;

class ReviewSubnetHelper
{
    public static function fromIp(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            if (count($parts) !== 4) {
                return null;
            }

            return sprintf('%s.%s.%s.0/24', $parts[0], $parts[1], $parts[2]);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $normalized = inet_ntop(inet_pton($ip));
            if ($normalized === false) {
                return null;
            }
            $segments = explode(':', str_replace('::', ':0000:', $normalized));
            $prefix = implode(':', array_slice(array_pad($segments, 4, '0'), 0, 4));

            return 'v6:'.$prefix.'::/48';
        }

        return null;
    }
}
