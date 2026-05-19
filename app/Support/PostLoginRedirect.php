<?php

namespace App\Support;

use App\Models\User;

final class PostLoginRedirect
{
    public static function intendedUrl(User $user): string
    {
        return match ($user->role?->slug) {
            'admin' => route('operations.dashboard'),
            'super_admin' => route('admin.dashboard'),
            default => route('dashboard'),
        };
    }
}
