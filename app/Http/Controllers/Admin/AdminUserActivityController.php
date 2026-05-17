<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AdminActivityLog;
use App\Models\LoginEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserActivityController extends Controller
{
    public function show(Request $request, User $user): Response
    {
        $user->load('role:id,slug,name');

        $activity = ActivityLog::query()
            ->where('subject_user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        $logins = LoginEvent::query()
            ->where('user_id', $user->id)
            ->orderByDesc('logged_in_at')
            ->limit(50)
            ->get();

        $adminTouches = AdminActivityLog::query()
            ->where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->with('actor:id,name,email')
            ->get();

        return Inertia::render('Admin/Management/UserActivity', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_slug' => $user->role?->slug,
                'suspended_at' => $user->suspended_at?->toIso8601String(),
                'last_active_at' => $user->last_active_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            'activity' => $activity,
            'logins' => $logins,
            'admin_audit' => $adminTouches,
        ]);
    }
}
