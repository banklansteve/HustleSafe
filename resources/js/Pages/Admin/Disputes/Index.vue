<template>
    <AdminShell
        title="All disputes"
        subtitle="Staff investigate and recommend — Super Admin decides, executes outcomes, and maintains the immutable audit trail."
    >
        <div class="space-y-5">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <div class="rounded-3xl border p-4 shadow-sm" :class="shell.card">
                    <p class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">Total</p>
                    <p class="mt-2 text-3xl font-black" :class="shell.title">{{ summaryState.total }}</p>
                </div>
                <div class="rounded-3xl border border-rose-200 bg-rose-50/60 p-4 shadow-sm dark:border-rose-900/40 dark:bg-rose-950/20">
                    <p class="text-[10px] font-black uppercase tracking-wider text-rose-800">Open</p>
                    <p class="mt-2 text-3xl font-black text-rose-950">{{ summaryState.open }}</p>
                </div>
                <div class="rounded-3xl border border-amber-200 bg-amber-50/60 p-4 shadow-sm dark:border-amber-900/40">
                    <p class="text-[10px] font-black uppercase tracking-wider text-amber-900">Under review</p>
                    <p class="mt-2 text-3xl font-black text-amber-950">{{ summaryState.under_review }}</p>
                </div>
                <div class="rounded-3xl border border-violet-200 bg-violet-50/60 p-4 shadow-sm dark:border-violet-900/40">
                    <p class="text-[10px] font-black uppercase tracking-wider text-violet-900">Ready for decision</p>
                    <p class="mt-2 text-3xl font-black text-violet-950">{{ summaryState.ready_for_decision }}</p>
                </div>
                <div class="rounded-3xl border border-emerald-200 bg-emerald-50/60 p-4 shadow-sm dark:border-emerald-900/40">
                    <p class="text-[10px] font-black uppercase tracking-wider text-emerald-800">Resolved</p>
                    <p class="mt-2 text-3xl font-black text-emerald-950">{{ summaryState.resolved }}</p>
                </div>
            </div>

            <AdminPanel title="Dispute registry" description="Filter, sort, and open any dispute for final decision or reassignment.">
                <template #actions>
                    <AdminQuickActions :export-actions="[{ label: 'Export CSV', href: exportUrl }]" />
                </template>

                <div class="mb-4 flex flex-wrap gap-2">
                    <button
                        v-for="filter in summaryState.filters"
                        :key="filter.key"
                        type="button"
                        class="rounded-full px-3 py-1.5 text-[10px] font-black uppercase tracking-wide transition"
                        :class="activeFilter === filter.key ? 'bg-primary-700 text-white' : shell.btnGhost"
                        @click="setFilter(filter.key)"
                    >
                        {{ filter.label }}
                    </button>
                </div>

                <div class="mb-4 flex flex-wrap items-center gap-2">
                    <select v-model="activeSort" class="rounded-2xl border px-3 py-2.5 text-xs font-bold uppercase" :class="shell.input" @change="reload">
                        <option v-for="sort in summaryState.sorts" :key="sort.key" :value="sort.key">{{ sort.label }}</option>
                    </select>
                    <input
                        v-model="searchQuery"
                        type="search"
                        placeholder="Search dispute, contract, party, staff…"
                        class="min-h-11 flex-1 rounded-2xl border px-4 text-sm font-semibold"
                        :class="shell.input"
                        @keydown.enter="reload"
                    />
                    <button type="button" class="rounded-2xl px-4 py-2.5 text-xs font-black uppercase" :class="shell.btnPrimary" @click="reload">Apply</button>
                </div>

                <div class="hidden overflow-x-auto lg:block">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10">
                        <thead>
                            <tr class="text-left text-[10px] font-black uppercase tracking-wider" :class="shell.label">
                                <th class="px-3 py-3">DSP-ID</th>
                                <th class="px-3 py-3">Contract</th>
                                <th class="px-3 py-3">Type</th>
                                <th class="px-3 py-3">Value</th>
                                <th class="px-3 py-3">Assigned to</th>
                                <th class="px-3 py-3">Status</th>
                                <th class="px-3 py-3">Days</th>
                                <th class="px-3 py-3" />
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-white/10">
                            <tr
                                v-for="row in queue.pageItems.value"
                                :key="row.uuid || row.id"
                                class="cursor-pointer hover:bg-primary-50/50 dark:hover:bg-white/[0.03]"
                                @click="openDetail(row)"
                            >
                                <td class="px-3 py-3 font-mono text-xs font-black">{{ row.reference }}</td>
                                <td class="px-3 py-3">
                                    <a
                                        v-if="row.contract?.url"
                                        :href="row.contract.url"
                                        class="font-semibold text-primary-700 underline underline-offset-2"
                                        @click.stop
                                    >{{ row.contract.reference_code }}</a>
                                    <span v-else class="font-semibold">{{ row.contract_reference || row.quest_reference || '—' }}</span>
                                    <span class="mt-0.5 block text-xs text-slate-500">{{ row.quest }}</span>
                                </td>
                                <td class="px-3 py-3 text-xs font-semibold">{{ row.category_label || '—' }}</td>
                                <td class="px-3 py-3 font-black">{{ formatMinor(row.disputed_amount_minor) }}</td>
                                <td class="px-3 py-3 text-xs font-semibold">{{ row.assigned_staff || '—' }}</td>
                                <td class="px-3 py-3">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-black uppercase" :class="statusClass(row.management_badge_tone)">
                                        {{ row.management_status_label }}
                                    </span>
                                </td>
                                <td class="px-3 py-3 text-xs font-bold">{{ row.days_open }}d</td>
                                <td class="px-3 py-3">
                                    <button type="button" class="text-[10px] font-black uppercase text-primary-700 underline" @click.stop="openDetail(row)">Open</button>
                                </td>
                            </tr>
                            <tr v-if="!queue.pageItems.value.length && !loading">
                                <td colspan="8" class="px-3 py-12 text-center text-sm font-semibold text-slate-500">No disputes match your filters.</td>
                            </tr>
                            <tr v-if="loading">
                                <td colspan="8" class="px-3 py-12 text-center text-sm font-semibold text-slate-500">Loading…</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="grid gap-3 lg:hidden">
                    <button
                        v-for="row in queue.pageItems.value"
                        :key="row.uuid || row.id"
                        type="button"
                        class="rounded-3xl border p-4 text-left"
                        :class="shell.card"
                        @click="openDetail(row)"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-mono text-xs font-black">{{ row.reference }}</p>
                                <p class="mt-1 font-black">
                                    <a
                                        v-if="row.contract?.url"
                                        :href="row.contract.url"
                                        class="text-primary-800 underline underline-offset-2"
                                        @click.stop
                                    >{{ row.contract.reference_code }}</a>
                                    <span v-else>{{ row.contract_reference || row.quest_reference }}</span>
                                </p>
                                <p class="text-xs text-slate-500">{{ row.quest }}</p>
                            </div>
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-black uppercase" :class="statusClass(row.management_badge_tone)">
                                {{ row.management_status_label }}
                            </span>
                        </div>
                        <p class="mt-3 text-xs font-bold">{{ row.assigned_staff || 'Unassigned' }} · {{ formatMinor(row.disputed_amount_minor) }} · {{ row.days_open }}d</p>
                    </button>
                </div>

                <div v-if="queue.totalPages.value > 1" class="mt-4 flex flex-wrap items-center justify-between gap-2 text-xs font-bold">
                    <span :class="shell.cardMuted">{{ queue.total.value }} dispute(s)</span>
                    <div class="flex gap-2">
                        <button type="button" class="rounded-xl border px-3 py-1.5" :class="shell.btnGhost" :disabled="queue.page.value <= 1" @click="queue.page.value--">Prev</button>
                        <span class="px-2 py-1.5" :class="shell.cardMuted">Page {{ queue.page.value }} / {{ queue.totalPages.value }}</span>
                        <button type="button" class="rounded-xl border px-3 py-1.5" :class="shell.btnGhost" :disabled="queue.page.value >= queue.totalPages.value" @click="queue.page.value++">Next</button>
                    </div>
                </div>
            </AdminPanel>
        </div>

        <AdminSlideOver
            :open="slideOpen"
            :title="slideTitle"
            eyebrow="Super Admin dispute workspace"
            width-class="max-w-full sm:max-w-2xl lg:max-w-3xl"
            @close="slideOpen = false"
        >
            <div v-if="detailLoading" class="py-12 text-center text-sm text-slate-500">Loading dispute…</div>
            <div v-else-if="detail" class="space-y-5">
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-2xl border p-3" :class="shell.card">
                        <p class="text-[10px] font-black uppercase" :class="shell.label">Status</p>
                        <p class="mt-1 text-sm font-black" :class="shell.title">{{ detail.dispute.management_status_label }}</p>
                    </div>
                    <div class="rounded-2xl border p-3" :class="shell.card">
                        <p class="text-[10px] font-black uppercase" :class="shell.label">Severity</p>
                        <p class="mt-1 text-sm font-black capitalize" :class="shell.title">{{ detail.dispute.severity || '—' }}</p>
                    </div>
                    <div class="rounded-2xl border p-3" :class="shell.card">
                        <p class="text-[10px] font-black uppercase" :class="shell.label">Value</p>
                        <p class="mt-1 text-sm font-black" :class="shell.title">{{ formatMinor(detail.dispute.disputed_amount_minor) }}</p>
                    </div>
                </div>

                <section class="rounded-3xl border p-4" :class="detail.dispute.needs_admin_acknowledgment ? 'border-amber-300 bg-amber-50/50 dark:border-amber-800/40 dark:bg-amber-950/20' : shell.card">
                    <p class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">Case status</p>
                    <div class="mt-2 flex flex-wrap gap-2 text-xs font-bold">
                        <span class="rounded-full bg-slate-100 px-3 py-1 dark:bg-white/10">{{ detail.dispute.management_status_label }}</span>
                        <span v-if="detail.dispute.resolution_outcome_label" class="rounded-full bg-emerald-100 px-3 py-1 text-emerald-900 dark:bg-emerald-900/30 dark:text-emerald-100">
                            {{ detail.dispute.resolution_outcome_label }}
                        </span>
                        <span v-if="detail.dispute.party_self_resolved" class="rounded-full bg-sky-100 px-3 py-1 text-sky-900">Party self-resolved</span>
                        <span v-if="detail.dispute.needs_admin_acknowledgment" class="rounded-full bg-amber-200 px-3 py-1 text-amber-950">Awaiting your review</span>
                        <span v-if="detail.dispute.finalized_at" class="rounded-full bg-slate-200 px-3 py-1 text-slate-900">Finalized</span>
                    </div>
                    <p v-if="detail.dispute.needs_admin_acknowledgment" class="mt-3 text-xs font-semibold text-amber-950 dark:text-amber-100">
                        Parties closed this dispute without a formal ruling. Acknowledge after review, then finalize when ready.
                    </p>
                    <p v-else-if="detail.dispute.party_self_resolved && detail.dispute.management_status === 'resolved'" class="mt-3 text-xs font-semibold text-emerald-900 dark:text-emerald-100">
                        Party resolution acknowledged. You can finalize the file when the appeal window policy allows.
                    </p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <button
                            v-if="detail.permissions?.can_acknowledge_party_resolution"
                            type="button"
                            class="rounded-2xl px-4 py-2.5 text-xs font-black uppercase text-white bg-emerald-700 hover:bg-emerald-800"
                            :disabled="busy.acknowledge"
                            @click="acknowledgePartyResolution"
                        >
                            Mark as reviewed (party resolved)
                        </button>
                        <button
                            v-if="detail.permissions?.can_finalize"
                            type="button"
                            class="rounded-2xl px-4 py-2.5 text-xs font-black uppercase text-white bg-slate-900 dark:bg-white dark:text-slate-950"
                            :disabled="busy.finalize"
                            @click="openAction('finalize')"
                        >
                            Finalize &amp; close file
                        </button>
                        <button
                            v-if="detail.permissions?.can_decide && detail.dispute.management_status === 'ready_for_decision'"
                            type="button"
                            class="rounded-2xl border px-4 py-2.5 text-xs font-black uppercase"
                            :class="shell.btnPrimary"
                            @click="scrollToDecision"
                        >
                            Issue formal decision
                        </button>
                    </div>
                </section>

                <div class="flex flex-wrap gap-2 text-xs font-bold">
                    <span class="rounded-full bg-slate-100 px-3 py-1 dark:bg-white/10">Client: {{ detail.parties?.client?.name || '—' }}</span>
                    <span class="rounded-full bg-slate-100 px-3 py-1 dark:bg-white/10">Freelancer: {{ detail.parties?.freelancer?.name || '—' }}</span>
                    <span class="rounded-full bg-slate-100 px-3 py-1 dark:bg-white/10">Filed by: {{ detail.parties?.filed_by_party }}</span>
                    <span class="rounded-full bg-slate-100 px-3 py-1 dark:bg-white/10">Assigned: {{ detail.dispute.assigned_staff || '—' }}</span>
                    <a
                        v-if="detail.dispute.contract?.url"
                        :href="detail.dispute.contract.url"
                        class="rounded-full bg-primary-100 px-3 py-1 text-primary-900 underline underline-offset-2 dark:bg-primary-900/30 dark:text-primary-100"
                    >
                        Contract {{ detail.dispute.contract.reference_code }}
                    </a>
                    <span
                        v-if="detail.dispute.party_self_resolved"
                        class="rounded-full bg-emerald-100 px-3 py-1 text-emerald-900 dark:bg-emerald-900/30 dark:text-emerald-100"
                    >
                        Party self-resolved
                    </span>
                </div>

                <section
                    v-if="detail.self_resolution_activity?.events?.length || detail.dispute.party_self_resolved"
                    class="rounded-3xl border border-emerald-200 bg-emerald-50/60 p-4 dark:border-emerald-900/40 dark:bg-emerald-950/20"
                >
                    <p class="text-[10px] font-black uppercase tracking-wider text-emerald-900 dark:text-emerald-200">Self-resolution activity</p>
                    <p v-if="detail.self_resolution_activity?.outcome_label" class="mt-1 text-sm font-bold text-emerald-950 dark:text-emerald-100">
                        Outcome: {{ detail.self_resolution_activity.outcome_label }}
                    </p>
                    <p v-if="detail.dispute.needs_admin_acknowledgment" class="mt-2 text-xs font-semibold text-amber-900 dark:text-amber-200">
                        Parties closed this case — acknowledge when you have reviewed the file.
                    </p>
                    <p class="mt-2 text-xs font-semibold text-emerald-900/80 dark:text-emerald-200/80">
                        {{ detail.self_resolution_activity?.settlement_offer_count || 0 }} settlement offer(s) ·
                        {{ detail.self_resolution_activity?.resolution_proposal_count || 0 }} resolution proposal(s)
                    </p>
                    <ul v-if="detail.self_resolution_activity?.events?.length" class="mt-3 max-h-48 space-y-2 overflow-y-auto text-xs font-semibold">
                        <li
                            v-for="event in detail.self_resolution_activity.events"
                            :key="event.id"
                            class="rounded-xl border border-emerald-100 bg-white/80 px-3 py-2 dark:border-emerald-900/30 dark:bg-white/5"
                        >
                            <p class="font-black text-emerald-950 dark:text-emerald-100">{{ formatWhen(event.created_at) }} · {{ event.actor }}</p>
                            <p class="mt-0.5 font-bold">{{ event.action_label }}</p>
                        </li>
                    </ul>
                </section>

                <section v-if="detail.contract_disputes?.length" class="rounded-3xl border p-4" :class="shell.card">
                    <p class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">Contract dispute history</p>
                    <p class="mt-1 text-xs font-semibold text-slate-500">Only one dispute can be active per contract. Previous cases remain on record.</p>
                    <ul class="mt-3 space-y-2 text-xs font-semibold">
                        <li v-for="item in detail.contract_disputes" :key="item.uuid" class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-2">
                            <span>
                                <a :href="item.admin_url" class="font-black text-primary-800 underline">{{ item.reference }}</a>
                                · {{ item.management_status_label }}
                                <span v-if="item.is_active" class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-black uppercase text-amber-900">Active</span>
                            </span>
                            <span class="text-slate-500">{{ formatWhen(item.created_at) }}</span>
                        </li>
                    </ul>
                </section>

                <section class="rounded-3xl border p-4" :class="shell.card">
                    <p class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">Dispute reason</p>
                    <p class="mt-1 text-sm font-bold" :class="shell.title">{{ detail.dispute.reason_label }}</p>
                    <p class="mt-3 whitespace-pre-wrap text-sm font-medium leading-relaxed text-slate-700 dark:text-slate-200">{{ detail.intake.description }}</p>
                    <div v-if="detail.intake.evidence_files?.length" class="mt-3">
                        <p class="text-[10px] font-black uppercase" :class="shell.label">Evidence</p>
                        <ul class="mt-1 space-y-1 text-xs font-semibold text-primary-700">
                            <li v-for="(file, idx) in detail.intake.evidence_files" :key="idx">
                                <a :href="file.url" target="_blank" rel="noopener noreferrer" class="underline">{{ file.original_name }}</a>
                            </li>
                        </ul>
                    </div>
                </section>

                <section v-if="detail.timeline && Object.keys(detail.timeline).length" class="rounded-3xl border p-4" :class="shell.card">
                    <p class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">Timeline</p>
                    <ul class="mt-2 space-y-1 text-sm font-semibold">
                        <li v-for="(value, key) in detail.timeline" :key="key">
                            <span class="text-slate-500">{{ formatTimelineKey(key) }}:</span> {{ value || '—' }}
                        </li>
                    </ul>
                </section>

                <section v-if="detail.latest_submitted_assessment" class="rounded-3xl border border-violet-200 bg-violet-50/50 p-4 dark:border-violet-900/40">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-[10px] font-black uppercase tracking-wider text-violet-900">Staff assessment (requires your approval)</p>
                        <button
                            v-if="detail.permissions?.can_decide"
                            type="button"
                            class="rounded-xl bg-violet-700 px-3 py-1.5 text-[10px] font-black uppercase text-white"
                            :disabled="busy.approve"
                            @click="loadStaffApproval"
                        >
                            Load staff recommendation
                        </button>
                    </div>
                    <p class="mt-1 text-xs font-bold text-violet-800">
                        {{ detail.latest_submitted_assessment.staff?.name || 'Staff' }}
                        <span v-if="detail.latest_submitted_assessment.time_spent_minutes"> · {{ formatMinutes(detail.latest_submitted_assessment.time_spent_minutes) }}</span>
                    </p>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        <div>
                            <p class="text-[10px] font-black uppercase text-violet-800">Quality</p>
                            <p class="text-sm font-black">{{ detail.latest_submitted_assessment.quality_rating || '—' }}/5</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-black uppercase text-violet-800">Recommendation</p>
                            <p class="text-sm font-black">{{ detail.latest_submitted_assessment.recommendation_label || '—' }}</p>
                            <p v-if="detail.latest_submitted_assessment.recommended_client_share_percent != null" class="text-xs font-semibold text-violet-900">
                                Client {{ detail.latest_submitted_assessment.recommended_client_share_percent }}% / Freelancer {{ 100 - detail.latest_submitted_assessment.recommended_client_share_percent }}%
                            </p>
                        </div>
                    </div>
                    <p v-if="detail.latest_submitted_assessment.violation_status" class="mt-2 text-xs font-bold uppercase text-violet-900">
                        {{ detail.latest_submitted_assessment.violation_status.replace(/_/g, ' ') }}
                    </p>
                    <ul v-if="detail.latest_submitted_assessment.key_findings?.length" class="mt-3 list-disc space-y-1 pl-5 text-sm font-semibold text-slate-800">
                        <li v-for="(finding, i) in detail.latest_submitted_assessment.key_findings" :key="i">{{ finding }}</li>
                    </ul>
                    <p class="mt-3 whitespace-pre-wrap text-sm font-medium text-slate-800">{{ detail.latest_submitted_assessment.reasoning }}</p>
                    <ul v-if="detail.latest_submitted_assessment.alternate_recommendations?.length" class="mt-3 space-y-1 text-xs font-semibold text-violet-900">
                        <li v-for="alt in detail.latest_submitted_assessment.alternate_recommendations" :key="alt">
                            Also viable: {{ formatResolutionOption(alt) }}
                        </li>
                    </ul>
                </section>

                <section v-if="detail.resolution_requests?.length" class="rounded-3xl border border-amber-200 bg-amber-50/50 p-4">
                    <p class="text-[10px] font-black uppercase tracking-wider text-amber-900">Party proposals</p>
                    <ul class="mt-3 space-y-2 text-sm font-semibold text-slate-800">
                        <li v-for="req in detail.resolution_requests" :key="req.id" class="rounded-xl border border-amber-100 bg-white/80 px-3 py-2">
                            <span class="font-black">{{ req.option_label }}</span>
                            <span class="text-xs text-slate-500"> · {{ req.requested_by }} · {{ req.status }}</span>
                            <p v-if="req.terms?.note" class="mt-1 text-xs font-medium text-slate-600">{{ req.terms.note }}</p>
                            <p v-if="req.terms?.extend_days != null" class="mt-0.5 text-xs text-slate-500">Extension: {{ req.terms.extend_days }} day(s)</p>
                            <p v-if="req.terms?.revision_days != null" class="mt-0.5 text-xs text-slate-500">Fix within: {{ req.terms.revision_days }} day(s)</p>
                            <p v-if="req.terms?.target_completion_date" class="mt-0.5 text-xs text-slate-500">Completion: {{ req.terms.target_completion_date }}</p>
                            <p v-if="req.terms?.client_share_percent != null" class="mt-0.5 text-xs text-slate-500">Client share: {{ req.terms.client_share_percent }}%</p>
                        </li>
                    </ul>
                </section>

                <section v-if="detail.permissions?.can_view_all_assessments && detail.assessments?.length > 1" class="rounded-3xl border p-4" :class="shell.card">
                    <p class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">All assessments ({{ detail.assessments.length }})</p>
                    <div class="mt-3 space-y-3">
                        <div v-for="assessment in detail.assessments" :key="assessment.id" class="rounded-2xl border p-3" :class="shell.card">
                            <p class="text-xs font-black">{{ assessment.staff?.name || 'Staff' }} · {{ assessment.status }}</p>
                            <p class="mt-1 text-xs font-semibold">{{ assessment.recommendation_label || 'No recommendation' }}</p>
                        </div>
                    </div>
                </section>

                <section v-if="detail.permissions?.can_decide || detail.permissions?.can_message_parties" class="rounded-3xl border p-4" :class="shell.card">
                    <p class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">Investigations, mediation &amp; reporting</p>

                    <div class="mt-3 flex flex-wrap gap-2 text-xs font-bold">
                        <span v-if="detail.dispute.chargeback_risk_flagged_at" class="rounded-full bg-rose-100 px-3 py-1 text-rose-900">Chargeback risk flagged</span>
                        <span v-if="detail.dispute.pattern_investigation_at" class="rounded-full bg-amber-100 px-3 py-1 text-amber-900">Pattern investigation open</span>
                        <span v-if="detail.dispute.sealed_at" class="rounded-full bg-slate-200 px-3 py-1 text-slate-900">Sealed &amp; archived</span>
                        <span v-if="detail.dispute.has_report" class="rounded-full bg-sky-100 px-3 py-1 text-sky-900">Report generated</span>
                    </div>

                    <div v-if="detail.mediation_sessions?.length" class="mt-3 space-y-2">
                        <p class="text-[10px] font-black uppercase" :class="shell.label">Mediation sessions</p>
                        <div v-for="session in detail.mediation_sessions" :key="session.id" class="rounded-2xl border p-3 text-xs font-semibold" :class="shell.card">
                            <p class="font-black">{{ session.status }} · {{ formatWhen(session.scheduled_at) }}</p>
                            <p v-if="session.meeting_url" class="mt-1 text-primary-700 underline break-all">{{ session.meeting_url }}</p>
                            <p v-if="session.instructions" class="mt-1 text-slate-600">{{ session.instructions }}</p>
                        </div>
                    </div>

                    <div v-if="detail.precedents?.length" class="mt-3 space-y-2">
                        <p class="text-[10px] font-black uppercase" :class="shell.label">Precedents</p>
                        <div v-for="precedent in detail.precedents" :key="precedent.id" class="rounded-2xl border p-3 text-xs" :class="shell.card">
                            <p class="font-black">{{ precedent.title }}</p>
                            <p class="mt-1 font-semibold text-slate-600">{{ precedent.summary }}</p>
                        </div>
                    </div>

                    <div v-if="detail.permissions?.can_decide" class="mt-4 grid gap-3 sm:grid-cols-2">
                        <div class="sm:col-span-2 rounded-2xl border p-3" :class="shell.card">
                            <p class="text-[10px] font-black uppercase" :class="shell.label">Schedule mediation</p>
                            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                <label class="block text-xs font-bold">
                                    Date &amp; time
                                    <input v-model="mediationForm.scheduled_at" type="datetime-local" class="mt-1 w-full rounded-xl border px-3 py-2 text-sm font-semibold" :class="shell.input" />
                                </label>
                                <label class="block text-xs font-bold">
                                    Meeting URL
                                    <input v-model="mediationForm.meeting_url" type="url" class="mt-1 w-full rounded-xl border px-3 py-2 text-sm font-semibold" :class="shell.input" placeholder="https://…" />
                                </label>
                                <label class="block text-xs font-bold sm:col-span-2">
                                    Instructions
                                    <textarea v-model="mediationForm.instructions" rows="2" class="mt-1 w-full rounded-xl border px-3 py-2 text-sm font-semibold" :class="shell.input" />
                                </label>
                                <button type="button" class="rounded-xl px-3 py-2 text-xs font-black uppercase" :class="shell.btnPrimary" :disabled="busy.mediation" @click="scheduleMediation">Schedule call</button>
                            </div>
                        </div>

                        <button type="button" class="rounded-xl border px-3 py-2 text-left text-xs font-bold" :class="actionButtonClass('chargeback')" :disabled="busy.special" @click="openAction('chargeback')">Flag chargeback risk</button>
                        <button type="button" class="rounded-xl border px-3 py-2 text-left text-xs font-bold" :class="actionButtonClass('pattern')" :disabled="busy.special" @click="openAction('pattern')">Open pattern investigation</button>

                        <div class="sm:col-span-2 rounded-2xl border p-3" :class="shell.card">
                            <p class="text-[10px] font-black uppercase" :class="shell.label">Record precedent</p>
                            <div class="mt-2 grid gap-2">
                                <input v-model="precedentForm.title" type="text" class="rounded-xl border px-3 py-2 text-sm font-semibold" :class="shell.input" placeholder="Precedent title" />
                                <textarea v-model="precedentForm.summary" rows="2" class="rounded-xl border px-3 py-2 text-sm font-semibold" :class="shell.input" placeholder="Summary for future cases" />
                                <button type="button" class="rounded-xl px-3 py-2 text-xs font-black uppercase" :class="shell.btnGhost" :disabled="busy.special" @click="savePrecedent">Save precedent</button>
                            </div>
                        </div>

                        <button type="button" class="rounded-xl border px-3 py-2 text-left text-xs font-bold" :class="shell.btnGhost" :disabled="busy.report" @click="generateReport">Generate PDF report</button>
                        <a
                            v-if="detail.report_download_url"
                            :href="detail.report_download_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="rounded-xl border px-3 py-2 text-center text-xs font-bold underline"
                            :class="shell.btnGhost"
                        >Download report</a>
                        <button
                            v-if="!detail.dispute.sealed_at"
                            type="button"
                            class="rounded-xl border px-3 py-2 text-left text-xs font-bold sm:col-span-2"
                            :class="actionButtonClass('seal')"
                            :disabled="busy.report"
                            @click="openAction('seal')"
                        >Seal &amp; archive dispute</button>
                    </div>
                </section>

                <section v-if="detail.permissions?.can_decide || detail.permissions?.can_message_parties" class="rounded-3xl border p-4" :class="shell.card">
                    <p class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">Super Admin actions</p>
                    <p class="mt-1 text-xs font-semibold text-slate-500">Choose an action below — a form opens in this panel so you can review, submit, and see progress without browser pop-ups.</p>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <button
                            v-for="act in visibleSuperActions"
                            :key="act.key"
                            type="button"
                            class="rounded-xl border px-3 py-2 text-left text-xs font-bold transition"
                            :class="actionButtonClass(act.key)"
                            :disabled="isActionDisabled(act.key)"
                            @click="openAction(act.key)"
                        >
                            {{ act.label }}
                        </button>
                        <button
                            v-if="detail.dispute.held_at"
                            type="button"
                            class="rounded-xl border px-3 py-2 text-left text-xs font-bold"
                            :class="shell.btnGhost"
                            :disabled="busy.superAction"
                            @click="releaseHold"
                        >
                            Release hold
                        </button>
                    </div>

                    <Transition
                        enter-active-class="transition duration-200 ease-out"
                        enter-from-class="translate-y-1 opacity-0"
                        enter-to-class="translate-y-0 opacity-100"
                        leave-active-class="transition duration-150 ease-in"
                        leave-from-class="opacity-100"
                        leave-to-class="opacity-0"
                    >
                        <div
                            v-if="activeAction"
                            ref="actionPanelRef"
                            class="mt-4 rounded-2xl border border-primary-200 bg-primary-50/40 p-4 dark:border-primary-900/40"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-black text-primary-950">{{ activeActionMeta.title }}</p>
                                    <p class="mt-1 text-xs font-semibold text-slate-600">{{ activeActionMeta.hint }}</p>
                                </div>
                                <button type="button" class="shrink-0 text-xs font-bold text-slate-500 hover:text-slate-800" @click="closeAction">Close</button>
                            </div>

                            <div class="mt-4 space-y-3">
                                <template v-if="activeAction === 'note'">
                                    <OperationsFormField v-model="actionForms.note.body" label="Private note" hint="Recorded on the audit trail — not visible to parties." multiline :rows="4" placeholder="Document your observation or next step…" />
                                </template>

                                <template v-else-if="activeAction === 'clarification'">
                                    <OperationsFormField v-model="actionForms.clarification.note" label="Clarification request" hint="Sent to the assigned staff investigator." multiline :rows="4" placeholder="What needs clarification in the staff assessment?" />
                                </template>

                                <template v-else-if="activeAction === 'evidence'">
                                    <label class="block text-xs font-bold text-slate-700">
                                        Evidence template
                                        <select v-model="actionForms.evidence.template" class="mt-1 w-full rounded-xl border px-3 py-2.5 text-sm font-semibold" :class="shell.input" @change="applyEvidenceTemplate">
                                            <option value="">Custom message</option>
                                            <option v-for="t in detail.evidence_templates || []" :key="t.key" :value="t.key">{{ t.label }}</option>
                                        </select>
                                    </label>
                                    <label class="block text-xs font-bold text-slate-700">
                                        Audience
                                        <select v-model="actionForms.evidence.audience" class="mt-1 w-full rounded-xl border px-3 py-2.5 text-sm font-semibold" :class="shell.input">
                                            <option value="both">Both parties</option>
                                            <option value="client">Client only</option>
                                            <option value="freelancer">Freelancer only</option>
                                        </select>
                                    </label>
                                    <OperationsFormField v-model="actionForms.evidence.body" label="Request message" multiline :rows="5" placeholder="Describe exactly what evidence you need and why…" />
                                </template>

                                <template v-else-if="activeAction === 'message'">
                                    <label class="block text-xs font-bold text-slate-700">
                                        Party
                                        <select v-model="actionForms.message.party" class="mt-1 w-full rounded-xl border px-3 py-2.5 text-sm font-semibold" :class="shell.input">
                                            <option value="client">Client — {{ detail.parties?.client?.name || 'Client' }}</option>
                                            <option value="freelancer">Freelancer — {{ detail.parties?.freelancer?.name || 'Freelancer' }}</option>
                                        </select>
                                    </label>
                                    <label class="block text-xs font-bold text-slate-700">
                                        Delivery
                                        <select v-model="actionForms.message.channel" class="mt-1 w-full rounded-xl border px-3 py-2.5 text-sm font-semibold" :class="shell.input">
                                            <option value="both">In-app + email</option>
                                            <option value="email">Email only</option>
                                            <option value="in_app">In-app only</option>
                                        </select>
                                    </label>
                                    <OperationsFormField v-model="actionForms.message.subject" label="Subject" placeholder="Dispute update from HustleSafe" />
                                    <OperationsFormField v-model="actionForms.message.body" label="Message" multiline :rows="5" placeholder="Write your message to the party…" />
                                </template>

                                <template v-else-if="activeAction === 'hold'">
                                    <OperationsFormField v-model="actionForms.hold.reason" label="Hold reason" hint="Explain why this dispute is paused." multiline :rows="4" placeholder="e.g. Awaiting external bank response…" />
                                </template>

                                <template v-else-if="activeAction === 'rate'">
                                    <label class="block text-xs font-bold text-slate-700">
                                        Rating (1–5)
                                        <input v-model.number="actionForms.rate.rating" type="number" min="1" max="5" class="mt-1 w-full rounded-xl border px-3 py-2.5 text-sm font-bold" :class="shell.input" />
                                    </label>
                                    <OperationsFormField v-model="actionForms.rate.feedback" label="Feedback for staff" hint="Optional — helps coaching and performance reviews." multiline :rows="3" />
                                </template>

                                <template v-else-if="activeAction === 'review'">
                                    <OperationsFormField v-model="actionForms.review.note" label="Note for staff" hint="Optional — explain why more investigation is needed." multiline :rows="4" placeholder="What should staff revisit before you decide?" />
                                </template>

                                <template v-else-if="activeAction === 'chargeback'">
                                    <OperationsFormField v-model="actionForms.chargeback.note" label="Chargeback risk note" hint="Optional internal context for finance and audit." multiline :rows="3" />
                                </template>

                                <template v-else-if="activeAction === 'pattern'">
                                    <OperationsFormField v-model="actionForms.pattern.note" label="Pattern investigation note" hint="Optional — describe the behaviour pattern you are investigating." multiline :rows="3" />
                                </template>

                                <template v-else-if="activeAction === 'appeal'">
                                    <OperationsFormField v-model="actionForms.appeal.note" label="Appeal review note" hint="Optional context for reopening appeal review." multiline :rows="3" />
                                </template>

                                <template v-else-if="activeAction === 'finalize'">
                                    <div class="rounded-xl border border-amber-200 bg-amber-50/80 px-3 py-3 text-xs font-semibold text-amber-950">
                                        Finalizing closes the appeal window permanently. Parties will no longer be able to appeal this outcome.
                                    </div>
                                </template>

                                <template v-else-if="activeAction === 'seal'">
                                    <div class="rounded-xl border border-slate-300 bg-slate-50 px-3 py-3 text-xs font-semibold text-slate-800">
                                        Sealing marks this dispute record as archived. Ensure the PDF report is generated if you need a formal export.
                                    </div>
                                </template>
                            </div>

                            <p v-if="actionError" class="mt-3 text-xs font-bold text-rose-700">{{ actionError }}</p>

                            <div class="mt-4 flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-xs font-black uppercase text-white"
                                    :class="activeAction === 'finalize' || activeAction === 'seal' ? 'bg-amber-700 hover:bg-amber-800' : 'bg-primary-700 hover:bg-primary-800'"
                                    :disabled="actionBusy"
                                    @click="submitActiveAction"
                                >
                                    <span v-if="actionBusy" class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                                    {{ actionBusy ? 'Processing…' : activeActionMeta.submitLabel }}
                                </button>
                                <button type="button" class="rounded-xl border px-4 py-2.5 text-xs font-black uppercase" :class="shell.btnGhost" :disabled="actionBusy" @click="closeAction">Cancel</button>
                            </div>
                        </div>
                    </Transition>
                </section>

                <section v-if="detail.permissions?.can_decide" ref="decisionSectionRef" class="rounded-3xl border border-primary-200 bg-primary-50/40 p-4 dark:border-primary-900/40">
                    <p class="text-[10px] font-black uppercase tracking-wider text-primary-900">My decision</p>

                    <label class="mt-3 flex items-center gap-2 text-sm font-bold">
                        <input v-model="agreeWithStaff" type="checkbox" class="rounded border-slate-300 text-primary-600" @change="onAgreeToggle" />
                        I agree with staff assessment
                    </label>

                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <label class="block text-xs font-bold text-slate-700">
                            Decision type
                            <select v-model="decisionForm.outcome" class="mt-1 w-full rounded-2xl border px-3 py-2.5 text-sm font-bold" :class="shell.input" @change="onOutcomeChange">
                                <option value="partial_award">Split the payment</option>
                                <option value="award_client_full">Full refund to client</option>
                                <option value="award_freelancer_full">Full payment to freelancer</option>
                                <option value="force_revision">Give another chance to fix</option>
                                <option value="extend_deadline">More time to finish</option>
                                <option value="refund_cancel">Refund client and cancel job</option>
                                <option value="terminate_contract">End contract without refund</option>
                                <option value="mediation">Schedule mediation call</option>
                            </select>
                        </label>
                        <label v-if="isPayoutOutcome" class="block text-xs font-bold text-slate-700">
                            Client share %
                            <input v-model.number="decisionForm.client_share_percent" type="number" min="0" max="100" class="mt-1 w-full rounded-2xl border px-3 py-2.5 text-sm font-bold" :class="shell.input" />
                        </label>
                        <label v-if="decisionForm.outcome === 'extend_deadline'" class="block text-xs font-bold text-slate-700">
                            Extend by (days)
                            <input v-model.number="decisionForm.extend_days" type="number" min="1" max="90" class="mt-1 w-full rounded-2xl border px-3 py-2.5 text-sm font-bold" :class="shell.input" />
                        </label>
                        <label v-if="decisionForm.outcome === 'mediation'" class="block text-xs font-bold text-slate-700 sm:col-span-2">
                            Mediation date &amp; time
                            <input v-model="decisionForm.scheduled_at" type="datetime-local" class="mt-1 w-full rounded-2xl border px-3 py-2.5 text-sm font-bold" :class="shell.input" />
                        </label>
                        <label v-if="decisionForm.outcome === 'mediation'" class="block text-xs font-bold text-slate-700 sm:col-span-2">
                            Meeting URL
                            <input v-model="decisionForm.meeting_url" type="url" class="mt-1 w-full rounded-2xl border px-3 py-2.5 text-sm font-bold" :class="shell.input" placeholder="https://…" />
                        </label>
                    </div>

                    <div v-if="isPayoutOutcome" class="mt-3 rounded-2xl border border-emerald-200 bg-emerald-50/70 p-3 text-xs font-bold text-emerald-950">
                        <p>Outcome execution preview</p>
                        <p class="mt-1">Client payout: {{ formatMinor(payoutPreview.client) }}</p>
                        <p>Freelancer payout: {{ formatMinor(payoutPreview.freelancer) }}</p>
                        <p class="text-emerald-800">Held in escrow: {{ formatMinor(payoutPreview.held) }}</p>
                    </div>

                    <label class="mt-3 block text-xs font-bold text-slate-700">
                        Decision notes
                        <textarea v-model="decisionForm.decision_notes" rows="4" class="mt-1 w-full rounded-2xl border px-3 py-2.5 text-sm font-semibold" :class="shell.input" placeholder="Document why this decision is fair and supported by evidence." />
                    </label>

                    <div class="mt-3 space-y-2">
                        <p class="text-[10px] font-black uppercase text-slate-500">Sanctions</p>
                        <label class="flex items-center gap-2 text-sm font-bold">
                            <input v-model="decisionForm.sanctions.warn_freelancer" type="checkbox" class="rounded border-slate-300 text-primary-600" />
                            Warn freelancer
                        </label>
                        <label class="flex items-center gap-2 text-sm font-bold">
                            <input v-model="decisionForm.sanctions.warn_client" type="checkbox" class="rounded border-slate-300 text-primary-600" />
                            Warn client
                        </label>
                        <label class="block text-xs font-bold text-slate-700">
                            Sanction type
                            <select v-model="decisionForm.sanctions.type" class="mt-1 w-full rounded-2xl border px-3 py-2.5 text-sm font-bold" :class="shell.input">
                                <option value="">None</option>
                                <option v-for="opt in detail.sanction_options || []" :key="opt.value" :value="opt.value === 'none' ? '' : opt.value">{{ opt.label }}</option>
                            </select>
                        </label>
                        <label v-if="needsSanctionTarget" class="block text-xs font-bold text-slate-700">
                            Sanction target
                            <select v-model="decisionForm.sanctions.target_user_id" class="mt-1 w-full rounded-2xl border px-3 py-2.5 text-sm font-bold" :class="shell.input">
                                <option :value="null">Select party…</option>
                                <option v-if="detail.parties?.client?.id" :value="detail.parties.client.id">Client — {{ detail.parties.client.name }}</option>
                                <option v-if="detail.parties?.freelancer?.id" :value="detail.parties.freelancer.id">Freelancer — {{ detail.parties.freelancer.name }}</option>
                            </select>
                        </label>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <button type="button" class="rounded-2xl px-4 py-2.5 text-xs font-black uppercase text-white bg-emerald-700" :disabled="busy.decision" @click="executeDecision">
                            Approve &amp; execute
                        </button>
                        <button type="button" class="rounded-2xl border px-4 py-2.5 text-xs font-black uppercase" :class="shell.btnGhost" :disabled="busy.review" @click="openAction('review')">
                            Request more review
                        </button>
                    </div>
                </section>

                <section v-if="detail.dispute.super_admin_decision_notes" class="rounded-3xl border p-4" :class="shell.card">
                    <p class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">Executed decision</p>
                    <p class="mt-2 text-sm font-black" :class="shell.title">
                        Client {{ detail.dispute.final_client_share_percent ?? '—' }}% / Freelancer {{ detail.dispute.final_client_share_percent != null ? 100 - detail.dispute.final_client_share_percent : '—' }}%
                    </p>
                    <p class="mt-2 whitespace-pre-wrap text-sm font-medium">{{ detail.dispute.super_admin_decision_notes }}</p>
                    <p v-if="detail.dispute.appeal_window_ends_at" class="mt-2 text-xs font-bold text-amber-800">
                        Appeal window ends {{ formatWhen(detail.dispute.appeal_window_ends_at) }}
                    </p>
                </section>

                <section v-if="detail.permissions?.can_reassign" class="rounded-3xl border p-4" :class="shell.card">
                    <p class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">Reassign to staff</p>
                    <p class="mt-1 text-xs font-semibold text-slate-500">Max {{ maxReassignments }} reassignments · Used {{ detail.dispute.reassignment_count ?? 0 }}</p>
                    <div class="mt-3 grid gap-3">
                        <select v-model="reassignForm.staff_id" class="rounded-2xl border px-3 py-2.5 text-sm font-bold" :class="shell.input">
                            <option :value="null">Select staff…</option>
                            <option v-for="staff in detail.staff_options || []" :key="staff.id" :value="staff.id">
                                {{ staff.name }} ({{ staff.active_load }} active)
                            </option>
                        </select>
                        <textarea v-model="reassignForm.reason" rows="3" class="rounded-2xl border px-3 py-2.5 text-sm font-semibold" :class="shell.input" placeholder="Reason for reassignment (required)" />
                        <button type="button" class="rounded-2xl px-4 py-2.5 text-xs font-black uppercase" :class="shell.btnPrimary" :disabled="busy.reassign" @click="reassign">Reassign dispute</button>
                    </div>
                </section>

                <section v-if="openPartyAppeal" class="rounded-3xl border border-amber-300 bg-amber-50/50 p-4 dark:border-amber-900/40">
                    <p class="text-[10px] font-black uppercase tracking-wider text-amber-900">Party appeal — final review</p>
                    <p class="mt-2 text-sm font-bold text-amber-950">{{ openPartyAppeal.filed_by }} ({{ openPartyAppeal.party_role }})</p>
                    <p class="mt-2 whitespace-pre-wrap text-sm text-slate-800">{{ openPartyAppeal.unfair_reason }}</p>
                    <p v-if="openPartyAppeal.counter_response" class="mt-3 whitespace-pre-wrap rounded-xl border border-slate-200 bg-white/80 p-3 text-sm text-slate-700">
                        <span class="font-bold">Other party response:</span> {{ openPartyAppeal.counter_response }}
                    </p>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <label class="flex items-center gap-2 text-sm font-bold">
                            <input v-model="appealResolveForm.upheld_original" type="radio" :value="true" />
                            Uphold original decision
                        </label>
                        <label class="flex items-center gap-2 text-sm font-bold">
                            <input v-model="appealResolveForm.upheld_original" type="radio" :value="false" />
                            Grant modified outcome
                        </label>
                    </div>
                    <label v-if="!appealResolveForm.upheld_original" class="mt-3 block text-xs font-bold">
                        Client share (%)
                        <input v-model.number="appealResolveForm.client_share_percent" type="number" min="0" max="100" class="mt-1 w-full rounded-2xl border px-3 py-2.5 text-sm font-bold" :class="shell.input" />
                    </label>
                    <textarea v-model="appealResolveForm.review_outcome_notes" rows="4" class="mt-3 w-full rounded-2xl border px-3 py-2.5 text-sm font-semibold" :class="shell.input" placeholder="Explain your final binding decision…" />
                    <button type="button" class="mt-3 rounded-2xl bg-amber-800 px-4 py-2.5 text-xs font-black uppercase text-white" :disabled="busy.resolveAppeal" @click="resolvePartyAppeal">
                        Confirm final decision
                    </button>
                </section>

                <section v-if="detail.negotiation_history?.length" class="rounded-3xl border p-4" :class="shell.card">
                    <p class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">Peer negotiation history</p>
                    <ul class="mt-2 space-y-2 text-xs font-semibold">
                        <li v-for="offer in detail.negotiation_history" :key="offer.id" class="rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-2">
                            <p class="font-black">{{ offer.summary }} · {{ offer.status }}</p>
                            <p class="text-slate-600">{{ offer.offered_by }} · Attempt {{ offer.attempt_number }}</p>
                        </li>
                    </ul>
                </section>

                <div class="flex flex-wrap gap-2">
                    <button
                        v-if="detail.permissions?.can_create_appeal"
                        type="button"
                        class="rounded-2xl border px-4 py-2.5 text-xs font-black uppercase"
                        :class="actionButtonClass('appeal')"
                        :disabled="busy.appeal"
                        @click="openAction('appeal')"
                    >
                        Open appeal review
                    </button>
                </div>

                <section class="rounded-3xl border p-4" :class="shell.card">
                    <p class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">Complete audit trail</p>
                    <ul class="mt-2 max-h-72 space-y-3 overflow-y-auto text-xs font-semibold">
                        <li v-for="event in sortedEvents" :key="event.id" class="rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-2 dark:border-white/10 dark:bg-white/5">
                            <p class="font-black" :class="shell.title">{{ formatWhen(event.created_at) }} · {{ event.actor || 'System' }}</p>
                            <p class="mt-0.5 font-bold text-primary-800">{{ event.action_label || formatEventAction(event.action) }}</p>
                            <p v-if="event.properties?.body" class="mt-1 whitespace-pre-wrap text-slate-700 dark:text-slate-200">{{ event.properties.body }}</p>
                            <p v-if="event.properties?.note" class="mt-1 whitespace-pre-wrap text-slate-700 dark:text-slate-200">{{ event.properties.note }}</p>
                            <p v-if="event.properties?.in_response_to" class="mt-1 text-[10px] font-bold uppercase text-slate-500">In response to</p>
                            <p v-if="event.properties?.in_response_to" class="whitespace-pre-wrap text-slate-600">{{ event.properties.in_response_to }}</p>
                        </li>
                    </ul>
                </section>
            </div>
        </AdminSlideOver>

        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-y-2 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="toastVisible"
                class="fixed bottom-5 right-5 z-[90] max-w-sm rounded-2xl px-4 py-3 text-sm font-bold shadow-xl"
                :class="toastType === 'error' ? 'bg-rose-600 text-white' : 'bg-slate-900 text-white'"
            >
                {{ toastText }}
            </div>
        </Transition>
    </AdminShell>
