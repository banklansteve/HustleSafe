<?php

namespace App\Services\ConversationMonitoring;

use App\Enums\ConversationFlagCategory;
use App\Models\ConversationMonitoringTerm;
use Illuminate\Support\Str;

class ConversationMonitoringScanner
{
    public function __construct(private readonly ConversationFuzzyMatcher $fuzzy) {}

    /**
     * @return list<array{category: ConversationFlagCategory, pattern_redacted: string, confidence: float}>
     */
    public function scan(string $body): array
    {
        $normalized = $this->fuzzy->normalize($body);
        $hits = [];

        foreach ($this->scanOffPlatformPayment($body, $normalized) as $hit) {
            $hits[] = $hit;
        }
        foreach ($this->scanExternalContact($body, $normalized) as $hit) {
            $hits[] = $hit;
        }
        foreach ($this->scanAbusiveTerms($normalized) as $hit) {
            $hits[] = $hit;
        }
        foreach ($this->scanCustomKeywords($normalized) as $hit) {
            $hits[] = $hit;
        }

        return $this->dedupeHits($hits);
    }

    /**
     * @return list<array{category: ConversationFlagCategory, pattern_redacted: string, confidence: float}>
     */
    private function scanOffPlatformPayment(string $raw, string $normalized): array
    {
        $hits = [];
        $maxDist = (int) config('conversation_monitoring.fuzzy.max_levenshtein', 2);

        if (preg_match('/\b\d{10}\b/', $raw)) {
            $hits[] = $this->hit(ConversationFlagCategory::OffPlatformPayment, '[NUBAN:10-digit account]', 1.0);
        }

        foreach (['aza', 'azza', 'acct no', 'account no', 'account number'] as $token) {
            if ($this->fuzzy->containsPhrase($normalized, $token, $maxDist)) {
                $hits[] = $this->hit(ConversationFlagCategory::OffPlatformPayment, '[phrase:'.Str::limit($token, 24).']', $token === 'aza' || $token === 'azza' ? 1.0 : 0.95);
            }
        }

        if (preg_match('/(paystack\.com|flutterwave\.com|pay\.stack|flw\.link)/i', $raw)) {
            $hits[] = $this->hit(ConversationFlagCategory::OffPlatformPayment, '[payment-provider-url]', 1.0);
        }

        foreach (config('conversation_monitoring.off_platform_phrases', []) as $phrase) {
            if ($this->fuzzy->containsPhrase($normalized, (string) $phrase, $maxDist)) {
                $hits[] = $this->hit(
                    ConversationFlagCategory::OffPlatformPayment,
                    '[phrase:'.Str::limit((string) $phrase, 40).']',
                    $this->confidenceFromFuzzy($normalized, (string) $phrase, $maxDist),
                );
            }
        }

        return $hits;
    }

    /**
     * @return list<array{category: ConversationFlagCategory, pattern_redacted: string, confidence: float}>
     */
    private function scanExternalContact(string $raw, string $normalized): array
    {
        $hits = [];
        $maxDist = (int) config('conversation_monitoring.fuzzy.max_levenshtein', 2);

        if (preg_match('/\b0?(?:70|80|81|90|91)\d{8}\b/', $raw)) {
            $hits[] = $this->hit(ConversationFlagCategory::ExternalContact, '[phone:nigerian-mobile]', 1.0);
        }

        if (preg_match('/@[a-z0-9_]{3,32}/i', $raw)) {
            $hits[] = $this->hit(ConversationFlagCategory::ExternalContact, '[social:@handle]', 0.95);
        }

        foreach (config('conversation_monitoring.contact_phrases', []) as $phrase) {
            if ($this->fuzzy->containsPhrase($normalized, (string) $phrase, $maxDist)) {
                $hits[] = $this->hit(
                    ConversationFlagCategory::ExternalContact,
                    '[phrase:'.Str::limit((string) $phrase, 40).']',
                    $this->confidenceFromFuzzy($normalized, (string) $phrase, $maxDist),
                );
            }
        }

        return $hits;
    }

    /**
     * @return list<array{category: ConversationFlagCategory, pattern_redacted: string, confidence: float}>
     */
    private function scanAbusiveTerms(string $normalized): array
    {
        $hits = [];
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
                $hits[] = $this->hit(
                    ConversationFlagCategory::AbusiveLanguage,
                    '[term:'.Str::limit($termNorm, 20).']',
                    str_contains($normalized, $termNorm) ? 1.0 : $this->confidenceFromFuzzy($normalized, $termNorm, $maxDist),
                );
            }
        }

        return $hits;
    }

    /**
     * @return list<array{category: ConversationFlagCategory, pattern_redacted: string, confidence: float}>
     */
    private function scanCustomKeywords(string $normalized): array
    {
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

            if ($matched) {
                $hits[] = $this->hit(
                    ConversationFlagCategory::BlacklistedKeyword,
                    '[keyword:'.Str::limit($pattern, 24).']',
                    1.0,
                );
            }
        }

        return $hits;
    }

    private function wildcardMatch(string $haystack, string $pattern): bool
    {
        $regex = '/^'.str_replace('\*', '.*', preg_quote($pattern, '/')).'$/iu';

        return (bool) preg_match($regex, $haystack);
    }

    private function confidenceFromFuzzy(string $normalized, string $phrase, int $maxDistance): float
    {
        if (str_contains($normalized, $this->fuzzy->normalize($phrase))) {
            return 1.0;
        }

        return max(0.55, 1.0 - (0.15 * $maxDistance));
    }

    /**
     * @param  list<array{category: ConversationFlagCategory, pattern_redacted: string, confidence: float}>  $hits
     * @return list<array{category: ConversationFlagCategory, pattern_redacted: string, confidence: float}>
     */
    private function dedupeHits(array $hits): array
    {
        $seen = [];
        $out = [];
        foreach ($hits as $hit) {
            $key = $hit['category']->value.'|'.$hit['pattern_redacted'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $hit;
        }

        return $out;
    }

    /**
     * @return array{category: ConversationFlagCategory, pattern_redacted: string, confidence: float}
     */
    private function hit(ConversationFlagCategory $category, string $pattern, float $confidence): array
    {
        return [
            'category' => $category,
            'pattern_redacted' => $pattern,
            'confidence' => round(min(1.0, max(0.0, $confidence)), 3),
        ];
    }

    public function redactForDisplay(string $body): string
    {
        $redacted = $body;
        $redacted = preg_replace('/\b\d{10}\b/', '[REDACTED ACCOUNT]', $redacted) ?? $redacted;
        $redacted = preg_replace('/\b0?(?:70|80|81|90|91)\d{8}\b/', '[REDACTED PHONE]', $redacted) ?? $redacted;
        $redacted = preg_replace('/@[a-z0-9_]{3,32}/i', '[REDACTED HANDLE]', $redacted) ?? $redacted;
        $redacted = preg_replace('/(paystack\.com|flutterwave\.com)\S*/i', '[REDACTED URL]', $redacted) ?? $redacted;

        return $redacted;
    }
}
