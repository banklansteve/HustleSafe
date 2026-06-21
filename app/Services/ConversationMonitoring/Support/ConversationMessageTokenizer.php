<?php

namespace App\Services\ConversationMonitoring\Support;

use App\Services\ConversationMonitoring\ConversationFuzzyMatcher;

class ConversationMessageTokenizer
{
    /**
     * @return array{
     *     raw: string,
     *     normalized: string,
     *     compact: string,
     *     tokens: list<string>,
     *     alpha_ratio: float,
     *     uppercase_ratio: float,
     * }
     */
    public function analyze(string $body): array
    {
        $raw = trim($body);
        $normalized = $this->normalize($raw);
        $compact = preg_replace('/[\s\-_.]+/u', '', $normalized) ?? $normalized;
        $tokens = preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $letters = preg_replace('/[^a-z]/u', '', $normalized) ?? '';
        $upper = preg_replace('/[^A-Z]/', '', $raw) ?? '';
        $allLetters = preg_replace('/[^A-Za-z]/', '', $raw) ?? '';

        return [
            'raw' => $raw,
            'normalized' => $normalized,
            'compact' => $compact,
            'tokens' => array_values($tokens),
            'alpha_ratio' => mb_strlen($letters) > 0 ? 1.0 : 0.0,
            'uppercase_ratio' => strlen($allLetters) > 0 ? strlen($upper) / strlen($allLetters) : 0.0,
        ];
    }

