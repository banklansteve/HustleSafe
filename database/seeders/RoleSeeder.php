<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Platform roles — four user types used across auth, policies, and admin tooling.
     *
     * @var list<array{slug: string, name: string, description: string}>
     */
    private array $roles = [
        [
            'slug' => Role::SLUG_SUPER_ADMIN,
            'name' => 'Super Admin',
            'description' => 'Full platform control — financial audit, quest boosts, super-admin settings, and unrestricted admin access.',
        ],
        [
            'slug' => Role::SLUG_STAFF_ADMIN,
            'name' => 'Operations Staff Admin',
            'description' => 'Operational staff with admin console access — support, moderation, verification, and day-to-day platform operations.',
        ],
        [
            'slug' => Role::SLUG_FREELANCER,
            'name' => 'Freelancer (Pro)',
            'description' => 'Hustlers and freelancers who submit proposals, deliver work, and receive payouts. Pro tier unlocks extra quota and visibility.',
        ],
        [
            'slug' => Role::SLUG_CLIENT,
            'name' => 'Client (Sponsor)',
            'description' => 'Clients and sponsors who post quests, fund escrow, review delivery, and manage contracts.',
        ],
    ];

    public function run(): void
    {
        foreach ($this->roles as $role) {
            Role::query()->updateOrCreate(
                ['slug' => $role['slug']],
                [
                    'name' => $role['name'],
                    'description' => $role['description'],
                ],
            );
        }

        $this->command?->info('Roles seeded: '.Role::query()->count().' roles ('.implode(', ', Role::query()->orderBy('id')->pluck('slug')->all()).').');
    }
}
