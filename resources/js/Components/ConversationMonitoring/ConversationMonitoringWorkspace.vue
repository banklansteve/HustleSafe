<template>
    <div class="space-y-4">
        <div class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm ring-1 ring-slate-100">
                <p class="text-[10px] font-black uppercase text-slate-500">Moderation queue</p>
                <p class="mt-1 font-display text-3xl font-black text-slate-950">{{ summary.moderation_queue ?? 0 }}</p>
            </div>
            <div class="rounded-2xl border border-rose-100 bg-rose-50/50 p-4 shadow-sm ring-1 ring-rose-100">
                <p class="text-[10px] font-black uppercase text-rose-700">Systematic escalations</p>
                <p class="mt-1 font-display text-3xl font-black text-rose-900">{{ summary.systematic_queue ?? 0 }}</p>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm ring-1 ring-slate-100">
                <p class="text-[10px] font-black uppercase text-slate-500">Flags today</p>
                <p class="mt-1 font-display text-3xl font-black text-primary-700">{{ summary.flags_today ?? 0 }}</p>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <button
                v-for="t in tabs"
                :key="t.key"
                type="button"
                class="rounded-xl px-4 py-2 text-xs font-black uppercase"
                :class="tab === t.key ? 'bg-primary-700 text-white' : 'border border-slate-200 bg-white text-slate-700'"
                @click="switchTab(t.key)"
            >
                {{ t.label }}
            </button>
        </div>

        <div v-if="tab === 'queue'" class="space-y-3">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-wrap items-center gap-2">
                    <label class="text-[10px] font-black uppercase tracking-wide text-slate-500">Status</label>
                    <select
                        v-model="queueStatusFilter"
                        class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold uppercase tracking-wide text-slate-700"
                        @change="onQueueFilterChange"
                    >
                        <option v-for="filter in statusFilters" :key="filter.value" :value="filter.value">{{ filter.label }}</option>
                    </select>
                </div>
                <div v-if="isSuperAdmin && selectedReviewIds.length" class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-[10px] font-black uppercase text-rose-800"
                        :disabled="isActionBusy"
                        @click="bulkDeleteSelected"
                    >
                        Delete selected ({{ selectedReviewIds.length }})
                    </button>
                </div>
            </div>

            <OperationsQueueTable
                :columns="queueColumns"
                :rows="queueRows"
                :loading="loading"
                :page="queuePage"
                :total="queueTotal"
                :total-pages="queueTotalPages"
                :empty-message="queueEmptyMessage"
                @page="(p) => { queuePage = p; loadQueue(); }"
                @open="openReview"
            >
                <template #cell-select="{ row }">
                    <input
                        v-if="isSuperAdmin"
                        type="checkbox"
                        class="rounded border-slate-300"
                        :checked="selectedReviewIds.includes(row.id)"
                        @click.stop
                        @change="toggleReviewSelection(row.id)"
                    />
                </template>
                <template #cell-quest="{ row }">
                    <span class="font-semibold text-slate-950">{{ row.quest?.title || 'Quest' }}</span>
                    <span class="block text-xs text-slate-500">{{ row.quest?.reference }}</span>
                    <span class="mt-1 inline-block rounded-full bg-sky-100 px-2 py-0.5 text-[10px] font-black uppercase text-sky-900">{{ row.source_label }}</span>
                </template>
                <template #cell-status="{ row }">
                    <span class="rounded-full px-2 py-0.5 text-[10px] font-black uppercase" :class="statusBadgeClass(row.status)">{{ row.status_label || row.status }}</span>
                    <span v-if="row.reviewed_at" class="mt-1 block text-[10px] font-semibold text-slate-500">{{ formatDate(row.reviewed_at) }}</span>
                </template>
                <template #cell-categories="{ row }">
                    <div class="flex flex-wrap gap-1">
                        <span v-for="c in row.categories" :key="c" class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-black uppercase text-amber-900">{{ c }}</span>
                    </div>
                </template>
                <template #cell-priority="{ row }">
                    <span class="rounded-full px-2 py-0.5 text-[10px] font-black uppercase" :class="row.priority === 'high' ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-700'">{{ row.priority }}</span>
                </template>
            </OperationsQueueTable>
        </div>

        <OperationsQueueTable
            v-else-if="tab === 'systematic'"
            :columns="systematicColumns"
            :rows="systematicRows"
            :loading="loading"
            :page="sysPage"
            :total="sysTotal"
            :total-pages="sysTotalPages"
            empty-message="No systematic patterns detected."
            @page="(p) => { sysPage = p; loadSystematic(); }"
            @open="openSystematic"
        >
            <template #cell-user="{ row }">
                <span class="font-semibold text-slate-950">{{ row.user?.name }}</span>
                <span class="block text-xs text-slate-500">{{ row.user?.email }}</span>
            </template>
        </OperationsQueueTable>

        <div v-else-if="tab === 'terms' && isSuperAdmin" class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold text-slate-600">Manage abusive blacklist and custom keyword patterns. Fuzzy matching uses Levenshtein distance ≤ 2.</p>
            <form class="mt-4 grid gap-2 sm:grid-cols-2" @submit.prevent="addTerm">
                <select v-model="termForm.term_type" class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold">
                    <option value="abusive_blacklist">Abusive blacklist</option>
                    <option value="custom_keyword">Custom keyword</option>
                </select>
                <input v-model="termForm.pattern" required maxlength="200" placeholder="Pattern or *wildcard*" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" />
                <label class="flex items-center gap-2 text-sm font-semibold text-slate-700 sm:col-span-2">
                    <input v-model="termForm.is_wildcard" type="checkbox" class="rounded border-slate-300" />
                    Wildcard pattern
                </label>
                <button type="submit" class="rounded-xl bg-primary-700 px-4 py-2 text-sm font-black text-white sm:col-span-2" :disabled="termBusy">Add term</button>
            </form>
            <ul class="mt-4 max-h-64 space-y-2 overflow-y-auto">
                <li v-for="t in terms" :key="t.id" class="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2 text-sm">
                    <span><span class="font-black uppercase text-[10px] text-primary-700">{{ t.term_type }}</span> · {{ t.pattern }}</span>
                    <button type="button" class="text-xs font-black uppercase text-rose-700" @click="removeTerm(t)">Remove</button>
                </li>
            </ul>
        </div>

        <OperationsSlideOver :open="slideOpen" :title="slideTitle" subtitle="Privacy-first redacted view" eyebrow="Trust review" @close="slideOpen = false">
            <div v-if="detail" class="space-y-4">
                <div v-if="detail.review" class="flex flex-wrap items-center gap-2 text-xs font-semibold text-slate-600">
                    <span class="rounded-full px-2 py-0.5 font-black uppercase" :class="statusBadgeClass(detail.review.status)">{{ detail.review.status_label || detail.review.status }}</span>
                    <span class="rounded-full bg-sky-100 px-2 py-0.5 font-black uppercase text-sky-900">{{ detail.review.source_label }}</span>
                    <span v-if="detail.review.assigned_staff" class="text-slate-500">Assigned: {{ detail.review.assigned_staff.name }}</span>
                    <span v-if="detail.review.super_admin_escalated_at" class="rounded-full bg-violet-100 px-2 py-0.5 font-black uppercase text-violet-900">Awaiting Super Admin</span>
                </div>

                <p v-if="detail.review?.super_admin_escalation_note" class="rounded-xl border border-violet-200 bg-violet-50 px-3 py-2 text-xs font-semibold text-violet-900">
                    Staff note: {{ detail.review.super_admin_escalation_note }}
                </p>

                <p v-if="detail.review?.in_risk_queue_hint?.length" class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-800">
                    Trust cross-ref: {{ detail.review.in_risk_queue_hint.join(' · ') }}
                </p>

                <section
                    v-if="!systematicDetail && detail.conversation_links?.length"
                    class="rounded-xl border border-primary-200 bg-primary-50/50 p-4"
                >
                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-primary-800">
                        Conversation access
                    </p>
                    <p class="mt-1 text-xs font-semibold text-slate-600">
                        Review the flagged trigger below, then open the full thread for surrounding context.
                    </p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <a
                            v-for="link in detail.conversation_links"
                            :key="link.href + link.label"
                            :href="link.href"
                            :target="link.external ? '_blank' : undefined"
                            :rel="link.external ? 'noopener noreferrer' : undefined"
                            class="inline-flex items-center gap-1 rounded-full border border-primary-200 bg-white px-3 py-1.5 text-[10px] font-black uppercase tracking-wide text-primary-900 shadow-sm transition hover:border-primary-400 hover:bg-primary-50"
                            @click="link.external ? null : scrollToConversation($event)"
                        >
                            {{ link.label }}
                            <span v-if="link.external" aria-hidden="true">↗</span>
                        </a>
                    </div>
                </section>

                <section
                    v-if="!systematicDetail && flaggedMessages.length"
                    class="rounded-xl border border-rose-200 bg-rose-50/70 p-4"
                >
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-rose-800">
                            Flagged trigger
                        </p>
                        <span class="rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-black uppercase text-rose-800">
                            {{ flaggedMessages.length }} message{{ flaggedMessages.length === 1 ? '' : 's' }}
                        </span>
                    </div>
                    <p class="mt-1 text-xs font-semibold text-rose-900/80">
                        The highlighted text in <span class="font-black text-rose-700">bold red</span> is what matched the scanner rule.
                    </p>
                    <ul class="mt-3 space-y-3">
                        <li
                            v-for="msg in flaggedMessages"
                            :key="`flagged-${msg.id}`"
                            class="rounded-xl border border-rose-200 bg-white p-3 text-sm shadow-sm"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-black text-slate-900">{{ msg.user?.name }}</span>
                                <span class="text-[10px] font-semibold text-slate-500">{{ formatDate(msg.created_at) }}</span>
                            </div>
                            <RedactedMessageBody
                                :body="msg.body"
                                :is-redacted="msg.display_mode === 'redacted'"
                                :is-revealed="msg.is_revealed"
                                :redaction-label="msg.redaction_label || msg.body"
                                :trigger-highlights="msg.trigger_highlights || []"
                                class="mt-2"
                            />
                            <div v-if="msg.flags?.length" class="mt-2 flex flex-wrap gap-1">
                                <span
                                    v-for="f in msg.flags"
                                    :key="`${msg.id}-${f.pattern}`"
                                    class="rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-black uppercase text-rose-800"
                                >
                                    {{ f.category_label }}
                                </span>
                            </div>
                            <p v-if="msg.flags?.[0]?.pattern" class="mt-2 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                Matched pattern: {{ msg.flags[0].pattern }}
                            </p>
                            <div v-if="msg.flags?.[0]?.reasoning?.length" class="mt-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
                                <p class="text-[10px] font-black uppercase tracking-wide text-slate-600">
                                    Detection reasoning
                                </p>
                                <p class="mt-1 text-[11px] font-semibold text-slate-600">
                                    Pattern {{ msg.flags[0].pattern_score ?? 0 }} + Context {{ msg.flags[0].context_score ?? 0 }}
                                    = {{ msg.flags[0].confidence ?? 0 }}% confidence
                                </p>
                                <ul class="mt-2 space-y-1">
                                    <li
                                        v-for="(line, ri) in msg.flags[0].reasoning"
                                        :key="ri"
                                        class="text-[11px] font-medium text-slate-700"
                                    >
                                        <span class="font-black text-slate-900">{{ line.signal }}</span>:
                                        {{ line.match }}
                                        <span class="font-black" :class="line.points >= 0 ? 'text-rose-700' : 'text-emerald-700'">
                                            {{ line.points >= 0 ? '+' : '' }}{{ line.points }}
                                        </span>
                                    </li>
                                </ul>
                            </div>
                        </li>
                    </ul>
                    <button
                        v-if="isSuperAdmin && !revealFull"
                        type="button"
                        class="mt-3 w-full rounded-xl border border-rose-300 bg-white py-2 text-xs font-black uppercase text-rose-800"
                        @click="enableReveal"
                    >
                        Reveal full conversation text
                    </button>
                </section>

                <div v-if="systematicDetail" class="space-y-3">
                    <p class="text-sm font-semibold text-slate-700">{{ systematicDetail.escalation.trigger_label }} · {{ systematicDetail.escalation.instance_count }} instances across {{ systematicDetail.escalation.distinct_counterparties }} counterparties</p>
                    <ul class="max-h-48 space-y-2 overflow-y-auto rounded-xl border border-slate-100 bg-slate-50 p-3 text-xs">
                        <li v-for="(ev, i) in systematicDetail.escalation.timeline" :key="i" class="border-b border-slate-100 pb-2 last:border-0">
                            <span class="font-black text-slate-800">{{ ev.pattern }}</span>
                            <span class="text-slate-500"> · {{ ev.category }} · {{ formatDate(ev.flagged_at) }}</span>
                        </li>
                    </ul>
                    <textarea v-if="isSuperAdmin" v-model="resolveNote" rows="3" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Mandatory resolution note (Super Admin)" />
                    <button v-if="isSuperAdmin" type="button" class="w-full rounded-xl bg-primary-700 py-2 text-sm font-black text-white" :disabled="isActionBusy" @click="resolveSystematic">Resolve systematic case</button>
                    <p v-else class="text-xs font-semibold text-rose-700">Staff cannot dismiss systematic escalations. Super Admin resolution required.</p>
                </div>

                <section v-else id="conversation-monitoring-thread" class="space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-600">
                            Full conversation
                        </p>
                        <button
                            v-if="isSuperAdmin && detail.review"
                            type="button"
                            class="rounded-full border border-slate-200 bg-white px-3 py-1 text-[10px] font-black uppercase text-slate-700"
                            @click="toggleReveal"
                        >
                            {{ revealFull ? 'Hide full message text' : 'Reveal full message text' }}
                        </button>
                    </div>
                    <ul class="max-h-[50vh] space-y-2 overflow-y-auto">
                    <li
                        v-for="msg in detail.messages"
                        :key="msg.id"
                        :id="`conversation-message-${msg.id}`"
                        class="rounded-xl border p-3 text-sm scroll-mt-24"
                        :class="msg.is_flagged ? 'border-amber-300 bg-amber-50/80' : 'border-slate-100 bg-slate-50/50'"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-black text-slate-900">{{ msg.user?.name }}</span>
                            <span class="text-[10px] font-semibold text-slate-500">{{ formatDate(msg.created_at) }}</span>
                        </div>
                        <RedactedMessageBody
                            :body="msg.body"
                            :is-redacted="msg.display_mode === 'redacted'"
                            :is-revealed="msg.is_revealed"
                            :redaction-label="msg.redaction_label || msg.body"
                            :trigger-highlights="msg.trigger_highlights || []"
                            class="mt-1"
                        />
                        <div v-if="msg.flags?.length" class="mt-2 flex flex-wrap gap-1">
                            <span v-for="f in msg.flags" :key="f.pattern" class="rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-black uppercase text-rose-800">{{ f.category_label }}</span>
                        </div>
                    </li>
                    </ul>
                </section>

                <div v-if="isSuperAdmin && detail.assignable_staff?.length && detail.review && !systematicDetail" class="rounded-xl border border-primary-200 bg-primary-50/50 p-3">
                    <label class="text-[10px] font-black uppercase text-primary-800">Assign to staff admin</label>
                    <div class="mt-2 flex gap-2">
                        <select v-model="assignStaffId" class="min-w-0 flex-1 rounded-lg border border-primary-200 px-2 py-2 text-sm font-semibold">
                            <option :value="null">Select staff admin…</option>
                            <option v-for="s in detail.assignable_staff" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                        <button type="button" class="shrink-0 rounded-lg bg-primary-700 px-3 py-2 text-xs font-black uppercase text-white" :disabled="isActionBusy || !assignStaffId" @click="assignReview">Assign</button>
                    </div>
                </div>

                <div v-if="detail.party_actions?.length && detail.review && !systematicDetail" class="space-y-3">
                    <div
                        v-for="party in detail.party_actions"
                        :key="party.user.id"
                        class="rounded-xl border border-slate-200 bg-white p-3"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p class="text-xs font-black uppercase text-slate-500">{{ party.label }}</p>
                                <p class="font-semibold text-slate-950">{{ party.user.name }}</p>
                                <p class="text-xs text-slate-500">{{ party.user.email }}</p>
                            </div>
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-black uppercase text-slate-700">{{ party.flag_count }} flags</span>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button type="button" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-[10px] font-black uppercase text-amber-900" :disabled="isActionBusy" @click="openWarnFor(party)">Warn</button>
                            <button
                                v-if="party.can_suspend"
                                type="button"
                                class="rounded-lg border border-orange-200 bg-orange-50 px-3 py-1.5 text-[10px] font-black uppercase text-orange-900"
                                :disabled="isActionBusy"
                                @click="suspendParty(party)"
                            >
                                Suspend ({{ detail.sanction_thresholds?.suspend_duration_weeks }}w)
                            </button>
                            <button
                                v-if="party.can_escalate_suspend || party.can_escalate_ban"
                                type="button"
                                class="rounded-lg border border-violet-200 bg-violet-50 px-3 py-1.5 text-[10px] font-black uppercase text-violet-900"
                                :disabled="isActionBusy"
                                @click="openEscalateFor(party)"
                            >
                                Escalate to Super Admin
                            </button>
                            <button
                                v-if="party.can_ban"
                                type="button"
                                class="rounded-lg border border-rose-300 bg-rose-600 px-3 py-1.5 text-[10px] font-black uppercase text-white"
                                :disabled="isActionBusy"
                                @click="banParty(party)"
                            >
                                Ban permanently
                            </button>
                        </div>
                    </div>
                    <p class="text-[10px] font-semibold text-slate-500">
                        <template v-if="isSuperAdmin">
                            Suspend at {{ detail.sanction_thresholds?.suspend_threshold }} flags · Ban at {{ detail.sanction_thresholds?.ban_threshold }} flags
                        </template>
                        <template v-else>
                            At {{ detail.sanction_thresholds?.suspend_threshold }} flags, escalate for suspension review · At {{ detail.sanction_thresholds?.ban_threshold }} flags, escalate for permanent ban (Super Admin only)
                        </template>
                    </p>
                </div>

                <div v-if="detail.review && !systematicDetail" class="grid gap-2 sm:grid-cols-2">
                    <button type="button" class="rounded-xl border border-slate-200 py-2 text-xs font-black uppercase text-slate-700" :disabled="isActionBusy" @click="showDismiss = true">Dismiss</button>
                    <button type="button" class="rounded-xl border border-amber-200 bg-amber-50 py-2 text-xs font-black uppercase text-amber-900" :disabled="isActionBusy" @click="openWarnPrimary">Warn user</button>
                    <button type="button" class="rounded-xl bg-primary-700 py-2 text-xs font-black uppercase text-white sm:col-span-2" :disabled="isActionBusy" @click="flagRisk">Update risk scores</button>
                    <button
                        v-if="isSuperAdmin"
                        type="button"
                        class="rounded-xl border border-rose-200 bg-rose-50 py-2 text-xs font-black uppercase text-rose-800 sm:col-span-2"
                        :disabled="isActionBusy"
                        @click="deleteCurrentReview"
                    >
                        Delete review record
                    </button>
                </div>

                <section
                    v-if="detail.policy_warnings?.length && !systematicDetail"
                    class="rounded-xl border border-amber-200 bg-amber-50/60 p-4"
                >
                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-amber-900">Warnings issued</p>
                    <ul class="mt-3 space-y-3">
                        <li
                            v-for="warning in detail.policy_warnings"
                            :key="warning.id"
                            class="rounded-xl border border-amber-200 bg-white p-3 text-sm"
                        >
                            <p class="font-black text-slate-900">{{ warning.user?.name }}</p>
                            <p class="mt-1 whitespace-pre-wrap text-slate-700">{{ warning.note }}</p>
                            <p class="mt-2 text-[10px] font-semibold text-slate-500">
                                {{ formatDate(warning.issued_at) }}
                                <span v-if="warning.acknowledged_at"> · Acknowledged {{ formatDate(warning.acknowledged_at) }}</span>
                            </p>
                            <ul v-if="warning.replies?.length" class="mt-3 space-y-2 border-t border-slate-100 pt-3">
                                <li
                                    v-for="reply in warning.replies"
                                    :key="reply.id"
                                    class="rounded-lg bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700"
                                >
                                    <span class="font-black text-slate-900">{{ reply.user?.name }} replied:</span>
                                    <p class="mt-1 whitespace-pre-wrap">{{ reply.body }}</p>
                                    <p class="mt-1 text-[10px] text-slate-400">{{ formatDate(reply.created_at) }}</p>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </section>

                <div v-if="showDismiss" class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <textarea v-model="dismissReason" rows="2" class="w-full rounded-lg border border-slate-200 px-2 py-1 text-sm" placeholder="False positive reason" />
                    <button type="button" class="mt-2 w-full rounded-lg bg-slate-800 py-2 text-xs font-black uppercase text-white" @click="dismissReview">Confirm dismiss</button>
                </div>

                <div v-if="showWarn" class="rounded-xl border border-amber-200 bg-amber-50 p-3 space-y-2">
                    <p v-if="warnTarget" class="text-xs font-black uppercase text-amber-900">Warning for {{ warnTarget.user.name }}</p>
                    <select v-if="detail.party_actions?.length > 1" v-model="warnTargetUserId" class="w-full rounded-lg border border-amber-200 px-2 py-1.5 text-sm font-semibold">
                        <option v-for="party in detail.party_actions" :key="party.user.id" :value="party.user.id">{{ party.label }} — {{ party.user.name }}</option>
                    </select>
                    <select v-if="detail.warning_templates?.length" v-model="warnTemplateSlug" class="w-full rounded-lg border border-amber-200 px-2 py-1.5 text-sm font-semibold">
                        <option value="">Custom note only</option>
                        <option v-for="t in detail.warning_templates" :key="t.slug" :value="t.slug">{{ t.title }}</option>
                    </select>
                    <textarea v-model="warnNote" rows="3" class="w-full rounded-lg border border-amber-200 px-2 py-1 text-sm" placeholder="Policy warning note (sent to user if template selected)" />
                    <button type="button" class="w-full rounded-lg bg-amber-700 py-2 text-xs font-black uppercase text-white" @click="warnReview">Issue warning</button>
                </div>

                <div v-if="showEscalateBan" class="rounded-xl border border-violet-200 bg-violet-50 p-3">
                    <p v-if="escalateTarget" class="text-xs font-black uppercase text-violet-900">
                        Escalating {{ escalateTarget.user.name }} for Super Admin review
                    </p>
                    <textarea
                        v-model="escalateNote"
                        rows="3"
                        class="w-full rounded-lg border border-violet-200 px-2 py-1 text-sm"
                        :placeholder="escalatePlaceholder"
                    />
                    <button type="button" class="mt-2 w-full rounded-lg bg-violet-700 py-2 text-xs font-black uppercase text-white" @click="escalateToSuperAdmin">Escalate to Super Admin</button>
                </div>

                <button v-if="isSuperAdmin && detail.review && !systematicDetail && !revealFull" type="button" class="w-full rounded-xl border border-slate-200 py-2 text-xs font-black uppercase text-slate-600" @click="enableReveal">
                    Reveal full message text (Super Admin)
                </button>
            </div>
        </OperationsSlideOver>
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import RedactedMessageBody from '@/Components/ConversationMonitoring/RedactedMessageBody.vue';
import OperationsQueueTable from '@/Pages/Operations/Components/OperationsQueueTable.vue';
import OperationsSlideOver from '@/Pages/Operations/Components/OperationsSlideOver.vue';
import { useOperationsAction } from '@/composables/useOperationsAction';

