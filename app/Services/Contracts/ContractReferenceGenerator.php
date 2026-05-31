<?php

namespace App\Services\Contracts;

use App\Models\QuestContract;
use Illuminate\Support\Facades\DB;

class ContractReferenceGenerator
{
    public function next(): string
    {
        $year = now('Africa/Lagos')->format('Y');

        return DB::transaction(function () use ($year): string {
            $latest = QuestContract::query()
                ->where('reference_code', 'like', "CTR-{$year}-%")
                ->orderByDesc('reference_code')
                ->lockForUpdate()
                ->value('reference_code');

            $sequence = 1;
            if (is_string($latest) && preg_match('/CTR-\d{4}-(\d+)/', $latest, $matches)) {
                $sequence = ((int) $matches[1]) + 1;
            }

            return sprintf('CTR-%s-%05d', $year, $sequence);
        });
    }
}
