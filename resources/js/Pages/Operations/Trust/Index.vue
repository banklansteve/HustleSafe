<template>
    <OperationsShell title="Trust & risk monitoring" subtitle="Live risk scores, fraud network graph, watchlist, and activity feed.">
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
                <span v-if="t.key === 'queue' && queueCount" class="ml-1 rounded-full bg-white/20 px-1.5">{{ queueCount }}</span>
            </button>
        </div>

        <OperationsQueueTable
            v-if="tab === 'queue'"
            :columns="queueColumns"
            :rows="queueRows"
            :loading="loading"
            v-model:search="queueSearch"
            :page="queuePage"
            :total="queueTotal"
            :total-pages="queueTotalPages"
            empty-message="No users in the risk queue."
            @page="(p) => { queuePage = p; loadQueue(); }"
            @open="openUser"
        >
            <template #cell-name="{ row }">
                <span class="font-semibold text-slate-950">{{ row.name }}</span>
                <span v-if="row.on_watchlist" class="mt-0.5 block text-[10px] font-black uppercase text-primary-700">On watchlist</span>
            </template>
            <template #cell-tier="{ row }">
                <span class="rounded-full px-2 py-0.5 text-[10px] font-black uppercase" :class="tierClass(row.tier)">{{ row.tier }}</span>
            </template>
            <template #cell-composite_score="{ row }">
                <span class="font-black text-slate-900">{{ row.composite_score }}</span>
            </template>
            <template #actions="{ row }">
                <button type="button" class="rounded-lg bg-primary-700 px-2 py-1 text-[10px] font-black uppercase text-white" @click.stop="openUser(row)">Investigate</button>
            </template>
        </OperationsQueueTable>

        <OperationsQueueTable
            v-else-if="tab === 'watchlist'"
            :columns="watchColumns"
            :rows="watchRows"
            :loading="loading"
            empty-message="Watchlist is empty."
            @open="openWatchItem"
        >
            <template #cell-title="{ row }">
                <span class="font-semibold text-slate-950">{{ row.title }}</span>
                <span class="block text-xs text-slate-500">{{ row.subtitle }}</span>
                <span v-if="row.in_risk_queue" class="mt-1 inline-block rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-black uppercase text-rose-800">In risk queue</span>
            </template>
            <template #cell-severity="{ row }">
                <span class="rounded-full px-2 py-0.5 text-[10px] font-black uppercase" :class="severityClass(row.severity)">{{ row.severity }}</span>
            </template>
            <template #actions="{ row }">
                <button type="button" class="rounded-lg bg-primary-700 px-2 py-1 text-[10px] font-black uppercase text-white" @click.stop="openWatchItem(row)">Open</button>
            </template>
        </OperationsQueueTable>

        <div v-else-if="tab === 'feed'" class="space-y-4">
            <article v-for="group in feedGroups" :key="group.user.id" class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="font-display text-lg font-black text-slate-950">{{ group.user.name }}</h3>
                    <button type="button" class="text-xs font-black uppercase text-primary-700" @click="openUserById(group.user.id)">Profile</button>
                </div>
                <ul class="mt-3 space-y-2">
                    <li v-for="ev in group.events" :key="ev.id" class="rounded-xl border border-slate-100 bg-slate-50 p-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm font-black text-slate-900">{{ ev.title }}</span>
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-black uppercase" :class="severityClass(ev.severity)">{{ ev.severity }}</span>
                        </div>
                        <p v-if="ev.summary" class="mt-1 text-sm text-slate-600">{{ ev.summary }}</p>
                        <a v-if="ev.action_url" :href="ev.action_url" class="mt-2 inline-block text-xs font-black uppercase text-primary-700">Open →</a>
                    </li>
                </ul>
            </article>
            <p v-if="!feedGroups.length && !loading" class="text-sm font-semibold text-slate-500">No watchlist activity yet.</p>
        </div>

        <div v-else class="grid gap-3 sm:grid-cols-2">
            <article v-for="cluster in clusters" :key="cluster.id" class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                <p class="text-[10px] font-black uppercase text-primary-700">{{ cluster.type?.replace('_', ' ') }}</p>
                <h3 class="mt-1 font-display text-lg font-black text-slate-950">{{ cluster.label }}</h3>
                <p class="mt-1 text-sm font-semibold text-slate-600">{{ cluster.signal }}</p>
            </article>
            <p v-if="!clusters.length && !loading" class="text-sm font-semibold text-slate-500">No clusters detected.</p>
        </div>

        <OperationsSlideOver :open="detailOpen" :title="detailUser?.name || 'Risk profile'" subtitle="Score breakdown & fraud network" eyebrow="Trust" @close="detailOpen = false">
            <div v-if="detailUser" class="space-y-6">
                <RiskScoreBreakdownBar :score="detailUser.composite_score" :tier="detailUser.tier" :breakdown="detailUser.breakdown" />
                <div>
                    <h4 class="text-xs font-black uppercase text-slate-500">Fraud network</h4>
                    <FraudNetworkGraph :graph="networkGraph" :loading="networkLoading" @node-select="pivotUser" />
                </div>
                <form v-if="!detailUser.on_watchlist" class="space-y-3 rounded-2xl border border-slate-100 bg-slate-50 p-4" @submit.prevent="submitWatch">
                    <p class="text-xs font-black uppercase text-slate-600">Add to watchlist</p>
                    <textarea v-model="watchForm.reason" required maxlength="300" rows="2" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Reason (max 300 chars)" />
                    <div class="grid gap-2 sm:grid-cols-2">
                        <input v-model="watchForm.review_by_date" type="date" required class="rounded-xl border border-slate-200 px-3 py-2 text-sm" />
                        <select v-model="watchForm.severity" class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold">
                            <option value="observe">Observe</option>
                            <option value="concern">Concern</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    <select v-model="watchForm.visibility" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold">
                        <option value="personal">Personal watchlist</option>
                        <option value="team">Team-shared</option>
                    </select>
                    <button type="submit" class="w-full rounded-xl bg-primary-700 py-2 text-sm font-black text-white" :disabled="watchBusy">Add to watchlist</button>
                </form>
            </div>
        </OperationsSlideOver>
    </OperationsShell>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import OperationsQueueTable from '@/Pages/Operations/Components/OperationsQueueTable.vue';
