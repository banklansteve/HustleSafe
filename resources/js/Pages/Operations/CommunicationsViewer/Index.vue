<template>
    <OperationsShell title="Platform communications log" subtitle="Read-only view of banners, mass emails, and scheduled announcements.">
        <section v-if="upcoming.length" class="mb-5 rounded-2xl border border-primary-100 bg-primary-50/60 p-4">
            <p class="text-[10px] font-black uppercase tracking-wide text-primary-800">Upcoming</p>
            <ul class="mt-2 space-y-2">
                <li v-for="item in upcoming" :key="`${item.type}-${item.id}`" class="text-sm font-semibold text-slate-800">{{ item.title }} · {{ item.audience }} · {{ formatWhen(item.starts_at) }}</li>
            </ul>
        </section>

        <OperationsQueueTable :columns="columns" :rows="items" :loading="loading" empty-message="No communications logged yet.">
            <template #cell-title="{ row }"><span class="font-semibold text-slate-950">{{ row.title }}</span></template>
            <template #cell-type="{ row }"><span class="text-xs font-black uppercase text-primary-700">{{ row.type.replace('_', ' ') }}</span></template>
            <template #cell-status="{ row }"><span class="text-xs font-bold text-slate-600">{{ row.status }}</span></template>
        </OperationsQueueTable>
    </OperationsShell>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import OperationsQueueTable from '@/Pages/Operations/Components/OperationsQueueTable.vue';
import OperationsShell from '@/Layouts/OperationsShell.vue';

const columns = [{ key: 'type', label: 'Channel' }, { key: 'title', label: 'Message' }, { key: 'audience', label: 'Audience' }, { key: 'status', label: 'Status' }];
const items = ref([]);
const upcoming = ref([]);
const loading = ref(false);

onMounted(async () => {
    loading.value = true;
    try {
        const { data } = await window.axios.get(route('operations.api.communications-log.listing'));
        items.value = data.items ?? [];
        upcoming.value = data.upcoming ?? [];
    } finally {
        loading.value = false;
    }
});

function formatWhen(iso) {
    try { return new Date(iso).toLocaleString(); } catch { return ''; }
}
</script>
