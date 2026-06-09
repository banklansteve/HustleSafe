<template>
    <Teleport to="body">
        <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0" leave-active-class="transition duration-150" leave-to-class="opacity-0">
            <div v-if="open" class="fixed inset-0 z-[85] flex justify-end bg-slate-900/45 backdrop-blur-sm" @click.self="emit('close')">
                <Transition enter-active-class="transition duration-250 ease-out" enter-from-class="translate-x-full" leave-active-class="transition duration-200 ease-in" leave-to-class="translate-x-full">
                    <aside v-if="open" class="flex h-full w-full max-w-2xl flex-col border-l bg-white shadow-2xl dark:bg-slate-950">
                        <div v-if="loading" class="flex flex-1 items-center justify-center p-8">
                            <span class="inline-block h-8 w-8 animate-spin rounded-full border-2 border-primary-600 border-t-transparent" />
                        </div>

                        <div v-else-if="loadError" class="flex flex-1 flex-col items-center justify-center gap-3 p-8 text-center">
                            <p class="text-sm font-semibold text-red-700">{{ loadError }}</p>
                            <button type="button" class="rounded-xl bg-primary-700 px-4 py-2 text-xs font-black uppercase text-white" @click="loadDetail(props.userId)">Retry</button>
                        </div>

                        <template v-else-if="detail">
                            <header class="shrink-0 border-b px-5 py-4">
                                <div class="flex items-start gap-4">
                                    <img :src="detail.header.avatar_url || defaultAvatar(detail.header.fullname)" alt="" class="h-14 w-14 rounded-2xl object-cover ring-2 ring-primary-100" />
                                    <div class="min-w-0 flex-1">
                                        <p class="text-[10px] font-black uppercase tracking-wider text-primary-600">User activity review</p>
                                        <h2 class="truncate text-lg font-black text-slate-900 dark:text-white">{{ detail.header.fullname }}</h2>
                                        <p class="text-xs font-semibold text-slate-500">@{{ detail.header.username }} · ID {{ detail.header.user_id }}</p>
                                        <div class="mt-2 flex flex-wrap gap-2 text-[10px] font-black uppercase">
                                            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 dark:bg-slate-800">{{ detail.header.tier_label }}</span>
                                            <span class="rounded-full px-2.5 py-0.5" :class="riskBadgeClass(detail.header.risk_level)">Risk {{ detail.header.risk_score }}%</span>
                                            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 dark:bg-slate-800">{{ detail.header.account_age_days }}d old</span>
                                        </div>
                                        <p class="mt-1 text-xs text-slate-500">{{ detail.header.location }}</p>
                                        <p v-if="isSuperAdmin && detail.header.latest_login_ip" class="text-xs font-semibold text-slate-600">
                                            Latest login IP: <span class="font-mono">{{ detail.header.latest_login_ip }}</span>
                                        </p>
                                        <p v-if="detail.header.status_label" class="mt-1 text-xs font-bold uppercase text-primary-700">
                                            Case status: {{ detail.header.status_label }}
                                            <span v-if="detail.header.assigned_to" class="font-semibold normal-case text-slate-500"> · Assigned to {{ detail.header.assigned_to.name }}</span>
                                        </p>
                                        <p class="text-xs font-semibold text-slate-600">
                                            ⭐ {{ detail.header.rating ?? '—' }} ({{ detail.header.ratings_count }} reviews) ·
                                            {{ detail.header.completed_jobs }} jobs · {{ detail.header.earned_display }} earned
                                        </p>
                                    </div>
                                    <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" @click="emit('close')">
                                        <XMarkIcon class="h-5 w-5" />
                                    </button>
                                </div>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <button
                                        v-if="canReleaseCase"
                                        type="button"
                                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-[10px] font-black uppercase"
                                        :disabled="assigning"
                                        @click="releaseCase"
                                    >
                                        {{ assigning ? 'Releasing…' : 'Release case' }}
                                    </button>
                                    <button
                                        v-else
                                        type="button"
                                        class="rounded-lg bg-primary-700 px-3 py-1.5 text-[10px] font-black uppercase text-white"
                                        :disabled="assigning || !props.flagId"
                                        @click="assignToMe"
                                    >
                                        {{ assigning ? 'Assigning…' : 'Assign to me' }}
                                    </button>
                                    <button type="button" class="rounded-lg border px-3 py-1.5 text-[10px] font-black uppercase" @click="activeModal = 'message'">Message</button>
                                    <button type="button" class="rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-[10px] font-black uppercase text-emerald-900" :disabled="!props.flagId" @click="activeModal = 'resolve'">Resolve case</button>
                                    <button type="button" class="rounded-lg border border-slate-300 px-3 py-1.5 text-[10px] font-black uppercase text-slate-600" :disabled="!props.flagId" @click="activeModal = 'dismiss'">Dismiss false positive</button>
                                </div>
                            </header>

                            <div class="flex-1 space-y-4 overflow-y-auto px-5 py-5">
                                <section v-if="detail.anomaly_summary" class="rounded-2xl border p-4" :class="anomalyBannerClass(detail.anomaly_summary.risk_level)">
                                    <p class="text-[10px] font-black uppercase tracking-wider">{{ detail.anomaly_summary.risk_level === 'critical' || detail.anomaly_summary.risk_level === 'high' ? '⚠️ HIGH RISK DETECTED' : '🟡 MEDIUM/LOW RISK' }}</p>
                                    <p class="mt-2 text-sm font-black">Primary: {{ detail.anomaly_summary.primary_anomaly }}</p>
                                    <p class="text-xs text-slate-600">Severity: {{ detail.anomaly_summary.severity }} · {{ formatDate(detail.anomaly_summary.detected_at) }}</p>
                                    <p class="mt-1 text-xs font-bold uppercase">{{ detail.anomaly_summary.status_label }}</p>
                                    <ul class="mt-3 list-disc space-y-1 pl-4 text-xs text-slate-700">
                                        <li v-for="(d, i) in detail.anomaly_summary.details" :key="i">{{ d }}</li>
                                    </ul>
                                    <div class="mt-3 rounded-xl bg-white/60 p-3 dark:bg-slate-900/40">
                                        <p class="text-[10px] font-black uppercase text-slate-500">Risk assessment</p>
                                        <ul class="mt-1 space-y-1 text-xs">
                                            <li v-for="(a, i) in detail.anomaly_summary.assessments" :key="i">— {{ a }}</li>
                                        </ul>
                                    </div>
                                </section>

                                <section class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                                    <h3 class="text-[10px] font-black uppercase tracking-wider text-slate-500">Verification status</h3>
                                    <p v-if="!detail.verification.staff_sees_documents" class="mt-1 text-[10px] text-slate-400">Staff view: status only — documents hidden</p>
                                    <ul class="mt-3 space-y-3">
                                        <li v-for="item in detail.verification.items" :key="item.id || item.type" class="rounded-xl border border-slate-100 p-3 text-sm dark:border-slate-800">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span>{{ item.status === 'approved' ? '✓' : '○' }}</span>
                                                <span class="font-semibold">{{ item.label }}</span>
                                                <span class="text-xs text-slate-500">{{ item.status_label || item.status }}</span>
                                            </div>
                                            <ul v-if="item.fields?.length" class="mt-2 space-y-1 text-xs text-slate-600">
                                                <li v-for="f in item.fields" :key="f.key">{{ f.label }}: {{ f.value }}</li>
                                            </ul>
                                            <div v-if="item.documents?.length" class="mt-2 flex flex-wrap gap-2">
                                                <a
                                                    v-for="doc in item.documents"
                                                    :key="doc.url || doc.label"
                                                    :href="doc.url"
                                                    target="_blank"
                                                    rel="noopener"
                                                    class="rounded-lg bg-primary-50 px-2 py-1 text-[10px] font-bold uppercase text-primary-800"
                                                >
                                                    {{ doc.label || 'View document' }}
                                                </a>
                                            </div>
                                        </li>
                                    </ul>
                                </section>

                                <section class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                                    <h3 class="text-[10px] font-black uppercase tracking-wider text-slate-500">Active disputes ({{ detail.disputes.active_count }})</h3>
                                    <ul v-if="detail.disputes.items.length" class="mt-3 space-y-3">
                                        <li v-for="d in detail.disputes.items" :key="d.id" class="rounded-xl border p-3 text-sm">
                                            <p class="font-bold">{{ d.quest_title }}</p>
                                            <p class="text-xs text-slate-500">{{ d.amount_display }} · {{ d.status }} · by {{ d.filed_by }}</p>
                                        </li>
                                    </ul>
                                    <p v-else class="mt-2 text-sm text-emerald-700">No active disputes.</p>
                                </section>

                                <section class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                                    <h3 class="text-[10px] font-black uppercase tracking-wider text-slate-500">Payment & transactions (30d)</h3>
                                    <p v-if="detail.transactions.financial_red_flags" class="mt-2 rounded-lg bg-red-50 px-3 py-2 text-xs font-bold text-red-800">⚠️ Financial red flags detected</p>
                                    <p v-else class="mt-2 text-xs text-emerald-700">No financial red flags detected.</p>
                                    <p class="mt-2 text-xs text-slate-600">Refund rate: {{ detail.transactions.refund_rate_percent ?? 0 }}% · Chargebacks: {{ detail.transactions.chargeback_count ?? 0 }}</p>
                                    <ul v-if="detail.transactions.income?.length" class="mt-3 space-y-1 text-xs">
                                        <li v-for="(t, i) in detail.transactions.income" :key="'in-'+i">{{ t.date }} — {{ t.label }} — {{ t.amount_display }}</li>
                                    </ul>
                                    <ul v-if="detail.transactions.refunds?.length" class="mt-2 space-y-1 text-xs text-orange-800">
                                        <li v-for="(t, i) in detail.transactions.refunds" :key="'rf-'+i">{{ t.date }} — {{ t.label }} — {{ t.amount_display }}</li>
                                    </ul>
                                </section>

                                <section v-if="detail.review_signals?.length" class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                                    <h3 class="text-[10px] font-black uppercase tracking-wider text-slate-500">Review integrity signals</h3>
                                    <ul class="mt-3 space-y-2">
                                        <li v-for="(s, i) in detail.review_signals" :key="i" class="rounded-lg border p-2 text-xs">
                                            <span class="font-bold">{{ s.label }}</span>
                                            <span class="text-slate-500"> · {{ s.type }} · {{ Math.round((s.confidence || 0) * 100) }}%</span>
                                        </li>
                                    </ul>
                                </section>

                                <section class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                                    <h3 class="text-[10px] font-black uppercase tracking-wider text-slate-500">Activity timeline (30d)</h3>
                                    <ul class="mt-3 space-y-2">
                                        <li v-for="(e, i) in detail.timeline" :key="i" class="text-xs">
                                            <span class="font-bold text-slate-500">{{ formatDate(e.at) }}</span> — {{ e.label }}
                                            <span v-if="e.detail" class="text-slate-600">({{ e.detail }})</span>
                                        </li>
                                    </ul>
                                </section>

                                <section class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                                    <h3 class="text-[10px] font-black uppercase tracking-wider text-slate-500">Related accounts</h3>
                                    <p class="mt-2 text-sm" :class="detail.related_accounts.isolated ? 'text-emerald-700' : 'text-orange-700'">{{ detail.related_accounts.message }}</p>
                                    <p v-if="detail.related_accounts.shared_ip" class="mt-2 text-xs text-slate-600">
                                        Shared IP: <span class="font-mono font-semibold">{{ detail.related_accounts.shared_ip }}</span>
                                    </p>
                                    <ul v-if="detail.related_accounts.items.length" class="mt-2 space-y-1 text-xs">
                                        <li v-for="r in detail.related_accounts.items" :key="r.id">
                                            @{{ r.username }} (ID {{ r.id }})
                                            <span v-if="r.shared_ip" class="text-slate-500"> · {{ r.shared_ip }}</span>
                                        </li>
                                    </ul>
                                </section>

                                <section v-if="detail.conversation_flags.length" class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                                    <h3 class="text-[10px] font-black uppercase tracking-wider text-slate-500">Flagged conversations</h3>
                                    <ul class="mt-3 space-y-3">
                                        <li v-for="c in detail.conversation_flags" :key="c.id" class="rounded-xl border p-3 text-xs">
                                            <p class="font-bold">{{ c.quest_title }}</p>
                                            <p class="text-slate-500">{{ c.snippet }}</p>
                                        </li>
                                    </ul>
                                </section>

                                <section class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                                    <h3 class="text-[10px] font-black uppercase tracking-wider text-slate-500">Moderation history</h3>
                                    <ul class="mt-3 space-y-3">
                                        <li v-for="(h, i) in detail.moderation_history" :key="i" class="border-l-2 border-slate-200 pl-3 text-xs">
                                            <p class="font-bold">{{ h.label }} <span class="font-normal text-slate-500">· {{ h.actor }}</span></p>
                                            <p class="text-slate-500">{{ formatDate(h.at) }}</p>
                                            <p v-if="h.body" class="mt-1 text-slate-700">{{ h.body }}</p>
                                        </li>
                                    </ul>
                                </section>
                            </div>

                            <footer class="shrink-0 border-t bg-slate-50 px-5 py-4 dark:bg-slate-900">
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" class="rounded-xl border border-amber-300 bg-amber-50 px-3 py-2 text-[10px] font-black uppercase text-amber-900" @click="activeModal = 'warn'">Warn</button>
                                    <button type="button" class="rounded-xl border px-3 py-2 text-[10px] font-black uppercase" @click="activeModal = 'watchlist'">Watchlist</button>
                                    <button type="button" class="rounded-xl border px-3 py-2 text-[10px] font-black uppercase" @click="activeModal = 'investigate'">Investigate</button>
                                    <button type="button" class="rounded-xl border px-3 py-2 text-[10px] font-black uppercase" @click="activeModal = 'note'">Add note</button>
                                    <template v-if="capabilities.can_suspend">
                                        <button type="button" class="rounded-xl bg-orange-600 px-3 py-2 text-[10px] font-black uppercase text-white" @click="activeModal = 'suspend'">Suspend</button>
                                        <button type="button" class="rounded-xl bg-red-700 px-3 py-2 text-[10px] font-black uppercase text-white" @click="activeModal = 'terminate'">Terminate</button>
                                        <button v-if="capabilities.can_reverse_transaction" type="button" class="rounded-xl border border-red-300 px-3 py-2 text-[10px] font-black uppercase text-red-800" @click="activeModal = 'reverse'">Reverse txn</button>
                                        <button v-if="capabilities.can_merge_accounts" type="button" class="rounded-xl border px-3 py-2 text-[10px] font-black uppercase" @click="activeModal = 'merge'">Merge accounts</button>
                                        <button v-if="capabilities.can_impose_sanction" type="button" class="rounded-xl border px-3 py-2 text-[10px] font-black uppercase" @click="activeModal = 'sanction'">Impose sanction</button>
                                    </template>
                                    <button v-else type="button" disabled class="cursor-not-allowed rounded-xl border px-3 py-2 text-[10px] font-black uppercase text-slate-400" title="Super Admin only">Suspend (SA only)</button>
                                </div>
                            </footer>
                        </template>
                    </aside>
                </Transition>
            </div>
        </Transition>

        <div v-if="activeModal" class="fixed inset-0 z-[90] flex items-end justify-center bg-slate-900/50 p-4 sm:items-center" @click.self="activeModal = null">
            <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-5 shadow-2xl dark:bg-slate-950">
                <h3 class="text-sm font-black uppercase">{{ modalTitle }}</h3>

                <form class="mt-4 space-y-3" @submit.prevent="submitModal">
                    <template v-if="activeModal === 'warn'">
                        <select v-model="form.warning_type" class="w-full rounded-xl border-slate-200 text-sm" required>
                            <option value="policy_violation">Policy violation</option>
                            <option value="suspicious_activity">Suspicious activity</option>
                            <option value="payment_dispute">Payment/dispute issue</option>
                            <option value="communication">Communication violation</option>
                            <option value="other">Other</option>
                        </select>
                        <select v-model="form.severity" class="w-full rounded-xl border-slate-200 text-sm">
                            <option value="informal">Informal warning</option>
                            <option value="formal">Formal warning</option>
                            <option value="final">Final warning</option>
                        </select>
                        <select v-model="form.template" class="w-full rounded-xl border-slate-200 text-sm" @change="applyTemplate">
                            <option value="">Choose template…</option>
                            <option v-for="t in warningTemplates" :key="t.value" :value="t.label">{{ t.label.slice(0, 60) }}…</option>
                        </select>
                        <textarea v-model="form.message" rows="4" maxlength="500" class="w-full rounded-xl border-slate-200 text-sm" placeholder="Warning message" required />
                    </template>

                    <template v-else-if="activeModal === 'watchlist'">
                        <select v-model="form.reason" class="w-full rounded-xl border-slate-200 text-sm" required>
                            <option value="pattern">Monitor for pattern continuation</option>
                            <option value="verification">Monitor for verification completion</option>
                            <option value="fraud">Fraud investigation ongoing</option>
                            <option value="behavioral">Behavioral monitoring</option>
                        </select>
                        <select v-model="form.duration" class="w-full rounded-xl border-slate-200 text-sm">
                            <option value="14d">14 days</option>
                            <option value="30d">30 days</option>
                            <option value="90d">90 days</option>
                            <option value="indefinite">Indefinite</option>
                        </select>
                    </template>

                    <template v-else-if="activeModal === 'investigate'">
                        <input v-model="form.title" type="text" class="w-full rounded-xl border-slate-200 text-sm" placeholder="Investigation title" />
                        <select v-model="form.severity" class="w-full rounded-xl border-slate-200 text-sm">
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                        <textarea v-model="form.notes" rows="5" minlength="20" class="w-full rounded-xl border-slate-200 text-sm" placeholder="Initial evidence and notes" required />
                    </template>

                    <template v-else-if="activeModal === 'message'">
                        <input v-model="form.subject" type="text" class="w-full rounded-xl border-slate-200 text-sm" placeholder="Subject" required />
                        <textarea v-model="form.message" rows="5" maxlength="1000" class="w-full rounded-xl border-slate-200 text-sm" placeholder="Message" required />
                    </template>

                    <template v-else-if="activeModal === 'suspend'">
                        <select v-model="form.reason" class="w-full rounded-xl border-slate-200 text-sm" required>
                            <option value="fraud">Fraud detected</option>
                            <option value="policy">Policy violation (severe)</option>
                            <option value="dispute">Dispute/chargeback issue</option>
                            <option value="off_platform">Off-platform activity</option>
                            <option value="investigation">Under investigation</option>
                        </select>
                        <select v-model="form.duration" class="w-full rounded-xl border-slate-200 text-sm">
                            <option value="7d">7 days (temporary)</option>
                            <option value="30d">30 days (temporary)</option>
                        </select>
                        <textarea v-model="form.notes" rows="3" class="w-full rounded-xl border-slate-200 text-sm" placeholder="Additional notes" />
                    </template>

                    <template v-else-if="activeModal === 'terminate'">
                        <p class="text-xs text-red-700">Permanent termination — user must appeal to Super Admin. Distinct from temporary suspend.</p>
                        <select v-model="form.reason" class="w-full rounded-xl border-slate-200 text-sm" required>
                            <option value="fraud_detected">Fraud detected</option>
                            <option value="policy_violation">Policy violation (severe)</option>
                            <option value="chargeback">Chargeback / payment fraud</option>
                            <option value="off_platform">Off-platform activity</option>
                        </select>
                        <textarea v-model="form.notes" rows="3" class="w-full rounded-xl border-slate-200 text-sm" placeholder="Termination reason (required)" required />
                    </template>

                    <template v-else-if="activeModal === 'reverse'">
                        <select v-model="form.escrow_id" class="w-full rounded-xl border-slate-200 text-sm" required>
                            <option value="">Select transaction…</option>
                            <option v-for="tx in detail?.reversible_transactions || []" :key="tx.id" :value="tx.id">
                                {{ tx.quest_title }} — {{ tx.amount_display }} ({{ tx.reference }})
                            </option>
                        </select>
                        <select v-model="form.reverse_type" class="w-full rounded-xl border-slate-200 text-sm">
                            <option value="full">Full refund</option>
                            <option value="partial">Partial refund</option>
                            <option value="chargeback">Chargeback</option>
                        </select>
                        <input v-if="form.reverse_type === 'partial'" v-model.number="form.partial_amount_minor" type="number" min="1" class="w-full rounded-xl border-slate-200 text-sm" placeholder="Partial amount (kobo)" />
                        <select v-model="form.reason" class="w-full rounded-xl border-slate-200 text-sm" required>
                            <option value="fraud_detected">Fraud detected</option>
                            <option value="dispute_ruling">Dispute ruling</option>
                            <option value="chargeback">Payment processor chargeback</option>
                            <option value="billing_error">Billing error</option>
                        </select>
                        <label class="flex items-center gap-2 text-xs"><input v-model="form.suspend_account" type="checkbox" /> Suspend account (recommended if fraud)</label>
                        <textarea v-model="form.notes" rows="2" class="w-full rounded-xl border-slate-200 text-sm" placeholder="Notes" />
                    </template>

                    <template v-else-if="activeModal === 'merge'">
                        <p class="text-xs text-slate-600">Merge a related account into this primary account. Secondary account will be deactivated.</p>
                        <select v-model="form.secondary_user_id" class="w-full rounded-xl border-slate-200 text-sm" required>
                            <option value="">Select related account…</option>
                            <option v-for="r in detail?.merge_candidates || []" :key="r.id" :value="r.id">@{{ r.username }} (ID {{ r.id }})</option>
                        </select>
                        <select v-model="form.reason" class="w-full rounded-xl border-slate-200 text-sm">
                            <option value="fraud_ring">Fraud ring detected</option>
                            <option value="duplicate_identity">Duplicate identity</option>
                            <option value="shared_ip">Shared IP / device</option>
                        </select>
                        <textarea v-model="form.notes" rows="3" class="w-full rounded-xl border-slate-200 text-sm" placeholder="Merge notes" required />
                    </template>

                    <template v-else-if="activeModal === 'sanction'">
                        <select v-model="form.sanction_type" class="w-full rounded-xl border-slate-200 text-sm">
                            <option value="restriction">Restriction (under review)</option>
                            <option value="suspension">Suspension</option>
                            <option value="ban">Permanent ban</option>
                        </select>
                        <select v-model="form.duration" class="w-full rounded-xl border-slate-200 text-sm">
                            <option value="7d">7 days</option>
                            <option value="30d">30 days</option>
                            <option value="90d">90 days</option>
                            <option value="indefinite">Indefinite</option>
                        </select>
                        <select v-model="form.reason" class="w-full rounded-xl border-slate-200 text-sm" required>
                            <option value="policy_violation">Policy violation</option>
                            <option value="fraud_detected">Fraud detected</option>
                            <option value="payment_issue">Payment issue</option>
                        </select>
                        <textarea v-model="form.notes" rows="3" class="w-full rounded-xl border-slate-200 text-sm" placeholder="Sanction details" required />
                    </template>

                    <template v-else-if="activeModal === 'note'">
                        <textarea v-model="form.body" rows="4" minlength="10" class="w-full rounded-xl border-slate-200 text-sm" placeholder="Internal note" required />
                    </template>

                    <template v-else-if="activeModal === 'resolve'">
                        <p class="text-xs text-slate-600">Mark this anomaly as reviewed and resolved. It will leave the active patrol queue.</p>
                        <textarea v-model="form.notes" rows="3" maxlength="500" class="w-full rounded-xl border-slate-200 text-sm" placeholder="Resolution notes (optional)" />
                    </template>

                    <template v-else-if="activeModal === 'dismiss'">
                        <p class="text-xs text-slate-600">Dismiss when the detection is a false positive or not actionable.</p>
                        <textarea v-model="form.reason" rows="3" maxlength="500" class="w-full rounded-xl border-slate-200 text-sm" placeholder="Dismissal reason (required)" required />
                    </template>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" class="rounded-xl border px-4 py-2 text-xs font-bold" @click="activeModal = null">Cancel</button>
                        <button type="submit" class="rounded-xl bg-primary-700 px-4 py-2 text-xs font-black uppercase text-white" :disabled="submitting">
                            {{ submitting ? 'Saving…' : 'Confirm' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { XMarkIcon } from '@heroicons/vue/24/outline';

const page = usePage();

const props = defineProps({
    open: { type: Boolean, default: false },
    userId: { type: Number, default: null },
    flagId: { type: Number, default: null },
    routePrefix: { type: String, default: 'operations' },
    isSuperAdmin: { type: Boolean, default: false },
    capabilities: { type: Object, default: () => ({}) },
    warningTemplates: { type: Array, default: () => [] },
    messageTemplates: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'action-done']);

const loading = ref(false);
const loadError = ref(null);
const detail = ref(null);
const assigning = ref(false);
const activeModal = ref(null);
const submitting = ref(false);
const form = ref({});

const currentStaffId = computed(() => page.props.auth?.user?.id ?? null);

const canReleaseCase = computed(() => {
    const assigneeId = detail.value?.header?.assigned_to?.id;
    if (!assigneeId) return false;
    return assigneeId === currentStaffId.value || props.isSuperAdmin;
});

const modalTitles = {
    warn: 'Send warning',
    watchlist: 'Add to watchlist',
    investigate: 'Open investigation',
    message: 'Message user',
    suspend: 'Suspend account (temporary)',
    terminate: 'Terminate account (permanent)',
    reverse: 'Reverse transaction',
    merge: 'Merge accounts',
    sanction: 'Impose sanction',
    note: 'Add internal note',
    resolve: 'Resolve case',
    dismiss: 'Dismiss false positive',
};

const modalTitle = ref('');

watch(activeModal, (m) => {
    modalTitle.value = modalTitles[m] || '';
    form.value = { flag_id: props.flagId, severity: 'medium', duration: '30d' };
});

watch(() => [props.open, props.userId], async ([isOpen, id]) => {
    if (isOpen && id) {
        await loadDetail(id);
    } else {
        detail.value = null;
        loadError.value = null;
    }
}, { immediate: true });

function routeName(name) {
    return `${props.routePrefix}.${name}`;
}

function defaultAvatar(name) {
    return `https://ui-avatars.com/api/?name=${encodeURIComponent(name || 'U')}&background=6366f1&color=fff`;
}

function formatDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString();
}

function riskBadgeClass(level) {
    return {
        critical: 'bg-red-100 text-red-800',
        high: 'bg-orange-100 text-orange-800',
        medium: 'bg-amber-100 text-amber-800',
        low: 'bg-emerald-100 text-emerald-800',
    }[level] || 'bg-slate-100 text-slate-700';
}

function anomalyBannerClass(level) {
    if (level === 'critical' || level === 'high') {
        return 'border-red-200 bg-red-50/50 dark:border-red-900';
    }
    return 'border-amber-200 bg-amber-50/30 dark:border-amber-900';
}

async function loadDetail(userId) {
    loading.value = true;
    loadError.value = null;
    try {
        const { data } = await axios.get(route(routeName('api.user-activity-patrol.detail'), userId), {
            params: { flag_id: props.flagId },
        });
        detail.value = data;
    } catch (error) {
        detail.value = null;
        loadError.value = error?.response?.data?.message || 'Unable to load user review details. Please try again.';
    } finally {
        loading.value = false;
    }
}

async function assignToMe() {
    if (!props.flagId) return;
    assigning.value = true;
    try {
        await axios.post(route(routeName('api.user-activity-patrol.flags.assign'), props.flagId));
        emit('action-done', 'Case assigned to you — status is now Under Review.');
        await loadDetail(props.userId);
    } finally {
        assigning.value = false;
    }
}

async function releaseCase() {
    if (!props.flagId) return;
    assigning.value = true;
    try {
        await axios.post(route(routeName('api.user-activity-patrol.flags.release'), props.flagId));
        emit('action-done', 'Case released back to Open queue.');
        await loadDetail(props.userId);
    } finally {
        assigning.value = false;
    }
}

function applyTemplate() {
    if (form.value.template) {
        form.value.message = form.value.template;
    }
}

const actionRoutes = {
    warn: 'user-activity-patrol.users.warn',
    watchlist: 'user-activity-patrol.users.watchlist',
    investigate: 'user-activity-patrol.users.investigate',
    message: 'user-activity-patrol.users.message',
    suspend: 'user-activity-patrol.users.suspend',
    terminate: 'user-activity-patrol.users.terminate',
    reverse: 'user-activity-patrol.users.reverse-transaction',
    merge: 'user-activity-patrol.users.merge-accounts',
    sanction: 'user-activity-patrol.users.sanction',
    note: 'user-activity-patrol.users.note',
};

function submitModal() {
    const action = activeModal.value;
    if (!action) return;

    if (action === 'resolve') {
        if (!props.flagId) return;
        submitting.value = true;
        router.post(route(routeName('api.user-activity-patrol.flags.resolve'), props.flagId), { notes: form.value.notes || '' }, {
            preserveScroll: true,
            onSuccess: () => {
                activeModal.value = null;
                emit('action-done', 'Case resolved and removed from queue.');
                emit('close');
            },
            onFinish: () => { submitting.value = false; },
        });
        return;
    }

    if (action === 'dismiss') {
        if (!props.flagId || !form.value.reason?.trim()) return;
        submitting.value = true;
        router.post(route(routeName('api.user-activity-patrol.flags.dismiss'), props.flagId), { reason: form.value.reason }, {
            preserveScroll: true,
            onSuccess: () => {
                activeModal.value = null;
                emit('action-done', 'Anomaly dismissed as false positive.');
                emit('close');
            },
            onFinish: () => { submitting.value = false; },
        });
        return;
    }

    if (!props.userId) return;
    submitting.value = true;
    router.post(route(routeName(actionRoutes[action]), props.userId), { ...form.value, flag_id: props.flagId }, {
        preserveScroll: true,
        onSuccess: () => {
            activeModal.value = null;
            emit('action-done', 'Action completed.');
            loadDetail(props.userId);
        },
        onFinish: () => { submitting.value = false; },
    });
}
</script>
