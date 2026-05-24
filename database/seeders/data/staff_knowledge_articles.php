<?php

/**
 * Staff (operations) knowledge base articles.
 * Run: php artisan knowledge-base:seed --force
 */
return [
    [
        'slug' => 'staff-console-overview',
        'category' => 'Getting started',
        'title' => 'Operations console — overview and navigation',
        'body' => <<<'HTML'
<h2>Purpose</h2>
<p>The operations console is your daily workspace for trust &amp; safety, customer support, moderation, cases, and people review. Everything is organised in the left sidebar by job type.</p>
<h2>Sidebar sections</h2>
<ul>
<li><strong>Workspace</strong> — personal dashboard, alert centre, tasks, and this knowledge base.</li>
<li><strong>Chat</strong> — live customer support, team chat, and direct messages to other admins.</li>
<li><strong>Moderation</strong> — quest/proposal queues, reviews, patrol, badge requests.</li>
<li><strong>People</strong> — users, verifications (KYC), trust monitoring, quality, onboarding.</li>
<li><strong>Cases</strong> — disputes, escrow anomalies, sanction appeals, payments, payout exceptions.</li>
<li><strong>Insights</strong> — support hub search, communications log, category health.</li>
</ul>
<h2>Daily rhythm (recommended)</h2>
<ol>
<li>Open <strong>Alert centre</strong> — clear critical banners and unread inbox items.</li>
<li>Check <strong>My Tasks</strong> — work referrals, flags, and SLA items assigned to you.</li>
<li>Drain your primary queue (live support, verifications, or disputes depending on role).</li>
<li>Use <strong>Knowledge base</strong> when unsure of policy or escalation path.</li>
<li>Log decisions with a clear reason — audit trails protect you and users.</li>
</ol>
<h2>Principles</h2>
<ul>
<li>User-facing status and admin/moderation status are separate — read both before acting.</li>
<li>When in doubt, escalate to Super Admin rather than guessing on money, permanent bans, or legal risk.</li>
<li>Never share internal notes or admin-only context with customers.</li>
</ul>
HTML,
    ],
    [
        'slug' => 'workspace-dashboard-alerts-tasks',
        'category' => 'Workspace',
        'title' => 'Dashboard, alert centre, and my tasks',
        'body' => <<<'HTML'
<h2>Operations dashboard</h2>
<p>Your home snapshot shows assigned workload, queue counts, and shortcuts. Use it to decide which module needs attention first — it is directional, not the source of record.</p>
<h2>Alert centre — step by step</h2>
<ol>
<li>Go to <strong>Workspace → Alert centre</strong>.</li>
<li>Read <strong>critical banners</strong> at the top first (platform-wide or role-specific).</li>
<li>Filter inbox: unread, critical, or actioned.</li>
<li>Click an alert to open the linked record (quest, proposal, dispute, verification, etc.).</li>
<li>The alert marks read when opened — confirm the underlying issue is actually handled.</li>
<li>Adjust <strong>notification preferences</strong> if you need fewer email/in-app events for non-critical categories.</li>
</ol>
<h2>My tasks — step by step</h2>
<ol>
<li>Go to <strong>Workspace → My Tasks</strong>.</li>
<li>Sort by due date or priority.</li>
<li>Open a task — read subject, linked user/quest/proposal, and referral notes.</li>
<li>Complete the work in the linked module (verification, dispute, moderation, etc.).</li>
<li>Mark task done or update status with a reason when finished.</li>
<li>If blocked, reassign or escalate per <em>Escalating to Super Admin</em> article.</li>
</ol>
<h2>Insights on the dashboard</h2>
<ul>
<li>Rising dispute or verification counts → prioritise Cases or People queues.</li>
<li>Stale live chats → open Live support and clear assigned conversations.</li>
<li>Overdue tasks → resolve or escalate same day where possible.</li>
</ul>
HTML,
    ],
    [
        'slug' => 'common-tasks-index',
        'category' => 'Common tasks',
        'title' => 'Common tasks — quick reference index',
        'body' => <<<'HTML'
<h2>How to use this section</h2>
<p>Search by keyword (e.g. "dispute", "KYC", "fraud") or filter category <strong>Common tasks</strong>. Each linked article has full step-by-step procedures.</p>
<h2>Customer &amp; chat</h2>
<ul>
<li><strong>Live support</strong> — real-time customer chats, assignment, end session, handoff.</li>
<li><strong>Team chat / Direct messages</strong> — internal coordination with admins.</li>
</ul>
<h2>Moderation &amp; marketplace</h2>
<ul>
<li><strong>Dispute management</strong> — evidence, mediation, outcomes.</li>
<li><strong>Quest &amp; proposal investigation</strong> — flag, restrict, suspend, refer.</li>
<li><strong>Reviews management</strong> — remove, uphold, integrity patterns.</li>
<li><strong>Content patrol &amp; badge requests</strong> — proactive sampling and manual badges.</li>
</ul>
<h2>People &amp; trust</h2>
<ul>
<li><strong>KYC / verifications</strong> — document review queue.</li>
<li><strong>Flag account or transaction for fraud/scam</strong> — risk signals and referrals.</li>
<li><strong>User warnings &amp; short suspensions</strong> — 72-hour and restriction patterns.</li>
</ul>
<h2>Escalation</h2>
<ul>
<li><strong>Escalating to Super Admin</strong> — when and how.</li>
<li><strong>Payout exceptions &amp; escrow anomalies</strong> — money movement edge cases.</li>
</ul>
HTML,
    ],
    [
        'slug' => 'live-support-workflow',
        'category' => 'Common tasks',
        'title' => 'Live support — handling customer chats',
        'body' => <<<'HTML'
<h2>Purpose</h2>
<p>Live support lets you chat in real time with clients and freelancers. Chats are assigned to you or sit in the unassigned queue.</p>
<h2>Opening the workspace</h2>
<ol>
<li>Go to <strong>Chat → Live support</strong>.</li>
<li>Use <strong>Live</strong> tab for active/queued sessions; <strong>History</strong> for closed sessions.</li>
<li>Select a conversation from the left list — the thread opens in the main panel.</li>
</ol>
<h2>Handling a chat — step by step</h2>
<ol>
<li>Read the customer profile slide-over (quests, KYC, past support, disputes) before replying.</li>
<li>Greet clearly; confirm the issue in one sentence.</li>
<li>Use <strong>Reply</strong> mode for customer-visible messages; <strong>Internal note</strong> only for staff (never sensitive accusations in notes that could be mis-sent).</li>
<li>Attach files or GIFs when helpful; keep tone professional.</li>
<li>If you need another admin, use <strong>Reassign</strong> (Super Admin) or coordinate in team chat.</li>
<li>When resolved, click <strong>End session</strong> — customer receives feedback prompt; session moves to History.</li>
</ol>
<h2>Assignment &amp; queue</h2>
<ul>
<li><strong>Queued</strong> — waiting for an agent; claim or wait for auto-assignment.</li>
<li><strong>Active</strong> — conversation in progress.</li>
<li><strong>Closed</strong> — no new messages; history only.</li>
</ul>
<h2>Badges &amp; notifications</h2>
<p>While you are on the chat page and reading messages, alerts should clear automatically. If counts look wrong, refresh the page after ending the session.</p>
<h2>Escalate when</h2>
<ul>
<li>Refund, payout, or escrow adjustment is requested.</li>
<li>Legal threat, fraud report, or law enforcement contact.</li>
<li>Account deletion, permanent ban, or tier override needed.</li>
</ul>
HTML,
    ],
    [
        'slug' => 'dispute-management',
        'category' => 'Common tasks',
        'title' => 'Dispute management — mediation workflow',
        'body' => <<<'HTML'
<h2>Purpose</h2>
<p>Disputes are formal conflicts between client and freelancer on an active or completed contract. Outcomes may affect escrow release, refunds, and trust scores.</p>
<h2>Step-by-step</h2>
<ol>
<li>Go to <strong>Cases → Disputes</strong> (or Support hub → dispute filter).</li>
<li>Open the dispute — read reason, parties, quest/contract link, and timeline.</li>
<li>Review <strong>evidence</strong> from both sides (messages, files, deliverables).</li>
<li>Check <strong>escrow state</strong> — funded, frozen, partial release; do not promise payout until policy allows.</li>
<li>Contact parties via approved channels if clarification is needed; document in notes.</li>
<li>Choose outcome path: mutual agreement, partial release, full release to one party, or escalate.</li>
<li>Update dispute status with a <strong>clear written reason</strong> — this is audited.</li>
<li>If money movement is non-standard, open <strong>Payout exceptions</strong> or escalate to Super Admin.</li>
</ol>
<h2>Evidence tips</h2>
<ul>
<li>Compare deliverables to quest requirements and revision history.</li>
<li>Check messaging for scope changes agreed on-platform.</li>
<li>Off-platform payment requests are policy violations — factor into decision.</li>
</ul>
<h2>SLA mindset</h2>
<p>Prioritise disputes near deadline or with frozen escrow affecting both parties. Stalled disputes increase chargeback and reputation risk.</p>
HTML,
    ],
    [
        'slug' => 'kyc-verification-review',
        'category' => 'Common tasks',
        'title' => 'KYC &amp; verifications — document review',
        'body' => <<<'HTML'
<h2>Purpose</h2>
<p>Verifications queue holds BVN, NIN, utility bills, identity documents, and tier upgrades. Your decision affects trust level and marketplace limits.</p>
<h2>Step-by-step</h2>
<ol>
<li>Go to <strong>People → Verifications</strong>.</li>
<li>Filter by type (BVN, NIN, utility, identity) or priority.</li>
<li>Open the submission slide-over — review metadata, selfies, and document images.</li>
<li>Check for tampering, mismatched names, expired IDs, and duplicate accounts (trust monitoring).</li>
<li>Decision: <strong>Verified</strong>, <strong>Unverified</strong> (with reason), or <strong>Flagged</strong> for fraud review.</li>
<li>Enter decision reason — user may see a generic status; internal reason stays in audit.</li>
<li>If regularisation is needed (blur photo, wrong doc type), refer to onboarding assist or send user guidance via support.</li>
</ol>
<h2>When to flag instead of reject</h2>
<ul>
<li>Suspected synthetic identity or stolen documents.</li>
<li>Same document on multiple accounts.</li>
<li>Data conflicts with BVN/NIN providers.</li>
</ul>
<h2>Escalate to Super Admin</h2>
<ul>
<li>Manual tier override or bypass of automated limits.</li>
<li>Whitelist after confirmed false positive on fraud engine.</li>
</ul>
HTML,
    ],
    [
        'slug' => 'escalate-to-super-admin',
        'category' => 'Escalations',
        'title' => 'Escalating to Super Admin',
        'body' => <<<'HTML'
<h2>Always escalate when</h2>
<ul>
<li>Moving escrow funds outside standard dispute outcome.</li>
<li>Permanent account ban or reversing a Super Admin sanction.</li>
<li>Platform-wide bug affecting payments or security.</li>
<li>Legal/law enforcement requests.</li>
<li>Changing verification tier rules or global limits.</li>
<li>Email broadcast or mass user communication.</li>
<li>Any request you are not explicitly trained and authorised to perform.</li>
</ul>
<h2>How to escalate — step by step</h2>
<ol>
<li>Complete your investigation first — gather IDs, links, screenshots, audit references.</li>
<li>Create or update <strong>My Tasks</strong> with summary: what happened, what you tried, what you need.</li>
<li>Use <strong>Team chat</strong> or <strong>Direct message</strong> only for urgent blockers; put formal detail in the task.</li>
<li>Tag priority: financial and safety issues are highest.</li>
<li>Do not tell the user the issue is "fixed" until Super Admin confirms.</li>
</ol>
<h2>Good escalation note template</h2>
<p><em>User ID / Quest ID / Dispute ID — Issue — Actions taken — Policy question — Recommended outcome — Urgency</em></p>
HTML,
    ],
    [
        'slug' => 'flag-fraud-scam-account',
        'category' => 'Common tasks',
        'title' => 'Flagging accounts &amp; transactions for fraud or scam',
        'body' => <<<'HTML'
<h2>Purpose</h2>
<p>Early fraud flags protect users and escrow. Operations can flag users, proposals, quests, and payment patterns; Super Admin handles severe or platform-wide fraud config.</p>
<h2>Account flag — step by step</h2>
<ol>
<li>Go to <strong>People → Users</strong> and open the user slide-over.</li>
<li>Review activity, verification state, disputes, proposals, and trust monitoring hits.</li>
<li>From moderation context or user panel, <strong>flag</strong> with type and priority (low → critical).</li>
<li>Add reason: e.g. off-platform payment, duplicate identity, chargeback pattern.</li>
<li>Apply <strong>restriction</strong> or 72-hour suspension if immediate harm reduction is needed (within your authority).</li>
<li>Refer to Super Admin if permanent ban or financial freeze is required.</li>
</ol>
<h2>Transaction / escrow concerns</h2>
<ol>
<li>Open related quest/contract from user or <strong>Cases → Escrow anomalies</strong>.</li>
<li>Document anomaly (stalled release, suspicious funding source, mismatch).</li>
<li>Do not manually release funds — escalate with Financial context.</li>
</ol>
<h2>Trust monitoring</h2>
<p>Use <strong>People → Trust monitoring</strong> for clusters, watchlists, and linked accounts. Add watchlist items when you see repeat patterns.</p>
<h2>Evidence</h2>
<p>Save message IDs, proposal text, and payment references in task notes. Fraud decisions without evidence do not survive audit.</p>
HTML,
    ],
    [
        'slug' => 'quest-proposal-investigation',
        'category' => 'Common tasks',
        'title' => 'Quest &amp; proposal investigation and flagging',
        'body' => <<<'HTML'
<h2>Purpose</h2>
<p>Moderation centre and patrol let you review marketplace listings and bids after they go live (retrospective moderation).</p>
<h2>Proposal review — step by step</h2>
<ol>
<li>Go to <strong>Moderation → Moderation centre</strong> (Proposals tab) or open from alert/task link.</li>
<li>Read proposal text, price, timeline, and risk panel signals (off-platform contact, lowball, velocity).</li>
<li>Open freelancer profile and quest context.</li>
<li>Actions: <strong>Clear</strong>, <strong>Flag</strong>, <strong>Under review</strong>, <strong>Restrict</strong> (visible, not acceptable), <strong>Suspend</strong> (hidden).</li>
<li>Post <strong>notice</strong> to user if they must edit content.</li>
<li>Refer to another staff member if specialised (KYC, dispute) review is needed.</li>
</ol>
<h2>Quest review — step by step</h2>
<ol>
<li>Open quest from Moderation centre (Quests tab) or Support hub.</li>
<li>Check budget realism, category, client history, and escrow readiness.</li>
<li>Flag misleading scope, prohibited services, or contact details in description.</li>
<li>Feature/unfeature only if policy allows and you have permission.</li>
</ol>
<h2>Content patrol</h2>
<p><strong>Moderation → Content patrol</strong> provides sampled quests/proposals for proactive review — use for trend spotting, not only reactive flags.</p>
<h2>Statuses reminder</h2>
<p><strong>Operational status</strong> = business lifecycle. <strong>Admin status</strong> = moderation state. Both appear in UI — do not confuse them.</p>
HTML,
    ],
    [
        'slug' => 'reviews-management',
        'category' => 'Common tasks',
        'title' => 'Reviews management &amp; review integrity',
        'body' => <<<'HTML'
<h2>Reviews queue</h2>
<ol>
<li>Go to <strong>Moderation → Reviews</strong>.</li>
<li>Filter by reported, low rating burst, or keyword hits.</li>
<li>Read review text, contract context, and both parties' history.</li>
<li>Actions: uphold, hide/remove (policy violation), warn author, or refer to dispute if contractual.</li>
<li>Document reason — removed reviews are sensitive for trust scores.</li>
</ol>
<h2>Review integrity</h2>
<ol>
<li>Go to <strong>Moderation → Review integrity</strong>.</li>
<li>Investigate ring patterns: reciprocal 5-star, same IP cluster, new accounts reviewing each other.</li>
<li>Coordinate with Trust monitoring; escalate coordinated fraud to Super Admin.</li>
</ol>
<h2>When to remove a review</h2>
<ul>
<li>Hate, threats, or private data.</li>
<li>Clearly about wrong contract or not a genuine transaction.</li>
<li>Extortion ("change rating or else").</li>
</ul>
<p>Do not remove solely because a party disagrees with fairness — that belongs in disputes.</p>
HTML,
    ],
    [
        'slug' => 'team-chat-direct-messages',
        'category' => 'Chat',
        'title' => 'Team chat and direct messages',
        'body' => <<<'HTML'
<h2>Team chat</h2>
<p>Group channel for operations and admins. Use for shift handoffs, policy questions, and non-urgent coordination.</p>
<ol>
<li>Open <strong>Chat → Team chat</strong>.</li>
<li>Keep operational detail in threads; avoid PII dumps where unnecessary.</li>
<li>@mention or name the next shift lead for open incidents.</li>
</ol>
<h2>Direct messages</h2>
<p>1:1 private chat with Super Admin or other staff.</p>
<ol>
<li>Open via sidebar <strong>Direct messages</strong> (messenger panel).</li>
<li>Use for sensitive previews before formal escalation.</li>
<li>Do not replace task audit trail — confirm decisions in My Tasks when action is taken.</li>
</ol>
<h2>Etiquette</h2>
<ul>
<li>Urgent money/safety → task + DM, not DM alone.</li>
<li>Never share customer passwords or full document numbers in chat.</li>
</ul>
HTML,
    ],
    [
        'slug' => 'moderation-centre-deep-dive',
        'category' => 'Moderation',
        'title' => 'Moderation centre — tabs, filters, and actions',
        'body' => <<<'HTML'
<h2>Layout</h2>
<p>Tabbed interface for <strong>Proposals</strong> and <strong>Quests</strong> with intelligence bar metrics (click metrics to filter).</p>
<h2>Effective filtering</h2>
<ul>
<li>Admin status: flagged, under review, restricted.</li>
<li>Risk signals: off-platform, velocity, new account high value.</li>
<li>Date and category for campaign reviews.</li>
</ul>
<h2>Slide-over panels</h2>
<p>Detail opens on the right — review risk, audit, flags, notices, communications without losing list position.</p>
<h2>Bulk actions</h2>
<p>Only when every selected row shares the same issue and resolution. Always enter a shared reason.</p>
HTML,
    ],
    [
        'slug' => 'users-warnings-suspensions',
        'category' => 'People',
        'title' => 'Users — warnings, restrictions, and suspensions',
        'body' => <<<'HTML'
<h2>Open user context</h2>
<ol>
<li><strong>People → Users</strong> — search email, username, or name.</li>
<li>Slide-over: overview, verification, sanctions, notes, activity.</li>
</ol>
<h2>72-hour suspension (operations authority)</h2>
<ol>
<li>Confirm policy allows for the violation (spam, harassment, repeat off-platform contact).</li>
<li>Apply suspension with reason; user sees limited access message.</li>
<li>Create task for follow-up before expiry if permanent action may be needed.</li>
</ol>
<h2>Warnings &amp; notices</h2>
<p>Post user-visible notice when behaviour must change before restriction. Urgent notices can block proposal acceptance.</p>
<h2>Sanction appeals</h2>
<p>If user appeals, route to <strong>Cases → Sanction appeals</strong> — read original sanction audit before overturning.</p>
HTML,
    ],
    [
        'slug' => 'trust-quality-onboarding',
        'category' => 'People',
        'title' => 'Trust monitoring, freelancer quality, onboarding assist',
        'body' => <<<'HTML'
<h2>Trust monitoring</h2>
<p>Watchlists and clusters of linked accounts. Add items when you see duplicate phones, devices, or payment instruments across users.</p>
<h2>Freelancer quality</h2>
<p>Performance trends, completion rates, dispute rates. Use for coaching flags — not automatic punishment without review.</p>
<h2>Onboarding assist</h2>
<ol>
<li><strong>People → Onboarding assist</strong> — users stuck in signup, verification, or first quest.</li>
<li>Outreach with clear next step (complete BVN, fix portfolio, etc.).</li>
<li>Close assist record when user progresses or escalates.</li>
</ol>
HTML,
    ],
    [
        'slug' => 'escrow-anomalies-payments',
        'category' => 'Cases',
        'title' => 'Escrow anomalies, payments, and payout exceptions',
        'body' => <<<'HTML'
<h2>Escrow anomalies</h2>
<p>Contracts with stalled milestones, funding mismatches, or pre-dispute tension.</p>
<ol>
<li><strong>Cases → Escrow anomalies</strong>.</li>
<li>Compare contract timeline, deliverables, and escrow ledger.</li>
<li>Nudge parties to dispute path if conflict is formal; otherwise coordinate milestone updates.</li>
<li>Escalate frozen or mis-allocated funds.</li>
</ol>
<h2>Payments (limited view)</h2>
<p>Operations sees support-oriented payment context — not full treasury. Use for status checks and user guidance.</p>
<h2>Payout exceptions</h2>
<ol>
<li><strong>Cases → Payout exceptions</strong> for failed payouts, bank rejects, manual retry requests.</li>
<li>Verify identity and dispute state before supporting retry.</li>
<li>Super Admin executes actual treasury movement.</li>
</ol>
HTML,
    ],
    [
        'slug' => 'sanction-appeals',
        'category' => 'Cases',
        'title' => 'Sanction appeals process',
        'body' => <<<'HTML'
<ol>
<li>Open <strong>Cases → Sanction appeals</strong>.</li>
<li>Read original sanction in audit log — who, when, why.</li>
<li>Compare user's new evidence against original reason.</li>
<li>Outcomes: uphold, reduce sanction, or escalate to Super Admin for full reinstatement.</li>
<li>Reply via support if user is waiting — do not leave appeals silent past SLA.</li>
</ol>
HTML,
    ],
    [
        'slug' => 'badge-requests-content-patrol',
        'category' => 'Moderation',
        'title' => 'Badge requests and content patrol',
        'body' => <<<'HTML'
<h2>Badge requests</h2>
<ol>
<li><strong>Moderation → Badge requests</strong> — Top Rated, talent badges, etc.</li>
<li>Verify metrics eligibility (reviews, completion, disputes).</li>
<li>Approve or deny with reason; denied users may reapply after meeting criteria.</li>
</ol>
<h2>Content patrol</h2>
<ol>
<li><strong>Moderation → Content patrol</strong> — sampled listings.</li>
<li>Treat like moderation centre: flag, restrict, suspend with documented reason.</li>
<li>Patrol is proactive quality — schedule regular sessions per team rota.</li>
</ol>
HTML,
    ],
    [
        'slug' => 'insights-support-hub',
        'category' => 'Insights',
        'title' => 'Support hub, communications log, category health',
        'body' => <<<'HTML'
<h2>Support hub</h2>
<p>Global search across users, quests, tickets, disputes. Use when alert links are missing or customer cites multiple IDs.</p>
<ol>
<li><strong>Insights → Support hub</strong>.</li>
<li>Search user email, quest code, ticket ID.</li>
<li>Jump to authoritative module from result type.</li>
</ol>
<h2>Communications log</h2>
<p>Read-only view of banners, mass emails, scheduled sends — helps explain what users saw.</p>
<h2>Category health</h2>
<p>Volume, fill rates, dispute rates per category — use for patrol prioritisation and outreach to ops leadership.</p>
HTML,
    ],
    [
        'slug' => 'knowledge-base-for-staff',
        'category' => 'Getting started',
        'title' => 'Using this knowledge base',
        'body' => <<<'HTML'
<h2>Search tips</h2>
<ul>
<li>Use specific words: "dispute", "BVN", "escalate", "restrict", "live support".</li>
<li>Filter by category to narrow results.</li>
</ul>
<h2>Suggest updates</h2>
<p>When policy changes or a gap exists, open any article and submit a <strong>suggestion</strong> at the bottom. Super Admins publish updates via Knowledge base (admin).</p>
<h2>Dashboard guide (Super Admin only)</h2>
<p>Super Admins have a separate in-app <strong>Dashboard Guide</strong> for platform-wide engines. Staff procedures live here.</p>
HTML,
    ],
];
