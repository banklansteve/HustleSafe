<template>
    <section class="space-y-2">
        <header class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <label class="min-w-[12rem] flex-1">
                <span class="text-[10px] font-black uppercase tracking-[0.2em]" :class="shell.cardMuted">Search</span>
                <input
                    v-model="globalFilter"
                    type="search"
                    :placeholder="searchPlaceholder"
                    class="mt-1 w-full rounded-xl border px-3 py-2 text-sm font-semibold outline-none ring-2 ring-transparent"
                    :class="shell.input"
                />
            </label>
            <p class="text-xs font-semibold" :class="shell.cardMuted">
                {{ table.getFilteredRowModel().rows.length }} of {{ rows.length }} · click headers to sort
            </p>
        </header>

        <div class="overflow-x-auto rounded-2xl border" :class="shell.card">
            <table class="min-w-full text-left text-sm" :class="shell.tableDivide">
                <thead :class="shell.tableHead">
                    <tr>
                        <th
                            v-for="header in table.getFlatHeaders()"
                            :key="header.id"
                            class="px-4 py-3 text-[10px] font-black uppercase tracking-[0.16em]"
                        >
                            <button
                                v-if="header.column.getCanSort()"
                                type="button"
                                class="inline-flex items-center gap-1 transition hover:opacity-80"
                                @click="header.column.getToggleSortingHandler()?.($event)"
                            >
                                {{ header.column.columnDef.header }}
                                <span v-if="header.column.getIsSorted() === 'asc'" class="text-teal-500">↑</span>
                                <span v-else-if="header.column.getIsSorted() === 'desc'" class="text-teal-500">↓</span>
                            </button>
                            <span v-else>{{ header.column.columnDef.header }}</span>
                        </th>
                    </tr>
                </thead>
                <tbody :class="[shell.tableRow, shell.tableDivide, 'divide-y']">
                    <tr v-for="row in table.getRowModel().rows" :key="row.id" class="transition hover:bg-teal-500/5">
                        <td v-for="cell in row.getVisibleCells()" :key="cell.id" class="px-4 py-3 align-top font-semibold">
                            <slot v-if="cell.column.id === '_actions'" name="actions" :row="row.original" />
                            <template v-else>
                                <Link
                                    v-if="cell.getValue()?.href"
                                    :href="cell.getValue().href"
                                    class="font-bold underline decoration-teal-400/50 underline-offset-2"
                                    :class="shell.link"
                                >
                                    {{ cell.getValue().label }}
                                </Link>
                                <span v-else>{{ formatCell(cell.getValue(), cell.column.id) }}</span>
                            </template>
                        </td>
                    </tr>
                    <tr v-if="table.getRowModel().rows.length === 0">
                        <td :colspan="columns.length" class="px-4 py-10 text-center text-sm font-semibold" :class="shell.cardMuted">
                            No rows match your filters.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>

<script setup>
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';
import { formatAdminDateTime, isAdminDateColumn } from '@/utils/adminDateTime';
import {
    getCoreRowModel,
    getFilteredRowModel,
    getSortedRowModel,
    useVueTable,
} from '@tanstack/vue-table';
import { Link } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    rows: { type: Array, required: true },
    columns: { type: Array, required: true },
    searchPlaceholder: { type: String, default: 'Filter current page…' },
    initialSort: { type: Array, default: () => [] },
});

const { shell } = useInjectedAdminTheme();
const globalFilter = ref('');
const sorting = ref([...props.initialSort]);

const table = useVueTable({
    get data() {
        return props.rows;
    },
    get columns() {
        return props.columns;
    },
    state: {
        get globalFilter() {
            return globalFilter.value;
        },
        get sorting() {
            return sorting.value;
        },
    },
    onGlobalFilterChange: (updater) => {
        globalFilter.value = typeof updater === 'function' ? updater(globalFilter.value) : updater;
    },
    onSortingChange: (updater) => {
        sorting.value = typeof updater === 'function' ? updater(sorting.value) : updater;
    },
    getCoreRowModel: getCoreRowModel(),
    getFilteredRowModel: getFilteredRowModel(),
    getSortedRowModel: getSortedRowModel(),
    enableSorting: true,
});

watch(
    () => props.rows,
    () => {
        table.setOptions((prev) => ({ ...prev, data: props.rows }));
    },
);

function formatCell(value, columnId = '') {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    if (typeof value === 'object' && value.label) {
        return value.label;
    }

    if (columnId && isAdminDateColumn(columnId)) {
        return formatAdminDateTime(value, columnId);
    }

    if (typeof value === 'boolean') {
        return value ? 'Yes' : 'No';
    }

    return value;
}
</script>
