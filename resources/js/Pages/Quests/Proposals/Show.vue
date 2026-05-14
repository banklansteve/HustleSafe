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

            <div class="flex flex-wrap items-center justify-between gap-2">
                <BackChevronLink :href="route('quests.show', quest.route_key)" aria-label="Back to quest" />
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
                    Shortlist to signal interest, pin to keep it on your radar, or decide. Every action is confirmed so expectations stay crystal clear.
                </p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button
                        v-if="offer.status === 'submitted'"
                        type="button"
                        class="rounded-full bg-white px-4 py-2 text-xs font-black uppercase tracking-wide text-primary-900 shadow-sm ring-1 ring-primary-200 hover:bg-primary-50"
                        @click="openModal('shortlist')"
                    >
                        Shortlist
                    </button>
                    <button
                        v-if="offer.status === 'shortlisted'"
                        type="button"
                        class="rounded-full bg-white px-4 py-2 text-xs font-black uppercase tracking-wide text-primary-900 shadow-sm ring-1 ring-primary-200 hover:bg-primary-50"
                        @click="openModal('unshortlist')"
                    >
                        Remove shortlist
                    </button>
                    <button
                        type="button"
                        class="rounded-full bg-white px-4 py-2 text-xs font-black uppercase tracking-wide text-violet-900 shadow-sm ring-1 ring-violet-200 hover:bg-violet-50"
                        @click="openModal('pin')"
                    >
                        {{ offer.client_pinned_at ? 'Unpin' : 'Pin for later' }}
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
                        Accept proposal
                    </button>
                </div>
            </section>

            <section
                v-if="!observer_mode && is_client && offer.status === 'accepted' && quest.escrow_status === 'awaiting_funding'"
                class="rounded-2xl border border-amber-200/90 bg-amber-50/90 px-4 py-3 text-sm font-semibold text-amber-950 ring-1 ring-amber-100 sm:px-5"
            >
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-amber-900">Escrow funding</p>
                <p class="mt-1 text-xs leading-relaxed">
                    Fund escrow for
                    <span class="font-black">{{ formatBudget(offer.quoted_amount_minor) }}</span>
                    (including fees in the breakdown) before the freelancer is expected to start. Nothing is released to them until you mark the job
                    completed.
                </p>
                <button
                    type="button"
                    class="mt-3 rounded-full bg-amber-700 px-5 py-2 text-xs font-black uppercase tracking-wide text-white shadow-sm hover:bg-amber-800"
                    @click="openModal('escrow')"
                >
                    I have funded escrow
                </button>
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
                v-if="!observer_mode && (is_client || is_author)"
                :action-url="route('quests.proposals.reports.store', [quest.route_key, offer.id])"
                subtitle="Misleading quotes, harassment, or attempts to move payment off-platform should be reported. Our team triages by severity."
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
                        <template v-if="activeModal === 'shortlist'">
                            <label class="flex cursor-pointer items-start gap-3 text-sm font-semibold text-slate-800">
                                <input v-model="shortlistForm.confirm" type="checkbox" class="mt-1 rounded border-slate-300 text-primary-600 focus:ring-primary-500" />
                                <span>I want to shortlist this proposal and notify the freelancer.</span>
                            </label>
                            <InputError :message="shortlistForm.errors.confirm" />
                        </template>

                        <template v-else-if="activeModal === 'unshortlist'">
                            <label class="flex cursor-pointer items-start gap-3 text-sm font-semibold text-slate-800">
                                <input v-model="unshortlistForm.confirm" type="checkbox" class="mt-1 rounded border-slate-300 text-primary-600 focus:ring-primary-500" />
                                <span>I want to move this proposal back to the general list.</span>
                            </label>
                            <InputError :message="unshortlistForm.errors.confirm" />
                        </template>

                        <template v-else-if="activeModal === 'pin'">
                            <label class="flex cursor-pointer items-start gap-3 text-sm font-semibold text-slate-800">
                                <input v-model="pinForm.confirm" type="checkbox" class="mt-1 rounded border-slate-300 text-violet-600 focus:ring-violet-500" />
                                <span>{{ offer.client_pinned_at ? 'I want to remove the pin from this proposal.' : 'I want to pin this proposal to my review queue.' }}</span>
                            </label>
                            <InputError :message="pinForm.errors.confirm" />
                        </template>

                        <template v-else-if="activeModal === 'decline'">
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
                            <ul class="list-disc space-y-2 pl-4 text-xs font-semibold text-slate-700">
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
                                <span>I confirm I want to accept this proposal for this quest.</span>
                            </label>
                            <InputError :message="acceptForm.errors.confirm" />
                            <label class="flex cursor-pointer items-start gap-3 text-sm font-semibold text-slate-800">
                                <input v-model="acceptForm.accept_escrow_rules" type="checkbox" class="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                                <span>I understand I must fund escrow before the freelancer should begin work, and funds are released when I mark the job complete or, if I do not act in time, under the 72-hour rule described above.</span>
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

                        <template v-else-if="activeModal === 'withdraw'">
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
import BackChevronLink from '@/Components/Ui/BackChevronLink.vue';
import InputError from '@/Components/InputError.vue';
import UserProfileAvatar from '@/Components/Ui/UserProfileAvatar.vue';
import AppShell from '@/Layouts/AppShell.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ReLoader4Line } from '@kalimahapps/vue-icons/re';
import { computed, ref } from 'vue';

