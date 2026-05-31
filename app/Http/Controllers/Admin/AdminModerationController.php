<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Operations\OperationsModerationController;
use App\Services\ConversationMonitoring\ConversationMonitoringAdminService;
use App\Services\Operations\StaffModerationQueueService;
use App\Support\Operations\StaffCapabilities;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminModerationController extends OperationsModerationController
{
    public function index(Request $request, StaffModerationQueueService $queues, ConversationMonitoringAdminService $conversationMonitoring): Response
    {
        return Inertia::render('Operations/Moderation/Index', [
            'quest_queues' => $queues->questQueues(),
            'proposal_queues' => $queues->proposalQueues(),
            'options' => $queues->options(),
            'conversation_monitoring_summary' => $conversationMonitoring->summary($request->user()),
            'capabilities' => [
                'quest_admin_statuses' => StaffCapabilities::questAdminStatusValues(),
                'proposal_admin_statuses' => StaffCapabilities::proposalAdminStatusValues(),
            ],
            'route_prefix' => 'admin',
            'use_admin_shell' => true,
        ]);
    }
}
