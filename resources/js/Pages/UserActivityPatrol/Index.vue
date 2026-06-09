<template>
    <component :is="shellComponent" title="User activity patrol" subtitle="Live dashboard of auto-detected user anomalies. Smart detection — not manual flagging.">
        <div class="mb-4 flex flex-wrap gap-2">
            <button
                type="button"
                class="rounded-xl px-3 py-2 text-xs font-black uppercase"
                :class="quickFilter === 'critical' ? 'bg-red-600 text-white' : 'border border-slate-200 bg-white text-slate-700'"
                @click="applyQuick('critical')"
            >
                Critical issues ({{ quick_counts.critical_open }})
            </button>
            <button
                type="button"
                class="rounded-xl px-3 py-2 text-xs font-black uppercase"
                :class="quickFilter === 'mine' ? 'bg-primary-700 text-white' : 'border border-slate-200 bg-white text-slate-700'"
                @click="applyQuick('mine')"
            >
                My assigned ({{ quick_counts.my_assigned }})
            </button>
            <button
                type="button"
                class="rounded-xl px-3 py-2 text-xs font-black uppercase"
                :class="quickFilter === 'needs_review' ? 'bg-amber-600 text-white' : 'border border-slate-200 bg-white text-slate-700'"
                @click="applyQuick('needs_review')"
            >
                Needs review ({{ quick_counts.needs_review }})
            </button>
            <button v-if="quickFilter" type="button" class="rounded-xl border px-3 py-2 text-xs font-bold text-slate-500" @click="applyQuick(null)">Clear quick filter</button>
        </div>

        <div class="mb-4 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950 lg:grid-cols-4">
            <div class="lg:col-span-2">
                <label class="text-[10px] font-black uppercase text-slate-500">Search</label>
                <input v-model="filters.q" type="search" placeholder="Username, user ID, email…" class="mt-1 w-full rounded-xl border-slate-200 text-sm dark:border-slate-700 dark:bg-slate-900" @keyup.enter="reloadListing" />
            </div>
            <div>
                <label class="text-[10px] font-black uppercase text-slate-500">Risk level</label>
                <select v-model="filters.risk_level" class="mt-1 w-full rounded-xl border-slate-200 text-sm dark:border-slate-700 dark:bg-slate-900" @change="reloadListing">
                    <option value="">All levels</option>
                    <option v-for="opt in filter_options.risk_levels" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
            </div>
            <div>
                <label class="text-[10px] font-black uppercase text-slate-500">Status</label>
                <select v-model="filters.status" class="mt-1 w-full rounded-xl border-slate-200 text-sm dark:border-slate-700 dark:bg-slate-900" @change="reloadListing">
                    <option value="">Open queue</option>
                    <option v-for="opt in filter_options.statuses" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
            </div>
            <div class="lg:col-span-2">
                <label class="text-[10px] font-black uppercase text-slate-500">Anomaly type</label>
                <select v-model="filters.anomaly_type" class="mt-1 w-full rounded-xl border-slate-200 text-sm dark:border-slate-700 dark:bg-slate-900" @change="reloadListing">
                    <option value="">All types</option>
                    <option v-for="opt in filter_options.anomaly_types" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
            </div>
            <div>
                <label class="text-[10px] font-black uppercase text-slate-500">Date range</label>
                <select v-model="filters.range" class="mt-1 w-full rounded-xl border-slate-200 text-sm dark:border-slate-700 dark:bg-slate-900" @change="reloadListing">
                    <option v-for="opt in filter_options.date_ranges" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
            </div>
            <div class="flex items-end">
                <button type="button" class="w-full rounded-xl bg-primary-700 px-4 py-2.5 text-xs font-black uppercase text-white" @click="reloadListing">Apply filters</button>
            </div>
        </div>

        <div v-if="toast" class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ toast }}</div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500 dark:bg-slate-900">
                        <tr>
                            <th class="px-4 py-3">User</th>
                            <th class="px-4 py-3">Tier</th>
                            <th class="px-4 py-3">Anomaly</th>
                            <th class="px-4 py-3">Risk</th>
                            <th class="px-4 py-3">Detected</th>
                            <th class="px-4 py-3">Summary</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading">
                            <td colspan="8" class="px-4 py-10 text-center text-slate-500">
                                <span class="inline-block h-6 w-6 animate-spin rounded-full border-2 border-primary-600 border-t-transparent" />
                            </td>
                        </tr>
                        <tr v-else-if="!rows.length">
                            <td colspan="8" class="px-4 py-10 text-center font-semibold text-slate-500">No anomalies match your filters.</td>
                        </tr>
                        <tr v-for="row in rows" :key="row.id" class="border-b border-slate-100 hover:bg-slate-50/80 dark:border-slate-800 dark:hover:bg-slate-900/50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <img :src="row.avatar_url || defaultAvatar(row.fullname)" alt="" class="h-8 w-8 rounded-lg object-cover" />
                                    <div>
                                        <p class="font-bold text-slate-900 dark:text-white">{{ row.fullname }}</p>
                                        <p class="text-xs text-slate-500">@{{ row.username }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 font-semibold">T{{ row.tier || 0 }}</td>
                            <td class="px-4 py-3 font-semibold">{{ row.anomaly_label }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-black uppercase" :class="riskBadgeClass(row.risk_level)">
                                    {{ riskEmoji(row.risk_level) }} {{ row.risk_level }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-500">{{ row.detected_ago }}</td>
                            <td class="px-4 py-3 max-w-xs truncate text-xs text-slate-600">{{ row.summary }}</td>
                            <td class="px-4 py-3">
                                <span class="text-xs font-bold uppercase text-slate-600">{{ row.status_label }}</span>
                                <p v-if="row.assigned_to" class="text-[10px] text-slate-400">{{ row.assigned_to.name }}</p>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button type="button" class="rounded-lg bg-primary-700 px-3 py-1.5 text-xs font-black uppercase text-white" @click="openReview(row)">View</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="meta.total > meta.per_page" class="flex items-center justify-between border-t px-4 py-3 text-xs text-slate-500">
                <p>{{ meta.total }} total · page {{ meta.current_page }} / {{ meta.last_page }}</p>
                <div class="flex gap-2">
                    <button type="button" class="rounded-lg border px-3 py-1 disabled:opacity-40" :disabled="meta.current_page <= 1" @click="changePage(meta.current_page - 1)">Prev</button>
                    <button type="button" class="rounded-lg border px-3 py-1 disabled:opacity-40" :disabled="meta.current_page >= meta.last_page" @click="changePage(meta.current_page + 1)">Next</button>
                </div>
            </div>
        </div>

    </component>

    <UserActivityReviewPanel
        :open="panelOpen"
        :user-id="selectedRow?.user_id"
        :flag-id="selectedRow?.id"
        :route-prefix="route_prefix"
        :is-super-admin="is_super_admin"
        :capabilities="capabilities"
        :warning-templates="warning_templates"
        :message-templates="message_templates"
        @close="closePanel"
        @action-done="onActionDone"
    />
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import AdminShell from '@/Layouts/AdminShell.vue';
import OperationsShell from '@/Layouts/OperationsShell.vue';
import UserActivityReviewPanel from '@/Pages/UserActivityPatrol/Components/UserActivityReviewPanel.vue';
import { useUserActivityPatrolEcho } from '@/composables/useUserActivityPatrolEcho.js';

const props = defineProps({
    listing: { type: Object, required: true },
    filter_options: { type: Object, required: true },
    quick_counts: { type: Object, required: true },
    is_super_admin: { type: Boolean, default: false },
    capabilities: { type: Object, required: true },
    warning_templates: { type: Array, default: () => [] },
    message_templates: { type: Array, default: () => [] },
    route_prefix: { type: String, default: 'operations' },
    use_admin_shell: { type: Boolean, default: false },
});

const page = usePage();
const shellComponent = computed(() => (props.use_admin_shell ? AdminShell : OperationsShell));
const rows = ref(props.listing.items ?? []);
const meta = ref(props.listing.meta ?? { total: 0, per_page: 25, current_page: 1, last_page: 1 });
const loading = ref(false);
const quickFilter = ref(null);
const panelOpen = ref(false);
const selectedRow = ref(null);
const toast = ref(null);

const filters = ref({
    q: '',
    risk_level: '',
    status: '',
    anomaly_type: '',
    range: '30d',
});

function routeName(name) {
    return `${props.route_prefix}.${name}`;
}

function listingUrl() {
    return route(routeName('api.user-activity-patrol.listing'));
}

function defaultAvatar(name) {
    return `https://ui-avatars.com/api/?name=${encodeURIComponent(name || 'U')}&background=6366f1&color=fff`;
}

function riskBadgeClass(level) {
    return {
        critical: 'bg-red-100 text-red-800',
        high: 'bg-orange-100 text-orange-800',
        medium: 'bg-amber-100 text-amber-800',
        low: 'bg-emerald-100 text-emerald-800',
    }[level] || 'bg-slate-100 text-slate-700';
}

function riskEmoji(level) {
    return { critical: '🔴', high: '🟠', medium: '🟡', low: '🟢' }[level] || '';
}

async function reloadListing(pageNum = 1, { silent = false } = {}) {
    if (!silent) {
        loading.value = true;
    }
    try {
        const params = {
            page: pageNum,
            q: filters.value.q || undefined,
            risk_levels: filters.value.risk_level ? [filters.value.risk_level] : undefined,
            statuses: filters.value.status ? [filters.value.status] : undefined,
            anomaly_types: filters.value.anomaly_type ? [filters.value.anomaly_type] : undefined,
            range: filters.value.range,
            quick: quickFilter.value || undefined,
        };
        const { data } = await axios.get(listingUrl(), { params });
        rows.value = data.items;
        meta.value = data.meta;
    } finally {
        if (!silent) {
            loading.value = false;
        }
    }
}

function applyQuick(key) {
    quickFilter.value = key;
    reloadListing(1);
}

function changePage(p) {
    reloadListing(p);
}

function openReview(row) {
    selectedRow.value = row;
    panelOpen.value = true;
}

function closePanel() {
    panelOpen.value = false;
    selectedRow.value = null;
}

function onActionDone(message) {
    toast.value = message;
    reloadListing(meta.value.current_page);
    setTimeout(() => { toast.value = null; }, 4000);
}

watch(() => page.props.flash?.success, (msg) => {
    if (msg) {
        onActionDone(msg);
    }
});

onMounted(() => {
    if (page.props.flash?.success) {
        toast.value = page.props.flash.success;
    }
});

useUserActivityPatrolEcho(
    computed(() => page.props.broadcast),
    () => {
        if (!panelOpen.value) {
            reloadListing(meta.value.current_page, { silent: true });
        }
    },
);
</script>
