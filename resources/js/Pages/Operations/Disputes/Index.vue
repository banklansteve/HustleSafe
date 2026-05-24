<template>
    <OperationsShell title="Dispute management" subtitle="Claim cases, communicate with parties, manage mediation tiers, and issue rulings within your threshold.">
        <div class="mb-4 flex gap-2 overflow-x-auto pb-1">
            <button
                v-for="queue in queues"
                :key="queue.key"
                type="button"
                class="shrink-0 rounded-2xl px-4 py-2 text-left"
                :class="activeQueue === queue.key ? 'bg-primary-700 text-white shadow-md' : 'border border-slate-200 bg-white text-slate-700'"
                @click="loadQueue(queue)"
            >
                <span class="block text-xs font-black uppercase">{{ queue.label }}</span>
                <span class="mt-0.5 block text-[11px] font-semibold opacity-80">{{ queue.hint }}</span>
            </button>
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
            empty-message="No disputes in this queue."
            @sort="queue.setSort"
            @page="(p) => (queue.page.value = p)"
            @open="openDetail"
        >
            <template #cell-quest="{ row }">
                <span class="font-semibold text-slate-950">{{ row.quest }}</span>
                <span class="block text-xs text-slate-500">{{ row.quest_reference }}</span>
            </template>
            <template #actions="{ row }">
                <button type="button" class="rounded-lg bg-primary-700 px-2 py-1 text-[10px] font-black uppercase text-white" @click.stop="openDetail(row)">Open</button>
            </template>
        </OperationsQueueTable>

        <OperationsSlideOver :open="slideOpen" :title="slideTitle" subtitle="Dispute workspace" eyebrow="Disputes" @close="slideOpen = false">
            <div v-if="detailLoading" class="py-10 text-center text-sm text-slate-500">Loading…</div>
            <div v-else-if="detail" class="space-y-4">
                <OperationsContextStats
                    heading="Case overview"
                    :stats="[
                        { label: 'Status', value: detail.dispute.status },
                        { label: 'Tier', value: `Tier ${detail.dispute.tier}` },
                        { label: 'Amount', value: formatMinor(detail.dispute.disputed_amount_minor) },
                    ]"
                    :chips="[
                        { label: 'Client', value: detail.parties?.client?.name },
                        { label: 'Freelancer', value: detail.parties?.freelancer?.name },
                    ]"
                />

                <button v-if="!detail.dispute.assigned_staff" type="button" class="w-full rounded-xl bg-primary-700 py-3 text-sm font-black text-white" :disabled="busy.claim" @click="claim">Claim dispute</button>

                <OperationsExpandableAction title="Contact party" icon="✉" submit-label="Send" :busy="busy.contact" @submit="contact">
                    <select v-model="contactForm.party" class="form-input">
                        <option value="client">Client</option>
                        <option value="freelancer">Freelancer</option>
                    </select>
                    <input v-model="contactForm.subject" class="form-input mt-3" placeholder="Subject" />
                    <textarea v-model="contactForm.body" class="form-input mt-3 min-h-24" placeholder="Message" />
                </OperationsExpandableAction>

                <OperationsExpandableAction title="Post notice" icon="📣" submit-label="Post" :busy="busy.notice" @submit="notice">
                    <input v-model="noticeForm.subject" class="form-input" placeholder="Subject" />
                    <textarea v-model="noticeForm.body" class="form-input mt-3 min-h-24" placeholder="Notice body" />
                </OperationsExpandableAction>

                <OperationsExpandableAction title="Request evidence" icon="📎" submit-label="Request" :busy="busy.evidence" @submit="evidence">
                    <textarea v-model="evidenceForm.body" class="form-input min-h-24" placeholder="What evidence do you need?" />
                </OperationsExpandableAction>

                <OperationsExpandableAction title="Internal note" icon="📝" tone="slate" submit-label="Save note" :busy="busy.note" @submit="note">
                    <textarea v-model="noteForm.body" class="form-input min-h-24" placeholder="Staff-only note" />
                </OperationsExpandableAction>

                <OperationsExpandableAction title="Mediation tier" icon="⚖" submit-label="Update tier" :busy="busy.tier" @submit="tier">
                    <select v-model="tierForm.tier" class="form-input">
                        <option :value="1">Tier 1 · Self-resolution</option>
                        <option :value="2">Tier 2 · Mediation</option>
                        <option :value="3">Tier 3 · Admin review</option>
                    </select>
                </OperationsExpandableAction>

                <OperationsExpandableAction v-if="detail.permissions?.can_issue_ruling" title="Issue ruling" icon="⚖" tone="rose" submit-label="Issue ruling" :busy="busy.ruling" @submit="ruling">
                    <input v-model="rulingForm.outcome" class="form-input" placeholder="Outcome code" />
                    <input v-model.number="rulingForm.client_share_percent" type="number" min="0" max="100" class="form-input mt-3" placeholder="Client share %" />
                    <textarea v-model="rulingForm.summary" class="form-input mt-3 min-h-28" placeholder="Ruling summary for both parties" />
                </OperationsExpandableAction>
                <p v-else class="rounded-xl border border-amber-100 bg-amber-50 p-3 text-xs font-semibold text-amber-900">Dispute value exceeds your ruling threshold — escalate to Super Admin.</p>

                <section v-if="detail.messages?.length" class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                    <p class="text-xs font-black uppercase text-slate-500">Thread</p>
                    <article v-for="msg in detail.messages" :key="msg.id" class="mt-2 rounded-xl bg-white p-3 text-sm">
                        <p class="text-[10px] font-black uppercase text-slate-400">{{ msg.author }} · {{ msg.kind }}</p>
                        <p class="mt-1 font-semibold text-slate-800">{{ msg.body }}</p>
                    </article>
                </section>
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

