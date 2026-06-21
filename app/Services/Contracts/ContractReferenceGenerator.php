<?php

namespace App\Services\Contracts;

use App\Models\Quest;
use App\Models\QuestContract;
use App\Support\References\HustleSafeReferenceAlphabet;
use App\Support\References\QuestReferenceCodec;
use Carbon\CarbonInterface;

class ContractReferenceGenerator
{
    public function nextForQuest(Quest $quest, ?CarbonInterface $at = null): string
    {
        $at ??= now('Africa/Lagos');
        $year2 = QuestReferenceCodec::year2($at);
        $questHash4 = QuestReferenceCodec::questHash4($quest);

        do {
            $suffix = HustleSafeReferenceAlphabet::random(4);
            $code = "CTR-{$year2}-{$questHash4}-{$suffix}";
        } while (QuestContract::query()->where('reference_code', $code)->exists());

        return $code;
    }
}
