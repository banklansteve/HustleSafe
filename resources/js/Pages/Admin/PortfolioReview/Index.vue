<template>
    <component
        :is="shellComponent"
        title="Portfolio review"
        subtitle="Review every freelancer portfolio and its media for scams, fraud, and inappropriate content before or after publication."
    >
        <div class="space-y-5">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div v-for="tile in summaryTiles" :key="tile.key" class="rounded-3xl border p-4 shadow-sm" :class="shell.card">
                    <p class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">{{ tile.label }}</p>
                    <p class="mt-2 text-3xl font-black" :class="shell.title">{{ tile.value }}</p>
                </div>
            </div>

            <AdminPanel title="All portfolios" :description="`${portfolios.total ?? 0} portfolios match your filters`">
                <div class="mb-4 grid gap-3 lg:grid-cols-[1.4fr_repeat(4,minmax(0,1fr))_auto]">
                    <input
                        v-model="localFilters.q"
                        type="search"
                        placeholder="Search title, slug, description, owner…"
                        class="rounded-2xl border px-4 py-3 text-sm font-semibold"
                        :class="shell.input"
                        @input="debouncedApply"
                    />
                    <select v-model="localFilters.status" class="rounded-2xl border px-3 py-3 text-sm font-bold" :class="shell.input" @change="applyFilters">
                        <option value="">All statuses</option>
                        <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                    <select v-model="localFilters.visibility" class="rounded-2xl border px-3 py-3 text-sm font-bold" :class="shell.input" @change="applyFilters">
                        <option value="">Any visibility</option>
                        <option value="visible">Publicly visible</option>
                        <option value="hidden">Admin hidden</option>
                    </select>
                    <select v-model="localFilters.per_page" class="rounded-2xl border px-3 py-3 text-sm font-bold" :class="shell.input" @change="applyFilters">
                        <option :value="10">10 / page</option>
                        <option :value="20">20 / page</option>
                        <option :value="50">50 / page</option>
                    </select>
                    <button type="button" class="rounded-2xl px-4 py-3 text-sm font-black uppercase" :class="shell.btnGhost" @click="clearFilters">
                        Reset
                    </button>
                </div>

                <div class="hidden overflow-x-auto lg:block">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10">
                        <thead>
                            <tr class="text-left text-[10px] font-black uppercase tracking-wider" :class="shell.label">
                                <th class="px-3 py-3">Preview</th>
                                <th class="px-3 py-3"><button type="button" @click="toggleSort('title')">Portfolio</button></th>
                                <th class="px-3 py-3">Owner</th>
                                <th class="px-3 py-3"><button type="button" @click="toggleSort('status')">Status</button></th>
                                <th class="px-3 py-3">Media</th>
                                <th class="px-3 py-3">Visibility</th>
                                <th class="px-3 py-3"><button type="button" @click="toggleSort('updated_at')">Updated</button></th>
                                <th class="px-3 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-white/10">
                            <tr v-for="row in portfolios.data" :key="row.id" class="hover:bg-primary-50/50 dark:hover:bg-white/[0.03]">
                                <td class="px-3 py-3">
                                    <div class="h-12 w-16 overflow-hidden rounded-lg bg-slate-100 ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-white/10">
                                        <img v-if="row.cover_url" :src="row.cover_url" alt="" class="h-full w-full object-cover" />
                                    </div>
                                </td>
                                <td class="px-3 py-3">
                                    <p class="font-black">{{ row.title }}</p>
                                    <p class="text-xs text-slate-500">{{ row.slug }}</p>
                                </td>
                                <td class="px-3 py-3">
                                    <p class="font-bold">{{ row.owner?.name || '—' }}</p>
                                    <p class="text-xs text-slate-500">{{ row.owner?.email }}</p>
                                </td>
                                <td class="px-3 py-3">
                                    <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase" :class="statusClass(row.status)">
                                        {{ formatStatus(row.status) }}
                                    </span>
                                </td>
                                <td class="px-3 py-3 font-bold">{{ row.files_count }}</td>
                                <td class="px-3 py-3 text-xs font-bold">{{ row.admin_hidden ? 'Hidden' : 'Visible' }}</td>
                                <td class="px-3 py-3 text-xs font-bold">{{ formatDate(row.updated_at) }}</td>
                                <td class="px-3 py-3">
                                    <Link
                                        :href="pageRoute('portfolio-review.show', row.slug)"
                                        class="text-xs font-black text-primary-700 underline dark:text-primary-300"
                                    >
                                        Review
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="grid gap-3 lg:hidden">
                    <Link
                        v-for="row in portfolios.data"
                        :key="row.id"
                        :href="pageRoute('portfolio-review.show', row.slug)"
                        class="rounded-3xl border p-4"
                        :class="shell.card"
                    >
                        <div class="flex gap-3">
                            <div class="h-16 w-20 shrink-0 overflow-hidden rounded-xl bg-slate-100">
                                <img v-if="row.cover_url" :src="row.cover_url" alt="" class="h-full w-full object-cover" />
                            </div>
                            <div class="min-w-0">
                                <p class="font-black">{{ row.title }}</p>
                                <p class="text-xs text-slate-500">{{ row.owner?.name }} · {{ row.files_count }} files</p>
                                <p class="mt-1 text-[10px] font-black uppercase" :class="statusClass(row.status)">{{ formatStatus(row.status) }}</p>
                            </div>
                        </div>
                    </Link>
                </div>

                <nav v-if="portfolios.links?.length > 3" class="mt-6 flex flex-wrap justify-center gap-2">
                    <component
                        :is="link.url ? Link : 'span'"
                        v-for="link in portfolios.links"
                        :key="String(link.label) + (link.url || 'x')"
                        :href="link.url || undefined"
                        prefetch="false"
                        class="min-w-[2.5rem] rounded-lg px-3 py-1.5 text-center text-xs font-bold transition"
                        :class="[link.active ? 'bg-primary-700 text-white' : 'border border-slate-200 bg-white text-slate-700 dark:border-white/10 dark:bg-slate-900 dark:text-slate-200', !link.url ? 'pointer-events-none opacity-40' : '']"
                        preserve-state
                    >
                        <span v-html="link.label" />
                    </component>
                </nav>
            </AdminPanel>
        </div>
    </component>
