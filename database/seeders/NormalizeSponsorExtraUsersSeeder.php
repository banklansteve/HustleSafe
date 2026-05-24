<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class NormalizeSponsorExtraUsersSeeder extends Seeder
{
    /**
     * Normalize sponsor.extra* users to firstname.lastname@mail.com and first_name_last_name usernames.
     */
    public function run(): void
    {
        $users = User::query()
            ->where(function ($query): void {
                $query->where('email', 'like', 'sponsor.extra%@hustlesafe.test')
                    ->orWhere('username', 'like', 'sponsor_extra_%');
            })
            ->orderBy('id')
            ->get();

        if ($users->isEmpty()) {
            $this->command?->warn('No sponsor.extra users found to normalize.');

            return;
        }

        foreach ($users as $user) {
            $this->normalizeUser($user);
        }

        $this->command?->info("Normalized {$users->count()} sponsor user(s).");
    }

    public static function normalizeUser(User $user): void
    {
        $first = self::namePart((string) ($user->first_name ?: Str::before((string) $user->name, ' ')));
        $last = self::namePart((string) ($user->last_name ?: Str::after((string) $user->name, ' ')));

        if ($first === '' || $last === '') {
            return;
        }

        $emailLocal = "{$first}.{$last}";
        $usernameBase = "{$first}_{$last}";

        $username = self::uniqueUsername($usernameBase, (int) $user->id);
        $slug = self::uniqueSlug(Str::slug($username) ?: $username, (int) $user->id);

        $user->forceFill([
            'email' => self::uniqueEmail($emailLocal, (int) $user->id),
            'username' => $username,
            'slug' => $slug,
        ])->save();
    }

    private static function namePart(string $name): string
    {
        return Str::lower(Str::slug(trim($name), ''));
    }

    private static function uniqueEmail(string $local, int $userId): string
    {
        $candidate = "{$local}@mail.com";
        $suffix = 1;

        while (
            User::query()
                ->where('email', $candidate)
                ->where('id', '!=', $userId)
                ->exists()
        ) {
            $candidate = "{$local}".sprintf('%02d', $suffix).'@mail.com';
            $suffix++;
        }

        return $candidate;
    }

    private static function uniqueUsername(string $base, int $userId): string
    {
        $candidate = Str::limit($base, 60, '');
        $suffix = 1;

        while (
            User::query()
                ->where('username', $candidate)
                ->where('id', '!=', $userId)
                ->exists()
        ) {
            $candidate = Str::limit($base, 56, '').'_'.sprintf('%02d', $suffix);
            $suffix++;
        }

        return $candidate;
    }

    private static function uniqueSlug(string $base, int $userId): string
    {
        $candidate = $base !== '' ? $base : 'profile';
        $suffix = 1;

        while (
            User::query()
                ->where('slug', $candidate)
                ->where('id', '!=', $userId)
                ->exists()
        ) {
            $candidate = $base.'-'.sprintf('%02d', $suffix);
            $suffix++;
        }

        return $candidate;
    }
}
