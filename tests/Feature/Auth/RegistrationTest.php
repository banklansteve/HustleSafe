<?php

namespace Tests\Feature\Auth;

use App\Mail\WelcomeVerifyEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register_and_receive_welcome_verification_mail(): void
    {
        Mail::fake();

        $response = $this->post('/register', [
            'account_type' => 'hustler',
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'phone' => '+234 801 234 5678',
            'address_line' => '12 Allen Avenue',
            'local_government' => 'Ikeja',
            'state' => 'Lagos',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();

        $user = User::query()->where('email', 'test@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at);

        Mail::assertSent(WelcomeVerifyEmail::class, function (WelcomeVerifyEmail $mail) use ($user) {
            return $mail->user->is($user)
                && str_contains($mail->verificationUrl, (string) $user->getKey());
        });
    }
}
