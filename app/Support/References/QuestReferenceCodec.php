<?php

namespace App\Support\References;

use App\Models\Quest;
use Carbon\CarbonInterface;

final class QuestReferenceCodec
{
    /** @var string Q2606-VJX8HT (current) or Q-2606-VJX8HT (legacy) — dash after Q is optional for backward compatibility */
    public const QUEST_PATTERN = '/^Q-?(\d{4})-([23456789ABCDEFGHJKMNPQRSTUVWXYZ]{6})$/';

    /** @var string PR-26-VJX8-L9YT or CTR-26-VJX8-AB1C */
    public const CHILD_PATTERN = '/^(PR|CTR)-(\d{2})-([23456789ABCDEFGHJKMNPQRSTUVWXYZ]{4})-([23456789ABCDEFGHJKMNPQRSTUVWXYZ]{4})$/';

    public static function yearMonth2(?CarbonInterface $at = null): string
    {
        return ($at ?? now('Africa/Lagos'))->format('ym');
    }

    public static function year2(?CarbonInterface $at = null): string
    {
        return ($at ?? now('Africa/Lagos'))->format('y');
    }

    /**
     * First four characters of the quest hash segment (links proposals/contracts to a quest).
     */
    public static function questHash4(Quest $quest): string
    {
        $reference = HustleSafeReferenceAlphabet::normalize((string) ($quest->reference_code ?? ''));

        if (preg_match(self::QUEST_PATTERN, $reference, $matches)) {
            return substr($matches[2], 0, 4);
        }

        $seed = (string) ($quest->uuid ?? '');
        if ($seed === '') {
            $seed = 'quest:'.$quest->getKey();
        }

        return HustleSafeReferenceAlphabet::hash4FromString($seed);
    }

    public static function isQuestReference(string $value): bool
    {
        return (bool) preg_match(self::QUEST_PATTERN, HustleSafeReferenceAlphabet::normalize($value));
    }

    public static function isChildReference(string $value): bool
    {
        return (bool) preg_match(self::CHILD_PATTERN, HustleSafeReferenceAlphabet::normalize($value));
    }

    public static function isLegacyProposalReference(string $value): bool
    {
        return (bool) preg_match('/^PROP-\d{4}-\d{5}$/i', $value);
    }

    public static function isLegacyContractReference(string $value): bool
    {
        return (bool) preg_match('/^CTR-\d{4}-\d{5}$/i', $value);
    }
}
