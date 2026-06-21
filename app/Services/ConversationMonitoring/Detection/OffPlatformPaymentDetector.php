<?php

namespace App\Services\ConversationMonitoring\Detection;

use App\Enums\ConversationFlagCategory;
use App\Services\ConversationMonitoring\Support\ConversationMessageTokenizer;

class OffPlatformPaymentDetector
{
    public function __construct(private readonly ConversationMessageTokenizer $tokenizer) {}

    /**
     * @param  array<string, mixed>  $analysis
     * @return array<string, mixed>|null
     */
    public function detect(string $raw, array $analysis): ?array
    {
        $normalized = (string) $analysis['normalized'];
        $weights = config('conversation_monitoring.payment.weights', []);
        $reasoning = [];
        $score = 0;

        $actionMatches = $this->tokenizer->matchPhrases($normalized, config('conversation_monitoring.payment.action_verbs', []));
        if ($actionMatches !== []) {
            $score += (int) ($weights['action_verb'] ?? 30);
            $reasoning[] = $this->reason('action_verb', $actionMatches[0]['match'], (int) ($weights['action_verb'] ?? 30));
            if (count($actionMatches) > 1) {
                $score += (int) ($weights['second_action_verb'] ?? 10);
                $reasoning[] = $this->reason('second_action_verb', $actionMatches[1]['match'], (int) ($weights['second_action_verb'] ?? 10));
            }
        }

        $providerMatches = $this->tokenizer->matchPaymentProviders($raw, $normalized);
        $resolvedProviders = $this->tokenizer->resolveCanonicalPaymentProviders($providerMatches);

        foreach ($resolvedProviders as $provider) {
            $points = (int) ($provider['obfuscated']
                ? ($weights['obfuscated_payment_provider'] ?? 40)
                : ($weights['payment_provider'] ?? 25));

            $score += $points;
            $reasoning[] = $this->reason(
                $provider['obfuscated'] ? 'obfuscated_payment_provider' : 'payment_provider',
                $provider['token'],
                $points,
            );
        }

        if (count($resolvedProviders) >= 2) {
            $score += (int) ($weights['multi_payment_escalation'] ?? 45);
            $reasoning[] = $this->reason(
                'multi_payment_escalation',
                collect($resolvedProviders)->pluck('token')->implode(' + '),
                (int) ($weights['multi_payment_escalation'] ?? 45),
            );
        }

        $methodMatches = $this->tokenizer->matchPhrases($normalized, config('conversation_monitoring.payment.method_references', []));
        if ($methodMatches !== []) {
            $score += (int) ($weights['payment_method'] ?? 20);
            $reasoning[] = $this->reason('payment_method', $methodMatches[0]['match'], (int) ($weights['payment_method'] ?? 20));

            foreach (['aza', 'azza', 'acct', 'acc', 'a/c', 'account', 'nuban', 'bank'] as $accountRef) {
                if ($this->tokenizer->containsToken($normalized, $accountRef)) {
                    $score += (int) ($weights['account_reference'] ?? 15);
                    $reasoning[] = $this->reason('account_reference', $accountRef, (int) ($weights['account_reference'] ?? 15));
                    break;
                }
            }
        }

        $payViaMatches = $this->tokenizer->matchPhrases($normalized, config('conversation_monitoring.payment.pay_via_phrases', []));
        if ($payViaMatches !== [] && $providerMatches !== []) {
            $score += (int) ($weights['pay_via_provider'] ?? 20);
            $reasoning[] = $this->reason('pay_via_provider', $payViaMatches[0]['match'], (int) ($weights['pay_via_provider'] ?? 20));
        }

        foreach (['aza', 'azza', 'trow'] as $slang) {
            if ($this->tokenizer->containsToken($normalized, $slang)) {
                $score += (int) ($weights['nigerian_slang'] ?? 15);
                $reasoning[] = $this->reason('nigerian_slang', $slang, (int) ($weights['nigerian_slang'] ?? 15));
                break;
            }
        }

        $numberPattern = $this->tokenizer->obfuscatedNumberPattern($raw, $normalized);
        if ($numberPattern) {
            $patternPoints = (int) ($numberPattern['score'] >= 25
                ? ($weights['complex_number_pattern'] ?? 25)
                : ($weights['number_pattern'] ?? 20));
            $score += $patternPoints;
            $reasoning[] = $this->reason('number_pattern', $numberPattern['label'], $patternPoints);
        }

        if ($providerMatches !== [] && $numberPattern !== null) {
            $score += (int) ($weights['provider_phone_combo'] ?? 30);
            $reasoning[] = $this->reason(
                'provider_phone_combo',
                $providerMatches[0]['match'].' + digit pattern',
                (int) ($weights['provider_phone_combo'] ?? 30),
            );
        }

        if (preg_match('/\b\d{10}\b/', $raw)) {
            $score += (int) ($weights['nuban'] ?? 25);
            $reasoning[] = $this->reason('nuban', '10-digit account sequence', (int) ($weights['nuban'] ?? 25));
        }

        if (preg_match('/(paystack\.com|flutterwave\.com|pay\.stack|flw\.link)/i', $raw)) {
            $score += (int) ($weights['payment_url'] ?? 30);
            $reasoning[] = $this->reason('payment_url', 'external payment provider link', (int) ($weights['payment_url'] ?? 30));
        }

        $bypassMatches = $this->tokenizer->matchPhrases($normalized, config('conversation_monitoring.payment.bypass_phrases', []));
        if ($bypassMatches !== []) {
            $score += (int) ($weights['explicit_bypass'] ?? 20);
            $reasoning[] = $this->reason('explicit_bypass', $bypassMatches[0]['match'], (int) ($weights['explicit_bypass'] ?? 20));
        }

        if ($numberPattern && $this->tokenizer->isLocationContext($normalized, $numberPattern) && $actionMatches === []) {
            $score += (int) ($weights['location_dampener'] ?? -15);
            $reasoning[] = $this->reason('location_context', 'location reference dampener', (int) ($weights['location_dampener'] ?? -15));
        }

        if ($numberPattern && $actionMatches === [] && $methodMatches === [] && $providerMatches === []) {
            $score = min($score, (int) ($weights['number_pattern'] ?? 20));
        }

        $score = $this->clampScore($score);
        if ($score < 1 || $reasoning === []) {
            return null;
        }

        $patternScore = min(50, (int) collect($reasoning)->where('kind', 'pattern')->sum('points'));
        $contextScore = max(0, $score - $patternScore);

        return $this->result(
            ConversationFlagCategory::OffPlatformPayment,
            $score,
            $patternScore,
            $contextScore,
            $reasoning,
            $this->patternLabel($reasoning),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $reasoning
     */
    private function patternLabel(array $reasoning): string
    {
        $primary = collect($reasoning)->sortByDesc('points')->first();
        $label = (string) ($primary['match'] ?? 'payment_context');

        return '[payment:'.mb_substr($label, 0, 40).']';
    }

    /**
     * @param  list<array<string, mixed>>  $reasoning
     * @return array<string, mixed>
     */
    private function result(
        ConversationFlagCategory $category,
        int $score,
        int $patternScore,
        int $contextScore,
        array $reasoning,
        string $patternRedacted,
    ): array {
        return [
            'category' => $category,
            'confidence_score' => $score,
            'pattern_score' => $patternScore,
            'context_score' => $contextScore,
            'pattern_redacted' => $patternRedacted,
            'reasoning' => $reasoning,
        ];
    }

    /**
     * @return array{signal: string, match: string, points: int, kind: string}
     */
    private function reason(string $signal, string $match, int $points): array
    {
        $kind = in_array($signal, ['number_pattern', 'nuban', 'payment_url', 'provider_phone_combo'], true) ? 'pattern' : 'context';

        return [
            'signal' => $signal,
            'match' => $match,
            'points' => $points,
            'kind' => $kind,
        ];
    }

    private function clampScore(int $score): int
    {
        return max(0, min(100, $score));
    }
}
