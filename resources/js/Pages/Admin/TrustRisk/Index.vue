<template>
    <AdminShell title="Trust & risk monitoring" subtitle="Consolidated watchlists, risk queue, fraud network, and investigation notes.">
        <div class="mb-4 flex flex-wrap gap-2">
            <button
                v-for="t in tabs"
                :key="t.key"
                type="button"
                class="rounded-xl px-4 py-2 text-xs font-black uppercase"
                :class="tab === t.key ? 'bg-primary-700 text-white' : 'border border-slate-200 bg-white text-slate-700'"
                @click="switchTab(t.key)"
            >
                {{ t.label }}
            </button>
            <Link :href="route('admin.settings.index', { section: 'trust_risk' })" class="ml-auto rounded-xl border border-primary-200 bg-primary-50 px-3 py-2 text-xs font-black uppercase text-primary-800">Score thresholds</Link>
        </div>

        <OperationsQueueTable
            v-if="tab === 'queue'"
            :columns="queueColumns"
            :rows="queueRows"
            :loading="loading"
            :page="queuePage"
            :total="queueTotal"
            :total-pages="queueTotalPages"
            empty-message="Risk queue is empty."
            @page="(p) => { queuePage = p; loadQueue(); }"
            @open="openUser"
        >
            <template #cell-tier="{ row }">
                <span class="rounded-full px-2 py-0.5 text-[10px] font-black uppercase" :class="tierClass(row.tier)">{{ row.tier }}</span>
            </template>
            <template #actions="{ row }">
                <button type="button" class="rounded-lg bg-primary-700 px-2 py-1 text-[10px] font-black uppercase text-white" @click.stop="openUser(row)">Open</button>
            </template>
        </OperationsQueueTable>

        <OperationsQueueTable
            v-else-if="tab === 'watchlists'"
            :columns="allWatchColumns"
            :rows="watchRows"
            :loading="loading"
            empty-message="No watchlist entries."
            @open="(row) => openUserById(row.watchable_id)"
        >
            <template #cell-title="{ row }">
                <span class="font-semibold text-slate-950">{{ row.title }}</span>
                <span v-if="row.in_risk_queue" class="mt-1 block text-[10px] font-black uppercase text-rose-700">In risk queue</span>
            </template>
            <template #cell-created_by="{ row }">
                <span class="text-xs font-semibold text-slate-600">{{ row.created_by?.name || '—' }}</span>
            </template>
        </OperationsQueueTable>

        <div v-else-if="tab === 'feed'" class="space-y-4">
            <article v-for="group in feedGroups" :key="group.user.id" class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                <h3 class="font-display text-lg font-black text-slate-950">{{ group.user.name }}</h3>
                <ul class="mt-3 space-y-2">
                    <li v-for="ev in group.events" :key="ev.id" class="rounded-xl bg-slate-50 p-3 text-sm">
                        <span class="font-black">{{ ev.title }}</span>
                        <a v-if="ev.action_url" :href="ev.action_url" class="ml-2 text-xs font-black uppercase text-primary-700">Open</a>
                    </li>
                </ul>
            </article>
        </div>

        <OperationsSlideOver :open="detailOpen" :title="detailUser?.name || 'Risk'" subtitle="Network graph & notes" eyebrow="Trust" @close="detailOpen = false">
            <div v-if="detailUser" class="space-y-6">
                <RiskScoreBreakdownBar :score="detailUser.composite_score" :tier="detailUser.tier" :breakdown="detailUser.breakdown" />
                <FraudNetworkGraph :graph="networkGraph" :loading="networkLoading" @node-select="pivotUser" />
                <form class="space-y-2 rounded-2xl border border-slate-100 bg-slate-50 p-4" @submit.prevent="saveNote">
                    <p class="text-xs font-black uppercase text-slate-600">Investigation note (Super Admin)</p>
                    <textarea v-model="noteText" rows="3" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Annotate this node…" />
                    <button type="submit" class="rounded-xl bg-primary-700 px-4 py-2 text-xs font-black uppercase text-white" :disabled="noteBusy">Save note</button>
                </form>
                <ul v-if="notes.length" class="space-y-2 text-sm">
                    <li v-for="n in notes" :key="n.id" class="rounded-xl border border-slate-100 p-3">
                        <p class="font-semibold text-slate-800">{{ n.note }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ n.author }} · {{ n.created_at }}</p>
                    </li>
                </ul>
            </div>
        </OperationsSlideOver>
    </AdminShell>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import AdminShell from '@/Layouts/AdminShell.vue';
