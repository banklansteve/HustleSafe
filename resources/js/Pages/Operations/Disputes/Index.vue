<template>
    <OperationsShell title="My dispute queue" subtitle="Investigate assigned cases, document assessments, and route ready files to Super Admin for final decisions.">
        <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm ring-1 ring-slate-100">
                <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Assigned to me</p>
                <p class="mt-1 font-display text-2xl font-black text-slate-900">{{ queueSummary.assigned_total }}</p>
            </div>
            <div class="rounded-2xl border border-amber-100 bg-amber-50/70 p-4 shadow-sm ring-1 ring-amber-100">
                <p class="text-[10px] font-black uppercase tracking-wider text-amber-800">Pending my action</p>
                <p class="mt-1 font-display text-2xl font-black text-amber-950">{{ queueSummary.pending_action }}</p>
            </div>
            <div v-if="queueSummary.needs_super_admin_response" class="rounded-2xl border border-violet-200 bg-violet-50/70 p-4 shadow-sm ring-1 ring-violet-200">
                <p class="text-[10px] font-black uppercase tracking-wider text-violet-900">Super Admin requests</p>
                <p class="mt-1 font-display text-2xl font-black text-violet-950">{{ queueSummary.needs_super_admin_response }}</p>
            </div>
        </div>

        <div class="mb-4 flex flex-wrap gap-2">
            <button
                v-for="filter in queueSummary.filters"
                :key="filter.key"
                type="button"
                class="rounded-full px-3 py-1.5 text-[10px] font-black uppercase tracking-wide transition"
                :class="activeFilter === filter.key ? 'bg-primary-700 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-200'"
                @click="setFilter(filter.key)"
            >
                {{ filter.label }}
            </button>
        </div>

        <div class="mb-4 flex flex-wrap items-center gap-2">
            <select v-model="activeSort" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold uppercase text-slate-700" @change="reload">
                <option v-for="sort in queueSummary.sorts" :key="sort.key" :value="sort.key">{{ sort.label }}</option>
            </select>
            <input
                v-model="searchQuery"
                type="search"
                placeholder="Search dispute, contract, party…"
                class="min-h-10 flex-1 rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold"
                @keydown.enter="reload"
            />
            <button type="button" class="rounded-xl bg-primary-700 px-4 py-2 text-xs font-black uppercase text-white" @click="reload">Apply</button>
        </div>

        <OperationsQueueTable
            :columns="columns"
            :rows="queue.pageItems.value"
            :loading="loading"
            v-model:search="queue.search.value"
            v-model:per-page="queue.perPage.value"
            :page="queue.page.value"
            :total="queue.total.value"
            :total-pages="queue.totalPages.value"
            :sort-key="queue.sortKey.value"
            :sort-dir="queue.sortDir.value"
            empty-message="No disputes in this queue."
            @sort="queue.setSort"
            @page="(p) => (queue.page.value = p)"
            @open="openDetail"
        >
            <template #cell-reference="{ row }">
                <span class="font-black text-slate-950">{{ row.reference }}</span>
            </template>
            <template #cell-contract="{ row }">
                <a
                    v-if="row.contract?.url"
                    :href="row.contract.url"
                    class="font-semibold text-primary-700 underline underline-offset-2"
                    @click.stop
                >{{ row.contract.reference_code }}</a>
                <span v-else class="font-semibold text-slate-900">{{ row.contract_reference || row.quest_reference }}</span>
                <span class="block text-xs text-slate-500">{{ row.quest }}</span>
            </template>
            <template #cell-type="{ row }">
                <span class="text-xs font-semibold text-slate-700">{{ row.category_label || '—' }}</span>
            </template>
            <template #cell-value="{ row }">
                <span class="font-bold text-slate-900">{{ formatMinor(row.disputed_amount_minor) }}</span>
            </template>
            <template #cell-status="{ row }">
                <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-black uppercase" :class="statusClass(row.management_badge_tone)">
                    {{ row.management_status_label }}
                </span>
                <span v-if="row.needs_staff_action" class="mt-1 block text-[10px] font-black uppercase text-violet-700">Super Admin request</span>
            </template>
            <template #cell-days="{ row }">
                <span class="font-semibold text-slate-700">{{ row.days_open }}d</span>
            </template>
            <template #actions="{ row }">
                <button type="button" class="rounded-lg bg-primary-700 px-2 py-1 text-[10px] font-black uppercase text-white" @click.stop="openDetail(row)">Open</button>
            </template>
        </OperationsQueueTable>

        <OperationsSlideOver :open="slideOpen" :title="slideTitle" :subtitle="detail?.dispute?.reference" eyebrow="Staff dispute workspace" @close="slideOpen = false">
            <div v-if="detailLoading" class="py-10 text-center text-sm text-slate-500">Loading…</div>
            <OperationsDisputeWorkspace
                v-else-if="detail"
                :detail="detail"
                :busy="busy"
                @acknowledge="acknowledge"
                @checklist="updateChecklist"
                @checklist-auto="autoChecklist"
                @evidence-reviewed="markEvidenceReviewed"
                @note="onNote"
                @evidence="onEvidence"
                @contact="onContact"
                @ready="markReady"
                @respond-guidance="respondGuidance"
                @approve-mutual="approveMutual"
            >
                <template #assessment>
                    <div class="mt-3 space-y-3">
                        <div class="flex items-center justify-between gap-2">
                            <span v-if="assessmentForm.status === 'submitted'" class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-black uppercase text-emerald-800">Submitted — awaiting Super Admin</span>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="block text-xs font-bold text-slate-600">
                                Quality rating
                                <select v-model.number="assessmentForm.quality_rating" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-semibold">
                                    <option :value="null">—</option>
                                    <option v-for="n in 5" :key="n" :value="n">{{ n }} / 5</option>
                                </select>
                            </label>
                            <OperationsFormField v-model="assessmentTimeSpent" label="Time spent (minutes)" type="number" />
                        </div>
                        <div class="space-y-2">
                            <p class="text-[10px] font-black uppercase text-slate-500">Investigation checklist</p>
                            <label v-for="item in detail.investigation_checklist_options" :key="item.key" class="flex items-center gap-2 text-sm font-semibold">
                                <input v-model="assessmentForm.investigation_checklist" type="checkbox" :value="item.key" class="rounded border-slate-300 text-primary-600" />
                                {{ item.label }}
                            </label>
                        </div>
                        <OperationsFormField v-model="assessmentForm.violation_status" label="Violation status" placeholder="e.g. partial_violation" />
                        <OperationsFormField v-model="findingsText" label="Key findings" hint="One per line." multiline :rows="3" />
                        <label class="block text-xs font-bold text-slate-600">
                            Recommendation
                            <select v-model="assessmentForm.recommendation" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-semibold">
                                <option value="">Select…</option>
                                <option v-for="opt in detail.recommendation_options" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                        </label>
                        <OperationsFormField v-if="assessmentForm.recommendation === 'partial_award'" v-model="assessmentClientShare" label="Client share %" type="number" />
                        <div v-if="staffAlternateOptions.length" class="space-y-2 rounded-xl border border-slate-100 bg-slate-50/80 p-3">
                            <p class="text-xs font-bold text-slate-700">Other fair options for Super Admin to review</p>
                            <label
                                v-for="opt in staffAlternateOptions"
                                :key="opt.value"
                                class="flex cursor-pointer items-start gap-2 text-xs font-semibold text-slate-700"
                            >
                                <input
                                    v-model="assessmentForm.alternate_recommendations"
                                    type="checkbox"
                                    class="mt-0.5 rounded border-slate-300 text-primary-600"
                                    :value="opt.value"
                                />
                                <span>{{ opt.label }}</span>
                            </label>
                        </div>
                        <div v-if="assessmentForm.recommendation && detail.dispute.disputed_amount_minor" class="rounded-xl border border-emerald-200 bg-emerald-50/70 p-3 text-xs font-bold text-emerald-950">
                            <p>Client payout: {{ formatMinor(payoutPreview.client) }}</p>
                            <p>Freelancer payout: {{ formatMinor(payoutPreview.freelancer) }}</p>
                        </div>
                        <label class="block text-xs font-bold text-slate-600">
                            Recommended sanction
                            <select v-model="assessmentForm.recommended_sanction" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-semibold">
                                <option value="">None</option>
                                <option v-for="opt in detail.sanction_options || []" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                        </label>
                        <OperationsFormField v-model="assessmentForm.reasoning" label="Reasoning" multiline :rows="4" />
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-black uppercase" :disabled="busy.assessment" @click="saveAssessment(false)">Save draft</button>
                            <button type="button" class="rounded-xl bg-primary-700 px-4 py-2 text-xs font-black uppercase text-white" :disabled="busy.assessment" @click="saveAssessment(true)">Submit assessment</button>
                        </div>
                    </div>
                </template>
            </OperationsDisputeWorkspace>
        </OperationsSlideOver>
    </OperationsShell>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import OperationsDisputeWorkspace from '@/Pages/Operations/Components/OperationsDisputeWorkspace.vue';
