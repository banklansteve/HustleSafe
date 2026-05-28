<?php

namespace App\Services\ConversationMonitoring;

use App\Models\ConversationMessageFlag;
use App\Models\ConversationSystematicEscalation;
use App\Models\QuestConversationThread;
use Illuminate\Support\Facades\Schema;

class ConversationSystematicPatternService
{
    public function analyze(): int
    {
        if (! Schema::hasTable('conversation_systematic_escalations')) {
            return 0;
        }

        $cfg = config('conversation_monitoring.systematic', []);
        $days = (int) ($cfg['window_days'] ?? 30);
        $minParties = (int) ($cfg['min_distinct_counterparties'] ?? 3);
        $minInstances = (int) ($cfg['min_instances'] ?? 3);
        $categories = $cfg['categories'] ?? ['off_platform_payment', 'external_contact'];
        $since = now()->subDays($days);

        $created = 0;

        $flags = ConversationMessageFlag::query()
            ->where('flagged_at', '>=', $since)
            ->whereIn('trigger_category', $categories)
            ->whereIn('status', ['pending', 'confirmed'])
            ->get()
            ->groupBy('sender_user_id');

        foreach ($flags as $userId => $userFlags) {
            if ($userFlags->count() < $minInstances) {
                continue;
            }

            $counterparties = [];
            $contracts = [];
            $timeline = [];

            foreach ($userFlags as $flag) {
                $thread = QuestConversationThread::query()->find($flag->quest_conversation_thread_id);
                if (! $thread) {
                    continue;
                }
                $party = (int) $thread->client_id === (int) $userId
                    ? (int) $thread->freelancer_id
                    : (int) $thread->client_id;
                $counterparties[$party] = true;
                if ($flag->quest_offer_id) {
                    $contracts[$flag->quest_offer_id] = true;
                } elseif ($flag->quest_id) {
                    $contracts['q:'.$flag->quest_id] = true;
                }

                $timeline[] = [
                    'flag_id' => $flag->id,
                    'thread_id' => $flag->quest_conversation_thread_id,
                    'quest_id' => $flag->quest_id,
                    'quest_offer_id' => $flag->quest_offer_id,
                    'counterparty_id' => $party,
                    'category' => $flag->trigger_category?->value ?? $flag->trigger_category,
                    'pattern' => $flag->matched_pattern_redacted,
                    'flagged_at' => $flag->flagged_at?->toIso8601String(),
                ];
            }

            if (count($counterparties) < $minParties) {
                continue;
            }

            $primaryCategory = $userFlags->groupBy(fn ($f) => $f->trigger_category?->value ?? $f->trigger_category)
                ->sortByDesc(fn ($g) => $g->count())
                ->keys()
                ->first();

            $existing = ConversationSystematicEscalation::query()
                ->where('user_id', $userId)
                ->where('trigger_category', $primaryCategory)
                ->where('status', 'open')
                ->exists();

            if ($existing) {
                continue;
            }

            ConversationSystematicEscalation::query()->create([
                'user_id' => $userId,
                'trigger_category' => $primaryCategory,
                'status' => 'open',
                'instance_count' => $userFlags->count(),
                'distinct_counterparties' => count($counterparties),
                'distinct_contracts' => count($contracts),
                'timeline' => $timeline,
                'detected_at' => now(),
            ]);

            $created++;
        }

        return $created;
    }
}
