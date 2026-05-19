<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminStaffActivityDigestService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminStaffActivityDigestController extends Controller
{
    public function __invoke(Request $request, AdminStaffActivityDigestService $digest): Response
    {
        return Inertia::render('Admin/Activity/Digest', [
            ...$digest->payload($request),
        ]);
    }
}
