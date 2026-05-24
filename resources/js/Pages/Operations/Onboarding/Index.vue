<template>
    <OperationsShell title="Onboarding assistance" subtitle="Abandoned-flow tracking for clients and freelancers — prioritised by staleness.">
        <div class="mb-4 flex flex-wrap gap-2">
            <button v-for="f in filters" :key="f.key" type="button" class="rounded-lg px-3 py-2 text-xs font-black uppercase transition active:scale-[0.98]" :class="activeType === f.key ? 'bg-primary-700 text-white shadow-md' : 'border border-slate-200 bg-white text-slate-700'" @click="setType(f.key)">{{ f.label }}</button>
            <select v-model="activeScenario" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-800" @change="reload">
                <option value="">All milestones</option>
                <option v-for="s in scenarios" :key="s.key" :value="s.key">{{ s.label }}</option>
            </select>
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
            empty-message="No open onboarding cases. Run the daily job or check back tomorrow."
            @sort="queue.setSort"
            @page="(p) => (queue.page.value = p)"
            @open="openCase"
        >
            <template #cell-user="{ row }">
                <span class="font-semibold text-slate-950">{{ row.user?.name }}</span>
                <span class="block text-xs text-slate-500">{{ row.user?.email }}</span>
            </template>
            <template #cell-user_type="{ row }">
                <span class="text-xs font-black uppercase text-primary-800">{{ row.user_type }}</span>
            </template>
            <template #cell-staleness_score="{ row }">
                <span class="rounded-full px-2 py-0.5 text-xs font-black" :class="row.staleness_score >= 70 ? 'bg-rose-100 text-rose-900' : row.staleness_score >= 40 ? 'bg-amber-100 text-amber-900' : 'bg-slate-100 text-slate-700'">{{ row.staleness_score }}</span>
            </template>
            <template #cell-last_activity_at="{ row }">
                <span class="text-sm font-semibold text-slate-600">{{ formatHumanDateTime(row.last_activity_at) }}</span>
            </template>
            <template #actions="{ row }">
                <button type="button" class="rounded-lg bg-primary-700 px-2 py-1 text-[10px] font-black uppercase text-white active:scale-[0.98]" @click.stop="openCase(row)">Open</button>
            </template>
        </OperationsQueueTable>

        <OperationsSlideOver :open="slideOpen" :title="detail?.user?.name || 'Case'" :subtitle="detail?.record?.milestone_reached" eyebrow="Onboarding" @close="slideOpen = false">
            <div v-if="detail" class="space-y-2">
                <div class="grid grid-cols-2 gap-2 rounded-xl border border-slate-100 bg-slate-50 p-3 text-sm">
                    <div><p class="text-[10px] font-black uppercase text-slate-400">Staleness</p><p class="font-black text-slate-900">{{ detail.record.staleness_score }}</p></div>
                    <div><p class="text-[10px] font-black uppercase text-slate-400">Cycles</p><p class="font-black text-slate-900">{{ detail.record.cycles_elapsed }}</p></div>
                    <div class="col-span-2"><p class="text-[10px] font-black uppercase text-slate-400">Last meaningful action</p><p class="font-semibold text-slate-700">{{ formatHumanDateTime(detail.record.last_meaningful_action_at) }}</p></div>
                </div>

                <OperationsExpandableAction title="Contact user" icon="✉" submit-label="Send outreach" :busy="busy.outreach" @submit="sendOutreach">
                    <div class="space-y-2">
                        <OperationsFormField v-model="outreachForm.subject" label="Subject" />
                        <OperationsFormField v-model="outreachForm.body" label="Message" multiline :rows="6" />
                        <label class="block text-xs font-bold text-slate-600">
                            Channel
                            <select v-model="outreachForm.channel" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-semibold text-slate-900">
                                <option value="both">Email + in-app</option>
                                <option value="email">Email only</option>
                                <option value="in_app">In-app only</option>
                            </select>
                        </label>
                    </div>
                </OperationsExpandableAction>

                <OperationsExpandableAction title="Create support ticket" icon="🎫" submit-label="Create ticket" :busy="busy.ticket" @submit="createTicket">
                    <div class="space-y-2">
                        <OperationsFormField v-model="ticketForm.subject" label="Subject" placeholder="Onboarding follow-up" />
                        <OperationsFormField v-model="ticketForm.body" label="Notes" multiline :rows="4" />
                    </div>
                </OperationsExpandableAction>

                <button type="button" class="w-full rounded-xl border border-emerald-200 bg-emerald-50 py-2.5 text-sm font-black text-emerald-900 active:scale-[0.98]" :disabled="busy.resolve" @click="markResolved">Mark resolved</button>
            </div>
        </OperationsSlideOver>
    </OperationsShell>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import OperationsExpandableAction from '@/Pages/Operations/Components/OperationsExpandableAction.vue';
