<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserFollow;
use App\Notifications\UserFollowedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserFollowController extends Controller
{
    public function toggle(Request $request, string $slug): JsonResponse
    {
        $viewer = $request->user();
        if ($viewer === null) {
            abort(403);
        }

        if ($viewer->role?->slug !== 'client') {
            abort(403);
        }

        if (! Schema::hasTable('user_follows')) {
            return response()->json([
                'message' => __('Follow is temporarily unavailable. Ask the site admin to run database migrations.'),
            ], 503);
        }

        $target = User::query()
            ->where('slug', $slug)
            ->whereHas('role', fn ($q) => $q->where('slug', 'freelancer'))
            ->firstOrFail();

        if ($viewer->id === $target->id) {
            abort(403);
        }

        $notify = false;

        DB::transaction(function () use ($viewer, $target, &$notify) {
            $exists = UserFollow::query()
                ->where('follower_id', $viewer->id)
                ->where('following_id', $target->id)
                ->exists();

            if ($exists) {
                UserFollow::query()
                    ->where('follower_id', $viewer->id)
                    ->where('following_id', $target->id)
                    ->delete();

                return;
            }

            UserFollow::query()->create([
                'follower_id' => $viewer->id,
                'following_id' => $target->id,
            ]);
            $notify = true;
        });

        if ($notify) {
            $target->notify(new UserFollowedNotification($viewer));
        }

        $following = UserFollow::query()
            ->where('follower_id', $viewer->id)
            ->where('following_id', $target->id)
            ->exists();

        $count = UserFollow::query()->where('following_id', $target->id)->count();

        return response()->json([
            'following' => $following,
            'followers_count' => $count,
        ]);
    }
}
