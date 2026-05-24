<template>
    <OperationsShell title="Payout exceptions" subtitle="Failed payouts, pending Super Admin approvals, and resolution tracking.">
        <div class="mb-4 flex flex-wrap gap-2">
            <button v-for="f in filters" :key="f.key" type="button" class="rounded-xl px-4 py-2 text-xs font-black uppercase" :class="activeFilter === f.key ? 'bg-primary-700 text-white' : 'border border-slate-200 bg-white text-slate-700'" @click="setFilter(f.key)">{{ f.label }}</button>
        </div>
        <OperationsQueueTable :columns="columns" :rows="queue.pageItems.value" :loading="loading" v-model:search="queue.search.value" v-model:per-page="queue.perPage.value" :page="queue.page.value" :total="queue.total.value" :total-pages="queue.totalPages.value" :sort-key="queue.sortKey.value" :sort-dir="queue.sortDir.value" empty-message="No payout exceptions." @sort="queue.setSort" @page="(p) => (queue.page.value = p)" @open="openDetail">
            <template #cell-type="{ row }"><span class="font-semibold uppercase text-slate-800">{{ row.type.replace(/_/g, ' ') }}</span></template>
            <template #actions="{ row }"><button type="button" class="rounded-lg bg-primary-700 px-2 py-1 text-[10px] font-black uppercase text-white" @click.stop="openDetail(row)">Open</button></template>
        </OperationsQueueTable>
        <OperationsSlideOver :open="slideOpen" :title="detail?.exception?.quest || 'Exception'" subtitle="Payout context" eyebrow="Payouts" @close="slideOpen = false">
            <div v-if="detail" class="space-y-4">
                <p class="rounded-xl border border-amber-100 bg-amber-50 p-3 text-sm font-semibold text-amber-950">{{ detail.exception.error_summary }}</p>
                <OperationsExpandableAction title="Resolve exception" icon="✓" submit-label="Mark resolved" :busy="busy.resolve" @submit="resolve"><textarea v-model="resolveForm.summary" class="form-input min-h-28" placeholder="Resolution summary for audit" /></OperationsExpandableAction>
                <Link v-if="detail.quest?.id" :href="route('operations.payments.index')" class="text-sm font-black text-primary-700">View payments panel →</Link>
            </div>
        </OperationsSlideOver>
    </OperationsShell>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { onMounted, reactive, ref } from 'vue';
import OperationsExpandableAction from '@/Pages/Operations/Components/OperationsExpandableAction.vue';
import OperationsQueueTable from '@/Pages/Operations/Components/OperationsQueueTable.vue';
import OperationsSlideOver from '@/Pages/Operations/Components/OperationsSlideOver.vue';
import OperationsShell from '@/Layouts/OperationsShell.vue';
import { useClientQueue } from '@/composables/useClientQueue';
import { useOperationsAction } from '@/composables/useOperationsAction';

const filters = [{ key: 'open', label: 'Open' }, { key: 'pending_super_admin', label: 'Pending SA' }, { key: 'resolved', label: 'Resolved' }];
const columns = [{ key: 'type', label: 'Type' }, { key: 'status', label: 'Status' }, { key: 'user', label: 'User' }, { key: 'quest', label: 'Quest' }];
const rawItems = ref([]);
const loading = ref(false);
const activeFilter = ref('open');
const slideOpen = ref(false);
const detail = ref(null);
const selected = ref(null);
const resolveForm = reactive({ summary: '' });
const queue = useClientQueue(() => rawItems.value);
const { busy, runAction } = useOperationsAction();

onMounted(() => reload());

async function reload() {
    loading.value = true;
    try {
        const { data } = await window.axios.get(route('operations.api.payout-exceptions.listing'), { params: { filter: activeFilter.value } });
        rawItems.value = data.items ?? [];
    } finally {
        loading.value = false;
    }
}

function setFilter(key) {
    activeFilter.value = key;
    reload();
}

async function openDetail(row) {
    selected.value = row;
    slideOpen.value = true;
    const { data } = await window.axios.get(route('operations.api.payout-exceptions.detail', row.id));
    detail.value = data;
}

async function resolve() {
    await runAction('resolve', () => window.axios.patch(route('operations.api.payout-exceptions.resolve', selected.value.id), resolveForm), 'Resolved.', () => {
        slideOpen.value = false;
        reload();
    });
}
</script>
