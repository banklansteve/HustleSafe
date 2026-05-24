<template>
    <OperationsShell title="Trust & risk monitoring" subtitle="Personal watchlist and automatically detected risk clusters.">
        <div class="mb-4 flex gap-2">
            <button type="button" class="rounded-xl px-4 py-2 text-xs font-black uppercase" :class="tab === 'watchlist' ? 'bg-primary-700 text-white' : 'border border-slate-200 bg-white text-slate-700'" @click="tab = 'watchlist'">Watchlist</button>
            <button type="button" class="rounded-xl px-4 py-2 text-xs font-black uppercase" :class="tab === 'clusters' ? 'bg-primary-700 text-white' : 'border border-slate-200 bg-white text-slate-700'" @click="loadClusters">Risk clusters</button>
        </div>

        <OperationsQueueTable v-if="tab === 'watchlist'" :columns="watchColumns" :rows="queue.pageItems.value" :loading="loading" v-model:search="queue.search.value" v-model:per-page="queue.perPage.value" :page="queue.page.value" :total="queue.total.value" :total-pages="queue.totalPages.value" :sort-key="queue.sortKey.value" :sort-dir="queue.sortDir.value" empty-message="Watchlist is empty." @sort="queue.setSort" @page="(p) => (queue.page.value = p)" @open="openWatch">
            <template #cell-title="{ row }"><span class="font-semibold text-slate-950">{{ row.title }}</span><span class="block text-xs text-slate-500">{{ row.subtitle }}</span></template>
            <template #actions="{ row }"><button type="button" class="rounded-lg bg-primary-700 px-2 py-1 text-[10px] font-black uppercase text-white" @click.stop="openWatch(row)">Open</button></template>
        </OperationsQueueTable>

        <div v-else class="grid gap-3 sm:grid-cols-2">
            <article v-for="cluster in clusters" :key="cluster.id" class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm ring-1 ring-slate-100">
                <p class="text-[10px] font-black uppercase text-primary-700">{{ cluster.type.replace('_', ' ') }}</p>
                <h3 class="mt-1 font-display text-lg font-black text-slate-950">{{ cluster.label }}</h3>
                <p class="mt-1 text-sm font-semibold text-slate-600">{{ cluster.signal }}</p>
                <ul v-if="cluster.members?.length" class="mt-3 space-y-1 text-sm font-semibold text-slate-700">
                    <li v-for="m in cluster.members" :key="m.id">{{ m.name }} · {{ m.email }}</li>
                </ul>
                <Link :href="route('operations.users.index', { q: cluster.label })" class="mt-3 inline-block text-xs font-black uppercase text-primary-700">Investigate users →</Link>
            </article>
            <p v-if="!clusters.length && !loading" class="text-sm font-semibold text-slate-500">No clusters detected right now.</p>
        </div>

        <OperationsSlideOver :open="slideOpen" :title="detail?.item?.title || 'Watchlist'" subtitle="Activity timeline" eyebrow="Trust" @close="slideOpen = false">
            <div v-if="detail" class="space-y-3">
                <p class="text-sm font-semibold text-slate-700">{{ detail.item?.notes || 'No notes.' }}</p>
                <ul class="space-y-2">
                    <li v-for="ev in detail.timeline" :key="ev.id" class="rounded-xl border border-slate-100 bg-slate-50 p-3 text-sm"><span class="font-black text-slate-900">{{ ev.title }}</span><p class="mt-1 text-slate-600">{{ ev.body }}</p></li>
                </ul>
                <button type="button" class="w-full rounded-xl border border-rose-200 bg-rose-50 py-2 text-sm font-black text-rose-900" :disabled="busy.remove" @click="removeWatch">Remove from watchlist</button>
            </div>
        </OperationsSlideOver>
    </OperationsShell>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import OperationsQueueTable from '@/Pages/Operations/Components/OperationsQueueTable.vue';
import OperationsSlideOver from '@/Pages/Operations/Components/OperationsSlideOver.vue';
import OperationsShell from '@/Layouts/OperationsShell.vue';
import { useClientQueue } from '@/composables/useClientQueue';
import { useOperationsAction } from '@/composables/useOperationsAction';

const watchColumns = [{ key: 'title', label: 'Subject' }, { key: 'priority', label: 'Priority' }, { key: 'watchable_type', label: 'Type' }];
const rawItems = ref([]);
const clusters = ref([]);
const loading = ref(false);
const tab = ref('watchlist');
const slideOpen = ref(false);
const detail = ref(null);
const selected = ref(null);
const queue = useClientQueue(() => rawItems.value);
const { busy, runAction } = useOperationsAction();

onMounted(reload);

async function reload() {
    loading.value = true;
    try {
        const { data } = await window.axios.get(route('operations.api.trust.watchlist'));
        rawItems.value = data.items ?? [];
    } finally {
        loading.value = false;
    }
}

async function loadClusters() {
    tab.value = 'clusters';
    loading.value = true;
    try {
        const { data } = await window.axios.get(route('operations.api.trust.clusters'));
        clusters.value = data.clusters ?? [];
    } finally {
        loading.value = false;
    }
}

async function openWatch(row) {
    selected.value = row;
    slideOpen.value = true;
    const { data } = await window.axios.get(route('operations.api.trust.watchlist.detail', row.id));
    detail.value = data;
}

async function removeWatch() {
    await runAction('remove', () => window.axios.delete(route('operations.api.trust.watchlist.destroy', selected.value.id)), 'Removed.', () => {
        slideOpen.value = false;
        reload();
    });
}
</script>