</template>

<script setup>
import AdminPanel from '@/Components/Admin/AdminPanel.vue';
import AdminQuickActions from '@/Components/Admin/AdminQuickActions.vue';
import AdminSlideOver from '@/Components/Admin/AdminSlideOver.vue';
import OperationsFormField from '@/Pages/Operations/Components/OperationsFormField.vue';
import { useClientQueue } from '@/composables/useClientQueue';
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';
import AdminShell from '@/Layouts/AdminShell.vue';
import { computed, nextTick, onMounted, reactive, ref } from 'vue';
import { FLASH_TOAST_MS } from '@/composables/useFlashToast';

const props = defineProps({
    summary: {
        type: Object,
        default: () => ({
            total: 0,
            open: 0,
            under_review: 0,
            ready_for_decision: 0,
            resolved: 0,
            filters: [],
            sorts: [],
        }),
    },
});

const { shell } = useInjectedAdminTheme();

const summaryState = ref({ ...props.summary });
const rawItems = ref([]);
const loading = ref(false);
const activeFilter = ref('all');
const activeSort = ref('newest');
const searchQuery = ref('');
const slideOpen = ref(false);
const detailLoading = ref(false);
const detail = ref(null);
const selectedRow = ref(null);
const agreeWithStaff = ref(true);
const maxReassignments = 2;
const toastVisible = ref(false);
const toastText = ref('');
const toastType = ref('success');
let toastTimer = null;

