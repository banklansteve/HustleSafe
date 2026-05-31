<template>
    <AppShell>
        <Head :title="`Proposal · ${quest.title}`" />

        <div class="mx-auto max-w-5xl space-y-2">
            <div
                v-if="page.props.flash?.success"
                class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-950 ring-1 ring-emerald-100"
                role="status"
            >
                {{ page.props.flash.success }}
            </div>

            <section
                v-if="!observer_mode && showFundingNotice"
                class="rounded-2xl border-2 border-amber-400 bg-amber-50 px-4 py-4 text-sm font-semibold text-amber-950 ring-2 ring-amber-200 sm:px-5"
                role="alert"
                aria-live="polite"
            >
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-amber-900">Escrow funded — read before you act</p>
                <p class="mt-2 text-xs leading-relaxed">
                    Work may begin now. Fund release stays locked for
                    <span class="font-black">{{ completionUi.cooldown_hours }} hours</span>
                    after funding (until {{ completionUi.release_eligible_label || 'the cooldown ends' }}).
                </p>
                <p class="mt-2 text-xs font-black leading-relaxed text-amber-950">
                    Do not tap “Confirm delivery” unless work has actually been delivered. That step only acknowledges delivery — it does not pay the freelancer.
                    Release is a separate step after the cooldown (and any high-value authorisation).
                </p>
            </section>

            <div class="flex flex-wrap items-center justify-between gap-2">
                <BackChevronLink
                    :href="is_client && client_proposals_hub_url ? client_proposals_hub_url : route('quests.show', quest.route_key)"
                    :aria-label="is_client ? 'Back to proposals' : 'Back to quest'"
                />
                <div class="flex flex-wrap gap-2">
                    <a
                        v-if="can_download_pdf"
                        :href="route('quests.proposals.pdf', [quest.route_key, offer.id])"
                        class="inline-flex items-center rounded-full border border-white/30 bg-white/10 px-4 py-2 text-xs font-black uppercase tracking-wide text-white shadow-sm hover:bg-white/20"
                    >
                        Download PDF
                    </a>
                    <span
                        class="inline-flex items-center rounded-full bg-white/15 px-4 py-2 text-xs font-black uppercase tracking-wide text-white ring-1 ring-white/25"
                        :class="statusPillClass"
                    >
                        {{ statusLabel }}
                    </span>
                </div>
            </div>

            <header
                class="relative overflow-hidden rounded-2xl border border-primary-900/30 bg-gradient-to-br from-primary-900 via-primary-700 to-primary-500 p-5 text-white shadow-lg ring-1 ring-primary-400/40 sm:p-7"
            >
                <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_15%_10%,rgba(255,255,255,0.12),transparent_42%)]" />
                <div class="relative space-y-3">
                    <Link
                        :href="route('quests.show', quest.route_key)"
                        class="inline-flex max-w-full items-center gap-2 rounded-full border border-white/25 bg-white/10 px-3 py-1.5 text-[11px] font-black uppercase tracking-wide text-white/95 ring-1 ring-white/20 backdrop-blur-sm hover:bg-white/20"
                    >
                        <span class="truncate">Quest · {{ quest.title }}</span>
                        <span aria-hidden="true" class="shrink-0">→</span>
                    </Link>
                    <p class="text-[10px] font-black uppercase tracking-[0.25em] text-white/70">Proposal</p>
                    <h1 class="font-display text-2xl font-black tracking-tight text-white sm:text-3xl">
                        {{ quest.title }}
                    </h1>
                    <p v-if="!observer_mode" class="text-sm font-semibold text-white/90">
                        Grand total <span class="font-black text-emerald-200">{{ formatBudget(offer.quoted_amount_minor) }}</span>
                        · Submitted {{ formatWhen(offer.created_at) }}
                    </p>
                    <p v-else class="text-sm font-semibold text-white/90">
                        Public preview — pricing and commercial breakdowns stay between the client and the freelancer.
                        · Submitted {{ formatWhen(offer.created_at) }}
                    </p>
                </div>
            </header>

            <section
                v-if="observer_mode"
                class="rounded-2xl border border-sky-200/90 bg-sky-50/90 px-4 py-3 text-sm font-semibold text-sky-950 ring-1 ring-sky-100 sm:px-5"
            >
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-sky-900">Community preview</p>
                <p class="mt-1 text-xs leading-relaxed">
                    You can read how this freelancer frames the work. Escrow amounts, materials, and reporting tools are hidden to protect commercial
                    confidentiality — explore their public profile if you want to collaborate on your own quests.
                </p>
            </section>

            <section
                v-if="!observer_mode && is_author && offer.client_view_count > 0"
                class="rounded-2xl border border-violet-200/90 bg-gradient-to-r from-violet-50 via-white to-fuchsia-50 px-4 py-3 text-sm font-semibold text-violet-950 shadow-sm ring-1 ring-violet-100 sm:px-5"
            >
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-violet-800">Client attention</p>
                <p class="mt-1 leading-relaxed">
                    This proposal has been opened
                    <span class="font-black text-violet-900">{{ offer.client_view_count }}</span>
                    {{ offer.client_view_count === 1 ? 'time' : 'times' }} by the client — we nudge you at gentle milestones so you never fly blind.
                </p>
            </section>

            <section
                v-if="!observer_mode && is_client && quest.status === 'open' && clientDecisionOffer"
                class="rounded-2xl border border-primary-200/90 bg-primary-50/90 px-4 py-3 text-sm font-semibold text-primary-950 ring-1 ring-primary-100 sm:px-5"
            >
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-primary-900">Your move</p>
                <p class="mt-1 text-xs leading-relaxed text-primary-950/90">
                    Shortlist to signal interest, or award when you are ready. Decline and award still require confirmation.
                </p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <Link
                        v-if="clarification_url"
                        :href="clarification_url"
                        class="rounded-full bg-sky-600 px-4 py-2 text-xs font-black uppercase tracking-wide text-white shadow-sm hover:bg-sky-700"
                    >
                        {{ clarification_summary ? `Clarify (${clarification_summary})` : 'Ask clarifying questions' }}
                    </Link>
                    <button
                        v-if="canToggleShortlist"
                        type="button"
                        class="rounded-full px-4 py-2 text-xs font-black uppercase tracking-wide shadow-sm transition disabled:opacity-60"
                        :class="localOfferStatus === 'shortlisted'
                            ? 'bg-sky-600 text-white ring-2 ring-sky-300 hover:bg-sky-700'
                            : 'bg-white text-sky-900 ring-1 ring-sky-200 hover:bg-sky-50'"
                        @click="toggleShortlist"
                    >
                        {{ localOfferStatus === 'shortlisted' ? 'Shortlisted' : 'Shortlist' }}
                    </button>
                    <button
                        type="button"
                        class="rounded-full bg-rose-600 px-4 py-2 text-xs font-black uppercase tracking-wide text-white shadow-sm hover:bg-rose-700"
                        @click="openModal('decline')"
                    >
                        Decline
                    </button>
                    <button
                        type="button"
                        class="rounded-full bg-emerald-600 px-4 py-2 text-xs font-black uppercase tracking-wide text-white shadow-sm hover:bg-emerald-700"
                        @click="openModal('accept')"
                    >
                        Award proposal
                    </button>
                </div>
            </section>

            <section
                v-if="!observer_mode && is_client && offer.status === 'pending_award'"
                class="rounded-2xl border border-amber-200/90 bg-amber-50/90 px-4 py-3 text-sm font-semibold text-amber-950 ring-1 ring-amber-100 sm:px-5"
            >
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-amber-900">Awaiting freelancer confirmation</p>
                <p class="mt-1 text-xs leading-relaxed">
                    You confirmed the award terms. Escrow funding unlocks only after the freelancer confirms scope, price, and deadline.
                </p>
                <ul v-if="awardTerms" class="mt-3 space-y-1 text-xs font-semibold text-amber-950/90">
                    <li>Price: <span class="font-black">{{ awardTerms.price_label }}</span></li>
                    <li v-if="awardTerms.deadline_label">Finish: <span class="font-black">{{ awardTerms.deadline_label }}</span></li>
                </ul>
            </section>

            <section
                v-if="!observer_mode && is_author && offer.status === 'pending_award'"
                class="rounded-2xl border border-emerald-200/90 bg-emerald-50/90 px-4 py-3 text-sm font-semibold text-emerald-950 ring-1 ring-emerald-100 sm:px-5"
            >
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-emerald-900">Confirm your award</p>
                <p class="mt-1 text-xs leading-relaxed">
                    The client selected you. Review the contract snapshot below and confirm — then they can fund escrow.
                </p>
                <ul v-if="awardTerms" class="mt-3 space-y-2 text-xs font-semibold text-emerald-950/90">
                    <li>Price: <span class="font-black">{{ awardTerms.price_label }}</span></li>
                    <li v-if="awardTerms.deadline_label">Target finish: <span class="font-black">{{ awardTerms.deadline_label }}</span></li>
                    <li class="leading-relaxed">Scope: {{ awardTerms.scope_summary }}</li>
                </ul>
                <button
                    type="button"
                    class="mt-3 rounded-full bg-emerald-700 px-4 py-2 text-xs font-black uppercase tracking-wide text-white hover:bg-emerald-800"
                    @click="openModal('confirm_award')"
                >
                    Confirm award terms
                </button>
            </section>

            <section
                v-if="commerce?.contract_url"
                class="rounded-2xl border border-primary-200/90 bg-primary-50/60 px-4 py-3 text-sm font-semibold text-primary-950 ring-1 ring-primary-100 sm:px-5"
            >
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-primary-900">Contract generated</p>
                <p class="mt-1 text-xs leading-relaxed">
                    Reference <span class="font-black">{{ commerce.contract_reference }}</span>
                    · {{ commerce.contract_status_label }}
                </p>
                <Link
                    :href="commerce.contract_url"
                    class="mt-3 inline-flex items-center rounded-full bg-primary-700 px-4 py-2 text-xs font-black uppercase tracking-wide text-white hover:bg-primary-800"
                >
                    View contract
                </Link>
            </section>

            <section
                v-if="!observer_mode && is_client && offer.status === 'accepted' && quest.escrow_status === 'awaiting_funding' && !commerce?.award_mutually_confirmed"
                class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700"
            >
                Escrow funding will appear here once both parties have confirmed the award terms.
            </section>

            <section
                v-if="!observer_mode && is_client && offer.status === 'accepted' && quest.escrow_status === 'awaiting_funding' && commerce?.award_mutually_confirmed"
                class="rounded-2xl border border-amber-200/90 bg-amber-50/90 px-4 py-3 text-sm font-semibold text-amber-950 ring-1 ring-amber-100 sm:px-5"
            >
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-amber-900">Escrow funding</p>
                <p class="mt-1 text-xs leading-relaxed">
                    Fund escrow for
                    <span class="font-black">{{ formatBudget(offer.quoted_amount_minor) }}</span>
                    (including fees in the breakdown) before the freelancer is expected to start. Nothing is released to them until you mark the job
                    completed.
                </p>
                <form
                    v-if="commerce?.show_fund_button && commerce?.funding_post_url"
                    :action="commerce.funding_post_url"
                    method="POST"
                    class="mt-3 inline-block"
                >
                    <input type="hidden" name="_token" :value="csrfToken" />
                    <button
                        type="submit"
                        class="inline-flex items-center rounded-full bg-emerald-700 px-4 py-2 text-xs font-black uppercase tracking-wide text-white shadow-sm hover:bg-emerald-800"
                    >
                        Pay with Paystack
                    </button>
                </form>
                <div v-if="commerce && (!observer_mode && (is_client || is_author))" class="mt-3 flex flex-wrap gap-2">
                    <Link
                        v-if="commerce.active_dispute"
                        :href="commerce.active_dispute.url"
                        class="inline-flex rounded-full border border-slate-200 bg-white px-4 py-2 text-xs font-black uppercase tracking-wide text-slate-800 hover:bg-slate-50"
                    >
                        Open dispute
                    </Link>
                    <Link
                        v-else-if="commerce.can_open_dispute && commerce.dispute_create_url"
                        :href="commerce.dispute_create_url"
                        class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-4 py-2 text-xs font-black uppercase tracking-wide text-amber-950 hover:bg-amber-100"
                    >
                        Open dispute
                    </Link>
                </div>
                <p v-if="commerce?.dispute_block_reason && !commerce?.can_open_dispute && !commerce?.active_dispute" class="mt-2 text-xs font-semibold text-amber-900">
                    {{ commerce.dispute_block_reason }}
                </p>
                <DisputePreventionPrompts v-if="commerce?.dispute_prevention_prompts?.length" class="mt-3" :prompts="commerce.dispute_prevention_prompts" />
            </section>

            <section
                v-if="!observer_mode && is_author && offer.status === 'accepted' && quest.escrow_status === 'awaiting_funding'"
                class="rounded-2xl border border-sky-200/90 bg-sky-50/90 px-4 py-3 text-sm font-semibold text-sky-950 ring-1 ring-sky-100 sm:px-5"
            >
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-sky-900">Hold steady</p>
                <p class="mt-1 text-xs leading-relaxed">
                    The client accepted your proposal. Please wait until escrow is funded and confirmed — only then should you begin billable work. Payouts
                    unlock when the client marks the quest complete.
                </p>
            </section>

            <section
                v-if="!observer_mode && commerce?.escrow_timeline && quest.escrow_status && !['none', 'awaiting_funding'].includes(quest.escrow_status)"
                class="rounded-2xl border border-emerald-200/90 bg-white px-4 py-4 shadow-sm ring-1 ring-emerald-100 sm:px-5"
            >
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-emerald-900">Escrow timeline</p>
                <p class="mt-1 text-xs font-semibold text-slate-600">Live status of funds on this contract — updated as each stage completes.</p>
                <div class="mt-3">
                    <EscrowTransparencyTimeline :timeline="commerce.escrow_timeline" />
                </div>
                <DisputePreventionPrompts v-if="commerce.dispute_prevention_prompts?.length" class="mt-4" :prompts="commerce.dispute_prevention_prompts" />
            </section>

            <section
                v-if="!observer_mode && completionUi.show_completion_section"
                class="rounded-2xl border border-emerald-200/90 bg-emerald-50/90 px-4 py-3 text-sm font-semibold text-emerald-950 ring-1 ring-emerald-100 sm:px-5"
            >
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-emerald-900">Delivery & escrow release</p>
                <p class="mt-1 text-xs leading-relaxed">
                    Two separate steps: confirm delivery when work is done, then release funds after the protection window (and any platform authorisation for high-value contracts).
                </p>
                <p v-if="completionUi.delivery_acknowledged" class="mt-2 rounded-xl border border-sky-200 bg-sky-50 px-3 py-2 text-xs font-bold text-sky-950">
                    Delivery acknowledged — escrow stays locked until you release funds when eligible.
                </p>
                <p v-if="completionUi.requires_admin_authorization && !completionUi.has_admin_authorization" class="mt-2 rounded-xl border border-violet-200 bg-violet-50 px-3 py-2 text-xs font-bold text-violet-950">
                    This contract is {{ completionUi.high_value_threshold }} or above. HustleSafe must authorise release before funds can move, even after the cooldown.
                </p>
                <p v-if="completionUi.release_held" class="mt-2 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-bold text-rose-950">
                    {{ completionUi.release_hold_reason || 'Release is on hold by platform staff.' }}
                </p>
                <p v-if="completionUi.blocked_release_reason" class="mt-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-bold text-amber-950">
                    {{ completionUi.blocked_release_reason }}
                    <span v-if="cooldownLabel && completionUi.delivery_acknowledged" class="mt-1 block font-black">Release unlocks in {{ cooldownLabel }}</span>
                </p>
                <DisputePreventionPrompts v-if="commerce?.dispute_prevention_prompts?.length && !commerce?.escrow_timeline" class="mt-3" :prompts="commerce.dispute_prevention_prompts" />
                <div class="mt-3 flex flex-wrap gap-2">
                    <button
                        v-if="!completionUi.delivery_acknowledged"
                        type="button"
                        class="inline-flex rounded-full bg-sky-700 px-4 py-2 text-xs font-black uppercase tracking-wide text-white hover:bg-sky-800 disabled:opacity-50"
                        :disabled="!completionUi.can_acknowledge_delivery"
                        @click="openAcknowledgeModal"
                    >
                        Confirm delivery
                    </button>
                    <button
                        v-else
                        type="button"
                        class="inline-flex rounded-full bg-emerald-700 px-4 py-2 text-xs font-black uppercase tracking-wide text-white hover:bg-emerald-800 disabled:opacity-50"
                        :disabled="!completionUi.can_release_funds"
                        @click="openReleaseModal"
                    >
                        Release funds to freelancer
                    </button>
                </div>
            </section>

            <section v-if="!observer_mode && is_client && conversation_with_freelancer_url" class="rounded-xl border border-primary-200 bg-primary-50/80 px-4 py-3 text-sm font-semibold text-primary-950 ring-1 ring-primary-100">
                <p class="font-black uppercase tracking-wide text-primary-900">Clarify before you decide</p>
                <p class="mt-1 text-xs leading-relaxed text-primary-900/90">
                    Keep all contact on-platform — numbers and social handles are blocked and may lead to bans.
                </p>
                <Link
                    :href="conversation_with_freelancer_url"
                    class="mt-3 inline-flex rounded-full bg-primary-700 px-4 py-2 text-xs font-black uppercase tracking-wide text-white shadow-sm hover:bg-primary-800"
                >
                    Message freelancer
                </Link>
            </section>

            <div class="grid gap-2 lg:grid-cols-3">
                <div class="space-y-2 lg:col-span-2">
                    <section class="space-y-2 rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6">
                        <h2 class="font-display text-sm font-black uppercase tracking-wide text-slate-500">
                            Executive pitch
                        </h2>
                        <p class="whitespace-pre-wrap text-sm font-medium leading-relaxed text-slate-800">
                            {{ offer.pitch }}
                        </p>
                    </section>
                    <section class="space-y-2 rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6">
                        <h2 class="font-display text-sm font-black uppercase tracking-wide text-slate-500">
                            Scope & approach
                        </h2>
                        <p class="whitespace-pre-wrap text-sm font-medium leading-relaxed text-slate-800">
                            {{ offer.scope_detail }}
                        </p>
                    </section>
                    <section v-if="offer.warranty_terms" class="space-y-2 rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6">
                        <h2 class="font-display text-sm font-black uppercase tracking-wide text-slate-500">
                            Warranty / assurance
                        </h2>
                        <p class="whitespace-pre-wrap text-sm font-medium leading-relaxed text-slate-800">
                            {{ offer.warranty_terms }}
                        </p>
                    </section>
                </div>
                <aside class="space-y-2">
                    <section class="space-y-2 rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm ring-1 ring-slate-100">
                        <h2 class="font-display text-sm font-black uppercase tracking-wide text-slate-500">
                            Freelancer
                        </h2>
                        <div v-if="offer.freelancer" class="flex items-center gap-3">
                            <UserProfileAvatar
                                :href="offer.freelancer.slug ? route('freelancers.public', offer.freelancer.slug) : null"
                                :src="offer.freelancer.avatar_url"
                                :name="offer.freelancer.name"
                                :alt="offer.freelancer.name"
                                frame-class="h-12 w-12 text-xs"
                            />
                            <div class="min-w-0">
                                <p class="truncate font-bold text-slate-900">{{ offer.freelancer.name }}</p>
                                <p v-if="offer.freelancer.headline" class="truncate text-xs font-semibold text-slate-600">
                                    {{ offer.freelancer.headline }}
                                </p>
                                <Link
                                    v-if="offer.freelancer.slug"
                                    :href="route('freelancers.public', offer.freelancer.slug)"
                                    class="mt-1 text-[11px] font-black uppercase tracking-wide text-primary-800 underline"
                                >
                                    Public profile
                                </Link>
                            </div>
                        </div>
                    </section>
                    <section
                        v-if="!observer_mode && is_author && ['submitted', 'shortlisted'].includes(offer.status)"
                        class="space-y-3 rounded-2xl border border-slate-200/90 bg-gradient-to-br from-slate-50 to-white p-5 shadow-sm ring-1 ring-slate-100"
                    >
                        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-600">Your proposal</p>
                        <div class="flex flex-col gap-2">
                            <Link
                                v-if="offer.can_edit"
                                :href="route('quests.proposals.edit', [quest.route_key, offer.id])"
                                class="inline-flex justify-center rounded-full bg-primary-600 px-4 py-2.5 text-center text-xs font-black uppercase tracking-wide text-white shadow-sm hover:bg-primary-700"
                            >
                                Edit proposal
                            </Link>
                            <p v-else-if="offer.freelancer_edit_deadline_at" class="text-center text-xs font-semibold text-slate-500">
                                Edit window closed {{ formatWhen(offer.freelancer_edit_deadline_at) }}
                            </p>
                            <button
                                type="button"
                                class="rounded-full border border-rose-200 bg-rose-50 px-4 py-2.5 text-xs font-black uppercase tracking-wide text-rose-900 hover:bg-rose-100"
                                @click="openModal('withdraw')"
                            >
                                Withdraw proposal
                            </button>
                        </div>
                        <p class="text-xs leading-relaxed text-slate-600">
                            After acceptance, withdrawal is not available here — contact support if you must exit.
                        </p>
                    </section>
                    <section class="space-y-2 rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm ring-1 ring-slate-100">
                        <h2 class="font-display text-sm font-black uppercase tracking-wide text-slate-500">
                            Timeline
                        </h2>
                        <ul class="space-y-2 text-sm font-semibold text-slate-800">
                            <li>Planned start: <span class="font-black">{{ offer.planned_start_date || '—' }}</span></li>
                            <li>Planned finish: <span class="font-black">{{ offer.planned_finish_date || '—' }}</span></li>
                            <li v-if="offer.estimated_duration_days">Estimated duration: <span class="font-black">{{ offer.estimated_duration_days }} days</span></li>
                            <li v-if="offer.progress_report_frequency">
                                Progress reports: <span class="font-black">{{ progressLabel(offer.progress_report_frequency) }}</span>
                            </li>
                            <li>
                                Corrections / redo:
                                <span class="font-black">{{ offer.corrections_included ? `${offer.corrections_rounds ?? '—'} rounds` : 'Not included' }}</span>
                            </li>
                        </ul>
                    </section>
                </aside>
            </div>

            <section v-if="!observer_mode" class="space-y-2 rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6">
                <h2 class="font-display text-sm font-black uppercase tracking-wide text-slate-500">
                    Materials & parts
                </h2>
                <ul class="divide-y divide-slate-100 rounded-xl border border-slate-100">
                    <li v-for="(m, i) in offer.materials" :key="i" class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm font-semibold text-slate-900">
                        <span>
                            {{ m.label }}
                            <span v-if="m.quantity" class="text-xs font-bold text-slate-500">× {{ m.quantity }}</span>
                            <span v-if="m.unit_price_minor" class="ml-1 text-xs font-semibold text-slate-500">
                                @ {{ formatMinor(m.unit_price_minor) }} / unit
                            </span>
                        </span>
                        <span class="font-black text-primary-800">{{ formatMinor(m.line_total_minor ?? m.cost_minor) }}</span>
                    </li>
                </ul>
            </section>

            <section v-if="!observer_mode" class="space-y-2 rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6">
                <h2 class="font-display text-sm font-black uppercase tracking-wide text-slate-500">
                    Pricing breakdown
                </h2>
                <div class="grid gap-2 sm:grid-cols-2">
                    <div v-for="row in pricingRows" :key="row.k" class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50/70 px-4 py-2.5 text-sm font-semibold text-slate-800">
                        <span>{{ row.label }}</span>
                        <span class="font-black">
                            <template v-if="row.label === 'Discount'">−</template>{{ formatMinor(Math.abs(Number(row.v) || 0)) }}
                        </span>
                    </div>
                </div>
            </section>

            <ReportConcernSheet
                v-if="canReportProposal"
                :action-url="route('quests.proposals.reports.store', [quest.route_key, offer.id])"
                subtitle="Misleading quotes, harassment, or attempts to move payment off-platform should be reported. Our team triages by severity."
                :context="{
                    type: 'proposal',
                    proposal_id: offer.id,
                    quest_title: quest.title,
                    freelancer_name: offer.freelancer?.name || offer.freelancer?.first_name,
                }"
            />
        </div>

        <Teleport to="body">
            <div
                v-if="activeModal"
                class="fixed inset-0 z-[60] flex items-end justify-center bg-slate-950/50 p-4 backdrop-blur-[2px] sm:items-center"
                role="dialog"
                aria-modal="true"
                @click.self="closeModal"
            >
                <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl sm:p-6">
                    <div class="flex items-start justify-between gap-3">
                        <h3 class="font-display text-lg font-black text-slate-900">{{ modalTitle }}</h3>
                        <button type="button" class="rounded-full p-1 text-slate-500 hover:bg-slate-100" aria-label="Close" @click="closeModal">✕</button>
                    </div>
                    <p class="mt-2 text-sm font-medium leading-relaxed text-slate-600">{{ modalIntro }}</p>

                    <form class="mt-5 space-y-4" @submit.prevent="submitModal">
                        <template v-if="activeModal === 'decline'">
                            <p class="text-xs font-semibold text-slate-600">
                                Declining closes this thread for the freelancer on this quest. They are notified immediately. You can still accept another
                                proposal while the quest stays open.
                            </p>
                            <label class="flex cursor-pointer items-start gap-3 text-sm font-semibold text-slate-800">
                                <input v-model="declineForm.confirm" type="checkbox" class="mt-1 rounded border-slate-300 text-rose-600 focus:ring-rose-500" />
                                <span>I confirm I want to decline this proposal.</span>
                            </label>
                            <label class="flex cursor-pointer items-start gap-3 text-sm font-semibold text-slate-800">
                                <input v-model="declineForm.understand_decline" type="checkbox" class="mt-1 rounded border-slate-300 text-rose-600 focus:ring-rose-500" />
                                <span>I understand the freelancer will be informed and this proposal will no longer be eligible for acceptance.</span>
                            </label>
                            <InputError :message="declineForm.errors.confirm" />
                            <InputError :message="declineForm.errors.understand_decline" />
                        </template>

                        <template v-else-if="activeModal === 'accept'">
                            <div v-if="awardTermsPreview" class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs font-semibold text-slate-800">
                                <p class="font-black uppercase tracking-wide text-slate-500">Contract snapshot</p>
                                <p class="mt-2">Price: <span class="font-black">{{ awardTermsPreview.price_label }}</span></p>
                                <p v-if="awardTermsPreview.deadline_label">Finish: <span class="font-black">{{ awardTermsPreview.deadline_label }}</span></p>
                                <p class="mt-2 leading-relaxed">{{ awardTermsPreview.scope_summary }}</p>
                            </div>
                            <ul class="mt-4 list-disc space-y-2 pl-4 text-xs font-semibold text-slate-700">
                                <li>
                                    You agree to fund escrow for the full quote
                                    <span class="font-black text-slate-900">{{ formatBudget(offer.quoted_amount_minor) }}</span>
                                    (including platform and statutory lines shown in the breakdown) before the freelancer is obligated to start.
                                </li>
                                <li>
                                    After the agreed end date, if you do not mark the job completed or open a dispute, escrow may automatically release to the freelancer after
                                    <span class="font-black text-slate-900">72 hours</span>
                                    — this protects freelancers from indefinite holds while you still have a short window to review delivery.
                                </li>
                                <li>
                                    Platform fee is shown in the quote (reference rate ~{{ offer.platform_fee_percent_display }}% where applicable — see
                                    <a :href="route('legal.terms')" target="_blank" rel="noopener noreferrer" class="font-black text-primary-800 underline">Terms</a>).
                                </li>
                                <li>
                                    If delivery fails or disputes arise, eligible cases receive a full refund under our
                                    <a :href="route('legal.terms')" target="_blank" rel="noopener noreferrer" class="font-black text-primary-800 underline">Terms</a>
                                    and dispute process.
                                </li>
                            </ul>
                            <label class="flex cursor-pointer items-start gap-3 text-sm font-semibold text-slate-800">
                                <input v-model="acceptForm.confirm" type="checkbox" class="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                                <span>I confirm I want to award this proposal.</span>
                            </label>
                            <InputError :message="acceptForm.errors.confirm" />
                            <label class="flex cursor-pointer items-start gap-3 text-sm font-semibold text-slate-800">
                                <input v-model="acceptForm.confirm_scope" type="checkbox" class="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                                <span>I confirm the agreed scope above matches what I expect delivered.</span>
                            </label>
                            <InputError :message="acceptForm.errors.confirm_scope" />
                            <label class="flex cursor-pointer items-start gap-3 text-sm font-semibold text-slate-800">
                                <input v-model="acceptForm.confirm_price" type="checkbox" class="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                                <span>I confirm the quoted price is what I will fund in escrow (including fees in the breakdown).</span>
                            </label>
                            <InputError :message="acceptForm.errors.confirm_price" />
                            <label class="flex cursor-pointer items-start gap-3 text-sm font-semibold text-slate-800">
                                <input v-model="acceptForm.confirm_deadline" type="checkbox" class="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                                <span>I confirm the target finish date / timeline is acceptable.</span>
                            </label>
                            <InputError :message="acceptForm.errors.confirm_deadline" />
                            <label class="flex cursor-pointer items-start gap-3 text-sm font-semibold text-slate-800">
                                <input v-model="acceptForm.accept_escrow_rules" type="checkbox" class="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                                <span>I understand I must fund escrow after the freelancer confirms, before work should begin, and funds release when I mark complete or under the 72-hour rule.</span>
                            </label>
                            <InputError :message="acceptForm.errors.accept_escrow_rules" />
                            <label class="flex cursor-pointer items-start gap-3 text-sm font-semibold text-slate-800">
                                <input v-model="acceptForm.accept_fees_and_terms" type="checkbox" class="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                                <span>I accept applicable fees and the escrow rules described in the Terms.</span>
                            </label>
                            <InputError :message="acceptForm.errors.accept_fees_and_terms" />
                            <label class="flex cursor-pointer items-start gap-3 text-sm font-semibold text-slate-800">
                                <input v-model="acceptForm.accept_auto_release_ack" type="checkbox" class="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                                <span>I understand the 72-hour auto-release rule after the planned job end if I do not mark complete or dispute in time.</span>
                            </label>
                            <InputError :message="acceptForm.errors.accept_auto_release_ack" />
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                                <p class="text-xs font-black uppercase tracking-wide text-slate-500">Confirm deliverables</p>
                                <p class="mt-1 text-xs font-semibold text-slate-600">List each deliverable as a separate line item — this is frozen into the contract.</p>
                                <ul class="mt-3 space-y-2">
                                    <li v-for="(item, idx) in acceptDeliverables" :key="idx" class="flex gap-2">
                                        <input v-model="item.title" type="text" class="min-w-0 flex-1 rounded-lg border-slate-200 text-sm shadow-sm" placeholder="Deliverable title" required />
                                        <button v-if="acceptDeliverables.length > 1" type="button" class="shrink-0 text-xs font-bold text-rose-700" @click="acceptDeliverables.splice(idx, 1)">Remove</button>
                                    </li>
                                </ul>
                                <button type="button" class="mt-2 text-xs font-black uppercase text-primary-800 underline" @click="acceptDeliverables.push({ title: '', description: '' })">Add deliverable</button>
                                <InputError :message="acceptForm.errors.deliverables" />
                            </div>
                            <label class="mt-2 block text-xs font-bold uppercase text-slate-600">Revision definition</label>
                            <textarea
                                v-model="acceptForm.revision_definition"
                                rows="3"
                                class="mt-1 w-full rounded-xl border-slate-200 text-sm shadow-sm"
                                placeholder="What counts as a revision vs a scope change?"
                            />
                        </template>

                        <template v-else-if="activeModal === 'confirm_award'">
                            <ul v-if="awardTerms" class="list-disc space-y-2 pl-4 text-xs font-semibold text-slate-700">
                                <li>Price: <span class="font-black">{{ awardTerms.price_label }}</span></li>
                                <li v-if="awardTerms.deadline_label">Finish: <span class="font-black">{{ awardTerms.deadline_label }}</span></li>
                                <li class="leading-relaxed">{{ awardTerms.scope_summary }}</li>
                            </ul>
                            <label class="mt-4 flex cursor-pointer items-start gap-3 text-sm font-semibold text-slate-800">
                                <input v-model="confirmAwardForm.confirm" type="checkbox" class="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                                <span>I confirm I understand and accept this award.</span>
                            </label>
                            <label class="flex cursor-pointer items-start gap-3 text-sm font-semibold text-slate-800">
                                <input v-model="confirmAwardForm.confirm_scope" type="checkbox" class="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                                <span>I accept the scope described above.</span>
                            </label>
                            <label class="flex cursor-pointer items-start gap-3 text-sm font-semibold text-slate-800">
                                <input v-model="confirmAwardForm.confirm_price" type="checkbox" class="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                                <span>I accept the quoted price.</span>
                            </label>
                            <label class="flex cursor-pointer items-start gap-3 text-sm font-semibold text-slate-800">
                                <input v-model="confirmAwardForm.confirm_deadline" type="checkbox" class="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                                <span>I accept the target finish timeline.</span>
                            </label>
                            <InputError :message="confirmAwardForm.errors.confirm" />
                        </template>

                        <template v-else-if="activeModal === 'escrow'">
                            <p class="text-xs font-semibold text-slate-600">
                                Only confirm after you have completed the escrow funding flow for the full amount (including fees). This tells the freelancer they
                                may start — misrepresentation may violate our Terms.
                            </p>
                            <label class="flex cursor-pointer items-start gap-3 text-sm font-semibold text-slate-800">
                                <input v-model="escrowForm.confirm" type="checkbox" class="mt-1 rounded border-slate-300 text-amber-600 focus:ring-amber-500" />
                                <span>I confirm I am ready to mark escrow as funded.</span>
                            </label>
                            <label class="flex cursor-pointer items-start gap-3 text-sm font-semibold text-slate-800">
                                <input v-model="escrowForm.confirm_funds_in_escrow" type="checkbox" class="mt-1 rounded border-slate-300 text-amber-600 focus:ring-amber-500" />
                                <span>I have transferred the full required amount (including fees) into escrow.</span>
                            </label>
                            <InputError :message="escrowForm.errors.confirm" />
                            <InputError :message="escrowForm.errors.confirm_funds_in_escrow" />
                        </template>

                        <template v-else-if="activeModal === 'acknowledge'">
                            <p class="text-xs font-semibold text-slate-600">
                                This records that you received the deliverables. Escrow is <span class="font-black">not</span> released yet — use “Release funds” only after the
                                {{ completionUi.cooldown_hours }}-hour protection window and any required authorisations.
                            </p>
                            <label class="flex cursor-pointer items-start gap-3 text-sm font-semibold text-slate-800">
                                <input v-model="acknowledgeForm.confirm" type="checkbox" class="mt-1 rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                                <span>I confirm deliverables meet the agreed brief. I am not releasing payment yet.</span>
                            </label>
                            <InputError :message="acknowledgeForm.errors.confirm" />
                            <InputError :message="acknowledgeForm.errors.quest" />
                        </template>

                        <template v-else-if="activeModal === 'release'">
                            <p class="text-xs font-semibold text-slate-600">
                                This releases escrow to the freelancer's wallet (minus platform fee) and marks the quest complete. Logged for compliance. Release is blocked until
                                {{ completionUi.cooldown_hours }} hours after funding unless platform staff intervene.
                            </p>
                            <label class="flex cursor-pointer items-start gap-3 text-sm font-semibold text-slate-800">
                                <input v-model="releaseForm.confirm" type="checkbox" class="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                                <span>I confirm deliverables are satisfactory and I want to release escrow now.</span>
                            </label>
                            <label class="flex cursor-pointer items-start gap-3 text-sm font-semibold text-slate-800">
                                <input v-model="releaseForm.acknowledge_release" type="checkbox" class="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                                <span>I understand funds move to the freelancer's wallet and this cannot be undone from the app.</span>
                            </label>
                            <InputError :message="releaseForm.errors.confirm" />
                            <InputError :message="releaseForm.errors.acknowledge_release" />
                            <InputError :message="releaseForm.errors.escrow" />
                        </template>

                        <template v-else-if="activeModal === 'withdraw'">
                            <p v-if="offer.status === 'shortlisted' || offer.shortlisted_at" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-950">
                                You were shortlisted — withdrawing now is logged and may slightly reduce your reliability score on the platform.
                            </p>
                            <p class="text-xs font-semibold text-slate-600">
                                Withdrawing removes your proposal from the client’s queue. They are notified. You may submit a fresh proposal if the quest remains
                                open.
                            </p>
                            <label class="flex cursor-pointer items-start gap-3 text-sm font-semibold text-slate-800">
                                <input v-model="withdrawForm.confirm" type="checkbox" class="mt-1 rounded border-slate-300 text-rose-600 focus:ring-rose-500" />
                                <span>I confirm I want to withdraw this proposal.</span>
                            </label>
                            <label class="flex cursor-pointer items-start gap-3 text-sm font-semibold text-slate-800">
                                <input v-model="withdrawForm.understand_withdraw" type="checkbox" class="mt-1 rounded border-slate-300 text-rose-600 focus:ring-rose-500" />
                                <span>I understand the client will be notified and this version of the proposal will be closed.</span>
                            </label>
                            <InputError :message="withdrawForm.errors.confirm" />
                            <InputError :message="withdrawForm.errors.understand_withdraw" />
                        </template>

                        <div class="flex flex-wrap justify-end gap-2 pt-2">
                            <button type="button" class="rounded-full border border-slate-200 px-4 py-2 text-xs font-black uppercase tracking-wide text-slate-700 hover:bg-slate-50" @click="closeModal">
                                Cancel
                            </button>
                            <button
                                type="submit"
                                class="inline-flex items-center rounded-full px-5 py-2 text-xs font-black uppercase tracking-wide text-white shadow-sm disabled:opacity-50"
                                :class="modalPrimaryClass"
                                :disabled="modalSubmitDisabled"
                            >
                                <ReLoader4Line v-if="anyModalFormProcessing" class="mr-2 h-4 w-4 shrink-0 animate-spin text-white/90" aria-hidden="true" />
                                Confirm
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AppShell>
</template>

