<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\SeededUserIdentity;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class NormalizeSponsorExtraUsersSeeder extends Seeder
{
    /**
     * Normalize legacy sponsor.extra* users to the canonical seeded identity format.
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
            self::normalizeUser($user);
        }

        $this->command?->info("Normalized {$users->count()} sponsor user(s).");
    }

    public static function normalizeUser(User $user): void
    {
        $first = (string) ($user->first_name ?: Str::before((string) $user->name, ' '));
        $last = (string) ($user->last_name ?: Str::after((string) $user->name, ' '));

        if (trim($first) === '' || trim($last) === '') {
            return;
        }

        $identity = SeededUserIdentity::forNames($first, $last, (int) $user->id);

        $user->forceFill($identity)->save();
    }
}
