<?php

namespace App\Services\Proposals;

use App\Models\Quest;
use App\Models\QuestOffer;
use App\Support\References\HustleSafeReferenceAlphabet;
use App\Support\References\QuestReferenceCodec;
use Carbon\CarbonInterface;

class ProposalReferenceGenerator
{
    public function nextForQuest(Quest $quest, ?CarbonInterface $at = null): string
    {
        $at ??= now('Africa/Lagos');
        $year2 = QuestReferenceCodec::year2($at);
        $questHash4 = QuestReferenceCodec::questHash4($quest);

        do {
            $suffix = HustleSafeReferenceAlphabet::random(4);
            $code = "PR-{$year2}-{$questHash4}-{$suffix}";
        } while (QuestOffer::query()->where('reference_code', $code)->exists());

        return $code;
    }
}