<script setup>
import ReportConcernSheet from '@/Components/Quests/ReportConcernSheet.vue';
import EscrowTransparencyTimeline from '@/Components/Quests/EscrowTransparencyTimeline.vue';
import DisputePreventionPrompts from '@/Components/Quests/DisputePreventionPrompts.vue';
import BackChevronLink from '@/Components/Ui/BackChevronLink.vue';
import InputError from '@/Components/InputError.vue';
import UserProfileAvatar from '@/Components/Ui/UserProfileAvatar.vue';
import AppShell from '@/Layouts/AppShell.vue';
import axios from 'axios';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ReLoader4Line } from '@kalimahapps/vue-icons/re';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    quest: { type: Object, required: true },
    offer: { type: Object, required: true },
    is_client: { type: Boolean, default: false },
    is_author: { type: Boolean, default: false },
    observer_mode: { type: Boolean, default: false },
    can_download_pdf: { type: Boolean, default: true },
    client_proposals_hub_url: { type: String, default: null },
    conversation_with_freelancer_url: { type: String, default: null },
    clarification_url: { type: String, default: null },
    clarification_summary: { type: Number, default: 0 },
    commerce: { type: Object, default: null },
});

const page = usePage();

const isStaffRole = computed(() => ['admin', 'super_admin'].includes(page.props.auth?.user?.role?.slug ?? ''));
const canReportProposal = computed(() => Boolean(page.props.auth?.user) && !props.is_author && !props.observer_mode && !isStaffRole.value);

