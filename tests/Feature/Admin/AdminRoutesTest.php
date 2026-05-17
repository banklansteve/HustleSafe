<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_when_visiting_admin(): void
    {
        $this->get('/admin')->assertRedirect(route('login', absolute: false));
    }

    public function test_client_cannot_access_admin_area(): void
    {
        $roleId = Role::query()->where('slug', 'client')->value('id');
        $user = User::factory()->create(['role_id' => $roleId]);

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_super_admin_can_open_admin_dashboard(): void
    {
        $roleId = Role::query()->where('slug', 'super_admin')->value('id');
        $this->assertNotNull($roleId, 'super_admin role must exist after migrations');

        $user = User::factory()->create(['role_id' => $roleId]);

        $this->actingAs($user)->get('/admin')->assertOk();
    }

    public function test_operations_staff_is_redirected_from_admin_console(): void
    {
        $roleId = Role::query()->where('slug', 'admin')->value('id');
        $user = User::factory()->create(['role_id' => $roleId]);

        $this->actingAs($user)->get('/admin')->assertRedirect(route('operations.dashboard', absolute: false));
    }
}
