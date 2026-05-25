<template>
    <OperationsShell title="Payments (limited)" subtitle="View escrow and payout status. Request hold, release, or refund — Super Admin approves execution.">
        <div class="mb-4 flex flex-wrap gap-2">
            <select v-model="escrowFilter" class="rounded-2xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold" @change="reload">
                <option value="">All escrow states</option>
                <option v-for="e in escrow_options" :key="e" :value="e">{{ e }}</option>
            </select>
            <a :href="route('operations.payments.export', { escrow: escrowFilter })" class="rounded-2xl border border-slate-200 bg-white px-4 py-2 text-sm font-black text-slate-700">Export CSV</a>
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
            empty-message="No contracts match this filter."
            @sort="queue.setSort"
            @page="(p) => (queue.page.value = p)"
            @open="openDetail"
        >
            <template #cell-title="{ row }">
                <span class="font-semibold text-slate-950">{{ row.title }}</span>
                <span class="block text-xs text-slate-500">{{ row.reference_code }}</span>
            </template>
            <template #actions="{ row }">
                <button type="button" class="rounded-lg bg-primary-700 px-2 py-1 text-[10px] font-black uppercase text-white" @click.stop="openDetail(row)">View</button>
            </template>
        </OperationsQueueTable>

        <OperationsSlideOver :open="slideOpen" :title="slideTitle" subtitle="Read-only financial context" eyebrow="Payments" @close="slideOpen = false">
            <div v-if="detailLoading" class="py-10 text-center text-sm text-slate-500">Loading…</div>
            <div v-else-if="detail" class="space-y-4">
                <OperationsContextStats
                    heading="Escrow snapshot"
                    :stats="[
                        { label: 'Escrow', value: detail.quest.escrow_status || '—' },
                        { label: 'Payout', value: detail.quest.payout_status || '—' },
                        { label: 'Budget', value: formatMinor(detail.quest.budget_amount_minor) },
                    ]"
                />
                <p v-if="detail.quest.payout_failure_reason" class="rounded-xl border border-rose-100 bg-rose-50 p-3 text-sm font-semibold text-rose-900">{{ detail.quest.payout_failure_reason }}</p>

                <OperationsExpandableAction v-for="type in request_types" :key="type" :title="requestLabel(type)" :hint="`Creates a Super Admin task — you cannot ${type} directly.`" icon="⬆" submit-label="Submit request" :busy="busy[type]" @submit="request(type)">
                    <textarea v-model="requestForms[type].reason" class="form-input min-h-28" placeholder="Reason (min 20 characters)" />
                </OperationsExpandableAction>
            </div>
        </OperationsSlideOver>
    </OperationsShell>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import OperationsContextStats from '@/Pages/Operations/Components/OperationsContextStats.vue';
import OperationsExpandableAction from '@/Pages/Operations/Components/OperationsExpandableAction.vue';
import OperationsQueueTable from '@/Pages/Operations/Components/OperationsQueueTable.vue';
import OperationsSlideOver from '@/Pages/Operations/Components/OperationsSlideOver.vue';
import OperationsShell from '@/Layouts/OperationsShell.vue';
import { useClientQueue } from '@/composables/useClientQueue';
import { useOperationsAction } from '@/composables/useOperationsAction';

const props = defineProps({
    escrow_options: { type: Array, default: () => [] },
    request_types: { type: Array, default: () => ['hold_payout', 'release_payout', 'refund'] },
});

const columns = [
    { key: 'title', label: 'Quest' },
    { key: 'escrow_status', label: 'Escrow' },
    { key: 'status', label: 'Status' },
    { key: 'paid_out_display', label: 'Paid out' },
];

const rawItems = ref([]);
const loading = ref(false);
const escrowFilter = ref('');
const slideOpen = ref(false);
const detailLoading = ref(false);
const detail = ref(null);
const selectedRow = ref(null);
const requestForms = reactive({
    hold_payout: { reason: '' },
    release_payout: { reason: '' },
    refund: { reason: '' },
});

const queue = useClientQueue(() => rawItems.value, { searchFields: ['title', 'reference_code', 'escrow_status'] });
const { busy, runAction } = useOperationsAction();
const slideTitle = computed(() => detail.value?.quest?.title || 'Payment');

onMounted(reload);

async function reload() {
    loading.value = true;
    try {
        const { data } = await window.axios.get(route('operations.api.payments.listing'), { params: { escrow: escrowFilter.value } });
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
        const { data } = await window.axios.get(route('operations.api.payments.detail', row.route_key ?? row.id));
        detail.value = data;
    } finally {
        detailLoading.value = false;
    }
}

async function request(type) {
    await runAction(type, () => window.axios.post(route('operations.api.payments.requests', selectedRow.value.route_key ?? selectedRow.value.id), { type, reason: requestForms[type].reason }), 'Request submitted to Super Admin.');
}

function requestLabel(type) {
    return String(type).replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function formatMinor(minor) {
    if (!minor) return '—';
    return `₦${(Number(minor) / 100).toLocaleString()}`;
}
</script>
