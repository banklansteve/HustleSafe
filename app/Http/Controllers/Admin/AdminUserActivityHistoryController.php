<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserActivityHistoryRequest;
use App\Models\User;
use App\Services\Admin\AdminUserActivityHistoryService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserActivityHistoryController extends Controller
{
    public function __construct(
        private readonly AdminUserActivityHistoryService $history,
    ) {}

    public function index(Request $request): Response
    {
        $directory = $this->history->userDirectory();
        $selectedUser = null;

        if ($request->filled('user_id')) {
            $userId = $request->integer('user_id');
            $selectedUser = collect($directory)->firstWhere('id', $userId);

            if ($selectedUser === null) {
                $user = User::query()
                    ->with('role:id,slug,name')
                    ->find($userId);

                if ($user) {
                    $selectedUser = $this->history->userSnapshot($user);
                }
            }
        }

        return Inertia::render('Admin/UserActivityHistory/Index', [
            'defaults' => [
                'from' => now()->subDays(7)->toDateString(),
                'to' => now()->toDateString(),
            ],
            'selected_user' => $selectedUser,
            'user_directory' => $directory,
            'opened_from_registry' => $request->boolean('registry'),
        ]);
    }

    public function timeline(UserActivityHistoryRequest $request): JsonResponse
    {
        $user = User::query()
            ->with('role:id,slug,name')
            ->findOrFail($request->integer('user_id'));

        return response()->json(
            $this->history->timeline(
                $user,
                Carbon::parse($request->input('from')),
                Carbon::parse($request->input('to')),
            ),
        );
    }
}