const acknowledgeForm = useForm({ confirm: false });
const releaseForm = useForm({ confirm: false, acknowledge_release: false });

const showFundingNotice = computed(() => Boolean(page.props.flash?.show_escrow_funding_notice));

const completionUi = computed(() => {
    const c = props.commerce?.completion ?? {};
    return {
        show_completion_section: Boolean(c.show_completion_section),
        can_acknowledge_delivery: Boolean(c.can_acknowledge_delivery),
        can_release_funds: Boolean(c.can_release_funds),
        delivery_acknowledged: Boolean(c.delivery_acknowledged),
        blocked_release_reason: c.blocked_release_reason ?? null,
        cooldown_hours: c.cooldown_hours ?? 24,
        seconds_until_release: Number(c.seconds_until_release ?? 0),
        release_eligible_label: c.release_eligible_label ?? null,
        requires_admin_authorization: Boolean(c.requires_admin_authorization),
        has_admin_authorization: Boolean(c.has_admin_authorization),
        release_held: Boolean(c.release_held),
        release_hold_reason: c.release_hold_reason ?? null,
        high_value_threshold: c.high_value_threshold ?? null,
    };
});

const cooldownSeconds = ref(completionUi.value.seconds_until_release);
let cooldownTimer = null;

function formatCooldown(totalSeconds) {
    const s = Math.max(0, totalSeconds);
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const sec = s % 60;
    if (h > 0) {
        return `${h}h ${m}m`;
    }
    if (m > 0) {
        return `${m}m ${sec}s`;
    }
    return `${sec}s`;
}

