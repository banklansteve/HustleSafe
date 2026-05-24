<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminPlatformFeeLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminPlatformFeeLedgerController extends Controller
{
    public function __construct(private readonly AdminPlatformFeeLedgerService $fees) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->role?->slug === 'super_admin', 403);

        return response()->json($this->fees->index($request));
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->role?->slug === 'super_admin', 403);

        return $this->fees->exportCsv($request);
    }
}
