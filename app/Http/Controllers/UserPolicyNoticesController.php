<?php

namespace App\Http\Controllers;

use App\Services\UserPolicyNoticesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserPolicyNoticesController extends Controller
{
    public function index(Request $request, UserPolicyNoticesService $notices): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        return Inertia::render('Account/PolicyNotices/Index', $notices->indexPayload($user));
    }

    public function acknowledge(Request $request, string $source, int $id, UserPolicyNoticesService $notices): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $notices->acknowledge($user, $source, $id);

        return response()->json([
            'message' => __('Thanks — we have recorded that you saw this notice.'),
            ...$notices->indexPayload($user),
        ]);
    }
}