const cooldownLabel = computed(() => formatCooldown(cooldownSeconds.value));

onMounted(() => {
    cooldownSeconds.value = completionUi.value.seconds_until_release;
    cooldownTimer = window.setInterval(() => {
        if (cooldownSeconds.value > 0) {
            cooldownSeconds.value -= 1;
        }
    }, 1000);
});

onBeforeUnmount(() => {
    if (cooldownTimer) {
        window.clearInterval(cooldownTimer);
    }
});

const csrfToken = computed(() => {
    const fromPage = page.props?.csrf_token;
    if (fromPage) {
        return fromPage;
    }
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
});

function openAcknowledgeModal() {
    if (!completionUi.value.can_acknowledge_delivery) {
        return;
    }
    acknowledgeForm.clearErrors();
    acknowledgeForm.reset();
    openModal('acknowledge');
}

function openReleaseModal() {
    if (!completionUi.value.can_release_funds) {
        return;
    }
    releaseForm.clearErrors();
    releaseForm.reset();
    openModal('release');
}

const activeModal = ref(null);
const localOfferStatus = ref(props.offer.status);

watch(
    () => props.offer.status,
    (status) => {
        localOfferStatus.value = status;
    },
);

const declineForm = useForm({ confirm: false, understand_decline: false });
const acceptForm = useForm({
    confirm: false,
    confirm_scope: false,
    confirm_price: false,
    confirm_deadline: false,
    accept_escrow_rules: false,
    accept_fees_and_terms: false,
    accept_auto_release_ack: false,
    revision_definition: 'A revision adjusts the agreed deliverable within the original scope. New features or material scope expansion require an amendment.',
});
const acceptDeliverables = ref([{ title: '', description: '' }]);
const confirmAwardForm = useForm({
    confirm: false,
    confirm_scope: false,
    confirm_price: false,
    confirm_deadline: false,
});
const escrowForm = useForm({ confirm: false, confirm_funds_in_escrow: false });
const withdrawForm = useForm({ confirm: false, understand_withdraw: false });