const busy = reactive({
    decision: false,
    resolveAppeal: false,
    review: false,
    reassign: false,
    finalize: false,
    acknowledge: false,
    appeal: false,
    approve: false,
    superAction: false,
    mediation: false,
    special: false,
    report: false,
});

const decisionForm = reactive({
    outcome: 'partial_award',
    client_share_percent: 50,
    decision_notes: '',
    favoured_user_id: null,
    extend_days: 7,
    scheduled_at: '',
    meeting_url: '',
    sanctions: {
        warn_freelancer: false,
        warn_client: false,
        type: '',
        target_user_id: null,
        suspend_user_id: null,
    },
});

const mediationForm = reactive({
    scheduled_at: '',
    meeting_url: '',
    instructions: '',
});

const precedentForm = reactive({
    title: '',
    summary: '',
});

const activeAction = ref(null);
const actionError = ref('');
const actionPanelRef = ref(null);
const decisionSectionRef = ref(null);

const actionForms = reactive({
    note: { body: '' },
    clarification: { note: '' },
    evidence: { body: '', audience: 'both', template: '' },
    message: { party: 'client', subject: 'Dispute update from HustleSafe', body: '', channel: 'both' },
    hold: { reason: '' },
    rate: { rating: 4, feedback: '' },
    review: { note: '' },
    chargeback: { note: '' },
    pattern: { note: '' },
    appeal: { note: '' },
});

