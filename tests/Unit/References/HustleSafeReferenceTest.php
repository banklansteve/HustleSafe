<?php

namespace Tests\Unit\References;

use App\Models\Quest;
use App\Support\References\HustleSafeReferenceAlphabet;
use App\Support\References\QuestReferenceCodec;
use PHPUnit\Framework\TestCase;

class HustleSafeReferenceTest extends TestCase
{
    public function test_quest_reference_codec_recognizes_new_format(): void
    {
        $this->assertTrue(QuestReferenceCodec::isQuestReference('Q-2606-VJX8HT'));
        $this->assertSame('VJX8', QuestReferenceCodec::questHash4(new Quest([
            'reference_code' => 'Q-2606-VJX8HT',
            'uuid' => '550e8400-e29b-41d4-a716-446655440000',
        ])));
    }

    public function test_legacy_quest_uses_stable_hash4_from_uuid(): void
    {
        $quest = new Quest([
            'reference_code' => 'TEC-OLDREF1',
            'uuid' => '550e8400-e29b-41d4-a716-446655440000',
        ]);

        $hash4A = QuestReferenceCodec::questHash4($quest);
        $hash4B = QuestReferenceCodec::questHash4(new Quest([
            'reference_code' => 'TEC-OLDREF1',
            'uuid' => '550e8400-e29b-41d4-a716-446655440000',
        ]));

        $this->assertSame(4, strlen($hash4A));
        $this->assertSame($hash4A, $hash4B);
        $this->assertMatchesRegularExpression('/^[23456789ABCDEFGHJKMNPQRSTUVWXYZ]{4}$/', $hash4A);
    }

    public function test_alphabet_random_never_uses_ambiguous_characters(): void
    {
        $sample = HustleSafeReferenceAlphabet::random(400);

        $this->assertSame(400, strlen($sample));
        $this->assertFalse(strpbrk($sample, '01OIL') !== false);
    }

    public function test_child_reference_patterns(): void
    {
        $this->assertTrue(QuestReferenceCodec::isChildReference('PR-26-VJX8-K9YT'));
        $this->assertTrue(QuestReferenceCodec::isChildReference('CTR-26-VJX8-ABCD'));
        $this->assertTrue(QuestReferenceCodec::isLegacyProposalReference('PROP-2026-00042'));
        $this->assertTrue(QuestReferenceCodec::isLegacyContractReference('CTR-2026-00042'));
    }
}
