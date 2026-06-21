<?php

namespace App\Services\ConversationMonitoring\Detection;

use App\Enums\ConversationFlagCategory;
use App\Services\ConversationMonitoring\Support\ConversationMessageTokenizer;

class OffPlatformContactDetector
{
    public function __construct(private readonly ConversationMessageTokenizer $tokenizer) {}

    /**
     * @param  array<string, mixed>  $analysis
     * @return array<string, mixed>|null
     */
    public function detect(string $raw, array $analysis): ?array
    {
        $normalized = (string) $analysis['normalized'];
        $weights = config('conversation_monitoring.contact.weights', []);
        $reasoning = [];
        $score = 0;

        $platformMatches = $this->tokenizer->matchPlatforms($raw, $normalized);
        $resolvedPlatforms = $this->tokenizer->resolveCanonicalPlatforms($platformMatches);
        $shortPlatformTokens = config('conversation_monitoring.contact.short_platform_tokens', []);

        foreach ($resolvedPlatforms as $platform) {
            $points = (int) ($platform['obfuscated']
                ? ($weights['obfuscated_platform_token'] ?? 40)
                : (in_array($platform['token'], $shortPlatformTokens, true)
                    ? 15
                    : ($weights['canonical_platform'] ?? 30)));

            $score += $points;
            $reasoning[] = $this->reason(
                $platform['obfuscated'] ? 'obfuscated_platform_token' : 'external_platform',
                $platform['token'],
                $points,
            );
        }

        if (count($resolvedPlatforms) >= 2) {
            $score += (int) ($weights['multi_platform_escalation'] ?? 45);
            $reasoning[] = $this->reason(
                'multi_platform_escalation',
                collect($resolvedPlatforms)->pluck('token')->implode(' + '),
                (int) ($weights['multi_platform_escalation'] ?? 45),
            );
        }

        $bypassMatches = $this->tokenizer->matchPhrases($normalized, config('conversation_monitoring.contact.bypass_phrases', []));
        if ($bypassMatches !== []) {
            $score += (int) ($weights['explicit_bypass'] ?? 35);
            $reasoning[] = $this->reason('explicit_bypass', $bypassMatches[0]['match'], (int) ($weights['explicit_bypass'] ?? 35));
        }

        $reachMatches = $this->tokenizer->matchPhrases($normalized, config('conversation_monitoring.contact.reach_phrases', []));
        if ($reachMatches !== []) {
            $score += (int) ($weights['reach_via'] ?? 30);
            $reasoning[] = $this->reason('reach_via', $reachMatches[0]['match'], (int) ($weights['reach_via'] ?? 30));
        }

        $cueMatches = $this->tokenizer->matchContactCueTokens($normalized);
        foreach ($cueMatches as $cue) {
            $score += (int) ($weights['contact_cue'] ?? 15);
            $reasoning[] = $this->reason('contact_cue', $cue, (int) ($weights['contact_cue'] ?? 15));
        }

        if (count($cueMatches) >= 2) {
            $score += (int) ($weights['multiple_contact_cues'] ?? 15);
            $reasoning[] = $this->reason(
                'multiple_contact_cues',
                implode(' + ', $cueMatches),
                (int) ($weights['multiple_contact_cues'] ?? 15),
            );
        }

        $actionMatches = $this->tokenizer->matchPhrases($normalized, config('conversation_monitoring.contact.action_verbs', []));
        if ($actionMatches !== []) {
            $score += (int) ($weights['contact_action'] ?? 25);
            $reasoning[] = $this->reason('contact_action', $actionMatches[0]['match'], (int) ($weights['contact_action'] ?? 25));
        }

        if (preg_match('/\b(?:slide|shoot)\s+dm\b/u', $normalized)) {
            $score += (int) ($weights['adjacent_slide_dm'] ?? 20);
            $reasoning[] = $this->reason('adjacent_slide_dm', 'slide dm', (int) ($weights['adjacent_slide_dm'] ?? 20));
        }

        if (preg_match('/\b(?:slide|shoot)\s+me(?:\s+a)?\s+msg\b/u', $normalized)) {
            $score += (int) ($weights['slide_me_msg'] ?? 25);
            $reasoning[] = $this->reason('slide_me_msg', 'slide me a msg', (int) ($weights['slide_me_msg'] ?? 25));
        }

        if (preg_match('/\bhit\s+me\b/u', $normalized)
            && ($bypassMatches !== [] || $cueMatches !== [] || $actionMatches !== [] || $resolvedPlatforms !== [])) {
            $score += (int) ($weights['hit_me_contact'] ?? 20);
            $reasoning[] = $this->reason('hit_me_contact', 'hit me with contact intent', (int) ($weights['hit_me_contact'] ?? 20));
        }

        $numberPattern = $this->tokenizer->obfuscatedNumberPattern($raw, $normalized);

        if ($this->tokenizer->hasNigerianPhone($raw) || $numberPattern !== null) {
            $phonePoints = (int) ($weights['phone_number'] ?? 20);
            if (($numberPattern['label'] ?? '') === 'mixed_word_figure_cluster'
                || ($numberPattern['label'] ?? '') === 'mixed_word_figure_run') {
                $phonePoints = max($phonePoints, (int) ($weights['mixed_word_figure'] ?? 20));
            }

            $score += $phonePoints;
            $reasoning[] = $this->reason(
                'phone_number',
                $numberPattern['label'] ?? 'phone or obfuscated number pattern',
                $phonePoints,
            );
        }

        if ($platformMatches !== [] && $numberPattern !== null) {
            $score += (int) ($weights['platform_phone_combo'] ?? 30);
            $reasoning[] = $this->reason(
                'platform_phone_combo',
                $platformMatches[0]['match'].' + digit pattern',
                (int) ($weights['platform_phone_combo'] ?? 30),
            );
        }

        if ($this->tokenizer->hasEmail($raw) || preg_match('/@\s*(gmail|yahoo|hotmail|outlook|icloud)\b/i', $raw)) {
            $score += (int) ($weights['email'] ?? 15);
            $reasoning[] = $this->reason('email', 'email or provider handle', (int) ($weights['email'] ?? 15));
        }

        if (preg_match('/\bhandle\s*[:@]/i', $raw) || preg_match('/@[a-z0-9_]{2,32}/i', $raw)) {
            $score += (int) ($weights['handle_disclosure'] ?? 15);
            $reasoning[] = $this->reason('handle_disclosure', 'social handle disclosure', (int) ($weights['handle_disclosure'] ?? 15));
        }

        $hasPlatformContext = $resolvedPlatforms !== []
            || preg_match('/\b(?:on|via|reach|find|message)\s+me\b/u', $normalized)
            || $reachMatches !== [];

        if ($actionMatches !== [] && ! $hasPlatformContext && ! $this->tokenizer->hasNigerianPhone($raw) && ! $this->tokenizer->hasEmail($raw)
            && $bypassMatches === [] && $numberPattern === null && $cueMatches === []) {
            $score = min($score, (int) (($weights['contact_action'] ?? 25)));
        }

        if ($cueMatches === ['dm'] && $bypassMatches === [] && $numberPattern === null
            && $actionMatches === [] && $resolvedPlatforms === [] && $reachMatches === []
            && ! preg_match('/\bhit\s+me\b/u', $normalized)
            && ! preg_match('/\b(?:slide|shoot)\b/u', $normalized)) {
            $score = min($score, 40);
        }

        if (count($resolvedPlatforms) === 1
            && $this->tokenizer->hasBenignPlatformContext($normalized)
            && $actionMatches === []
            && $reachMatches === []
            && $numberPattern === null
            && $cueMatches === []) {
            $score += (int) ($weights['benign_platform_dampener'] ?? -35);
            $reasoning[] = $this->reason('benign_platform_context', 'bio or profile reference', (int) ($weights['benign_platform_dampener'] ?? -35));
        }

        $score = max(0, min(100, $score));
        if ($score < 1 || $reasoning === []) {
            return null;
        }

        $patternScore = min(50, collect($reasoning)->where('kind', 'pattern')->sum('points'));
        $contextScore = $score - $patternScore;

        return [
            'category' => ConversationFlagCategory::ExternalContact,
            'confidence_score' => $score,
            'pattern_score' => $patternScore,
            'context_score' => $contextScore,
            'pattern_redacted' => '[contact:'.mb_substr((string) ($reasoning[0]['match'] ?? 'context'), 0, 40).']',
            'reasoning' => $reasoning,
        ];
    }

    /**
     * @return array{signal: string, match: string, points: int, kind: string}
     */
    private function reason(string $signal, string $match, int $points): array
    {
        $kind = in_array($signal, ['phone_number', 'email', 'handle_disclosure'], true) ? 'pattern' : 'context';

        return [
            'signal' => $signal,
            'match' => $match,
            'points' => $points,
            'kind' => $kind,
        ];
    }
}
