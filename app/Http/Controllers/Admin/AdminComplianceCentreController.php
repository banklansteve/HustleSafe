<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdminComplianceRequest;
use App\Models\AdminComplianceRequest;
use App\Services\Admin\AdminCommandCentreService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AdminComplianceCentreController extends Controller
{
    public function index(AdminCommandCentreService $service): Response
    {
        return Inertia::render('Admin/CommandRisk/Index', [
            'mode' => 'compliance',
            'payload' => $service->compliancePayload(),
        ]);
    }

    public function store(StoreAdminComplianceRequest $request): RedirectResponse
    {
        AdminComplianceRequest::query()->create($request->validated());

        return back()->with('success', 'Compliance request opened.');
    }
}
