<?php

namespace App\Services\Contracts;

use App\Models\QuestContract;
use App\Models\QuestContractEvent;
use App\Models\User;
use Illuminate\Http\Request;

class ContractEventLogger
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function log(QuestContract $contract, string $eventType, ?User $user = null, array $properties = [], ?Request $request = null): QuestContractEvent
    {
        $req = $request ?? request();

        return QuestContractEvent::query()->create([
            'quest_contract_id' => $contract->id,
            'user_id' => $user?->id,
            'event_type' => $eventType,
            'properties' => $properties ?: null,
            'ip_address' => $req?->ip(),
            'user_agent' => $req ? substr((string) $req->userAgent(), 0, 2000) : null,
            'created_at' => now(),
        ]);
    }
}
