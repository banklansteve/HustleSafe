<template>
    <OperationsShell title="Review & rating moderation" subtitle="Authenticity engine, amendment workflow, appeals, and manipulation intelligence.">
        <div class="mb-4 flex gap-2 overflow-x-auto pb-1">
            <button
                v-for="queue in queues"
                :key="queue.key"
                type="button"
                class="shrink-0 rounded-2xl px-4 py-2 text-left"
                :class="activeQueue === queue.key ? 'bg-primary-700 text-white shadow-md' : 'border border-slate-200 bg-white text-slate-700 hover:bg-primary-50'"
                @click="loadQueue(queue)"
            >
                <span class="block text-xs font-black uppercase tracking-wide">{{ queue.label }}</span>
                <span class="mt-0.5 block text-[11px] font-semibold opacity-80">{{ queue.hint }}</span>
            </button>
        </div>

        <section v-if="activeQueue === 'manipulation'" class="space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm font-semibold text-slate-600">{{ manipulationHint }}</p>
                <div v-if="canExportManipulation" class="flex gap-2">
                    <a
                        :href="route('operations.api.reviews.manipulation.export', 'freelancer_concentration')"
                        class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-[10px] font-black uppercase text-slate-700"
                    >Export freelancers CSV</a>
                    <a
                        :href="route('operations.api.reviews.manipulation.export', 'client_pattern')"
                        class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-[10px] font-black uppercase text-slate-700"
                    >Export clients CSV</a>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="text-xs font-black uppercase tracking-wide text-slate-500">Freelancer concentration</h3>
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="text-[10px] font-black uppercase text-slate-500">
                            <tr>
                                <th class="py-2 pr-4">Freelancer</th>
                                <th class="py-2 pr-4">Suspicious %</th>
                                <th class="py-2 pr-4">Reviews</th>
                                <th class="py-2">Tier</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in freelancerRows"
                                :key="row.freelancer_id"
                                class="cursor-pointer border-t border-slate-100 hover:bg-primary-50/40"
                                @click="openFreelancerBreakdown(row)"
                            >
                                <td class="py-2 pr-4 font-semibold text-slate-900">{{ row.freelancer_name }}</td>
                                <td class="py-2 pr-4">{{ row.concentration_pct }}%</td>
                                <td class="py-2 pr-4">{{ row.suspicious_count }} / {{ row.total_reviews }}</td>
                                <td class="py-2">
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-black uppercase" :class="tierClass(row.risk_tier)">{{ row.risk_tier }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="text-xs font-black uppercase tracking-wide text-slate-500">Client low-rating patterns (60d)</h3>
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="text-[10px] font-black uppercase text-slate-500">
                            <tr>
                                <th class="py-2 pr-4">Client</th>
                                <th class="py-2 pr-4">Low ratings</th>
                                <th class="py-2 pr-4">Freelancers</th>
                                <th class="py-2 pr-4">Disputes</th>
                                <th class="py-2">No dispute</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in clientRows" :key="row.client_id" class="border-t border-slate-100">
                                <td class="py-2 pr-4 font-semibold text-slate-900">{{ row.client_name }}</td>
                                <td class="py-2 pr-4">{{ row.low_rating_count }} ({{ row.low_rating_ratio_pct }}%)</td>
                                <td class="py-2 pr-4">{{ row.distinct_freelancers_low }}</td>
                                <td class="py-2 pr-4">{{ row.disputes_filed }}</td>
                                <td class="py-2 font-semibold text-rose-700">{{ row.no_dispute_low_ratings }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <OperationsQueueTable
            v-else
            :columns="tableColumns"
            :rows="queue.pageItems.value"
            :loading="loading"
            v-model:search="queue.search.value"
            v-model:per-page="queue.perPage.value"
            :page="queue.page.value"
            :total="queue.total.value"
            :total-pages="queue.totalPages.value"
            :sort-key="queue.sortKey.value"
            :sort-dir="queue.sortDir.value"
            empty-message="No reviews in this queue."
            @sort="queue.setSort"
            @page="(p) => (queue.page.value = p)"
            @open="openRow"
        >
            <template #cell-title="{ row }">
                <span class="font-semibold text-slate-950">{{ row.title || row.cluster_type || 'Untitled' }}</span>
                <span class="mt-0.5 block text-xs text-slate-500">
                    <template v-if="row.is_cluster">Cluster · {{ row.cluster_type }}</template>
                    <template v-else>{{ row.reviewer }} → {{ row.reviewee }}</template>
                </span>
            </template>
            <template #cell-status="{ row }">
                <span class="rounded-full bg-primary-50 px-2 py-1 text-[10px] font-black uppercase text-primary-800">{{ row.status || row.authenticity_flag }}</span>
            </template>
            <template #actions="{ row }">
                <button type="button" class="rounded-lg bg-primary-700 px-2 py-1 text-[10px] font-black uppercase text-white" @click.stop="openRow(row)">Open</button>
            </template>
        </OperationsQueueTable>

        <OperationsSlideOver :open="slideOpen" :title="slideTitle" subtitle="Review actions" eyebrow="Reviews" @close="slideOpen = false">
            <div v-if="detailLoading" class="py-10 text-center text-sm font-semibold text-slate-500">Loading…</div>
            <div v-else-if="clusterDetail" class="space-y-4">
                <p class="text-xs font-black uppercase text-primary-800">{{ clusterDetail.cluster.cluster_type }} cluster</p>
                <article v-for="r in clusterDetail.reviews" :key="r.id" class="rounded-2xl border border-slate-100 bg-slate-50 p-3">
                    <p class="text-sm font-bold text-slate-900">{{ r.reviewer }} → {{ r.reviewee }}</p>
                    <p class="text-xs text-slate-600">{{ r.quest }} · {{ r.rating }}★</p>
                    <button type="button" class="mt-2 text-[10px] font-black uppercase text-primary-700" @click="openReviewById(r.id)">Open review</button>
                </article>
            </div>
            <div v-else-if="detail" class="space-y-4">
                <OperationsContextStats
                    heading="Review snapshot"
                    :stats="[
                        { label: 'Rating', value: `${detail.review.rating || '—'}/5` },
                        { label: 'Status', value: detail.review.status },
                        { label: 'Authenticity', value: detail.review.authenticity_flag || '—' },
                        { label: 'Quality', value: detail.review.quality_score ?? '—' },
                    ]"
                    :chips="[
                        { label: 'Reviewer', value: detail.review.reviewer },
                        { label: 'Reviewee', value: detail.review.reviewee },
                        ...(detail.review.is_brief ? [{ label: 'Brief', value: '40% weight' }] : []),
                        ...(detail.review.subnet_collision ? [{ label: 'Subnet', value: detail.review.reviewer_subnet || 'collision' }] : []),
                    ]"
                />
                <p v-if="detail.review.sentiment_score != null" class="rounded-xl border border-amber-100 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-900">
                    Sentiment {{ detail.review.sentiment_score }} — check signals for polarity mismatch.
                </p>
                <ul v-if="detail.review.signals?.length" class="space-y-2">
                    <li v-for="(sig, i) in detail.review.signals" :key="i" class="rounded-xl border border-rose-100 bg-rose-50/50 px-3 py-2 text-xs font-semibold text-rose-900">
                        {{ sig.label || sig.type }}
                    </li>
                </ul>
                <p class="rounded-2xl border border-slate-100 bg-slate-50 p-4 text-sm font-semibold text-slate-800">{{ detail.review.comment_full }}</p>

                <section v-if="detail.reciprocal_counterpart" class="rounded-2xl border border-violet-100 bg-violet-50/50 p-4">
                    <p class="text-xs font-black uppercase text-violet-900">Reciprocal counterpart</p>
                    <p class="mt-2 text-sm font-semibold">{{ detail.reciprocal_counterpart.reviewer }} → {{ detail.reciprocal_counterpart.reviewee }} · {{ detail.reciprocal_counterpart.rating }}★</p>
                </section>

                <OperationsExpandableAction title="Issue amendment request" hint="In-app prompt — 48h to update; held from ratings until resolved." icon="✎" tone="amber" submit-label="Send amendment" :busy="busy.amendment" @submit="requestAmendment">
                    <textarea v-model="amendmentForm.instructions" class="form-input min-h-24" placeholder="Tell the reviewer exactly what to add or remove" />
                </OperationsExpandableAction>

                <OperationsExpandableAction title="Approve with edits" hint="Publish with optional title or comment changes." icon="✓" submit-label="Approve" :busy="busy.approve" @submit="approveReview">
                    <input v-model="approveForm.title" class="form-input" placeholder="Title (optional)" />
                    <textarea v-model="approveForm.comment" class="form-input mt-3 min-h-24" placeholder="Comment (optional)" />
                    <input v-model="approveForm.reason" class="form-input mt-3" placeholder="Internal note (optional)" />
                </OperationsExpandableAction>

                <OperationsExpandableAction title="Request revision" hint="Legacy revision flow." icon="↺" tone="amber" submit-label="Request revision" :busy="busy.revision" @submit="requestRevision">
                    <textarea v-model="revisionForm.reason" class="form-input min-h-24" placeholder="What should the reviewer change?" />
                </OperationsExpandableAction>

                <OperationsExpandableAction title="Remove review" hint="Requires a clear reason — logged immutably." icon="✕" tone="rose" submit-label="Remove" :busy="busy.remove" @submit="removeReview">
                    <textarea v-model="removeForm.reason" class="form-input min-h-24" placeholder="Removal reason" />
                </OperationsExpandableAction>

                <OperationsExpandableAction title="Flag for investigation" hint="Opens or updates a moderation case." icon="🚩" tone="rose" submit-label="Flag" :busy="busy.flag" @submit="flagReview">
                    <textarea v-model="flagForm.description" class="form-input min-h-24" placeholder="Describe the concern" />
                    <select v-model="flagForm.priority" class="form-input mt-3">
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="critical">Critical</option>
                    </select>
                </OperationsExpandableAction>

                <section v-if="detail.moderation_case?.appeals?.length" class="rounded-2xl border border-amber-100 bg-amber-50/60 p-4">
                    <p class="text-xs font-black uppercase tracking-wide text-amber-900">Open appeals</p>
                    <article v-for="appeal in detail.moderation_case.appeals" :key="appeal.id" class="mt-3 rounded-xl border border-amber-100 bg-white p-3">
                        <p class="text-sm font-semibold text-slate-800">{{ appeal.statement }}</p>
                        <select v-model="appealForms[appeal.id].outcome" class="form-input mt-2">
                            <option value="uphold">Uphold removal</option>
                            <option value="overturn">Overturn · republish</option>
                        </select>
                        <textarea v-model="appealForms[appeal.id].note" class="form-input mt-2 min-h-20" placeholder="Decision note" />
                        <button type="button" class="mt-2 rounded-xl bg-primary-700 px-3 py-2 text-xs font-black text-white" :disabled="busy[`appeal-${appeal.id}`]" @click="resolveAppeal(appeal)">Resolve appeal</button>
                    </article>
                </section>
            </div>
        </OperationsSlideOver>
    </OperationsShell>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import OperationsContextStats from '@/Pages/Operations/Components/OperationsContextStats.vue';
