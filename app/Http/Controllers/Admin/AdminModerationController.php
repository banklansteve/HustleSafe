<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Operations\OperationsModerationController;
use App\Services\ConversationMonitoring\ConversationMonitoringAdminService;
use App\Services\Admin\QuestPatrol\QuestPatrolTrendsService;
use App\Services\Operations\StaffModerationQueueService;
use App\Support\Operations\ModerationPatrolCapabilities;
use App\Support\Operations\StaffCapabilities;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminModerationController extends OperationsModerationController
{
    public function index(Request $request, StaffModerationQueueService $queues, ConversationMonitoringAdminService $conversationMonitoring, QuestPatrolTrendsService $patrolTrends): Response
    {
        $isSuper = $request->user()?->role?->slug === 'super_admin';

        return Inertia::render('Operations/Moderation/Index', [
            'quest_queues' => $queues->questQueues(),
            'proposal_queues' => $queues->proposalQueues(),
            'options' => $queues->options(),
            'conversation_monitoring_summary' => $conversationMonitoring->summary($request->user()),
            'capabilities' => [
                'quest_admin_statuses' => StaffCapabilities::questAdminStatusValues(),
                'proposal_admin_statuses' => StaffCapabilities::proposalAdminStatusValues(),
            ],
            'patrol_capabilities' => ModerationPatrolCapabilities::forUser($request->user()),
            'patrol_trends' => $isSuper ? $patrolTrends->summary() : null,
            'pending_approval_requests' => $isSuper ? $patrolTrends->pendingApprovals() : [],
            'patrol_options' => [
                'dismissal_reasons' => collect(config('quest_patrol.dismissal_reasons', []))->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all(),
                'admin_boost_reasons' => collect(config('quest_patrol.admin_boost_reasons', []))->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all(),
                'revision_issue_types' => collect(config('quest_patrol.revision_issue_types', []))->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all(),
                'boost_tiers' => [
                    ['value' => '3_days', 'label' => '3 days'],
                    ['value' => '7_days', 'label' => '7 days'],
                    ['value' => '14_days', 'label' => '14 days'],
                    ['value' => '30_days', 'label' => '30 days'],
                ],
            ],
            'route_prefix' => 'admin',
            'use_admin_shell' => true,
        ]);
    }
}
