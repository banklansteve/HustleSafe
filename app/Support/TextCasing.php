<?php

namespace App\Support;

final class TextCasing
{
    /**
     * Title-style words (names, cities, headlines).
     */
    public static function titleWords(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = preg_replace('/\s+/u', ' ', trim($value));
        if ($trimmed === '') {
            return '';
        }

        return mb_convert_case($trimmed, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Capitalise the first alphabetic character in the string (address lines, bios).
     */
    public static function capitalizeFirstAlphabetic(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = trim($value);
        if ($s === '') {
            return '';
        }
        $len = mb_strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $ch = mb_substr($s, $i, 1);
            if ($ch !== '' && ctype_alpha($ch)) {
                $upper = mb_strtoupper($ch, 'UTF-8');

                return mb_substr($s, 0, $i).$upper.mb_substr($s, $i + 1);
            }
        }

        return $s;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $keysTitleWords
     * @param  list<string>  $keysFirstAlpha
     * @return array<string, mixed>
     */
    public static function patchUserProfile(array $data, array $keysTitleWords, array $keysFirstAlpha): array
    {
        foreach ($keysTitleWords as $key) {
            if (! array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '') {
                continue;
            }
            if (is_string($data[$key])) {
                $data[$key] = self::titleWords($data[$key]);
            }
        }
        foreach ($keysFirstAlpha as $key) {
            if (! array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '') {
                continue;
            }
            if (is_string($data[$key])) {
                $data[$key] = self::capitalizeFirstAlphabetic($data[$key]);
            }
        }

        return $data;
    }
}
