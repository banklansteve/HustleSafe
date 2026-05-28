<?php

namespace App\Services\ReviewModeration;

class ReviewQualityScorer
{
    /**
     * @return array{score: int, is_brief: bool}
     */
    public function score(?string $title, ?string $comment): array
    {
        $body = trim(((string) $title).' '.((string) $comment));
        $words = preg_split('/\s+/u', $body, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $wordCount = count($words);

        $minWords = (int) config('review_moderation.quality.min_words_full_weight', 30);
        $threshold = (int) config('review_moderation.quality.brief_threshold', 40);

        $wordScore = min(40, (int) round(($wordCount / max(1, $minWords)) * 40));

        $hints = config('review_moderation.quality.work_language_hints', []);
        $lower = strtolower($body);
        $workHits = 0;
        foreach ($hints as $hint) {
            if (str_contains($lower, strtolower((string) $hint))) {
                $workHits++;
            }
        }
        $workScore = min(35, $workHits * 12);

        $fillers = config('review_moderation.quality.filler_phrases', []);
        $fillerPenalty = 0;
        foreach ($fillers as $phrase) {
            if (str_contains($lower, strtolower((string) $phrase))) {
                $fillerPenalty += 8;
            }
        }
        $fillerScore = max(0, 25 - $fillerPenalty);

        $total = max(0, min(100, $wordScore + $workScore + $fillerScore));

        return [
            'score' => $total,
            'is_brief' => $total < $threshold,
        ];
    }
}
