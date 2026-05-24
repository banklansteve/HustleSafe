<?php

use Illuminate\Support\Str;

/**
 * Additional Super Admin Dashboard Guide topics.
 * Merged in AdminDocumentationService::topics().
 *
 * @return array<int, array<string, mixed>>
 */
$section = function (string $title, array $items, array $keywords = []): array {
    return [
        'id' => Str::slug($title),
        'title' => $title,
        'items' => $items,
        'keywords' => $keywords,
    ];
};

$topic = function (string $slug, string $title, string $category, string $summary, string $module, array $sections, array $related = []): array {
    return compact('slug', 'title', 'category', 'summary', 'module', 'sections', 'related');
};

return [
    $topic('insights-analytics', 'Insights', 'Home', 'Marketplace trends, cohort movement, and where to drill into Reports.', 'Insights', [
        $section('What It Does', [
            'Insights surfaces trend lines and breakdowns across quests, users, revenue, and trust metrics.',
            'Use it for weekly reviews and to spot anomalies before they become support volume.',
        ], ['insights', 'trends', 'analytics']),
        $section('Step-by-Step', [
            'Open Home → Insights.',
            'Select date range and segment (role, category, region if available).',
            'Click a chart segment to jump to the underlying engine when a drill-down link is offered.',
            'Export or screenshot for leadership reviews; confirm numbers in Reports before operational changes.',
        ], ['how to', 'date range']),
        $section('Common Tasks', [
            'Investigate sudden drop in funded quests → check Financial Control and category health.',
            'Investigate verification spike → open Verification Engine queue depth.',
            'Share trend summary with operations leads for patrol prioritisation.',
        ], ['common tasks']),
    ], ['reporting-analytics', 'dashboard-home']),

    $topic('live-activity-alerts', 'Live Activity & Alert Centre', 'Home', 'Real-time platform events and the super-admin notification inbox.', 'Live Activity', [
        $section('Live Activity', [
            'Shows a live feed of notable marketplace events: signups, quests, proposals, payments, flags.',
            'Use to monitor launch days, incidents, or marketing campaigns.',
            'Click an event to open the related admin record when linked.',
        ], ['live activity', 'feed', 'real time']),
        $section('Alert Centre — Step-by-Step', [
            'Open Home → Alert Centre.',
            'Process critical items first (fraud, treasury, system).',
            'Open each alert — marking read does not replace fixing the underlying issue.',
            'Use filters for unread vs actioned; bulk mark read only after triage.',
            'Assign follow-up via Tasks or staff referral when operations should own it.',
        ], ['alert centre', 'notifications', 'inbox']),
        $section('Tasks (Super Admin)', [
            'Home → Tasks lists platform tasks and assignments.',
            'Create tasks for operations referrals with clear acceptance criteria.',
            'Close tasks when audit shows resolution; link quest/user IDs in notes.',
        ], ['tasks', 'assignments']),
    ], ['audit-trails', 'communications']),

    $topic('live-support-super-admin', 'Live Support (Super Admin)', 'Customer support', 'All-queue live chat, reassignment, performance, and ticket archives.', 'Live support', [
        $section('Workspace Layout', [
            'Left: Live vs History tabs, search, filters (super admin sees all admins’ chats).',
            'Centre: message thread, composer, internal notes, GIF/attachments.',
            'Right: customer profile slide-over with quests, KYC, past support, disputes.',
        ], ['layout', 'workspace']),
        $section('Handling Chats — Step-by-Step', [
            'Select a chat from Live queue sorted by latest activity.',
            'Review profile context before replying.',
            'Reply in customer-visible mode; use Internal note only for staff.',
            'Reassign to another admin if specialist or load balancing — full history travels.',
            'End session when resolved; customer gets feedback survey; chat moves to History.',
        ], ['live support', 'chat', 'end session']),
        $section('Super Admin Filters', [
            'Filter by assigned admin, status (queued/active), category, assignment state.',
            'History tab: closed sessions grouped by date; open old sessions for complaints research.',
        ], ['filters', 'history']),
        $section('Support Performance', [
            'Customer support → Support performance shows per-admin ratings, volume, resolution time.',
            'Click an admin card to open feedback table for individual sessions.',
            'Use for coaching operations staff — not public to users.',
        ], ['performance', 'ratings']),
        $section('Support Tickets (Legacy/Email)', [
            'Support Tickets may hold non-live-channel tickets; triage separately from real-time queue.',
        ], ['support tickets']),
    ], ['communications', 'user-management']),

    $topic('staff-knowledge-base', 'Staff Knowledge Base', 'Customer support', 'Authoring procedures and policies for operations staff.', 'Knowledge base', [
        $section('Purpose', [
            'Operations staff read published articles under Operations → Knowledge base.',
            'Super Admins author content under Customer support → Knowledge base.',
        ], ['knowledge base', 'documentation']),
        $section('Authoring — Step-by-Step', [
            'Open Customer support → Knowledge base.',
            'Enter title, category (e.g. Common tasks, Cases, Escalations), and HTML body.',
            'Use headings (h2), ordered lists for steps, and bold for warnings.',
            'Publish — status defaults to published for staff visiblity.',
            'Run php artisan knowledge-base:seed --force in deployment to bulk-update seeded articles.',
            'Review staff suggestions submitted from operations slide-over (stored in DB).',
        ], ['authoring', 'publish', 'seed']),
        $section('Organisation Tips', [
            'Keep a Common tasks category for high-frequency workflows.',
            'One article per workflow; cross-link in overview articles.',
            'Update articles when policy changes — date in title or intro helps.',
        ], ['organisation', 'categories']),
    ], ['live-support-super-admin']),

    $topic('categories-moderation-content', 'Categories & Content Moderation', 'Marketplace', 'Quest taxonomy and UGC moderation beyond proposals/quests engines.', 'Categories', [
        $section('Categories', [
            'Marketplace → Categories manages quest taxonomy, fees, and display names.',
            'Editing names/fees on active categories requires acknowledgement of impact.',
            'Use when restructuring verticals or fixing misclassified quests.',
        ], ['categories', 'fees', 'taxonomy']),
        $section('Content Moderation', [
            'Marketplace → Content Moderation handles portfolios, reviews, messages, and reported UGC.',
            'Workflow mirrors flag/restrict patterns: open item, read context, action with reason.',
            'Coordinate with operations Reviews and Review integrity for rating abuse.',
        ], ['content moderation', 'ugc', 'portfolios']),
    ], ['quest-management', 'proposal-management']),

    $topic('user-intelligence-compliance', 'User Intelligence & Compliance', 'Risk & compliance', 'Deep user graphs and regulatory reporting.', 'User Intelligence', [
        $section('User Intelligence', [
            'Risk & compliance → User Intelligence links accounts, devices, payments, and behaviour graphs.',
            'Use for organised fraud rings and duplicate account investigations.',
            'Export or refer to operations trust monitoring for day-to-day watchlists.',
        ], ['intelligence', 'linked accounts']),
        $section('Compliance', [
            'Compliance module supports regulatory requests, data retention, and policy attestations.',
            'Legal requests should be escalated through compliance playbook — document every export.',
        ], ['compliance', 'legal', 'gdpr']),
    ], ['risk-engine', 'fraud']),

    $topic('internal-communications', 'Team Chat & Direct Messages', 'Communications', 'Internal staff messaging at super-admin level.', 'Team chat', [
        $section('Team Chat', [
            'Communications → Team chat is the operations/admin group channel.',
            'Super Admin can pin guidance, announce policy changes, and coordinate incidents.',
            'Do not post customer PII unnecessarily; use ticket links instead.',
        ], ['team chat', 'internal']),
        $section('Direct Messages', [
            'Sidebar Direct messages opens 1:1 messenger with admins/operations.',
            'Use for sensitive escalations; confirm outcomes in Tasks or audit when decision is made.',
        ], ['direct messages', 'dm']),
        $section('Staff Digest', [
            'Communications → Staff Digest summarises daily admin activity for oversight.',
            'Pair with Audit log for investigations into who changed a record.',
        ], ['staff digest', 'activity']),
    ], ['communications', 'audit-trails']),

    $topic('treasury-promotions', 'Treasury & Promotions', 'Revenue & growth', 'Cash movement oversight and growth campaigns.', 'Treasury', [
        $section('Treasury', [
            'Revenue & growth → Treasury shows platform balances, reserves, and movement summaries.',
            'Use with Financial Control when investigating payout delays or holds.',
            'Treasury actions are highly restricted — dual-control with policy.',
        ], ['treasury', 'balances']),
        $section('Promotions & Growth', [
            'Configure campaigns, coupons, or featured placement rules per product settings.',
            'Monitor uptake in Insights; watch for abuse via Fraud & Risk.',
        ], ['promotions', 'growth', 'campaigns']),
    ], ['payments-escrow', 'reporting-analytics']),

    $topic('staff-platform-settings', 'Staff, Settings & Platform', 'Platform', 'Roles, maintenance mode, engagement rules, and registry.', 'Staff & roles', [
        $section('Staff & Roles', [
            'Platform → Staff & roles manages admin and operations accounts.',
            'Creating admins requires password confirmation and is audited + emailed.',
            'Deactivate rather than delete when staff leave; preserve audit attribution.',
        ], ['staff', 'roles', 'admin accounts']),
        $section('Settings & Maintenance', [
            'Settings: platform configuration sections (feature flags, integrations).',
            'Site maintenance: enable banner/maintenance mode for deploys — communicate in Team chat first.',
            'Engagement policy: rules shown to users about proposals, messaging, and fees.',
        ], ['settings', 'maintenance', 'engagement policy']),
        $section('Data Registry', [
            'Dynamic management screens for reference data (tags, reasons, templates).',
            'Changes affect production immediately — edit in low-traffic windows.',
        ], ['data registry', 'reference data']),
    ], ['audit-trails', 'overview']),
];
