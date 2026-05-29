<?php

namespace App\Services\Hr;

use App\Models\StaffHrAuditTrail;
use App\Models\User;
use Illuminate\Http\Request;

class HrAuditTrailService
{
    public function log(
        User $actor,
        string $actionType,
        ?int $targetStaffUserId = null,
        ?array $before = null,
        ?array $after = null,
        ?array $metadata = null,
        ?Request $request = null,
    ): StaffHrAuditTrail {
        $req = $request ?? request();

        return StaffHrAuditTrail::query()->create([
            'actor_user_id' => $actor->id,
            'action_type' => $actionType,
            'target_staff_user_id' => $targetStaffUserId,
            'before_values' => $before,
            'after_values' => $after,
            'metadata' => $metadata,
            'ip_address' => $req?->ip(),
            'user_agent' => $req ? substr((string) $req->userAgent(), 0, 2000) : null,
            'created_at' => now(),
        ]);
    }
}
