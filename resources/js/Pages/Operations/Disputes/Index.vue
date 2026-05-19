<template>
    <OperationsShell
        title="Disputes (registry)"
        subtitle="Read-only list for triage. Open a row in the member disputes workspace to take action."
    >
        <div class="mb-6 flex flex-col gap-3 rounded-2xl border border-slate-100 bg-white p-4 shadow-sm ring-1 ring-slate-100 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm font-semibold text-slate-600">Export respects the status filter.</p>
            <a :href="exportUrl" class="inline-flex rounded-xl bg-primary-700 px-4 py-2 text-sm font-black uppercase tracking-wide text-white shadow-md hover:bg-primary-800">
                Export CSV
            </a>
        </div>

        <form class="flex flex-wrap items-end gap-3 rounded-2xl border border-slate-100 bg-white p-4 shadow-sm" @submit.prevent="apply">
            <div>
                <label class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500" for="od-st">Status</label>
                <select id="od-st" v-model="form.status" class="mt-1 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-900 outline-none focus:border-primary-400 focus:ring-primary-100">
                    <option value="">Any</option>
                    <option v-for="o in status_options" :key="o.value" :value="o.value">{{ o.label }}</option>
                </select>
            </div>
            <button type="submit" class="rounded-xl bg-primary-700 px-4 py-2 text-sm font-black uppercase tracking-wide text-white hover:bg-primary-800">
                Apply
            </button>
            <Link :href="route('disputes.index')" prefetch="false" class="ml-auto text-sm font-bold text-primary-700 underline decoration-primary-400/40 underline-offset-4">
                Open member disputes UI →
            </Link>
        </form>

        <div class="mt-6 overflow-x-auto rounded-2xl border border-slate-100 bg-white shadow-sm ring-1 ring-slate-100">
            <table class="min-w-full divide-y divide-slate-100 text-left text-sm">
                <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">
                    <tr>
                        <th class="px-4 py-3">ID</th>
                        <th class="px-4 py-3">Quest</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Opened by</th>
                        <th class="px-4 py-3">Created</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    <tr v-for="d in queue.pageItems.value" :key="d.id" class="hover:bg-primary-50/50">
                        <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ d.id }}</td>
                        <td class="px-4 py-3">
                            <Link :href="route('disputes.show', d.uuid)" prefetch="false" class="font-semibold text-primary-700 underline decoration-primary-400/40 underline-offset-2">
                                {{ d.quest?.title ?? '—' }}
                            </Link>
                        </td>
                        <td class="px-4 py-3">{{ String(d.status).replace(/_/g, ' ') }}</td>
                        <td class="px-4 py-3 text-xs text-slate-500">{{ d.opened_by?.email ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs text-slate-500">{{ formatDate(d.created_at) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav v-if="queue.totalPages.value > 1" class="mt-4 flex justify-center gap-2">
            <button type="button" class="rounded-lg border border-slate-200 px-3 py-1 text-xs font-black" :disabled="queue.page.value <= 1" @click="queue.prevPage()">Prev</button>
            <span class="px-2 text-xs font-bold text-slate-500">{{ queue.page.value }} / {{ queue.totalPages.value }}</span>
            <button type="button" class="rounded-lg border border-slate-200 px-3 py-1 text-xs font-black" :disabled="queue.page.value >= queue.totalPages.value" @click="queue.nextPage()">Next</button>
        </nav>

        <nav v-if="false && disputes.links?.length > 3" class="mt-6 flex flex-wrap justify-center gap-2">
            <component
                :is="link.url ? Link : 'span'"
                v-for="link in disputes.links"
                :key="String(link.label) + (link.url || 'x')"
                :href="link.url || undefined"
                prefetch="false"
                class="min-w-[2.5rem] rounded-lg px-3 py-1.5 text-center text-xs font-bold transition"
                :class="[link.active ? 'bg-primary-700 text-white' : 'border border-slate-200 bg-white text-slate-700', !link.url ? 'pointer-events-none opacity-40' : '']"
                preserve-state
            >
                <span v-html="link.label" />
            </component>
        </nav>
    </OperationsShell>
</template>

<script setup>
import OperationsShell from '@/Layouts/OperationsShell.vue';
import { useClientQueue } from '@/composables/useClientQueue';
import { Link, router } from '@inertiajs/vue3';
import { computed, reactive, toRef, watch } from 'vue';

const props = defineProps({
    disputes: { type: Object, required: true },
    filters: { type: Object, required: true },
    status_options: { type: Array, required: true },
});

const queue = useClientQueue(
    computed(() => props.disputes?.data ?? []),
    { defaultSortKey: 'id', searchFields: ['id', 'status', 'quest.title', 'opened_by.email'] },
);

const form = reactive({ status: props.filters.status ?? '' });

watch(
    () => props.filters,
    (f) => {
        form.status = f.status ?? '';
    },
    { deep: true },
);

const exportUrl = computed(() => route('operations.disputes.export', { status: form.status || undefined }));

function formatDate(iso) {
    if (!iso) {
        return '—';
    }
    try {
        return new Date(iso).toLocaleDateString('en-NG', { timeZone: 'Africa/Lagos' });
    } catch {
        return iso;
    }
}

function apply() {
    router.get(
        route('operations.disputes.index'),
        { status: form.status || undefined, per_page: props.filters.per_page },
        { preserveState: true, replace: true },
    );
}
</script>