const actionCatalog = {
    note: { title: 'Add private note', hint: 'Super Admin only — saved to the immutable audit trail.', submitLabel: 'Save note', busyKey: 'superAction' },
    clarification: { title: 'Request staff clarification', hint: 'Returns the case to staff with your questions.', submitLabel: 'Send to staff', busyKey: 'superAction' },
    evidence: { title: 'Request evidence from parties', hint: 'Parties are notified in-app, by email, and via SMS when configured.', submitLabel: 'Send evidence request', busyKey: 'superAction' },
    message: { title: 'Message party directly', hint: 'Delivered through your selected channel.', submitLabel: 'Send message', busyKey: 'superAction' },
    hold: { title: 'Hold dispute', hint: 'Pauses active processing until you release the hold.', submitLabel: 'Place on hold', busyKey: 'superAction' },
    rate: { title: 'Rate staff assessment', hint: 'Scores the latest submitted staff investigation.', submitLabel: 'Submit rating', busyKey: 'superAction' },
    review: { title: 'Request more review', hint: 'Sends the dispute back to staff for further investigation.', submitLabel: 'Return to staff', busyKey: 'review' },
    chargeback: { title: 'Flag chargeback risk', hint: 'Marks this case for finance monitoring.', submitLabel: 'Flag chargeback risk', busyKey: 'special' },
    pattern: { title: 'Open pattern investigation', hint: 'Links related disputes involving the same parties.', submitLabel: 'Open investigation', busyKey: 'special' },
    appeal: { title: 'Open appeal review', hint: 'Reopens appeal handling on this dispute.', submitLabel: 'Open appeal review', busyKey: 'appeal' },
    finalize: { title: 'Finalize dispute', hint: 'Confirm below — this action closes the appeal window.', submitLabel: 'Confirm finalize', busyKey: 'finalize' },
    seal: { title: 'Seal & archive dispute', hint: 'Confirm below — marks the record as permanently archived.', submitLabel: 'Confirm seal & archive', busyKey: 'report' },
};

