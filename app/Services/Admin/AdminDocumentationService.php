<?php

namespace App\Services\Admin;

use Illuminate\Support\Str;

class AdminDocumentationService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function topics(): array
    {
        return [
            $this->topic('overview', 'Overview', 'Start here', 'What the Super Admin dashboard is, how engines connect, and the principles behind moderation.', 'Dashboard Home', [
                $this->section('What the Dashboard Is', [
                    'The Super Admin dashboard is the internal operating system for HustleSafe. It brings together marketplace operations, trust and safety, verification, payments, disputes, communications, reporting, and audit trails.',
                    'Admins use it to review activity after users act, intervene when risk appears, and keep users moving through quests safely.',
                ], ['dashboard', 'overview', 'engines', 'moderation']),
                $this->section('Core Principles', [
                    'Dual-status architecture: user-facing operational statuses are kept separate from admin review statuses. Admins can restrict, flag, or resolve without overwriting normal business state.',
                    'Retrospective moderation: proposals and quests can go live first, then admins review them through moderation layers.',
                    'Trust and safety first: verification, fraud signals, flags, notices, audit logs, and staff tasks work together to protect users.',
                    'Every meaningful admin action should record who acted, what changed, why it changed, and when it happened.',
                ], ['dual status', 'retrospective moderation', 'audit']),
                $this->section('How Modules Relate', [
                    'Proposals belong to Quests. A risky proposal may create flags, notices, admin tasks, audit entries, and communication follow-ups.',
                    'Verification affects trust levels and limits. Those limits influence proposal access, quest posting, escrow safeguards, and high-value checks.',
                    'Payments, escrow, disputes, and treasury reports are connected through quest lifecycle state.',
                ], ['relationships', 'verification', 'escrow']),
                $this->section('Quick Links', [
                    'Use Proposals for freelancer bid moderation.',
                    'Use Quests for client listing and contract operations.',
                    'Use Verification Engine for BVN, NIN, utility, identity, trust levels, limits, and document review.',
                    'Use Staff Digest, Reports, and Audit Log for oversight.',
                ], ['quick links', 'where to find']),
            ], ['proposal-management', 'quest-management', 'verification-trust', 'audit-trails']),

            $this->topic('dashboard-home', 'Dashboard Home', 'Overview', 'Executive snapshot for marketplace health, live metrics, alerts, and shortcuts.', 'Dashboard Home', [
                $this->section('What It Does', [
                    'Dashboard Home gives super admins a fast snapshot of platform health, active work, recent changes, and important shortcuts.',
                    'It is the best place to start when deciding which engine needs attention first.',
                ], ['home', 'metrics', 'kpi']),
                $this->section('Key Actions', [
                    'Scan KPI tiles for unusual movement.',
                    'Open quick actions for common admin tasks.',
                    'Jump into engines such as Proposals, Quests, Users, Reports, and Verification.',
                ], ['actions', 'shortcuts']),
                $this->section('Interpreting Metrics', [
                    'Treat dashboard metrics as directional signals. Use the dedicated module pages for the source data and action tools.',
                    'If a number looks wrong, check Reports, Live Activity, and Audit Log before changing records.',
                ], ['metrics', 'troubleshooting']),
            ], ['reporting-analytics', 'audit-trails']),

            $this->topic('proposal-management', 'Proposal Management Engine', 'Operations', 'Retrospective moderation, risk intelligence, notices, referrals, and bulk actions for freelancer proposals.', 'Proposals', [
                $this->section('What It Does', [
                    'The Proposal Management Engine reviews freelancer proposals after submission without changing the operational proposal lifecycle.',
                    'It is used to flag risky proposals, restrict acceptance, suspend visibility, contact users, post notices, edit content, and create audit trails.',
                ], ['proposals', 'moderation', 'proposal engine']),
                $this->section('Operational Status Definitions', [
                    'Submitted: freelancer sent the proposal and it is waiting for client/admin activity.',
                    'Viewed: client or relevant actor has opened it.',
                    'Shortlisted: client marked it as a strong candidate.',
                    'Accepted: proposal became the selected offer.',
                    'Rejected, Withdrawn, Expired: proposal is no longer active for normal selection.',
                ], ['submitted', 'viewed', 'shortlisted', 'accepted', 'rejected', 'withdrawn', 'expired']),
                $this->section('Admin Status Lifecycle', [
                    'Clear: no active admin concern.',
                    'Flagged: risk or policy issue has been identified.',
                    'Under Review: admin is investigating.',
                    'Referred: assigned or passed to another admin/staff member.',
                    'Action Required: user or staff follow-up is needed.',
                    'Restricted: proposal remains visible but cannot be accepted.',
                    'Suspended: proposal is hidden from users.',
                    'Resolved: concern has been closed and normal behaviour resumes.',
                ], ['admin status', 'clear', 'flagged', 'restricted', 'suspended', 'resolved']),
                $this->section('Risk Signals Explained', [
                    'Off-platform contact: phone numbers, WhatsApp handles, external URLs, or instructions to leave HustleSafe.',
                    'Lowball bid: quoted amount is far below the quest budget and may indicate spam or unsafe engagement.',
                    'Velocity spam: many proposals in a short window.',
                    'New account high-value activity: a low-trust or new freelancer bids on high-value work.',
                    'Prior admin actions: the freelancer has previous flags, restrictions, or moderation history.',
                ], ['risk signals', 'off platform', 'lowball', 'velocity', 'trust']),
                $this->section('Common Workflows', [
                    'Open a proposal from table, card, or kanban view.',
                    'Review proposal content, risk panel, freelancer profile, quest context, flags, communications, audit, and notes.',
                    'Choose an action, enter a reason, save, and confirm the audit trail updates.',
                    'Use bulk actions only when selected proposals share the same risk or workflow context.',
                ], ['workflow', 'bulk actions', 'slide-over']),
                $this->section('Troubleshooting', [
                    'Proposal hidden: admin status is Suspended.',
                    'Proposal cannot be accepted: admin status is Restricted or an urgent notice blocks acceptance.',
                    'Conflicting statuses: operational status shows business lifecycle; admin status shows moderation state. Do not treat them as the same thing.',
                ], ['hidden proposal', 'cannot accept', 'conflicting statuses']),
            ], ['quest-management', 'flags-notices', 'risk-engine', 'audit-trails']),

            $this->topic('quest-management', 'Quest Management Engine', 'Operations', 'Quest listing moderation, lifecycle intervention, flags, featured boosts, and escrow context.', 'Quests', [
                $this->section('What It Does', [
                    'The Quest Management Engine helps admins supervise client quest listings, intervene in lifecycle issues, manage visibility, and inspect operational context.',
                    'It mirrors the proposal engine patterns so admins can move between both engines without learning a new workflow.',
                ], ['quests', 'quest engine', 'lifecycle']),
                $this->section('Key Actions', [
                    'Review quest details, client context, proposal activity, escrow state, flags, notices, and audit history.',
                    'Change admin review state, flag a quest, post notices, feature or unfeature listings, and resolve issues with a reason.',
                    'Use table and card layouts to scan many quests quickly.',
                ], ['actions', 'flags', 'notices', 'featured']),
                $this->section('Statuses and Signals', [
                    'Quest operational status tells you where the quest is in the marketplace lifecycle.',
                    'Admin status tells you whether staff have a moderation concern.',
                    'Escrow status tells you whether funds are pending, funded, released, frozen, or under dispute context.',
                ], ['quest status', 'admin status', 'escrow']),
                $this->section('Where to Find Things', [
                    'Use the search bar for title, client, email, category, or Quest ID.',
                    'Use advanced filters for status, budget, date, project type, proposal count, flags, and featured state.',
                    'Open detail slide-over for full context without leaving the engine.',
                ], ['search', 'advanced filters', 'slide-over']),
            ], ['proposal-management', 'payments-escrow', 'disputes-resolutions']),

            $this->topic('user-management', 'User Management', 'Operations', 'User profiles, trust context, sanctions, activity, communications, and staff interventions.', 'Users', [
                $this->section('What It Does', [
                    'User Management gives admins a broad view of clients, freelancers, staff, verification state, trust, sanctions, activity, and communication history.',
                    'Use it when the issue is attached to a person rather than one proposal or quest.',
                ], ['users', 'profiles', 'sanctions']),
                $this->section('Key Actions', [
                    'Open a user slide-over to inspect overview, verification, sanctions, notes, activity, and communications.',
                    'Apply restrictions only when the reason is clear and auditable.',
                    'Use profile context to understand repeated flags or risky behaviour.',
                ], ['user slide-over', 'restrictions', 'notes']),
                $this->section('Where to Find Things', [
                    'Use Users for account-level review.',
                    'Use Verification Engine for document-level decisions.',
                    'Use Activity Log and Staff Digest for who changed what.',
                ], ['where to find', 'verification', 'activity']),
            ], ['verification-trust', 'communications', 'audit-trails']),

            $this->topic('verification-trust', 'Verification & Trust', 'Trust & Safety', 'BVN, NIN, utility, identity, CAC, trust levels, limits, and document review workflows.', 'Verification Engine', [
                $this->section('What It Does', [
                    'Verification & Trust controls identity checks, document review, trust levels, marketplace limits, safeguards, anomaly flags, and manual overrides.',
                    'It is where super admins mark BVN, NIN, utility, address, identity, business, and credential submissions as verified, unverified, or flagged.',
                ], ['verification', 'bvn', 'nin', 'utility', 'trust']),
                $this->section('Document Review Workflow', [
                    'Open the Document Review Desk.',
                    'Open a submission slide-over.',
                    'Review metadata and documents.',
                    'Choose Verified, Unverified, or Flagged.',
                    'Enter the decision reason and concern when follow-up is needed.',
                    'Refer to a staff admin to create a regularisation task.',
                ], ['document review', 'regularisation', 'referral']),
                $this->section('Trust Levels and Limits', [
                    'Earned level is based on completed verification requirements.',
                    'Effective level may be lower when safeguards such as new-account cooldown apply.',
                    'Posting and proposal limits are calculated from effective level unless a super admin override exists.',
                ], ['earned level', 'effective level', 'limits']),
                $this->section('Troubleshooting', [
                    'Freelancer restricted: verification restriction is active or effective level gives no proposal access.',
                    'Document not raising level: the required verification type may not match the configured level requirement.',
                    'Flagged document: regularise the concern, then mark verified or unverified after review.',
                ], ['restricted freelancer', 'flagged document', 'level not updating']),
            ], ['user-management', 'risk-engine', 'audit-trails']),

            $this->topic('payments-escrow', 'Payments & Escrow', 'Finance', 'Financial control, escrow funding, release context, frozen funds, and treasury oversight.', 'Financial Control', [
                $this->section('What It Does', [
                    'Payments & Escrow shows money movement and funding status around quests, contracts, payouts, fees, refunds, and frozen funds.',
                    'Use it to understand whether a quest is financially ready to proceed or release.',
                ], ['payments', 'escrow', 'treasury']),
                $this->section('Key Actions', [
                    'Inspect escrow ledger and funding state.',
                    'Review frozen or disputed funds.',
                    'Coordinate with disputes before releasing or adjusting money.',
                ], ['ledger', 'frozen funds', 'release']),
                $this->section('Status Interpretation', [
                    'Awaiting funding means the client still needs to fund escrow.',
                    'Funded means money is available under platform controls.',
                    'Disputed or frozen means release should wait until resolution.',
                ], ['awaiting funding', 'funded', 'disputed']),
            ], ['quest-management', 'disputes-resolutions', 'reporting-analytics']),

            $this->topic('flags-notices', 'Flags & Notices System', 'Trust & Safety', 'Admin flags, user-visible notices, priorities, visibility impact, and resolution patterns.', 'Proposals', [
                $this->section('What It Does', [
                    'Flags are internal admin markers for risk, policy issues, or follow-up.',
                    'Notices are messages or warnings that can be visible to users and may block actions depending on severity.',
                ], ['flags', 'notices', 'warnings']),
                $this->section('Flag Types and Priorities', [
                    'Low priority: monitor but no immediate action.',
                    'Medium priority: review soon and decide whether to contact the user.',
                    'High priority: active risk, likely needs restriction, referral, or notice.',
                    'Critical priority: urgent safety, fraud, or platform integrity concern.',
                ], ['flag priorities', 'critical', 'high']),
                $this->section('Notice Types', [
                    'Info: explains a requirement without blocking the user.',
                    'Warning: alerts users about a problem that may need correction.',
                    'Urgent: high-severity message that can block acceptance or require admin release.',
                ], ['notice types', 'warning', 'urgent']),
                $this->section('Resolution Workflow', [
                    'Open the affected proposal, quest, or user.',
                    'Read the flag history and audit trail.',
                    'Take needed action and enter a reason.',
                    'Resolve the flag only after the issue is no longer active.',
                ], ['resolve flags', 'audit trail']),
            ], ['proposal-management', 'quest-management', 'risk-engine']),

            $this->topic('risk-engine', 'Risk Detection Engine', 'Trust & Safety', 'Risk signals, anomaly flags, high-value safeguards, and how admins interpret automated alerts.', 'Fraud & Risk', [
                $this->section('What It Does', [
                    'Risk Detection surfaces patterns that may indicate fraud, spam, unsafe transactions, or trust abuse.',
                    'Signals do not replace admin judgement; they explain why a record may need review.',
                ], ['risk detection', 'fraud', 'anomaly']),
                $this->section('Risk Levels', [
                    'Low: informational signal.',
                    'Medium: review before taking action.',
                    'High: likely needs staff attention.',
                    'Critical: urgent safety or fraud concern.',
                ], ['risk levels', 'severity']),
                $this->section('Common Signals', [
                    'Off-platform contact detected.',
                    'Proposal burst on high-value quests.',
                    'New account near tier ceiling.',
                    'Rapid verification followed by high-value action.',
                    'Duplicate identifiers or repeated document concerns.',
                ], ['signals', 'off platform', 'proposal burst', 'duplicate']),
            ], ['verification-trust', 'flags-notices', 'user-management']),

            $this->topic('communications', 'Communications', 'Operations', 'Email broadcasts, notifications, CS chat, user contact, templates, and communication auditability.', 'Email Broadcasts', [
                $this->section('What It Does', [
                    'Communications tools let admins contact users, send email broadcasts, issue notifications, reuse templates, and coordinate follow-ups.',
                    'Use communication tools when a user needs explanation, regularisation, or status updates.',
                ], ['communications', 'email', 'notifications', 'chat']),
                $this->section('Key Actions', [
                    'Send or schedule email broadcasts.',
                    'Contact freelancers or clients from moderation panels.',
                    'Use templates for consistent operational messages.',
                    'Keep sensitive decisions tied to audit trails and task notes.',
                ], ['email broadcasts', 'templates', 'contact']),
            ], ['user-management', 'proposal-management', 'verification-trust']),

            $this->topic('disputes-resolutions', 'Disputes & Resolutions', 'Operations', 'Dispute intake, evidence, escrow impact, resolution state, and staff handling.', 'Disputes', [
                $this->section('What It Does', [
                    'Disputes & Resolutions helps admins manage conflicts between clients and freelancers, inspect evidence, and coordinate outcomes.',
                    'Dispute state may affect escrow, quest status, payouts, and user trust.',
                ], ['disputes', 'resolutions', 'evidence']),
                $this->section('Key Actions', [
                    'Review dispute reason, evidence, parties, deadlines, and quest context.',
                    'Update dispute state when a decision or next step is clear.',
                    'Coordinate with Financial Control before payout or refund changes.',
                ], ['evidence', 'payout', 'refund']),
            ], ['payments-escrow', 'quest-management', 'communications']),

            $this->topic('bulk-operations', 'Bulk Operations', 'Operations', 'How to safely apply actions to many records without losing auditability or context.', 'Proposals', [
                $this->section('What It Does', [
                    'Bulk operations let admins apply the same action to several records at once.',
                    'Use bulk tools only when the selected records truly share the same reason and desired outcome.',
                ], ['bulk actions', 'bulk operations']),
                $this->section('Common Bulk Actions', [
                    'Change admin status.',
                    'Flag selected records.',
                    'Refer selected records.',
                    'Restrict or suspend where allowed.',
                    'Email, notify, post notice, or export selected records.',
                ], ['bulk status', 'bulk flag', 'bulk export']),
                $this->section('Safety Rules', [
                    'Always use a clear shared reason.',
                    'Avoid bulk destructive actions on records tied to active contracts.',
                    'Review the audit trail after large bulk changes.',
                ], ['safety', 'audit']),
            ], ['proposal-management', 'quest-management', 'audit-trails']),

            $this->topic('audit-trails', 'Admin Notes & Audit Trails', 'Governance', 'Private notes, immutable logs, reasons, before/after values, and staff accountability.', 'Audit log', [
                $this->section('What It Does', [
                    'Admin notes capture internal discussion and follow-up context.',
                    'Audit trails capture immutable action history: actor, timestamp, reason, before state, after state, and subject.',
                ], ['audit trails', 'admin notes', 'logs']),
                $this->section('How Audit Logs Are Generated', [
                    'Most moderation actions require a reason.',
                    'Services write audit entries after changing records.',
                    'Audit entries should explain what changed and why, not just that a button was clicked.',
                ], ['audit generated', 'reason']),
                $this->section('Best Practices', [
                    'Use notes for discussion and audit reasons for decision records.',
                    'Do not put sensitive secrets in notes.',
                    'Resolve pinned notes when the issue is closed.',
                ], ['notes', 'best practices']),
            ], ['staff-digest', 'reporting-analytics']),

            $this->topic('reporting-analytics', 'Reporting & Analytics', 'Insights', 'Reports, exports, analytics, staff digest, and high-level oversight.', 'Reports & analytics', [
                $this->section('What It Does', [
                    'Reporting & Analytics turns operational data into trends, exports, and oversight views.',
                    'Staff Digest shows what admins did each day: resolved work, pending work, overdue items, messages, verifications, disputes, and footprints.',
                ], ['reports', 'analytics', 'staff digest']),
                $this->section('Key Actions', [
                    'Preview and run reports.',
                    'Export data for review.',
                    'Use Staff Digest to understand daily admin activity.',
                    'Use Insights to watch marketplace trends.',
                ], ['export', 'digest', 'insights']),
            ], ['dashboard-home', 'audit-trails']),

            $this->topic('troubleshooting', 'Troubleshooting', 'Reference', 'Common admin questions and how to resolve confusing statuses, restrictions, and blocked actions.', 'Documentation', [
                $this->section('Why a Proposal Is Hidden', [
                    'Most likely reason: admin status is Suspended.',
                    'Check the proposal audit trail, flags, and notices before releasing it.',
                ], ['hidden proposal', 'suspended']),
                $this->section('Why a Proposal Cannot Be Accepted', [
                    'Most likely reason: proposal is Restricted, Suspended, or blocked by urgent notice.',
                    'Resolve the blocking issue, change admin status to Clear or Resolved, then retry.',
                ], ['cannot accept', 'restricted', 'notice']),
                $this->section('Why a Freelancer Is Restricted', [
                    'Verification restriction may be active.',
                    'Effective trust level may be too low for the quest value.',
                    'An anomaly flag or admin action may have set a temporary restriction.',
                ], ['freelancer restricted', 'verification restriction']),
                $this->section('How to Resolve Conflicting Statuses', [
                    'Read operational status as the user-facing lifecycle.',
                    'Read admin status as the moderation lifecycle.',
                    'If they seem inconsistent, check audit logs to understand the last admin action.',
                ], ['conflicting statuses', 'dual status']),
            ], ['proposal-management', 'verification-trust', 'audit-trails']),

            $this->topic('glossary', 'Glossary', 'Reference', 'Definitions for common terms, components, statuses, signals, actions, and system behaviours.', 'Documentation', [
                $this->section('Terms', [
                    'Admin status: internal moderation status separate from user-facing lifecycle.',
                    'Operational status: normal business lifecycle status.',
                    'Slide-over: right-side detail panel that keeps the admin on the current page.',
                    'Retrospective moderation: content goes live, then admins review it.',
                    'Regularisation: staff follow-up to help a user correct verification or compliance issues.',
                ], ['terms', 'definitions']),
                $this->section('Components', [
                    'AdminTabs: shared tab navigation for admin pages.',
                    'AdminSlideOver: shared right-side detail panel.',
                    'Intelligence bar: horizontal metrics that also act as quick filters.',
                    'Risk panel: section explaining automated signals and severity.',
                ], ['components', 'admin tabs', 'slide-over']),
                $this->section('Admin Actions', [
                    'Flag: mark a concern for review.',
                    'Restrict: keep visible but block sensitive actions.',
                    'Suspend: hide from users.',
                    'Refer: assign follow-up to another admin or staff member.',
                    'Resolve: close a concern after the issue is handled.',
                ], ['admin actions', 'flag', 'restrict', 'suspend', 'refer']),
            ], ['overview', 'troubleshooting']),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function searchIndex(): array
    {
        return collect($this->topics())->flatMap(function (array $topic): array {
            $rows = [[
                'topic' => $topic['slug'],
                'section' => '',
                'title' => $topic['title'],
                'module' => $topic['module'],
                'body' => $topic['summary'],
                'href' => route('admin.documentation.guide', ['topic' => $topic['slug']]),
            ]];

            foreach ($topic['sections'] as $section) {
                $rows[] = [
                    'topic' => $topic['slug'],
                    'section' => $section['id'],
                    'title' => $section['title'],
                    'module' => $topic['title'],
                    'body' => implode(' ', $section['items']).' '.implode(' ', $section['keywords']),
                    'href' => route('admin.documentation.guide', ['topic' => $topic['slug']]).'#'.$section['id'],
                ];
            }

            return $rows;
        })->values()->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @param  array<int, string>  $related
     * @return array<string, mixed>
     */
    private function topic(string $slug, string $title, string $category, string $summary, string $module, array $sections, array $related = []): array
    {
        return compact('slug', 'title', 'category', 'summary', 'module', 'sections', 'related');
    }

    /**
     * @param  array<int, string>  $items
     * @param  array<int, string>  $keywords
     * @return array<string, mixed>
     */
    private function section(string $title, array $items, array $keywords = []): array
    {
        return [
            'id' => Str::slug($title),
            'title' => $title,
            'items' => $items,
            'keywords' => $keywords,
        ];
    }
}