const anyModalFormProcessing = computed(
    () =>
        declineForm.processing
        || acceptForm.processing
        || confirmAwardForm.processing
        || escrowForm.processing
        || acknowledgeForm.processing
        || releaseForm.processing
        || withdrawForm.processing,
);

const canToggleShortlist = computed(
    () => props.is_client && props.quest.status === 'open' && ['submitted', 'shortlisted'].includes(localOfferStatus.value),
);

const awardTerms = computed(() => props.offer.award_terms_snapshot || null);

const awardTermsPreview = computed(() => {
    if (awardTerms.value) {
        return awardTerms.value;
    }

    const finish = props.offer.planned_finish_date || props.offer.proposed_completion_date;

    return {
        scope_summary: props.offer.scope_detail || props.offer.pitch || '',
        price_label: formatBudget(props.offer.quoted_amount_minor),
        deadline_label: finish || null,
    };
});

const clientDecisionOffer = computed(
    () => props.is_client && props.quest.status === 'open' && ['submitted', 'shortlisted'].includes(localOfferStatus.value),
);

const statusLabel = computed(() => {
    const s = props.offer.status;
    const map = {
        submitted: 'Submitted',
        shortlisted: 'Shortlisted',
        pending_award: 'Awaiting confirmation',
        accepted: 'Accepted',
        declined: 'Declined',
        withdrawn: 'Withdrawn',
    };

    return map[s] || s;
});

