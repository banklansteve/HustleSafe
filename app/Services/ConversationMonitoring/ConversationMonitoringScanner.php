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
        $raw = $body;
        $normalized = $this->fuzzy->normalize($body);
        $hits = [];

        foreach ($this->scanOffPlatformPayment($raw, $normalized) as $hit) {
            $hits[] = $hit;
        }
        foreach ($this->scanExternalContact($raw, $normalized) as $hit) {
            $hits[] = $hit;
        }
        foreach ($this->scanSpokenPhoneNumbers($raw, $normalized) as $hit) {
            $hits[] = $hit;
        }
        foreach ($this->scanEmailAndHandles($raw, $normalized) as $hit) {
            $hits[] = $hit;
        }
        foreach ($this->scanSocialBypass($raw, $normalized) as $hit) {
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

        if (preg_match('/\b0?(?:70|80|81|90|91|71|81)\d{8}\b/', $raw)) {
            $hits[] = $this->hit(ConversationFlagCategory::ExternalContact, '[phone:nigerian-mobile]', 1.0);
        }

        if (preg_match('/\b0(?:\s*\d){9,12}\b/', $raw)) {
            $hits[] = $this->hit(ConversationFlagCategory::ExternalContact, '[phone:spaced-digits]', 0.98);
        }

        if (preg_match('/@[a-z0-9_]{2,32}/i', $raw)) {
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
    private function scanSpokenPhoneNumbers(string $raw, string $normalized): array
    {
        $hits = [];
        $digitWords = config('conversation_monitoring.spoken_digit_words', []);
        $digitPattern = implode('|', array_map(fn ($w) => preg_quote((string) $w, '/'), $digitWords));

        if ($digitPattern === '') {
            return $hits;
        }

        if (preg_match('/\b(?:'.$digitPattern.')\s+(?:'.$digitPattern.'|\d)(?:\s+(?:'.$digitPattern.'|\d)){4,}/i', $normalized)) {
            $hits[] = $this->hit(ConversationFlagCategory::ExternalContact, '[phone:spoken-sequence]', 0.97);
        }

        if (preg_match('/\b(?:zero|oh|o)\s+(?:one|two|three|four|five|six|seven|eight|nine|\d)\b/i', $normalized)) {
            $hits[] = $this->hit(ConversationFlagCategory::ExternalContact, '[phone:spoken-digit]', 0.92);
        }

        if (preg_match('/\bcall\s+me\b/i', $normalized) && preg_match('/(?:'.$digitPattern.'|\d)/i', $normalized)) {
            $hits[] = $this->hit(ConversationFlagCategory::ExternalContact, '[phone:call-me-context]', 0.9);
        }

        return $hits;
    }

    /**
     * @return list<array{category: ConversationFlagCategory, pattern_redacted: string, confidence: float}>
     */
    private function scanEmailAndHandles(string $raw, string $normalized): array
    {
        $hits = [];

        if (preg_match('/\b[a-z0-9._%+\-]+@[a-z0-9.\-]+\.(com|net|org|co\.uk|io|me)\b/i', $raw)) {
            $hits[] = $this->hit(ConversationFlagCategory::ExternalContact, '[email:address]', 1.0);
        }

        if (preg_match('/@\s*(gmail|yahoo|hotmail|outlook|icloud|protonmail|aol|live|yandex)\b/i', $raw)) {
            $hits[] = $this->hit(ConversationFlagCategory::ExternalContact, '[email:provider-handle]', 0.98);
        }

        if (preg_match('/@\s*[a-z0-9._\-]+\.(com|net|org|co\.uk)\b/i', $raw)) {
            $hits[] = $this->hit(ConversationFlagCategory::ExternalContact, '[email:@domain]', 0.97);
        }

        foreach (['email me', 'send email', 'send me email', 'mail me'] as $phrase) {
            if ($this->fuzzy->containsPhrase($normalized, $phrase, 1)) {
                $hits[] = $this->hit(ConversationFlagCategory::ExternalContact, '[phrase:'.Str::limit($phrase, 24).']', 0.9);
            }
        }

        if (preg_match('/\bhandle\s*[:@]/i', $raw)) {
            $hits[] = $this->hit(ConversationFlagCategory::ExternalContact, '[social:handle-disclosure]', 0.88);
        }

        return $hits;
    }

    /**
     * @return list<array{category: ConversationFlagCategory, pattern_redacted: string, confidence: float}>
     */
    private function scanSocialBypass(string $raw, string $normalized): array
    {
        $hits = [];
        $maxDist = (int) config('conversation_monitoring.fuzzy.max_levenshtein', 2);

        foreach (config('conversation_monitoring.contact_short_tokens', []) as $token) {
            if ($this->containsShortToken($normalized, (string) $token)) {
                $hits[] = $this->hit(
                    ConversationFlagCategory::ExternalContact,
                    '[token:'.Str::limit((string) $token, 16).']',
                    0.85,
                );
            }
        }

        $platformPatterns = [
            '/\b(?:on|via|reach\s+me\s+on|find\s+me\s+on|message\s+me\s+on)\s+(?:x|twitter|tweeter|fb|facebook|snap(?:chat)?|insta(?:gram)?|telegram|teleg|tgm|whatsapp|wa)\b/i',
            '/\b(?:slide\s+(?:into\s+)?(?:my\s+)?dms?)\b/i',
            '/\b(?:dm|pm)\s+me\b/i',
            '/\bmy\s+(?:handle|telegram|whatsapp|snap|insta|twitter|fb)\b/i',
        ];

        foreach ($platformPatterns as $pattern) {
            if (preg_match($pattern, $normalized)) {
                $hits[] = $this->hit(ConversationFlagCategory::ExternalContact, '[social:platform-bypass]', 0.93);
            }
        }

        foreach (['slide', 'handle'] as $word) {
            if ($this->fuzzy->containsPhrase($normalized, $word, $maxDist) && preg_match('/\b(?:dm|dms|@|telegram|whatsapp|snap|insta|twitter|fb|x)\b/i', $normalized)) {
                $hits[] = $this->hit(ConversationFlagCategory::ExternalContact, '[social:'.Str::limit($word, 12).'-context]', 0.88);
            }
        }

        return $hits;
    }

    private function containsShortToken(string $normalized, string $token): bool
    {
        $token = mb_strtolower(trim($token));
        if ($token === '') {
            return false;
        }

        return (bool) preg_match('/(?<!\w)'.preg_quote($token, '/').'(?!\w)/iu', $normalized);
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
        $redacted = preg_replace('/\b0?(?:70|80|81|90|91|71)\d{8}\b/', '[REDACTED PHONE]', $redacted) ?? $redacted;
        $redacted = preg_replace('/\b0(?:\s*\d){9,12}\b/', '[REDACTED PHONE]', $redacted) ?? $redacted;
        $redacted = preg_replace('/@[a-z0-9_]{2,32}/i', '[REDACTED HANDLE]', $redacted) ?? $redacted;
        $redacted = preg_replace('/\b[a-z0-9._%+\-]+@[a-z0-9.\-]+\.(com|net|org|co\.uk|io|me)\b/i', '[REDACTED EMAIL]', $redacted) ?? $redacted;
        $redacted = preg_replace('/(paystack\.com|flutterwave\.com)\S*/i', '[REDACTED URL]', $redacted) ?? $redacted;

        return $redacted;
    }
}
