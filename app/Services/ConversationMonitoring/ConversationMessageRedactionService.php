<?php

namespace App\Services\ConversationMonitoring;

use App\Enums\ConversationFlagCategory;
use App\Models\ProposalClarificationMessage;
use App\Models\QuestConversationMessage;

class ConversationMessageRedactionService
{
    /**
     * @param  list<array{category: ConversationFlagCategory, pattern_redacted: string, confidence: float}>  $hits
     */
    public function applyToConversationMessage(QuestConversationMessage $message, array $hits): QuestConversationMessage
    {
        if ($hits === [] || $message->is_redacted) {
            return $message;
        }

        $label = $this->labelForHits($hits);
        $message->update([
            'body_original' => $message->body_original ?? $message->body,
            'body' => $label,
            'is_redacted' => true,
            'redaction_label' => $label,
        ]);

        return $message->fresh();
    }

    /**
     * @param  list<array{category: ConversationFlagCategory, pattern_redacted: string, confidence: float}>  $hits
     */
    public function applyToClarificationMessage(ProposalClarificationMessage $message, array $hits): ProposalClarificationMessage
    {
        if ($hits === [] || $message->is_redacted) {
            return $message;
        }

        $label = $this->labelForHits($hits);
        $message->update([
            'body_original' => $message->body_original ?? $message->body,
            'body' => $label,
            'is_redacted' => true,
            'redaction_label' => $label,
        ]);

        return $message->fresh();
    }

    /**
     * @param  list<array{category: ConversationFlagCategory, pattern_redacted: string, confidence: float}>  $hits
     */
    public function labelForHits(array $hits): string
    {
        $priority = [
            ConversationFlagCategory::OffPlatformPayment->value => 'REDACTED OFF-PLATFORM PAYMENT',
            ConversationFlagCategory::ExternalContact->value => 'REDACTED CONTACT',
            ConversationFlagCategory::AbusiveLanguage->value => 'REDACTED — POLICY VIOLATION',
            ConversationFlagCategory::BlacklistedKeyword->value => 'REDACTED — POLICY VIOLATION',
        ];

        foreach ($priority as $category => $label) {
            foreach ($hits as $hit) {
                if (($hit['category']->value ?? $hit['category']) === $category) {
                    return $this->refineLabel($label, (string) ($hit['pattern_redacted'] ?? ''));
                }
            }
        }

        $first = $hits[0] ?? null;
        if ($first) {
            return $this->refineLabel('REDACTED — POLICY VIOLATION', (string) ($first['pattern_redacted'] ?? ''));
        }

        return 'REDACTED — POLICY VIOLATION';
    }

    private function refineLabel(string $fallback, string $pattern): string
    {
        if (str_contains($pattern, 'EMAIL') || str_contains($pattern, 'email')) {
            return 'REDACTED EMAIL';
        }
        if (str_contains($pattern, 'PHONE') || str_contains($pattern, 'phone')) {
            return 'REDACTED PHONE';
        }
        if (str_contains($pattern, 'ACCOUNT') || str_contains($pattern, 'NUBAN')) {
            return 'REDACTED ACCOUNT';
        }
        if (str_contains($pattern, 'HANDLE') || str_contains($pattern, 'social')) {
            return 'REDACTED CONTACT';
        }
        if (str_contains($pattern, 'URL') || str_contains($pattern, 'payment-provider')) {
            return 'REDACTED PAYMENT LINK';
        }

        return $fallback;
    }

    /**
     * @return array<string, mixed>
     */
    public function publicMessagePayload(string $body, bool $isRedacted, ?string $redactionLabel): array
    {
        return [
            'body' => $isRedacted ? ($redactionLabel ?: $body) : $body,
            'is_redacted' => $isRedacted,
            'redaction_label' => $isRedacted ? ($redactionLabel ?: $body) : null,
        ];
    }
}
