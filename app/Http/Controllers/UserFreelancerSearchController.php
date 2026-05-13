<?php

namespace App\Http\Controllers;

use App\Models\Quest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserFreelancerSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user?->can('create', Quest::class)) {
            abort(403);
        }

        $q = trim((string) $request->query('q', ''));
        if (strlen($q) < 2) {
            return response()->json(['users' => []]);
        }

        $users = User::query()
            ->whereRelation('role', 'slug', 'freelancer')
            ->where('users.id', '<>', $user->id)
            ->where(function ($w) use ($q): void {
                $w->where('first_name', 'like', '%'.$q.'%')
                    ->orWhere('last_name', 'like', '%'.$q.'%')
                    ->orWhere('name', 'like', '%'.$q.'%')
                    ->orWhere('username', 'like', '%'.$q.'%');
            })
            ->orderBy('first_name')
            ->limit(12)
            ->get(['id', 'first_name', 'name', 'slug', 'avatar_url']);

        return response()->json([
            'users' => $users->map(fn (User $u) => [
                'id' => $u->id,
                'label' => $u->first_name ?: $u->name,
                'name' => $u->name,
                'slug' => $u->slug,
                'avatar_url' => $u->avatar_url,
            ]),
        ]);
    }
}
