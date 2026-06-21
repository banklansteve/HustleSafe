<?php

namespace Tests\Unit;

use App\Services\ConversationMonitoring\ConversationTriggerHighlightService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ConversationTriggerHighlightServiceTest extends TestCase
{
    private ConversationTriggerHighlightService $highlighter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->highlighter = app(ConversationTriggerHighlightService::class);
    }

    #[DataProvider('highlightProvider')]
    public function test_locates_trigger_spans_in_original_message(string $message, string $pattern, string $expectedSnippet): void
    {
        $span = $this->highlighter->locateSpan($message, $pattern);

        $this->assertNotNull($span, "Expected highlight for pattern {$pattern}");
        $snippet = mb_substr($message, $span['start'], $span['end'] - $span['start']);
        $this->assertStringContainsStringIgnoringCase($expectedSnippet, $snippet);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function highlightProvider(): array
    {
        return [
            'nuban account' => [
                'Send payment to account number 0123456789 today.',
                '[NUBAN:10-digit account]',
                '0123456789',
            ],
            'abusive phrase' => [
                'You are a complete idiot and useless.',
                '[term:idiot]',
                'idiot',
            ],
            'telegram bypass' => [
                'Reach me on telegram for faster replies.',
                '[social:platform-bypass]',
                'telegram',
            ],
            'phone number' => [
                'Call me on 08031234567 after work.',
                '[phone:nigerian-mobile]',
                '08031234567',
            ],
        ];
    }
}
