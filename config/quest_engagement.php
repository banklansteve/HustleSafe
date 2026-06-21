<?php

return [
    'anchors' => [
        'contract_agreed_delivery_date' => 'Active contract agreed delivery date (highest priority once awarded).',
        'delivery_deadline' => 'Client hard cutoff (`delivery_deadline`) — drives engagement when set.',
        'proposal_planned_finish_date' => 'Accepted proposal finish date when the quest is funded/in progress.',
        'estimated_delivery_date' => 'Client planned finish (`estimated_delivery_date`) — soft target; used when no hard deadline exists (backward compatible).',
        'legacy_due_at' => 'Legacy `due_at` timestamp from create/edit when no explicit dates exist.',
        'estimated_completion_days' => 'Fallback: `escrow_funded_at` + `estimated_completion_days` when other anchors are missing.',
    ],
    'terminology' => [
        'planned_finish' => 'Soft target date the client hopes work will finish (`estimated_delivery_date`).',
        'delivery_deadline' => 'Hard cutoff — finished work must be delivered by this date (`delivery_deadline`).',
    ],
    'mid_checkin' => [
        'when' => 'Once, after escrow is funded, when “now” passes the midpoint between `escrow_funded_at` and the engagement anchor, and before that anchor.',
        'audience' => 'Separate emails to the freelancer and the client.',
        'topics' => [
            'Remind parties to keep coordination on-platform.',
            'Client: mark complete when satisfied; open a dispute early if expectations are not met.',
            'Freelancer: surface blockers early; disputes remain available if collaboration breaks down.',
        ],
    ],
    'post_deadline' => [
        'when' => 'After the engagement anchor: client-only emails at +0h (due day), +24h, and +36h (catch-up friendly if a run was missed).',
        'audience' => 'Client only — freelancers are not emailed on this schedule.',
        'topics' => [
            'Due day: review reminder on the agreed delivery anchor date.',
            '+24h: reminder to mark complete or open a dispute.',
            '+36h: final reminder before the auto-release window closes.',
        ],
    ],
    'auto_complete' => [
        'when' => 'Hourly job `quests:process-lifecycle` when: quest is `in_progress`, escrow `funded`, `completed_at` and `auto_completed_at` are empty, no blocking dispute exists, and now ≥ engagement anchor + `financial.auto_release_hours` (default 72).',
        'blocking_disputes' => 'Statuses treated as blocking: open, self_resolving, escalated, awaiting_ruling.',
        'effects' => [
            'Sets `status` to completed, stamps `completed_at`, `auto_completed_at`, `closure_type` = auto_completed_silent_72h, `completed_on_time` = false.',
            'If delivery was not acknowledged, sets `delivery_acknowledged_at` automatically before release.',
            'If the accepted proposal has `quoted_amount_minor` > 0, `paid_out_minor` is set from it; otherwise unchanged.',
        ],
        'notifications' => 'Both parties receive `QuestAutoCompletedNotification` (mail + in-app database channel) when funds auto-release.',
    ],
    'email_logging' => 'Every engagement email is deduplicated via `quest_lifecycle_email_logs` (quest_id + email_key + recipient_user_id).',
];