import OperationsQueueTable from '@/Pages/Operations/Components/OperationsQueueTable.vue';
import OperationsSlideOver from '@/Pages/Operations/Components/OperationsSlideOver.vue';
import RiskScoreBreakdownBar from '@/Components/TrustRisk/RiskScoreBreakdownBar.vue';
import FraudNetworkGraph from '@/Components/TrustRisk/FraudNetworkGraph.vue';

const props = defineProps({
    initialUserId: { type: Number, default: null },
    thresholds: { type: Object, default: () => ({}) },
    queueCount: { type: Number, default: 0 },
});

const tabs = [
    { key: 'queue', label: 'Risk queue' },
    { key: 'watchlists', label: 'All watchlists' },
    { key: 'feed', label: 'Activity feed' },
];

const tab = ref('queue');
const loading = ref(false);
const queueRows = ref([]);
const queuePage = ref(1);
const queueTotal = ref(0);
const queueTotalPages = ref(1);
const watchRows = ref([]);
const feedGroups = ref([]);
const detailOpen = ref(false);
const detailUser = ref(null);
const networkGraph = ref(null);
const networkLoading = ref(false);
const notes = ref([]);
const noteText = ref('');
const noteBusy = ref(false);

const queueColumns = [
    { key: 'name', label: 'User' },
    { key: 'composite_score', label: 'Score' },
    { key: 'tier', label: 'Tier' },
];

const allWatchColumns = [
    { key: 'title', label: 'User' },
    { key: 'severity', label: 'Severity' },
    { key: 'visibility', label: 'Visibility' },
    { key: 'created_by', label: 'Created by' },
];

onMounted(async () => {
    await loadQueue();
    if (props.initialUserId) await openUserById(props.initialUserId);
});

async function switchTab(key) {
    tab.value = key;
    if (key === 'watchlists') await loadWatchlists();
    if (key === 'feed') await loadFeed();
    if (key === 'queue') await loadQueue();
}

async function loadQueue() {
    loading.value = true;
    try {
        const { data } = await window.axios.get(route('admin.api.trust-risk.risk-queue'), { params: { page: queuePage.value } });
        queueRows.value = data.items || [];
        queueTotal.value = data.meta?.total || 0;
        queueTotalPages.value = data.meta?.last_page || 1;
    } finally {
        loading.value = false;
    }
}

async function loadWatchlists() {
    loading.value = true;
    try {
        const { data } = await window.axios.get(route('admin.api.trust-risk.watchlists'));
        watchRows.value = data.items || [];
    } finally {
        loading.value = false;
    }
}

async function loadFeed() {
    loading.value = true;
    try {
        const { data } = await window.axios.get(route('admin.api.trust-risk.feed'));
        feedGroups.value = data.groups || [];
    } finally {
        loading.value = false;
    }
}

async function openUser(row) {
    await openUserById(row.user_id);
}

async function openUserById(userId) {
    detailOpen.value = true;
    networkLoading.value = true;
    try {
        const { data } = await window.axios.get(route('admin.api.trust-risk.users.show', userId));
        detailUser.value = { ...data.user, ...data.profile };
        const [net, noteRes] = await Promise.all([
            window.axios.get(route('admin.api.trust-risk.users.network', userId)),
            window.axios.get(route('admin.api.trust-risk.users.network-notes', userId)),
        ]);
        networkGraph.value = net.data;
        notes.value = noteRes.data.notes || [];
    } finally {
        networkLoading.value = false;
    }
}

async function pivotUser(userId) {
    await openUserById(userId);
}

async function saveNote() {
    if (!detailUser.value?.id || !noteText.value.trim()) return;
    noteBusy.value = true;
    try {
        const { data } = await window.axios.post(route('admin.api.trust-risk.users.network-notes.store', detailUser.value.id), { note: noteText.value });
        notes.value.unshift(data.note);
        noteText.value = '';
    } finally {
        noteBusy.value = false;
    }
}

function tierClass(tier) {
    return { low: 'bg-emerald-100 text-emerald-800', medium: 'bg-amber-100 text-amber-900', high: 'bg-orange-100 text-orange-900', critical: 'bg-rose-100 text-rose-800' }[tier] || 'bg-slate-100';
}
</script>
