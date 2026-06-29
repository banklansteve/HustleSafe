<?php

namespace App\Services\UserActivity;

use App\Models\User;
use App\Models\UserAuditEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class UserActivityRecorder
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function record(
        User $user,
        string $action,
        string $title,
        ?string $summary = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $meta = [],
        ?Request $request = null,
    ): ?UserAuditEvent {
        if (! Schema::hasTable('user_audit_events')) {
            return null;
        }

        $req = $request ?? request();

        return UserAuditEvent::query()->create([
            'user_id' => $user->id,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'title' => $title,
            'summary' => $summary,
            'meta' => $meta === [] ? null : $meta,
            'ip_address' => $req?->ip(),
            'user_agent' => $req ? substr((string) $req->userAgent(), 0, 2000) : null,
            'occurred_at' => now(),
        ]);
    }

    public function recordModel(
        User $user,
        string $action,
        string $title,
        Model $subject,
        ?string $summary = null,
        array $meta = [],
        ?Request $request = null,
    ): ?UserAuditEvent {
        return $this->record(
            $user,
            $action,
            $title,
            $summary,
            $subject->getMorphClass(),
            (int) $subject->getKey(),
            $meta,
            $request,
        );
    }
}
