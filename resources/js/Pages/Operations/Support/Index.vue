<template>
    <OperationsShell title="Support hub" subtitle="Start with global search, then open ticket, chat, or dispute queues. Lists are paginated with instant client search and sorting.">
        <section class="rounded-[1.75rem] border border-primary-100 bg-gradient-to-r from-primary-50 via-white to-sky-50 p-5 shadow-sm ring-1 ring-primary-100">
            <p class="text-xs font-black uppercase tracking-[0.25em] text-primary-700">Universal search</p>
            <form class="mt-3 flex flex-col gap-3 sm:flex-row" @submit.prevent="runSearch">
                <input
                    v-model="globalQuery"
                    type="search"
                    class="min-h-12 flex-1 rounded-2xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-900 outline-none focus:border-primary-400 focus:ring-2 focus:ring-primary-100"
                    placeholder="Quest title, ID, proposal ID, user email, phone, ticket ID, keywords…"
                />
                <button type="submit" class="rounded-2xl bg-primary-700 px-6 py-3 text-sm font-black text-white hover:bg-primary-800" :disabled="searching">
                    {{ searching ? 'Searching…' : 'Search' }}
                </button>
            </form>
            <p v-if="searchMessage" class="mt-2 text-sm font-semibold text-amber-800">{{ searchMessage }}</p>
            <ul v-if="searchResults.length" class="mt-4 space-y-2">
                <li v-for="(hit, idx) in searchResults" :key="`${hit.type}-${hit.id}-${idx}`">
                    <button type="button" class="flex w-full items-center justify-between rounded-xl border border-slate-200 bg-white px-4 py-3 text-left hover:bg-primary-50" @click="openSearchHit(hit)">
                        <span>
                            <span class="text-[10px] font-black uppercase tracking-wide text-primary-700">{{ hit.type }}</span>
                            <span class="mt-1 block text-sm font-bold text-slate-900">{{ hit.label }}</span>
                        </span>
                        <span class="text-xs font-semibold text-slate-500">{{ hit.meta }}</span>
                    </button>
                </li>
            </ul>
        </section>

        <div class="mt-6 flex flex-wrap gap-2">
            <button
                v-for="tab in supportTabs"
                :key="tab.key"
                type="button"
                class="rounded-xl px-4 py-2 text-xs font-black uppercase tracking-wide"
                :class="activeTab === tab.key ? 'bg-primary-700 text-white' : 'border border-slate-200 bg-white text-slate-700'"
                @click="switchTab(tab.key)"
            >
                {{ tab.label }}
            </button>
        </div>

        <div v-if="subQueues.length" class="mt-3 flex gap-2 overflow-x-auto pb-1">
            <button
                v-for="queue in subQueues"
                :key="queue.key"
                type="button"
                class="shrink-0 rounded-xl border px-3 py-2 text-xs font-black uppercase"
                :class="activeSubQueue === queue.key ? 'border-primary-600 bg-primary-50 text-primary-900' : 'border-slate-200 bg-white text-slate-700'"
                @click="loadSubQueue(queue.key)"
            >
                {{ queue.label }}
            </button>
        </div>

        <OperationsQueueTable
            class="mt-4"
            :columns="activeColumns"
            :rows="queue.pageItems.value"
            :loading="loading"
            v-model:search="queue.search.value"
            v-model:per-page="queue.perPage.value"
            :page="queue.page.value"
            :total="queue.total.value"
            :total-pages="queue.totalPages.value"
            :sort-key="queue.sortKey.value"
            :sort-dir="queue.sortDir.value"
            :empty-message="tableEmptyMessage"
            @sort="queue.setSort"
            @page="(p) => (queue.page.value = p)"
            @open="openRow"
        >
            <template #actions="{ row }">
                <button type="button" class="rounded-lg bg-primary-700 px-2 py-1 text-[10px] font-black uppercase text-white" @click.stop="openRow(row)">Open</button>
            </template>
        </OperationsQueueTable>

        <OperationsSlideOver :open="slideOpen" :title="slideTitle" subtitle="Support action panel" eyebrow="Support" @close="slideOpen = false">
            <div v-if="activeTab === 'tickets' && selectedRow" class="space-y-4">
                <p class="text-sm font-semibold text-slate-700">{{ selectedRow.subject }}</p>
                <select v-model="ticketStatusForm.status" class="form-input">
                    <option value="open">Open</option>
                    <option value="waiting_on_customer">Waiting on customer</option>
                    <option value="waiting_on_internal">Waiting on internal</option>
                    <option value="in_review">In review</option>
                    <option value="resolved">Resolved</option>
                    <option value="closed">Closed</option>
                </select>
                <textarea v-model="ticketStatusForm.reason" class="form-input min-h-24" placeholder="Status update reason" />
                <button type="button" class="rounded-xl bg-primary-700 px-4 py-2 text-sm font-black text-white" @click="updateTicket">Update ticket</button>
            </div>
            <div v-else-if="selectedRow" class="text-sm font-semibold text-slate-600">
                Open the linked record from search or use moderation centre for full actions.
            </div>
        </OperationsSlideOver>
    </OperationsShell>
</template>

