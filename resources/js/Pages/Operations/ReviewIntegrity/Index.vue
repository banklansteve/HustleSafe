<template>
    <OperationsShell title="Review & rating integrity" subtitle="Detect coordinated manipulation patterns that individual review queues cannot surface.">
        <div class="mb-4 flex flex-wrap gap-2">
            <button type="button" class="rounded-xl px-4 py-2 text-xs font-black uppercase transition active:scale-[0.98]" :class="tab === 'signals' ? 'bg-primary-700 text-white shadow-md' : 'border border-slate-200 bg-white text-slate-700 hover:bg-primary-50'" @click="tab = 'signals'">Live signals</button>
            <button type="button" class="rounded-xl px-4 py-2 text-xs font-black uppercase transition active:scale-[0.98]" :class="tab === 'cases' ? 'bg-primary-700 text-white shadow-md' : 'border border-slate-200 bg-white text-slate-700 hover:bg-primary-50'" @click="tab = 'cases'">Investigations</button>
        </div>

        <div v-if="tab === 'signals'" class="grid gap-3 sm:grid-cols-2">
            <article v-for="signal in liveSignals" :key="signal.pattern_key" class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm ring-1 ring-slate-100">
                <p class="text-[10px] font-black uppercase text-primary-700">{{ signal.pattern_type.replace('_', ' ') }}</p>
                <h3 class="mt-1 font-display text-lg font-black text-slate-950">{{ signal.label }}</h3>
                <p class="mt-2 text-sm font-semibold text-slate-600">{{ signal.signal }}</p>
                <button type="button" class="mt-4 rounded-xl bg-primary-700 px-3 py-2 text-xs font-black uppercase text-white shadow-sm transition hover:bg-primary-800 active:scale-[0.98]" :disabled="busy.open" @click="openCase(signal)">Open investigation</button>
            </article>
            <p v-if="!liveSignals.length && !loading" class="text-sm font-semibold text-slate-500">No live integrity signals right now.</p>
        </div>

        <OperationsQueueTable v-else :columns="columns" :rows="cases" :loading="loading" empty-message="No investigations yet." @open="openCaseDetail">
            <template #cell-pattern_label="{ row }"><span class="font-semibold text-slate-950">{{ row.pattern_label }}</span></template>
            <template #cell-status="{ row }"><span class="rounded-full bg-primary-50 px-2 py-0.5 text-[10px] font-black uppercase text-primary-800">{{ row.status }}</span></template>
            <template #actions="{ row }"><button type="button" class="rounded-lg bg-primary-700 px-2 py-1 text-[10px] font-black uppercase text-white" @click.stop="openCaseDetail(row)">Open</button></template>
        </OperationsQueueTable>

        <OperationsSlideOver :open="slideOpen" :title="detail?.case?.pattern_label || 'Investigation'" subtitle="Document findings and flag reviews" eyebrow="Integrity" @close="slideOpen = false">
            <div v-if="detail" class="space-y-4">
                <p class="text-sm font-semibold text-slate-600">{{ detail.case?.findings || 'No findings recorded yet.' }}</p>
                <textarea v-model="findingsDraft" rows="4" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-900" placeholder="Document evidence and conclusions…" />
                <button type="button" class="w-full rounded-xl bg-primary-700 py-2.5 text-sm font-black text-white" :disabled="busy.findings" @click="saveFindings">Save findings</button>
                <button type="button" class="w-full rounded-xl border border-amber-200 bg-amber-50 py-2.5 text-sm font-black text-amber-900" :disabled="busy.flag" @click="bulkFlag">Flag linked reviews</button>
                <button type="button" class="w-full rounded-xl border border-slate-200 py-2.5 text-sm font-black text-slate-800" :disabled="busy.escalate" @click="escalate">Escalate to Super Admin</button>
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

const columns = [{ key: 'pattern_label', label: 'Pattern' }, { key: 'status', label: 'Status' }, { key: 'flagged_count', label: 'Flagged' }];
const tab = ref('signals');
const loading = ref(false);
const liveSignals = ref([]);
const cases = ref([]);
const slideOpen = ref(false);
const detail = ref(null);
const selectedId = ref(null);
const findingsDraft = ref('');
const { busy, runAction } = useOperationsAction();

onMounted(reload);

async function reload() {
    loading.value = true;
    try {
        const { data } = await window.axios.get(route('operations.api.review-integrity.listing'));
        liveSignals.value = data.live_signals ?? [];
        cases.value = data.cases ?? [];
    } finally {
        loading.value = false;
    }
}

async function openCase(signal) {
    await runAction('open', () => window.axios.post(route('operations.api.review-integrity.open'), signal), 'Investigation opened.', reload);
}

async function openCaseDetail(row) {
    selectedId.value = row.id;
    slideOpen.value = true;
    const { data } = await window.axios.get(route('operations.api.review-integrity.detail', row.id));
    detail.value = data;
    findingsDraft.value = data.case?.findings || '';
}

async function saveFindings() {
    await runAction('findings', () => window.axios.patch(route('operations.api.review-integrity.findings', selectedId.value), { findings: findingsDraft.value }), 'Findings saved.', () => openCaseDetail({ id: selectedId.value }));
}

async function bulkFlag() {
    const ids = (detail.value?.reviews ?? []).map((r) => r.id);
    if (!ids.length) return;
    await runAction('flag', () => window.axios.post(route('operations.api.review-integrity.flag', selectedId.value), { review_ids: ids }), 'Reviews flagged.', reload);
}

async function escalate() {
    await runAction('escalate', () => window.axios.post(route('operations.api.review-integrity.escalate', selectedId.value)), 'Escalated.', reload);
}
</script>
