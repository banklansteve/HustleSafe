<template>
    <AppShell>
        <Head :title="`Dispute · ${dispute.quest.title}`" />

        <div class="mx-auto max-w-4xl space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <BackChevronLink :href="route('quests.show', dispute.quest.route_key)" aria-label="Back to quest" />
                <Link
                    :href="route('disputes.index')"
                    class="text-xs font-black uppercase tracking-wide text-primary-800 underline underline-offset-2"
                >
                    All disputes
                </Link>
            </div>

            <header class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6">
                <p class="text-xs font-black uppercase tracking-[0.2em] text-slate-500">
                    {{ dispute.reference || dispute.uuid }} · {{ dispute.status_label || dispute.status }}
                </p>
                <h1 class="font-display mt-2 text-2xl font-black text-slate-900">
                    {{ dispute.quest.title }}
                </h1>
                <p class="mt-2 text-base font-semibold leading-normal text-slate-700">
                    {{ dispute.reason_label }}
                </p>
                <p v-if="dispute.category_label" class="mt-1 text-xs font-bold uppercase tracking-wide text-slate-500">
                    {{ dispute.category_label }}
                </p>
                <p v-if="dispute.contract?.url" class="mt-2 text-xs font-bold text-slate-600">
                    Contract
                    <Link :href="dispute.contract.url" class="ml-1 font-black text-primary-800 underline underline-offset-2">
                        {{ dispute.contract.reference_code }}
                    </Link>
                </p>
                <div v-if="dispute.response_required_by" class="mt-3 text-xs font-bold text-amber-900">
                    Response window · {{ formatWhen(dispute.response_required_by) }}
                    <span v-if="dispute.awaiting_viewer" class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] uppercase">Your turn</span>
                </div>
                <div v-if="dispute.ruling_required_by" class="mt-1 text-xs font-bold text-rose-900">
                    Formal review closes · {{ formatWhen(dispute.ruling_required_by) }}
                </div>
            </header>

            <section v-if="workflow" class="rounded-2xl border border-primary-100 bg-gradient-to-br from-primary-50/80 to-white p-5 shadow-sm ring-1 ring-primary-100 sm:p-6">
                <h2 class="font-display text-sm font-black uppercase tracking-wide text-primary-900">What happens next</h2>
                <p class="mt-2 text-base font-bold leading-normal text-slate-900">{{ workflow.headline }}</p>
                <p class="mt-1 text-base font-medium leading-normal text-slate-700">{{ workflow.next_steps }}</p>

                <ol class="mt-4 space-y-3">
                    <li
                        v-for="stage in workflow.stages"
                        :key="stage.key"
                        class="flex gap-3 rounded-xl border px-3 py-3"
                        :class="stageStateClass(stage.state)"
                    >
                        <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[10px] font-black" :class="stageBadgeClass(stage.state)">
                            <template v-if="stage.state === 'completed'">✓</template>
                            <template v-else-if="stage.state === 'current'">●</template>
                            <template v-else>○</template>
                        </span>
                        <div>
                            <p class="text-sm font-black text-slate-900">{{ stage.label }}</p>
                            <p class="mt-0.5 text-xs font-medium leading-normal text-slate-600">{{ stage.description }}</p>
                        </div>
                    </li>
                </ol>
            </section>

            <section v-if="dispute.other_party" class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm ring-1 ring-slate-100">
                    <p class="text-[10px] font-black uppercase tracking-wide text-slate-500">Opened by</p>
                    <p class="mt-1 text-sm font-black text-slate-900">{{ dispute.opened_by?.name }}</p>
                    <p class="text-xs font-semibold capitalize text-slate-600">{{ dispute.opened_by?.party }}</p>
                </div>
                <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm ring-1 ring-slate-100">
                    <p class="text-[10px] font-black uppercase tracking-wide text-slate-500">Other party</p>
                    <div class="mt-1 flex items-center gap-3">
                        <img
                            v-if="dispute.other_party.avatar_url"
                            :src="dispute.other_party.avatar_url"
                            :alt="dispute.other_party.name"
                            class="h-10 w-10 rounded-full object-cover ring-2 ring-white"
                        />
                        <div>
                            <p class="text-sm font-black text-slate-900">{{ dispute.other_party.name }}</p>
                            <p class="text-xs font-semibold capitalize text-slate-600">{{ dispute.other_party.role }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <SlaExpectationNotice v-if="sla_expectation" :message="sla_expectation" />

            <section v-if="hasIntakeDetails" class="space-y-4 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6">
                <h2 class="font-display text-sm font-black uppercase tracking-wide text-slate-500">
                    Case summary
                </h2>

                <div v-if="dispute.opening_summary" class="rounded-xl bg-slate-50/80 p-4">
                    <p class="text-xs font-black uppercase tracking-wide text-slate-500">What happened</p>
                    <p class="mt-2 whitespace-pre-wrap text-base font-medium leading-normal text-slate-800">{{ dispute.opening_summary }}</p>
                </div>

                <div v-if="intakeImpactRows.length" class="grid gap-3 sm:grid-cols-2">
                    <div v-for="row in intakeImpactRows" :key="row.label" class="rounded-xl border border-slate-100 px-3 py-2 text-sm font-semibold text-slate-800">
                        <span class="block text-[10px] font-black uppercase tracking-wide text-slate-500">{{ row.label }}</span>
                        {{ row.value }}
                    </div>
                </div>

                <div v-if="intake.resolution_requested" class="text-sm font-semibold text-slate-800">
                    <span class="text-xs font-black uppercase tracking-wide text-slate-500">Resolution requested · </span>
                    {{ formatResolution(intake.resolution_requested, intake.resolution_amount_minor) }}
                </div>

                <div v-if="intakeTimelineRows.length" class="space-y-1">
                    <p class="text-xs font-black uppercase tracking-wide text-slate-500">Timeline</p>
                    <ul class="space-y-1 text-sm font-semibold text-slate-800">
                        <li v-for="row in intakeTimelineRows" :key="row.label">{{ row.label }}: {{ row.value }}</li>
                    </ul>
                </div>

                <div v-if="intakeAffectedAreas.length">
                    <p class="text-xs font-black uppercase tracking-wide text-slate-500">Affected areas</p>
                    <p class="mt-1 text-sm font-semibold text-slate-800">{{ intakeAffectedAreas.join(' · ') }}</p>
                </div>

                <div v-if="intakePriorAttempts.length">
                    <p class="text-xs font-black uppercase tracking-wide text-slate-500">Prior attempts</p>
                    <p class="mt-1 text-sm font-semibold text-slate-800">{{ intakePriorAttempts.join(' · ') }}</p>
                </div>

                <div v-if="intake.evidence_files?.length" class="space-y-2">
                    <p class="text-xs font-black uppercase tracking-wide text-slate-500">Uploaded evidence</p>
                    <ul class="space-y-1 text-sm font-semibold text-primary-800">
                        <li v-for="(file, idx) in intake.evidence_files" :key="idx">
                            <a :href="file.url" target="_blank" rel="noopener noreferrer" class="underline">{{ file.original_name }}</a>
                        </li>
                    </ul>
                </div>

                <div v-if="intake.external_links?.length" class="space-y-2">
                    <p class="text-xs font-black uppercase tracking-wide text-slate-500">External links</p>
                    <ul class="space-y-1 text-sm font-semibold text-primary-800">
                        <li v-for="(link, idx) in intake.external_links" :key="idx">
                            <a :href="link.url" target="_blank" rel="noopener noreferrer" class="underline">{{ link.description || link.url }}</a>
                        </li>
                    </ul>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-100 bg-slate-50/80 p-5 ring-1 ring-slate-100">
                <h2 class="font-display text-sm font-black uppercase tracking-wide text-slate-600">
                    Policy snapshot
                </h2>
                <ul class="mt-2 list-disc space-y-1 pl-4 text-xs font-semibold text-slate-700">
                    <li>Minimum contract value ₦{{ (policy.minimum_disputed_amount_minor / 100).toLocaleString() }}</li>
                    <li>Self-resolution · {{ policy.self_resolution_hours }}h per turn when the other party must respond</li>
                    <li>Escalation to staff if timers expire · formal window {{ policy.formal_ruling_hours }}h</li>
                    <li class="font-black text-emerald-800">No dispute resolution fee charged to either party</li>
                    <li>
                        Accounts involved in more than {{ policy.review_after_dispute_count }} disputes may be reviewed for trust &amp; safety
                        <span v-if="policy.user_dispute_count"> (you: {{ policy.user_dispute_count }})</span>
                    </li>
                    <li v-if="policy.user_near_review_threshold" class="text-amber-800">You are approaching the dispute review threshold — ensure every filing is evidence-based.</li>
                    <li>{{ policy.max_appeals }} appeal per dispute after a Super Admin decision</li>
                </ul>
            </section>

            <section v-if="!isOpen && dispute.party_self_resolved" class="rounded-2xl border border-emerald-200 bg-emerald-50/80 p-5 ring-1 ring-emerald-100">
                <h2 class="font-display text-sm font-black uppercase tracking-wide text-emerald-900">Dispute resolved</h2>
                <p class="mt-2 text-base font-medium leading-normal text-emerald-950">
                    {{ dispute.resolution_outcome_label || 'You and the other party closed this dispute.' }}
                </p>
                <p class="mt-2 text-sm font-semibold text-emerald-900/80">
                    Customer Support has been notified and will review the file. You do not need to do anything else unless they contact you.
                </p>
            </section>

            <section v-if="dispute.contract_disputes?.length > 1" class="rounded-2xl border border-slate-100 bg-slate-50/80 p-5 ring-1 ring-slate-100">
                <h2 class="font-display text-sm font-black uppercase tracking-wide text-slate-800">Contract dispute history</h2>
                <p class="mt-1 text-xs font-medium text-slate-600">Only one dispute can run at a time on this contract. Earlier cases stay on record.</p>
                <ul class="mt-3 space-y-2 text-xs font-semibold text-slate-700">
                    <li v-for="item in dispute.contract_disputes" :key="item.uuid" class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-white bg-white/80 px-3 py-2">
                        <span>
                            <Link v-if="item.uuid !== dispute.uuid" :href="item.party_url" class="font-black text-primary-800 underline">{{ item.reference }}</Link>
                            <span v-else class="font-black text-slate-900">{{ item.reference }} (this case)</span>
                            · {{ item.management_status_label }}
                            <span v-if="item.is_active" class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-black uppercase text-amber-900">Active</span>
                        </span>
                    </li>
                </ul>
            </section>

            <DisputeNegotiationPanel
                v-if="can_participate && ['peer_negotiation', 'mediation', 'awaiting_mutual_approval'].includes(dispute.negotiation?.phase)"
                :negotiation="dispute.negotiation"
                :resolution-options="dispute.resolution_options || []"
                :urls="dispute.urls"
            />

            <DisputeAppealPanel
                v-if="can_participate && dispute.appeal && (dispute.appeal.can_file || dispute.appeal.can_respond || dispute.appeal.open_appeal)"
                :appeal="dispute.appeal"
                :resolution-options="dispute.resolution_options || []"
                :urls="dispute.urls"
            />

            <section v-if="can_participate && isOpen && !['peer_negotiation', 'mediation', 'awaiting_mutual_approval'].includes(dispute.negotiation?.phase)" class="rounded-2xl border border-primary-100 bg-primary-50/40 p-5 ring-1 ring-primary-100">
                <div v-if="!showResolutionPanel" class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-display text-sm font-black uppercase tracking-wide text-primary-900">
                            Try to resolve together
                        </h2>
                        <p class="mt-1 text-sm font-medium leading-normal text-slate-700">
                            Talk to the other party — agree on fixes, timelines, deliverables, payment splits, or other terms before the timer runs out.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex shrink-0 items-center rounded-full bg-primary-700 px-4 py-2 text-xs font-black uppercase tracking-wide text-white"
                        @click="showResolutionPanel = true"
                    >
                        Resolve now
                    </button>
                </div>

                <div v-else class="space-y-5">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="font-display text-sm font-black uppercase tracking-wide text-primary-900">
                                Send an update
                            </h2>
                            <p class="mt-1 text-sm font-medium leading-normal text-slate-700">
                                Tell the other party what happened. Stay factual — insults can count against you in review.
                            </p>
                        </div>
                        <button
                            type="button"
                            class="text-xs font-black uppercase tracking-wide text-primary-800 underline underline-offset-2"
                            @click="showResolutionPanel = false"
                        >
                            Hide
                        </button>
                    </div>

                    <form class="space-y-4 rounded-xl border border-primary-100/80 bg-white/70 p-4" @submit.prevent="submitMessage">
                        <div>
                            <label for="dispute-update-message" class="text-sm font-bold text-slate-800">
                                Your message
                            </label>
                            <p class="mt-1 text-xs leading-normal text-slate-600">
                                Say what was agreed, what was delivered (or not), and what you want to happen next. Include dates if you remember them.
                            </p>
                            <textarea
                                id="dispute-update-message"
                                v-model="messageForm.body"
                                rows="5"
                                class="mt-2 w-full rounded-xl border-slate-200 text-base leading-normal shadow-sm"
                                placeholder="Example: I paid on 3 May. Work was due 10 May but only half was done. I asked for fixes on 12 May and got no reply."
                            />
                        </div>

                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-3 text-sm leading-normal text-slate-800">
                            <input
                                v-model="messageIsProof"
                                type="checkbox"
                                class="mt-0.5 rounded border-slate-300 text-primary-600 focus:ring-primary-500"
                            />
                            <span>
                                <span class="font-semibold">This message is mainly about proof</span>
                                <span class="mt-0.5 block text-xs text-slate-600">
                                    Tick this if you are pointing to photos, screenshots, files, or links you already shared in the case summary.
                                </span>
                            </span>
                        </label>

                        <button
                            type="submit"
                            class="inline-flex items-center rounded-full bg-primary-700 px-4 py-2.5 text-xs font-black uppercase tracking-wide text-white disabled:opacity-50"
                            :disabled="messageForm.processing || !messageForm.body.trim()"
                        >
                            <ReLoader4Line v-if="messageForm.processing" class="mr-2 h-4 w-4 animate-spin" aria-hidden="true" />
                            Send update
                        </button>
                    </form>

                    <form class="space-y-4 rounded-xl border border-emerald-100/80 bg-white/70 p-4" @submit.prevent="submitResolutionRequest">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">How do you want to move forward?</h3>
                            <p class="mt-1 text-xs leading-normal text-slate-600">
                                Choose one path. You cannot close the dispute and ask Customer Support to decide at the same time.
                            </p>
                        </div>

                        <div class="grid gap-2 sm:grid-cols-2">
                            <button
                                type="button"
                                class="rounded-xl border px-3 py-3 text-left transition"
                                :class="resolutionPath === 'together' ? 'border-emerald-300 bg-emerald-50/80 ring-1 ring-emerald-200' : 'border-slate-100 bg-white hover:border-slate-200'"
                                @click="setResolutionPath('together')"
                            >
                                <span class="block text-sm font-black text-slate-900">Agree with the other party</span>
                                <span class="mt-1 block text-xs leading-normal text-slate-600">
                                    Payment splits, revisions, extensions, timeline changes, scope updates, or any other deal. Closes when you both agree.
                                </span>
                            </button>
                            <button
                                type="button"
                                class="rounded-xl border px-3 py-3 text-left transition"
                                :class="resolutionPath === 'support' ? 'border-amber-300 bg-amber-50/80 ring-1 ring-amber-200' : 'border-slate-100 bg-white hover:border-slate-200'"
                                @click="setResolutionPath('support')"
                            >
                                <span class="block text-sm font-black text-slate-900">Ask Customer Support to decide</span>
                                <span class="mt-1 block text-xs leading-normal text-slate-600">
                                    Request a ruling, more time, fixes, or mediation. Stays open until staff decide.
                                </span>
                            </button>
                        </div>

                        <template v-if="resolutionPath === 'together'">
                            <div>
                                <h4 class="text-sm font-bold text-slate-900">What do you want to agree on?</h4>
                                <p class="mt-1 text-xs leading-normal text-slate-600">
                                    Your proposal goes to the other party first. Customer Support is only notified after you both agree or accept a split.
                                </p>
                            </div>

                            <div v-for="group in togetherOptionGroups" :key="group.key" class="space-y-2">
                                <p class="text-[10px] font-black uppercase tracking-wide text-slate-500">{{ group.label }}</p>
                                <label
                                    v-for="opt in group.options"
                                    :key="opt.value"
                                    class="flex cursor-pointer gap-3 rounded-xl border px-3 py-3 transition"
                                    :class="resolutionForm.option === opt.value ? 'border-primary-300 bg-primary-50/60 ring-1 ring-primary-200' : 'border-slate-100 bg-white hover:border-slate-200'"
                                >
                                    <input
                                        v-model="resolutionForm.option"
                                        type="radio"
                                        class="mt-1 text-primary-600 focus:ring-primary-500"
                                        :value="opt.value"
                                        @change="onResolutionOptionChange(opt)"
                                    />
                                    <span class="min-w-0">
                                        <span class="block text-sm font-bold text-slate-900">{{ opt.label }}</span>
                                        <span class="mt-0.5 block text-xs leading-normal text-slate-600">{{ opt.hint }}</span>
                                    </span>
                                </label>
                            </div>

                            <div class="rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-3">
                                <p class="text-sm font-bold text-slate-900">Already aligned without a payment split?</p>
                                <p class="mt-1 text-xs leading-normal text-slate-600">
                                    Both parties must press the button below. Customer Support is notified automatically when you both confirm.
                                </p>
                                <div class="mt-2 flex flex-wrap gap-2 text-xs font-bold">
                                    <span class="rounded-full px-2 py-1" :class="dispute.client_agrees_resolve_at ? 'bg-emerald-100 text-emerald-900' : 'bg-slate-100 text-slate-600'">
                                        Client {{ dispute.client_agrees_resolve_at ? 'confirmed' : 'pending' }}
                                    </span>
                                    <span class="rounded-full px-2 py-1" :class="dispute.freelancer_agrees_resolve_at ? 'bg-emerald-100 text-emerald-900' : 'bg-slate-100 text-slate-600'">
                                        Freelancer {{ dispute.freelancer_agrees_resolve_at ? 'confirmed' : 'pending' }}
                                    </span>
                                </div>
                                <button
                                    v-if="!viewerHasAgreedMutual"
                                    type="button"
                                    class="mt-3 inline-flex items-center rounded-full bg-emerald-700 px-4 py-2 text-xs font-black uppercase tracking-wide text-white disabled:opacity-50"
                                    :disabled="mutualBusy"
                                    @click="agreeMutualResolve"
                                >
                                    <ReLoader4Line v-if="mutualBusy" class="mr-2 h-4 w-4 animate-spin" aria-hidden="true" />
                                    I agree to close this dispute
                                </button>
                                <p v-else class="mt-3 text-xs font-semibold text-emerald-800">
                                    You confirmed. Waiting for the other party.
                                </p>
                            </div>
                        </template>

                        <template v-else-if="resolutionPath === 'support'">
                            <div>
                                <h4 class="text-sm font-bold text-slate-900">What outcome do you want Customer Support to decide?</h4>
                                <p class="mt-1 text-xs leading-normal text-amber-900">
                                    This sends your request to Customer Support. It does not close the dispute or move money yet.
                                </p>
                            </div>

                            <div class="space-y-2">
                                <label
                                    v-for="opt in supportResolutionOptions"
                                    :key="opt.value"
                                    class="flex cursor-pointer gap-3 rounded-xl border px-3 py-3 transition"
                                    :class="resolutionForm.option === opt.value ? 'border-amber-300 bg-amber-50/60 ring-1 ring-amber-200' : 'border-slate-100 bg-white hover:border-slate-200'"
                                >
                                    <input
                                        v-model="resolutionForm.option"
                                        type="radio"
                                        class="mt-1 text-amber-600 focus:ring-amber-500"
                                        :value="opt.value"
                                        @change="onResolutionOptionChange(opt)"
                                    />
                                    <span class="min-w-0">
                                        <span class="block text-sm font-bold text-slate-900">{{ opt.label }}</span>
                                        <span class="mt-0.5 block text-xs leading-normal text-slate-600">{{ opt.hint }}</span>
                                    </span>
                                </label>
                            </div>
                        </template>

                        <template v-if="resolutionPath && resolutionForm.option">
                            <div v-if="selectedResolutionOption?.requires_client_share" class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label for="resolution-client-share" class="text-xs font-bold text-slate-700">Client keeps (%)</label>
                                    <input
                                        id="resolution-client-share"
                                        v-model.number="resolutionForm.client_share_percent"
                                        type="number"
                                        min="0"
                                        max="100"
                                        class="mt-1 w-full rounded-xl border-slate-200 text-base font-bold shadow-sm"
                                    />
                                    <p class="mt-1 text-[10px] text-slate-500">Freelancer gets {{ resolutionFreelancerShare }}%</p>
                                </div>
                            </div>

                            <div v-if="selectedResolutionOption?.requires_days">
                                <label for="resolution-extend-days" class="text-xs font-bold text-slate-700">
                                    {{ resolutionPath === 'support' ? 'Extra days you are asking for' : 'Extra days agreed' }}
                                </label>
                                <input
                                    id="resolution-extend-days"
                                    v-model.number="resolutionForm.extend_days"
                                    type="number"
                                    min="1"
                                    max="90"
                                    class="mt-1 w-32 rounded-xl border-slate-200 text-base font-bold shadow-sm"
                                />
                                <p class="mt-1 text-xs leading-normal text-slate-600">How many extra days you both agree to add to the delivery date.</p>
                            </div>

                            <div v-if="selectedResolutionOption?.optional_revision_days">
                                <label for="resolution-revision-days" class="text-xs font-bold text-slate-700">Days to complete the fix (optional)</label>
                                <input
                                    id="resolution-revision-days"
                                    v-model.number="resolutionForm.revision_days"
                                    type="number"
                                    min="1"
                                    max="90"
                                    class="mt-1 w-32 rounded-xl border-slate-200 text-base font-bold shadow-sm"
                                />
                                <p class="mt-1 text-xs leading-normal text-slate-600">e.g. freelancer has 7 days to redo the bathroom tiling.</p>
                            </div>

                            <div v-if="selectedResolutionOption?.requires_target_date">
                                <label for="resolution-target-date" class="text-xs font-bold text-slate-700">New completion date</label>
                                <input
                                    id="resolution-target-date"
                                    v-model="resolutionForm.target_completion_date"
                                    type="date"
                                    class="mt-1 w-full max-w-xs rounded-xl border-slate-200 text-base font-bold shadow-sm"
                                />
                                <p class="mt-1 text-xs leading-normal text-slate-600">The finish date or milestone you both agree on.</p>
                            </div>

                            <div>
                                <label for="resolution-terms-note" class="text-xs font-bold text-slate-700">
                                    {{ resolutionTermsLabel }}
                                </label>
                                <textarea
                                    id="resolution-terms-note"
                                    v-model="resolutionForm.terms_note"
                                    :rows="selectedResolutionOption?.value === 'other' ? 5 : 3"
                                    class="mt-1 w-full rounded-xl border-slate-200 text-base leading-normal shadow-sm"
                                    :placeholder="resolutionNotePlaceholder"
                                />
                            </div>

                            <button
                                type="submit"
                                class="inline-flex items-center rounded-full px-4 py-2.5 text-xs font-black uppercase tracking-wide text-white disabled:opacity-50"
                                :class="resolutionPath === 'support' ? 'bg-amber-700' : 'bg-emerald-700'"
                                :disabled="resolutionForm.processing || !resolutionForm.option"
                            >
                                <ReLoader4Line v-if="resolutionForm.processing" class="mr-2 h-4 w-4 animate-spin" aria-hidden="true" />
                                {{ resolutionSubmitLabel }}
                            </button>
                        </template>
                    </form>
                </div>
            </section>

            <section v-if="dispute.resolution_requests?.length" class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100">
                <h2 class="font-display text-sm font-black uppercase tracking-wide text-slate-500">
                    Resolution proposals
                </h2>
                <ul class="mt-3 space-y-2">
                    <li
                        v-for="req in dispute.resolution_requests"
                        :key="req.id"
                        class="rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-2.5 text-sm text-slate-800"
                    >
                        <span class="font-bold">{{ req.option_label }}</span>
                        <span class="text-xs text-slate-500"> · {{ req.requested_by?.name }} · {{ formatProposalStatus(req.status) }}</span>
                        <p v-if="req.status === 'matched'" class="mt-1 text-xs font-bold text-emerald-800">
                            Both parties agreed — Customer Support will review and close the case.
                        </p>
                        <p v-if="req.terms?.note" class="mt-1 text-xs leading-normal text-slate-600">{{ req.terms.note }}</p>
                        <p v-if="req.terms?.extend_days != null" class="mt-0.5 text-xs text-slate-600">
                            Extension: {{ req.terms.extend_days }} day(s)
                        </p>
                        <p v-if="req.terms?.revision_days != null" class="mt-0.5 text-xs text-slate-600">
                            Fix within: {{ req.terms.revision_days }} day(s)
                        </p>
                        <p v-if="req.terms?.target_completion_date" class="mt-0.5 text-xs text-slate-600">
                            New completion date: {{ req.terms.target_completion_date }}
                        </p>
                        <p v-if="req.terms?.client_share_percent != null" class="mt-0.5 text-xs text-slate-600">
                            Client keeps {{ req.terms.client_share_percent }}%
                        </p>
                    </li>
                </ul>
            </section>

            <section v-if="dispute.settlement_offers?.length" class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100">
                <h2 class="font-display text-sm font-black uppercase tracking-wide text-slate-500">
                    Settlement offers
                </h2>
                <ul class="mt-3 space-y-3">
                    <li v-for="o in dispute.settlement_offers" :key="o.id" class="rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-3 text-sm font-semibold text-slate-800">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span>Client {{ o.client_share_percent }}% · {{ o.status }}</span>
                            <span class="text-xs text-slate-500">by {{ o.offered_by?.name }}</span>
                        </div>
                        <p v-if="o.note" class="mt-1 text-xs font-medium text-slate-600">
                            {{ o.note }}
                        </p>
                        <div v-if="o.accept_url && o.decline_url" class="mt-2 flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="rounded-full bg-emerald-700 px-3 py-1.5 text-[10px] font-black uppercase tracking-wide text-white disabled:opacity-50"
                                :disabled="offerBusyId === o.id"
                                @click="acceptOffer(o)"
                            >
                                <ReLoader4Line v-if="offerBusyId === o.id && offerBusyAction === 'accept'" class="mr-1 inline h-3 w-3 animate-spin" aria-hidden="true" />
                                Accept
                            </button>
                            <button
                                type="button"
                                class="rounded-full border border-rose-200 bg-white px-3 py-1.5 text-[10px] font-black uppercase tracking-wide text-rose-800 disabled:opacity-50"
                                :disabled="offerBusyId === o.id"
                                @click="declineOffer(o)"
                            >
                                <ReLoader4Line v-if="offerBusyId === o.id && offerBusyAction === 'decline'" class="mr-1 inline h-3 w-3 animate-spin" aria-hidden="true" />
                                Decline
                            </button>
                        </div>
                    </li>
                </ul>
            </section>

            <section class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100">
                <h2 class="font-display text-sm font-black uppercase tracking-wide text-slate-500">
                    Conversation
                </h2>
                <p v-if="!dispute.messages.length" class="mt-3 text-base font-medium leading-normal text-slate-600">
                    No updates yet beyond your case summary. Use “Resolve now” to post a message or settlement offer.
                </p>
                <ul v-else class="mt-4 space-y-4">
                    <li v-for="m in dispute.messages" :key="m.id" class="rounded-xl border border-slate-100 bg-slate-50/60 px-3 py-3">
                        <p class="text-[10px] font-black uppercase tracking-wide text-slate-500">
                            {{ m.kind_label || m.kind }} · {{ m.is_system ? 'HustleSafe' : (m.user ? m.user.name : 'System') }} · {{ formatWhen(m.created_at) }}
                        </p>
                        <p v-if="m.body" class="mt-2 whitespace-pre-wrap text-base font-medium leading-normal text-slate-800">
                            {{ m.body }}
                        </p>
                    </li>
                </ul>
            </section>

            <section v-if="dispute.events.length" class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100">
                <h2 class="font-display text-sm font-black uppercase tracking-wide text-slate-500">
                    Case updates
                </h2>
                <ul class="mt-3 space-y-2 text-xs font-semibold text-slate-600">
                    <li v-for="(e, i) in dispute.events" :key="i">
                        <span class="font-black text-slate-900">{{ e.action_label || e.action }}</span>
                        · {{ formatWhen(e.created_at) }}
                    </li>
                </ul>
            </section>
        </div>
    </AppShell>
</template>

<script setup>
import BackChevronLink from '@/Components/Ui/BackChevronLink.vue';
import DisputeNegotiationPanel from '@/Pages/Disputes/Components/DisputeNegotiationPanel.vue';
import DisputeAppealPanel from '@/Pages/Disputes/Components/DisputeAppealPanel.vue';
import SlaExpectationNotice from '@/Components/Platform/SlaExpectationNotice.vue';
import AppShell from '@/Layouts/AppShell.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ReLoader4Line } from '@kalimahapps/vue-icons/re';
import { computed, ref } from 'vue';

const showResolutionPanel = ref(false);
const messageIsProof = ref(false);
const resolutionPath = ref(null);
const mutualBusy = ref(false);

const props = defineProps({
    dispute: { type: Object, required: true },
    workflow: { type: Object, default: null },
    can_participate: { type: Boolean, default: false },
    philosophy: { type: Object, default: () => ({}) },
    policy: { type: Object, required: true },
    sla_expectation: { type: String, default: '' },
});

const isOpen = computed(() => !['resolved', 'closed_withdrawn'].includes(props.dispute.status));

const intake = computed(() => props.dispute.structured_intake ?? {});
const labels = computed(() => props.dispute.intake_labels ?? {});

const hasIntakeDetails = computed(() =>
    Boolean(
        props.dispute.opening_summary
        || intake.value.resolution_requested
        || intake.value.impact
        || intake.value.timeline
        || intake.value.evidence_files?.length,
    ),
);

function minorToNgn(minor) {
    if (minor === null || minor === undefined) return null;
    return `₦${(Number(minor) / 100).toLocaleString()}`;
}

const intakeImpactRows = computed(() => {
    const impact = intake.value.impact ?? {};
    const rows = [];
    if (impact.financial_impact_minor != null) rows.push({ label: 'Financial impact', value: minorToNgn(impact.financial_impact_minor) });
    if (impact.timeline_delay_days != null) rows.push({ label: 'Timeline delay', value: `${impact.timeline_delay_days} days` });
    if (impact.contract_value_at_risk_minor != null) rows.push({ label: 'Contract value at risk', value: minorToNgn(impact.contract_value_at_risk_minor) });
    if (impact.reputation_impact) rows.push({ label: 'Reputation impact', value: 'Yes' });
    return rows;
});

const intakeTimelineRows = computed(() => {
    const timeline = intake.value.timeline ?? {};
    const map = {
        contract_awarded: 'Contract awarded',
        work_started: 'Work started',
        expected_delivery: 'Expected delivery',
        deliverable_submitted: 'Deliverable submitted',
        issue_first_noticed: 'Issue first noticed',
        informal_resolution_date: 'Informal resolution date',
        escalation_date: 'Escalation date',
    };
    return Object.entries(map)
        .filter(([key]) => timeline[key])
        .map(([key, label]) => ({ label, value: timeline[key] }));
});

const intakeAffectedAreas = computed(() =>
    (intake.value.affected_areas ?? []).map((v) => labels.value[`affected_${v}`] ?? v.replaceAll('_', ' ')),
);

const intakePriorAttempts = computed(() =>
    (intake.value.prior_attempts ?? []).map((v) => labels.value[`prior_${v}`] ?? v.replaceAll('_', ' ')),
);

function formatResolution(value, amountMinor) {
    const base = value.replaceAll('_', ' ');
    if (['partial_refund', 'partial_payment'].includes(value) && amountMinor) {
        return `${base} · ${minorToNgn(amountMinor)}`;
    }
    return base;
}

const messageForm = useForm({
    kind: 'narrative',
    body: '',
    structured_key: '',
    structured_payload: null,
});

const resolutionForm = useForm({
    option: '',
    client_share_percent: 50,
    extend_days: 7,
    revision_days: 7,
    target_completion_date: '',
    terms_note: '',
});

const selectedResolutionOption = computed(() =>
    (props.dispute.resolution_options ?? []).find((o) => o.value === resolutionForm.option) ?? null,
);

const togetherResolutionOptions = computed(() =>
    (props.dispute.resolution_options ?? []).filter((o) => o.path === 'together'),
);

const togetherOptionGroups = computed(() => {
    const headings = {
        payment: 'Payment',
        delivery: 'Work, timeline & deliverables',
        negotiation: 'Other agreements',
    };
    const groups = {};

    for (const opt of togetherResolutionOptions.value) {
        const key = opt.category || 'negotiation';
        if (!groups[key]) {
            groups[key] = { key, label: headings[key] || 'Agreements', options: [] };
        }
        groups[key].options.push(opt);
    }

    return Object.values(groups);
});

const supportResolutionOptions = computed(() =>
    (props.dispute.resolution_options ?? []).filter((o) => o.path === 'support'),
);

const viewerHasAgreedMutual = computed(() => {
    const role = props.dispute.viewer_role;
    if (role === 'client') {
        return Boolean(props.dispute.client_agrees_resolve_at);
    }
    if (role === 'freelancer') {
        return Boolean(props.dispute.freelancer_agrees_resolve_at);
    }
    return false;
});

const resolutionSubmitLabel = computed(() => {
    if (resolutionPath.value === 'support') {
        return 'Send to Customer Support';
    }
    if (resolutionForm.option === 'split_fund') {
        return 'Send split to other party';
    }
    if (resolutionForm.option === 'other') {
        return 'Send agreement to other party';
    }
    return 'Send proposal to other party';
});

const resolutionTermsLabel = computed(() => {
    const opt = selectedResolutionOption.value;
    if (!opt) {
        return 'Explain in plain language';
    }
    if (opt.value === 'other') {
        return 'Describe the agreement (required)';
    }
    if (opt.value === 'scope_adjustment') {
        return 'What deliverables or scope are you changing?';
    }
    if (opt.value === 'revise_redo') {
        return 'What needs to be fixed, redone, or repaired?';
    }
    if (opt.category === 'delivery') {
        return 'Describe the timeline or work agreement';
    }
    return 'Explain in plain language';
});

const resolutionFreelancerShare = computed(() => {
    const client = Number(resolutionForm.client_share_percent);
    if (!Number.isFinite(client)) return '—';
    return Math.max(0, Math.min(100, 100 - client));
});

const resolutionNotePlaceholder = computed(() => {
    const opt = selectedResolutionOption.value;
    if (!opt) return 'Explain what you want and why it is fair.';
    if (opt.value === 'other') return 'Example: Client will supply tiles; freelancer reinstalls within 10 days. No refund — job continues.';
    if (opt.value === 'scope_adjustment') return 'Example: Add electrical points in two extra rooms; remove garden lighting from scope.';
    if (opt.value === 'revise_redo') return 'Example: Replace cracked tiles in the bathroom and reseal the shower — same materials as agreed.';
    if (opt.value === 'adjust_timeline') return 'Example: Full handover moved to 30 June; plumbing milestone by 15 June.';
    if (opt.value === 'extend_delivery') return 'Example: Rain delayed outdoor work — we agree to add 14 days.';
    if (opt.value === 'refund_cancel') return 'Example: We both want to end this job and refund the client.';
    if (opt.value === 'mediation') return 'Example: We keep talking past each other — we need a mediator.';
    if (opt.value === 'force_revision') return 'Example: The work is close but needs styling fixes within 7 days.';
    if (opt.value === 'extend_deadline') return 'Example: Family emergency delayed me — I need 7 more days.';
    return 'Example: Half the work was done, so a 50/50 split is fair.';
});

function onResolutionOptionChange(opt) {
    if (opt.default_client_share_percent != null) {
        resolutionForm.client_share_percent = opt.default_client_share_percent;
    }
}

function setResolutionPath(path) {
    resolutionPath.value = path;
    resolutionForm.option = '';
    resolutionForm.terms_note = '';
    resolutionForm.target_completion_date = '';
}

function formatProposalStatus(status) {
    const map = {
        pending: 'Waiting for other party',
        matched: 'Both parties agreed',
        superseded: 'Replaced',
        settlement_offered: 'Sent as payment split',
    };
    return map[status] || status;
}

function submitResolutionRequest() {
    resolutionForm
        .transform((data) => ({
            ...data,
            target_completion_date: data.target_completion_date || null,
            revision_days: selectedResolutionOption.value?.optional_revision_days ? data.revision_days : null,
        }))
        .post(props.dispute.urls.resolution_request, {
            preserveScroll: true,
            onSuccess: () => {
                resolutionForm.reset('terms_note', 'target_completion_date');
                resolutionForm.option = '';
            },
        });
}

function agreeMutualResolve() {
    mutualBusy.value = true;
    router.post(props.dispute.urls.mutual_resolve, {}, {
        preserveScroll: true,
        onFinish: () => {
            mutualBusy.value = false;
        },
    });
}

const offerBusyId = ref(null);
const offerBusyAction = ref(null);

function submitMessage() {
    messageForm
        .transform((data) => ({
            ...data,
            kind: messageIsProof.value ? 'evidence' : 'narrative',
            structured_key: '',
            structured_payload: null,
        }))
        .post(props.dispute.urls.message, {
            preserveScroll: true,
            onSuccess: () => {
                messageForm.reset('body');
                messageIsProof.value = false;
            },
        });
}

function acceptOffer(o) {
    offerBusyId.value = o.id;
    offerBusyAction.value = 'accept';
    router.post(o.accept_url, {}, {
        preserveScroll: true,
        onFinish: () => {
            offerBusyId.value = null;
            offerBusyAction.value = null;
        },
    });
}

function declineOffer(o) {
    offerBusyId.value = o.id;
    offerBusyAction.value = 'decline';
    router.post(o.decline_url, {}, {
        preserveScroll: true,
        onFinish: () => {
            offerBusyId.value = null;
            offerBusyAction.value = null;
        },
    });
}

function formatWhen(iso) {
    if (!iso) {
        return '—';
    }
    try {
        return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso));
    } catch {
        return iso;
    }
}

function stageStateClass(state) {
    return {
        completed: 'border-emerald-100 bg-emerald-50/50',
        current: 'border-primary-200 bg-primary-50/60 ring-1 ring-primary-100',
        upcoming: 'border-slate-100 bg-white/80 opacity-80',
    }[state] ?? 'border-slate-100 bg-white';
}

function stageBadgeClass(state) {
    return {
        completed: 'bg-emerald-600 text-white',
        current: 'bg-primary-700 text-white',
        upcoming: 'bg-slate-200 text-slate-600',
    }[state] ?? 'bg-slate-200 text-slate-600';
}
</script>
