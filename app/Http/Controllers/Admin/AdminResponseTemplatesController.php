<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Operations\OperationsResponseTemplatesController;
use Inertia\Inertia;
use Inertia\Response;

class AdminResponseTemplatesController extends OperationsResponseTemplatesController
{
    public function index(): Response
    {
        abort_unless(request()->user()?->role?->slug === 'super_admin', 403);

        return Inertia::render('Operations/ResponseTemplates/Index', [
            'situations' => $this->service->situationOptions(),
            'route_prefix' => 'admin',
            'use_admin_shell' => true,
        ]);
    }
}
