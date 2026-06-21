<?php

namespace App\Services\ConversationMonitoring;

class ConversationTriggerHighlightService
{
    public function __construct(private readonly ConversationFuzzyMatcher $fuzzy) {}

    /**
     * @return list<array{start: int, end: int}>
     */
    public function spans(string $original, string $patternRedacted): array
    {
        $span = $this->locateSpan($original, $patternRedacted);

        return $span ? [$span] : [];
    }

    /**
     * @return array{start: int, end: int}|null
     */
    public function locateSpan(string $original, string $patternRedacted): ?array
    {
        $original = (string) $original;
        $patternRedacted = trim($patternRedacted);

        if ($original === '' || $patternRedacted === '') {
            return null;
        }

        if (preg_match('/^\[(payment|contact|abuse|keyword):(.+)\]$/', $patternRedacted, $matches)) {
            return $this->locateLiteralNeedle($original, trim($matches[2]));
        }

        if (preg_match('/^\[(phrase|term|token):(.+)\]$/', $patternRedacted, $matches)) {
            return $this->locateLiteralNeedle($original, trim($matches[2]));
        }

        if (preg_match('/^\[social:(.+)\]$/', $patternRedacted, $matches)) {
            return $this->locateSocialContext($original, (string) $matches[1]);
        }

        return match ($patternRedacted) {
            '[NUBAN:10-digit account]' => $this->locateRegex($original, '/\b\d{10}\b/'),
            '[phone:nigerian-mobile]' => $this->locateRegex($original, '/\b0?(?:70|80|81|90|91|71|81)\d{8}\b/'),
            '[phone:spaced-digits]' => $this->locateRegex($original, '/\b0(?:\s*\d){9,12}\b/'),
            '[phone:spoken-sequence]' => $this->locateRegex($original, '/\b(?:zero|oh|o|one|two|three|four|five|six|seven|eight|nine|\d)(?:\s+(?:zero|oh|o|one|two|three|four|five|six|seven|eight|nine|\d)){4,}\b/i'),
            '[phone:spoken-digit]' => $this->locateRegex($original, '/\b(?:zero|oh|o)\s+(?:one|two|three|four|five|six|seven|eight|nine|\d)\b/i'),
            '[phone:call-me-context]' => $this->locateRegex($original, '/\bcall\s+me\b.{0,80}(?:\d|zero|oh|o|one|two|three|four|five|six|seven|eight|nine)/i')
                ?? $this->locateRegex($original, '/\bcall\s+me\b/i'),
            '[social:@handle]' => $this->locateRegex($original, '/@[a-z0-9_]{2,32}/i'),
            '[email:address]' => $this->locateRegex($original, '/\b[a-z0-9._%+\-]+@[a-z0-9.\-]+\.(com|net|org|co\.uk|io|me)\b/i'),
            '[email:phrase-context]' => $this->locateRegex($original, '/\b(?:email|e-mail|mail)\b.{0,40}(?:@|[a-z0-9._%+\-]+@)/i'),
            '[payment-provider-url]' => $this->locateRegex($original, '/(paystack\.com|flutterwave\.com|pay\.stack|flw\.link)\S*/i'),
            '[social:platform-bypass]' => $this->locateSocialContext($original, 'platform-bypass'),
            default => null,
        };
    }

    /**
     * @return array{start: int, end: int}|null
     */
    private function locateLiteralNeedle(string $original, string $needle): ?array
    {
        if ($needle === '') {
            return null;
        }

        $pos = mb_stripos($original, $needle);
        if ($pos !== false) {
            return [
                'start' => $pos,
                'end' => $pos + mb_strlen($needle),
            ];
        }

        $words = preg_split('/\s+/', $needle, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($words) > 1) {
            $pattern = implode('[\\s\\-_.]{0,6}', array_map(fn ($word) => preg_quote($word, '/'), $words));
            if (preg_match('/'.$pattern.'/iu', $original, $match, PREG_OFFSET_CAPTURE)) {
                return $this->spanFromByteMatch($original, $match[0]);
            }
        }

        $normalizedHaystack = $this->fuzzy->normalize($original);
        $normalizedNeedle = $this->fuzzy->normalize($needle);
        if ($normalizedNeedle !== '' && str_contains($normalizedHaystack, $normalizedNeedle)) {
            return $this->locateNormalizedSubstring($original, $normalizedNeedle);
        }

        $maxDistance = (int) config('conversation_monitoring.fuzzy.max_levenshtein', 2);
        if ($this->fuzzy->fuzzyTermMatch($normalizedHaystack, $normalizedNeedle, $maxDistance)) {
            return $this->locateFuzzyToken($original, $normalizedNeedle, $maxDistance);
        }

        return null;
    }

    /**
     * @return array{start: int, end: int}|null
     */
    private function locateSocialContext(string $original, string $context): ?array
    {
        $patterns = [
            '/\b(?:on|via|reach\s+me\s+on|find\s+me\s+on|message\s+me\s+on)\s+(?:x|twitter|tweeter|fb|facebook|snap(?:chat)?|insta(?:gram)?|telegram|teleg|tgm|whatsapp|wa)\b/i',
            '/\b(?:slide\s+(?:into\s+)?(?:my\s+)?dms?)\b/i',
            '/\b(?:dm|pm)\s+me\b/i',
            '/\bmy\s+(?:handle|telegram|whatsapp|snap|insta|twitter|fb)\b/i',
        ];

        if ($context !== 'platform-bypass') {
            $patterns[] = '/\b'.preg_quote($context, '/').'\b/i';
        }

        foreach ($patterns as $pattern) {
            $span = $this->locateRegex($original, $pattern);
            if ($span) {
                return $span;
            }
        }

        return null;
    }

    /**
     * @param  array{0: string, 1: int}  $byteMatch
     * @return array{start: int, end: int}
     */
    private function spanFromByteMatch(string $original, array $byteMatch): array
    {
        $start = mb_strlen(mb_strcut($original, 0, $byteMatch[1]));
        $end = $start + mb_strlen($byteMatch[0]);

        return ['start' => $start, 'end' => $end];
    }

    /**
     * @return array{start: int, end: int}|null
     */
    private function locateRegex(string $original, string $pattern): ?array
    {
        if (! preg_match($pattern, $original, $match, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        return $this->spanFromByteMatch($original, $match[0]);
    }

    /**
     * @return array{start: int, end: int}|null
     */
    private function locateNormalizedSubstring(string $original, string $normalizedNeedle): ?array
    {
        $length = mb_strlen($original);
        $needleLength = mb_strlen($normalizedNeedle);

        for ($start = 0; $start < $length; $start++) {
            for ($end = $start + 1; $end <= $length; $end++) {
                $slice = mb_substr($original, $start, $end - $start);
                if ($this->fuzzy->normalize($slice) === $normalizedNeedle) {
                    return ['start' => $start, 'end' => $end];
                }

                if (mb_strlen($this->fuzzy->normalize($slice)) > $needleLength) {
                    break;
                }
            }
        }

        return null;
    }

    /**
     * @return array{start: int, end: int}|null
     */
    private function locateFuzzyToken(string $original, string $normalizedNeedle, int $maxDistance): ?array
    {
        foreach (preg_split('/(\s+)/u', $original, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
            if (trim($token) === '') {
                continue;
            }

            if (levenshtein($this->fuzzy->normalize($token), $normalizedNeedle) <= $maxDistance) {
                $pos = mb_strpos($original, $token);
                if ($pos === false) {
                    continue;
                }

                return [
                    'start' => $pos,
                    'end' => $pos + mb_strlen($token),
                ];
            }
        }

        return null;
    }
}
