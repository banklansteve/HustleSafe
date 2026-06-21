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

    #[DataProvider('shouldFlagProvider')]
    public function test_flags_high_confidence_policy_violations(string $message, string $expectedCategory): void
    {
        $hits = $this->scanner->scan($message);

        $this->assertNotEmpty($hits, "Expected flag for: {$message}");
        $this->assertContains(
            $expectedCategory,
            collect($hits)->map(fn ($hit) => $hit['category']->value)->all(),
        );

        foreach ($hits as $hit) {
            $this->assertGreaterThanOrEqual(85, $hit['confidence_score'] ?? ($hit['confidence'] * 100));
        }
    }

    #[DataProvider('shouldNotFlagProvider')]
    public function test_ignores_low_context_or_benign_messages(string $message): void
    {
        $hits = $this->scanner->scan($message);

        $this->assertSame([], $hits, "Did not expect a flag for: {$message}");
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function shouldFlagProvider(): array
    {
        return [
            'direct off-platform payment' => [
                'Pya me directly send to my acct dont use platform',
                ConversationFlagCategory::OffPlatformPayment->value,
            ],
            'obfuscated payment with intent' => [
                'Send ₦50K to my zero 8 zero number trf it now aza',
                ConversationFlagCategory::OffPlatformPayment->value,
            ],
            'account number with payment intent' => [
                'Please pay me directly outside the platform using my account number 0123456789.',
                ConversationFlagCategory::OffPlatformPayment->value,
            ],
            'contact with platform phone and action' => [
                'Reach me on WhatsApp, call 08031234567 or email me at john@gmail.com',
                ConversationFlagCategory::ExternalContact->value,
            ],
            'off-platform dm with spoken phone' => [
                'Is there a way we can talk off here? Can you hit me slide dm on zero et zero 756 one five 2 5 6',
                ConversationFlagCategory::ExternalContact->value,
            ],
            'fuzzy social with spoken digits' => [
                'can we do 0 eight 0 1 5 for six fb whtsapp',
                ConversationFlagCategory::ExternalContact->value,
            ],
            'fuzzy telegram reach phrase' => [
                'Ok thank you. I will have to see a way of reaching you via telgm or if you can slide me a msg',
                ConversationFlagCategory::ExternalContact->value,
            ],
            'abusive threat escalation' => [
                "YOU'RE INCOMPETENT! USELESS! I'LL SUE YOU!!!",
                ConversationFlagCategory::AbusiveLanguage->value,
            ],
            'opay with spoken account digits' => [
                'Can you pay me on opay 0 six 1 three 5 2 6 5 nine four',
                ConversationFlagCategory::OffPlatformPayment->value,
            ],
            'opay via spoken phone refusal' => [
                'no i wont pay you via opay on 0 six 1 three 5 6 five nine 7 o',
                ConversationFlagCategory::OffPlatformPayment->value,
            ],
            'instagram handle with moniepoint digits' => [
                'ok send me on instag @flexib or you just do moniept six five 2 2 9 one 2 for 7 3',
                ConversationFlagCategory::ExternalContact->value,
            ],
            'smuggled platforms in filler prose' => [
                'At of hardly sister favour. As society explain country raising weather of wtsapp Sentiments nor everything off out teleg uncommonly partiality bed.',
                ConversationFlagCategory::ExternalContact->value,
            ],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function shouldNotFlagProvider(): array
    {
        return [
            'benign logistics message' => ['Park be fine easy am size away.'],
            'isolated spoken digits' => ['zero 8 zero 1 2 3 4 5 6 7 8 9 0'],
            'location reference' => ["I'm from zero 8 zero state, that's where I work"],
            'work criticism only' => ['The design is poor quality and does not match the spec'],
            'self directed frustration' => ["I'm so frustrated I'm stupid for missing the deadline"],
            'telegram token without action context' => ['My tgm is in the bio'],
            'dm without platform context' => ['dm me for details'],
            'casual social mention' => ['Find me on fb'],
            'email phrase without enough context' => ['email me your quote'],
            'spaced phone without contact intent' => ['0 8 0 3 1 2 3 4 5 6 7'],
        ];
    }
}
