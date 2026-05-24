<template>
    <OperationsShell title="Review moderation" subtitle="Approve with edits, remove, request revision, flag, and resolve appeals.">
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
            empty-message="No reviews in this queue."
            @sort="queue.setSort"
            @page="(p) => (queue.page.value = p)"
            @open="openDetail"
        >
            <template #cell-title="{ row }">
                <span class="font-semibold text-slate-950">{{ row.title || 'Untitled review' }}</span>
                <span class="mt-0.5 block text-xs text-slate-500">{{ row.reviewer }} → {{ row.reviewee }}</span>
            </template>
            <template #cell-status="{ row }">
                <span class="rounded-full bg-primary-50 px-2 py-1 text-[10px] font-black uppercase text-primary-800">{{ row.status }}</span>
            </template>
            <template #actions="{ row }">
                <button type="button" class="rounded-lg bg-primary-700 px-2 py-1 text-[10px] font-black uppercase text-white" @click.stop="openDetail(row)">Open</button>
            </template>
        </OperationsQueueTable>

        <OperationsSlideOver :open="slideOpen" :title="slideTitle" subtitle="Review actions" eyebrow="Reviews" @close="slideOpen = false">
            <div v-if="detailLoading" class="py-10 text-center text-sm font-semibold text-slate-500">Loading…</div>
            <div v-else-if="detail" class="space-y-4">
                <OperationsContextStats
                    heading="Review snapshot"
                    :stats="[
                        { label: 'Rating', value: `${detail.review.rating || '—'}/5` },
                        { label: 'Status', value: detail.review.status },
                        { label: 'Quest', value: detail.review.quest || '—' },
                    ]"
                    :chips="[{ label: 'Reviewer', value: detail.review.reviewer }, { label: 'Reviewee', value: detail.review.reviewee }]"
                />
                <p class="rounded-2xl border border-slate-100 bg-slate-50 p-4 text-sm font-semibold text-slate-800">{{ detail.review.comment_full }}</p>

                <OperationsExpandableAction title="Approve with edits" hint="Publish with optional title or comment changes." icon="✓" submit-label="Approve" :busy="busy.approve" @submit="approveReview">
                    <input v-model="approveForm.title" class="form-input" placeholder="Title (optional)" />
                    <textarea v-model="approveForm.comment" class="form-input mt-3 min-h-24" placeholder="Comment (optional)" />
                    <input v-model="approveForm.reason" class="form-input mt-3" placeholder="Internal note (optional)" />
                </OperationsExpandableAction>

                <OperationsExpandableAction title="Request revision" hint="Send back to reviewer with instructions." icon="↺" tone="amber" submit-label="Request revision" :busy="busy.revision" @submit="requestRevision">
                    <textarea v-model="revisionForm.reason" class="form-input min-h-24" placeholder="What should the reviewer change?" />
                </OperationsExpandableAction>

                <OperationsExpandableAction title="Remove review" hint="Requires a clear reason." icon="✕" tone="rose" submit-label="Remove" :busy="busy.remove" @submit="removeReview">
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
});

const columns = [
    { key: 'title', label: 'Review' },
    { key: 'rating', label: 'Rating' },
    { key: 'status', label: 'Status' },
    { key: 'created_at', label: 'Submitted' },
];

const rawItems = ref([]);
const loading = ref(false);
const activeQueue = ref('pending');
const slideOpen = ref(false);
const detailLoading = ref(false);
const detail = ref(null);
const selectedRow = ref(null);

const approveForm = reactive({ title: '', comment: '', reason: '' });
const revisionForm = reactive({ reason: '' });
const removeForm = reactive({ reason: '' });
const flagForm = reactive({ description: '', priority: 'medium' });
const appealForms = reactive({});

const queue = useClientQueue(() => rawItems.value, { searchFields: ['title', 'comment', 'reviewer', 'reviewee', 'status'] });
const { busy, runAction } = useOperationsAction();

const slideTitle = computed(() => detail.value?.review?.title || selectedRow.value?.title || 'Review');

onMounted(() => {
    const first = props.queues[0];
    if (first) {
        loadQueue(first);
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

async function loadQueue(queueDef) {
    activeQueue.value = queueDef.key;
    loading.value = true;
    rawItems.value = [];
    try {
        const { data } = await window.axios.get(route('operations.api.reviews.listing'), { params: { queue: queueDef.key } });
        rawItems.value = data.items ?? [];
    } finally {
        loading.value = false;
    }
}

async function openDetail(row) {
    selectedRow.value = row;
    slideOpen.value = true;
    detailLoading.value = true;
    detail.value = null;
    try {
        const { data } = await window.axios.get(route('operations.api.reviews.detail', row.id));
        detail.value = data;
        approveForm.title = data.review?.title ?? '';
        approveForm.comment = data.review?.comment_full ?? '';
    } finally {
        detailLoading.value = false;
    }
}

async function approveReview() {
    if (!selectedRow.value) return;
    await runAction('approve', () => window.axios.post(route('operations.api.reviews.approve', selectedRow.value.id), { ...approveForm }), 'Review approved.', async () => {
        slideOpen.value = false;
        await loadQueue(props.queues.find((q) => q.key === activeQueue.value) || { key: activeQueue.value });
    });
}

async function requestRevision() {
    if (!selectedRow.value) return;
    await runAction('revision', () => window.axios.post(route('operations.api.reviews.revision', selectedRow.value.id), { ...revisionForm }), 'Revision requested.', () => openDetail(selectedRow.value));
}

async function removeReview() {
    if (!selectedRow.value) return;
    await runAction('remove', () => window.axios.post(route('operations.api.reviews.remove', selectedRow.value.id), { ...removeForm }), 'Review removed.', async () => {
        slideOpen.value = false;
        await loadQueue(props.queues.find((q) => q.key === activeQueue.value) || { key: activeQueue.value });
    });
}

async function flagReview() {
    if (!selectedRow.value) return;
    await runAction('flag', () => window.axios.post(route('operations.api.reviews.flag', selectedRow.value.id), { ...flagForm }), 'Review flagged.', () => openDetail(selectedRow.value));
}

async function resolveAppeal(appeal) {
    const form = appealForms[appeal.id];
    await runAction(`appeal-${appeal.id}`, () => window.axios.post(route('operations.api.reviews.appeals.resolve', appeal.id), form), 'Appeal resolved.', () => openDetail(selectedRow.value));
}
</script>
