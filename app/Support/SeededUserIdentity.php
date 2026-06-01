<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Canonical identity fields for seeded / demo users.
 *
 * - username: first name only, with a numeric suffix when taken (e.g. john, john2)
 * - slug: firstname-lastname, with a numeric suffix when taken (e.g. john-doe, john-doe-02)
 * - email: firstname.lastname@mail.com, with a numeric suffix when taken
 */
final class SeededUserIdentity
{
    /**
     * @return array{email: string, username: string, slug: string}
     */
    public static function forNames(string $firstName, string $lastName, ?int $excludeUserId = null): array
    {
        $first = self::namePart($firstName);
        $last = self::namePart($lastName);

        if ($first === '' || $last === '') {
            throw new \InvalidArgumentException('First and last name are required to build seeded user identity.');
        }

        return [
            'email' => self::uniqueEmail($first, $last, $excludeUserId),
            'username' => self::uniqueUsername($first, $excludeUserId),
            'slug' => self::uniqueSlug($first, $last, $excludeUserId),
        ];
    }

    public static function namePart(string $name): string
    {
        return Str::lower(Str::slug(trim($name), ''));
    }

    public static function uniqueEmail(string $first, string $last, ?int $excludeUserId = null): string
    {
        $local = "{$first}.{$last}";
        $candidate = "{$local}@mail.com";
        $suffix = 2;

        while (self::emailTaken($candidate, $excludeUserId)) {
            $candidate = "{$local}".sprintf('%02d', $suffix).'@mail.com';
            $suffix++;
        }

        return $candidate;
    }

    public static function uniqueUsername(string $first, ?int $excludeUserId = null): string
    {
        $base = Str::limit($first !== '' ? $first : 'user', 48, '');
        $candidate = $base;
        $suffix = 2;

        while (self::usernameTaken($candidate, $excludeUserId)) {
            $candidate = Str::limit($base, 44, '').$suffix;
            $suffix++;
        }

        return $candidate;
    }

    public static function uniqueSlug(string $first, string $last, ?int $excludeUserId = null): string
    {
        $base = Str::slug("{$first}-{$last}") ?: 'profile';
        $candidate = $base;
        $suffix = 2;

        while (self::slugTaken($candidate, $excludeUserId)) {
            $candidate = $base.'-'.sprintf('%02d', $suffix);
            $suffix++;
        }

        return $candidate;
    }

    private static function emailTaken(string $email, ?int $excludeUserId): bool
    {
        return User::query()
            ->where('email', $email)
            ->when($excludeUserId !== null, fn ($q) => $q->where('id', '!=', $excludeUserId))
            ->exists();
    }

    private static function usernameTaken(string $username, ?int $excludeUserId): bool
    {
        return User::query()
            ->where('username', $username)
            ->when($excludeUserId !== null, fn ($q) => $q->where('id', '!=', $excludeUserId))
            ->exists();
    }

    private static function slugTaken(string $slug, ?int $excludeUserId): bool
    {
        return User::query()
            ->where('slug', $slug)
            ->when($excludeUserId !== null, fn ($q) => $q->where('id', '!=', $excludeUserId))
            ->exists();
    }
}
