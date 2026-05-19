<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminCommandCentreService;
use Inertia\Inertia;
use Inertia\Response;

class AdminTreasuryController extends Controller
{
    public function __invoke(AdminCommandCentreService $service): Response
    {
        return Inertia::render('Admin/CommandRisk/Index', [
            'mode' => 'treasury',
            'payload' => $service->treasuryPayload(),
        ]);
    }
}
