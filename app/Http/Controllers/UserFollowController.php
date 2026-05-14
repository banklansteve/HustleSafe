<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserFollow;
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

        if (! Schema::hasTable('user_follows')) {
            return response()->json([
                'message' => __('Follow is temporarily unavailable. Ask the site admin to run database migrations.'),
            ], 503);
        }

        $target = User::query()
            ->where('slug', $slug)
            ->whereHas('role', fn ($q) => $q->whereIn('slug', ['freelancer', 'client']))
            ->firstOrFail();

        if ($viewer->id === $target->id) {
            abort(403);
        }

        $viewerRole = $viewer->role?->slug;
        $targetRole = $target->role?->slug;

        if (! (($viewerRole === 'client' && $targetRole === 'freelancer')
            || ($viewerRole === 'freelancer' && $targetRole === 'client'))) {
            abort(403, __('You can only follow clients or freelancers across roles.'));
        }

        DB::transaction(function () use ($viewer, $target): void {
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
        });

        $following = UserFollow::query()
            ->where('follower_id', $viewer->id)
            ->where('following_id', $target->id)
            ->exists();

        $followersCount = UserFollow::query()->where('following_id', $target->id)->count();
        $followingCount = UserFollow::query()->where('follower_id', $target->id)->count();

        return response()->json([
            'following' => $following,
            'followers_count' => $followersCount,
            'following_count' => $followingCount,
        ]);
    }
}
