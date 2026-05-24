<?php

namespace App\Services\Operations;

use App\Models\AnnouncementBanner;
use App\Models\EmailBroadcast;

class StaffCommunicationsViewerService
{
    public function listing(): array
    {
        $banners = AnnouncementBanner::query()
            ->with('creator:id,name')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (AnnouncementBanner $b) => [
                'type' => 'banner',
                'id' => $b->id,
                'title' => str($b->message)->limit(80)->toString(),
                'audience' => $b->segment ?? 'all',
                'status' => $b->status,
                'scheduled' => $b->starts_at?->isFuture(),
                'starts_at' => $b->starts_at?->toIso8601String(),
                'ends_at' => $b->ends_at?->toIso8601String(),
                'sent_by' => $b->creator?->name,
                'created_at' => $b->created_at?->toIso8601String(),
            ]);

        $broadcasts = EmailBroadcast::query()
            ->with('creator:id,name')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (EmailBroadcast $e) => [
                'type' => 'email_broadcast',
                'id' => $e->id,
                'title' => $e->subject,
                'audience' => $e->audience_description ?? (is_array($e->audience) ? implode(', ', $e->audience) : 'segmented'),
                'status' => $e->status,
                'scheduled' => $e->scheduled_for?->isFuture(),
                'starts_at' => $e->scheduled_for?->toIso8601String() ?? $e->sent_at?->toIso8601String(),
                'ends_at' => null,
                'sent_by' => $e->creator?->name,
                'recipients' => $e->total_recipients,
                'created_at' => $e->created_at?->toIso8601String(),
            ]);

        $items = $banners->concat($broadcasts)->sortByDesc('created_at')->values();

        return [
            'items' => $items->all(),
            'upcoming' => $items->filter(fn ($i) => $i['scheduled'])->values()->all(),
        ];
    }
}
