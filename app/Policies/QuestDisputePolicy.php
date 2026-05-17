<?php

namespace App\Policies;

use App\Models\QuestDispute;
use App\Models\User;

class QuestDisputePolicy
{
    public function view(User $user, QuestDispute $dispute): bool
    {
        return $dispute->isParty($user)
            || in_array($user->role?->slug, ['admin', 'super_admin'], true);
    }

    public function participate(User $user, QuestDispute $dispute): bool
    {
        return $dispute->isParty($user);
    }
}
