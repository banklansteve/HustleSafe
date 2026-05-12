<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QuestCreatePlaceholderController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $user->loadMissing('role');
        $slug = $user->role?->slug ?? 'client';

        if ($slug === 'freelancer' || in_array($slug, ['admin', 'super_admin'], true)) {
            abort(404);
        }

        return Inertia::render('Quests/CreatePlaceholder', [
            'copy' => [
                'title' => __('Create a quest'),
                'lead' => __('Full quest publishing is almost here. For now, prepare your brief and keep your account verified so we can prioritise your launch.'),
                'bullets' => [
                    __('Define the outcome, not just tasks — freelancers quote faster.'),
                    __('Set a realistic budget range and deadline.'),
                    __('Mention tools, files, and approval steps upfront.'),
                ],
            ],
        ]);
    }
}
