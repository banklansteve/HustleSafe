<template>
    <AdminShell
        title="Quest operations"
        subtitle="Search and filter are server-backed. Export reflects the same filters. Import logs support notes per reference code — it does not mutate quests."
    >
        <div class="mb-6 flex flex-col gap-3 rounded-2xl border border-white/10 bg-slate-900/40 p-4 ring-1 ring-white/5 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">CSV</p>
                <p class="mt-1 text-sm font-semibold text-slate-400">Export listing · import support notes (audit only).</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a :href="exportUrl" class="inline-flex rounded-xl bg-teal-500 px-4 py-2 text-sm font-black uppercase tracking-wide text-slate-950 shadow-lg shadow-teal-900/30 transition hover:bg-teal-400">
                    Export CSV
                </a>
                <form class="flex flex-wrap items-end gap-2" @submit.prevent="submitImport">
                    <div>
                        <label class="sr-only" for="quest-csv">Support notes CSV</label>
                        <input
                            id="quest-csv"
                            type="file"
                            accept=".csv,.txt"
                            class="block w-full max-w-xs text-xs font-semibold text-slate-300 file:mr-2 file:rounded-lg file:border-0 file:bg-white/10 file:px-3 file:py-2 file:text-xs file:font-bold file:text-white"
                            @change="onQuestFile"
                        />
                    </div>
                    <button
                        type="submit"
                        class="rounded-xl border border-violet-400/50 bg-violet-500/20 px-4 py-2 text-sm font-black uppercase tracking-wide text-violet-100 transition hover:bg-violet-500/30 disabled:opacity-40"
                        :disabled="importForm.processing || !importForm.file"
                    >
                        Import
                    </button>
                </form>
            </div>
            <p v-if="importForm.errors.file" class="w-full text-xs font-bold text-rose-300">
                {{ importForm.errors.file }}
            </p>
        </div>

        <form class="flex flex-col gap-3 rounded-2xl border border-white/10 bg-slate-900/50 p-4 ring-1 ring-white/5 sm:flex-row sm:flex-wrap sm:items-end" @submit.prevent="apply">
            <div class="min-w-[12rem] flex-1">
                <label class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500" for="ad-q">Search</label>
                <input
                    id="ad-q"
                    v-model="form.q"
                    type="search"
                    class="mt-1 w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2 text-sm font-semibold text-white outline-none ring-2 ring-transparent transition focus:border-teal-400/60 focus:ring-teal-500/40"
                    placeholder="Title or reference"
                    autocomplete="off"
                />
            </div>
            <div class="w-full sm:w-48">
                <label class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500" for="ad-status">Status</label>
                <select
                    id="ad-status"
                    v-model="form.status"
                    class="mt-1 w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2 text-sm font-semibold text-white outline-none ring-2 ring-transparent focus:border-teal-400/60 focus:ring-teal-500/40"
                >
                    <option value="">Any status</option>
                    <option v-for="opt in status_options" :key="opt.value" :value="opt.value">
                        {{ opt.label }}
                    </option>
                </select>
            </div>
            <div class="flex gap-2">
                <button
                    type="submit"
                    class="inline-flex items-center justify-center rounded-xl bg-teal-500 px-4 py-2 text-sm font-black uppercase tracking-wide text-slate-950 shadow-lg shadow-teal-900/30 transition hover:bg-teal-400"
                >
                    Apply
                </button>
                <button
                    type="button"
                    class="rounded-xl border border-white/15 px-4 py-2 text-sm font-bold text-slate-200 transition hover:bg-white/5"
                    @click="reset"
                >
                    Reset
                </button>
            </div>
        </form>

        <div class="mt-6 space-y-3 lg:hidden">
            <article
                v-for="row in table.getRowModel().rows"
                :key="row.id"
                class="rounded-2xl border border-white/10 bg-slate-900/60 p-4 ring-1 ring-white/5"
            >
                <p class="text-xs font-black uppercase tracking-wide text-slate-500">Quest #{{ row.original.id }}</p>
                <p class="mt-1 font-display text-lg font-bold text-white">
                    {{ row.original.title }}
                </p>
                <dl class="mt-3 space-y-1 text-xs font-semibold text-slate-300">
                    <div class="flex justify-between gap-2">
                        <dt>Status</dt>
                        <dd class="text-teal-200">{{ row.original.status }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt>Client</dt>
                        <dd class="truncate text-right">{{ row.original.client?.email ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt>Freelancer</dt>
                        <dd class="truncate text-right">{{ row.original.freelancer?.email ?? '—' }}</dd>
                    </div>
                </dl>
                <Link
                    :href="route('quests.show', row.original.slug ?? row.original.uuid)"
                    class="mt-4 inline-flex w-full items-center justify-center rounded-xl border border-white/15 py-2 text-xs font-black uppercase tracking-wide text-white transition hover:bg-white/5"
                >
                    Open quest
                </Link>
            </article>
        </div>

        <div class="mt-6 hidden overflow-x-auto rounded-2xl border border-white/10 bg-slate-900/40 ring-1 ring-white/5 lg:block">
            <table class="min-w-full divide-y divide-white/10 text-left text-sm">
                <thead class="bg-slate-900/80 text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">
                    <tr>
                        <th
                            v-for="header in table.getFlatHeaders()"
                            :key="header.id"
                            class="cursor-pointer select-none px-4 py-3 transition hover:text-teal-200"
                            @click="header.column.getToggleSortingHandler()?.($event)"
                        >
                            <span class="inline-flex items-center gap-1">
                                {{ header.column.columnDef.header }}
                                <span v-if="header.column.getIsSorted() === 'asc'">▲</span>
                                <span v-else-if="header.column.getIsSorted() === 'desc'">▼</span>
                            </span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5 text-slate-100">
                    <tr v-for="row in table.getRowModel().rows" :key="row.id" class="hover:bg-white/5">
                        <td v-for="cell in row.getVisibleCells()" :key="cell.id" class="px-4 py-3 font-semibold">
                            <Link
                                v-if="cell.column.id === 'actions'"
                                :href="route('quests.show', cell.row.original.slug ?? cell.row.original.uuid)"
                                class="text-xs font-black uppercase tracking-wide text-teal-300 underline decoration-teal-500/50 underline-offset-4"
                            >
                                View
                            </Link>
                            <template v-else>
                                {{ cell.getValue() }}
                            </template>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav v-if="quests.links?.length > 3" class="mt-6 flex flex-wrap items-center justify-center gap-2" aria-label="Pagination">
            <component
                :is="link.url ? Link : 'span'"
                v-for="link in quests.links"
                :key="String(link.label) + (link.url || 'gap')"
                :href="link.url || undefined"
                prefetch="false"
                class="min-w-[2.5rem] rounded-lg px-3 py-1.5 text-center text-xs font-bold transition"
                :class="[
                    link.active ? 'bg-teal-500 text-slate-950' : 'border border-white/10 text-slate-200 hover:bg-white/5',
                    !link.url ? 'pointer-events-none opacity-40' : '',
                ]"
                preserve-state
            >
                <span v-html="link.label" />
            </component>
        </nav>
    </AdminShell>
</template>

<script setup>
import AdminShell from '@/Layouts/AdminShell.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import {
    createColumnHelper,
    getCoreRowModel,
    getSortedRowModel,
    useVueTable,
} from '@tanstack/vue-table';
import { computed, reactive, watch } from 'vue';

const props = defineProps({
    quests: { type: Object, required: true },
    filters: { type: Object, required: true },
    status_options: { type: Array, default: () => [] },
});

const form = reactive({
    q: props.filters.q ?? '',
    status: props.filters.status ?? '',
});

const importForm = useForm({
    file: null,
});

watch(
    () => props.filters,
    (f) => {
        form.q = f.q ?? '';
        form.status = f.status ?? '';
    },
    { deep: true },
);

const exportUrl = computed(() =>
    route('admin.quests.export', {
        q: form.q || undefined,
        status: form.status || undefined,
    }),
);

const columnHelper = createColumnHelper();

const columns = [
    columnHelper.accessor('id', { header: 'ID', cell: (info) => info.getValue() }),
    columnHelper.accessor('title', { header: 'Quest', cell: (info) => info.getValue() }),
    columnHelper.accessor('status', {
        header: 'Status',
        cell: (info) => String(info.getValue() ?? ''),
    }),
    columnHelper.accessor((row) => row.client?.email ?? '—', { id: 'client', header: 'Client' }),
    columnHelper.accessor((row) => row.freelancer?.email ?? '—', { id: 'freelancer', header: 'Freelancer' }),
    columnHelper.accessor((row) => row.quest_category?.parent?.name ?? row.quest_category?.name ?? '—', {
        id: 'category',
        header: 'Category',
    }),
    columnHelper.accessor((row) => row.state_model?.name ?? '—', { id: 'state', header: 'State' }),
    columnHelper.display({ id: 'actions', header: 'Action', cell: (ctx) => ctx.row.original }),
];

const tableData = computed(() => props.quests.data ?? []);

const table = useVueTable({
    get data() {
        return tableData.value;
    },
    columns,
    getCoreRowModel: getCoreRowModel(),
    getSortedRowModel: getSortedRowModel(),
});

function apply() {
    router.get(
        route('admin.quests.index'),
        {
            q: form.q || undefined,
            status: form.status || undefined,
            per_page: props.filters.per_page,
        },
        { preserveState: true, replace: true },
    );
}

function reset() {
    form.q = '';
    form.status = '';
    router.get(route('admin.quests.index'), {}, { preserveState: true, replace: true });
}

function onQuestFile(e) {
    const f = e.target.files?.[0];
    importForm.file = f || null;
}

function submitImport() {
    if (!importForm.file) {
        return;
    }
    importForm.post(route('admin.quests.import'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            importForm.reset();
            if (document.getElementById('quest-csv')) {
                document.getElementById('quest-csv').value = '';
            }
        },
    });
}
</script>
