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

        <OperationsQueueTable
            v-if="tab === 'queue'"
            :columns="queueColumns"
            :rows="queueRows"
            :loading="loading"
            :page="queuePage"
            :total="queueTotal"
            :total-pages="queueTotalPages"
            empty-message="No flagged conversations awaiting review."
            @page="(p) => { queuePage = p; loadQueue(); }"
            @open="openReview"
        >
            <template #cell-quest="{ row }">
                <span class="font-semibold text-slate-950">{{ row.quest?.title || 'Quest' }}</span>
                <span class="block text-xs text-slate-500">{{ row.quest?.reference }}</span>
                <span class="mt-1 inline-block rounded-full bg-sky-100 px-2 py-0.5 text-[10px] font-black uppercase text-sky-900">{{ row.source_label }}</span>
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

        <OperationsSlideOver :open="slideOpen" :title="slideTitle" subtitle="Privacy-first redacted view" eyebrow="Conversation monitoring" @close="slideOpen = false">
            <div v-if="detail" class="space-y-4">
                <div v-if="detail.review" class="flex flex-wrap items-center gap-2 text-xs font-semibold text-slate-600">
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

                <ul v-else class="max-h-[50vh] space-y-2 overflow-y-auto">
                    <li
                        v-for="msg in detail.messages"
                        :key="msg.id"
                        class="rounded-xl border p-3 text-sm"
                        :class="msg.is_flagged ? 'border-amber-300 bg-amber-50/80' : 'border-slate-100 bg-slate-50/50'"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-black text-slate-900">{{ msg.user?.name }}</span>
                            <span class="text-[10px] font-semibold text-slate-500">{{ formatDate(msg.created_at) }}</span>
                        </div>
                        <RedactedMessageBody
                            :body="msg.body"
                            :is-redacted="msg.is_redacted || msg.is_flagged"
                            :redaction-label="msg.redaction_label || msg.body"
                            class="mt-1"
                        />
                        <div v-if="msg.flags?.length" class="mt-2 flex flex-wrap gap-1">
                            <span v-for="f in msg.flags" :key="f.pattern" class="rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-black uppercase text-rose-800">{{ f.category_label }}</span>
                        </div>
                    </li>
                </ul>

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
                                v-if="party.can_ban"
                                type="button"
                                class="rounded-lg border border-rose-300 bg-rose-600 px-3 py-1.5 text-[10px] font-black uppercase text-white"
                                :disabled="isActionBusy"
                                @click="banParty(party)"
                            >
                                Ban permanently
                            </button>
                            <button
                                v-if="party.can_escalate_ban"
                                type="button"
                                class="rounded-lg border border-violet-200 bg-violet-50 px-3 py-1.5 text-[10px] font-black uppercase text-violet-900"
                                :disabled="isActionBusy"
                                @click="openEscalateBan"
                            >
                                Escalate to Super Admin
                            </button>
                        </div>
                    </div>
                    <p class="text-[10px] font-semibold text-slate-500">
                        Suspend at {{ detail.sanction_thresholds?.suspend_threshold }} flags · Ban at {{ detail.sanction_thresholds?.ban_threshold }} flags (Super Admin only)
                    </p>
                </div>

                <div v-if="detail.review && !systematicDetail" class="grid gap-2 sm:grid-cols-2">
                    <button type="button" class="rounded-xl border border-slate-200 py-2 text-xs font-black uppercase text-slate-700" :disabled="isActionBusy" @click="showDismiss = true">Dismiss</button>
                    <button type="button" class="rounded-xl border border-amber-200 bg-amber-50 py-2 text-xs font-black uppercase text-amber-900" :disabled="isActionBusy" @click="openWarnPrimary">Warn user</button>
                    <button type="button" class="rounded-xl bg-primary-700 py-2 text-xs font-black uppercase text-white sm:col-span-2" :disabled="isActionBusy" @click="flagRisk">Update risk scores</button>
                </div>

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
                    <textarea v-model="escalateNote" rows="3" class="w-full rounded-lg border border-violet-200 px-2 py-1 text-sm" placeholder="Why Super Admin must decide (e.g. ban threshold reached)" />
                    <button type="button" class="mt-2 w-full rounded-lg bg-violet-700 py-2 text-xs font-black uppercase text-white" @click="escalateToSuperAdmin">Escalate to Super Admin</button>
                </div>

                <button v-if="isSuperAdmin && detail.review && !systematicDetail" type="button" class="w-full rounded-xl border border-slate-200 py-2 text-xs font-black uppercase text-slate-600" @click="revealFull = !revealFull; reloadReview()">
                    {{ revealFull ? 'Hide full message text' : 'Reveal full message text (Super Admin)' }}
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
const assignStaffId = ref(null);
const resolveNote = ref('');
const terms = ref([]);
const termForm = ref({ term_type: 'abusive_blacklist', pattern: '', is_wildcard: false });
const termBusy = ref(false);
const { busy: actionBusy, runAction } = useOperationsAction();

const isActionBusy = computed(() => Object.values(actionBusy).some(Boolean));

const queueColumns = [
    { key: 'quest', label: 'Quest' },
    { key: 'categories', label: 'Triggers' },
    { key: 'flag_count', label: 'Flags' },
    { key: 'priority', label: 'Priority' },
];
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

const api = (name, params) => route(`${props.routePrefix}.${name}`, params);

onMounted(async () => {
    await loadQueue();
    if (props.isSuperAdmin) loadTerms();
    if (props.openReviewId) {
        selectedReviewId.value = props.openReviewId;
        slideOpen.value = true;
        await reloadReview();
    }
});

watch(() => props.openReviewId, async (id) => {
    if (!id) return;
    selectedReviewId.value = id;
    slideOpen.value = true;
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
        const { data } = await window.axios.get(api('api.conversation-monitoring.queue'), { params: { page: queuePage.value } });
        queueRows.value = data.items || [];
        queueTotal.value = data.meta?.total || 0;
        queueTotalPages.value = data.meta?.last_page || 1;
    } finally {
        loading.value = false;
    }
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

function openEscalateBan() {
    showEscalateBan.value = true;
    showWarn.value = false;
    showDismiss.value = false;
}

async function dismissReview() {
    await runAction('dismiss', () => window.axios.post(api('api.conversation-monitoring.reviews.dismiss', selectedReviewId.value), { reason: dismissReason.value }), 'Dismissed.', () => {
        slideOpen.value = false;
        loadQueue();
    });
}

async function warnReview() {
    const payload = {
        note: warnNote.value,
        target_user_id: warnTargetUserId.value || warnTarget.value?.user?.id,
        template_slug: warnTemplateSlug.value || undefined,
    };
    await runAction('warn', () => window.axios.post(api('api.conversation-monitoring.reviews.warn', selectedReviewId.value), payload), 'Warning issued.', () => {
        slideOpen.value = false;
        loadQueue();
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