const props = defineProps({
    summary: { type: Object, default: () => ({}) },
    isSuperAdmin: { type: Boolean, default: false },
    routePrefix: { type: String, required: true },
    openReviewId: { type: Number, default: null },
});

const tabs = computed(() => {
    const base = [
        { key: 'queue', label: 'Flagged conversations' },
        { key: 'systematic', label: 'Systematic escalations' },
    ];
    if (props.isSuperAdmin) base.push({ key: 'terms', label: 'Blacklist & keywords' });
    return base;
});

const tab = ref('queue');
const loading = ref(false);
const queueRows = ref([]);
const queuePage = ref(1);
const queueTotal = ref(0);
const queueTotalPages = ref(1);
const queueStatusFilter = ref('needs_action');
const statusFilters = ref([
    { value: 'needs_action', label: 'Needs action' },
    { value: 'warned', label: 'Warned' },
    { value: 'dismissed', label: 'Dismissed' },
    { value: 'resolved', label: 'Resolved' },
    { value: 'all', label: 'All (24 months)' },
]);
const selectedReviewIds = ref([]);
const systematicRows = ref([]);
const sysPage = ref(1);
const sysTotal = ref(0);
const sysTotalPages = ref(1);
const slideOpen = ref(false);
const detail = ref(null);
const systematicDetail = ref(null);
const selectedReviewId = ref(null);
const selectedEscalationId = ref(null);
const revealFull = ref(false);
const showDismiss = ref(false);
const showWarn = ref(false);
const showEscalateBan = ref(false);
const dismissReason = ref('');
const warnNote = ref('');
const warnTemplateSlug = ref('');
const warnTarget = ref(null);
const warnTargetUserId = ref(null);
const escalateNote = ref('');
const escalateTarget = ref(null);
const assignStaffId = ref(null);
const resolveNote = ref('');
const terms = ref([]);
const termForm = ref({ term_type: 'abusive_blacklist', pattern: '', is_wildcard: false });
const termBusy = ref(false);
const { busy: actionBusy, runAction } = useOperationsAction();

