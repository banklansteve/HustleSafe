<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use App\Support\RoleSessionLifetime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffAdminLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_admin_with_password_but_missing_setup_flag_can_log_in(): void
    {
        $role = Role::query()->create(['name' => 'Admin', 'slug' => 'admin']);
        $password = 'Staff-Secure-Password-1';

        $staff = User::factory()->create([
            'role_id' => $role->id,
            'email' => 'staff-login@example.com',
            'password' => Hash::make($password),
            'operations_staff_password_set_at' => null,
            'operations_staff_invited_at' => now(),
        ]);

        $response = $this->post('/login', [
            'email' => $staff->email,
            'password' => $password,
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($staff);
        $staff->refresh();
        $this->assertNotNull($staff->operations_staff_password_set_at);
    }

    public function test_staff_admin_without_password_setup_sees_invitation_message(): void
    {
        $role = Role::query()->create(['name' => 'Admin', 'slug' => 'admin']);

        $staff = User::factory()->create([
            'role_id' => $role->id,
            'email' => 'staff-pending@example.com',
            'password' => Hash::make('temporary-random-password'),
            'operations_staff_password_set_at' => null,
            'operations_staff_invited_at' => now(),
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => $staff->email,
            'password' => 'wrong-password',
        ]);

        $response
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertStringContainsString(
            'password setup',
            session('errors')->get('email')[0] ?? ''
        );
    }

    public function test_role_session_lifetime_values(): void
    {
        $this->assertSame(300, RoleSessionLifetime::minutesForRole('admin'));
        $this->assertSame(10080, RoleSessionLifetime::minutesForRole('client'));
        $this->assertSame(20160, RoleSessionLifetime::superAdminMinutes());
    }
}