const statusPillClass = computed(() => {
    const s = props.offer.status;
    if (s === 'accepted') {
        return 'bg-emerald-600';
    }
    if (s === 'declined' || s === 'withdrawn') {
        return 'bg-slate-500';
    }
    if (s === 'shortlisted') {
        return 'bg-violet-600';
    }
    if (s === 'pending_award') {
        return 'bg-amber-600';
    }

    return 'bg-slate-900';
});

const modalTitle = computed(() => {
    const m = activeModal.value;
    const titles = {
        decline: 'Decline proposal',
        accept: 'Award proposal',
        confirm_award: 'Confirm award terms',
        escrow: 'Confirm escrow funded',
        acknowledge: 'Confirm delivery',
        release: 'Release funds to freelancer',
        withdraw: 'Withdraw proposal',
    };

    return titles[m] || '';
});

const modalIntro = computed(() => {
    const m = activeModal.value;
    if (m === 'decline') {
        return 'Declining is permanent for this proposal. Other proposals remain untouched.';
    }
    if (m === 'accept') {
        return 'You confirm scope, price, and deadline. The freelancer must confirm too before escrow funding unlocks.';
    }
    if (m === 'confirm_award') {
        return 'This creates a documented contract moment — escrow funding follows only after you confirm.';
    }
    if (m === 'escrow') {
        return 'You are confirming funds are in escrow so the freelancer receives the official go-ahead.';
    }
    if (m === 'acknowledge') {
        return 'This step does not pay the freelancer — it only records that delivery happened.';
    }
    if (m === 'release') {
        return 'Please read each line carefully. Releasing escrow by mistake is difficult to reverse.';
    }
    if (m === 'withdraw') {
        return 'Use withdraw only while the client has not accepted. After acceptance, contact support to unwind.';
    }

    return '';
});

