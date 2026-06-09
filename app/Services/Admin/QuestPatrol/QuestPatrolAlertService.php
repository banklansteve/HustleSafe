<?php

namespace App\Services\Admin\QuestPatrol;

use App\Enums\QuestPatrolFlagType;
use App\Models\AdminNotification;
use App\Models\QuestPatrolFlag;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

final class QuestPatrolAlertService
{
    public function notifyIfCritical(QuestPatrolFlag $flag, bool $wasCreated): void
    {
        if (! $wasCreated || ! Schema::hasTable('admin_notifications')) {
            return;
        }

        if ($flag->severity !== 'high') {
            return;
        }

        $type = QuestPatrolFlagType::tryFrom($flag->flag_type);
        $label = $type?->label() ?? $flag->flag_type;
        $url = $flag->subject_type === 'proposal'
            ? route('admin.moderation.index', ['module' => 'proposals'])
            : route('admin.moderation.index', ['module' => 'quests']);

        User::query()
            ->whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))
            ->get(['id'])
            ->each(function (User $admin) use ($flag, $label, $url): void {
                $dedupe = "quest_patrol:{$flag->id}:{$admin->id}";
                if (AdminNotification::query()->where('admin_user_id', $admin->id)->where('data->dedupe_key', $dedupe)->exists()) {
                    return;
                }

                AdminNotification::query()->create([
                    'admin_user_id' => $admin->id,
                    'category' => 'quest_patrol',
                    'priority' => 'high',
                    'title' => 'Critical patrol anomaly',
                    'body' => "{$label} detected on {$flag->subject_type} #{$flag->subject_id}.",
                    'action_label' => 'Open moderation',
                    'action_url' => $url,
                    'data' => [
                        'dedupe_key' => $dedupe,
                        'flag_id' => $flag->id,
                        'flag_type' => $flag->flag_type,
                        'subject_type' => $flag->subject_type,
                        'subject_id' => $flag->subject_id,
                    ],
                ]);
            });
    }
}
