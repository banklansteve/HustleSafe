<?php

namespace App\Support;

use App\Models\User;

final class InertiaAuthUser
{
    /**
     * Lightweight auth payload for Inertia shared props (avoids heavy User serialization).
     *
     * @return array<string, mixed>|null
     */
    public static function for(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        $user->loadMissing('role:id,name,slug');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'avatar_url' => $user->avatar_url,
            'role' => $user->role ? [
                'id' => $user->role->id,
                'name' => $user->role->name,
                'slug' => $user->role->slug,
            ] : null,
        ];
    }
}
