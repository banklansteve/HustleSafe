<?php

namespace App\Services\Verification;

use App\Models\VerificationAnomalyFlag;
use Illuminate\Support\Str;

final class VerificationAnomalyPresenter
{
    public function __construct(
        private readonly VerificationEngineService $engine,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(VerificationAnomalyFlag $flag): array
    {
        $context = is_array($flag->context) ? $flag->context : [];
        $lines = $this->contextLines($flag->type, $context);

        return [
            'id' => $flag->id,
            'type' => $flag->type,
            'type_label' => $this->typeLabel($flag->type),
            'status' => $flag->status,
            'severity' => $flag->severity,
            'summary' => $lines[0] ?? $this->typeLabel($flag->type),
            'details' => $lines,
            'context' => $context,
            'created_at' => $flag->created_at?->toIso8601String(),
            'created_at_label' => \App\Support\FormatsHumanDateTime::format($flag->created_at, config('app.timezone')),
        ];
    }

    public function typeLabel(string $type): string
    {
        return match ($type) {
            'new_account_near_tier_ceiling' => __('New account near tier ceiling'),
            'rapid_verification_then_high_value_action' => __('Rapid verification then high-value action'),
            'proposal_burst_on_high_value_quests' => __('Proposal burst on high-value quests'),
            'quest_repost_limit_exceeded' => __('Quest repost limit exceeded'),
            default => Str::headline(str_replace('_', ' ', $type)),
        };
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<string>
     */
    private function contextLines(string $type, array $context): array
    {
        return match ($type) {
            'new_account_near_tier_ceiling' => [
                __('Account age: :days days', ['days' => (int) ($context['ageDays'] ?? 0)]),
                __('Action value: :amount', ['amount' => $this->money($context['value'] ?? 0)]),
                __('Tier limit: :amount', ['amount' => $this->money($context['limit'] ?? 0)]),
            ],
            'rapid_verification_then_high_value_action' => [
                __('Verifications completed recently: :count', ['count' => (int) ($context['recentVerifications'] ?? 0)]),
                __('High-value action amount: :amount', ['amount' => $this->money($context['value'] ?? 0)]),
            ],
            'proposal_burst_on_high_value_quests' => [
                __('High-value proposals in burst window: :count', ['count' => (int) ($context['recentHighValueOffers'] ?? 0)]),
                __('Burst threshold: :count proposals', ['count' => (int) ($context['burstCount'] ?? 0)]),
            ],
            'quest_repost_limit_exceeded' => array_values(array_filter([
                isset($context['quest_id']) ? __('Quest ID: :id', ['id' => $context['quest_id']]) : null,
                isset($context['repost_count']) ? __('Repost count: :count', ['count' => (int) $context['repost_count']]) : null,
                isset($context['limit']) ? __('Allowed reposts: :limit', ['limit' => (int) $context['limit']]) : null,
                isset($context['title']) ? __('Quest: :title', ['title' => (string) $context['title']]) : null,
            ])),
            default => $this->genericContextLines($context),
        };
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<string>
     */
    private function genericContextLines(array $context): array
    {
        if ($context === []) {
            return [__('No additional context recorded.')];
        }

        $lines = [];
        foreach ($context as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $label = Str::headline(str_replace('_', ' ', (string) $key));
            if (is_scalar($value)) {
                $lines[] = str_contains(strtolower((string) $key), 'minor') || in_array($key, ['value', 'limit', 'amount'], true)
                    ? "{$label}: ".$this->money($value)
                    : "{$label}: {$value}";
            } elseif (is_array($value)) {
                $lines[] = "{$label}: ".implode(', ', array_map('strval', $value));
            }
        }

        return $lines !== [] ? $lines : [__('No additional context recorded.')];
    }

    private function money(mixed $minor): string
    {
        return $this->engine->formatMoneyMinor(max(0, (int) $minor));
    }
}
