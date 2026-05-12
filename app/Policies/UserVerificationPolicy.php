<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserVerification;

class UserVerificationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, UserVerification $verification): bool
    {
        return $user->id === $verification->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }
}
