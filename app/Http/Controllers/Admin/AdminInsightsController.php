<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminInsightsService;
use Inertia\Inertia;
use Inertia\Response;

class AdminInsightsController extends Controller
{
    public function __invoke(AdminInsightsService $insights): Response
    {
        return Inertia::render('Admin/Insights/Index', [
            'insights' => $insights->payload(),
        ]);
    }
}