const isActionBusy = computed(() => Object.values(actionBusy).some(Boolean));

const queueColumns = computed(() => {
    const cols = [
        { key: 'quest', label: 'Quest' },
        { key: 'status', label: 'Status' },
        { key: 'categories', label: 'Triggers' },
        { key: 'flag_count', label: 'Flags' },
        { key: 'priority', label: 'Priority' },
    ];
    if (props.isSuperAdmin) {
        cols.unshift({ key: 'select', label: '' });
    }
    return cols;
});
const systematicColumns = [
    { key: 'user', label: 'User' },
    { key: 'trigger_label', label: 'Pattern' },
    { key: 'instance_count', label: 'Instances' },
    { key: 'distinct_counterparties', label: 'Parties' },
];

const slideTitle = computed(() => {
    if (systematicDetail.value) return systematicDetail.value.escalation?.user?.name || 'Systematic case';
    return detail.value?.review?.quest?.title || 'Conversation review';
});

const flaggedMessages = computed(() => detail.value?.flagged_messages || detail.value?.messages?.filter((msg) => msg.is_flagged) || []);

const escalatePlaceholder = computed(() => {
    if (escalateTarget.value?.can_escalate_ban) {
        return 'Why Super Admin should review for suspension or permanent ban…';
    }

    return 'Why Super Admin should review for suspension…';
});

