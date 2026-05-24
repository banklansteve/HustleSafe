<?php

namespace Tests\Feature\Admin;

use App\Models\AdminPlatformSetting;
use App\Models\Role;
use App\Models\User;
use App\Services\Admin\MaintenanceModeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceToggleTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_disable_maintenance_with_false_boolean(): void
    {
        $role = Role::query()->create(['name' => 'Super Admin', 'slug' => 'super_admin']);
        $super = User::factory()->create(['role_id' => $role->id]);

        app(MaintenanceModeService::class)->enable('Testing');

        $this->assertTrue(app(MaintenanceModeService::class)->isEnabled());

        $response = $this->actingAs($super)->postJson(route('admin.api.maintenance.off'));

        $response->assertOk()
            ->assertJsonPath('status.enabled', false);

        $this->assertFalse(app(MaintenanceModeService::class)->isEnabled());

        $record = AdminPlatformSetting::query()->where('key', 'maintenance.enabled')->first();
        $this->assertFalse($record->value['value']);
    }

    public function test_super_admin_can_enable_maintenance(): void
    {
        $role = Role::query()->create(['name' => 'Super Admin', 'slug' => 'super_admin']);
        $super = User::factory()->create(['role_id' => $role->id]);

        $response = $this->actingAs($super)->postJson(route('admin.api.maintenance.on'), [
            'message' => 'Workshop',
            'return_time' => '2026-05-20T18:00',
        ]);

        $response->assertOk()
            ->assertJsonPath('status.enabled', true);

        $this->assertTrue(app(MaintenanceModeService::class)->isEnabled());
    }

    public function test_guest_homepage_returns_maintenance_page_when_enabled(): void
    {
        app(MaintenanceModeService::class)->enable('Workshop test');

        $response = $this->get('/');

        $response->assertStatus(503);
    }
}
