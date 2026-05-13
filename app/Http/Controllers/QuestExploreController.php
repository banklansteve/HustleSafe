<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\FreelancerWorkspaceReadinessService;
use App\Services\QuestMatchingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QuestExploreController extends Controller
{
    public function __invoke(Request $request, FreelancerWorkspaceReadinessService $workspace): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing(['role', 'questCategoryPreferences']);

        if ($user->role?->slug !== 'freelancer') {
            return redirect()->route('dashboard');
        }

        $rows = app(QuestMatchingService::class)->rankedOpenQuestsForFreelancer($user, 48);

        $quests = $rows->map(function (array $row) use ($user, $workspace) {
            $q = $row['quest'];

            return [
                'id' => $q->id,
                'uuid' => $q->uuid,
                'title' => $q->title,
                'match_score' => $row['match_score'],
                'reasons' => array_slice($row['reasons'], 0, 3),
                'budget_minor' => (int) ($q->budget_amount_minor ?? 0),
                'category' => $q->questCategory?->name,
                'state' => $q->stateModel?->name,
                'city' => $q->city,
                'posted_at' => $q->created_at?->timezone('Africa/Lagos')->toIso8601String(),
                'category_match' => $workspace->matchesQuestCategory($user, $q),
            ];
        })->values()->all();

        return Inertia::render('Quests/Explore', [
            'quests' => $quests,
            'workspace' => $workspace->toInertiaProps($user),
        ]);
    }
}