const queueEmptyMessage = computed(() => {
    if (queueStatusFilter.value === 'needs_action') {
        return 'No flagged conversations awaiting review.';
    }
    return 'No review records match this filter.';
});

const api = (name, params) => route(`${props.routePrefix}.${name}`, params);

onMounted(async () => {
    await loadQueue();
    if (props.isSuperAdmin) loadTerms();
    if (props.openReviewId) {
        selectedReviewId.value = props.openReviewId;
        slideOpen.value = true;
        if (props.isSuperAdmin) {
            revealFull.value = true;
        }
        await reloadReview();
    }
});

watch(() => props.openReviewId, async (id) => {
    if (!id) return;
    selectedReviewId.value = id;
    slideOpen.value = true;
    if (props.isSuperAdmin) {
        revealFull.value = true;
    }
    await reloadReview();
});

async function switchTab(key) {
    tab.value = key;
    if (key === 'systematic') loadSystematic();
    if (key === 'queue') loadQueue();
    if (key === 'terms') loadTerms();
}

async function loadQueue() {
    loading.value = true;
    try {
        const { data } = await window.axios.get(api('api.conversation-monitoring.queue'), {
            params: { page: queuePage.value, status: queueStatusFilter.value },
        });
        queueRows.value = data.items || [];
        queueTotal.value = data.meta?.total || 0;
        queueTotalPages.value = data.meta?.last_page || 1;
        if (Array.isArray(data.status_filters) && data.status_filters.length) {
            statusFilters.value = data.status_filters;
        }
        selectedReviewIds.value = selectedReviewIds.value.filter((id) => queueRows.value.some((row) => row.id === id));
    } finally {
        loading.value = false;
    }
}

