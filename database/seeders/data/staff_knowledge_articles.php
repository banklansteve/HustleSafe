<?php

/**
 * Staff (operations) knowledge base — internal ops bible.
 * Run: php artisan knowledge-base:seed --force
 *
 * Categories align with StaffRoleGroup labels:
 * - Group A — Chat & Communications
 * - Group B — Moderation Operations
 * - Group C — People & Trust
 * - Group D — Financial & Disputes
 * - Escalations (cross-cutting)
 */
return [
    [
        'slug' => 'staff-console-overview',
        'category' => 'Getting started',
        'title' => 'Operations console — overview and navigation',
        'body' => <<<'HTML'
<h2>Purpose</h2>
<p>The operations console is your daily workspace. Procedures are organised by role group in this knowledge base.</p>
<h2>Role groups</h2>
<ul>
<li><strong>Group A — Chat &amp; Communications</strong> — live support, team chat, flagged conversations.</li>
<li><strong>Group B — Moderation Operations</strong> — quests, proposals, reviews, patrol, badges.</li>
<li><strong>Group C — People &amp; Trust</strong> — users, KYC, trust monitoring, warnings.</li>
<li><strong>Group D — Financial &amp; Disputes</strong> — disputes, escrow anomalies, payment monitoring, payouts.</li>
<li><strong>Escalations</strong> — when to involve Super Admin and the escalation matrix.</li>
</ul>
<h2>Daily rhythm</h2>
<ol>
<li>Clear <strong>Alert centre</strong> critical items.</li>
<li>Work <strong>My Tasks</strong> by due date.</li>
<li>Drain your primary queue for your role group.</li>
<li>Use the <strong>Admin Escalation Matrix</strong> before making authority decisions.</li>
</ol>
HTML,
    ],
    [
        'slug' => 'admin-escalation-matrix',
        'category' => 'Escalations',
        'title' => 'Admin Escalation Matrix — when, to whom, and how',
        'body' => <<<'HTML'
<h2>Purpose</h2>
<p>Single reference for: <em>Do I handle this, flag Super Admin, open compliance, or declare an incident?</em></p>
<h2>Action key</h2>
<ul>
<li><strong>Handle</strong> — you may resolve within operations authority.</li>
<li><strong>Flag SA</strong> — create task + escalate to Super Admin; do not promise outcome.</li>
<li><strong>Compliance</strong> — legal/law-enforcement/data-subject request; SA + compliance lead.</li>
<li><strong>Incident</strong> — platform-wide payment/security failure; SA immediately + pause public comms.</li>
</ul>
<h2>Matrix by scenario</h2>
<table>
<thead><tr><th>Scenario</th><th>First responder</th><th>Action</th><th>SLA to escalate</th></tr></thead>
<tbody>
<tr><td>Live chat — account access, how-to</td><td>Group A</td><td>Handle</td><td>—</td></tr>
<tr><td>Live chat — refund / escrow movement request</td><td>Group A</td><td>Flag SA</td><td>Same shift</td></tr>
<tr><td>Flagged conversation — off-platform contact (1st)</td><td>Group A / B</td><td>Handle (warn)</td><td>—</td></tr>
<tr><td>Flagged conversation — repeat / smurfing pattern</td><td>Group B</td><td>Flag SA</td><td>4 business hours</td></tr>
<tr><td>Proposal/quest — misleading scope</td><td>Group B</td><td>Handle (restrict)</td><td>—</td></tr>
<tr><td>Proposal/quest — prohibited service / fraud ring</td><td>Group B</td><td>Flag SA</td><td>Same day</td></tr>
<tr><td>KYC — blurry doc, wrong type</td><td>Group C</td><td>Handle (reject + guide)</td><td>—</td></tr>
<tr><td>KYC — stolen identity / duplicate doc</td><td>Group C</td><td>Flag SA</td><td>4 business hours</td></tr>
<tr><td>Trust score spike — review queue</td><td>Group C</td><td>Handle (watchlist)</td><td>—</td></tr>
<tr><td>Trust score — coordinated fraud cluster</td><td>Group C</td><td>Flag SA</td><td>Same day</td></tr>
<tr><td>Dispute — self-resolution stage</td><td>Group D</td><td>Handle (mediate)</td><td>—</td></tr>
<tr><td>Dispute — formal ruling needed</td><td>Group D</td><td>Handle (staff review)</td><td>72h formal window</td></tr>
<tr><td>Dispute — non-standard escrow split</td><td>Group D</td><td>Flag SA</td><td>Before executing</td></tr>
<tr><td>Escrow anomaly — stale client review</td><td>Group D</td><td>Handle (outreach)</td><td>—</td></tr>
<tr><td>Escrow anomaly — funds stuck / wrong amount</td><td>Group D</td><td>Flag SA</td><td>Same shift</td></tr>
<tr><td>Payment anomaly queue — velocity / smurfing</td><td>Group D</td><td>Handle (flag review)</td><td>—</td></tr>
<tr><td>Payment anomaly — confirmed fraud</td><td>Group D</td><td>Flag SA</td><td>2 business hours</td></tr>
<tr><td>Contract in disputed state — user asks status</td><td>Group A / D</td><td>Handle (explain process)</td><td>—</td></tr>
<tr><td>Permanent ban / reinstatement</td><td>Any</td><td>Flag SA</td><td>Never decide alone</td></tr>
<tr><td>Manual escrow release / refund</td><td>Any</td><td>Flag SA</td><td>Super Admin executes</td></tr>
<tr><td>Law enforcement / NDPR data request</td><td>Any</td><td>Compliance</td><td>Immediate</td></tr>
<tr><td>Paystack outage / mass failed payouts</td><td>Any</td><td>Incident</td><td>Immediate</td></tr>
</tbody>
</table>
<h2>How to escalate</h2>
<ol>
<li>Finish fact-gathering — IDs, links, screenshots, audit references.</li>
<li>Create <strong>My Tasks</strong> with template: User/Quest/Dispute ID · Issue · Actions taken · Needed decision · Urgency.</li>
<li>For money/safety: also use <strong>Operations → Escalations</strong> or team chat for urgent visibility.</li>
<li>Do not tell the user it is resolved until Super Admin confirms.</li>
</ol>
<p>See also: <em>Escalating to Super Admin</em>.</p>
HTML,
    ],
    [
        'slug' => 'escalate-to-super-admin',
        'category' => 'Escalations',
        'title' => 'Escalating to Super Admin — procedure',
        'body' => <<<'HTML'
<h2>Always escalate (never decide alone)</h2>
<ul>
<li>Moving escrow outside standard dispute outcome or staff authority.</li>
<li>Permanent ban, full reinstatement, or reversing Super Admin sanction.</li>
<li>Platform-wide bug affecting payments or security.</li>
<li>Legal / law enforcement / NDPR data-subject requests.</li>
<li>Global limit or verification tier rule changes.</li>
<li>Mass email or broadcast communication.</li>
</ul>
<h2>Steps</h2>
<ol>
<li>Read the <strong>Admin Escalation Matrix</strong> for your scenario type.</li>
<li>Document: user ID, quest/contract ref, dispute ID, payment ref, timestamps.</li>
<li>Create task with recommended outcome and urgency.</li>
<li>For financial urgency: flag in Payment Monitoring or Escrow Anomalies with cross-link to task.</li>
<li>Monitor task until closed; update user only when SA confirms.</li>
</ol>
<h2>Note template</h2>
<p><em>[ID] — Issue — Actions taken — Policy question — Recommended outcome — Urgency (low/med/high/critical)</em></p>
HTML,
    ],
    [
        'slug' => 'dispute-resolution-end-to-end',
        'category' => 'Group D — Financial & Disputes',
        'title' => 'Dispute resolution — end-to-end process guide',
        'body' => <<<'HTML'
<h2>1. What triggers this process</h2>
<ul>
<li>Client or freelancer opens a dispute on a funded quest (min ₦5,000 contract value).</li>
<li>Case auto-escalates when self-resolution timers expire.</li>
<li>Either party requests staff review after failed settlement talks.</li>
</ul>
<h2>2. What you see in your queue</h2>
<p><strong>Cases → Disputes</strong> — reason, stage, countdown timers, escrow state, evidence uploads, settlement offers.</p>
<h2>3. Steps (in order)</h2>
<ol>
<li>Open dispute; confirm quest, contract ref, parties, and escrow is not already released.</li>
<li>Read structured intake: reason category, narrative, checklist answers.</li>
<li>Review evidence: contract PDF, deliverables, quest thread, clarification history, extension tags.</li>
<li>Check stage: <strong>Self-resolution (48h)</strong> vs <strong>Formal review (72h evidence window)</strong>.</li>
<li>During self-resolution: nudge both parties to settle on-platform; do not side publicly.</li>
<li>If settlement reached: confirm recorded split; verify escrow can execute per Dispute Policy.</li>
<li>If escalated: request final evidence summary; compare to contract milestones and revision policy.</li>
<li>Apply outcome: full release, full refund, partial split, or return to self-resolution.</li>
<li>Update dispute status with written ruling reason (audited).</li>
<li>If money movement is non-standard → escalate Super Admin before execution.</li>
</ol>
<h2>4. Decision criteria</h2>
<ul>
<li><strong>Release to freelancer</strong> — deliverables match contract; client unresponsive after reminders; no material breach.</li>
<li><strong>Refund client</strong> — no delivery, material breach, or scope never agreed on-platform.</li>
<li><strong>Partial split</strong> — documented partial delivery or mutual settlement offer accepted.</li>
<li><strong>Decline / close</strong> — duplicate dispute, below minimum amount, or outside 14-day post-completion window without new evidence.</li>
</ul>
<h2>5. SLA</h2>
<ul>
<li>Self-resolution response: <strong>48 hours</strong> per party (timer shown in UI).</li>
<li>Formal evidence summary: <strong>72 hours</strong> when escalated.</li>
<li>Staff first touch on escalated cases: <strong>within 1 business day</strong>.</li>
</ul>
<h2>6. Escalate to Super Admin when</h2>
<ul>
<li>Manual escrow override needed outside dispute settlement engine.</li>
<li>High-value contract without release authorization.</li>
<li>Legal threat or fraud allegation with platform liability.</li>
</ul>
<h2>7. Log before closing</h2>
<ul>
<li>Final outcome code and plain-English ruling summary.</li>
<li>Evidence list reviewed (with dates).</li>
<li>Escrow action taken (or SA ticket ref if pending).</li>
<li>Whether resolution fee (~2%) applies.</li>
</ul>
HTML,
    ],
    [
        'slug' => 'disputed-contract-handling',
        'category' => 'Group D — Financial & Disputes',
        'title' => 'What to do when a contract is in disputed state',
        'body' => <<<'HTML'
<h2>1. Triggers</h2>
<ul>
<li>Contract status shows <strong>disputed</strong>; quest <code>dispute_opened</code> is true.</li>
<li>User asks why funds are frozen or work is paused.</li>
<li>Alert/task links to active dispute on a contract.</li>
</ul>
<h2>2. Queue / where to look</h2>
<ul>
<li><strong>Cases → Disputes</strong> — authoritative case record.</li>
<li>Contract page — timeline shows disputed stage; escrow banner shows frozen messaging.</li>
<li>Quest thread — dispute messages and settlement offers.</li>
</ul>
<h2>3. Steps</h2>
<ol>
<li>Confirm one open dispute per quest — no duplicate cases.</li>
<li>Explain to users (if contacted): escrow is protected; auto-release is paused; both parties see the same dispute thread.</li>
<li>Do <strong>not</strong> tell either party funds will release on a date unless dispute outcome says so.</li>
<li>Review whether self-resolution timer is running — remind non-responding party via approved comms.</li>
<li>If deliverables still arriving during dispute — note in case file; do not instruct off-platform payment.</li>
<li>Coordinate with assigned dispute handler; avoid contradictory messages.</li>
<li>When dispute closes — verify contract status and escrow updated by system before telling users "done".</li>
</ol>
<h2>4. Decision criteria</h2>
<ul>
<li>Support questions → explain process; link Dispute Policy if user-facing.</li>
<li>Pressure to release/refund → refer to dispute stage; escalate if user demands override.</li>
</ul>
<h2>5. SLA</h2>
<p>User status inquiries: respond within <strong>4 business hours</strong>. Do not resolve the dispute itself unless you own the case.</p>
<h2>6. Escalate when</h2>
<ul>
<li>User requests Super Admin override or threatens chargeback/litigation.</li>
<li>Escrow appears released while dispute still open (system inconsistency).</li>
</ul>
<h2>7. Log before closing contact</h2>
<p>Dispute ID, what you told each party, next expected action date, handoff to case owner if applicable.</p>
HTML,
    ],
    [
        'slug' => 'payment-anomaly-queue',
        'category' => 'Group D — Financial & Disputes',
        'title' => 'Payment anomaly queue — review process',
        'body' => <<<'HTML'
<h2>1. Triggers</h2>
<ul>
<li>Automated detection: smurfing, rapid releases, payout velocity spikes, market-rate outliers, escrow funding patterns.</li>
<li>Item appears in <strong>Cases → Payment monitoring</strong>.</li>
</ul>
<h2>2. What you see</h2>
<p>Anomaly type, fingerprint, severity, linked user/quest, amount, detection reason, prior flags.</p>
<h2>3. Steps</h2>
<ol>
<li>Open <strong>Operations → Payment monitoring</strong>.</li>
<li>Sort by severity; read detection reason and linked quest/contract.</li>
<li>Open user profiles — verification, dispute history, trust score, prior flags.</li>
<li>Compare to normal behaviour for account age and tier.</li>
<li>If benign (e.g. legitimate high-value client): document and dismiss with reason.</li>
<li>If suspicious: raise <strong>Payment review flag</strong> to Super Admin financial queue.</li>
<li>If active fraud: escalate immediately; consider user restriction per matrix.</li>
<li>Do not execute payouts, refunds, or escrow release from this screen.</li>
</ol>
<h2>4. Decision criteria</h2>
<ul>
<li><strong>Dismiss</strong> — false positive with documented rationale.</li>
<li><strong>Flag for review</strong> — needs treasury/SA eyes but no immediate harm.</li>
<li><strong>Escalate critical</strong> — smurfing, stolen card pattern, collusion ring.</li>
</ul>
<h2>5. SLA</h2>
<ul>
<li>Critical severity: <strong>2 business hours</strong> first review.</li>
<li>Standard: <strong>1 business day</strong>.</li>
</ul>
<h2>6. Escalate to Super Admin when</h2>
<ul>
<li>Any confirmed fraud or requested fund movement.</li>
<li>Repeat fingerprint after prior SA decision.</li>
</ul>
<h2>7. Log before closing</h2>
<p>Anomaly fingerprint, decision, user/quest IDs, flag ID if raised, staff notes.</p>
HTML,
    ],
    [
        'slug' => 'raise-payment-review-flag',
        'category' => 'Group D — Financial & Disputes',
        'title' => 'How to raise a payment review flag',
        'body' => <<<'HTML'
<h2>1. Triggers</h2>
<ul>
<li>Payment monitoring anomaly needs Super Admin financial review.</li>
<li>Escrow pattern suspicious but not yet a formal dispute.</li>
<li>User report of double charge / wrong funding amount.</li>
</ul>
<h2>2. Where</h2>
<p><strong>Operations → Payment monitoring</strong> — select anomaly → Raise flag. Or from investigation notes referencing fingerprint.</p>
<h2>3. Steps</h2>
<ol>
<li>Confirm anomaly fingerprint is unique — duplicate pending flags are rejected.</li>
<li>Gather Paystack reference, quest ID, contract ref, amounts, timestamps.</li>
<li>Select anomaly type matching detection (smurfing, velocity, outlier, etc.).</li>
<li>Write summary: what happened, why it needs SA review, recommended action.</li>
<li>Submit flag — appears in Super Admin financial review queue.</li>
<li>Create linked My Task if urgent.</li>
<li>Tell user (if applicable): case logged, finance team reviewing — no promise of refund timeline.</li>
</ol>
<h2>4. Decision criteria</h2>
<ul>
<li>Flag when SA must decide on hold/release/refund — not for routine dispute path.</li>
<li>Do not flag for issues resolved via standard dispute workflow.</li>
</ul>
<h2>5. SLA</h2>
<p>Submit flag within <strong>same shift</strong> of discovery for high-severity items.</p>
<h2>6. Escalate further when</h2>
<p>User threatening chargeback or legal action while flag pending → Compliance path in matrix.</p>
<h2>7. Log</h2>
<p>Flag ID, fingerprint, quest/user IDs, customer comms summary.</p>
HTML,
    ],
    [
        'slug' => 'escrow-anomaly-queue',
        'category' => 'Group D — Financial & Disputes',
        'title' => 'Escrow anomaly queue — handling guide',
        'body' => <<<'HTML'
<h2>1. Triggers</h2>
<ul>
<li><strong>Funded no start</strong> — escrow funded past scheduled start, no progress.</li>
<li><strong>Client review stale</strong> — delivery past due, client silent, auto-release approaching.</li>
<li><strong>Long frozen</strong> — escrow frozen/disputed extended period.</li>
<li><strong>Overdue milestones</strong> — proxy signals from contract timeline.</li>
</ul>
<h2>2. Queue</h2>
<p><strong>Cases → Escrow anomalies</strong> — severity score, type label, quest ref, parties.</p>
<h2>3. Steps</h2>
<ol>
<li>Triage by severity score descending.</li>
<li>Open quest + contract; read milestone/delivery dates and extension history.</li>
<li>Record outreach note in anomaly panel — who you contacted and outcome.</li>
<li>For stale client review: nudge client to approve or open dispute before auto-release (72h after agreed delivery).</li>
<li>For funded no start: contact freelancer and client; confirm work should begin.</li>
<li>If conflict formalized → direct parties to dispute; link dispute ID in note.</li>
<li>If funds appear mis-allocated → escalate Super Admin; do not manual release.</li>
<li>Mark anomaly note resolved when situation stable or handed off.</li>
</ol>
<h2>4. Decision criteria</h2>
<ul>
<li><strong>Outreach only</strong> — parties responsive, no policy breach.</li>
<li><strong>Dispute path</strong> — material disagreement on delivery/payment.</li>
<li><strong>SA escalation</strong> — system error, wrong amount, release failed.</li>
</ul>
<h2>5. SLA</h2>
<p>High severity: first outreach <strong>1 business day</strong>. Medium: <strong>2 business days</strong>.</p>
<h2>6. Escalate when</h2>
<p>Manual escrow movement needed — Super Admin uses Financial Control Centre or contract escrow override.</p>
<h2>7. Log</h2>
<p>Anomaly type, outreach summary, next follow-up date, dispute/SA ticket refs.</p>
HTML,
    ],
    [
        'slug' => 'payout-exceptions',
        'category' => 'Group D — Financial & Disputes',
        'title' => 'Payout exceptions — failed bank payouts',
        'body' => <<<'HTML'
<h2>1. Triggers</h2>
<ul>
<li>Freelancer payout failed / bank rejected.</li>
<li>User requests manual retry after fixing bank details.</li>
</ul>
<h2>2. Queue</h2>
<p><strong>Cases → Payout exceptions</strong></p>
<h2>3. Steps</h2>
<ol>
<li>Verify identity matches wallet owner; check open disputes on active quests.</li>
<li>Confirm bank details updated in profile.</li>
<li>If clean: document and escalate retry to Super Admin treasury.</li>
<li>If dispute open on related quest: hold until dispute outcome.</li>
<li>Update user with realistic Paystack processing timeline.</li>
</ol>
<h2>4. SLA</h2>
<p>First response <strong>1 business day</strong>. Execution is Super Admin.</p>
<h2>5. Log</h2>
<p>User ID, payout ref, failure reason, SA task ID.</p>
HTML,
    ],
    [
        'slug' => 'read-risk-score',
        'category' => 'Group C — People & Trust',
        'title' => 'How to read a risk score and trust monitoring queue',
        'body' => <<<'HTML'
<h2>1. Triggers</h2>
<ul>
<li>User appears in <strong>People → Trust monitoring</strong> queue.</li>
<li>Risk score change feed alert.</li>
<li>Moderation or payment panel shows elevated composite score.</li>
</ul>
<h2>2. What you see</h2>
<p>Composite score, component signals (velocity, disputes lost, flags, device clusters), watchlist status, linked accounts.</p>
<h2>3. Steps</h2>
<ol>
<li>Open user slide-over from queue entry.</li>
<li>Review score <strong>components</strong> — not just the headline number.</li>
<li>Check recent disputes, moderation flags, conversation flags, payment anomalies.</li>
<li>Compare account age, verification tier, and transaction history.</li>
<li>Low score + verified + clean history → likely false positive; document and clear watchlist if added in error.</li>
<li>High score + multiple independent signals → add watchlist note; consider restriction per policy.</li>
<li>Coordinated cluster (same device/IP/payment) → escalate Super Admin with linked user IDs.</li>
</ol>
<h2>4. Decision criteria</h2>
<ul>
<li><strong>Monitor</strong> — elevated but no actionable violation yet.</li>
<li><strong>Restrict / 72h suspend</strong> — clear policy breach with evidence.</li>
<li><strong>Escalate</strong> — fraud ring, stolen identity, platform loss risk.</li>
</ul>
<h2>5. SLA</h2>
<p>Queue items: first review <strong>2 business days</strong>. Critical alerts: <strong>same day</strong>.</p>
<h2>6. Escalate when</h2>
<p>Permanent ban, financial freeze, or whitelist override needed.</p>
<h2>7. Log</h2>
<p>Score at review, signals considered, action taken, linked accounts noted.</p>
HTML,
    ],
    [
        'slug' => 'flagged-conversation-review',
        'category' => 'Group A — Chat & Communications',
        'title' => 'Flagged conversation review — step-by-step',
        'body' => <<<'HTML'
<h2>1. Triggers</h2>
<ul>
<li>Automated flag: off-platform contact, payment solicitation, harassment keywords.</li>
<li>Item in <strong>Moderation → Conversation monitoring</strong> queue (also shown on Moderation dashboard banner).</li>
</ul>
<h2>2. Queue</h2>
<p>Flagged message, quest/proposal context, author, flag reason, prior flags on user.</p>
<h2>3. Steps</h2>
<ol>
<li>Open review from moderation queue — read full thread context, not isolated message.</li>
<li>Confirm flag type matches content (phone/email/social, payment off-platform, abuse).</li>
<li>Check user's flag count against suspend/ban thresholds in platform settings.</li>
<li>First minor offence → user-visible warning via notice workflow if applicable.</li>
<li>Repeat offence → apply restriction or 72h suspension within ops authority.</li>
<li>Update review status: upheld, dismissed false positive, or escalated.</li>
<li>If smurfing or fraud pattern across threads → escalate Super Admin.</li>
</ol>
<h2>4. Decision criteria</h2>
<ul>
<li><strong>Dismiss</strong> — false positive (e.g. legitimate business email in portfolio field).</li>
<li><strong>Upheld + warn</strong> — clear policy breach, first time.</li>
<li><strong>Upheld + restrict/suspend</strong> — repeat or severe (threats, scam).</li>
</ul>
<h2>5. SLA</h2>
<p>New flags: <strong>4 business hours</strong> first review. Repeat offenders: <strong>same day</strong>.</p>
<h2>6. Escalate when</h2>
<ul>
<li>Permanent ban threshold reached (ban action is Super Admin only).</li>
<li>Coordinated off-platform payment ring.</li>
<li>User mentions legal action regarding moderation.</li>
</ul>
<h2>7. Log</h2>
<p>Review ID, message ID, decision, user flag count after action, notice ID if sent.</p>
HTML,
    ],
    [
        'slug' => 'conversation-monitoring-escalation',
        'category' => 'Group A — Chat & Communications',
        'title' => 'When to escalate a flagged conversation',
        'body' => <<<'HTML'
<h2>Escalate to Super Admin when ANY apply</h2>
<ol>
<li>User at or above <strong>ban flag threshold</strong> — permanent ban requires SA.</li>
<li>Evidence of fraud ring linking multiple accounts in conversation metadata.</li>
<li>Message contains child safety, terrorism, or imminent violence — immediate SA + incident path.</li>
<li>User is staff impersonation or platform phishing.</li>
<li>Conversation tied to active high-value escrow dispute needing message as evidence for SA ruling.</li>
</ol>
<h2>Steps to escalate</h2>
<ol>
<li>Do not delete thread — preserve evidence.</li>
<li>Apply interim restriction if ongoing harm.</li>
<li>Create task with conversation review ID and quest ref.</li>
<li>Use conversation monitoring escalate action if available — notifies Super Admin.</li>
</ol>
<h2>SLA</h2>
<p>Safety-critical: <strong>immediate</strong>. Ban threshold: <strong>within 2 business hours</strong>.</p>
HTML,
    ],
    [
        'slug' => 'live-support-workflow',
        'category' => 'Group A — Chat & Communications',
        'title' => 'Live support — handling customer chats',
        'body' => <<<'HTML'
<h2>1. Triggers</h2>
<p>Customer opens live chat from help bubble or support entry point.</p>
<h2>2. Queue</h2>
<p><strong>Chat → Live support</strong> — Queued / Active / History tabs.</p>
<h2>3. Steps</h2>
<ol>
<li>Read customer profile slide-over (quests, KYC, disputes) before replying.</li>
<li>Confirm issue in one sentence.</li>
<li>Use <strong>Reply</strong> for customer-visible text; <strong>Internal note</strong> for staff only.</li>
<li>Route money/dispute issues to Cases modules — do not promise refunds in chat.</li>
<li>Reassign or escalate per Escalation Matrix when out of authority.</li>
<li><strong>End session</strong> when resolved — customer gets feedback prompt.</li>
</ol>
<h2>4. SLA</h2>
<p>Queued chats: first response <strong>15 minutes</strong> during staffed hours.</p>
<h2>5. Log</h2>
<p>Internal note if handoff; link dispute/task ID in note.</p>
HTML,
    ],
    [
        'slug' => 'team-chat-direct-messages',
        'category' => 'Group A — Chat & Communications',
        'title' => 'Team chat and direct messages',
        'body' => <<<'HTML'
<h2>Team chat</h2>
<p>Shift handoffs and policy questions. Not a substitute for audited tasks.</p>
<h2>Direct messages</h2>
<p>1:1 with Super Admin or peers for sensitive previews. Confirm decisions in My Tasks.</p>
<h2>Rules</h2>
<ul>
<li>Urgent money/safety → task + DM, not DM alone.</li>
<li>No full document numbers or passwords in chat.</li>
</ul>
HTML,
    ],
    [
        'slug' => 'moderation-centre-deep-dive',
        'category' => 'Group B — Moderation Operations',
        'title' => 'Moderation centre — proposals and quests',
        'body' => <<<'HTML'
<h2>1. Triggers</h2>
<p>Post-publish flags, patrol samples, alerts, user reports.</p>
<h2>2. Queue</h2>
<p><strong>Moderation → Moderation centre</strong> — Proposals / Quests tabs, intelligence metrics.</p>
<h2>3. Steps</h2>
<ol>
<li>Filter by admin status: flagged, under review, restricted.</li>
<li>Open slide-over — risk panel, audit, communications.</li>
<li>Clear, flag, restrict, or suspend with documented reason.</li>
<li>Post user notice when edit required.</li>
<li>Refer cross-domain issues (KYC, dispute) via task.</li>
</ol>
<h2>4. SLA</h2>
<p>Flagged live listings: <strong>4 business hours</strong>.</p>
<h2>5. Log</h2>
<p>Action, reason code, listing ID, notice ID.</p>
HTML,
    ],
    [
        'slug' => 'quest-proposal-investigation',
        'category' => 'Group B — Moderation Operations',
        'title' => 'Quest & proposal investigation',
        'body' => <<<'HTML'
<h2>Proposal review</h2>
<ol>
<li>Read text, price, timeline, risk signals.</li>
<li>Check freelancer profile and quest context.</li>
<li>Actions: Clear, Flag, Under review, Restrict, Suspend.</li>
<li>Off-platform contact → uphold flag + user notice.</li>
</ol>
<h2>Quest review</h2>
<ol>
<li>Check budget realism, category, client history.</li>
<li>Flag prohibited services or contact details in description.</li>
</ol>
<p>See Escalation Matrix for fraud ring scenarios.</p>
HTML,
    ],
    [
        'slug' => 'reviews-management',
        'category' => 'Group B — Moderation Operations',
        'title' => 'Reviews management & integrity',
        'body' => <<<'HTML'
<h2>Reviews queue</h2>
<ol>
<li><strong>Moderation → Reviews</strong> — filter reported / burst patterns.</li>
<li>Read contract context and both parties' history.</li>
<li>Uphold, hide (policy violation), or warn author.</li>
<li>Contractual fairness disputes → refer to Group D, do not delete review alone.</li>
</ol>
<h2>Remove when</h2>
<p>Hate, threats, private data, wrong contract, extortion — with audited reason.</p>
HTML,
    ],
    [
        'slug' => 'badge-requests-content-patrol',
        'category' => 'Group B — Moderation Operations',
        'title' => 'Badge requests and content patrol',
        'body' => <<<'HTML'
<h2>Badge requests</h2>
<ol>
<li>Verify eligibility metrics (reviews, completion, disputes).</li>
<li>Approve or deny with reason.</li>
</ol>
<h2>Content patrol</h2>
<ol>
<li>Sampled listings — same actions as moderation centre.</li>
<li>Schedule regular patrol sessions per rota.</li>
</ol>
HTML,
    ],
    [
        'slug' => 'kyc-verification-review',
        'category' => 'Group C — People & Trust',
        'title' => 'KYC & verifications — document review',
        'body' => <<<'HTML'
<h2>1. Triggers</h2>
<p>New BVN, NIN, utility, identity submissions.</p>
<h2>2. Queue</h2>
<p><strong>People → Verifications</strong></p>
<h2>3. Steps</h2>
<ol>
<li>Review images for tampering, name match, expiry.</li>
<li>Check duplicate documents via trust monitoring.</li>
<li>Verify, Unverified (with reason), or Flag for fraud.</li>
<li>Guide user via support for regularisation when appropriate.</li>
</ol>
<h2>4. Escalate</h2>
<p>Tier override, whitelist false positive, stolen identity → Super Admin.</p>
<h2>5. SLA</h2>
<p>Standard queue: <strong>2 business days</strong>.</p>
HTML,
    ],
    [
        'slug' => 'users-warnings-suspensions',
        'category' => 'Group C — People & Trust',
        'title' => 'Users — warnings, restrictions, and suspensions',
        'body' => <<<'HTML'
<h2>72-hour suspension (ops authority)</h2>
<ol>
<li>Confirm violation type (spam, harassment, repeat off-platform contact).</li>
<li>Apply with reason; user sees limited access message.</li>
<li>Task for follow-up before expiry if permanent action likely.</li>
</ol>
<h2>Sanction appeals</h2>
<p><strong>Cases → Sanction appeals</strong> — read original audit before overturning.</p>
HTML,
    ],
    [
        'slug' => 'flag-fraud-scam-account',
        'category' => 'Group C — People & Trust',
        'title' => 'Flagging accounts for fraud or scam',
        'body' => <<<'HTML'
<h2>Steps</h2>
<ol>
<li><strong>People → Users</strong> — review activity, verification, disputes.</li>
<li>Flag with type and priority; add reason.</li>
<li>Apply restriction or 72h suspend if immediate harm reduction needed.</li>
<li>Escalate permanent ban or financial freeze to Super Admin.</li>
<li>Link payment/dispute evidence in task notes.</li>
</ol>
HTML,
    ],
    [
        'slug' => 'trust-quality-onboarding',
        'category' => 'Group C — People & Trust',
        'title' => 'Trust monitoring, quality, and onboarding assist',
        'body' => <<<'HTML'
<h2>Trust monitoring</h2>
<p>Watchlists and linked accounts — add when duplicate phones/devices/payment instruments found.</p>
<h2>Freelancer quality</h2>
<p>Completion and dispute trends — coaching flags, not auto-punishment.</p>
<h2>Onboarding assist</h2>
<p>Users stuck in signup/KYC/first quest — clear next step outreach.</p>
HTML,
    ],
    [
        'slug' => 'workspace-dashboard-alerts-tasks',
        'category' => 'Workspace',
        'title' => 'Dashboard, alert centre, and my tasks',
        'body' => <<<'HTML'
<h2>Alert centre</h2>
<ol>
<li>Read critical banners first.</li>
<li>Open linked record; confirm issue handled.</li>
</ol>
<h2>My tasks</h2>
<ol>
<li>Sort by due date / priority.</li>
<li>Complete work in linked module.</li>
<li>Mark done with reason or escalate if blocked.</li>
</ol>
HTML,
    ],
    [
        'slug' => 'common-tasks-index',
        'category' => 'Getting started',
        'title' => 'Staff admin bible — article index by role group',
        'body' => <<<'HTML'
<h2>Escalations</h2>
<ul>
<li><strong>Admin Escalation Matrix</strong> — master reference</li>
<li><strong>Escalating to Super Admin</strong></li>
</ul>
<h2>Group A — Chat &amp; Communications</h2>
<ul>
<li>Live support workflow</li>
<li>Flagged conversation review</li>
<li>When to escalate a flagged conversation</li>
<li>Team chat &amp; direct messages</li>
</ul>
<h2>Group B — Moderation Operations</h2>
<ul>
<li>Moderation centre</li>
<li>Quest &amp; proposal investigation</li>
<li>Reviews management</li>
<li>Badge requests &amp; content patrol</li>
</ul>
<h2>Group C — People &amp; Trust</h2>
<ul>
<li>KYC &amp; verifications</li>
<li>How to read a risk score</li>
<li>Flagging fraud/scam accounts</li>
<li>Warnings, suspensions, onboarding assist</li>
</ul>
<h2>Group D — Financial &amp; Disputes</h2>
<ul>
<li>Dispute resolution end-to-end</li>
<li>Disputed contract handling</li>
<li>Payment anomaly queue</li>
<li>Raise payment review flag</li>
<li>Escrow anomaly queue</li>
<li>Payout exceptions</li>
</ul>
HTML,
    ],
    [
        'slug' => 'knowledge-base-for-staff',
        'category' => 'Getting started',
        'title' => 'Using this knowledge base',
        'body' => <<<'HTML'
<h2>Search tips</h2>
<p>Use: dispute, KYC, escalate, escrow, anomaly, risk score, flagged conversation.</p>
<h2>Filter by role group</h2>
<p>Categories match your StaffRoleGroup assignment (A–D).</p>
<h2>Suggest updates</h2>
<p>Submit suggestions at the bottom of any article. Super Admins publish via Admin → Knowledge base.</p>
HTML,
    ],
    [
        'slug' => 'sanction-appeals',
        'category' => 'Group D — Financial & Disputes',
        'title' => 'Sanction appeals process',
        'body' => <<<'HTML'
<ol>
<li><strong>Cases → Sanction appeals</strong></li>
<li>Read original sanction audit — who, when, why.</li>
<li>Compare new user evidence.</li>
<li>Outcomes: uphold, reduce, or escalate SA for full reinstatement.</li>
<li>Reply via support within SLA — do not leave silent.</li>
</ol>
HTML,
    ],
    [
        'slug' => 'insights-support-hub',
        'category' => 'Insights',
        'title' => 'Support hub and communications log',
        'body' => <<<'HTML'
<h2>Support hub</h2>
<p>Global search — user email, quest code, ticket ID. Jump to authoritative module from results.</p>
<h2>Communications log</h2>
<p>What banners/emails users saw — useful for dispute context.</p>
HTML,
    ],
];
