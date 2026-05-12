<?php

namespace App\Enums;

/**
 * Quest lifecycle for escrow-backed work.
 *
 * Paused: temporarily on hold (mutual / ops).
 * InDispute: active dispute resolution.
 * Closed: resolved and administratively closed.
 * Archived: completed work aged into long-term read-only history.
 */
enum QuestStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Paused = 'paused';
    case PendingReview = 'pending_review';
    case InDispute = 'in_dispute';
    case Completed = 'completed';
    case CancelledMutual = 'cancelled_mutual';
    case CancelledByAdmin = 'cancelled_by_admin';
    case WithdrawnByClient = 'withdrawn_by_client';
    case WithdrawnByFreelancer = 'withdrawn_by_freelancer';
    case Closed = 'closed';
    case Archived = 'archived';

    /**
     * States where parties may still leave structured reviews (full or partial).
     */
    public static function reviewEligibleStatuses(): array
    {
        return [
            self::Completed,
            self::CancelledMutual,
            self::WithdrawnByClient,
            self::WithdrawnByFreelancer,
            self::Closed,
            self::InDispute,
            self::CancelledByAdmin,
        ];
    }

    /**
     * Work that is not terminal — needs sponsor/freelancer or platform attention.
     *
     * @return list<QuestStatus>
     */
    public static function operationalStatuses(): array
    {
        return [
            self::Open,
            self::Assigned,
            self::InProgress,
            self::Paused,
            self::PendingReview,
            self::InDispute,
        ];
    }

    public function allowsFullReview(): bool
    {
        return $this === self::Completed;
    }

    public function allowsPartialReviewOnly(): bool
    {
        return in_array($this, [
            self::CancelledMutual,
            self::WithdrawnByClient,
            self::WithdrawnByFreelancer,
            self::CancelledByAdmin,
            self::InDispute,
        ], true);
    }
}