</template>

<script setup>
import AdminPanel from '@/Components/Admin/AdminPanel.vue';
import AdminShell from '@/Layouts/AdminShell.vue';
import OperationsShell from '@/Layouts/OperationsShell.vue';
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';
import { Link, router } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';

const props = defineProps({
    routeNamespace: { type: String, default: 'admin' },
    portfolios: { type: Object, required: true },
    filters: { type: Object, required: true },
    statusOptions: { type: Array, default: () => [] },
    summary: { type: Object, required: true },
});

const shellComponent = computed(() => (props.routeNamespace === 'operations' ? OperationsShell : AdminShell));
const { shell } = useInjectedAdminTheme();

function pageRoute(name, params = {}) {
    return route(`${props.routeNamespace}.${name}`, params);
}

const localFilters = reactive({
    q: props.filters.q ?? '',
    status: props.filters.status ?? '',
    visibility: props.filters.visibility ?? '',
    sort: props.filters.sort ?? 'updated_at',
    direction: props.filters.direction ?? 'desc',
    per_page: props.filters.per_page ?? 20,
});

watch(
    () => props.filters,
    (f) => {
        localFilters.q = f.q ?? '';
        localFilters.status = f.status ?? '';
        localFilters.visibility = f.visibility ?? '';
        localFilters.sort = f.sort ?? 'updated_at';
        localFilters.direction = f.direction ?? 'desc';
        localFilters.per_page = f.per_page ?? 20;
    },
    { deep: true },
);

const summaryTiles = computed(() => [
    { key: 'total', label: 'Total portfolios', value: props.summary.total ?? 0 },
    { key: 'pending', label: 'Pending review', value: props.summary.pending_review ?? 0 },
    { key: 'published', label: 'Published', value: props.summary.published ?? 0 },
    { key: 'hidden', label: 'Admin hidden', value: props.summary.hidden ?? 0 },
]);

let debounceTimer = null;

function debouncedApply() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(applyFilters, 300);
}

function applyFilters() {
    router.get(
        pageRoute('portfolio-review.index'),
        {
            q: localFilters.q || undefined,
            status: localFilters.status || undefined,
            visibility: localFilters.visibility || undefined,
            sort: localFilters.sort,
            direction: localFilters.direction,
            per_page: localFilters.per_page,
        },
        { preserveState: true, replace: true },
    );
}

function clearFilters() {
    localFilters.q = '';
    localFilters.status = '';
    localFilters.visibility = '';
    localFilters.sort = 'updated_at';
    localFilters.direction = 'desc';
    localFilters.per_page = 20;
    applyFilters();
}

function toggleSort(column) {
    if (localFilters.sort === column) {
        localFilters.direction = localFilters.direction === 'asc' ? 'desc' : 'asc';
    } else {
        localFilters.sort = column;
        localFilters.direction = 'desc';
    }
    applyFilters();
}

function formatStatus(value) {
    return String(value || '').replace(/_/g, ' ');
}

function formatDate(value) {
    if (!value) {
        return '—';
    }
    try {
        return new Date(value).toLocaleString('en-NG', { dateStyle: 'medium', timeStyle: 'short' });
    } catch {
        return value;
    }
}

function statusClass(status) {
    if (status === 'published') {
        return 'bg-emerald-100 text-emerald-900 dark:bg-emerald-500/20 dark:text-emerald-100';
    }
    if (status === 'pending_review') {
        return 'bg-amber-100 text-amber-950 dark:bg-amber-500/20 dark:text-amber-100';
    }
    if (status === 'removed') {
        return 'bg-rose-100 text-rose-900 dark:bg-rose-500/20 dark:text-rose-100';
    }

    return 'bg-slate-100 text-slate-800 dark:bg-white/10 dark:text-slate-200';
}
</script>
