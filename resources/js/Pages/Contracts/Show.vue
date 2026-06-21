<template>
    <AppShell>
        <Head :title="`Contract · ${contract.reference_code}`" />

        <div class="mx-auto max-w-4xl space-y-6 pb-10">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <BackChevronLink :href="contract.quest_url || route('contracts.index')" aria-label="Back" />
                <Link
                    :href="route('contracts.index')"
                    class="text-xs font-black uppercase tracking-wide text-primary-800 underline underline-offset-2"
                >
                    All contracts
                </Link>
            </div>

            <div
                v-if="contract.status === 'disputed'"
                class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-950 ring-1 ring-rose-100"
            >
                This contract is under dispute. Escrow is frozen while the case is reviewed.
                <Link v-if="contract.dispute_url" :href="contract.dispute_url" class="ml-1 font-black underline">Open dispute case</Link>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <section
                    v-if="role.is_client && contract.escrow?.is_held"
                    class="rounded-2xl border border-amber-200/90 bg-amber-50/90 p-4 ring-1 ring-amber-100 sm:p-5"
                >
                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-amber-900">Escrow held</p>
                    <p class="font-display mt-1 text-2xl font-black text-amber-950">{{ contract.financial.total_label }}</p>
                    <p class="mt-2 text-xs font-semibold leading-relaxed text-amber-950/90">
                        These funds are securely held until you mark the job complete or escrow auto-releases per your contract timeline.
                        Read our
                        <Link :href="route('legal.escrow')" class="font-black text-amber-950 underline underline-offset-2">Escrow Policy</Link>.
                    </p>
                </section>

                <section
                    v-else-if="role.is_client && contract.escrow?.awaiting_funding"
                    class="rounded-2xl border border-amber-200/90 bg-amber-50/90 p-4 ring-1 ring-amber-100 sm:p-5"
                >
                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-amber-900">Escrow funding</p>
                    <p class="font-display mt-1 text-2xl font-black text-amber-950">{{ contract.financial.total_label }}</p>
                    <p class="mt-2 text-xs font-semibold leading-relaxed text-amber-950/90">
                        Fund escrow for this contract before work is expected to start. Nothing is released to the freelancer until you mark the job complete.
                        Read our
                        <Link :href="route('legal.escrow')" class="font-black text-amber-950 underline underline-offset-2">Escrow Policy</Link>.
                    </p>
                    <p v-if="contract.escrow_expires_at" class="mt-2 text-xs font-bold text-amber-900">
                        Fund by {{ formatWhen(contract.escrow_expires_at) }}
                    </p>
                    <form
                        v-if="contract.escrow?.show_fund_button && contract.escrow?.funding_post_url"
                        :action="contract.escrow.funding_post_url"
                        method="POST"
                        class="mt-3"
                    >
                        <input type="hidden" name="_token" :value="csrfToken" />
                        <button
                            type="submit"
                            class="inline-flex items-center rounded-full bg-emerald-700 px-4 py-2 text-xs font-black uppercase tracking-wide text-white shadow-sm hover:bg-emerald-800"
                        >
                            Pay with Paystack
                        </button>
                    </form>
                    <Link
                        v-else-if="contract.escrow?.proposal_url"
                        :href="contract.escrow.proposal_url"
                        class="mt-3 inline-flex items-center rounded-full bg-emerald-700 px-4 py-2 text-xs font-black uppercase tracking-wide text-white shadow-sm hover:bg-emerald-800"
                    >
                        Fund escrow
                    </Link>
                </section>

                <section
                    v-if="role.is_freelancer"
                    class="rounded-2xl border border-emerald-200/90 bg-emerald-50/90 p-4 ring-1 ring-emerald-100 sm:p-5"
                >
                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-emerald-900">Your net payout</p>
                    <p class="font-display mt-1 text-2xl font-black text-emerald-950">{{ contract.financial.freelancer_net_label }}</p>
                    <p v-if="deliveryCountdown.active" class="mt-2 text-xs font-bold text-emerald-900">
                        Delivery deadline · {{ deliveryCountdown.deadline_label }} · {{ countdownLabel(deliveryCountdown.seconds_remaining) }} left
                    </p>
                    <p v-if="autoRelease.visible" class="mt-2 text-xs font-semibold text-emerald-950/90">
                        Auto-release · {{ autoRelease.auto_release_label }}
                        <span v-if="autoRelease.active"> · {{ countdownLabel(liveAutoReleaseSeconds) }} left</span>
                    </p>
                    <p class="mt-2 text-xs font-semibold text-emerald-950/90">
                        Revisions · {{ contract.revisions_used }} of {{ contract.revisions_included }} used
                    </p>
                </section>
            </div>

            <header class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <HustleSafeLogo variant="lockup" theme="light" lockup-class="h-8 w-auto max-w-[10rem]" />
                        <p class="mt-3 text-[10px] font-black uppercase tracking-[0.22em] text-slate-500">Service agreement</p>
                        <h1 class="font-display mt-1 text-xl font-black text-slate-900 sm:text-2xl">{{ contract.reference_code }}</h1>
                    </div>
                    <span class="rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-wide ring-1" :class="statusClass(contract.status)">
                        {{ contract.status_label }}
                    </span>
                </div>
            </header>

            <article class="space-y-6 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-8">
                <section class="border-t border-slate-200 pt-6 first:border-t-0 first:pt-0">
                    <h2 class="font-display text-xs font-black uppercase tracking-[0.18em] text-slate-500">Parties</h2>
                    <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4">
                            <dt class="text-[10px] font-black uppercase tracking-wide text-slate-500">Client</dt>
                            <dd class="mt-1 text-sm font-bold text-slate-900">{{ contract.parties.client.full_name }}</dd>
                            <dd class="text-xs font-semibold text-slate-600">@{{ contract.parties.client.username }} · ID {{ contract.parties.client.user_id }}</dd>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4">
                            <dt class="text-[10px] font-black uppercase tracking-wide text-slate-500">Freelancer</dt>
                            <dd class="mt-1 text-sm font-bold text-slate-900">{{ contract.parties.freelancer.full_name }}</dd>
                            <dd class="text-xs font-semibold text-slate-600">@{{ contract.parties.freelancer.username }} · ID {{ contract.parties.freelancer.user_id }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="border-t border-slate-200 pt-6">
                    <h2 class="font-display text-xs font-black uppercase tracking-[0.18em] text-slate-500">Quest details</h2>
                    <dl class="mt-4 space-y-2 text-sm font-semibold text-slate-800">
                        <div class="flex flex-wrap justify-between gap-2 border-b border-slate-100 py-2">
                            <dt class="text-slate-500">Title</dt>
                            <dd class="font-bold text-slate-900">{{ contract.quest.title }}</dd>
                        </div>
                        <div class="flex flex-wrap justify-between gap-2 border-b border-slate-100 py-2">
                            <dt class="text-slate-500">Reference</dt>
                            <dd>{{ contract.quest.reference_code }}</dd>
                        </div>
                        <div class="flex flex-wrap justify-between gap-2 border-b border-slate-100 py-2">
                            <dt class="text-slate-500">Category</dt>
                            <dd>{{ contract.quest.category || '—' }}</dd>
                        </div>
                    </dl>
                    <p class="mt-4 text-sm leading-relaxed text-slate-700">{{ contract.quest.scope_description }}</p>
                    <h3 class="mt-4 text-xs font-black uppercase tracking-wide text-slate-500">Deliverables</h3>
                    <ol class="mt-2 list-decimal space-y-2 pl-5 text-sm font-semibold text-slate-800">
                        <li v-for="(d, i) in contract.deliverables" :key="i">
                            {{ d.title }}
                            <span v-if="d.description" class="block text-xs font-medium text-slate-600">{{ d.description }}</span>
                        </li>
                    </ol>
                </section>

                <section class="border-t border-slate-200 pt-6">
                    <h2 class="font-display text-xs font-black uppercase tracking-[0.18em] text-slate-500">Financial terms</h2>
                    <dl class="mt-4 space-y-2 text-sm font-semibold text-slate-800">
                        <div class="flex justify-between border-b border-slate-100 py-2"><dt>Total contract value</dt><dd class="font-black">{{ contract.financial.total_label }}</dd></div>
                        <div class="flex justify-between border-b border-slate-100 py-2"><dt>Platform service fee</dt><dd>{{ contract.financial.platform_fee_label }} ({{ contract.financial.platform_fee_percent }}%)</dd></div>
                        <div class="flex justify-between border-b border-slate-100 py-2"><dt>Freelancer net payout</dt><dd class="font-black text-emerald-800">{{ contract.financial.freelancer_net_label }}</dd></div>
                    </dl>
                    <PlatformFeeDisclosureNote class="mt-4" :platform-fee-percent="contract.financial.platform_fee_percent" compact />
                </section>

                <section class="border-t border-slate-200 pt-6">
                    <h2 class="font-display text-xs font-black uppercase tracking-[0.18em] text-slate-500">Timeline</h2>
                    <dl class="mt-4 space-y-2 text-sm font-semibold text-slate-800">
                        <div class="flex justify-between border-b border-slate-100 py-2"><dt>Contract generated</dt><dd>{{ formatWhen(contract.generated_at) }}</dd></div>
                        <div class="flex justify-between border-b border-slate-100 py-2"><dt>Contract start</dt><dd>{{ contract.activated_at ? formatWhen(contract.activated_at) : 'Pending escrow funding' }}</dd></div>
                        <div v-if="contract.delivery_timeline?.has_extension" class="border-b border-slate-100 py-2">
                            <dt class="text-slate-500">Original agreed delivery</dt>
                            <dd class="mt-1 line-through text-slate-400">{{ contract.delivery_timeline.original_deadline_label }}</dd>
                            <dt class="mt-2 text-slate-500">Current agreed delivery</dt>
                            <dd class="mt-1 font-bold text-slate-900">{{ contract.delivery_timeline.current_deadline_label }}</dd>
                            <dd v-if="contract.delivery_timeline.extension_badge" class="mt-2">
                                <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-amber-900 ring-1 ring-amber-200">
                                    {{ contract.delivery_timeline.extension_badge }}
                                </span>
                            </dd>
                            <ul v-if="contract.delivery_timeline.history?.length" class="mt-3 space-y-2 text-xs text-slate-600">
                                <li v-for="(ext, idx) in contract.delivery_timeline.history" :key="idx">
                                    Extension #{{ ext.extension_number }} · {{ ext.original_label }} → {{ ext.new_label }}
                                    <span v-if="ext.reason_label" class="block font-semibold text-slate-500">Reason: {{ ext.reason_label }}</span>
                                </li>
                            </ul>
                        </div>
                        <div v-else class="flex justify-between border-b border-slate-100 py-2"><dt>Agreed delivery date</dt><dd>{{ contract.timeline.agreed_delivery_label || '—' }}</dd></div>
                    </dl>
                    <p class="mt-3 rounded-xl border border-slate-100 bg-slate-50/80 p-3 text-xs font-semibold leading-relaxed text-slate-700">
                        {{ contract.timeline.auto_release_plain_english }}
                    </p>
                </section>

                <section class="border-t border-slate-200 pt-6">
                    <h2 class="font-display text-xs font-black uppercase tracking-[0.18em] text-slate-500">Revision policy</h2>
                    <p class="mt-4 text-sm font-semibold text-slate-800">{{ contract.revisions_included }} revisions included</p>
                    <p class="mt-2 text-sm leading-relaxed text-slate-700">{{ contract.revision_policy.revision_definition }}</p>
                </section>

                <section v-if="contract.amendments.length" class="border-t border-slate-200 pt-6">
                    <h2 class="font-display text-xs font-black uppercase tracking-[0.18em] text-slate-500">Amendment history</h2>
                    <div v-for="a in contract.amendments" :key="a.id" class="mb-4 mt-4 border-l-4 border-primary-400 pl-4">
                        <p class="text-xs font-black uppercase tracking-wide text-slate-500">Amendment #{{ a.amendment_number }} · {{ a.type_label }}</p>
                        <p class="mt-1 text-sm font-semibold text-slate-800">{{ a.description }}</p>
                        <p v-if="a.original_value || a.new_value" class="mt-1 text-xs text-slate-600">
                            Original: {{ a.original_value || '—' }} · New: {{ a.new_value || '—' }}
                        </p>
                    </div>
                </section>

                <section class="border-t border-slate-200 pt-6">
                    <h2 class="font-display text-xs font-black uppercase tracking-[0.18em] text-slate-500">Platform terms</h2>
                    <div class="mt-4 flex flex-wrap gap-x-4 gap-y-2 text-sm font-semibold">
                        <a :href="contract.platform_terms.terms_url || route('legal.terms')" target="_blank" rel="noopener noreferrer" class="font-black text-primary-800 underline">Terms of Service</a>
                        <a :href="contract.platform_terms.escrow_url || route('legal.escrow')" target="_blank" rel="noopener noreferrer" class="font-black text-primary-800 underline">Escrow Policy</a>
                        <a :href="contract.platform_terms.dispute_url || route('legal.dispute')" target="_blank" rel="noopener noreferrer" class="font-black text-primary-800 underline">Dispute Policy</a>
                        <a :href="contract.platform_terms.privacy_url || route('legal.privacy')" target="_blank" rel="noopener noreferrer" class="font-black text-primary-800 underline">Privacy Policy</a>
                    </div>
                    <ul class="mt-3 list-disc space-y-2 pl-5 text-sm font-semibold text-slate-700">
                        <li v-for="(clause, i) in contract.platform_terms.clauses" :key="i">{{ clause }}</li>
                    </ul>
                </section>

                <section class="border-t border-slate-200 pt-6">
                    <h2 class="font-display text-xs font-black uppercase tracking-[0.18em] text-slate-500">Signatures</h2>
                    <div class="mt-4 grid gap-3 sm:grid-cols-3">
                        <div v-for="key in ['client', 'freelancer', 'platform']" :key="key" class="rounded-xl border border-slate-200 bg-slate-50/60 p-4">
                            <p class="text-[10px] font-black uppercase tracking-wide text-slate-500">{{ key }}</p>
                            <p class="mt-2 text-sm font-bold text-slate-900">{{ contract.signatures[key]?.name || '—' }}</p>
                            <p class="mt-1 text-xs font-semibold text-slate-600">{{ contract.signatures[key]?.action || '' }}</p>
                            <p class="mt-1 text-[10px] font-semibold text-slate-500">{{ formatWhen(contract.signatures[key]?.confirmed_at) }}</p>
                        </div>
                    </div>
                </section>
            </article>

            <section class="flex flex-wrap gap-3">
                <a
                    :href="route('contracts.pdf', contract.reference_code)"
                    class="inline-flex items-center rounded-full border-2 border-slate-200 bg-white px-4 py-2 text-xs font-black uppercase tracking-wide text-slate-800 shadow-sm transition hover:border-primary-200"
                >
                    Download PDF
                </a>
                <button
                    v-if="permissions.can_request_amendment"
                    type="button"
                    class="inline-flex items-center rounded-full bg-primary-700 px-4 py-2 text-xs font-black uppercase tracking-wide text-white hover:bg-primary-800"
                    @click="showAmendmentForm = true"
                >
                    Request amendment
                </button>
                <button
                    v-else-if="contract.amendment_count >= contract.amendment_limit && contract.status === 'active'"
                    type="button"
                    disabled
                    class="inline-flex cursor-not-allowed items-center rounded-full bg-slate-200 px-4 py-2 text-xs font-black uppercase tracking-wide text-slate-500"
                >
                    Amendment limit reached
                </button>
            </section>

            <section v-if="pending_amendment" class="rounded-2xl border border-sky-200 bg-sky-50/80 p-5 ring-1 ring-sky-100">
                <h2 class="font-display text-sm font-black uppercase tracking-wide text-sky-900">Amendment awaiting your response</h2>
                <p class="mt-2 text-sm font-semibold text-sky-950">{{ pending_amendment.type_label }} · {{ pending_amendment.description }}</p>
                <textarea v-if="showDeclineNote" v-model="respondForm.response_note" rows="3" class="mt-3 w-full rounded-xl border-slate-200 text-sm shadow-sm" placeholder="Mandatory note if declining" />
                <div class="mt-4 flex flex-wrap gap-2">
                    <button type="button" class="rounded-full bg-emerald-700 px-4 py-2 text-xs font-black uppercase text-white" :disabled="respondForm.processing" @click="respondAmendment('accept')">Accept</button>
                    <button type="button" class="rounded-full bg-rose-700 px-4 py-2 text-xs font-black uppercase text-white" :disabled="respondForm.processing" @click="respondAmendment('decline')">Decline</button>
                </div>
            </section>

            <section
                v-if="role.is_client && contract.escrow?.is_held && autoRelease.visible"
                class="rounded-2xl border border-sky-200/90 bg-gradient-to-br from-sky-50/95 to-white p-4 ring-1 ring-sky-100 sm:p-5"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-sky-900">Escrow auto-release</p>
                        <p v-if="autoRelease.phase === 'paused'" class="mt-2 text-sm font-bold text-amber-900">
                            {{ autoRelease.pause_reason }}
                        </p>
                        <p v-else-if="autoRelease.active" class="mt-2 font-display text-2xl font-black text-sky-950">
                            {{ countdownLabel(liveAutoReleaseSeconds) }} remaining
                        </p>
                        <p v-else class="mt-2 text-sm font-bold text-sky-950">
                            Scheduled for {{ autoRelease.auto_release_label }}
                        </p>
                    </div>
                    <span
                        v-if="autoRelease.active"
                        class="rounded-full bg-rose-100 px-3 py-1 text-[10px] font-black uppercase tracking-wide text-rose-800 ring-1 ring-rose-200"
                    >
                        Countdown active
                    </span>
                </div>

                <dl class="mt-4 grid gap-2 text-xs font-semibold text-sky-950/90 sm:grid-cols-2">
                    <div class="rounded-xl border border-sky-100 bg-white/70 px-3 py-2">
                        <dt class="text-[10px] font-black uppercase text-sky-800">Expected delivery</dt>
                        <dd class="mt-0.5 font-black text-sky-950">{{ autoRelease.expected_delivery_label }}</dd>
                    </div>
                    <div class="rounded-xl border border-sky-100 bg-white/70 px-3 py-2">
                        <dt class="text-[10px] font-black uppercase text-sky-800">Auto-release window</dt>
                        <dd class="mt-0.5 font-black text-sky-950">{{ autoRelease.auto_release_hours }} hours after delivery date</dd>
                    </div>
                    <div class="rounded-xl border border-sky-100 bg-white/70 px-3 py-2 sm:col-span-2">
                        <dt class="text-[10px] font-black uppercase text-sky-800">Exact auto-release date & time</dt>
                        <dd class="mt-0.5 font-black text-sky-950">{{ autoRelease.auto_release_label }} (WAT)</dd>
                    </div>
                </dl>

                <p class="mt-3 rounded-xl border border-sky-100 bg-white/60 p-3 text-xs font-semibold leading-relaxed text-sky-950/90">
                    If you do not mark this job complete, escrow will auto-release to the freelancer
                    <span class="font-black">{{ autoRelease.auto_release_hours }} hours</span>
                    after the agreed delivery date
                    (<span class="font-black">{{ autoRelease.expected_delivery_label }}</span>),
                    unless you open a dispute before then.
                </p>

                <div class="mt-4 flex flex-wrap gap-2">
                    <Link
                        v-if="autoRelease.quest_url"
                        :href="autoRelease.quest_url"
                        class="inline-flex items-center rounded-full bg-emerald-700 px-4 py-2 text-[10px] font-black uppercase tracking-wide text-white hover:bg-emerald-800"
                    >
                        Mark job complete
                    </Link>
                    <Link
                        v-if="autoRelease.active_dispute"
                        :href="autoRelease.active_dispute.url"
                        class="inline-flex items-center rounded-full border border-rose-200 bg-rose-50 px-4 py-2 text-[10px] font-black uppercase tracking-wide text-rose-900 hover:bg-rose-100"
                    >
                        Open dispute case
                    </Link>
                    <Link
                        v-else-if="autoRelease.can_open_dispute && autoRelease.dispute_create_url"
                        :href="autoRelease.dispute_create_url"
                        class="inline-flex items-center rounded-full border border-rose-200 bg-white px-4 py-2 text-[10px] font-black uppercase tracking-wide text-rose-900 hover:bg-rose-50"
                    >
                        Raise a dispute
                    </Link>
                    <button
                        type="button"
                        class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-4 py-2 text-[10px] font-black uppercase tracking-wide text-amber-950 hover:bg-amber-100"
                        @click="scrollToDeliveryExtension"
                    >
                        {{ autoRelease.has_pending_extension ? 'Reply to date change' : 'Change finish date' }}
                    </button>
                </div>
                <p v-if="autoRelease.dispute_block_reason" class="mt-2 text-[10px] font-semibold text-slate-500">{{ autoRelease.dispute_block_reason }}</p>
            </section>

            <section
                v-if="showDeliveryExtensionsPanel"
                id="delivery-extension"
                class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm ring-1 ring-slate-100"
            >
                <div class="border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white px-4 py-4 sm:px-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Finish date</p>
                            <h2 class="font-display mt-1 text-lg font-black text-slate-900">Change finish date</h2>
                            <p class="mt-1 text-xs font-semibold text-slate-600">
                                Workers can ask for more time or to finish sooner. You must approve before the date changes.
                            </p>
                        </div>
                        <div class="text-right text-xs font-semibold text-slate-600">
                            <p class="font-black text-slate-900">{{ delivery_extension.summary?.current_deadline_label || '—' }}</p>
                            <p>Current deadline</p>
                            <p class="mt-2 text-[10px] font-black uppercase tracking-wide text-slate-500">
                                {{ delivery_extension.summary?.adjustment_limit_label || `${delivery_extension.summary?.extension_count || 0} / ${delivery_extension.summary?.extension_limit || 2} used` }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="space-y-4 p-4 sm:p-5">
                    <div
                        v-if="delivery_extension.pending && role.is_client"
                        class="rounded-2xl border-2 border-amber-300 bg-gradient-to-br from-amber-50 to-white p-4 ring-1 ring-amber-100"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-wide text-amber-900">Your reply needed</p>
                                <h3 class="font-display text-base font-black text-amber-950">
                                    {{ delivery_extension.pending.adjustment_type_label || 'Date change' }} request
                                </h3>
                            </div>
                            <span class="rounded-full bg-amber-200/90 px-2.5 py-1 text-[10px] font-black uppercase text-amber-950">
                                #{{ delivery_extension.pending.extension_number }}
                            </span>
                        </div>
                        <p class="mt-2 text-xs font-bold text-amber-800">
                            Respond within {{ countdownLabel(delivery_extension.pending.client_seconds_remaining) }}
                            · by {{ delivery_extension.pending.client_deadline_label }}
                        </p>
                        <dl class="mt-3 grid gap-2 text-sm font-semibold text-amber-950 sm:grid-cols-3">
                            <div class="rounded-xl border border-amber-100 bg-white/80 px-3 py-2">
                                <dt class="text-[10px] font-black uppercase text-amber-800">From</dt>
                                <dd class="font-black">{{ delivery_extension.pending.original_delivery_label }}</dd>
                            </div>
                            <div class="rounded-xl border border-amber-100 bg-white/80 px-3 py-2">
                                <dt class="text-[10px] font-black uppercase text-amber-800">Requested</dt>
                                <dd class="font-black">{{ delivery_extension.pending.proposed_delivery_label }}</dd>
                            </div>
                            <div class="rounded-xl border border-amber-100 bg-white/80 px-3 py-2">
                                <dt class="text-[10px] font-black uppercase text-amber-800">Reason</dt>
                                <dd>{{ delivery_extension.pending.reason_label }}</dd>
                            </div>
                        </dl>
                        <p class="mt-3 rounded-xl border border-amber-100 bg-white/70 p-3 text-sm leading-relaxed text-amber-950">{{ delivery_extension.pending.explanation }}</p>
                        <p v-if="delivery_extension.pending.progress_note" class="mt-2 text-xs font-semibold text-amber-900">
                            Progress: {{ delivery_extension.pending.progress_note }}
                        </p>
                        <p class="mt-2 text-[11px] font-semibold text-amber-800">If you do not reply within 48 hours, the new date is accepted automatically.</p>
                        <div v-if="showCounterForm" class="mt-4">
                            <label class="text-xs font-black uppercase text-amber-900">Counter-proposed date</label>
                            <input v-model="extensionRespondForm.counter_proposed_date" type="date" class="mt-1 w-full rounded-xl border-amber-200 text-sm shadow-sm" />
                        </div>
                        <textarea v-if="showExtensionDecline" v-model="extensionRespondForm.decline_reason" rows="3" class="mt-4 w-full rounded-xl border-amber-200 text-sm shadow-sm" placeholder="Mandatory reason if declining" />
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button type="button" class="rounded-full bg-emerald-700 px-4 py-2 text-xs font-black uppercase text-white" :disabled="extensionRespondForm.processing" @click="respondExtension('accept')">Accept</button>
                            <button type="button" class="rounded-full bg-sky-700 px-4 py-2 text-xs font-black uppercase text-white" :disabled="extensionRespondForm.processing" @click="toggleCounterForm">Counter-propose</button>
                            <button v-if="showCounterForm" type="button" class="rounded-full bg-sky-900 px-4 py-2 text-xs font-black uppercase text-white" :disabled="extensionRespondForm.processing" @click="respondExtension('counter')">Submit counter</button>
                            <button type="button" class="rounded-full bg-rose-700 px-4 py-2 text-xs font-black uppercase text-white" :disabled="extensionRespondForm.processing" @click="respondExtension('decline')">Decline</button>
                        </div>
                    </div>

                    <div
                        v-else-if="delivery_extension.pending_counter && role.is_freelancer"
                        class="rounded-2xl border-2 border-sky-300 bg-gradient-to-br from-sky-50 to-white p-4 ring-1 ring-sky-100"
                    >
                        <p class="text-[10px] font-black uppercase tracking-wide text-sky-900">Action required</p>
                        <h3 class="font-display text-base font-black text-sky-950">Client counter-proposal</h3>
                        <p class="mt-2 text-sm font-semibold text-sky-950">
                            Client proposed <span class="font-black">{{ delivery_extension.pending_counter.counter_proposed_label }}</span>
                            instead of your <span class="font-black">{{ delivery_extension.pending_counter.proposed_delivery_label }}</span>.
                        </p>
                        <p class="mt-2 text-xs font-bold text-sky-800">
                            Respond within {{ countdownLabel(delivery_extension.pending_counter.freelancer_seconds_remaining) }}
                            <span v-if="delivery_extension.pending_counter.freelancer_deadline_label"> · by {{ delivery_extension.pending_counter.freelancer_deadline_label }}</span>
                        </p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button type="button" class="rounded-full bg-emerald-700 px-4 py-2 text-xs font-black uppercase text-white" :disabled="counterRespondForm.processing" @click="respondCounter('accept')">Accept counter</button>
                            <button type="button" class="rounded-full bg-rose-700 px-4 py-2 text-xs font-black uppercase text-white" :disabled="counterRespondForm.processing" @click="respondCounter('decline')">Decline counter</button>
                        </div>
                    </div>

                    <div
                        v-else-if="delivery_extension.pending && role.is_freelancer"
                        class="rounded-2xl border border-amber-200 bg-amber-50/60 px-4 py-3 text-sm font-semibold text-amber-950"
                    >
                        <p class="text-[10px] font-black uppercase tracking-wide text-amber-900">Awaiting client</p>
                        <p class="mt-1">
                            Extension #{{ delivery_extension.pending.extension_number }} is under client review
                            ({{ delivery_extension.pending.proposed_delivery_label }} requested).
                            Escrow auto-release is paused until they respond.
                        </p>
                    </div>

                    <div v-if="extensionHistoryRows.length" class="space-y-3">
                        <p class="text-[10px] font-black uppercase tracking-wide text-slate-500">Extension history</p>
                        <article
                            v-for="ext in extensionHistoryRows"
                            :key="ext.id"
                            class="rounded-xl border p-4 transition"
                            :class="ext.is_active ? 'border-amber-300 bg-amber-50/40 ring-1 ring-amber-200' : 'border-slate-100 bg-slate-50/50'"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-wide text-slate-500">Extension #{{ ext.extension_number }}</p>
                                    <p class="text-sm font-bold text-slate-900">{{ ext.requester_name || 'Freelancer' }}</p>
                                    <p class="text-[11px] font-semibold text-slate-500">Submitted {{ ext.submitted_at_label || '—' }}</p>
                                </div>
                                <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wide ring-1" :class="extensionStatusClass(ext.status)">
                                    {{ ext.status_label }}
                                </span>
                            </div>
                            <div class="mt-3 flex flex-wrap items-center gap-2 text-xs font-bold text-slate-700">
                                <span class="rounded-lg bg-white px-2 py-1 ring-1 ring-slate-200">{{ ext.original_delivery_label }}</span>
                                <span class="text-slate-400">→</span>
                                <span class="rounded-lg bg-white px-2 py-1 ring-1 ring-slate-200">{{ ext.proposed_delivery_label }}</span>
                                <span v-if="ext.applied_delivery_label && ext.is_terminal" class="text-emerald-700">
                                    · Applied {{ ext.applied_delivery_label }}
                                </span>
                            </div>
                            <p class="mt-2 text-xs font-semibold text-slate-600">{{ ext.reason_label }}</p>
                            <p v-if="ext.explanation" class="mt-2 line-clamp-3 text-xs leading-relaxed text-slate-600">{{ ext.explanation }}</p>
                            <p v-if="ext.decline_reason" class="mt-2 text-xs font-semibold text-rose-700">Declined: {{ ext.decline_reason }}</p>
                            <p v-if="ext.resolved_at_label" class="mt-2 text-[10px] font-semibold text-slate-400">Resolved {{ ext.resolved_at_label }}</p>
                        </article>
                    </div>

                    <p v-else-if="!delivery_extension.pending && !delivery_extension.pending_counter" class="rounded-xl border border-dashed border-slate-200 bg-slate-50/80 px-4 py-6 text-center text-sm font-semibold text-slate-500">
                        No finish-date changes yet.
                        <span v-if="role.is_freelancer"> You can ask for more time or to finish sooner — your client must agree first.</span>
                        <span v-else> Your worker can request a new finish date when needed.</span>
                    </p>

                    <div v-if="role.is_freelancer" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
                        <p class="text-xs font-semibold text-slate-600">
                            {{ delivery_extension.summary?.remaining || 0 }} date change(s) left on this job.
                        </p>
                        <button
                            v-if="delivery_extension.freelancer_button?.can_request"
                            type="button"
                            class="inline-flex items-center rounded-full px-4 py-2 text-xs font-black uppercase tracking-wide text-white transition"
                            :class="delivery_extension.freelancer_button?.button_tone === 'amber' ? 'bg-amber-600 hover:bg-amber-700' : 'bg-primary-700 hover:bg-primary-800'"
                            @click="openExtensionForm"
                        >
                            {{ delivery_extension.freelancer_button?.button_label }}
                        </button>
                        <button
                            v-else
                            type="button"
                            disabled
                            class="inline-flex cursor-not-allowed items-center rounded-full bg-slate-200 px-4 py-2 text-xs font-black uppercase tracking-wide text-slate-500"
                            :title="delivery_extension.freelancer_button?.reason || ''"
                        >
                            {{ delivery_extension.freelancer_button?.button_label || 'Change finish date' }}
                        </button>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6">
                <h2 class="font-display text-sm font-black uppercase tracking-wide text-slate-500">Contract timeline</h2>
                <ol class="mt-5 space-y-0">
                    <li v-for="(stage, i) in timeline_stages" :key="stage.key" class="relative flex gap-4 pb-8 last:pb-0">
                        <div class="flex flex-col items-center">
                            <span
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-black ring-2"
                                :class="stage.completed ? 'bg-primary-600 text-white ring-primary-600' : (stage.current ? 'bg-white text-primary-700 ring-primary-400' : 'bg-slate-100 text-slate-400 ring-slate-200')"
                            >{{ i + 1 }}</span>
                        </div>
                        <div class="min-w-0 pt-1">
                            <p class="text-sm font-bold" :class="stage.current ? 'text-primary-900' : 'text-slate-800'">{{ stage.label }}</p>
                            <p v-if="stage.at_label" class="text-xs font-semibold text-slate-500">{{ stage.at_label }}</p>
                        </div>
                    </li>
                </ol>
            </section>

            <section v-if="admin_panel" class="rounded-2xl border border-slate-200 bg-slate-50 p-5 ring-1 ring-slate-200 sm:p-6">
                <h2 class="font-display text-sm font-black uppercase tracking-wide text-slate-600">Admin panel</h2>

                <!-- Super Admin escrow override -->
                <div
                    v-if="admin_panel.escrow_controls"
                    class="mt-4 rounded-2xl border border-violet-200 bg-gradient-to-br from-violet-50/90 to-white p-4 ring-1 ring-violet-100 sm:p-5"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-violet-900">Escrow override</p>
                            <p class="mt-1 text-xs font-semibold text-violet-950/90">Super Admin only — pause auto-release or move funds. Every action is ledger-audited.</p>
                        </div>
                        <a
                            :href="admin_panel.escrow_controls.documentation_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-[10px] font-black uppercase tracking-wide text-violet-800 underline underline-offset-2"
                        >
                            Payments guide
                        </a>
                    </div>

                    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="rounded-xl border border-violet-100 bg-white p-3">
                            <p class="text-[10px] font-black uppercase text-slate-500">Escrow status</p>
                            <p class="mt-1 text-sm font-black capitalize text-slate-900">{{ admin_panel.escrow_controls.escrow_status || '—' }}</p>
                        </div>
                        <div class="rounded-xl border border-violet-100 bg-white p-3">
                            <p class="text-[10px] font-black uppercase text-slate-500">Held balance</p>
                            <p class="mt-1 text-sm font-black text-slate-900">{{ admin_panel.escrow_controls.held_label }}</p>
                        </div>
                        <div class="rounded-xl border border-violet-100 bg-white p-3">
                            <p class="text-[10px] font-black uppercase text-slate-500">Auto-release</p>
                            <p class="mt-1 text-xs font-bold text-slate-800">
                                <span v-if="admin_panel.escrow_controls.auto_release_countdown_active">Countdown active · {{ admin_panel.escrow_controls.auto_release_label }}</span>
                                <span v-else-if="admin_panel.escrow_controls.release_policy?.release_held">Paused — hold in place</span>
                                <span v-else>Not in countdown window</span>
                            </p>
                        </div>
                        <div class="rounded-xl border border-violet-100 bg-white p-3">
                            <p class="text-[10px] font-black uppercase text-slate-500">Dispute</p>
                            <p class="mt-1 text-sm font-black" :class="admin_panel.escrow_controls.dispute_opened ? 'text-rose-800' : 'text-emerald-800'">
                                {{ admin_panel.escrow_controls.dispute_opened ? 'Open' : 'None' }}
                            </p>
                        </div>
                    </div>

                    <p v-if="admin_panel.escrow_controls.hold_reason" class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-950">
                        Release hold: {{ admin_panel.escrow_controls.hold_reason }}
                        <span v-if="admin_panel.escrow_controls.hold_until_label"> · until {{ admin_panel.escrow_controls.hold_until_label }}</span>
                    </p>
                    <p v-if="admin_panel.escrow_controls.freeze_reason" class="mt-2 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-950">
                        Frozen: {{ admin_panel.escrow_controls.freeze_reason }}
                        <span v-if="admin_panel.escrow_controls.frozen_at_label"> · {{ admin_panel.escrow_controls.frozen_at_label }}</span>
                    </p>
                    <p v-if="admin_panel.escrow_controls.requires_authorization && !admin_panel.escrow_controls.has_authorization" class="mt-2 rounded-xl border border-sky-200 bg-sky-50 px-3 py-2 text-xs font-semibold text-sky-950">
                        High-value contract (≥ {{ admin_panel.escrow_controls.high_value_threshold }}) — authorize release before manual payout.
                    </p>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <button
                            v-if="!admin_panel.escrow_controls.release_policy?.release_held"
                            type="button"
                            class="rounded-full border border-amber-300 bg-amber-50 px-3 py-1.5 text-[10px] font-black uppercase tracking-wide text-amber-950 hover:bg-amber-100"
                            :disabled="escrowBusy"
                            @click="openEscrowModal('pause_auto_release')"
                        >
                            Pause auto-release
                        </button>
                        <button
                            v-if="admin_panel.escrow_controls.release_policy?.release_held"
                            type="button"
                            class="rounded-full border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-[10px] font-black uppercase tracking-wide text-emerald-950 hover:bg-emerald-100"
                            :disabled="escrowBusy"
                            @click="openEscrowModal('lift_auto_release_hold')"
                        >
                            Lift release hold
                        </button>
                        <button
                            v-if="admin_panel.escrow_controls.requires_authorization && !admin_panel.escrow_controls.has_authorization"
                            type="button"
                            class="rounded-full border border-sky-300 bg-sky-50 px-3 py-1.5 text-[10px] font-black uppercase tracking-wide text-sky-950 hover:bg-sky-100"
                            :disabled="escrowBusy"
                            @click="openEscrowModal('authorize_release')"
                        >
                            Authorize release
                        </button>
                        <button
                            type="button"
                            class="rounded-full border border-rose-300 bg-rose-50 px-3 py-1.5 text-[10px] font-black uppercase tracking-wide text-rose-950 hover:bg-rose-100"
                            :disabled="escrowBusy"
                            @click="openEscrowModal('freeze')"
                        >
                            Freeze escrow
                        </button>
                        <button
                            type="button"
                            class="rounded-full border border-violet-300 bg-violet-100 px-3 py-1.5 text-[10px] font-black uppercase tracking-wide text-violet-950 hover:bg-violet-200"
                            :disabled="escrowBusy"
                            @click="openEscrowModal('unfreeze')"
                        >
                            Unfreeze
                        </button>
                        <button
                            type="button"
                            class="rounded-full bg-emerald-700 px-3 py-1.5 text-[10px] font-black uppercase tracking-wide text-white hover:bg-emerald-800"
                            :disabled="escrowBusy || !admin_panel.escrow_controls.held_minor"
                            @click="openEscrowModal('manual_release')"
                        >
                            Release to freelancer
                        </button>
                        <button
                            type="button"
                            class="rounded-full bg-slate-800 px-3 py-1.5 text-[10px] font-black uppercase tracking-wide text-white hover:bg-slate-900"
                            :disabled="escrowBusy || !admin_panel.escrow_controls.held_minor"
                            @click="openEscrowModal('full_refund')"
                        >
                            Refund client
                        </button>
                        <button
                            type="button"
                            class="rounded-full border border-slate-300 bg-white px-3 py-1.5 text-[10px] font-black uppercase tracking-wide text-slate-800 hover:bg-slate-50"
                            :disabled="escrowLedgerLoading"
                            @click="loadEscrowLedger"
                        >
                            {{ escrowLedger ? 'Refresh ledger' : 'View ledger' }}
                        </button>
                    </div>

                    <div v-if="escrowLedgerLoading" class="mt-4 text-xs font-semibold text-slate-600">Loading ledger…</div>
                    <div v-else-if="escrowLedger?.entries?.length" class="mt-4 max-h-48 overflow-y-auto rounded-xl border border-slate-200 bg-white">
                        <table class="min-w-full text-left text-[11px] font-semibold text-slate-700">
                            <thead class="sticky top-0 bg-slate-50 text-[10px] uppercase text-slate-500">
                                <tr><th class="px-3 py-2">Entry</th><th class="px-3 py-2">Amount</th><th class="px-3 py-2">Balance</th></tr>
                            </thead>
                            <tbody>
                                <tr v-for="(entry, ei) in escrowLedger.entries" :key="ei" class="border-t border-slate-100">
                                    <td class="px-3 py-2">{{ entry.description }}</td>
                                    <td class="px-3 py-2">{{ entry.amount }}</td>
                                    <td class="px-3 py-2">{{ entry.balance }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-xl border border-slate-200 bg-white p-4 text-xs">
                        <p class="font-black uppercase text-slate-500">Client forensics</p>
                        <p class="mt-2 font-mono text-slate-700">IP: {{ admin_panel.parties_forensics.client.ip || '—' }}</p>
                        <p class="mt-1 break-all text-slate-600">{{ admin_panel.parties_forensics.client.user_agent || '—' }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-4 text-xs">
                        <p class="font-black uppercase text-slate-500">Freelancer forensics</p>
                        <p class="mt-2 font-mono text-slate-700">IP: {{ admin_panel.parties_forensics.freelancer.ip || '—' }}</p>
                        <p class="mt-1 break-all text-slate-600">{{ admin_panel.parties_forensics.freelancer.user_agent || '—' }}</p>
                    </div>
                </div>
                <form v-if="admin_panel.can_flag_for_review && !admin_panel.flagged_for_review" class="mt-4 space-y-2" @submit.prevent="submitFlag">
                    <textarea v-model="flagForm.reason" rows="3" class="w-full rounded-xl border-slate-200 text-sm shadow-sm" placeholder="Reason for financial review flag" required />
                    <button type="submit" class="rounded-full bg-rose-700 px-4 py-2 text-xs font-black uppercase text-white" :disabled="flagForm.processing">Flag contract</button>
                </form>
                <div class="mt-5 max-h-64 overflow-y-auto rounded-xl border border-slate-200 bg-white">
                    <table class="min-w-full text-left text-[11px] font-semibold text-slate-700">
                        <thead class="sticky top-0 bg-slate-50 text-[10px] uppercase text-slate-500">
                            <tr><th class="px-3 py-2">Event</th><th class="px-3 py-2">Actor</th><th class="px-3 py-2">When</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="(e, i) in admin_panel.event_log" :key="i" class="border-t border-slate-100">
                                <td class="px-3 py-2">{{ e.event_type }}</td>
                                <td class="px-3 py-2">{{ e.actor }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ e.at_label }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <Teleport to="body">
            <div v-if="showAmendmentForm" class="fixed inset-0 z-[60] flex items-end justify-center bg-slate-950/50 p-4 backdrop-blur-[2px] sm:items-center" @click.self="showAmendmentForm = false">
                <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl sm:p-6">
                    <h3 class="font-display text-lg font-black text-slate-900">Request amendment</h3>
                    <form class="mt-4 space-y-3" @submit.prevent="submitAmendment">
                        <select v-model="amendmentForm.amendment_type" class="w-full rounded-xl border-slate-200 text-sm font-semibold shadow-sm">
                            <option value="scope">Scope change</option>
                            <option value="price">Price adjustment</option>
                            <option value="delivery_date">Delivery date extension</option>
                        </select>
                        <textarea v-model="amendmentForm.description" rows="3" class="w-full rounded-xl border-slate-200 text-sm shadow-sm" placeholder="Describe the change" required />
                        <input v-if="amendmentForm.amendment_type !== 'scope'" v-model="amendmentForm.new_value" type="text" class="w-full rounded-xl border-slate-200 text-sm shadow-sm" placeholder="New value (NGN or date)" />
                        <textarea v-model="amendmentForm.reason" rows="2" class="w-full rounded-xl border-slate-200 text-sm shadow-sm" placeholder="Reason (required)" required />
                        <div class="flex gap-2">
                            <button type="submit" class="rounded-full bg-primary-700 px-4 py-2 text-xs font-black uppercase text-white" :disabled="amendmentForm.processing">Submit</button>
                            <button type="button" class="rounded-full border border-slate-200 px-4 py-2 text-xs font-black uppercase text-slate-700" @click="showAmendmentForm = false">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <Teleport to="body">
            <div v-if="showExtensionForm" class="fixed inset-0 z-[60] flex items-end justify-center bg-slate-950/50 p-4 backdrop-blur-[2px] sm:items-center" @click.self="showExtensionForm = false">
                <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl sm:p-6">
                    <h3 class="font-display text-lg font-black text-slate-900">Change finish date</h3>
                    <p class="mt-1 text-xs font-semibold text-slate-600">
                        Request {{ (delivery_extension.freelancer_button?.extension_count || 0) + 1 }} of {{ delivery_extension.freelancer_button?.extension_limit || 2 }}
                    </p>
                    <form class="mt-4 space-y-3" @submit.prevent="submitExtension">
                        <div>
                            <label class="text-xs font-black uppercase text-slate-500">What do you need?</label>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <label
                                    v-for="opt in delivery_extension.adjustment_types"
                                    :key="opt.value"
                                    class="inline-flex cursor-pointer items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-bold"
                                    :class="extensionForm.adjustment_type === opt.value ? 'border-primary-400 bg-primary-50 text-primary-900' : 'border-slate-200 bg-white text-slate-700'"
                                >
                                    <input v-model="extensionForm.adjustment_type" type="radio" :value="opt.value" class="sr-only" />
                                    {{ opt.label }}
                                </label>
                            </div>
                        </div>
                        <div>
                            <label class="text-xs font-black uppercase text-slate-500">New finish date</label>
                            <input v-model="extensionForm.proposed_delivery_date" type="date" class="mt-1 w-full rounded-xl border-slate-200 text-sm shadow-sm" required />
                            <p class="mt-1 text-[10px] font-semibold text-slate-500">
                                <template v-if="extensionForm.adjustment_type === 'reduction'">
                                    Pick a date before the current finish date (up to {{ delivery_extension.max_reduction_days || 14 }} days earlier).
                                </template>
                                <template v-else>
                                    Pick a date after the current finish date (up to {{ delivery_extension.max_extension_days || 14 }} days later).
                                </template>
                            </p>
                        </div>
                        <div>
                            <label class="text-xs font-black uppercase text-slate-500">Reason category</label>
                            <select v-model="extensionForm.reason_category" class="mt-1 w-full rounded-xl border-slate-200 text-sm font-semibold shadow-sm" required>
                                <option v-for="opt in delivery_extension.reason_categories" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                        </div>
                        <div v-if="extensionForm.reason_category === 'client_requested_changes'" class="rounded-xl border border-sky-100 bg-sky-50/80 p-3">
                            <p class="text-xs font-bold text-sky-900">Tag the conversation message where the client requested the scope change.</p>
                            <button type="button" class="mt-2 text-xs font-black uppercase text-primary-800 underline" @click="loadThreadMessages">Load quest messages</button>
                            <ul v-if="threadMessages.length" class="mt-3 max-h-40 space-y-2 overflow-y-auto text-xs">
                                <li v-for="msg in threadMessages" :key="msg.id">
                                    <label class="flex cursor-pointer gap-2 rounded-lg border border-slate-200 bg-white p-2">
                                        <input v-model="extensionForm.scope_change_message_id" type="radio" :value="msg.id" class="mt-1" />
                                        <span><span class="font-black">{{ msg.author }}</span> · {{ msg.body?.slice(0, 120) }}</span>
                                    </label>
                                </li>
                            </ul>
                        </div>
                        <div>
                            <label class="text-xs font-black uppercase text-slate-500">Explanation (min 50 characters)</label>
                            <textarea v-model="extensionForm.explanation" rows="4" class="mt-1 w-full rounded-xl border-slate-200 text-sm shadow-sm" required minlength="50" />
                        </div>
                        <label class="flex cursor-pointer items-start gap-3 text-sm font-semibold text-slate-800">
                            <input v-model="extensionForm.include_progress" type="checkbox" class="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                            <span>Include a progress update</span>
                        </label>
                        <textarea v-if="extensionForm.include_progress" v-model="extensionForm.progress_note" rows="3" class="w-full rounded-xl border-slate-200 text-sm shadow-sm" placeholder="Progress note" />
                        <input v-if="extensionForm.include_progress" type="file" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx" class="w-full text-xs" @change="onExtensionFiles" />
                        <div class="flex gap-2">
                            <button type="submit" class="rounded-full bg-primary-700 px-4 py-2 text-xs font-black uppercase text-white" :disabled="extensionForm.processing">Submit request</button>
                            <button type="button" class="rounded-full border border-slate-200 px-4 py-2 text-xs font-black uppercase text-slate-700" @click="showExtensionForm = false">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <Teleport to="body">
            <div
                v-if="escrowModalOpen"
                class="fixed inset-0 z-[70] flex items-end justify-center bg-slate-950/50 p-4 backdrop-blur-[2px] sm:items-center"
                @click.self="escrowModalOpen = false"
            >
                <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl sm:p-6">
                    <h3 class="font-display text-lg font-black text-slate-900">{{ escrowModalTitle }}</h3>
                    <p class="mt-1 text-xs font-semibold text-slate-600">{{ escrowModalHint }}</p>
                    <form class="mt-4 space-y-3" @submit.prevent="submitEscrowOverride">
                        <div v-if="escrowModalAction === 'pause_auto_release'">
                            <label class="text-xs font-black uppercase text-slate-500">Hold until (optional)</label>
                            <input v-model="escrowHoldForm.hold_until" type="date" class="mt-1 w-full rounded-xl border-slate-200 text-sm shadow-sm" />
                            <label class="mt-2 flex cursor-pointer items-center gap-2 text-xs font-semibold text-slate-700">
                                <input v-model="escrowHoldForm.indefinite" type="checkbox" class="rounded border-slate-300 text-violet-600" />
                                Indefinite hold (until manually lifted)
                            </label>
                        </div>
                        <div v-if="escrowModalAction === 'manual_release' || escrowModalAction === 'full_refund'">
                            <label class="text-xs font-black uppercase text-slate-500">Amount (NGN)</label>
                            <input
                                v-model="escrowActionForm.amount"
                                type="number"
                                min="0"
                                step="0.01"
                                class="mt-1 w-full rounded-xl border-slate-200 text-sm shadow-sm"
                                :placeholder="heldAmountNgn"
                            />
                            <p class="mt-1 text-[10px] font-semibold text-slate-500">Held: {{ admin_panel?.escrow_controls?.held_label }}. Leave blank for full held balance.</p>
                        </div>
                        <div>
                            <label class="text-xs font-black uppercase text-slate-500">Audit reason (required, min 10 characters)</label>
                            <textarea v-model="escrowActionForm.reason" rows="4" required minlength="10" class="mt-1 w-full rounded-xl border-slate-200 text-sm shadow-sm" placeholder="Why this override is necessary…" />
                        </div>
                        <p v-if="escrowError" class="text-xs font-bold text-rose-700">{{ escrowError }}</p>
                        <div class="flex gap-2">
                            <button type="submit" class="rounded-full bg-violet-700 px-4 py-2 text-xs font-black uppercase text-white hover:bg-violet-800" :disabled="escrowBusy">
                                Confirm
                            </button>
                            <button type="button" class="rounded-full border border-slate-200 px-4 py-2 text-xs font-black uppercase text-slate-700" @click="escrowModalOpen = false">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AppShell>
</template>

<script setup>
import AppShell from '@/Layouts/AppShell.vue';
import PlatformFeeDisclosureNote from '@/Components/Billing/PlatformFeeDisclosureNote.vue';
import BackChevronLink from '@/Components/Ui/BackChevronLink.vue';
import HustleSafeLogo from '@/Components/Brand/HustleSafeLogo.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';

const page = usePage();
const csrfToken = computed(() => page.props.csrf_token ?? document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '');

const props = defineProps({
    contract: { type: Object, required: true },
    timeline_stages: { type: Array, default: () => [] },
    role: { type: Object, default: () => ({}) },
    permissions: { type: Object, default: () => ({}) },
    pending_amendment: { type: Object, default: null },
    delivery_extension: { type: Object, default: () => ({}) },
    admin_panel: { type: Object, default: null },
});

const deliveryCountdown = computed(() => props.contract.delivery_countdown || { active: false });
const autoRelease = computed(() => props.contract.dispute_window || { visible: false, active: false });

const showDeliveryExtensionsPanel = computed(() => {
    const ext = props.delivery_extension ?? {};
    const hasHistory = (ext.history?.length ?? 0) > 0;
    const hasPending = Boolean(ext.pending || ext.pending_counter);
    const isActive = props.contract.status === 'active';

    return isActive || hasHistory || hasPending;
});

const extensionHistoryRows = computed(() => {
    const history = props.delivery_extension?.history ?? [];
    const hasActionCard = Boolean(props.delivery_extension?.pending || props.delivery_extension?.pending_counter);

    return hasActionCard ? history.filter((ext) => !ext.is_active) : history;
});

const liveAutoReleaseSeconds = ref(0);
let autoReleaseTickTimer;

watch(
    () => autoRelease.value.seconds_until_auto_release_total,
    (seconds) => {
        liveAutoReleaseSeconds.value = Math.max(0, Number(seconds) || 0);
    },
    { immediate: true },
);

onMounted(() => {
    autoReleaseTickTimer = setInterval(() => {
        if (liveAutoReleaseSeconds.value > 0) {
            liveAutoReleaseSeconds.value -= 1;
        }
    }, 1000);
});

onUnmounted(() => {
    clearInterval(autoReleaseTickTimer);
});

function scrollToDeliveryExtension() {
    document.getElementById('delivery-extension')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

const showAmendmentForm = ref(false);
const showExtensionForm = ref(false);
const showDeclineNote = ref(false);
const showExtensionDecline = ref(false);
const showCounterForm = ref(false);
const threadMessages = ref([]);

const amendmentForm = useForm({
    amendment_type: 'scope',
    description: '',
    reason: '',
    new_value: '',
});

const extensionForm = useForm({
    adjustment_type: 'extension',
    proposed_delivery_date: '',
    reason_category: 'scope_larger_than_estimated',
    explanation: '',
    include_progress: false,
    progress_note: '',
    scope_change_message_id: null,
    progress_attachments: [],
});

const extensionRespondForm = useForm({
    action: 'accept',
    decline_reason: '',
    counter_proposed_date: '',
});

const counterRespondForm = useForm({ action: 'accept' });

const respondForm = useForm({ action: 'accept', response_note: '' });
const flagForm = useForm({ reason: '' });

const escrowBusy = ref(false);
const escrowLedgerLoading = ref(false);
const escrowLedger = ref(null);
const escrowModalOpen = ref(false);
const escrowModalAction = ref('');
const escrowError = ref('');
const escrowActionForm = reactive({ reason: '', amount: '' });
const escrowHoldForm = reactive({ hold_until: '', indefinite: false });

const heldAmountNgn = computed(() => {
    const minor = props.admin_panel?.escrow_controls?.held_minor ?? 0;
    return minor > 0 ? (minor / 100).toFixed(2) : '';
});

const escrowModalTitle = computed(() => ({
    pause_auto_release: 'Pause auto-release',
    lift_auto_release_hold: 'Lift release hold',
    authorize_release: 'Authorize high-value release',
    freeze: 'Freeze escrow',
    unfreeze: 'Unfreeze escrow',
    manual_release: 'Release to freelancer',
    full_refund: 'Refund client',
}[escrowModalAction.value] || 'Escrow action'));

const escrowModalHint = computed(() => ({
    pause_auto_release: 'Stops client release and the 72-hour auto-release job until you lift the hold.',
    lift_auto_release_hold: 'Restores normal release rules including auto-release countdown.',
    authorize_release: 'Required before high-value contracts can release to the freelancer.',
    freeze: 'Marks escrow as frozen — funds cannot move until unfreeze.',
    unfreeze: 'Restores escrow to funded status when safe to do so.',
    manual_release: 'Credits the freelancer wallet now. Bypasses cooldown for Super Admin.',
    full_refund: 'Returns held funds to the client via Paystack refund flow.',
}[escrowModalAction.value] || ''));

function formatWhen(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
}

function countdownLabel(seconds) {
    const s = Math.max(0, Number(seconds) || 0);
    const days = Math.floor(s / 86400);
    const hours = Math.floor((s % 86400) / 3600);
    if (days > 0) return `${days}d ${hours}h`;
    const mins = Math.floor((s % 3600) / 60);
    return hours > 0 ? `${hours}h ${mins}m` : `${mins}m`;
}

function statusClass(status) {
    return {
        pending_escrow: 'bg-amber-50 text-amber-900 ring-amber-200',
        active: 'bg-emerald-50 text-emerald-900 ring-emerald-200',
        amendment_pending: 'bg-sky-50 text-sky-900 ring-sky-200',
        completed: 'bg-slate-100 text-slate-700 ring-slate-200',
        disputed: 'bg-rose-50 text-rose-900 ring-rose-200',
        cancelled: 'bg-slate-50 text-slate-500 ring-slate-200',
    }[status] || 'bg-slate-100 text-slate-700 ring-slate-200';
}

function extensionStatusClass(status) {
    return {
        pending_client: 'bg-amber-100 text-amber-900 ring-amber-200',
        counter_proposed: 'bg-sky-100 text-sky-900 ring-sky-200',
        approved: 'bg-emerald-100 text-emerald-900 ring-emerald-200',
        auto_approved: 'bg-emerald-100 text-emerald-900 ring-emerald-200',
        declined: 'bg-rose-100 text-rose-900 ring-rose-200',
        counter_rejected: 'bg-rose-100 text-rose-900 ring-rose-200',
    }[status] || 'bg-slate-100 text-slate-700 ring-slate-200';
}

function submitAmendment() {
    amendmentForm.post(route('contracts.amendments.store', props.contract.reference_code), {
        preserveScroll: true,
        onSuccess: () => { showAmendmentForm.value = false; },
    });
}

function respondAmendment(action) {
    if (action === 'decline' && !showDeclineNote.value) {
        showDeclineNote.value = true;
        return;
    }
    if (action === 'decline' && !respondForm.response_note.trim()) {
        return;
    }
    respondForm.action = action;
    respondForm.post(route('contracts.amendments.respond', [props.contract.reference_code, props.pending_amendment.id]), {
        preserveScroll: true,
    });
}

function submitFlag() {
    flagForm.post(route('admin.contracts.flag', props.contract.reference_code), { preserveScroll: true });
}

function openEscrowModal(action) {
    escrowModalAction.value = action;
    escrowActionForm.reason = '';
    escrowActionForm.amount = heldAmountNgn.value;
    escrowHoldForm.hold_until = '';
    escrowHoldForm.indefinite = false;
    escrowError.value = '';
    escrowModalOpen.value = true;
}

async function loadEscrowLedger() {
    const routes = props.admin_panel?.escrow_controls?.routes;
    if (!routes?.ledger) {
        return;
    }
    escrowLedgerLoading.value = true;
    try {
        const { data } = await window.axios.get(routes.ledger);
        escrowLedger.value = data;
    } finally {
        escrowLedgerLoading.value = false;
    }
}

async function submitEscrowOverride() {
    const controls = props.admin_panel?.escrow_controls;
    if (!controls?.routes || escrowActionForm.reason.trim().length < 10) {
        escrowError.value = 'Enter a clear audit reason (at least 10 characters).';
        return;
    }

    escrowBusy.value = true;
    escrowError.value = '';

    try {
        const action = escrowModalAction.value;

        if (action === 'pause_auto_release') {
            await window.axios.post(controls.routes.hold_auto_release, {
                reason: escrowActionForm.reason,
                hold_until: escrowHoldForm.indefinite ? null : escrowHoldForm.hold_until || null,
                indefinite: escrowHoldForm.indefinite,
            });
        } else if (action === 'lift_auto_release_hold') {
            await window.axios.post(controls.routes.lift_auto_release_hold, {
                reason: escrowActionForm.reason,
            });
        } else if (action === 'authorize_release') {
            await window.axios.post(controls.routes.authorize_release, {
                reason: escrowActionForm.reason,
            });
        } else if (['freeze', 'unfreeze', 'manual_release', 'full_refund'].includes(action)) {
            await window.axios.post(controls.routes.action, {
                action,
                reason: escrowActionForm.reason,
                amount: escrowActionForm.amount || undefined,
            });
        }

        escrowModalOpen.value = false;
        router.reload({ preserveScroll: true });
    } catch (err) {
        escrowError.value = err?.response?.data?.message
            || Object.values(err?.response?.data?.errors || {}).flat()[0]
            || 'Escrow action failed. Check Financial Control Centre for details.';
    } finally {
        escrowBusy.value = false;
    }
}

function openExtensionForm() {
    showExtensionForm.value = true;
    threadMessages.value = [];
}

async function loadThreadMessages() {
    const { data } = await window.axios.get(props.delivery_extension.messages_url);
    threadMessages.value = data.messages || [];
}

function onExtensionFiles(event) {
    extensionForm.progress_attachments = Array.from(event.target.files || []);
}

function submitExtension() {
    extensionForm.post(route('contracts.extensions.store', props.contract.reference_code), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => { showExtensionForm.value = false; },
    });
}

function toggleCounterForm() {
    showCounterForm.value = !showCounterForm.value;
}

function respondExtension(action) {
    if (action === 'decline' && !showExtensionDecline.value) {
        showExtensionDecline.value = true;
        return;
    }
    if (action === 'decline' && !extensionRespondForm.decline_reason.trim()) {
        return;
    }
    extensionRespondForm.action = action;
    extensionRespondForm.post(route('contracts.extensions.respond', [props.contract.reference_code, props.delivery_extension.pending.id]), {
        preserveScroll: true,
    });
}

function respondCounter(action) {
    counterRespondForm.action = action;
    counterRespondForm.post(route('contracts.extensions.counter-respond', [props.contract.reference_code, props.delivery_extension.pending_counter.id]), {
        preserveScroll: true,
    });
}
</script>
