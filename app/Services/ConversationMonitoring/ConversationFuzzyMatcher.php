<?php

namespace App\Services\ConversationMonitoring;

class ConversationFuzzyMatcher
{
    public function containsPhrase(string $haystack, string $phrase, int $maxDistance = 2): bool
    {
        $haystack = $this->normalize($haystack);
        $phrase = $this->normalize($phrase);

        if ($phrase === '' || str_contains($haystack, $phrase)) {
            return $phrase !== '';
        }

        $words = preg_split('/\s+/', $phrase, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($words) > 1) {
            return $this->containsPhraseWords($haystack, $words, $maxDistance);
        }

        return $this->tokenFuzzyMatch($haystack, $phrase, $maxDistance);
    }

    /**
     * @param  list<string>  $words
     */
    private function containsPhraseWords(string $haystack, array $words, int $maxDistance): bool
    {
        $pattern = implode('[\\s\\-_.]{0,6}', array_map(fn ($w) => preg_quote($w, '/'), $words));

        return (bool) preg_match('/'.$pattern.'/iu', $haystack);
    }

    private function tokenFuzzyMatch(string $haystack, string $token, int $maxDistance): bool
    {
        $minLen = (int) config('conversation_monitoring.fuzzy.min_token_length', 4);
        if (strlen($token) < $minLen) {
            return false;
        }

        foreach (preg_split('/\s+/', $haystack, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            if (levenshtein($word, $token) <= $maxDistance) {
                return true;
            }
        }

        return false;
    }

    public function fuzzyTermMatch(string $haystack, string $term, int $maxDistance = 2): bool
    {
        $haystack = $this->normalize($haystack);
        $term = $this->normalize($term);

        if ($term === '') {
            return false;
        }

        if (str_contains($haystack, $term)) {
            return true;
        }

        if (strlen($term) < (int) config('conversation_monitoring.fuzzy.min_token_length', 4)) {
            return false;
        }

        foreach (preg_split('/\s+/', $haystack, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            if (levenshtein($word, $term) <= $maxDistance) {
                return true;
            }
        }

        return false;
    }

    public function normalize(string $text): string
    {
        $text = mb_strtolower($text);
        $text = str_replace(['0', '1', '3', '4', '5', '@'], ['o', 'i', 'e', 'a', 's', 'a'], $text);

        return preg_replace('/[^a-z0-9\s@._\-+]/u', ' ', $text) ?? $text;
    }
}
