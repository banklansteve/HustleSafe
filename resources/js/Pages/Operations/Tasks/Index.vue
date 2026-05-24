<template>
    <OperationsShell title="My tasks" subtitle="Assigned work, overdue items, and deep links to source records.">
        <section class="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <button
                v-for="tile in summary"
                :key="tile.key"
                type="button"
                class="rounded-2xl border border-slate-100 bg-white p-4 text-left shadow-sm ring-1 ring-slate-100 hover:bg-primary-50/40"
                @click="setQuick(tile.key)"
            >
                <p class="text-[10px] font-black uppercase tracking-wide text-slate-500">{{ tile.label }}</p>
                <p class="mt-2 text-3xl font-black text-slate-950">{{ tile.value }}</p>
                <p class="mt-1 text-xs font-semibold text-slate-600">{{ tile.hint }}</p>
            </button>
        </section>

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
            empty-message="No tasks assigned to you."
            @sort="queue.setSort"
            @page="(p) => (queue.page.value = p)"
            @open="openDetail"
        >
            <template #cell-title="{ row }">
                <span class="font-semibold text-slate-950">{{ row.title }}</span>
                <span v-if="row.overdue" class="ml-2 rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-black uppercase text-rose-800">Overdue</span>
            </template>
            <template #actions="{ row }">
                <button type="button" class="rounded-lg bg-primary-700 px-2 py-1 text-[10px] font-black uppercase text-white" @click.stop="openDetail(row)">Open</button>
            </template>
        </OperationsQueueTable>

        <OperationsSlideOver :open="slideOpen" :title="slideTitle" subtitle="Task detail" eyebrow="Workload" @close="slideOpen = false">
            <div v-if="detailLoading" class="py-10 text-center text-sm text-slate-500">Loading…</div>
            <div v-else-if="taskDetail" class="space-y-4">
                <p class="text-sm font-semibold text-slate-700">{{ taskDetail.task.description_full || taskDetail.task.description }}</p>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-black" :disabled="busy.status" @click="setStatus('in_progress')">Start</button>
                    <button type="button" class="rounded-xl bg-primary-700 px-4 py-2 text-sm font-black text-white" :disabled="busy.status" @click="setStatus('done')">Complete</button>
                    <a v-if="taskDetail.source_url" :href="taskDetail.source_url" class="rounded-xl border border-primary-200 bg-primary-50 px-4 py-2 text-sm font-black text-primary-900">Open source</a>
                </div>
            </div>
        </OperationsSlideOver>
    </OperationsShell>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import OperationsQueueTable from '@/Pages/Operations/Components/OperationsQueueTable.vue';
import OperationsSlideOver from '@/Pages/Operations/Components/OperationsSlideOver.vue';
import OperationsShell from '@/Layouts/OperationsShell.vue';
import { useClientQueue } from '@/composables/useClientQueue';
import { useOperationsAction } from '@/composables/useOperationsAction';

const props = defineProps({ summary: { type: Array, default: () => [] } });

const columns = [
    { key: 'title', label: 'Task' },
    { key: 'priority', label: 'Priority' },
    { key: 'status', label: 'Status' },
    { key: 'due_at', label: 'Due' },
];

const rawItems = ref([]);
const loading = ref(false);
const quick = ref('');
const slideOpen = ref(false);
const detailLoading = ref(false);
const taskDetail = ref(null);
const selectedRow = ref(null);

const queue = useClientQueue(() => rawItems.value, { searchFields: ['title', 'description', 'priority', 'status'] });
const { busy, runAction } = useOperationsAction();
const slideTitle = computed(() => taskDetail.value?.task?.title || selectedRow.value?.title || 'Task');

onMounted(reload);

function setQuick(key) {
    quick.value = key === quick.value ? '' : key;
    reload();
}

async function reload() {
    loading.value = true;
    try {
        const { data } = await window.axios.get(route('operations.api.tasks.listing'), { params: { quick: quick.value } });
        rawItems.value = data.items ?? [];
    } finally {
        loading.value = false;
    }
}

async function openDetail(row) {
    selectedRow.value = row;
    slideOpen.value = true;
    detailLoading.value = true;
    try {
        const { data } = await window.axios.get(route('operations.api.tasks.detail', row.id));
        taskDetail.value = data;
    } finally {
        detailLoading.value = false;
    }
}

async function setStatus(status) {
    if (!selectedRow.value) return;
    await runAction('status', () => window.axios.patch(route('operations.tasks.status', selectedRow.value.id), { status }), 'Task updated.', async () => {
        slideOpen.value = false;
        await reload();
    });
}
</script>
