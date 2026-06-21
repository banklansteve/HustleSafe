<?php

namespace App\Services\Quest;

use App\Enums\QuestStatus;
use App\Models\Quest;
use App\Models\User;

class EscrowTransparencyTimelineService
{
    /**
     * @return array{stages: list<array<string, mixed>>, current_stage: ?string, escrow_status: ?string}
     */
    public function build(Quest $quest): array
    {
        $quest->loadMissing(['client:id,name', 'freelancer:id,name', 'paymentEscrow']);

        $stages = [
            $this->stage('funded', __('Escrow funded'), $this->fundedMeta($quest)),
            $this->stage('work_started', __('Work started'), $this->workStartedMeta($quest)),
            $this->stage('under_review', __('Under review'), $this->underReviewMeta($quest)),
            $this->stage('released', __('Released'), $this->releasedMeta($quest)),
        ];

        $current = collect($stages)->first(fn (array $s) => $s['status'] === 'current')['key']
            ?? collect($stages)->reverse()->first(fn (array $s) => $s['status'] === 'completed')['key']
            ?? 'funded';

        return [
            'stages' => $stages,
            'current_stage' => $current,
            'escrow_status' => $quest->escrow_status,
        ];
    }

    /**
     * @return array{key: string, label: string, status: string, at: ?string, actor: ?string, actor_role: ?string, hint: ?string}
     */
    private function stage(string $key, string $label, array $meta): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'status' => $meta['status'],
            'at' => $meta['at'],
            'actor' => $meta['actor'],
            'actor_role' => $meta['actor_role'],
            'hint' => $meta['hint'] ?? null,
        ];
    }

    /**
     * @return array{status: string, at: ?string, actor: ?string, actor_role: ?string, hint: ?string}
     */
    private function fundedMeta(Quest $quest): array
    {
        $at = $quest->escrow_funded_at ?? $quest->paymentEscrow?->funded_at;

        if ($at === null) {
            return [
                'status' => 'pending',
                'at' => null,
                'actor' => null,
                'actor_role' => null,
                'hint' => __('Waiting for the client to fund escrow.'),
            ];
        }

        return [
            'status' => 'completed',
            'at' => $at->toIso8601String(),
            'actor' => $quest->client?->name,
            'actor_role' => __('Client'),
            'hint' => null,
        ];
    }

    /**
     * @return array{status: string, at: ?string, actor: ?string, actor_role: ?string, hint: ?string}
     */
    private function workStartedMeta(Quest $quest): array
    {
        if ($quest->escrow_funded_at === null) {
            return ['status' => 'pending', 'at' => null, 'actor' => null, 'actor_role' => null, 'hint' => null];
        }

        $inProgress = ($quest->status?->value ?? (string) $quest->status) === QuestStatus::InProgress->value
            || in_array($quest->escrow_status, ['funded', 'partially_released', 'released'], true);

        if (! $inProgress) {
            return [
                'status' => 'pending',
                'at' => null,
                'actor' => null,
                'actor_role' => null,
                'hint' => __('Work begins after escrow is funded.'),
            ];
        }

        if ($quest->delivered_at !== null) {
            return [
                'status' => 'completed',
                'at' => $quest->delivered_at->toIso8601String(),
                'actor' => $quest->freelancer?->name,
                'actor_role' => __('Freelancer'),
                'hint' => __('Deliverable submitted — client review window open.'),
            ];
        }

        return [
            'status' => 'current',
            'at' => ($quest->escrow_funded_at ?? $quest->updated_at)?->toIso8601String(),
            'actor' => $quest->freelancer?->name ?? __('Freelancer'),
            'actor_role' => __('Freelancer'),
            'hint' => __('Contract is active — deliverables in progress.'),
        ];
    }

    /**
     * @return array{status: string, at: ?string, actor: ?string, actor_role: ?string, hint: ?string}
     */
    private function underReviewMeta(Quest $quest): array
    {
        if ($quest->delivery_acknowledged_at !== null) {
            return [
                'status' => 'completed',
                'at' => $quest->delivery_acknowledged_at->toIso8601String(),
                'actor' => $this->actorName($quest, (int) $quest->delivery_acknowledged_by) ?? $quest->client?->name,
                'actor_role' => __('Client'),
                'hint' => __('Deliverable approved — payment releasing.'),
            ];
        }

        if ($quest->delivered_at !== null) {
            return [
                'status' => 'current',
                'at' => $quest->delivered_at->toIso8601String(),
                'actor' => $quest->freelancer?->name,
                'actor_role' => __('Freelancer'),
                'hint' => __('Client is reviewing the submission.'),
            ];
        }

        if ($quest->escrow_funded_at !== null && ($quest->status?->value ?? '') === QuestStatus::InProgress->value) {
            return [
                'status' => 'pending',
                'at' => null,
                'actor' => null,
                'actor_role' => null,
                'hint' => __('Awaiting freelancer deliverable submission.'),
            ];
        }

        return ['status' => 'pending', 'at' => null, 'actor' => null, 'actor_role' => null, 'hint' => null];
    }

    /**
     * @return array{status: string, at: ?string, actor: ?string, actor_role: ?string, hint: ?string}
     */
    private function releasedMeta(Quest $quest): array
    {
        $releasedAt = $quest->funds_released_at ?? $quest->paymentEscrow?->released_at;

        if ($releasedAt !== null || in_array($quest->escrow_status, ['released', 'partially_released'], true)) {
            return [
                'status' => 'completed',
                'at' => $releasedAt?->toIso8601String(),
                'actor' => $quest->client?->name,
                'actor_role' => __('Client'),
                'hint' => __('Funds moved to the freelancer wallet (minus platform fee).'),
            ];
        }

        if ($quest->delivery_acknowledged_at !== null) {
            return [
                'status' => 'current',
                'at' => null,
                'actor' => null,
                'actor_role' => null,
                'hint' => __('Escrow stays protected until the release window opens.'),
            ];
        }

        return ['status' => 'pending', 'at' => null, 'actor' => null, 'actor_role' => null, 'hint' => null];
    }

    private function actorName(Quest $quest, ?int $userId): ?string
    {
        if (! $userId) {
            return null;
        }

        if ((int) $quest->client_id === $userId) {
            return $quest->client?->name;
        }

        if ((int) $quest->freelancer_id === $userId) {
            return $quest->freelancer?->name;
        }

        return User::query()->whereKey($userId)->value('name');
    }
}
