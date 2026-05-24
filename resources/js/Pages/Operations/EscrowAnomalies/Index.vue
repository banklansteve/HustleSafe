<template>
    <OperationsShell title="Escrow anomaly monitor" subtitle="Contracts needing proactive outreach before they become formal disputes.">
        <OperationsQueueTable :columns="columns" :rows="items" :loading="loading" empty-message="No escrow anomalies detected." @open="openDetail">
            <template #cell-title="{ row }"><span class="font-semibold text-slate-950">{{ row.title }}</span><span class="block text-xs text-slate-500">{{ row.reference_code }}</span></template>
            <template #cell-anomaly_label="{ row }"><span class="text-xs font-black uppercase text-primary-800">{{ row.anomaly_label }}</span></template>
            <template #actions="{ row }"><button type="button" class="rounded-lg bg-primary-700 px-2 py-1 text-[10px] font-black uppercase text-white active:scale-[0.98]" @click.stop="openDetail(row)">Investigate</button></template>
        </OperationsQueueTable>

        <OperationsSlideOver :open="slideOpen" :title="detail?.quest?.title || 'Contract'" subtitle="Log outreach and resolution" eyebrow="Escrow" @close="slideOpen = false">
            <div v-if="detail" class="space-y-4">
                <p class="text-sm font-semibold text-slate-600">{{ detail.quest?.escrow_status }} · {{ detail.quest?.client?.name }} ↔ {{ detail.quest?.freelancer?.name }}</p>
                <textarea v-model="outreachNote" rows="4" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-900" placeholder="Outreach summary…" />
                <button type="button" class="w-full rounded-xl bg-primary-700 py-2.5 text-sm font-black text-white active:scale-[0.98]" :disabled="busy.outreach" @click="logOutreach">Log outreach</button>
            </div>
        </OperationsSlideOver>
    </OperationsShell>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import OperationsQueueTable from '@/Pages/Operations/Components/OperationsQueueTable.vue';
import OperationsSlideOver from '@/Pages/Operations/Components/OperationsSlideOver.vue';
import OperationsShell from '@/Layouts/OperationsShell.vue';
import { useOperationsAction } from '@/composables/useOperationsAction';

const columns = [{ key: 'title', label: 'Quest' }, { key: 'anomaly_label', label: 'Anomaly' }, { key: 'client', label: 'Client' }];
const items = ref([]);
const loading = ref(false);
const slideOpen = ref(false);
const detail = ref(null);
const selected = ref(null);
const outreachNote = ref('');
const anomalyType = ref('funded_no_start');
const { busy, runAction } = useOperationsAction();

onMounted(reload);

async function reload() {
    loading.value = true;
    try {
        const { data } = await window.axios.get(route('operations.api.escrow-anomalies.listing'));
        items.value = data.items ?? [];
    } finally {
        loading.value = false;
    }
}

async function openDetail(row) {
    selected.value = row;
    anomalyType.value = row.anomaly_type;
    slideOpen.value = true;
    const { data } = await window.axios.get(route('operations.api.escrow-anomalies.detail', row.quest_uuid));
    detail.value = data;
}

async function logOutreach() {
    await runAction('outreach', () => window.axios.post(route('operations.api.escrow-anomalies.outreach', selected.value.quest_uuid), {
        anomaly_type: anomalyType.value,
        outreach_summary: outreachNote.value,
    }), 'Outreach logged.', () => { slideOpen.value = false; reload(); });
}
</script>
