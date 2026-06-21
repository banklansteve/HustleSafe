<?php

namespace App\Services\ConversationMonitoring;

use App\Enums\ConversationFlagCategory;
use App\Models\ConversationMonitoringTerm;
use App\Services\ConversationMonitoring\Detection\AbusiveLanguageDetector;
use App\Services\ConversationMonitoring\Detection\OffPlatformContactDetector;
use App\Services\ConversationMonitoring\Detection\OffPlatformPaymentDetector;
use App\Services\ConversationMonitoring\Support\ConversationMessageTokenizer;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ConversationMonitoringEngine
{
    public function __construct(
        private readonly ConversationMessageTokenizer $tokenizer,
        private readonly OffPlatformPaymentDetector $paymentDetector,
        private readonly OffPlatformContactDetector $contactDetector,
        private readonly AbusiveLanguageDetector $abusiveDetector,
        private readonly ConversationFuzzyMatcher $fuzzy,
    ) {}

    /**
     * @return list<array{
     *     category: ConversationFlagCategory,
     *     pattern_redacted: string,
     *     confidence: float,
     *     confidence_score: int,
     *     pattern_score: int,
     *     context_score: int,
     *     reasoning: list<array<string, mixed>>,
     * }>
     */
    public function analyze(string $body): array
    {
        $body = trim($body);
        if ($body === '') {
            return [];
        }

        $analysis = $this->tokenizer->analyze($body);
        $candidates = array_filter([
            $this->paymentDetector->detect($body, $analysis),
            $this->contactDetector->detect($body, $analysis),
            $this->abusiveDetector->detect($body, $analysis),
            ...$this->customKeywordCandidates((string) $analysis['normalized']),
        ]);

        $threshold = $this->flagThreshold();
        $hits = [];

        foreach ($candidates as $candidate) {
            if (($candidate['confidence_score'] ?? 0) < $threshold) {
                continue;
            }

            $category = $candidate['category'];
            $key = $category->value;
            if (isset($hits[$key]) && ($hits[$key]['confidence_score'] ?? 0) >= ($candidate['confidence_score'] ?? 0)) {
                continue;
            }

            $hits[$key] = [
                'category' => $category,
                'pattern_redacted' => (string) $candidate['pattern_redacted'],
                'confidence' => round(($candidate['confidence_score'] ?? 0) / 100, 3),
                'confidence_score' => (int) $candidate['confidence_score'],
                'pattern_score' => (int) ($candidate['pattern_score'] ?? 0),
                'context_score' => (int) ($candidate['context_score'] ?? 0),
                'reasoning' => $candidate['reasoning'] ?? [],
            ];
        }

        return array_values($hits);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function customKeywordCandidates(string $normalized): array
    {
        if (! Schema::hasTable('conversation_monitoring_terms')) {
            return [];
        }

        $hits = [];
        $keywords = ConversationMonitoringTerm::query()
            ->where('term_type', 'custom_keyword')
            ->where('is_active', true)
            ->get();

        foreach ($keywords as $keyword) {
            $pattern = $this->fuzzy->normalize((string) $keyword->pattern);
            if ($pattern === '') {
                continue;
            }

            $matched = $keyword->is_wildcard
                ? $this->wildcardMatch($normalized, $pattern)
                : (str_contains($normalized, $pattern) || $this->fuzzy->fuzzyTermMatch($normalized, $pattern, (int) config('conversation_monitoring.fuzzy.max_levenshtein', 2)));

            if (! $matched) {
                continue;
            }

            $hits[] = [
                'category' => ConversationFlagCategory::BlacklistedKeyword,
                'confidence_score' => 90,
                'pattern_score' => 45,
                'context_score' => 45,
                'pattern_redacted' => '[keyword:'.Str::limit($pattern, 24).']',
                'reasoning' => [[
                    'signal' => 'admin_custom_keyword',
                    'match' => $pattern,
                    'points' => 90,
                    'kind' => 'pattern',
                ]],
            ];
        }

        return $hits;
    }

    private function wildcardMatch(string $haystack, string $pattern): bool
    {
        $regex = '/'.str_replace('\*', '.*', preg_quote($pattern, '/')).'/iu';

        return (bool) preg_match($regex, $haystack);
    }

    private function flagThreshold(): int
    {
        $base = (int) config('conversation_monitoring.scoring.flag_threshold', 85);

        try {
            $adjustment = app(ConversationThresholdAdjustmentService::class)->thresholdAdjustment();
        } catch (\Throwable) {
            $adjustment = 0;
        }

        return max(70, min(95, $base + $adjustment));
    }
}
