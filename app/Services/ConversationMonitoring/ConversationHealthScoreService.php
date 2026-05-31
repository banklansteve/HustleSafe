<?php

namespace App\Services\ConversationMonitoring;

use App\Models\ConversationMessageFlag;
use App\Models\ConversationThreadReview;
use App\Models\ConversationUserHealthScore;
use App\Models\ProposalClarificationThread;
use App\Models\QuestConversationThread;
use App\Models\User;
use App\Models\UserRiskProfile;
use App\Services\TrustRisk\TrustRiskSettingsService;
use App\Support\TrustRisk\UserRiskScoreDispatcher;
use Illuminate\Support\Facades\Schema;

class ConversationHealthScoreService
{
    public function recalculateForUser(int $userId): ConversationUserHealthScore
    {
        $penalties = config('conversation_monitoring.penalties', []);
        $since = now()->subDays(30);

        $flags = ConversationMessageFlag::query()
            ->where('sender_user_id', $userId)
            ->where('flagged_at', '>=', $since)
            ->whereIn('status', ['pending', 'confirmed'])
            ->get();

        $score = (int) config('conversation_monitoring.health_score.default', 100);
        $counterparties = [];

        foreach ($flags as $flag) {
            $penalty = match ($flag->trigger_category?->value ?? $flag->trigger_category) {
                'off_platform_payment' => (int) ($penalties['off_platform_payment'] ?? 22),
                'external_contact' => (int) ($penalties['external_contact'] ?? 18),
                'abusive_language' => (int) ($penalties['abusive_language'] ?? 15),
                default => (int) ($penalties['blacklisted_keyword'] ?? 8),
            };

            $thread = $flag->quest_conversation_thread_id
                ? QuestConversationThread::query()->find($flag->quest_conversation_thread_id)
                : null;
            $clarificationThread = $flag->proposal_clarification_thread_id
                ? ProposalClarificationThread::query()->find($flag->proposal_clarification_thread_id)
                : null;

            if ($thread) {
                $counterparty = (int) $thread->client_id === $userId
                    ? (int) $thread->freelancer_id
                    : (int) $thread->client_id;
                $counterparties[$counterparty] = ($counterparties[$counterparty] ?? 0) + 1;
            } elseif ($clarificationThread) {
                $counterparty = (int) $clarificationThread->client_id === $userId
                    ? (int) $clarificationThread->freelancer_id
                    : (int) $clarificationThread->client_id;
                $counterparties[$counterparty] = ($counterparties[$counterparty] ?? 0) + 1;
            }

            $score -= $penalty;
        }

        $distinctParties = count($counterparties);
        if ($distinctParties >= 3) {
            $score -= (int) (($distinctParties - 2) * 8 * (float) ($penalties['cross_party_multiplier'] ?? 1.35));
        } elseif ($distinctParties <= 1 && $flags->count() > 2) {
            $score = (int) floor($score * (float) ($penalties['repeat_same_party_multiplier'] ?? 0.85));
        }

        $score = max(0, min(100, $score));

        $record = ConversationUserHealthScore::query()->updateOrCreate(
            ['user_id' => $userId],
            [
                'health_score' => $score,
                'flag_count_30d' => $flags->count(),
                'distinct_counterparties_30d' => $distinctParties,
                'calculated_at' => now(),
            ],
        );

        $this->syncTrustRiskQueue($userId, $score);

        return $record;
    }

    private function syncTrustRiskQueue(int $userId, int $healthScore): void
    {
        if (! Schema::hasTable('user_risk_profiles')) {
            return;
        }

        $threshold = $this->healthThreshold();
        $profile = UserRiskProfile::query()->firstOrNew(['user_id' => $userId]);

        if ($healthScore < $threshold) {
            $signals = is_array($profile->signals) ? $profile->signals : [];
            $signals['conversation_risk'] = [
                'health_score' => $healthScore,
                'threshold' => $threshold,
                'source' => 'conversation_monitoring',
            ];
            $profile->fill([
                'in_risk_queue' => true,
                'queued_at' => $profile->queued_at ?? now(),
                'signals' => $signals,
                'composite_score' => $profile->composite_score ?? 50,
                'tier' => $profile->tier ?? 'medium',
            ]);
            $profile->save();
        }

        UserRiskScoreDispatcher::dispatch($userId);
    }

    public function healthThreshold(): int
    {
        if (Schema::hasTable('admin_platform_settings')) {
            $record = \App\Models\AdminPlatformSetting::query()
                ->where('key', 'conversation_monitoring.health_risk_threshold')
                ->first();
            if ($record && isset($record->value['value'])) {
                return (int) $record->value['value'];
            }
        }

        return (int) config('conversation_monitoring.health_score.risk_queue_threshold', 45);
    }
}