import OperationsFormField from '@/Pages/Operations/Components/OperationsFormField.vue';
import OperationsQueueTable from '@/Pages/Operations/Components/OperationsQueueTable.vue';
import OperationsSlideOver from '@/Pages/Operations/Components/OperationsSlideOver.vue';
import OperationsShell from '@/Layouts/OperationsShell.vue';
import { useClientQueue } from '@/composables/useClientQueue';
import { useOperationsAction } from '@/composables/useOperationsAction';

const props = defineProps({
    queue_summary: { type: Object, default: () => ({ assigned_total: 0, pending_action: 0, filters: [], sorts: [] }) },
});

const queueSummary = ref({ ...props.queue_summary });

const columns = [
    { key: 'reference', label: 'DSP-ID' },
    { key: 'contract', label: 'Contract' },
    { key: 'type', label: 'Type' },
    { key: 'value', label: 'Value' },
    { key: 'status', label: 'Status' },
    { key: 'days', label: 'Days' },
];

const rawItems = ref([]);
const loading = ref(false);
const activeFilter = ref('all');
const activeSort = ref('newest');
const searchQuery = ref('');
const slideOpen = ref(false);
const detailLoading = ref(false);
const detail = ref(null);
const selectedRow = ref(null);
const findingsText = ref('');