import OperationsExpandableAction from '@/Pages/Operations/Components/OperationsExpandableAction.vue';
import OperationsQueueTable from '@/Pages/Operations/Components/OperationsQueueTable.vue';
import OperationsSlideOver from '@/Pages/Operations/Components/OperationsSlideOver.vue';
import OperationsShell from '@/Layouts/OperationsShell.vue';
import { useClientQueue } from '@/composables/useClientQueue';
import { useOperationsAction } from '@/composables/useOperationsAction';

const props = defineProps({
    queues: { type: Array, default: () => [] },
    canExportManipulation: { type: Boolean, default: false },
});

const columns = [
    { key: 'title', label: 'Review' },
    { key: 'rating', label: 'Rating' },
    { key: 'status', label: 'Status' },
    { key: 'created_at', label: 'Submitted' },
];

const rawItems = ref([]);
const manipulation = ref(null);
const loading = ref(false);
const activeQueue = ref('authenticity');
const slideOpen = ref(false);
const detailLoading = ref(false);
const detail = ref(null);
const clusterDetail = ref(null);
const selectedRow = ref(null);

const approveForm = reactive({ title: '', comment: '', reason: '' });
const revisionForm = reactive({ reason: '' });
const removeForm = reactive({ reason: '' });
const flagForm = reactive({ description: '', priority: 'medium' });
const amendmentForm = reactive({ instructions: '' });
const appealForms = reactive({});

