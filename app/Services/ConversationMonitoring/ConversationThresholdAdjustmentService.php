<?php

namespace App\Services\ConversationMonitoring;

use App\Models\ConversationMessageFlag;
use Illuminate\Support\Facades\Schema;

class ConversationThresholdAdjustmentService
{
    public function thresholdAdjustment(): int
    {
        if (! Schema::hasTable('conversation_message_flags')) {
            return 0;
        }

        $since = now()->subDays(30);
        $total = ConversationMessageFlag::query()->where('flagged_at', '>=', $since)->count();
        if ($total < 20) {
            return 0;
        }

        $dismissed = ConversationMessageFlag::query()
            ->where('flagged_at', '>=', $since)
            ->where('status', 'dismissed')
            ->count();

        $dismissalRate = $dismissed / max(1, $total);
        $step = (int) config('conversation_monitoring.scoring.threshold_adjustment_per_dismissal_rate', 2);
        $max = (int) config('conversation_monitoring.scoring.max_threshold_adjustment', 5);

        if ($dismissalRate > 0.25) {
            return min($max, (int) ceil(($dismissalRate - 0.25) * 10) * $step);
        }

        if ($dismissalRate < 0.10) {
            return max(-2, (int) floor(($dismissalRate - 0.10) * 10) * $step);
        }

        return 0;
    }
}
