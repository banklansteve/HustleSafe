<?php

namespace App\Support;

use App\Models\Quest;
use App\Services\QuestEngagementLifecycleService;
use Carbon\Carbon;

final class EscrowAutoReleasePolicy
{
    /** Hours after agreed delivery when escrow auto-releases if no dispute. */
    public const DEFAULT_RELEASE_HOURS = 72;

    /** Client reminder offsets (hours after agreed delivery). */
    public const CLIENT_REMINDER_HOURS = [0, 24, 36];

    public static function releaseHours(): int
    {
        return max(1, min(720, PlatformSettings::escrowAutoReleaseHours()));
    }

    public static function releaseAt(Carbon $agreedDelivery): Carbon
    {
        return $agreedDelivery->copy()->addHours(self::releaseHours());
    }

    public static function hoursRemaining(Carbon $agreedDelivery, ?Carbon $now = null): int
    {
        $now ??= now();

        return (int) max(0, $now->diffInHours(self::releaseAt($agreedDelivery), false));
    }

    public static function secondsUntilRelease(Quest $quest): int
    {
        $due = app(QuestEngagementLifecycleService::class)->expectedCompletionAt($quest);
        if ($due === null) {
            return 0;
        }

        return (int) max(0, now()->diffInSeconds(self::releaseAt($due), false));
    }

    public static function plainEnglish(): string
    {
        return __('If you do not mark this job complete or raise a dispute within :hours hours of the agreed delivery date, escrow may release automatically to the freelancer.', [
            'hours' => self::releaseHours(),
        ]);
    }

    public static function plainEnglishWithReminders(): string
    {
        return __('On the agreed delivery date we email you a review reminder, then again at 24 and 36 hours after that date. If you have not marked the job complete or opened a dispute, escrow may auto-release :hours hours after the agreed delivery date.', [
            'hours' => self::releaseHours(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function timelineSnapshot(): array
    {
        $hours = self::releaseHours();

        return [
            'auto_release_hours' => $hours,
            'client_reminder_hours' => self::CLIENT_REMINDER_HOURS,
            'auto_release_plain_english' => self::plainEnglishWithReminders(),
        ];
    }
}
