<?php

namespace App\Services\ConversationMonitoring\Detection;

use App\Enums\ConversationFlagCategory;
use App\Models\ConversationMonitoringTerm;
use App\Services\ConversationMonitoring\ConversationFuzzyMatcher;
use App\Services\ConversationMonitoring\Support\ConversationMessageTokenizer;
use Illuminate\Support\Facades\Schema;

class AbusiveLanguageDetector
{
    public function __construct(
        private readonly ConversationMessageTokenizer $tokenizer,
        private readonly ConversationFuzzyMatcher $fuzzy,
    ) {}

    /**
     * @param  array<string, mixed>  $analysis
     * @return array<string, mixed>|null
     */
    public function detect(string $raw, array $analysis): ?array
    {
        $normalized = (string) $analysis['normalized'];
        $weights = config('conversation_monitoring.abuse.weights', []);
        $reasoning = [];
        $score = 0;

        $directed = $this->isDirectedAtOtherParty($normalized);

        foreach ($this->matchTerms($normalized, config('conversation_monitoring.abuse.discriminatory', [])) as $match) {
            $score = (int) config('conversation_monitoring.abuse.auto_flag_discriminatory_score', 99);
            $reasoning[] = $this->reason('discriminatory_language', $match, (int) ($weights['discriminatory'] ?? 60));

            return $this->buildResult($score, $reasoning, '[abuse:discriminatory]');
        }

        foreach ($this->matchTerms($normalized, config('conversation_monitoring.abuse.threats', [])) as $match) {
            $score += (int) ($weights['threat'] ?? 45);
            $reasoning[] = $this->reason('threat', $match, (int) ($weights['threat'] ?? 45));
        }

        if ($score >= (int) config('conversation_monitoring.abuse.threat_flag_score', 90)) {
            return $this->buildResult($score, $reasoning, '[abuse:threat]');
        }

        $insultCount = 0;
        foreach ($this->matchTerms($normalized, config('conversation_monitoring.abuse.insults', [])) as $match) {
            if (! $directed) {
                continue;
            }
            $insultCount++;
            $score += (int) ($weights['directed_insult'] ?? 30);
            $reasoning[] = $this->reason('directed_insult', $match, (int) ($weights['directed_insult'] ?? 30));
        }

        foreach ($this->databaseAbusiveTerms($normalized) as $match) {
            if (! $directed) {
                continue;
            }
            $score += (int) ($weights['directed_insult'] ?? 30);
            $reasoning[] = $this->reason('blacklisted_term', $match, (int) ($weights['directed_insult'] ?? 30));
            $insultCount++;
        }

        if ($this->isSelfDirected($normalized)) {
            $score += (int) ($weights['self_directed_dampener'] ?? -25);
            $reasoning[] = $this->reason('self_directed', 'self-directed criticism', (int) ($weights['self_directed_dampener'] ?? -25));
        }

        if ($this->isSystemCriticism($normalized)) {
            $score += (int) ($weights['system_criticism_dampener'] ?? -20);
            $reasoning[] = $this->reason('system_criticism', 'system/platform criticism', (int) ($weights['system_criticism_dampener'] ?? -20));
        }

        if ($this->isWorkCriticism($normalized) && $insultCount === 0) {
            $score += (int) ($weights['work_criticism_dampener'] ?? -20);
            $reasoning[] = $this->reason('work_criticism', 'work-quality feedback', (int) ($weights['work_criticism_dampener'] ?? -20));
        }

        if ($this->tokenizer->isEmphatic($raw, $analysis) && $directed && $insultCount > 0) {
            $score += (int) ($weights['all_caps'] ?? 10);
            $reasoning[] = $this->reason('emphatic_delivery', 'emphatic or all-caps delivery', (int) ($weights['all_caps'] ?? 10));
        }

        $score = max(0, min(100, $score));
        if ($score < 1 || $reasoning === []) {
            return null;
        }

        return $this->buildResult(
            $score,
            $reasoning,
            '[term:'.mb_substr((string) (collect($reasoning)->firstWhere('signal', 'directed_insult')['match'] ?? 'abusive_language'), 0, 24).']',
        );
    }

    /**
     * @param  list<array<string, mixed>>  $reasoning
     * @return array<string, mixed>
     */
    private function buildResult(int $score, array $reasoning, string $pattern): array
    {
        $patternScore = min(50, collect($reasoning)->where('kind', 'pattern')->sum('points'));
        $contextScore = max(0, $score - $patternScore);

        return [
            'category' => ConversationFlagCategory::AbusiveLanguage,
            'confidence_score' => $score,
            'pattern_score' => $patternScore,
            'context_score' => $contextScore,
            'pattern_redacted' => $pattern,
            'reasoning' => $reasoning,
        ];
    }

    /**
     * @param  list<string>  $terms
     * @return list<string>
     */
    private function matchTerms(string $normalized, array $terms): array
    {
        $matches = [];
        foreach ($terms as $term) {
            $termNorm = $this->tokenizer->normalize((string) $term);
            if ($termNorm === '') {
                continue;
            }
            if ($this->tokenizer->containsToken($normalized, $termNorm) || str_contains($normalized, $termNorm)) {
                $matches[] = $termNorm;
            }
        }

        return $matches;
    }

    /**
     * @return list<string>
     */
    private function databaseAbusiveTerms(string $normalized): array
    {
        if (! Schema::hasTable('conversation_monitoring_terms')) {
            return [];
        }
        $matches = [];
        $maxDist = (int) config('conversation_monitoring.fuzzy.max_levenshtein', 2);
        $terms = ConversationMonitoringTerm::query()
            ->where('term_type', 'abusive_blacklist')
            ->where('is_active', true)
            ->pluck('pattern');

        foreach ($terms as $term) {
            $termNorm = $this->fuzzy->normalize((string) $term);
            if ($termNorm === '') {
                continue;
            }
            if (str_contains($normalized, $termNorm) || $this->fuzzy->fuzzyTermMatch($normalized, $termNorm, $maxDist)) {
                $matches[] = $termNorm;
            }
        }

        return $matches;
    }

    private function isDirectedAtOtherParty(string $normalized): bool
    {
        if (preg_match('/\b(?:you|your|u)\b/u', $normalized)) {
            return true;
        }

        return ! $this->isSelfDirected($normalized);
    }

    private function isSelfDirected(string $normalized): bool
    {
        foreach (config('conversation_monitoring.abuse.self_directed_markers', []) as $marker) {
            if ($this->tokenizer->containsPhrase($normalized, (string) $marker)) {
                return true;
            }
        }

        return (bool) preg_match('/\bi\s+(?:am|was|feel)\s+(?:stupid|foolish|dumb|useless)\b/u', $normalized);
    }

    private function isSystemCriticism(string $normalized): bool
    {
        return $this->tokenizer->matchPhrases($normalized, config('conversation_monitoring.abuse.system_criticism', [])) !== [];
    }

    private function isWorkCriticism(string $normalized): bool
    {
        return $this->tokenizer->matchPhrases($normalized, config('conversation_monitoring.abuse.work_criticism', [])) !== [];
    }

    /**
     * @return array{signal: string, match: string, points: int, kind: string}
     */
    private function reason(string $signal, string $match, int $points): array
    {
        return [
            'signal' => $signal,
            'match' => $match,
            'points' => $points,
            'kind' => in_array($signal, ['directed_insult', 'blacklisted_term', 'discriminatory_language'], true) ? 'pattern' : 'context',
        ];
    }
}
