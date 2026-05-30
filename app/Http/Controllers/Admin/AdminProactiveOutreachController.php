<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Operations\OperationsProactiveOutreachController;
use Inertia\Inertia;
use Inertia\Response;

class AdminProactiveOutreachController extends OperationsProactiveOutreachController
{
    public function index(): Response
    {
        return Inertia::render('Operations/Outreach/Index', [
            'can_manage_templates' => request()->user()?->role?->slug === 'super_admin',
            'route_prefix' => 'admin',
            'use_admin_shell' => true,
        ]);
    }
}