const contactForm = reactive({ party: 'client', channel: 'both', subject: '', body: '' });
const evidenceForm = reactive({ body: '', audience: 'both' });
const noteForm = reactive({ body: '' });
const assessmentForm = reactive({
    quality_rating: null,
    investigation_checklist: [],
    violation_status: '',
    recommendation: '',
    recommended_client_share_percent: 50,
    recommended_sanction: '',
    alternate_recommendations: [],
    reasoning: '',
    time_spent_minutes: null,
    status: 'draft',
});

const queue = useClientQueue(() => rawItems.value);
const { busy, runAction, toast } = useOperationsAction();
const slideTitle = computed(() => detail.value?.dispute?.quest || 'Dispute');

const assessmentTimeSpent = computed({
    get: () => (assessmentForm.time_spent_minutes == null ? '' : String(assessmentForm.time_spent_minutes)),
    set: (value) => {
        assessmentForm.time_spent_minutes = value === '' ? null : Number(value);
    },
});

const assessmentClientShare = computed({
    get: () => String(assessmentForm.recommended_client_share_percent ?? ''),
    set: (value) => {
        assessmentForm.recommended_client_share_percent = value === '' ? null : Number(value);
    },
});

const staffAlternateOptions = computed(() =>
    (detail.value?.resolution_options ?? []).filter((opt) => opt.value !== assessmentForm.recommendation),
);

const payoutPreview = computed(() => {
    const held = Number(detail.value?.dispute?.disputed_amount_minor || 0);
    let clientPercent = Number(assessmentForm.recommended_client_share_percent ?? 50);
    if (assessmentForm.recommendation === 'award_client_full') clientPercent = 100;
    if (assessmentForm.recommendation === 'award_freelancer_full') clientPercent = 0;
    clientPercent = Math.max(0, Math.min(100, clientPercent));
    const client = Math.round(held * (clientPercent / 100));
    return { client, freelancer: Math.max(0, held - client) };
});

const quickStats = computed(() => {
    if (!detail.value?.dispute) return [];
    const d = detail.value.dispute;
    return [
        { label: 'Status', value: d.management_status_label },
        { label: 'Severity', value: d.severity },
        { label: 'Value', value: formatMinor(d.disputed_amount_minor) },
    ];
});

const quickChips = computed(() => [
    { label: 'Client', value: detail.value?.parties?.client?.name },
    { label: 'Freelancer', value: detail.value?.parties?.freelancer?.name },
    { label: 'Filed by', value: detail.value?.parties?.filed_by_party },
]);

onMounted(() => openFromQuery());

watch(findingsText, (text) => {
    assessmentForm.key_findings = text.split('\n').map((line) => line.trim()).filter(Boolean);
});

function disputeKey(row) {
    return row.uuid || row.id;
}

function setFilter(key) {
    activeFilter.value = key;
    reload();
}

async function openFromQuery() {
    await reload();

    const params = new URLSearchParams(window.location.search);
    const token = params.get('q') || params.get('dispute');
    if (!token) {
        return;
    }

    searchQuery.value = token;
    await reload();

    const row = rawItems.value.find(
        (item) => item.uuid === token || String(item.id) === token || item.reference === token,
    );

    if (row) {
        await openDetail(row);
        return;
    }

    try {
        const { data } = await window.axios.get(route('operations.api.disputes.detail', token));
        selectedRow.value = { uuid: token, quest: data.dispute?.quest, reference: data.dispute?.reference };
        detail.value = data;
        slideOpen.value = true;
        hydrateAssessment(data);
    } catch {
        // Ignore invalid deep links.
    }
}

async function respondGuidance(body) {
    await runAction('guidance', () => window.axios.post(route('operations.api.disputes.respond_guidance', disputeKey(selectedRow.value)), { body }), 'Response sent to Super Admin.', () => openDetail(selectedRow.value));
}