const reassignForm = reactive({
    staff_id: null,
    reason: '',
});

const queue = useClientQueue(() => rawItems.value, {
    searchFields: ['reference', 'quest', 'contract_reference', 'quest_reference', 'assigned_staff', 'category_label', 'reason_label'],
});

const exportUrl = computed(() => route('admin.disputes.export'));
const slideTitle = computed(() => detail.value?.dispute?.quest || selectedRow.value?.quest || 'Dispute');

const sortedEvents = computed(() => [...(detail.value?.events ?? [])].reverse());

const openPartyAppeal = computed(() => (detail.value?.appeals ?? []).find((a) => ['filed', 'counter_pending', 'under_review'].includes(a.status)) ?? null);

const appealResolveForm = reactive({
    upheld_original: true,
    client_share_percent: 50,
    review_outcome_notes: '',
});

const payoutPreview = computed(() => {
    const held = Number(detail.value?.dispute?.disputed_amount_minor || 0);
    let clientPercent = Number(decisionForm.client_share_percent || 0);

    if (decisionForm.outcome === 'award_client_full') {
        clientPercent = 100;
    } else if (decisionForm.outcome === 'award_freelancer_full') {
        clientPercent = 0;
    }

    clientPercent = Math.max(0, Math.min(100, clientPercent));
    const client = Math.round(held * (clientPercent / 100));
    const freelancer = Math.max(0, held - client);

    return { held, client, freelancer, clientPercent };
});

