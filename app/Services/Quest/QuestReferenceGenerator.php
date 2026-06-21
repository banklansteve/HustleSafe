<?php

namespace App\Services\Quest;

use App\Models\Quest;
use App\Support\References\HustleSafeReferenceAlphabet;
use App\Support\References\QuestReferenceCodec;
use Carbon\CarbonInterface;

class QuestReferenceGenerator
{
    public function next(?CarbonInterface $at = null): string
    {
        $at ??= now('Africa/Lagos');
        $ym = QuestReferenceCodec::yearMonth2($at);

        do {
            $hash6 = HustleSafeReferenceAlphabet::random(6);
            $code = "Q{$ym}-{$hash6}";
        } while (Quest::query()->where('reference_code', $code)->exists());

        return $code;
    }
}
