<?php

namespace App\Policies;

use App\Enums\AdminProposalStatus;
use App\Enums\QuestStatus;
use App\Models\QuestOffer;
use App\Models\User;

class QuestOfferPolicy
{
    public function view(User $user, QuestOffer $offer): bool
    {
        if (in_array($user->role?->slug, ['admin', 'super_admin'], true)) {
            return true;
        }

        if (($offer->admin_status?->value ?? (string) $offer->admin_status) === AdminProposalStatus::Suspended->value) {
            return false;
        }

        if ((int) $offer->freelancer_id === (int) $user->id) {
            return true;
        }

        $offer->loadMissing('quest');
        $quest = $offer->quest;

        if ($quest !== null && (int) $quest->client_id === (int) $user->id) {
            return true;
        }

        if ($quest !== null
            && $quest->status === QuestStatus::Open
            && in_array($offer->status, ['submitted', 'shortlisted'], true)
            && $user->can('view', $quest)) {
            return true;
        }

        return false;
    }

    public function downloadPdf(User $user, QuestOffer $offer): bool
    {
        if (in_array($user->role?->slug, ['admin', 'super_admin'], true)) {
            return true;
        }

        $offer->loadMissing('quest');

        if ((int) $offer->freelancer_id === (int) $user->id) {
            return true;
        }

        return (int) $offer->quest?->client_id === (int) $user->id;
    }

    public function respondAsClient(User $user, QuestOffer $offer): bool
    {
        $offer->loadMissing('quest');

        return $offer->quest !== null && (int) $offer->quest->client_id === (int) $user->id;
    }

    public function withdrawAsFreelancer(User $user, QuestOffer $offer): bool
    {
        return (int) $offer->freelancer_id === (int) $user->id;
    }

    public function update(User $user, QuestOffer $offer): bool
    {
        return (int) $offer->freelancer_id === (int) $user->id;
    }
}
