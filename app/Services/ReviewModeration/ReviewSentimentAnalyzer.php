<?php

namespace App\Services\ReviewModeration;

class ReviewSentimentAnalyzer
{
    /** @var list<string> */
    private array $positive = [
        'excellent', 'amazing', 'outstanding', 'fantastic', 'wonderful', 'great', 'love', 'perfect',
        'professional', 'recommend', 'happy', 'pleased', 'satisfied', 'brilliant', 'awesome', 'superb',
    ];

    /** @var list<string> */
    private array $negative = [
        'terrible', 'awful', 'horrible', 'worst', 'bad', 'poor', 'disappointed', 'unprofessional',
        'rude', 'late', 'scam', 'fraud', 'never', 'avoid', 'waste', 'unacceptable', 'failed', 'lazy',
    ];

    /**
     * @return array{score: float, polarity: string}
     */
    public function analyze(?string $text): array
    {
        $text = strtolower(trim((string) $text));
        if ($text === '') {
            return ['score' => 0.5, 'polarity' => 'neutral'];
        }

        $tokens = preg_split('/\W+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($tokens === []) {
            return ['score' => 0.5, 'polarity' => 'neutral'];
        }

        $pos = 0;
        $neg = 0;
        foreach ($tokens as $token) {
            if (in_array($token, $this->positive, true)) {
                $pos++;
            }
            if (in_array($token, $this->negative, true)) {
                $neg++;
            }
        }

        $total = max(1, $pos + $neg);
        $score = 0.5 + (($pos - $neg) / $total) * 0.5;
        $score = max(0.0, min(1.0, round($score, 3)));

        $polarity = match (true) {
            $score > 0.6 => 'positive',
            $score < 0.3 => 'negative',
            default => 'neutral',
        };

        return ['score' => $score, 'polarity' => $polarity];
    }
}
