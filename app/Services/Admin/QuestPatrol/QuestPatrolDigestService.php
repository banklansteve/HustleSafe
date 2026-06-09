<?php

namespace App\Services\Admin\QuestPatrol;

use App\Enums\QuestPatrolFlagType;
use App\Mail\QuestPatrolDigestMail;
use App\Models\QuestPatrolFlag;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

final class QuestPatrolDigestService
{
    public function __construct(
        private readonly QuestPatrolTrendsService $trends,
    ) {}

    public function sendDailyDigest(): int
    {
        if (! Schema::hasTable('quest_patrol_flags')) {
            return 0;
        }

        $since = now()->subDay();
        $newFlags = QuestPatrolFlag::query()
            ->where('detected_at', '>=', $since)
            ->orderByDesc('detected_at')
            ->limit(50)
            ->get();

        if ($newFlags->isEmpty()) {
            return 0;
        }

        $summary = $this->trends->summary();
        $highRisk = $newFlags->where('severity', 'high')->take(10)->map(fn (QuestPatrolFlag $flag) => [
            'label' => QuestPatrolFlagType::tryFrom($flag->flag_type)?->label() ?? $flag->flag_type,
            'subject_type' => $flag->subject_type,
            'subject_id' => $flag->subject_id,
            'severity' => $flag->severity,
            'detected_at' => $flag->detected_at?->toDateTimeString(),
        ])->values()->all();

        $payload = [
            'date' => now()->toDateString(),
            'new_flags_count' => $newFlags->count(),
            'high_risk' => $highRisk,
            'summary' => $summary,
            'moderation_url' => route('admin.moderation.index'),
        ];

        $sent = 0;
        User::query()
            ->whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))
            ->whereNotNull('email')
            ->each(function (User $admin) use ($payload, &$sent): void {
                Mail::to($admin->email)->queue(new QuestPatrolDigestMail($admin, $payload));
                $sent++;
            });

        return $sent;
    }
}