const modalPrimaryClass = computed(() => {
    const m = activeModal.value;
    if (m === 'decline' || m === 'withdraw') {
        return 'bg-rose-600 hover:bg-rose-700';
    }
    if (m === 'accept') {
        return 'bg-emerald-600 hover:bg-emerald-700';
    }
    if (m === 'escrow') {
        return 'bg-amber-600 hover:bg-amber-700';
    }
    if (m === 'acknowledge') {
        return 'bg-sky-600 hover:bg-sky-700';
    }
    if (m === 'release') {
        return 'bg-emerald-600 hover:bg-emerald-700';
    }

    return 'bg-primary-600 hover:bg-primary-700';
});

const modalSubmitDisabled = computed(() => {
    const m = activeModal.value;
    if (m === 'decline') {
        return declineForm.processing || !declineForm.confirm || !declineForm.understand_decline;
    }
    if (m === 'accept') {
        return acceptForm.processing || !acceptForm.confirm || !acceptForm.confirm_scope || !acceptForm.confirm_price || !acceptForm.confirm_deadline || !acceptForm.accept_escrow_rules || !acceptForm.accept_fees_and_terms || !acceptForm.accept_auto_release_ack;
    }
    if (m === 'confirm_award') {
        return confirmAwardForm.processing || !confirmAwardForm.confirm || !confirmAwardForm.confirm_scope || !confirmAwardForm.confirm_price || !confirmAwardForm.confirm_deadline;
    }
    if (m === 'escrow') {
        return escrowForm.processing || !escrowForm.confirm || !escrowForm.confirm_funds_in_escrow;
    }
    if (m === 'withdraw') {
        return withdrawForm.processing || !withdrawForm.confirm || !withdrawForm.understand_withdraw;
    }
    if (m === 'acknowledge') {
        return acknowledgeForm.processing || !acknowledgeForm.confirm;
    }
    if (m === 'release') {
        return releaseForm.processing || !releaseForm.confirm || !releaseForm.acknowledge_release;
    }

    return true;
});

function openModal(id) {
    activeModal.value = id;
    declineForm.clearErrors();
    acceptForm.clearErrors();
    confirmAwardForm.clearErrors();
    escrowForm.clearErrors();
    acknowledgeForm.clearErrors();
    releaseForm.clearErrors();
    withdrawForm.clearErrors();
    declineForm.reset();
    acceptForm.reset();
    confirmAwardForm.reset();
    escrowForm.reset();
    acknowledgeForm.reset();
    releaseForm.reset();
    withdrawForm.reset();
    if (id === 'accept') {
        acceptForm.revision_definition = 'A revision adjusts the agreed deliverable within the original scope. New features or material scope expansion require an amendment.';
        acceptDeliverables.value = buildSuggestedDeliverables();
    }
}

