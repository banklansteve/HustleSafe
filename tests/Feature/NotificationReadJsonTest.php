<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationReadJsonTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_read_returns_json_redirect_for_ajax_requests(): void
    {
        $user = User::factory()->create();

        $notification = $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\QuestThreadMessageNotification',
            'data' => ['href' => '/quests/example/messages'],
        ]);

        $this->actingAs($user)
            ->getJson(route('notifications.read', $notification->id))
            ->assertOk()
            ->assertJsonPath('redirect', '/quests/example/messages');
    }
}