const queue = useClientQueue(() => rawItems.value, { searchFields: ['title', 'comment', 'reviewer', 'reviewee', 'status', 'cluster_type'] });
const { busy, runAction } = useOperationsAction();

const tableColumns = computed(() => (activeQueue.value === 'clusters'
    ? [{ key: 'title', label: 'Cluster' }, { key: 'status', label: 'Status' }, { key: 'created_at', label: 'Detected' }]
    : columns));

const slideTitle = computed(() => detail.value?.review?.title || clusterDetail.value?.cluster?.cluster_type || selectedRow.value?.title || 'Review');
const manipulationHint = computed(() => manipulation.value?.generated_hint || '');
const freelancerRows = computed(() => manipulation.value?.freelancer_concentration?.rows ?? []);
const clientRows = computed(() => manipulation.value?.client_pattern?.rows ?? []);

onMounted(() => {
    const preferred = props.queues.find((q) => q.key === 'authenticity') || props.queues[0];
    if (preferred) {
        loadQueue(preferred);
    }
});

watch(detail, (value) => {
    if (!value?.moderation_case?.appeals) {
        return;
    }
    value.moderation_case.appeals.forEach((appeal) => {
        appealForms[appeal.id] = appealForms[appeal.id] || { outcome: 'uphold', note: '' };
    });
});