import OperationsSlideOver from '@/Pages/Operations/Components/OperationsSlideOver.vue';
import OperationsShell from '@/Layouts/OperationsShell.vue';
import RiskScoreBreakdownBar from '@/Components/TrustRisk/RiskScoreBreakdownBar.vue';
import FraudNetworkGraph from '@/Components/TrustRisk/FraudNetworkGraph.vue';

const props = defineProps({
    initialUserId: { type: Number, default: null },
    thresholds: { type: Object, default: () => ({}) },
    queueCount: { type: Number, default: 0 },
});

const tabs = [
    { key: 'queue', label: 'Risk queue' },
    { key: 'watchlist', label: 'Watchlist' },
    { key: 'feed', label: 'Activity feed' },
    { key: 'clusters', label: 'Clusters' },
];

const tab = ref('queue');
const loading = ref(false);
const queueRows = ref([]);
const queuePage = ref(1);
const queueTotal = ref(0);
const queueTotalPages = ref(1);
const queueSearch = ref('');
const watchRows = ref([]);
const feedGroups = ref([]);
const clusters = ref([]);
const detailOpen = ref(false);
const detailUser = ref(null);
const networkGraph = ref(null);
const networkLoading = ref(false);
const watchBusy = ref(false);
const watchForm = ref({ reason: '', review_by_date: '', severity: 'observe', visibility: 'personal' });

const queueColumns = [
    { key: 'name', label: 'User' },
    { key: 'composite_score', label: 'Score' },
    { key: 'tier', label: 'Tier' },
];

const watchColumns = [
    { key: 'title', label: 'User' },
    { key: 'severity', label: 'Severity' },
    { key: 'review_by_date', label: 'Review by' },
    { key: 'visibility', label: 'Visibility' },
];

onMounted(async () => {
    const params = new URLSearchParams(window.location.search);
    if (params.get('tab')) tab.value = params.get('tab');
    await loadQueue();
    await loadWatchlist();
    if (props.initialUserId) {
        await openUserById(props.initialUserId);
    }
});

async function switchTab(key) {
    tab.value = key;
    if (key === 'feed') await loadFeed();
    if (key === 'clusters') await loadClusters();
    if (key === 'watchlist') await loadWatchlist();
    if (key === 'queue') await loadQueue();
}

async function loadQueue() {
    loading.value = true;
    try {
        const { data } = await window.axios.get(route('operations.api.trust.risk-queue'), {
            params: { page: queuePage.value, q: queueSearch.value },
        });
        queueRows.value = (data.items || []).map((r) => ({ ...r, on_watchlist: false }));
        queueTotal.value = data.meta?.total || 0;
        queueTotalPages.value = data.meta?.last_page || 1;
    } finally {
        loading.value = false;
    }
}

async function loadWatchlist() {
    loading.value = true;
    try {
        const { data } = await window.axios.get(route('operations.api.trust.watchlist'));
        watchRows.value = data.items || [];
    } finally {
        loading.value = false;
    }
}

async function loadFeed() {
    loading.value = true;
    try {
        const { data } = await window.axios.get(route('operations.api.trust.feed'));
        feedGroups.value = data.groups || [];
    } finally {
        loading.value = false;
    }
}

async function loadClusters() {
    loading.value = true;
    try {
        const { data } = await window.axios.get(route('operations.api.trust.clusters'));
        clusters.value = data.clusters || [];
    } finally {
        loading.value = false;
    }
}

async function openUser(row) {
    await openUserById(row.user_id || row.id);
}

async function openUserById(userId) {
    detailOpen.value = true;
    networkLoading.value = true;
    try {
        const { data } = await window.axios.get(route('operations.api.trust.users.show', userId));
        detailUser.value = {
            ...data.user,
            ...data.profile,
            on_watchlist: data.on_watchlist?.on_watchlist,
            in_risk_queue: data.on_watchlist?.in_risk_queue,
        };
        const net = await window.axios.get(route('operations.api.trust.users.network', userId));
        networkGraph.value = net.data;
    } finally {
        networkLoading.value = false;
    }
}

async function pivotUser(userId) {
    await openUserById(userId);
}

async function submitWatch() {
    if (!detailUser.value?.id) return;
    watchBusy.value = true;
    try {
        await window.axios.post(route('operations.api.trust.watchlist.store'), {
            user_id: detailUser.value.id,
            ...watchForm.value,
        });
        detailUser.value.on_watchlist = true;
        await loadWatchlist();
    } finally {
        watchBusy.value = false;
    }
}

function openWatchItem(row) {
    openUserById(row.watchable_id);
}

function tierClass(tier) {
    return { low: 'bg-emerald-100 text-emerald-800', medium: 'bg-amber-100 text-amber-900', high: 'bg-orange-100 text-orange-900', critical: 'bg-rose-100 text-rose-900' }[tier] || 'bg-slate-100 text-slate-700';
}

function severityClass(s) {
    return { observe: 'bg-slate-100 text-slate-700', concern: 'bg-amber-100 text-amber-900', urgent: 'bg-rose-100 text-rose-800' }[s] || 'bg-slate-100 text-slate-700';
}
</script>
