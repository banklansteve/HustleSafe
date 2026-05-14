<?php

namespace Tests\Feature\Auth;

use App\Mail\WelcomeVerifyEmail;
use App\Models\LocalGovernment;
use App\Models\QuestCategory;
use App\Models\User;
use Database\Seeders\NigeriaGeoSeeder;
use Database\Seeders\QuestCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(NigeriaGeoSeeder::class);
        $this->seed(QuestCategorySeeder::class);
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register_and_receive_welcome_verification_mail(): void
    {
        Mail::fake();

        $lga = LocalGovernment::query()->with('state')->first();
        $this->assertNotNull($lga);
        $leafCategory = QuestCategory::query()->whereNotNull('parent_id')->first();
        $this->assertNotNull($leafCategory);

        $response = $this->post('/register', [
            'account_type' => 'hustler',
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'phone' => '+234 801 234 5678',
            'address_line' => '12 Allen Avenue',
            'city' => 'Ikeja',
            'state_id' => $lga->state_id,
            'local_government_id' => $lga->id,
            'quest_category_ids' => [$leafCategory->id],
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();

        $user = User::query()->where('email', 'test@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at);
        $this->assertSame($lga->state_id, $user->state_id);
        $this->assertSame($lga->id, $user->local_government_id);
        $this->assertSame('Ikeja', $user->city);
        $this->assertTrue($user->questCategoryPreferences()->where('quest_categories.id', $leafCategory->id)->exists());

        Mail::assertSent(WelcomeVerifyEmail::class, function (WelcomeVerifyEmail $mail) use ($user) {
            return $mail->user->is($user)
                && str_contains($mail->verificationUrl, (string) $user->getKey());
        });
    }

    public function test_sponsor_can_register_without_quest_category_ids(): void
    {
        Mail::fake();

        $lga = LocalGovernment::query()->with('state')->first();
        $this->assertNotNull($lga);

        $response = $this->post('/register', [
            'account_type' => 'sponsor',
            'first_name' => 'Sponsor',
            'last_name' => 'Client',
            'email' => 'sponsor@example.com',
            'phone' => '+234 802 000 0000',
            'address_line' => '1 Admiralty Way',
            'city' => 'Lekki',
            'state_id' => $lga->state_id,
            'local_government_id' => $lga->id,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();

        $user = User::query()->where('email', 'sponsor@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('sponsor', $user->account_type);
        $this->assertSame(0, $user->questCategoryPreferences()->count());
    }
}
