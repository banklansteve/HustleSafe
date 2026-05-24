<?php

namespace Database\Seeders;

use App\Models\LocalGovernment;
use App\Models\State;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SponsorUsersSeeder extends Seeder
{
    /**
     * Seed 10 additional sponsor users (email verified). Does not modify or remove existing users.
     */
    public function run(): void
    {
        $states = State::query()->with('localGovernments')->get()->filter(
            fn (State $state) => $state->localGovernments->isNotEmpty(),
        )->values();

        if ($states->isEmpty()) {
            $this->command?->warn('States/LGAs are missing. Run NigeriaGeoSeeder first.');

            return;
        }

        $password = Hash::make('password');
        $roleId = 4;

        for ($i = 1; $i <= 10; $i++) {
            $state = $states[($i - 1) % $states->count()];
            $lga = $state->localGovernments->random();
            $first = fake()->firstName();
            $last = fake()->lastName();
            $legacyEmail = sprintf('sponsor.extra%02d@hustlesafe.test', $i);

            $user = User::query()->firstOrCreate(
                ['email' => $legacyEmail],
                [
                    'username' => sprintf('sponsor_extra_%02d', $i),
                    'slug' => sprintf('sponsor-extra-%02d', $i),
                    'uid' => sprintf('SE%06d', $i),
                    'first_name' => $first,
                    'last_name' => $last,
                    'name' => $first.' '.$last,
                    'phone' => '090'.fake()->unique()->numerify('########'),
                    'gender' => fake()->randomElement(['female', 'male']),
                    'date_of_birth' => fake()->dateTimeBetween('-55 years', '-24 years')->format('Y-m-d'),
                    'company_name' => fake()->optional(0.4)->company(),
                    'address_line' => fake()->streetAddress(),
                    'city' => $lga->name,
                    'state_id' => $state->id,
                    'local_government_id' => $lga->id,
                    'account_type' => 'sponsor',
                    'role_id' => $roleId,
                    'job_title' => fake()->randomElement([
                        'Project Sponsor',
                        'Facilities Manager',
                        'Operations Lead',
                        'Home Owner',
                        'Small Business Owner',
                    ]),
                    'company_size' => fake()->randomElement(['1-5', '6-20', '21-50', '51-200']),
                    'onboarding_step' => 5,
                    'timezone' => 'Africa/Lagos',
                    'locale' => 'en-NG',
                    'email_verified_at' => now(),
                    'password' => $password,
                    'remember_token' => Str::random(10),
                ],
            );

            NormalizeSponsorExtraUsersSeeder::normalizeUser($user);
        }

        $this->command?->info('Seeded 10 additional sponsor users (role_id 4, email verified). Password: password');
        $this->command?->info('Emails use firstname.lastname@mail.com after normalization.');
    }
}
