<?php

namespace App\Http\Controllers;

use App\Enums\AdminProposalStatus;
use App\Models\Quest;
use App\Models\QuestOffer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class QuestClientProposalsController extends Controller
{
    public function index(Request $request, Quest $quest): Response
    {
        $this->authorize('view', $quest);

        if ((int) $quest->client_id !== (int) $request->user()?->id) {
            abort(403);
        }

        $proposals = QuestOffer::query()
            ->where('quest_id', $quest->id)
            ->when(Schema::hasColumn('quest_offers', 'admin_status'), function ($query): void {
                $query->whereNull('admin_status')
                    ->orWhere('admin_status', '!=', AdminProposalStatus::Suspended->value);
            })
            ->with(['freelancer:id,first_name,name,slug,avatar_url,headline'])
            ->latest('created_at')
            ->limit(400)
            ->get()
            ->map(fn (QuestOffer $o) => self::proposalRow($quest, $o))
            ->values()
            ->all();

        return Inertia::render('Quests/ClientQuestProposals', [
            'quest' => [
                'title' => $quest->title,
                'route_key' => $quest->getRouteKey(),
                'status' => $quest->status->value,
                'reference_code' => $quest->reference_code,
            ],
            'proposals' => $proposals,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function proposalRow(Quest $quest, QuestOffer $o): array
    {
        return [
            'id' => $o->id,
            'status' => $o->status,
            'created_at' => $o->created_at?->timezone('Africa/Lagos')->toIso8601String(),
            'quoted_amount_minor' => (int) ($o->quoted_amount_minor ?? 0),
            'client_pinned_at' => $o->client_pinned_at !== null,
            'shortlisted_at' => $o->shortlisted_at !== null,
            'freelancer' => $o->freelancer ? [
                'name' => $o->freelancer->name,
                'first_name' => $o->freelancer->first_name,
                'slug' => $o->freelancer->slug,
                'avatar_url' => $o->freelancer->avatar_url,
                'headline' => $o->freelancer->headline,
            ] : null,
            'show_url' => route('quests.proposals.show', [$quest->getRouteKey(), $o->id]),
        ];
    }
}