function buildSuggestedDeliverables() {
    const materials = Array.isArray(props.offer.materials) ? props.offer.materials : [];
    const fromMaterials = materials
        .map((m) => ({ title: String(m.label || '').trim(), description: '' }))
        .filter((m) => m.title);
    if (fromMaterials.length) {
        return fromMaterials;
    }
    const text = String(props.offer.scope_detail || props.offer.pitch || '').replace(/<[^>]+>/g, ' ').trim();
    const lines = text.split(/\r\n|\r|\n|(?:\s*[-•*]\s+)/).map((l) => l.replace(/^[-•*\d.)]+\s*/, '').trim()).filter((l) => l.length >= 8);
    if (lines.length) {
        return lines.slice(0, 12).map((title) => ({ title: title.slice(0, 120), description: '' }));
    }
    return [{ title: 'Deliver work as described in the accepted proposal and quest brief', description: '' }];
}

function closeModal() {
    activeModal.value = null;
}

function bool(v) {
    return !!v;
}

function toggleShortlist() {
    if (!canToggleShortlist.value) {
        return;
    }

    const wasShortlisted = localOfferStatus.value === 'shortlisted';
    localOfferStatus.value = wasShortlisted ? 'submitted' : 'shortlisted';

    axios
        .post(route('quests.proposals.toggle-shortlist', [props.quest.route_key, props.offer.id]), {}, {
            headers: { Accept: 'application/json' },
        })
        .then(({ data }) => {
            localOfferStatus.value = data.status || (data.shortlisted ? 'shortlisted' : 'submitted');
        })
        .catch(() => {
            localOfferStatus.value = wasShortlisted ? 'shortlisted' : 'submitted';
        });
}

function submitModal() {
    const m = activeModal.value;
    const rk = props.quest.route_key;
    const oid = props.offer.id;
    if (m === 'decline') {
        declineForm
            .transform(() => ({
                confirm: bool(declineForm.confirm),
                understand_decline: bool(declineForm.understand_decline),
            }))
            .post(route('quests.proposals.decline', [rk, oid]), { preserveScroll: true, onSuccess: closeModal });
    } else if (m === 'accept') {
        acceptForm
            .transform(() => ({
                confirm: bool(acceptForm.confirm),
                confirm_scope: bool(acceptForm.confirm_scope),
                confirm_price: bool(acceptForm.confirm_price),
                confirm_deadline: bool(acceptForm.confirm_deadline),
                accept_escrow_rules: bool(acceptForm.accept_escrow_rules),
                accept_fees_and_terms: bool(acceptForm.accept_fees_and_terms),
                accept_auto_release_ack: bool(acceptForm.accept_auto_release_ack),
                revision_definition: acceptForm.revision_definition,
                deliverables: acceptDeliverables.value.filter((d) => d.title.trim() !== ''),
            }))
            .post(route('quests.proposals.accept', [rk, oid]), { preserveScroll: true, onSuccess: closeModal });
    } else if (m === 'confirm_award') {
        confirmAwardForm
            .transform(() => ({
                confirm: bool(confirmAwardForm.confirm),
                confirm_scope: bool(confirmAwardForm.confirm_scope),
                confirm_price: bool(confirmAwardForm.confirm_price),
                confirm_deadline: bool(confirmAwardForm.confirm_deadline),
            }))
            .post(route('quests.proposals.confirm-award', [rk, oid]), { preserveScroll: true, onSuccess: closeModal });
    } else if (m === 'escrow') {
        escrowForm
            .transform(() => ({
                confirm: bool(escrowForm.confirm),
                confirm_funds_in_escrow: bool(escrowForm.confirm_funds_in_escrow),
            }))
            .post(route('quests.proposals.escrow-funded', [rk, oid]), { preserveScroll: true, onSuccess: closeModal });
    } else if (m === 'acknowledge') {
        const questKey = props.quest.route_key ?? props.quest.slug ?? props.quest.uuid ?? props.quest.id;
        acknowledgeForm
            .transform(() => ({ confirm: bool(acknowledgeForm.confirm) }))
            .post(route('quests.acknowledge-delivery', questKey), { preserveScroll: true, onSuccess: closeModal });
    } else if (m === 'release') {
        const questKey = props.quest.route_key ?? props.quest.slug ?? props.quest.uuid ?? props.quest.id;
        releaseForm
            .transform(() => ({
                confirm: bool(releaseForm.confirm),
                acknowledge_release: bool(releaseForm.acknowledge_release),
            }))
            .post(route('quests.release-funds', questKey), { preserveScroll: true, onSuccess: closeModal });
    } else if (m === 'withdraw') {
        withdrawForm
            .transform(() => ({
                confirm: bool(withdrawForm.confirm),
                understand_withdraw: bool(withdrawForm.understand_withdraw),
            }))
            .post(route('quests.proposals.withdraw', [rk, oid]), { onSuccess: closeModal });
    }
}

const pricingRows = computed(() => {
    const p = props.offer.pricing_snapshot || {};
    const vatLabel = p.vat_applies === false ? 'VAT (not applied)' : 'VAT';
    const rows = [
        { k: 'prof', label: 'Professional fee', v: p.professional_fee_minor },
        { k: 'mat', label: 'Materials', v: p.materials_total_minor },
        { k: 'travel', label: 'Travel', v: p.travel_cost_minor },
        { k: 'vat', label: vatLabel, v: p.vat_minor },
        { k: 'wht', label: 'Withholding tax', v: p.withholding_tax_minor },
        { k: 'stamp', label: 'Stamp duty', v: p.stamp_duty_minor },
        { k: 'plat', label: 'Platform / processing', v: p.platform_fee_minor },
        { k: 'disc', label: 'Discount', v: p.discount_minor },
        { k: 'grand', label: 'Grand total', v: p.grand_total_minor },
    ];

    return rows.filter((r) => {
        if (r.label === 'Grand total') {
            return true;
        }
        if (r.k === 'vat') {
            return true;
        }

        return r.v !== undefined && r.v !== null && Number(r.v) !== 0;
    });
});

const progressLabels = {
    daily: 'Daily',
    twice_weekly: 'Twice weekly',
    weekly: 'Weekly',
    biweekly: 'Bi-weekly',
    milestone_based: 'At milestones',
    on_request: 'On request',
};

function progressLabel(key) {
    return progressLabels[key] || key || '—';
}

function formatBudget(minor) {
    if (minor === undefined || minor === null) {
        return '—';
    }
    const n = Math.round(Number(minor) || 0) / 100;

    return `₦${n.toLocaleString('en-NG')}`;
}

function formatMinor(minor) {
    if (minor === undefined || minor === null) {
        return '—';
    }
    const n = Math.round(Number(minor)) / 100;

    return `₦${n.toLocaleString('en-NG')}`;
}

function formatWhen(iso) {
    try {
        return new Date(iso).toLocaleString('en-NG', { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'Africa/Lagos' });
    } catch {
        return '';
    }
}
</script>
