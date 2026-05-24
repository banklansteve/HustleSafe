<template>
    <OperationsShell title="Ban & sanction appeals" subtitle="Review appeals, lift sanctions, or escalate ambiguous cases.">
        <OperationsQueueTable :columns="columns" :rows="items" :loading="loading" empty-message="No appeals in queue." @open="openDetail">
            <template #cell-user="{ row }"><span class="font-semibold text-slate-950">{{ row.user?.name }}</span><span class="block text-xs text-slate-500">{{ row.user?.email }}</span></template>
            <template #cell-status="{ row }"><span class="rounded-full bg-primary-50 px-2 py-0.5 text-[10px] font-black uppercase text-primary-800">{{ row.status }}</span></template>
            <template #actions="{ row }"><button type="button" class="rounded-lg bg-primary-700 px-2 py-1 text-[10px] font-black uppercase text-white active:scale-[0.98]" @click.stop="openDetail(row)">Review</button></template>
        </OperationsQueueTable>

        <OperationsSlideOver :open="slideOpen" :title="detail?.appeal?.user?.name || 'Appeal'" subtitle="Sanction context & decision" eyebrow="Appeals" @close="slideOpen = false">
            <div v-if="detail" class="space-y-4">
                <div class="rounded-xl border border-slate-100 bg-slate-50 p-3 text-sm text-slate-800">
                    <p class="font-black text-slate-950">{{ detail.sanction?.type }} · {{ detail.sanction?.reason_code }}</p>
                    <p class="mt-1">{{ detail.sanction?.notes }}</p>
                </div>
                <p class="text-sm font-semibold text-slate-700">{{ detail.statement }}</p>
                <textarea v-model="decisionNote" rows="3" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Decision note to user…" />
                <button type="button" class="w-full rounded-xl bg-emerald-600 py-2.5 text-sm font-black text-white active:scale-[0.98]" :disabled="busy.approve" @click="decide('approve')">Approve & lift</button>
                <button type="button" class="w-full rounded-xl border border-rose-200 bg-rose-50 py-2.5 text-sm font-black text-rose-900 active:scale-[0.98]" :disabled="busy.reject" @click="decide('reject')">Reject appeal</button>
                <button type="button" class="w-full rounded-xl border border-slate-200 py-2.5 text-sm font-black text-slate-800 active:scale-[0.98]" :disabled="busy.escalate" @click="decide('escalate')">Escalate</button>
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

const columns = [{ key: 'user', label: 'User' }, { key: 'sanction_type', label: 'Sanction' }, { key: 'status', label: 'Status' }];
const items = ref([]);
const loading = ref(false);
const slideOpen = ref(false);
const detail = ref(null);
const selectedId = ref(null);
const decisionNote = ref('');
const { busy, runAction } = useOperationsAction();

onMounted(reload);

async function reload() {
    loading.value = true;
    try {
        const { data } = await window.axios.get(route('operations.api.sanction-appeals.listing'));
        items.value = data.items ?? [];
    } finally {
        loading.value = false;
    }
}

async function openDetail(row) {
    selectedId.value = row.id;
    slideOpen.value = true;
    const { data } = await window.axios.get(route('operations.api.sanction-appeals.detail', row.id));
    detail.value = data;
    decisionNote.value = '';
}

async function decide(action) {
    const routes = { approve: 'operations.api.sanction-appeals.approve', reject: 'operations.api.sanction-appeals.reject', escalate: 'operations.api.sanction-appeals.escalate' };
    await runAction(action, () => window.axios.post(route(routes[action], selectedId.value), { note: decisionNote.value }), 'Decision recorded.', () => { slideOpen.value = false; reload(); });
}
</script>
