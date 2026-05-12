<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\QuestMatchingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QuestExploreController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing(['role', 'questCategoryPreferences']);

        if ($user->role?->slug !== 'freelancer') {
            return redirect()->route('dashboard');
        }

        $rows = app(QuestMatchingService::class)->rankedOpenQuestsForFreelancer($user, 48);

        $quests = $rows->map(fn (array $row) => [
            'id' => $row['quest']->id,
            'uuid' => $row['quest']->uuid,
            'title' => $row['quest']->title,
            'match_score' => $row['match_score'],
            'reasons' => array_slice($row['reasons'], 0, 3),
            'budget_minor' => (int) ($row['quest']->budget_amount_minor ?? 0),
            'category' => $row['quest']->questCategory?->name,
            'state' => $row['quest']->stateModel?->name,
            'city' => $row['quest']->city,
            'posted_at' => $row['quest']->created_at?->timezone('Africa/Lagos')->toIso8601String(),
        ])->values()->all();

        return Inertia::render('Quests/Explore', [
            'quests' => $quests,
        ]);
    }
}
