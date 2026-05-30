<template>
    <div class="overflow-hidden rounded-xl border border-slate-100 bg-white shadow-sm ring-1 ring-slate-100">
        <div
            v-if="showSearch || showPerPage"
            class="flex flex-col gap-2 border-b border-slate-100 bg-slate-50/80 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex flex-1 flex-col gap-2 sm:flex-row sm:items-center">
                <input
                    v-if="showSearch"
                    v-model="searchModel"
                    type="search"
                    class="min-h-10 flex-1 rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-900 outline-none focus:border-primary-400 focus:ring-2 focus:ring-primary-100"
                    :placeholder="searchPlaceholder"
                />
                <select
                    v-if="showPerPage"
                    v-model="perPageModel"
                    class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold uppercase tracking-wide text-slate-700"
                >
                    <option :value="15">15 / page</option>
                    <option :value="25">25 / page</option>
                    <option :value="50">50 / page</option>
                </select>
            </div>
            <p class="text-xs font-bold text-slate-500">{{ total }} results</p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-left text-sm">
                <thead class="bg-white text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">
                    <tr>
                        <th v-for="col in columns" :key="col.key" class="px-4 py-3">
                            <button v-if="col.sortable" type="button" class="inline-flex items-center gap-1 hover:text-primary-700" @click="emit('sort', col.key)">
                                {{ col.label }}
                                <span v-if="sortKey === col.key" class="text-primary-600">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
                            </button>
                            <span v-else>{{ col.label }}</span>
                        </th>
                        <th v-if="$slots.actions" class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    <tr v-if="loading">
                        <td :colspan="columns.length + ($slots.actions ? 1 : 0)" class="px-4 py-10 text-center text-sm font-semibold text-slate-500">Loading queue…</td>
                    </tr>
                    <tr v-else-if="!rows.length">
                        <td :colspan="columns.length + ($slots.actions ? 1 : 0)" class="px-4 py-10 text-center text-sm font-semibold text-slate-500">{{ emptyMessage }}</td>
                    </tr>
                    <tr
                        v-for="row in rows"
                        :key="rowKey(row)"
                        class="transition"
                        :class="rowClickable ? 'cursor-pointer hover:bg-primary-50/60' : 'hover:bg-slate-50/80'"
                        @click="rowClickable && emit('open', row)"
                    >
                        <td v-for="col in columns" :key="col.key" class="px-4 py-3" :class="col.class">
                            <slot :name="`cell-${col.key}`" :row="row">
                                {{ formatCell(row, col) }}
                            </slot>
                        </td>
                        <td v-if="$slots.actions" class="px-4 py-3 text-right" @click.stop>
                            <slot name="actions" :row="row" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <footer v-if="totalPages > 1" class="flex items-center justify-between border-t border-slate-100 px-4 py-3">
            <button type="button" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-black uppercase text-slate-700 disabled:opacity-40" :disabled="page <= 1" @click="emit('page', page - 1)">
                Previous
            </button>
            <p class="text-xs font-bold text-slate-500">Page {{ page }} of {{ totalPages }}</p>
            <button type="button" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-black uppercase text-slate-700 disabled:opacity-40" :disabled="page >= totalPages" @click="emit('page', page + 1)">
                Next
            </button>
        </footer>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    columns: { type: Array, required: true },
    rows: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    search: { type: String, default: '' },
    perPage: { type: Number, default: 25 },
    page: { type: Number, default: 1 },
    total: { type: Number, default: 0 },
    totalPages: { type: Number, default: 1 },
    sortKey: { type: String, default: 'id' },
    sortDir: { type: String, default: 'desc' },
    emptyMessage: { type: String, default: 'No items in this queue.' },
    searchPlaceholder: { type: String, default: 'Search loaded results…' },
    rowKeyField: { type: String, default: 'id' },
    showSearch: { type: Boolean, default: true },
    showPerPage: { type: Boolean, default: true },
    rowClickable: { type: Boolean, default: true },
});

const emit = defineEmits(['update:search', 'update:perPage', 'sort', 'page', 'open']);

const searchModel = computed({
    get: () => props.search,
    set: (value) => emit('update:search', value),
});

const perPageModel = computed({
    get: () => props.perPage,
    set: (value) => emit('update:perPage', Number(value)),
});

function rowKey(row) {
    return row?.[props.rowKeyField] ?? JSON.stringify(row);
}

function formatCell(row, col) {
    const value = col.path ? col.path.split('.').reduce((c, key) => (c == null ? null : c[key]), row) : row[col.key];
    if (col.format === 'date' && value) {
        try {
            return new Date(value).toLocaleString('en-NG', { timeZone: 'Africa/Lagos' });
        } catch {
            return value;
        }
    }

    return value ?? '—';
}
</script>
