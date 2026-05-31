<?php

namespace Tests\Unit;

use App\Enums\ConversationFlagCategory;
use App\Services\ConversationMonitoring\ConversationMonitoringScanner;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ConversationMonitoringScannerTest extends TestCase
{
    private ConversationMonitoringScanner $scanner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scanner = app(ConversationMonitoringScanner::class);
    }

    #[DataProvider('bypassMessageProvider')]
    public function test_detects_contact_bypass_attempts(string $message, string $expectedCategory): void
    {
        $hits = $this->scanner->scan($message);

        $this->assertNotEmpty($hits, "Expected flag for: {$message}");
        $this->assertContains(
            $expectedCategory,
            collect($hits)->map(fn ($hit) => $hit['category']->value)->all(),
        );
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function bypassMessageProvider(): array
    {
        return [
            'telegram shorthand' => ['Reach me on teleg please', ConversationFlagCategory::ExternalContact->value],
            'tgm token' => ['My tgm is in the bio', ConversationFlagCategory::ExternalContact->value],
            'slide into dms' => ['Just slide into my dms', ConversationFlagCategory::ExternalContact->value],
            'dm me' => ['dm me for details', ConversationFlagCategory::ExternalContact->value],
            'on fb' => ['Find me on fb', ConversationFlagCategory::ExternalContact->value],
            'on x' => ['Message me on x', ConversationFlagCategory::ExternalContact->value],
            'tweeter' => ['I am on tweeter', ConversationFlagCategory::ExternalContact->value],
            'snapchat' => ['Add me on snapchat', ConversationFlagCategory::ExternalContact->value],
            'email me' => ['email me your quote', ConversationFlagCategory::ExternalContact->value],
            'send email' => ['send email to coordinate', ConversationFlagCategory::ExternalContact->value],
            'gmail handle' => ['Use @gmail for contact', ConversationFlagCategory::ExternalContact->value],
            'yahoo handle' => ['Reach @yahoo', ConversationFlagCategory::ExternalContact->value],
            'call me with digits' => ['call me on zero eight zero three', ConversationFlagCategory::ExternalContact->value],
            'spoken zero seven' => ['zero seven zero one two three four five six seven', ConversationFlagCategory::ExternalContact->value],
            'spaced phone digits' => ['0 8 0 3 1 2 3 4 5 6 7', ConversationFlagCategory::ExternalContact->value],
            'handle disclosure' => ['my handle: @someuser', ConversationFlagCategory::ExternalContact->value],
        ];
    }
}