function onQueueFilterChange() {
    queuePage.value = 1;
    selectedReviewIds.value = [];
    loadQueue();
}

function toggleReviewSelection(id) {
    if (selectedReviewIds.value.includes(id)) {
        selectedReviewIds.value = selectedReviewIds.value.filter((value) => value !== id);
        return;
    }
    selectedReviewIds.value = [...selectedReviewIds.value, id];
}

function statusBadgeClass(status) {
    if (status === 'warned') return 'bg-amber-100 text-amber-900';
    if (status === 'dismissed') return 'bg-slate-100 text-slate-700';
    if (status === 'resolved') return 'bg-emerald-100 text-emerald-800';
    if (status === 'awaiting_super_admin') return 'bg-violet-100 text-violet-900';
    if (status === 'assigned') return 'bg-sky-100 text-sky-900';
    return 'bg-rose-100 text-rose-800';
}

async function loadSystematic() {
    loading.value = true;
    try {
        const { data } = await window.axios.get(api('api.conversation-monitoring.systematic'), { params: { page: sysPage.value } });
        systematicRows.value = data.items || [];
        sysTotal.value = data.meta?.total || 0;
        sysTotalPages.value = data.meta?.last_page || 1;
    } finally {
        loading.value = false;
    }
}

async function openReview(row) {
    selectedReviewId.value = row.id;
    selectedEscalationId.value = null;
    systematicDetail.value = null;
    resetActionPanels();
    if (props.isSuperAdmin) {
        revealFull.value = true;
    } else {
        revealFull.value = false;
    }
    slideOpen.value = true;
    await reloadReview();
}

