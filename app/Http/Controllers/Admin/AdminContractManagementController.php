<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Operations\OperationsContractManagementController;
use Illuminate\Http\Request;
use Inertia\Response;

class AdminContractManagementController extends OperationsContractManagementController
{
    protected bool $isSuperAdmin = true;

    protected string $routePrefix = 'admin';

    protected bool $useAdminShell = true;

    public function index(Request $request): Response
    {
        return parent::index($request);
    }
}
