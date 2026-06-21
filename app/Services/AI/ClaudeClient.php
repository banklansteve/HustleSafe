<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Thin, reusable wrapper around the Anthropic (Claude) Messages API.
 *
 * Keep this generic — feature-specific prompting lives in dedicated services
 * (e.g. QuestDescriptionAiService) so the same client can power future AI helpers.
 */
class ClaudeClient
{
    public function isConfigured(): bool
    {
        return ! empty(config('services.anthropic.api_key'));
    }

    /**
     * Single-turn convenience: one system prompt + one user message → assistant text.
     *
     * @param  array<string, mixed>  $options  model, max_tokens, temperature, timeout
     */
    public function prompt(string $systemPrompt, string $userPrompt, array $options = []): string
    {
        return $this->message($systemPrompt, [
            ['role' => 'user', 'content' => $userPrompt],
        ], $options);
    }

    /**
     * Send messages to Claude and return the concatenated text content.
     *
     * @param  list<array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options
     */
    public function message(string $systemPrompt, array $messages, array $options = []): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Anthropic API key is not configured.');
        }

        $response = Http::withHeaders([
            'x-api-key' => (string) config('services.anthropic.api_key'),
            'anthropic-version' => (string) config('services.anthropic.version', '2023-06-01'),
            'content-type' => 'application/json',
        ])
            ->timeout((int) ($options['timeout'] ?? 30))
            ->baseUrl(rtrim((string) config('services.anthropic.base_url', 'https://api.anthropic.com'), '/'))
            ->post('/v1/messages', array_filter([
                'model' => $options['model'] ?? config('services.anthropic.model'),
                'max_tokens' => (int) ($options['max_tokens'] ?? config('services.anthropic.max_tokens', 1500)),
                'temperature' => $options['temperature'] ?? 0.7,
                'system' => $systemPrompt !== '' ? $systemPrompt : null,
                'messages' => $messages,
            ], fn ($value) => $value !== null));

        if ($response->failed()) {
            Log::warning('Anthropic request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException('AI request failed with status '.$response->status().'.');
        }

        return collect($response->json('content', []))
            ->where('type', 'text')
            ->pluck('text')
            ->filter()
            ->implode("\n");
    }
}
