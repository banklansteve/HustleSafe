<?php

namespace Tests\Feature\Operations;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationsRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_when_visiting_operations(): void
    {
        $this->get('/operations')->assertRedirect(route('login', absolute: false));
    }

    public function test_super_admin_is_redirected_from_operations_console(): void
    {
        $roleId = Role::query()->where('slug', 'super_admin')->value('id');
        $user = User::factory()->create(['role_id' => $roleId]);

        $this->actingAs($user)->get('/operations')->assertRedirect(route('admin.dashboard', absolute: false));
    }

    public function test_operations_staff_can_open_operations_dashboard(): void
    {
        $roleId = Role::query()->where('slug', 'admin')->value('id');
        $user = User::factory()->create(['role_id' => $roleId]);

        $this->actingAs($user)->get('/operations')->assertOk();
    }
}