const isPayoutOutcome = computed(() => ['partial_award', 'award_client_full', 'award_freelancer_full'].includes(decisionForm.outcome));

const needsSanctionTarget = computed(() => ['suspend_7', 'suspend_30', 'permanent_ban', 'tier_demotion', 'category_ban'].includes(decisionForm.sanctions.type));

const activeActionMeta = computed(() => actionCatalog[activeAction.value] ?? { title: '', hint: '', submitLabel: 'Submit', busyKey: 'superAction' });

const actionBusy = computed(() => busy[activeActionMeta.value.busyKey] ?? false);

const visibleSuperActions = computed(() => {
    const actions = [
        { key: 'note', label: 'Add private note' },
        { key: 'clarification', label: 'Request staff clarification' },
        { key: 'evidence', label: 'Request evidence' },
        { key: 'message', label: 'Message party' },
        { key: 'rate', label: 'Rate staff assessment' },
    ];

    if (!detail.value?.dispute?.held_at) {
        actions.splice(4, 0, { key: 'hold', label: 'Hold dispute' });
    }

    return actions;
});

function actionButtonClass(key) {
    return activeAction.value === key ? 'border-primary-600 bg-primary-700 text-white' : shell.btnGhost;
}

function isActionDisabled(key) {
    const meta = actionCatalog[key];
    if (!meta) {
        return false;
    }

    return busy[meta.busyKey] ?? false;
}