import OperationsFormField from '@/Pages/Operations/Components/OperationsFormField.vue';
import OperationsQueueTable from '@/Pages/Operations/Components/OperationsQueueTable.vue';
import OperationsSlideOver from '@/Pages/Operations/Components/OperationsSlideOver.vue';
import OperationsShell from '@/Layouts/OperationsShell.vue';
import { useClientQueue } from '@/composables/useClientQueue';
import { useOperationsAction } from '@/composables/useOperationsAction';
import { formatHumanDateTime } from '@/utils/formatHumanDateTime';

const columns = [
    { key: 'user_type', label: 'Type' },
    { key: 'user', label: 'User' },
    { key: 'milestone_reached', label: 'Milestone' },
    { key: 'staleness_score', label: 'Staleness' },
    { key: 'cycles_elapsed', label: 'Cycles' },
    { key: 'last_activity_at', label: 'Last activity' },
    { key: 'status', label: 'Status' },
];

const rawItems = ref([]);
const scenarios = ref([]);
const filters = ref([{ key: '', label: 'All types' }]);
const loading = ref(false);
const activeType = ref('');
const activeScenario = ref('');
const slideOpen = ref(false);
const detail = ref(null);
const selectedId = ref(null);
const outreachForm = reactive({ subject: '', body: '', channel: 'both' });
const ticketForm = reactive({ subject: '', body: '' });
const queue = useClientQueue(() => rawItems.value, {
    searchFields: ['user.name', 'user.email', 'milestone_reached', 'scenario_label'],
    defaultSortKey: 'staleness_score',
    defaultSortDir: 'desc',
});
const { busy, runAction } = useOperationsAction();

onMounted(reload);

function setType(key) {
    activeType.value = key;
    reload();
}

async function reload() {
    loading.value = true;
    try {
        const { data } = await window.axios.get(route('operations.api.onboarding.listing'), {
            params: {
                user_type: activeType.value || undefined,
                scenario: activeScenario.value || undefined,
                q: queue.search.value || undefined,
            },
        });
        rawItems.value = data.items ?? [];
        scenarios.value = data.scenarios ?? [];
        filters.value = data.filters ?? filters.value;
    } finally {
        loading.value = false;
    }
}

async function openCase(row) {
    selectedId.value = row.id;
    slideOpen.value = true;
    const { data } = await window.axios.get(route('operations.api.onboarding.detail', row.id));
    detail.value = data;
    outreachForm.subject = data.template?.subject ?? '';
    outreachForm.body = data.template?.body ?? '';
}

async function sendOutreach() {
    await runAction('outreach', () => window.axios.post(route('operations.api.onboarding.outreach', selectedId.value), { ...outreachForm }), 'Outreach sent.', () => {
        slideOpen.value = false;
        reload();
    });
}

async function createTicket() {
    await runAction('ticket', () => window.axios.post(route('operations.api.onboarding.ticket', selectedId.value), { ...ticketForm }), 'Ticket created.');
}

async function markResolved() {
    await runAction('resolve', () => window.axios.post(route('operations.api.onboarding.resolve', selectedId.value)), 'Resolved.', () => {
        slideOpen.value = false;
        reload();
    });
}
</script>
