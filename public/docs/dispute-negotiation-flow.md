# HustleSafe Dispute Resolution Flow

This document describes the end-to-end dispute resolution process for clients, freelancers, staff admins, and super admins.

## Binding agreement

By **posting a quest**, **sending a proposal**, **accepting work**, or **funding escrow** on HustleSafe, you agree that disputes from that engagement are resolved exclusively through this platform process. Outcomes are **binding**. External legal mediation or arbitration is not available for disputes resolved here.

You receive **email and in-app notifications** at every stage with links back to the dispute file.

---

## Phase 1 — Peer negotiation

**Max 2 proposals per party** · **24-hour response windows**

1. The dispute opener submits the first formal proposal (payment split, revision, extension, scope change, or other).
2. The other party may **Accept** (settlement submitted for staff approval) or **Counter** with an alternative.
3. Counters continue until each party has used up to two attempts.
4. On a **final offer**, the responding party may only **Accept** or **Reject** (escalate to mediation).

**If both accept at any point** → Phase 2A (mutual agreement approval).

**If no agreement after two rounds or a final rejection** → Phase 2B (mediation).

---

## Phase 2A — Mutual agreement approval (Staff)

Staff verify:

- Amounts match escrow
- Terms are clear and enforceable
- Both parties accepted on-platform
- No fraud indicators

**Approve & execute** → funds transfer → status **Resolved**.

**Appeal window: 4 days** after approval. If no appeal → **Finalized**.

---

## Phase 2B — Mediation (Staff)

Staff receive:

- All negotiation proposals (both parties)
- Full dispute intake and evidence
- Quest messages and contract history

Staff submit a written **assessment** with recommendation (split, revision, sanction, etc.).

**Before Super Admin review**, both parties must **acknowledge binding mediation**.

Staff mark the case **Ready for Super Admin decision**.

---

## Phase 3 — Super Admin approval

Super Admin reviews staff assessment, negotiation history, and evidence.

On approval:

- Decision is issued to both parties
- **48-hour rejection/appeal window** opens
- Escrow is **not** permanently distributed until the window closes or appeal is resolved

If no appeal → automatic enforcement → **Finalized**.

---

## Phase 4 — Appeal (one per party, per window)

During an open window, a party may file **one appeal** with:

- Why the decision is unfair
- What outcome they consider fair

The other party may respond (optional, 24 hours).

Super Admin conducts **final binding review**:

- Uphold original decision, or
- Grant modified outcome

**No further appeals** after this step.

---

## Resolution options (not only money)

Proposals and rulings may include:

| Category | Examples |
|----------|----------|
| Payment | Partial split, full refund, full award |
| Work | Revise, redo, repair deliverables |
| Timeline | Extend delivery, adjust milestones |
| Scope | Deliverable or scope adjustments |
| Other | Free-text custom terms |

---

## Admin roles

| Role | Responsibilities |
|------|------------------|
| **Parties** | Propose, accept, counter, reject, acknowledge binding mediation, file appeal |
| **Staff admin** | Investigate, assess, approve mutual agreements, route to Super Admin |
| **Super Admin** | Approve mediation decisions, resolve appeals, finalize |

---

## Automated deadlines

The `disputes:process-deadlines` command handles:

- Expired negotiation response timers → escalation to mediation
- Expired enforcement windows → automatic fund distribution
- Expired mutual-approval appeal windows → finalize

Configure timers in `config/disputes.php` under `negotiation`.

---

## Related pages

- [Dispute Policy](/legal/dispute) — legal terms
- Help article: **How to raise a dispute**
- Terms of Service — platform-wide binding dispute consent