const props = defineProps({
    quest: { type: Object, required: true },
    offer: { type: Object, required: true },
    is_client: { type: Boolean, default: false },
    is_author: { type: Boolean, default: false },
    observer_mode: { type: Boolean, default: false },
    can_download_pdf: { type: Boolean, default: true },
    conversation_with_freelancer_url: { type: String, default: null },
});

const page = usePage();

const activeModal = ref(null);

const shortlistForm = useForm({ confirm: false });
const unshortlistForm = useForm({ confirm: false });
const pinForm = useForm({ confirm: false });
const declineForm = useForm({ confirm: false, understand_decline: false });
const acceptForm = useForm({
    confirm: false,
    accept_escrow_rules: false,
    accept_fees_and_terms: false,
    accept_auto_release_ack: false,
});
const escrowForm = useForm({ confirm: false, confirm_funds_in_escrow: false });
const withdrawForm = useForm({ confirm: false, understand_withdraw: false });

const anyModalFormProcessing = computed(
    () =>
        shortlistForm.processing
        || unshortlistForm.processing
        || pinForm.processing
        || declineForm.processing
        || acceptForm.processing
        || escrowForm.processing
        || withdrawForm.processing,
);

const clientDecisionOffer = computed(
    () => props.is_client && props.quest.status === 'open' && ['submitted', 'shortlisted'].includes(props.offer.status),
);