async function reload() {
    loading.value = true;
    try {
        const { data } = await window.axios.get(route('operations.api.disputes.listing'), {
            params: { filter: activeFilter.value, sort: activeSort.value, q: searchQuery.value },
        });
        rawItems.value = data.items ?? [];
        if (data.queue_summary) {
            queueSummary.value = data.queue_summary;
        }
    } finally {
        loading.value = false;
    }
}

function hydrateAssessment(payload) {
    const current = payload?.current_assessment;
    if (!current) return;
    assessmentForm.quality_rating = current.quality_rating;
    assessmentForm.investigation_checklist = [...(current.investigation_checklist ?? [])];
    assessmentForm.violation_status = current.violation_status ?? '';
    assessmentForm.recommendation = current.recommendation ?? '';
    assessmentForm.recommended_client_share_percent = current.recommended_client_share_percent ?? 50;
    assessmentForm.recommended_sanction = current.recommended_sanction ?? '';
    assessmentForm.alternate_recommendations = [...(current.alternate_recommendations ?? [])];
    assessmentForm.reasoning = current.reasoning ?? '';
    assessmentForm.time_spent_minutes = current.time_spent_minutes;
    assessmentForm.status = current.status ?? 'draft';
    findingsText.value = (current.key_findings ?? []).join('\n');
}

async function openDetail(row) {
    selectedRow.value = row;
    slideOpen.value = true;
    detailLoading.value = true;
    detail.value = null;

    try {
        const { data } = await window.axios.get(route('operations.api.disputes.detail', disputeKey(row)));
        detail.value = data;
        hydrateAssessment(data);
    } catch (error) {
        slideOpen.value = false;
        toast(error?.response?.data?.message || 'Failed to load dispute.', 'error');
    } finally {
        detailLoading.value = false;
    }
}

async function acknowledge() {
    await runAction('acknowledge', () => window.axios.post(route('operations.api.disputes.acknowledge', disputeKey(selectedRow.value))), 'Dispute acknowledged.', () => openDetail(selectedRow.value));
}

async function updateChecklist(completed) {
    await runAction('checklist', () => window.axios.post(route('operations.api.disputes.checklist', disputeKey(selectedRow.value)), { completed }), null, async (res) => {
        if (detail.value && res?.data?.workflow) detail.value.workflow = res.data.workflow;
    });
}

async function autoChecklist(key) {
    const completed = [...(detail.value?.workflow?.completed ?? []), key];
    await updateChecklist(completed);
}

async function markEvidenceReviewed(payload) {
    await runAction('evidenceReview', () => window.axios.post(route('operations.api.disputes.evidence_reviewed', disputeKey(selectedRow.value)), payload), 'Evidence marked reviewed.', () => openDetail(selectedRow.value));
}

function onNote(body) {
    noteForm.body = body;
    note();
}

function onEvidence(payload) {
    evidenceForm.body = payload.body;
    evidenceForm.audience = payload.audience || 'both';
    evidence();
}

function onContact(payload) {
    Object.assign(contactForm, payload);
    contact();
}

async function contact() {
    await runAction('contact', () => window.axios.post(route('operations.api.disputes.contact', disputeKey(selectedRow.value)), contactForm), 'Message sent.');
}

async function evidence() {
    await runAction('evidence', () => window.axios.post(route('operations.api.disputes.evidence', disputeKey(selectedRow.value)), evidenceForm), 'Evidence requested.', () => openDetail(selectedRow.value));
}

async function note() {
    await runAction('note', () => window.axios.post(route('operations.api.disputes.notes', disputeKey(selectedRow.value)), noteForm), 'Note saved.');
}

async function saveAssessment(submit) {
    await runAction('assessment', () => window.axios.post(route('operations.api.disputes.assessment', disputeKey(selectedRow.value)), { ...assessmentForm, submit }), submit ? 'Assessment submitted.' : 'Draft saved.', () => openDetail(selectedRow.value));
}

async function markReady() {
    await runAction('ready', () => window.axios.post(route('operations.api.disputes.ready_for_decision', disputeKey(selectedRow.value))), 'Sent to Super Admin.', async () => {
        slideOpen.value = false;
        await reload();
    });
}

async function approveMutual() {
    await runAction('approveMutual', () => window.axios.post(route('operations.api.disputes.approve_mutual', disputeKey(selectedRow.value))), 'Mutual agreement approved.', () => openDetail(selectedRow.value));
}

function formatMinor(minor) {
    if (!minor) return '—';
    return `₦${(Number(minor) / 100).toLocaleString()}`;
}

function formatWhen(iso) {
    if (!iso) return '—';
    try {
        return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso));
    } catch {
        return iso;
    }
}

function formatTimelineKey(key) {
    return String(key).replaceAll('_', ' ');
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
</script>
