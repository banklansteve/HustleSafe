<?php

namespace Database\Seeders;

use App\Models\LocalGovernment;
use App\Models\QuestCategory;
use App\Models\Role;
use App\Models\State;
use App\Models\User;
use App\Support\SeededUserIdentity;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class FakeMarketplaceUsersSeeder extends Seeder
{
    /**
     * Seed 20 freelancers and 10 clients for local testing.
     */
    public function run(): void
    {
        $freelancerRole = Role::query()->where('slug', 'freelancer')->firstOrFail();
        $clientRole = Role::query()->where('slug', 'client')->firstOrFail();
        $states = State::query()->with('localGovernments')->get()->filter(fn (State $state) => $state->localGovernments->isNotEmpty())->values();
        $categories = QuestCategory::query()->whereNotNull('parent_id')->where('is_active', true)->orderBy('name')->get()->values();

        if ($states->isEmpty() || $categories->isEmpty()) {
            $this->command?->warn('States/LGAs or quest categories are missing. Run NigeriaGeoSeeder and QuestCategorySeeder first.');

            return;
        }

        $password = Hash::make('password');

        $professionByKeyword = [
            'clean' => 'Residential Cleaning Specialist',
            'plumb' => 'Plumber',
            'electric' => 'Electrician',
            'paint' => 'Painter',
            'carpent' => 'Carpenter',
            'mason' => 'Masonry Technician',
            'garden' => 'Gardener and Lawn Care Expert',
            'landscap' => 'Landscape Technician',
            'repair' => 'Home Repair Technician',
            'install' => 'Installation Technician',
            'moving' => 'Moving and Logistics Helper',
            'delivery' => 'Delivery Runner',
            'event' => 'Event Support Assistant',
            'cater' => 'Catering Assistant',
            'photo' => 'Photographer',
            'video' => 'Videographer',
            'design' => 'Graphic Designer',
            'web' => 'Web Developer',
            'data' => 'Data Entry Specialist',
            'write' => 'Content Writer',
            'tutor' => 'Private Tutor',
            'beauty' => 'Beauty Specialist',
            'hair' => 'Hair Stylist',
            'makeup' => 'Makeup Artist',
            'auto' => 'Auto Mechanic',
            'driver' => 'Professional Driver',
        ];

        $fallbackProfessions = [
            'Skilled Home Service Professional',
            'Field Service Technician',
            'Creative Services Freelancer',
            'Maintenance Specialist',
            'Personal Services Specialist',
        ];

        for ($i = 1; $i <= 20; $i++) {
            $category = $categories[($i - 1) % $categories->count()];
            $extraCategories = $categories->random(min(2, $categories->count()))->pluck('id')->all();
            $profession = $this->professionForCategory($category->name, $professionByKeyword, $fallbackProfessions);
            [$state, $lga] = $this->randomLocation($states);
            $first = fake()->firstName();
            $last = fake()->lastName();
            $uid = sprintf('FF%06d', $i);

            $existing = User::query()->where('uid', $uid)->first();
            $identity = SeededUserIdentity::forNames($first, $last, $existing?->id);

            $user = User::query()->updateOrCreate(
                ['uid' => $uid],
                [
                    'email' => $identity['email'],
                    'username' => $identity['username'],
                    'slug' => $identity['slug'],
                    'first_name' => $first,
                    'last_name' => $last,
                    'name' => $first.' '.$last,
                    'phone' => '080'.fake()->unique()->numerify('########'),
                    'gender' => fake()->randomElement(['female', 'male']),
                    'date_of_birth' => fake()->dateTimeBetween('-45 years', '-21 years')->format('Y-m-d'),
                    'address_line' => fake()->streetAddress(),
                    'city' => $lga->name,
                    'state_id' => $state->id,
                    'local_government_id' => $lga->id,
                    'account_type' => 'hustler',
                    'role_id' => $freelancerRole->id,
                    'profession' => $profession,
                    'headline' => $profession.' available for verified HustleSafe quests',
                    'bio' => 'Experienced '.$profession.' focused on reliable delivery, clear communication, and safe work practices.',
                    'hourly_rate_min' => fake()->numberBetween(250000, 800000),
                    'hourly_rate_max' => fake()->numberBetween(900000, 1800000),
                    'years_experience' => fake()->numberBetween(2, 12),
                    'availability' => fake()->randomElement(['weekdays', 'weekends', 'evenings', 'flexible']),
                    'verification_tier' => fake()->randomElement(['basic', 'verified', 'premium']),
                    'onboarding_step' => 5,
                    'timezone' => 'Africa/Lagos',
                    'locale' => 'en-NG',
                    'email_verified_at' => now(),
                    'password' => $password,
                    'remember_token' => Str::random(10),
                ],
            );

            $user->questCategoryPreferences()->sync(array_values(array_unique([$category->id, ...$extraCategories])));
        }

        for ($i = 1; $i <= 10; $i++) {
            [$state, $lga] = $this->randomLocation($states);
            $first = fake()->firstName();
            $last = fake()->lastName();
            $uid = sprintf('FC%06d', $i);

            $existing = User::query()->where('uid', $uid)->first();
            $identity = SeededUserIdentity::forNames($first, $last, $existing?->id);

            User::query()->updateOrCreate(
                ['uid' => $uid],
                [
                    'email' => $identity['email'],
                    'username' => $identity['username'],
                    'slug' => $identity['slug'],
                    'first_name' => $first,
                    'last_name' => $last,
                    'name' => $first.' '.$last,
                    'phone' => '081'.fake()->unique()->numerify('########'),
                    'gender' => fake()->randomElement(['female', 'male']),
                    'date_of_birth' => fake()->dateTimeBetween('-55 years', '-24 years')->format('Y-m-d'),
                    'company_name' => fake()->optional(0.35)->company(),
                    'address_line' => fake()->streetAddress(),
                    'city' => $lga->name,
                    'state_id' => $state->id,
                    'local_government_id' => $lga->id,
                    'account_type' => 'sponsor',
                    'role_id' => $clientRole->id,
                    'job_title' => fake()->randomElement(['Operations Lead', 'Home Owner', 'Facilities Manager', 'Project Sponsor', 'Small Business Owner']),
                    'company_size' => fake()->randomElement(['1-5', '6-20', '21-50', '51-200']),
                    'onboarding_step' => 5,
                    'timezone' => 'Africa/Lagos',
                    'locale' => 'en-NG',
                    'email_verified_at' => now(),
                    'password' => $password,
                    'remember_token' => Str::random(10),
                ],
            );
        }

        $this->normalizeLegacyFakeEmails();

        $this->command?->info('Seeded 20 fake freelancers and 10 fake clients.');
        $this->command?->info('Identity: username=firstname(+number), slug=firstname-lastname, email=firstname.lastname@mail.com');
        $this->command?->info('Password for all: password');
    }

    private function normalizeLegacyFakeEmails(): void
    {
        User::query()
            ->where(function ($query): void {
                $query->where('email', 'like', 'fake.freelancer%@hustlesafe.test')
                    ->orWhere('email', 'like', 'fake.client%@hustlesafe.test')
                    ->orWhere('username', 'like', 'fake_freelancer_%')
                    ->orWhere('username', 'like', 'fake_client_%');
            })
            ->orderBy('id')
            ->each(function (User $user): void {
                if (! filled($user->first_name) || ! filled($user->last_name)) {
                    return;
                }

                $identity = SeededUserIdentity::forNames(
                    (string) $user->first_name,
                    (string) $user->last_name,
                    (int) $user->id,
                );

                $user->forceFill($identity)->save();
            });
    }

    /**
     * @param  \Illuminate\Support\Collection<int, State>  $states
     * @return array{State, LocalGovernment}
     */
    private function randomLocation($states): array
    {
        /** @var State $state */
        $state = $states->random();

        return [$state, $state->localGovernments->random()];
    }

    /**
     * @param  array<string, string>  $professionByKeyword
     * @param  list<string>  $fallbackProfessions
     */
    private function professionForCategory(string $category, array $professionByKeyword, array $fallbackProfessions): string
    {
        $haystack = str($category)->lower()->toString();
        foreach ($professionByKeyword as $keyword => $profession) {
            if (str_contains($haystack, $keyword)) {
                return $profession;
            }
        }

        return fake()->randomElement($fallbackProfessions);
    }
}