function tierClass(tier) {
    if (tier === 'red') return 'bg-rose-100 text-rose-800';
    if (tier === 'amber') return 'bg-amber-100 text-amber-900';
    return 'bg-emerald-100 text-emerald-800';
}

async function loadQueue(queueDef) {
    activeQueue.value = queueDef.key;
    loading.value = true;
    rawItems.value = [];
    manipulation.value = null;
    try {
        const { data } = await window.axios.get(route('operations.api.reviews.listing'), { params: { queue: queueDef.key } });
        if (queueDef.key === 'manipulation') {
            manipulation.value = data;
        } else {
            rawItems.value = data.items ?? [];
        }
    } finally {
        loading.value = false;
    }
}

function openRow(row) {
    if (row.is_cluster && row.cluster_id) {
        openCluster(row.cluster_id);
        return;
    }
    openDetail(row);
}

async function openCluster(clusterId) {
    selectedRow.value = { cluster_id: clusterId };
    slideOpen.value = true;
    detailLoading.value = true;
    detail.value = null;
    clusterDetail.value = null;
    try {
        const { data } = await window.axios.get(route('operations.api.reviews.clusters.detail', clusterId));
        clusterDetail.value = data;
    } finally {
        detailLoading.value = false;
    }
}

async function openReviewById(id) {
    clusterDetail.value = null;
    await openDetail({ id });
}

async function openDetail(row) {
    selectedRow.value = row;
    slideOpen.value = true;
    detailLoading.value = true;
    detail.value = null;
    clusterDetail.value = null;
    try {
        const { data } = await window.axios.get(route('operations.api.reviews.detail', row.id));
        detail.value = data;
        approveForm.title = data.review?.title ?? '';
        approveForm.comment = data.review?.comment_full ?? '';
    } finally {
        detailLoading.value = false;
    }
}

async function openFreelancerBreakdown(row) {
    const { data } = await window.axios.get(route('operations.api.reviews.manipulation.breakdown', row.freelancer_id));
    rawItems.value = (data.reviews ?? []).map((r) => ({
        id: r.id,
        title: r.quest,
        rating: r.rating,
        reviewer: r.reviewer?.name,
        reviewee: row.freelancer_name,
        status: 'published',
    }));
    activeQueue.value = 'manipulation';
    slideOpen.value = false;
}

async function approveReview() {
    if (!selectedRow.value?.id) return;
    await runAction('approve', () => window.axios.post(route('operations.api.reviews.approve', selectedRow.value.id), { ...approveForm }), 'Review approved.', async () => {
        slideOpen.value = false;
        await loadQueue(props.queues.find((q) => q.key === activeQueue.value) || { key: activeQueue.value });
    });
}

async function requestAmendment() {
    if (!selectedRow.value?.id) return;
    await runAction('amendment', () => window.axios.post(route('operations.api.reviews.amendment', selectedRow.value.id), { ...amendmentForm }), 'Amendment sent.', () => openDetail(selectedRow.value));
}

async function requestRevision() {
    if (!selectedRow.value?.id) return;
    await runAction('revision', () => window.axios.post(route('operations.api.reviews.revision', selectedRow.value.id), { ...revisionForm }), 'Revision requested.', () => openDetail(selectedRow.value));
}

async function removeReview() {
    if (!selectedRow.value?.id) return;
    await runAction('remove', () => window.axios.post(route('operations.api.reviews.remove', selectedRow.value.id), { ...removeForm }), 'Review removed.', async () => {
        slideOpen.value = false;
        await loadQueue(props.queues.find((q) => q.key === activeQueue.value) || { key: activeQueue.value });
    });
}

async function flagReview() {
    if (!selectedRow.value?.id) return;
    await runAction('flag', () => window.axios.post(route('operations.api.reviews.flag', selectedRow.value.id), { ...flagForm }), 'Review flagged.', () => openDetail(selectedRow.value));
}

async function resolveAppeal(appeal) {
    const form = appealForms[appeal.id];
    await runAction(`appeal-${appeal.id}`, () => window.axios.post(route('operations.api.reviews.appeals.resolve', appeal.id), form), 'Appeal resolved.', () => openDetail(selectedRow.value));
}
</script>