const statusLabel = computed(() => {
    const s = props.offer.status;
    const map = {
        submitted: 'Submitted',
        shortlisted: 'Shortlisted',
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

    return 'bg-slate-900';
});

const modalTitle = computed(() => {
    const m = activeModal.value;
    const titles = {
        shortlist: 'Shortlist proposal',
        unshortlist: 'Remove shortlist',
        pin: props.offer.client_pinned_at ? 'Unpin proposal' : 'Pin proposal',
        decline: 'Decline proposal',
        accept: 'Accept proposal',
        escrow: 'Confirm escrow funded',
        withdraw: 'Withdraw proposal',
    };

    return titles[m] || '';
});

const modalIntro = computed(() => {
    const m = activeModal.value;
    if (m === 'shortlist') {
        return 'Shortlisting is a soft signal — it helps freelancers prioritise your quest without locking you in.';
    }
    if (m === 'unshortlist') {
        return 'This keeps the quest tidy if you change your mind before a final decision.';
    }
    if (m === 'pin') {
        return props.offer.client_pinned_at
            ? 'Remove the highlight from your review queue.'
            : 'Pin keeps this proposal visually starred while you compare options.';
    }
    if (m === 'decline') {
        return 'Declining is permanent for this proposal. Other proposals remain untouched.';
    }
    if (m === 'accept') {
        return 'Accepting assigns this freelancer and starts the escrow funding step for everyone’s protection.';
    }
    if (m === 'escrow') {
        return 'You are confirming funds are in escrow so the freelancer receives the official go-ahead.';
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

    return 'bg-primary-600 hover:bg-primary-700';
});

const modalSubmitDisabled = computed(() => {
    const m = activeModal.value;
    if (m === 'shortlist') {
        return shortlistForm.processing || !shortlistForm.confirm;
    }
    if (m === 'unshortlist') {
        return unshortlistForm.processing || !unshortlistForm.confirm;
    }
    if (m === 'pin') {
        return pinForm.processing || !pinForm.confirm;
    }
    if (m === 'decline') {
        return declineForm.processing || !declineForm.confirm || !declineForm.understand_decline;
    }
    if (m === 'accept') {
        return acceptForm.processing || !acceptForm.confirm || !acceptForm.accept_escrow_rules || !acceptForm.accept_fees_and_terms || !acceptForm.accept_auto_release_ack;
    }
    if (m === 'escrow') {
        return escrowForm.processing || !escrowForm.confirm || !escrowForm.confirm_funds_in_escrow;
    }
    if (m === 'withdraw') {
        return withdrawForm.processing || !withdrawForm.confirm || !withdrawForm.understand_withdraw;
    }

    return true;
});

function openModal(id) {
    activeModal.value = id;
    shortlistForm.clearErrors();
    unshortlistForm.clearErrors();
    pinForm.clearErrors();
    declineForm.clearErrors();
    acceptForm.clearErrors();
    escrowForm.clearErrors();
    withdrawForm.clearErrors();
    shortlistForm.reset('confirm');
    unshortlistForm.reset('confirm');
    pinForm.reset('confirm');
    declineForm.reset();
    acceptForm.reset();
    escrowForm.reset();
    withdrawForm.reset();
}

function closeModal() {
    activeModal.value = null;
}

function bool(v) {
    return !!v;
}

function submitModal() {
    const m = activeModal.value;
    const rk = props.quest.route_key;
    const oid = props.offer.id;
    if (m === 'shortlist') {
        shortlistForm.transform(() => ({ confirm: bool(shortlistForm.confirm) })).post(route('quests.proposals.shortlist', [rk, oid]), {
            preserveScroll: true,
            onSuccess: closeModal,
        });
    } else if (m === 'unshortlist') {
        unshortlistForm.transform(() => ({ confirm: bool(unshortlistForm.confirm) })).post(route('quests.proposals.unshortlist', [rk, oid]), {
            preserveScroll: true,
            onSuccess: closeModal,
        });
    } else if (m === 'pin') {
        pinForm.transform(() => ({ confirm: bool(pinForm.confirm) })).post(route('quests.proposals.pin', [rk, oid]), {
            preserveScroll: true,
            onSuccess: closeModal,
        });
    } else if (m === 'decline') {
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
                accept_escrow_rules: bool(acceptForm.accept_escrow_rules),
                accept_fees_and_terms: bool(acceptForm.accept_fees_and_terms),
                accept_auto_release_ack: bool(acceptForm.accept_auto_release_ack),
            }))
            .post(route('quests.proposals.accept', [rk, oid]), { preserveScroll: true, onSuccess: closeModal });
    } else if (m === 'escrow') {
        escrowForm
            .transform(() => ({
                confirm: bool(escrowForm.confirm),
                confirm_funds_in_escrow: bool(escrowForm.confirm_funds_in_escrow),
            }))
            .post(route('quests.proposals.escrow-funded', [rk, oid]), { preserveScroll: true, onSuccess: closeModal });
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