    public function normalize(string $text): string
    {
        $text = mb_strtolower($text);
        $text = str_replace(['0', '1', '3', '4', '5', '@'], ['o', 'i', 'e', 'a', 's', 'a'], $text);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    public function containsPhrase(string $haystack, string $phrase): bool
    {
        $phrase = trim($this->normalize($phrase));
        if ($phrase === '') {
            return false;
        }

        $words = preg_split('/\s+/u', $phrase, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($words) <= 1) {
            return $this->containsToken($haystack, $phrase);
        }

        if (str_contains($haystack, $phrase)) {
            return true;
        }

        $pattern = implode('[\\s\\-_.]{0,4}', array_map(fn ($word) => preg_quote($word, '/'), $words));

        return (bool) preg_match('/'.$pattern.'/u', $haystack);
    }

    public function containsToken(string $haystack, string $token): bool
    {
        $token = trim($this->normalize($token));
        if ($token === '') {
            return false;
        }

        return (bool) preg_match('/(?<![a-z0-9])'.preg_quote($token, '/').'(?![a-z0-9])/u', $haystack);
    }

    /**
     * @return list<array{phrase: string, match: string}>
     */
    public function matchPlatforms(string $raw, string $normalized): array
    {
        $matches = $this->matchPhrases($normalized, config('conversation_monitoring.contact.platforms', []));

        foreach (config('conversation_monitoring.contact.platform_aliases', []) as $alias) {
            $alias = (string) $alias;
            if ($this->containsToken($this->digitHaystack($raw), $alias)
                || $this->containsToken($normalized, $alias)) {
                $matches[] = ['phrase' => $alias, 'match' => $alias];
            }
        }

        foreach ($this->matchFuzzyPlatforms($raw, $normalized) as $match) {
            $matches[] = ['phrase' => $match, 'match' => $match];
        }

        $unique = [];
        foreach ($matches as $match) {
            $key = (string) ($match['match'] ?? '');
            if ($key !== '' && ! isset($unique[$key])) {
                $unique[$key] = $match;
            }
        }

        return array_values($unique);
    }

    /**
     * @return list<string>
     */
    public function matchFuzzyPlatforms(string $raw, string $normalized): array
    {
        $fuzzy = app(ConversationFuzzyMatcher::class);
        $haystack = trim($this->digitHaystack($raw).' '.$normalized);
        $maxDistance = (int) config('conversation_monitoring.fuzzy.platform_max_levenshtein', 3);
        $matches = [];

        foreach (config('conversation_monitoring.contact.fuzzy_platforms', []) as $platform) {
            $platform = (string) $platform;
            if ($platform === '') {
                continue;
            }

            if ($fuzzy->fuzzyTermMatch($haystack, $platform, $maxDistance)) {
                $matches[] = $platform;
            }
        }

        return array_values(array_unique($matches));
    }

    /**
     * @return list<array{phrase: string, match: string}>
     */
    public function matchPaymentProviders(string $raw, string $normalized): array
    {
        $matches = [];
        $haystacks = [$this->digitHaystack($raw), $normalized];

        foreach (config('conversation_monitoring.payment.payment_providers', []) as $provider) {
            $provider = (string) $provider;
            if ($provider === '') {
                continue;
            }

            foreach ($haystacks as $haystack) {
                if ($this->containsToken($haystack, $provider) || $this->containsPhrase($haystack, $provider)) {
                    $matches[] = ['phrase' => $provider, 'match' => $provider];
                    break;
                }
            }
        }

        $unique = [];
        foreach ($matches as $match) {
            $key = (string) ($match['match'] ?? '');
            if ($key !== '' && ! isset($unique[$key])) {
                $unique[$key] = $match;
            }
        }

        return array_values($unique);
    }

    /**
     * @param  list<string>  $phrases
     * @return list<array{phrase: string, match: string}>
     */
    public function matchPhrases(string $haystack, array $phrases): array
    {
        $matches = [];
        foreach ($phrases as $phrase) {
            $phrase = (string) $phrase;
            if ($this->containsPhrase($haystack, $phrase)) {
                $matches[] = ['phrase' => $phrase, 'match' => $phrase];
            }
        }

        return $matches;
    }

    /**
     * @return array{score: int, label: string}|null
     */
    public function obfuscatedNumberPattern(string $raw, string $normalized): ?array
    {
        if (preg_match('/\b\d{10}\b/', $raw)) {
            return ['score' => 25, 'label' => 'ten_digit_sequence'];
        }

        $digitHaystack = $this->digitHaystack($raw);

        if (preg_match('/\b0(?:\s*[\d'.implode('', config('conversation_monitoring.spoken_digit_words', [])).']){9,14}\b/iu', $digitHaystack)) {
            return ['score' => 25, 'label' => 'spoken_or_spaced_digit_run'];
        }

        $digitWords = config('conversation_monitoring.spoken_digit_words', []);
        $digitPattern = implode('|', array_map(fn ($w) => preg_quote((string) $w, '/'), $digitWords));
        if ($digitPattern !== '' && preg_match('/\b(?:'.$digitPattern.')\s+(?:'.$digitPattern.'|\d)(?:\s+(?:'.$digitPattern.'|\d)){3,}/iu', $digitHaystack)) {
            return ['score' => 20, 'label' => 'spoken_digit_cluster'];
        }

        if (preg_match('/\b0(?:\s*\d){9,12}\b/', $raw)) {
            return ['score' => 20, 'label' => 'spaced_phone_digits'];
        }

        if (preg_match('/\b0[789][01]\d{8}\b/', preg_replace('/\s+/', '', $raw) ?? $raw)) {
            return ['score' => 20, 'label' => 'nigerian_mobile'];
        }

        $collapsed = $this->collapseSpokenDigits($raw);
        if ($collapsed !== null) {
            return ['score' => 25, 'label' => 'mixed_spoken_phone'];
        }

        $mixed = $this->mixedWordFigurePattern($raw);
        if ($mixed !== null) {
            return $mixed;
        }

        return null;
    }

    /**
     * Detects interleaved spoken-number words and numeric tokens — a common off-platform escape pattern.
     *
     * @return array{score: int, label: string}|null
     */
    public function mixedWordFigurePattern(string $raw): ?array
    {
        $tokens = preg_split('/\s+/u', $this->digitHaystack($raw), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($tokens === []) {
            return null;
        }

        $map = [
            'zero' => '0', 'oh' => '0', 'o' => '0',
            'one' => '1', 'two' => '2', 'three' => '3', 'four' => '4',
            'five' => '5', 'six' => '6', 'seven' => '7', 'eight' => '8', 'et' => '8', 'nine' => '9',
        ];
        $fillers = ['and', 'et', 'at', 'on', 'the', 'a', 'to', 'my', 'me'];

        $digitBearingTokens = 0;
        $collapsed = '';
        $inRun = false;
        $runLength = 0;

        foreach ($tokens as $token) {
            $isDigitBearing = false;
            $chunk = '';

            if (preg_match('/^\d+$/', $token)) {
                $isDigitBearing = true;
                $chunk = $token;
            } elseif (isset($map[$token])) {
                $isDigitBearing = true;
                $chunk = $map[$token];
            } elseif (preg_match('/^(?:\d+[a-z]+|[a-z]+\d+)$/u', $token)) {
                $isDigitBearing = true;
                $chunk = preg_replace('/\D+/u', '', $token) ?? '';
            }

            if ($isDigitBearing && $chunk !== '') {
                $digitBearingTokens++;
                $collapsed .= $chunk;
                $runLength++;
                $inRun = true;

                continue;
            }

            if ($inRun && ! in_array($token, $fillers, true)) {
                $inRun = false;
            }
        }

        if ($digitBearingTokens >= 4 && strlen($collapsed) >= 7) {
            return ['score' => 20, 'label' => 'mixed_word_figure_cluster'];
        }

        if ($runLength >= 4 && strlen($collapsed) >= 6) {
            return ['score' => 20, 'label' => 'mixed_word_figure_run'];
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function matchContactCueTokens(string $normalized): array
    {
        $matches = [];
        foreach (config('conversation_monitoring.contact.cue_tokens', []) as $token) {
            $token = (string) $token;
            if ($this->containsToken($normalized, $token)) {
                $matches[] = $token;
            }
        }

        return $matches;
    }

    public function digitHaystack(string $raw): string
    {
        $text = mb_strtolower(trim($raw));
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text) ?? $text;

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    public function collapseSpokenDigits(string $raw): ?string
    {
        $tokens = preg_split('/\s+/u', $this->digitHaystack($raw), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($tokens === []) {
            return null;
        }

        $map = [
            'zero' => '0', 'oh' => '0', 'o' => '0',
            'one' => '1', 'two' => '2', 'three' => '3', 'four' => '4',
            'five' => '5', 'six' => '6', 'seven' => '7', 'eight' => '8', 'et' => '8', 'nine' => '9',
        ];

        $digits = '';
        foreach ($tokens as $token) {
            if (preg_match('/^\d+$/', $token)) {
                $digits .= $token;

                continue;
            }

            if (isset($map[$token])) {
                $digits .= $map[$token];
            }
        }

        if (strlen($digits) < 10) {
            return null;
        }

        if (preg_match('/0[789][01]\d{8}/', $digits)) {
            return $digits;
        }

        if (strlen($digits) >= 10 && preg_match('/\d{10,}/', $digits)) {
            return $digits;
        }

        return null;
    }

    public function hasNigerianPhone(string $raw): bool
    {
        return (bool) preg_match('/\b0[789][01]\d{8}\b/', preg_replace('/[\s\-_.]/', '', $raw) ?? $raw);
    }

    public function hasEmail(string $raw): bool
    {
        return (bool) preg_match('/\b[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}\b/i', $raw);
    }

    public function isLocationContext(string $normalized, ?array $numberPattern = null): bool
    {
        foreach (config('conversation_monitoring.payment.location_dampeners', []) as $marker) {
            if ($this->containsPhrase($normalized, (string) $marker)) {
                return true;
            }
        }

        if ($numberPattern && preg_match('/\b(?:zero|oh|o)\s*(?:8|eight)\s*(?:zero|oh|o)\s*(?:state|area|lga|city)\b/u', $normalized)) {
            return true;
        }

        return false;
    }

    public function isEmphatic(string $raw, array $analysis): bool
    {
        if (($analysis['uppercase_ratio'] ?? 0) >= 0.55 && strlen($raw) >= 8) {
            return true;
        }

        return substr_count($raw, '!') >= 2;
    }

    /**
     * @param  list<array{phrase: string, match: string}>  $matches
     * @return list<array{canonical: string, token: string, obfuscated: bool}>
     */
    public function resolveCanonicalPlatforms(array $matches): array
    {
        $resolved = [];

        foreach ($matches as $match) {
            $token = mb_strtolower((string) ($match['match'] ?? ''));
            if ($token === '') {
                continue;
            }

            foreach (config('conversation_monitoring.contact.canonical_platforms', []) as $canonical => $variants) {
                $canonical = (string) $canonical;
                $variants = array_map(static fn ($variant) => mb_strtolower((string) $variant), (array) $variants);

                if ($token !== $canonical && ! in_array($token, $variants, true)) {
                    continue;
                }

                if (! isset($resolved[$canonical])) {
                    $resolved[$canonical] = [
                        'canonical' => $canonical,
                        'token' => $token,
                        'obfuscated' => $this->isObfuscatedPlatformToken($token, $canonical),
                    ];

                    continue;
                }

                if ($this->isObfuscatedPlatformToken($token, $canonical)) {
                    $resolved[$canonical]['obfuscated'] = true;
                }
            }
        }

        return array_values($resolved);
    }

    /**
     * @param  list<array{phrase: string, match: string}>  $matches
     * @return list<array{canonical: string, token: string, obfuscated: bool}>
     */
    public function resolveCanonicalPaymentProviders(array $matches): array
    {
        $resolved = [];

        foreach ($matches as $match) {
            $token = mb_strtolower((string) ($match['match'] ?? ''));
            if ($token === '') {
                continue;
            }

            foreach (config('conversation_monitoring.payment.canonical_payment_providers', []) as $canonical => $variants) {
                $canonical = (string) $canonical;
                $variants = array_map(static fn ($variant) => mb_strtolower((string) $variant), (array) $variants);

                if ($token !== $canonical && ! in_array($token, $variants, true)) {
                    continue;
                }

                $obfuscated = $token !== $canonical;

                if (! isset($resolved[$canonical])) {
                    $resolved[$canonical] = [
                        'canonical' => $canonical,
                        'token' => $token,
                        'obfuscated' => $obfuscated,
                    ];

                    continue;
                }

                if ($obfuscated) {
                    $resolved[$canonical]['obfuscated'] = true;
                }
            }
        }

        return array_values($resolved);
    }

    public function isObfuscatedPlatformToken(string $token, string $canonical): bool
    {
        $token = mb_strtolower($token);
        $canonical = mb_strtolower($canonical);

        if (in_array($token, config('conversation_monitoring.contact.short_platform_tokens', []), true)) {
            return false;
        }

        if (in_array($token, config('conversation_monitoring.contact.platform_aliases', []), true)) {
            return true;
        }

        return $token !== $canonical;
    }

    public function hasBenignPlatformContext(string $normalized): bool
    {
        foreach (config('conversation_monitoring.contact.benign_platform_context_patterns', []) as $pattern) {
            if ($this->containsPhrase($normalized, (string) $pattern)) {
                return true;
            }
        }

        return false;
    }
}
