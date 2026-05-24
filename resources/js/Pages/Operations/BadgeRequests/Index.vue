<template>
    <OperationsShell title="Verification badge requests" subtitle="Human review for Top Rated, Rising Talent, and other earned badges.">
        <OperationsQueueTable :columns="columns" :rows="items" :loading="loading" empty-message="No badge requests pending." @open="openDetail">
            <template #cell-user="{ row }"><span class="font-semibold text-slate-950">{{ row.user?.name }}</span></template>
            <template #cell-badge_label="{ row }"><span class="text-xs font-black text-primary-800">{{ row.badge_label }}</span></template>
            <template #actions="{ row }"><button type="button" class="rounded-lg bg-primary-700 px-2 py-1 text-[10px] font-black uppercase text-white active:scale-[0.98]" @click.stop="openDetail(row)">Review</button></template>
        </OperationsQueueTable>

        <OperationsSlideOver :open="slideOpen" :title="detail?.request?.badge_label || 'Badge'" subtitle="Metrics & disqualifiers" eyebrow="Badges" @close="slideOpen = false">
            <div v-if="detail" class="space-y-4">
                <ul v-if="detail.disqualifiers?.length" class="rounded-xl border border-rose-100 bg-rose-50 p-3 text-sm font-semibold text-rose-900">
                    <li v-for="d in detail.disqualifiers" :key="d">⚠ {{ d }}</li>
                </ul>
                <pre class="overflow-auto rounded-xl bg-slate-50 p-3 text-xs text-slate-800">{{ JSON.stringify(detail.live_metrics, null, 2) }}</pre>
                <textarea v-model="note" rows="3" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Decision note…" />
                <button type="button" class="w-full rounded-xl bg-primary-700 py-2.5 text-sm font-black text-white active:scale-[0.98]" :disabled="busy.approve" @click="act('approve')">Approve badge</button>
                <button type="button" class="w-full rounded-xl border border-slate-200 py-2.5 text-sm font-black text-slate-800 active:scale-[0.98]" :disabled="busy.reject" @click="act('reject')">Reject</button>
                <button type="button" class="w-full rounded-xl border border-amber-200 bg-amber-50 py-2.5 text-sm font-black text-amber-900 active:scale-[0.98]" :disabled="busy.escalate" @click="act('escalate')">Escalate</button>
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

const columns = [{ key: 'user', label: 'Freelancer' }, { key: 'badge_label', label: 'Badge' }, { key: 'status', label: 'Status' }];
const items = ref([]);
const loading = ref(false);
const slideOpen = ref(false);
const detail = ref(null);
const selectedId = ref(null);
const note = ref('');
const { busy, runAction } = useOperationsAction();

onMounted(reload);

async function reload() {
    loading.value = true;
    try {
        const { data } = await window.axios.get(route('operations.api.badge-requests.listing'));
        items.value = data.items ?? [];
    } finally {
        loading.value = false;
    }
}

async function openDetail(row) {
    selectedId.value = row.id;
    slideOpen.value = true;
    const { data } = await window.axios.get(route('operations.api.badge-requests.detail', row.id));
    detail.value = data;
}

async function act(action) {
    const map = { approve: 'operations.api.badge-requests.approve', reject: 'operations.api.badge-requests.reject', escalate: 'operations.api.badge-requests.escalate' };
    await runAction(action, () => window.axios.post(route(map[action], selectedId.value), { note: note.value }), 'Saved.', () => { slideOpen.value = false; reload(); });
}
</script>