function resetActionPanels() {
    showDismiss.value = false;
    showWarn.value = false;
    showEscalateBan.value = false;
    warnTarget.value = null;
    warnNote.value = '';
    warnTemplateSlug.value = '';
    escalateNote.value = '';
    escalateTarget.value = null;
    assignStaffId.value = null;
}

async function reloadReview() {
    if (!selectedReviewId.value) return;
    const { data } = await window.axios.get(api('api.conversation-monitoring.reviews.show', selectedReviewId.value), {
        params: props.isSuperAdmin && revealFull.value ? { reveal: 1 } : {},
    });
    detail.value = data;
    if (data.review?.assigned_staff?.id) {
        assignStaffId.value = data.review.assigned_staff.id;
    }
}

async function enableReveal() {
    revealFull.value = true;
    await reloadReview();
}

async function toggleReveal() {
    revealFull.value = !revealFull.value;
    await reloadReview();
}

function scrollToConversation(event) {
    event.preventDefault();
    document.getElementById('conversation-monitoring-thread')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

async function openSystematic(row) {
    selectedEscalationId.value = row.id;
    selectedReviewId.value = null;
    slideOpen.value = true;
    resetActionPanels();
    const { data } = await window.axios.get(api('api.conversation-monitoring.systematic.show', row.id));
    systematicDetail.value = data;
    detail.value = null;
}

function openWarnFor(party) {
    warnTarget.value = party;
    warnTargetUserId.value = party?.user?.id ?? null;
    showWarn.value = true;
    showDismiss.value = false;
    showEscalateBan.value = false;
}

function openWarnPrimary() {
    const parties = detail.value?.party_actions || [];
    if (parties.length === 1) {
        openWarnFor(parties[0]);
        return;
    }
    warnTarget.value = parties[0] || null;
    warnTargetUserId.value = parties[0]?.user?.id ?? null;
    showWarn.value = true;
    showDismiss.value = false;
    showEscalateBan.value = false;
}

function openEscalateFor(party) {
    escalateTarget.value = party;
    showEscalateBan.value = true;
    showWarn.value = false;
    showDismiss.value = false;
}

function openEscalateBan() {
    openEscalateFor(detail.value?.party_actions?.find((party) => party.can_escalate_ban || party.can_escalate_suspend) || null);
}

async function dismissReview() {
    await runAction('dismiss', () => window.axios.post(api('api.conversation-monitoring.reviews.dismiss', selectedReviewId.value), { reason: dismissReason.value }), 'Dismissed.', async () => {
        queueStatusFilter.value = 'dismissed';
        await loadQueue();
        await reloadReview();
    });
}

async function warnReview() {
    const payload = {
        note: warnNote.value,
        target_user_id: warnTargetUserId.value || warnTarget.value?.user?.id,
        template_slug: warnTemplateSlug.value || undefined,
    };
    await runAction('warn', () => window.axios.post(api('api.conversation-monitoring.reviews.warn', selectedReviewId.value), payload), 'Warning issued.', async () => {
        showWarn.value = false;
        warnNote.value = '';
        warnTemplateSlug.value = '';
        queueStatusFilter.value = 'warned';
        await loadQueue();
        await reloadReview();
    });
}

async function assignReview() {
    await runAction('assign', () => window.axios.post(api('api.conversation-monitoring.reviews.assign', selectedReviewId.value), { staff_id: assignStaffId.value }), 'Assigned to staff admin.', () => reloadReview());
}

async function escalateToSuperAdmin() {
    if (escalateNote.value.trim().length < 8) return;
    await runAction('escalate', () => window.axios.post(api('api.conversation-monitoring.reviews.escalate-super-admin', selectedReviewId.value), { note: escalateNote.value }), 'Escalated to Super Admin.', () => {
        slideOpen.value = false;
        loadQueue();
    });
}

async function suspendParty(party) {
    if (!props.isSuperAdmin) {
        openEscalateFor(party);
        return;
    }
    const note = window.prompt(`Suspend ${party.user.name}? Optional note:`) ?? '';
    if (note === null) return;
    await runAction('suspend', () => window.axios.post(api('api.conversation-monitoring.reviews.suspend-user', selectedReviewId.value), { user_id: party.user.id, note }), 'User suspended.', () => {
        slideOpen.value = false;
        loadQueue();
    });
}

async function banParty(party) {
    if (!window.confirm(`Permanently ban ${party.user.name}? This cannot be undone.`)) return;
    const note = window.prompt('Optional ban note:') ?? '';
    if (note === null) return;
    await runAction('ban', () => window.axios.post(api('api.conversation-monitoring.reviews.ban-user', selectedReviewId.value), { user_id: party.user.id, note }), 'User banned.', () => {
        slideOpen.value = false;
        loadQueue();
    });
}

async function flagRisk() {
    await runAction('risk', () => window.axios.post(api('api.conversation-monitoring.reviews.flag-risk', selectedReviewId.value)), 'Risk update queued.');
}

async function deleteCurrentReview() {
    if (!selectedReviewId.value || !window.confirm('Delete this review record? This removes it from the audit list.')) {
        return;
    }
    await runAction('delete', () => window.axios.delete(api('api.conversation-monitoring.reviews.destroy', selectedReviewId.value)), 'Review deleted.', () => {
        slideOpen.value = false;
        selectedReviewIds.value = selectedReviewIds.value.filter((id) => id !== selectedReviewId.value);
        loadQueue();
    });
}

async function bulkDeleteSelected() {
    if (!selectedReviewIds.value.length || !window.confirm(`Delete ${selectedReviewIds.value.length} review record(s)?`)) {
        return;
    }
    await runAction('bulk-delete', () => window.axios.post(api('api.conversation-monitoring.reviews.bulk-delete'), { review_ids: selectedReviewIds.value }), 'Selected reviews deleted.', () => {
        if (selectedReviewIds.value.includes(selectedReviewId.value)) {
            slideOpen.value = false;
        }
        selectedReviewIds.value = [];
        loadQueue();
    });
}

async function resolveSystematic() {
    if (!resolveNote.value.trim()) return;
    await runAction('resolve', () => window.axios.post(api('api.conversation-monitoring.systematic.resolve', selectedEscalationId.value), { resolution_note: resolveNote.value }), 'Resolved.', () => {
        slideOpen.value = false;
        loadSystematic();
    });
}

async function loadTerms() {
    const { data } = await window.axios.get(api('api.conversation-monitoring.terms'));
    terms.value = data.terms || [];
}

async function addTerm() {
    termBusy.value = true;
    try {
        await window.axios.post(api('api.conversation-monitoring.terms.store'), termForm.value);
        termForm.value.pattern = '';
        await loadTerms();
    } finally {
        termBusy.value = false;
    }
}

async function removeTerm(t) {
    await window.axios.delete(api('api.conversation-monitoring.terms.destroy', t.id));
    await loadTerms();
}

function formatDate(iso) {
    try {
        return new Date(iso).toLocaleString();
    } catch {
        return iso || '';
    }
}
</script>
