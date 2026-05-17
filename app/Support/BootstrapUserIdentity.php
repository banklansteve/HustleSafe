<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Generates stable public identifiers for a new user row (mirrors legacy migration logic).
 */
final class BootstrapUserIdentity
{
    /**
     * @return array{username: string, slug: string, uid: string}
     */
    public static function forEmailAndDisplayName(string $email, string $displayName): array
    {
        $baseName = trim($displayName) !== '' ? $displayName : 'user';
        $slugBase = Str::slug($baseName) ?: 'user';
        $slug = self::uniqueSlug($slugBase);

        $emailLocal = explode('@', strtolower(trim($email)))[0] ?? 'user';
        $username = Str::slug($emailLocal, '');
        $username = $username !== '' ? substr($username, 0, 60) : 'user';
        $username = self::uniqueUsername($username);

        $uid = self::uniqueUid();

        return compact('username', 'slug', 'uid');
    }

    private static function uniqueSlug(string $slugBase): string
    {
        $slug = $slugBase;
        $n = 0;
        while (User::query()->where('slug', $slug)->exists()) {
            $slug = $slugBase.'-'.Str::lower(Str::random(4));
            $n++;
            if ($n > 50) {
                $slug = $slugBase.'-'.Str::lower(Str::random(8));
                break;
            }
        }

        return $slug;
    }

    private static function uniqueUsername(string $username): string
    {
        $u = $username;
        $i = 0;
        while (User::query()->where('username', $u)->exists()) {
            $u = substr($username, 0, 50).$i;
            $i++;
        }

        return $u;
    }

    private static function uniqueUid(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $uid = '';
            for ($j = 0; $j < 8; $j++) {
                $uid .= $chars[random_int(0, strlen($chars) - 1)];
            }
        } while (User::query()->where('uid', $uid)->exists());

        return $uid;
    }
}