function resetActionForms() {
    actionForms.note.body = '';
    actionForms.clarification.note = '';
    actionForms.evidence.body = '';
    actionForms.evidence.audience = 'both';
    actionForms.evidence.template = '';
    actionForms.message.party = 'client';
    actionForms.message.subject = 'Dispute update from HustleSafe';
    actionForms.message.body = '';
    actionForms.message.channel = 'both';
    actionForms.hold.reason = '';
    actionForms.rate.rating = detail.value?.latest_submitted_assessment?.super_admin_rating || 4;
    actionForms.rate.feedback = '';
    actionForms.review.note = '';
    actionForms.chargeback.note = '';
    actionForms.pattern.note = '';
    actionForms.appeal.note = '';
}

function openAction(key) {
    actionError.value = '';
    if (activeAction.value === key) {
        activeAction.value = null;
        return;
    }
    activeAction.value = key;
    if (key === 'rate') {
        actionForms.rate.rating = detail.value?.latest_submitted_assessment?.super_admin_rating || 4;
    }
    nextTick(() => {
        actionPanelRef.value?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
}

function closeAction() {
    activeAction.value = null;
    actionError.value = '';
}

function scrollToDecision() {
    decisionSectionRef.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function applyEvidenceTemplate() {
    const template = (detail.value?.evidence_templates ?? []).find((t) => t.key === actionForms.evidence.template);
    if (template) {
        actionForms.evidence.body = template.body;
    }
}

function outcomeActionForForm() {
    const map = {
        partial_award: 'standard_payout',
        award_client_full: 'standard_payout',
        award_freelancer_full: 'standard_payout',
        force_revision: 'force_revision',
        extend_deadline: 'extend_deadline',
        terminate_contract: 'terminate_contract',
        refund_cancel: 'refund_cancel',
        mediation: 'mediation',
    };
    return map[decisionForm.outcome] || 'standard_payout';
}

onMounted(() => reload());

function setFilter(key) {
    activeFilter.value = key;
    reload();
}

async function reload() {
    loading.value = true;
    try {
        const { data } = await window.axios.get(route('admin.api.disputes.listing'), {
            params: { filter: activeFilter.value, sort: activeSort.value, q: searchQuery.value },
        });
        rawItems.value = data.items ?? [];
        if (data.summary) {
            summaryState.value = data.summary;
        }
    } catch (error) {
        toast(error?.response?.data?.message || 'Failed to load disputes.', 'error');
    } finally {
        loading.value = false;
    }
}

function disputeKey(row) {
    return row.uuid || row.id;
}

function hydrateDecisionFromAssessment() {
    const assessment = detail.value?.latest_submitted_assessment;
    if (!assessment) {
        return;
    }

    if (assessment.recommendation === 'award_client_full') {
        decisionForm.outcome = 'award_client_full';
        decisionForm.client_share_percent = 100;
    } else if (assessment.recommendation === 'award_freelancer_full') {
        decisionForm.outcome = 'award_freelancer_full';
        decisionForm.client_share_percent = 0;
    } else if (assessment.recommendation === 'partial_award') {
        decisionForm.outcome = 'partial_award';
        decisionForm.client_share_percent = assessment.recommended_client_share_percent ?? 50;
    } else if (assessment.recommendation === 'mediation_needed') {
        decisionForm.outcome = 'mediation';
    }

    if (assessment.reasoning && !decisionForm.decision_notes) {
        decisionForm.decision_notes = `Approving staff recommendation. ${assessment.reasoning}`;
    }
}

async function openDetail(row) {
    selectedRow.value = row;
    slideOpen.value = true;
    detailLoading.value = true;
    agreeWithStaff.value = true;
    decisionForm.decision_notes = '';
    decisionForm.sanctions.warn_freelancer = false;
    decisionForm.sanctions.warn_client = false;
    decisionForm.sanctions.type = '';
    decisionForm.sanctions.target_user_id = null;
    decisionForm.sanctions.suspend_user_id = null;
    mediationForm.scheduled_at = '';
    mediationForm.meeting_url = '';
    mediationForm.instructions = '';
    precedentForm.title = '';
    precedentForm.summary = '';
    activeAction.value = null;
    actionError.value = '';
    resetActionForms();
    reassignForm.staff_id = null;
    reassignForm.reason = '';

    try {
        const { data } = await window.axios.get(route('admin.api.disputes.detail', disputeKey(row)));
        detail.value = data;
        hydrateDecisionFromAssessment();
    } catch (error) {
        toast(error?.response?.data?.message || 'Failed to load dispute.', 'error');
        slideOpen.value = false;
    } finally {
        detailLoading.value = false;
    }
}

function onAgreeToggle() {
    if (agreeWithStaff.value) {
        hydrateDecisionFromAssessment();
    }
}

function onOutcomeChange() {
    if (decisionForm.outcome === 'award_client_full') {
        decisionForm.client_share_percent = 100;
    } else if (decisionForm.outcome === 'award_freelancer_full') {
        decisionForm.client_share_percent = 0;
    }
}

async function runBusy(key, request, successMessage, after) {
    busy[key] = true;
    try {
        const response = await request();
        toast(response?.data?.message || successMessage);
        if (after) {
            await after(response);
        }
    } catch (error) {
        const errors = error?.response?.data?.errors;
        const firstError = errors ? Object.values(errors).flat()[0] : null;
        toast(firstError || error?.response?.data?.message || 'Action failed.', 'error');
    } finally {
        busy[key] = false;
    }
}

async function loadStaffApproval() {
    await runBusy('approve', () => window.axios.post(route('admin.api.disputes.approve_assessment', disputeKey(selectedRow.value))), 'Staff recommendation loaded.', (res) => {
        const d = res?.data?.decision;
        if (!d) return;
        agreeWithStaff.value = true;
        if (d.outcome === 'award_client_full') decisionForm.outcome = 'award_client_full';
        else if (d.outcome === 'award_freelancer_full') decisionForm.outcome = 'award_freelancer_full';
        else if (d.outcome === 'mediation_needed') decisionForm.outcome = 'mediation';
        else decisionForm.outcome = 'partial_award';
        decisionForm.client_share_percent = d.client_share_percent ?? 50;
        decisionForm.decision_notes = d.decision_notes ?? '';
        if (d.sanctions?.warn_freelancer) decisionForm.sanctions.warn_freelancer = true;
        if (d.sanctions?.warn_client) decisionForm.sanctions.warn_client = true;
        if (d.sanctions?.type) decisionForm.sanctions.type = d.sanctions.type;
        if (d.sanctions?.target_user_id) decisionForm.sanctions.target_user_id = d.sanctions.target_user_id;
    });
}

async function scheduleMediation() {
    if (!mediationForm.scheduled_at) {
        toast('Set a mediation date and time.', 'error');
        return;
    }
    await runBusy('mediation', () => window.axios.post(route('admin.api.disputes.mediation', disputeKey(selectedRow.value)), { ...mediationForm }), 'Mediation scheduled.', () => openDetail(selectedRow.value));
}

async function flagChargeback() {
    await runBusy('special', () => window.axios.post(route('admin.api.disputes.chargeback_flag', disputeKey(selectedRow.value)), { note: actionForms.chargeback.note || null }), 'Chargeback risk flagged.', () => {
        closeAction();
        return openDetail(selectedRow.value);
    });
}

async function openPatternInvestigation() {
    await runBusy('special', () => window.axios.post(route('admin.api.disputes.pattern_investigation', disputeKey(selectedRow.value)), { note: actionForms.pattern.note || null }), 'Pattern investigation opened.', () => {
        closeAction();
        return openDetail(selectedRow.value);
    });
}

async function savePrecedent() {
    if (!precedentForm.title?.trim() || !precedentForm.summary?.trim()) {
        toast('Precedent title and summary are required.', 'error');
        return;
    }
    await runBusy('special', () => window.axios.post(route('admin.api.disputes.precedent', disputeKey(selectedRow.value)), { ...precedentForm }), 'Precedent saved.', () => openDetail(selectedRow.value));
}

async function generateReport() {
    await runBusy('report', () => window.axios.post(route('admin.api.disputes.generate_report', disputeKey(selectedRow.value))), 'PDF report generated.', () => openDetail(selectedRow.value));
}

async function sealArchive() {
    await runBusy('report', () => window.axios.post(route('admin.api.disputes.seal', disputeKey(selectedRow.value))), 'Dispute sealed.', () => {
        closeAction();
        return openDetail(selectedRow.value);
    });
}

async function submitSuperNote() {
    if (!actionForms.note.body?.trim()) {
        actionError.value = 'Enter a note before saving.';
        return;
    }
    await runBusy('superAction', () => window.axios.post(route('admin.api.disputes.super_admin_note', disputeKey(selectedRow.value)), { body: actionForms.note.body }), 'Note saved.', () => {
        closeAction();
        return openDetail(selectedRow.value);
    });
}

async function submitClarification() {
    if (!actionForms.clarification.note?.trim()) {
        actionError.value = 'Describe what clarification you need from staff.';
        return;
    }
    await runBusy('superAction', () => window.axios.post(route('admin.api.disputes.request_clarification', disputeKey(selectedRow.value)), { note: actionForms.clarification.note }), 'Clarification requested.', () => {
        closeAction();
        return openDetail(selectedRow.value);
    });
}

async function submitEvidenceRequest() {
    if (!actionForms.evidence.body?.trim()) {
        actionError.value = 'Write the evidence request message for parties.';
        return;
    }
    await runBusy('superAction', () => window.axios.post(route('admin.api.disputes.request_evidence', disputeKey(selectedRow.value)), {
        body: actionForms.evidence.body,
        audience: actionForms.evidence.audience,
    }), 'Evidence request sent.', () => {
        closeAction();
        return openDetail(selectedRow.value);
    });
}

async function submitDirectMessage() {
    if (!actionForms.message.subject?.trim() || !actionForms.message.body?.trim()) {
        actionError.value = 'Subject and message are required.';
        return;
    }
    await runBusy('superAction', () => window.axios.post(route('admin.api.disputes.message_party', disputeKey(selectedRow.value)), { ...actionForms.message }), 'Message sent.', () => {
        closeAction();
        return openDetail(selectedRow.value);
    });
}

async function submitHold() {
    if (!actionForms.hold.reason?.trim()) {
        actionError.value = 'Provide a reason for holding this dispute.';
        return;
    }
    await runBusy('superAction', () => window.axios.post(route('admin.api.disputes.hold', disputeKey(selectedRow.value)), { reason: actionForms.hold.reason }), 'Dispute on hold.', () => {
        closeAction();
        return openDetail(selectedRow.value);
    });
}

async function submitRateStaff() {
    const assessment = detail.value?.latest_submitted_assessment;
    if (!assessment?.id) {
        actionError.value = 'No submitted assessment to rate.';
        return;
    }
    const rating = Number(actionForms.rate.rating);
    if (!rating || rating < 1 || rating > 5) {
        actionError.value = 'Rating must be between 1 and 5.';
        return;
    }
    await runBusy('superAction', () => window.axios.post(route('admin.api.disputes.rate_assessment', disputeKey(selectedRow.value)), {
        assessment_id: assessment.id,
        rating,
        feedback: actionForms.rate.feedback || null,
    }), 'Assessment rated.', () => {
        closeAction();
        return openDetail(selectedRow.value);
    });
}

async function requestReview() {
    await runBusy(
        'review',
        () => window.axios.post(route('admin.api.disputes.request_review', disputeKey(selectedRow.value)), { note: actionForms.review.note || null }),
        'Sent back for staff review.',
        async () => {
            closeAction();
            await reload();
            await openDetail(selectedRow.value);
        },
    );
}

async function finalizeDispute() {
    await runBusy(
        'finalize',
        () => window.axios.post(route('admin.api.disputes.finalize', disputeKey(selectedRow.value))),
        'Dispute finalized.',
        async () => {
            closeAction();
            await reload();
            await openDetail(selectedRow.value);
        },
    );
}

async function acknowledgePartyResolution() {
    await runBusy(
        'acknowledge',
        () => window.axios.post(route('admin.api.disputes.acknowledge_party_resolution', disputeKey(selectedRow.value))),
        'Party resolution acknowledged.',
        async () => {
            await reload();
            await openDetail(selectedRow.value);
        },
    );
}

async function openAppealReview() {
    await runBusy(
        'appeal',
        () => window.axios.post(route('admin.api.disputes.appeal_review', disputeKey(selectedRow.value)), { note: actionForms.appeal.note || null }),
        'Appeal review opened.',
        async () => {
            closeAction();
            await reload();
            await openDetail(selectedRow.value);
        },
    );
}

async function submitActiveAction() {
    actionError.value = '';

    const handlers = {
        note: submitSuperNote,
        clarification: submitClarification,
        evidence: submitEvidenceRequest,
        message: submitDirectMessage,
        hold: submitHold,
        rate: submitRateStaff,
        review: requestReview,
        chargeback: flagChargeback,
        pattern: openPatternInvestigation,
        appeal: openAppealReview,
        finalize: finalizeDispute,
        seal: sealArchive,
    };

    const handler = handlers[activeAction.value];
    if (handler) {
        await handler();
    }
}

async function releaseHold() {
    await runBusy('superAction', () => window.axios.post(route('admin.api.disputes.release_hold', disputeKey(selectedRow.value))), 'Hold released.', () => openDetail(selectedRow.value));
}

async function resolvePartyAppeal() {
    await runBusy('resolveAppeal', () => window.axios.post(route('admin.api.disputes.resolve_appeal', disputeKey(selectedRow.value)), {
        upheld_original: appealResolveForm.upheld_original,
        client_share_percent: appealResolveForm.upheld_original ? undefined : appealResolveForm.client_share_percent,
        review_outcome_notes: appealResolveForm.review_outcome_notes,
    }), 'Appeal resolved — decision is final.', () => openDetail(selectedRow.value));
}

async function executeDecision() {
    const outcomeAction = outcomeActionForForm();
    const payload = {
        outcome_action: outcomeAction,
        outcome: isPayoutOutcome.value ? decisionForm.outcome : decisionForm.outcome,
        client_share_percent: decisionForm.outcome === 'refund_cancel'
            ? 100
            : (isPayoutOutcome.value ? payoutPreview.value.clientPercent : undefined),
        decision_notes: decisionForm.decision_notes,
        instructions: decisionForm.decision_notes,
        days: decisionForm.outcome === 'extend_deadline' ? decisionForm.extend_days : undefined,
        scheduled_at: decisionForm.outcome === 'mediation' ? decisionForm.scheduled_at : undefined,
        meeting_url: decisionForm.outcome === 'mediation' ? decisionForm.meeting_url : undefined,
        favoured_user_id: decisionForm.favoured_user_id,
        sanctions: { ...decisionForm.sanctions },
    };

    if (payload.sanctions.type === 'warn_freelancer') {
        payload.sanctions.warn_freelancer = true;
        payload.sanctions.type = '';
    } else if (payload.sanctions.type === 'warn_client') {
        payload.sanctions.warn_client = true;
        payload.sanctions.type = '';
    }

    await runBusy(
        'decision',
        () => window.axios.post(route('admin.api.disputes.decision', disputeKey(selectedRow.value)), payload),
        'Decision executed.',
        async () => {
            await reload();
            await openDetail(selectedRow.value);
        },
    );
}

async function reassign() {
    if (!reassignForm.staff_id || !reassignForm.reason?.trim()) {
        toast('Select staff and provide a reassignment reason.', 'error');
        return;
    }

    await runBusy(
        'reassign',
        () => window.axios.post(route('admin.api.disputes.reassign', disputeKey(selectedRow.value)), { ...reassignForm }),
        'Dispute reassigned.',
        async () => {
            await reload();
            await openDetail(selectedRow.value);
        },
    );
}

function formatResolutionOption(value) {
    const map = {
        award_client_full: 'Full refund to client',
        award_freelancer_full: 'Full payment to freelancer',
        partial_award: 'Split the payment',
        split_fund: 'Split the payment',
        force_revision: 'Give another chance to fix',
        extend_deadline: 'More time to finish',
        mediation_needed: 'Talk with a mediator',
        mediation: 'Talk with a mediator',
        refund_cancel: 'Refund and cancel job',
        custom_settlement: 'Custom agreement',
    };
    return map[value] || String(value).replaceAll('_', ' ');
}

function formatMinor(minor) {
    if (!minor && minor !== 0) {
        return '—';
    }
    return `₦${(Number(minor) / 100).toLocaleString()}`;
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

function formatMinutes(minutes) {
    const hrs = Math.floor(minutes / 60);
    const mins = minutes % 60;
    if (hrs > 0) {
        return `${hrs}h ${mins}m`;
    }
    return `${mins}m`;
}

function formatTimelineKey(key) {
    return String(key).replaceAll('_', ' ');
}

function formatEventAction(action) {
    return String(action).replaceAll('.', ' · ').replaceAll('_', ' ');
}

function statusClass(tone) {
    return {
        rose: 'bg-rose-100 text-rose-800',
        amber: 'bg-amber-100 text-amber-900',
        orange: 'bg-orange-100 text-orange-900',
        violet: 'bg-violet-100 text-violet-900',
        sky: 'bg-sky-100 text-sky-900',
        slate: 'bg-slate-100 text-slate-700',
        emerald: 'bg-emerald-100 text-emerald-800',
    }[tone] ?? 'bg-slate-100 text-slate-700';
}

function toast(message, type = 'success') {
    if (!message) {
        return;
    }
    toastText.value = message;
    toastType.value = type;
    toastVisible.value = true;
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(() => {
        toastVisible.value = false;
    }, FLASH_TOAST_MS);
}
</script>
