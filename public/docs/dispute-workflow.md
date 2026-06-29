# HustleSafe dispute workflow (operator spec)

> **Updated:** See [dispute-negotiation-flow.md](./dispute-negotiation-flow.md) for the full multi-phase peer negotiation, mediation, Super Admin approval, and binding appeal process.

This document describes the **v1** dispute rails shipped in the application. Payment disbursement still depends on the escrow gateway; timers, evidence, and audit events are live today.

## Core philosophy

1. **Evidence-first** — Decisions reference dated uploads, URLs, and checklist answers captured in `structured_intake` and dispute messages.
2. **Time-boxed** — `response_required_by` enforces self-resolution turns (default **48h**). After escalation, `ruling_required_by` applies (default **72h**) before an automatic neutral outcome is logged.
3. **Transparent** — Both parties read the same private thread (`dispute_messages`) and the immutable `dispute_events` stream.
4. **Escalatable** — Missed deadlines promote the dispute from `self_resolving` → `escalated` without manual nudging.
5. **Auditable** — No destructive edits to history; new rows append facts.

## When a dispute can be raised

| Trigger | Raised by |
| --- | --- |
| Work delivered but client will not approve | Freelancer |
| Funds committed but deliverables missing | Client |
| Quality / spec mismatch | Client |
| Scope creep beyond agreement | Freelancer |
| Milestone rejected unfairly | Freelancer |
| Refund after substantive work started | Client |
| Silence (minimum days configurable) | Client or freelancer |
| Contract violation | Client or freelancer |

Business gates (configurable in `config/disputes.php`):

- Minimum disputed contract value (default **₦5,000** equivalent in minor units).
- Maximum days after **quest completion** to open a file (default **14**).
- Requires an **accepted proposal** and an active engagement state (assigned / in progress / pending review / in dispute / recently completed).

## Lifecycle

1. **Intake** — Party completes `Disputes/Create` with reason, narrative (≥40 chars), optional evidence links, requested outcome, and philosophy acknowledgement.
2. **Self-resolution** — Counterparty must respond before `response_required_by`. Each structured post or settlement action refreshes the timer for the other side.
3. **Escalation** — If the awaited party misses the window, status becomes `escalated`, phase `formal_review`, and a new `ruling_required_by` window opens.
4. **Auto outcome (stub)** — If the formal window expires without settlement, the system records `auto_timed_split` at **50/50** for audit purposes (disbursement awaits gateway wiring).
5. **Settlement path** — Either party may propose `client_share_percent`. Counterparty may accept/decline; acceptance resolves the dispute as `settlement_accepted`.
6. **Mutual resolve** — Both parties click **I agree to resolve**; timestamps on `client_agrees_resolve_at` + `freelancer_agrees_resolve_at` close the file as `mutual_resolve`.

## Notifications

`QuestDisputeUpdatedNotification` (database channel) fires on open, thread updates, settlement events, escalations, and resolutions so both parties see milestones inside the bell.

## Funding intents

`POST /quests/{quest}/proposals/{offer}/funding-intent` records a `quest_funding_intents` row with snapshot totals. Checkout UI will replace the flash message once a PSP is selected (`config/escrow.php`).

## Operational knobs

See `config/disputes.php` for: minimum amount, post-completion window, platform fee %, appeals count, suspension review threshold, silence days, and timer lengths.

## Appeals & suspensions

Appeals counter is stored on `quest_disputes.appeals_used` for future tiers. `users.disputes_lost_count` increments when explicit rulings favour a single party (auto-split does **not** increment today).

## Commands

`php artisan disputes:process-deadlines` — scheduled hourly in `bootstrap/app.php`.

---

For product or legal review, keep this file aligned with public Terms & FAQ copy.
