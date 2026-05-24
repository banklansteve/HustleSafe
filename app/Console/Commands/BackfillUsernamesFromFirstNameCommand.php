<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class BackfillUsernamesFromFirstNameCommand extends Command
{
    protected $signature = 'users:backfill-usernames {--dry-run : Preview changes without saving}';

    protected $description = 'Fill empty usernames from first name (or name) with numeric suffixes for uniqueness';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $updated = 0;

        User::query()
            ->orderBy('id')
            ->chunkById(200, function ($users) use ($dryRun, &$updated): void {
                foreach ($users as $user) {
                    if (filled($user->username)) {
                        continue;
                    }

                    $base = $this->baseFromUser($user);
                    $username = $this->uniqueUsername($base, (int) $user->id);

                    if ($dryRun) {
                        $this->line("User #{$user->id} ({$user->email}) → @{$username}");

                        continue;
                    }

                    $user->forceFill(['username' => $username])->save();
                    $updated++;
                }
            });

        $this->info($dryRun
            ? 'Dry run complete. Re-run without --dry-run to apply.'
            : "Updated {$updated} user(s).");

        return self::SUCCESS;
    }

    private function baseFromUser(User $user): string
    {
        $first = trim((string) ($user->first_name ?? ''));
        if ($first !== '') {
            return Str::slug($first, '');
        }

        $name = trim((string) ($user->name ?? ''));
        if ($name !== '') {
            $parts = preg_split('/\s+/', $name) ?: [];

            return Str::slug((string) ($parts[0] ?? $name), '');
        }

        if (filled($user->email)) {
            return Str::slug(Str::before((string) $user->email, '@'), '');
        }

        return 'user';
    }

    private function uniqueUsername(string $base, int $userId): string
    {
        $base = Str::limit($base !== '' ? $base : 'user', 48, '');
        $candidate = $base;
        $suffix = 1;

        while (
            User::query()
                ->where('username', $candidate)
                ->where('id', '!=', $userId)
                ->exists()
        ) {
            $candidate = Str::limit($base, 44, '').$suffix;
            $suffix++;
        }

        return $candidate;
    }
}
