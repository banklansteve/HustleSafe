<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserVerification;
use App\Services\Verification\UserVerificationDecisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserVerificationReviewController extends Controller
{
    public function __construct(private readonly UserVerificationDecisionService $decisions) {}

    public function decide(Request $request, UserVerification $verification): JsonResponse
    {
        abort_unless($request->user()?->role?->slug === 'super_admin', 403);

        $data = $request->validate([
            'action' => ['required', 'in:approve,reject,request_corrections'],
            'reason_code' => ['required_unless:action,approve', 'nullable', 'string', 'max:40'],
            'reason_note' => ['nullable', 'string', 'max:2000'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $result = $this->decisions->decide($verification, $request->user(), $data, $request, 'admin.verification');

        return response()->json($result);
    }
}
