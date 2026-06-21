<?php

namespace App\Services\ConversationMonitoring;

use App\Enums\ConversationFlagCategory;

class ConversationMonitoringScanner
{
    public function __construct(private readonly ConversationMonitoringEngine $engine) {}

    /**
     * @return list<array{
     *     category: ConversationFlagCategory,
     *     pattern_redacted: string,
     *     confidence: float,
     *     confidence_score?: int,
     *     pattern_score?: int,
     *     context_score?: int,
     *     reasoning?: list<array<string, mixed>>,
     * }>
     */
    public function scan(string $body): array
    {
        return $this->engine->analyze($body);
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