<script setup>
import OperationsQueueTable from '@/Pages/Operations/Components/OperationsQueueTable.vue';
import OperationsSlideOver from '@/Pages/Operations/Components/OperationsSlideOver.vue';
import OperationsShell from '@/Layouts/OperationsShell.vue';
import { useClientQueue } from '@/composables/useClientQueue';
import { computed, onMounted, reactive, ref } from 'vue';

const props = defineProps({
    ticket_queues: { type: Array, required: true },
    dispute_queues: { type: Array, required: true },
    support_tables_ready: { type: Boolean, default: true },
});

const supportTabs = [
    { key: 'tickets', label: 'Tickets' },
    { key: 'chats', label: 'CS chats waiting' },
    { key: 'disputes', label: 'Disputes' },
];

const activeTab = ref('tickets');
const activeSubQueue = ref(props.ticket_queues[0]?.key ?? 'my_tickets');
const rawItems = ref([]);
const loading = ref(false);
const globalQuery = ref('');
const searchResults = ref([]);
const searchMessage = ref('');
const searching = ref(false);
const slideOpen = ref(false);
const selectedRow = ref(null);
const ticketStatusForm = reactive({ status: 'open', reason: '' });

const queue = useClientQueue(() => rawItems.value, {
    defaultSortKey: 'updated_at',
    searchFields: ['id', 'subject', 'status', 'priority', 'user.name', 'user.email', 'quest', 'reference_code'],
});

const subQueues = computed(() => {
    if (activeTab.value === 'tickets') return props.ticket_queues;
    if (activeTab.value === 'disputes') return props.dispute_queues;
    return [];
});

const activeColumns = computed(() => {
    if (activeTab.value === 'disputes') {
        return [
            { key: 'id', label: 'ID', sortable: true },
            { key: 'tier', label: 'Tier', sortable: true },
            { key: 'status', label: 'Status', sortable: true },
            { key: 'quest', label: 'Quest', sortable: true, path: 'quest.title' },
            { key: 'updated_at', label: 'Updated', sortable: true, format: 'date' },
        ];
    }

    return [
        { key: 'id', label: 'ID', sortable: true },
        { key: 'subject', label: 'Subject', sortable: true },
        { key: 'status', label: 'Status', sortable: true },
        { key: 'priority', label: 'Priority', sortable: true },
        { key: 'updated_at', label: 'Updated', sortable: true, format: 'date' },
    ];
});

const tableEmptyMessage = computed(() =>
    props.support_tables_ready ? 'Select a queue to load items.' : 'Support tables are not migrated yet.',
);

const slideTitle = computed(() => selectedRow.value?.subject || selectedRow.value?.quest?.title || (selectedRow.value ? `#${selectedRow.value.id}` : ''));

onMounted(() => loadSubQueue(activeSubQueue.value));

function switchTab(key) {
    activeTab.value = key;
    const first = subQueues.value[0];
    activeSubQueue.value = first?.key ?? '';
    if (first) {
        loadSubQueue(first.key);
    } else if (key === 'chats') {
        loadChats();
    }
}

async function loadSubQueue(key) {
    activeSubQueue.value = key;
    loading.value = true;
    rawItems.value = [];

    try {
        if (activeTab.value === 'tickets') {
            const { data } = await window.axios.get(route('operations.api.support.tickets'), { params: { queue: key } });
            rawItems.value = data.items ?? [];
        } else if (activeTab.value === 'disputes') {
            const { data } = await window.axios.get(route('operations.api.support.disputes'), { params: { queue: key } });
            rawItems.value = data.items ?? [];
        }
    } finally {
        loading.value = false;
    }
}

async function loadChats() {
    loading.value = true;
    try {
        const { data } = await window.axios.get(route('operations.api.support.chats'));
        rawItems.value = (data.items ?? []).map((chat) => ({
            id: chat.thread_id || chat.id,
            subject: chat.quest || 'Support chat',
            status: 'assigned',
            priority: 'medium',
            updated_at: chat.last_message_at,
            user: chat.client || chat.freelancer,
        }));
    } finally {
        loading.value = false;
    }
}

async function runSearch() {
    searching.value = true;
    searchResults.value = [];
    searchMessage.value = '';
    try {
        const { data } = await window.axios.get(route('operations.api.support.search'), { params: { q: globalQuery.value } });
        searchResults.value = data.results ?? [];
        searchMessage.value = data.message ?? '';
    } finally {
        searching.value = false;
    }
}

function openSearchHit(hit) {
    if (hit.type === 'quest') {
        window.location.href = route('operations.moderation.index', { module: 'quests' });
        return;
    }
    if (hit.type === 'proposal') {
        window.location.href = route('operations.moderation.index', { module: 'proposals' });
        return;
    }
    if (hit.type === 'user') {
        window.location.href = route('operations.users.index', { q: hit.meta });
    }
}

function openRow(row) {
    selectedRow.value = row;
    ticketStatusForm.status = row.status || 'open';
    ticketStatusForm.reason = '';
    slideOpen.value = true;
}

async function updateTicket() {
    if (!selectedRow.value) return;
    await window.axios.patch(route('operations.support.tickets.status', selectedRow.value.id), ticketStatusForm);
    slideOpen.value = false;
    await loadSubQueue(activeSubQueue.value);
}
</script>

<style scoped>
.form-input {
    @apply w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 shadow-sm outline-none focus:border-primary-400 focus:ring-2 focus:ring-primary-100;
}
</style>
