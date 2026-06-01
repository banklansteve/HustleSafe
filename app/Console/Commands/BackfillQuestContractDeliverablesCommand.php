<?php

namespace App\Console\Commands;

use App\Models\QuestContract;
use App\Models\QuestContractDeliverable;
use App\Models\QuestOffer;
use Illuminate\Console\Command;

class BackfillQuestContractDeliverablesCommand extends Command
{
    protected $signature = 'contracts:backfill-deliverables';

    protected $description = 'Backfill quest_contract_deliverables for contracts created before child tables existed';

    public function handle(): int
    {
        $count = 0;

        QuestContract::query()
            ->whereDoesntHave('deliverables')
            ->with('offer')
            ->chunkById(50, function ($contracts) use (&$count): void {
                foreach ($contracts as $contract) {
                    $items = $this->deliverablesFor($contract);
                    foreach ($items as $index => $item) {
                        QuestContractDeliverable::query()->create([
                            'quest_contract_id' => $contract->id,
                            'position' => $index + 1,
                            'title' => $item['title'],
                            'description' => $item['description'],
                        ]);
                    }
                    $count++;
                }
            });

        $this->info("Backfilled deliverables for {$count} contract(s).");

        return self::SUCCESS;
    }

    /**
     * @return list<array{title: string, description: string|null}>
     */
    private function deliverablesFor(QuestContract $contract): array
    {
        $offer = $contract->offer;
        $terms = $offer?->award_terms_snapshot ?? [];

        if (! empty($terms['deliverables']) && is_array($terms['deliverables'])) {
            $items = collect($terms['deliverables'])
                ->map(fn ($row) => [
                    'title' => is_array($row) ? ($row['title'] ?? 'Deliverable') : (string) $row,
                    'description' => is_array($row) ? ($row['description'] ?? null) : null,
                ])
                ->filter(fn ($row) => trim($row['title']) !== '')
                ->values()
                ->all();

            if ($items !== []) {
                return $items;
            }
        }

        $scope = $contract->quest_snapshot['scope_description']
            ?? $terms['scope_summary']
            ?? null;

        if (is_string($scope) && trim($scope) !== '') {
            return [
                [
                    'title' => __('Deliver work as described in the accepted proposal and quest brief'),
                    'description' => str(strip_tags($scope))->limit(500)->toString(),
                ],
            ];
        }

        return [
            [
                'title' => __('Deliver work as described in the accepted proposal and quest brief'),
                'description' => null,
            ],
        ];
    }
}
