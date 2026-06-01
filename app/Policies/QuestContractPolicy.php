<?php

namespace App\Policies;

use App\Models\QuestContract;
use App\Models\User;

class QuestContractPolicy
{
    public function view(User $user, QuestContract $contract): bool
    {
        if (in_array($user->role?->slug, ['admin', 'super_admin'], true)) {
            return true;
        }

        return $contract->isParty($user);
    }

    public function downloadPdf(User $user, QuestContract $contract): bool
    {
        return $this->view($user, $contract);
    }

    public function requestAmendment(User $user, QuestContract $contract): bool
    {
        return $contract->isParty($user);
    }

    public function requestDeliveryExtension(User $user, QuestContract $contract): bool
    {
        return (int) $contract->freelancer_id === (int) $user->id;
    }

    public function respondDeliveryExtension(User $user, QuestContract $contract): bool
    {
        return (int) $contract->client_id === (int) $user->id;
    }

    public function flagForReview(User $user, QuestContract $contract): bool
    {
        return $user->role?->slug === 'super_admin';
    }
}
