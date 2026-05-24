<template>
    <OperationsShell title="Freelancer quality" subtitle="Proactive talent maintenance — trends, coaching, warnings, and referrals.">
        <OperationsQueueTable :columns="columns" :rows="queue.pageItems.value" :loading="loading" v-model:search="queue.search.value" v-model:per-page="queue.perPage.value" :page="queue.page.value" :total="queue.total.value" :total-pages="queue.totalPages.value" :sort-key="queue.sortKey.value" :sort-dir="queue.sortDir.value" empty-message="No freelancers below thresholds." @sort="queue.setSort" @page="(p) => (queue.page.value = p)" @open="openDetail">
            <template #cell-name="{ row }"><span class="font-semibold text-slate-950">{{ row.name }}</span><span class="block text-xs text-slate-500">{{ row.email }}</span></template>
            <template #cell-reasons="{ row }"><span class="text-xs font-bold text-amber-800">{{ (row.reason_labels || row.reasons).join(', ') }}</span></template>
            <template #actions="{ row }"><button type="button" class="rounded-lg bg-primary-700 px-2 py-1 text-[10px] font-black uppercase text-white" @click.stop="openDetail(row)">Review</button></template>
        </OperationsQueueTable>

        <OperationsSlideOver :open="slideOpen" :title="detail?.freelancer?.name || 'Freelancer'" subtitle="90-day performance" eyebrow="Quality" @close="slideOpen = false">
            <div v-if="detail" class="space-y-2">
                <OperationsContextStats heading="Metrics" :stats="metricStats" />
                <OperationsExpandableAction title="Coaching message" icon="✉" submit-label="Send" :busy="busy.contact" @submit="contact">
                    <div class="space-y-2">
                        <OperationsFormField v-model="forms.contact.subject" label="Subject" placeholder="How can we help you improve?" />
                        <OperationsFormField v-model="forms.contact.body" label="Message" multiline :rows="5" placeholder="Personalised coaching message…" />
                    </div>
                </OperationsExpandableAction>
                <OperationsExpandableAction title="Performance warning" icon="⚠" tone="amber" submit-label="Issue warning" :busy="busy.warning" @submit="warning">
                    <OperationsFormField v-model="forms.warning.notes" label="Warning notes" multiline :rows="5" placeholder="Describe the performance issue and expected improvement…" />
                </OperationsExpandableAction>
                <OperationsExpandableAction title="Restrict high-value bids" icon="⛔" tone="rose" submit-label="Apply restriction" :busy="busy.restrict" @submit="restrict">
                    <OperationsFormField v-model="forms.restrict.notes" label="Restriction rationale" multiline :rows="5" placeholder="Why this restriction applies and for how long…" />
                </OperationsExpandableAction>
                <OperationsExpandableAction title="Refer for account review" icon="⬆" submit-label="Refer" :busy="busy.refer" @submit="refer">
                    <OperationsFormField v-model="forms.refer.notes" label="Escalation notes" multiline :rows="5" placeholder="Context for Super Admin review…" />
                </OperationsExpandableAction>
                <Link :href="route('operations.users.index', { q: detail.freelancer.email })" class="text-sm font-black text-primary-700">Open full user profile →</Link>
            </div>
        </OperationsSlideOver>
    </OperationsShell>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import OperationsContextStats from '@/Pages/Operations/Components/OperationsContextStats.vue';
import OperationsFormField from '@/Pages/Operations/Components/OperationsFormField.vue';
import OperationsExpandableAction from '@/Pages/Operations/Components/OperationsExpandableAction.vue';
import OperationsQueueTable from '@/Pages/Operations/Components/OperationsQueueTable.vue';
import OperationsSlideOver from '@/Pages/Operations/Components/OperationsSlideOver.vue';
import OperationsShell from '@/Layouts/OperationsShell.vue';
import { useClientQueue } from '@/composables/useClientQueue';
import { useOperationsAction } from '@/composables/useOperationsAction';

const columns = [{ key: 'name', label: 'Freelancer' }, { key: 'reasons', label: 'Signals' }];
const rawItems = ref([]);
const loading = ref(false);
const slideOpen = ref(false);
const detail = ref(null);
const selected = ref(null);
const forms = reactive({ contact: { subject: '', body: '' }, warning: { notes: '' }, restrict: { notes: '' }, refer: { notes: '' } });
const queue = useClientQueue(() => rawItems.value);
const { busy, runAction } = useOperationsAction();
const metricStats = computed(() => {
    if (!detail.value?.metrics) return [];
    const m = detail.value.metrics;
    return [
        { label: 'Rating', value: m.avg_rating },
        { label: 'Completion', value: `${m.completion_rate_percent}%` },
        { label: 'Disputes', value: `${m.dispute_rate_percent}%` },
    ];
});

onMounted(async () => {
    loading.value = true;
    try {
        const { data } = await window.axios.get(route('operations.api.quality.listing'));
        rawItems.value = data.items ?? [];
    } finally {
        loading.value = false;
    }
});

async function openDetail(row) {
    selected.value = row;
    slideOpen.value = true;
    const { data } = await window.axios.get(route('operations.api.quality.detail', row.freelancer_id));
    detail.value = data;
}

async function contact() {
    await runAction('contact', () => window.axios.post(route('operations.api.quality.contact', selected.value.freelancer_id), forms.contact), 'Message sent.');
}
async function warning() {
    await runAction('warning', () => window.axios.post(route('operations.api.quality.warning', selected.value.freelancer_id), forms.warning), 'Warning issued.');
}
async function restrict() {
    await runAction('restrict', () => window.axios.post(route('operations.api.quality.restrict', selected.value.freelancer_id), forms.restrict), 'Restriction saved.');
}
async function refer() {
    await runAction('refer', () => window.axios.post(route('operations.api.quality.refer', selected.value.freelancer_id), forms.refer), 'Referred to Super Admin.');
}
</script>