const props = defineProps({ queues: { type: Array, default: () => [] } });

const columns = [
    { key: 'quest', label: 'Quest' },
    { key: 'status', label: 'Status' },
    { key: 'tier', label: 'Tier' },
    { key: 'assigned_staff', label: 'Owner' },
];

const rawItems = ref([]);
const loading = ref(false);
const activeQueue = ref('open');
const slideOpen = ref(false);
const detailLoading = ref(false);
const detail = ref(null);
const selectedRow = ref(null);

const contactForm = reactive({ party: 'client', subject: '', body: '' });
const noticeForm = reactive({ subject: '', body: '' });
const evidenceForm = reactive({ body: '' });
const noteForm = reactive({ body: '' });
const tierForm = reactive({ tier: 2 });
const rulingForm = reactive({ outcome: '', client_share_percent: 50, summary: '' });

const queue = useClientQueue(() => rawItems.value);
const { busy, runAction } = useOperationsAction();
const slideTitle = computed(() => detail.value?.dispute?.quest || 'Dispute');

onMounted(() => loadQueue(props.queues[0] || { key: 'open' }));

function disputeKey(row) {
    return row.uuid || row.id;
}

async function loadQueue(queueDef) {
    activeQueue.value = queueDef.key;
    loading.value = true;
    try {
        const { data } = await window.axios.get(route('operations.api.disputes.listing'), { params: { queue: queueDef.key } });
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
        const { data } = await window.axios.get(route('operations.api.disputes.detail', disputeKey(row)));
        detail.value = data;
        tierForm.tier = data.dispute?.tier ?? 2;
    } finally {
        detailLoading.value = false;
    }
}

async function claim() {
    await runAction('claim', () => window.axios.post(route('operations.api.disputes.claim', disputeKey(selectedRow.value))), 'Dispute claimed.', () => openDetail(selectedRow.value));
}

async function contact() {
    await runAction('contact', () => window.axios.post(route('operations.api.disputes.contact', disputeKey(selectedRow.value)), contactForm), 'Message sent.');
}

async function notice() {
    await runAction('notice', () => window.axios.post(route('operations.api.disputes.notices', disputeKey(selectedRow.value)), noticeForm), 'Notice posted.');
}

async function evidence() {
    await runAction('evidence', () => window.axios.post(route('operations.api.disputes.evidence', disputeKey(selectedRow.value)), evidenceForm), 'Evidence requested.');
}

async function note() {
    await runAction('note', () => window.axios.post(route('operations.api.disputes.notes', disputeKey(selectedRow.value)), noteForm), 'Note saved.');
}

async function tier() {
    await runAction('tier', () => window.axios.patch(route('operations.api.disputes.tier', disputeKey(selectedRow.value)), tierForm), 'Tier updated.');
}

async function ruling() {
    await runAction('ruling', () => window.axios.post(route('operations.api.disputes.ruling', disputeKey(selectedRow.value)), rulingForm), 'Ruling issued.', async () => {
        slideOpen.value = false;
        await loadQueue(props.queues.find((q) => q.key === activeQueue.value) || { key: activeQueue.value });
    });
}

function formatMinor(minor) {
    if (!minor) return '—';
    return `₦${(Number(minor) / 100).toLocaleString()}`;
}
</script>
