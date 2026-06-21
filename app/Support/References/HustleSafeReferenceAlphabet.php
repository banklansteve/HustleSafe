<?php

namespace App\Support\References;

/**
 * Crockford-style alphabet: excludes 0/O, 1/I/L to avoid transcription errors.
 */
final class HustleSafeReferenceAlphabet
{
    public const CHARSET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    public static function random(int $length): string
    {
        $max = strlen(self::CHARSET) - 1;
        $out = '';

        for ($i = 0; $i < $length; $i++) {
            $out .= self::CHARSET[random_int(0, $max)];
        }

        return $out;
    }

    /**
     * Deterministic 4-character code from a seed (legacy quest bridge, never exposes DB ids).
     */
    public static function hash4FromString(string $seed): string
    {
        $digest = hash('sha256', $seed, true);
        $max = strlen(self::CHARSET) - 1;
        $out = '';

        for ($i = 0; $i < 4; $i++) {
            $out .= self::CHARSET[ord($digest[$i]) % ($max + 1)];
        }

        return $out;
    }

    public static function normalize(string $value): string
    {
        return strtoupper(trim($value));
    }
}
