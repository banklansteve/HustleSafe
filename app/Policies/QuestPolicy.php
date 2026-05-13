<?php

namespace App\Policies;

use App\Enums\QuestStatus;
use App\Enums\QuestVisibility;
use App\Models\Quest;
use App\Models\User;

class QuestPolicy
{
    public function create(User $user): bool
    {
        return in_array($user->role?->slug, ['client', 'admin', 'super_admin'], true);
    }

    public function view(User $user, Quest $quest): bool
    {
        if (in_array($user->role?->slug, ['admin', 'super_admin'], true)) {
            return true;
        }

        if ($quest->client_id === $user->id) {
            return true;
        }

        if ($quest->freelancer_id !== null && $quest->freelancer_id === $user->id) {
            return true;
        }

        if ($quest->status === QuestStatus::Draft) {
            return false;
        }

        if ($quest->status !== QuestStatus::Open || $quest->freelancer_id !== null) {
            return false;
        }

        if ($quest->visibility === QuestVisibility::Private) {
            return false;
        }

        if ($quest->visibility === QuestVisibility::InviteOnly) {
            return $quest->isInvitedFreelancer($user);
        }

        return true;
    }

    public function update(User $user, Quest $quest): bool
    {
        if ($quest->client_id !== $user->id && ! in_array($user->role?->slug, ['admin', 'super_admin'], true)) {
            return false;
        }

        return $quest->freelancer_id === null
            && in_array($quest->status, [QuestStatus::Open, QuestStatus::Draft], true);
    }

    public function delete(User $user, Quest $quest): bool
    {
        return $this->update($user, $quest);
    }

    public function manageInvites(User $user, Quest $quest): bool
    {
        return $quest->client_id === $user->id && $this->update($user, $quest);
    }
}
