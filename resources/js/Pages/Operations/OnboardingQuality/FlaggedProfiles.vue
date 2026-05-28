<template>
    <OperationsShell title="Flagged profiles" subtitle="Accounts flagged for monitoring during onboarding quality review — suspicious but not bannable.">
        <div class="mb-4">
            <Link :href="route('operations.onboarding-quality.index')" class="text-xs font-black uppercase text-primary-700">← Onboarding quality control</Link>
        </div>
        <OperationsQueueTable
            :columns="columns"
            :rows="rows"
            :loading="loading"
            v-model:search="search"
            v-model:per-page="perPage"
            :page="page"
            :total="total"
            :total-pages="totalPages"
            empty-message="No flagged profiles."
            @page="(p) => { page = p; reload(); }"
            @open="openRow"
        >
            <template #cell-user="{ row }">
                <span class="font-semibold text-slate-950">{{ row.user?.name }}</span>
                <span class="block text-xs text-slate-500">{{ row.user?.email }}</span>
            </template>
            <template #cell-flags="{ row }">
                <span v-for="f in row.flags" :key="f.key" class="mr-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-black text-amber-900">{{ f.label }}</span>
            </template>
            <template #actions="{ row }">
                <button type="button" class="rounded-lg bg-primary-700 px-2 py-1 text-[10px] font-black uppercase text-white" @click.stop="openRow(row)">Open QC</button>
            </template>
        </OperationsQueueTable>
        <OperationsSlideOver :open="slideOpen" :title="detail?.user?.name || 'Flagged profile'" eyebrow="Monitoring" @close="slideOpen = false">
            <div v-if="detail" class="space-y-3 text-sm">
                <p class="font-semibold text-slate-700">{{ detail.monitoring_reason }}</p>
                <p class="text-xs text-slate-500">Status: {{ detail.status_label }} · Score {{ detail.completeness_score }}%</p>
                <Link :href="route('operations.onboarding-quality.index')" class="inline-block text-xs font-black uppercase text-primary-700">Open in onboarding QC →</Link>
            </div>
        </OperationsSlideOver>
    </OperationsShell>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { onMounted, ref, watch } from 'vue';
import OperationsQueueTable from '@/Pages/Operations/Components/OperationsQueueTable.vue';
import OperationsSlideOver from '@/Pages/Operations/Components/OperationsSlideOver.vue';
import OperationsShell from '@/Layouts/OperationsShell.vue';

const columns = [
    { key: 'user_type', label: 'Type' },
    { key: 'user', label: 'User' },
    { key: 'completeness_score', label: 'Score' },
    { key: 'flags', label: 'Flags' },
    { key: 'status', label: 'Status' },
];

const rows = ref([]);
const loading = ref(false);
const search = ref('');
const perPage = ref(25);
const page = ref(1);
const total = ref(0);
const totalPages = ref(1);
const slideOpen = ref(false);
const detail = ref(null);

onMounted(reload);
watch(search, () => { page.value = 1; reload(); });

async function reload() {
    loading.value = true;
    try {
        const { data } = await window.axios.get(route('operations.api.onboarding-quality.flagged'), {
            params: { q: search.value, page: page.value, per_page: perPage.value },
        });
        rows.value = data.items ?? [];
        total.value = data.meta?.total ?? 0;
        totalPages.value = data.meta?.last_page ?? 1;
    } finally {
        loading.value = false;
    }
}

function openRow(row) {
    detail.value = row;
    slideOpen.value = true;
}
</script>
