<template>
    <OperationsShell
        title="Quests"
        subtitle="Search and export. Listing is read-only here — open a quest in the main app for detail."
    >
        <div class="mb-6 flex flex-col gap-3 rounded-2xl border border-slate-100 bg-white p-4 shadow-sm ring-1 ring-slate-100 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm font-semibold text-slate-600">Export uses the same filters as the table.</p>
            <a
                :href="exportUrl"
                class="inline-flex rounded-xl bg-primary-700 px-4 py-2 text-sm font-black uppercase tracking-wide text-white shadow-md transition hover:bg-primary-800"
            >
                Export CSV
            </a>
        </div>

        <form class="flex flex-col gap-3 rounded-2xl border border-slate-100 bg-white p-4 shadow-sm sm:flex-row sm:flex-wrap sm:items-end" @submit.prevent="apply">
            <div class="min-w-[12rem] flex-1">
                <label class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500" for="op-q">Search</label>
                <input
                    id="op-q"
                    v-model="form.q"
                    type="search"
                    class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-900 outline-none ring-2 ring-transparent focus:border-primary-400/60 focus:ring-primary-100"
                    placeholder="Title or reference"
                    autocomplete="off"
                />
            </div>
            <div class="w-full sm:w-48">
                <label class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500" for="op-st">Status</label>
                <select
                    id="op-st"
                    v-model="form.status"
                    class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-900 outline-none ring-2 ring-transparent focus:border-primary-400/60 focus:ring-primary-100"
                >
                    <option value="">Any status</option>
                    <option v-for="opt in status_options" :key="opt.value" :value="opt.value">
                        {{ opt.label }}
                    </option>
                </select>
            </div>
            <button type="submit" class="rounded-xl bg-primary-700 px-4 py-2 text-sm font-black uppercase tracking-wide text-white hover:bg-primary-800">
                Apply
            </button>
        </form>

        <div class="mt-6 overflow-x-auto rounded-2xl border border-slate-100 bg-white shadow-sm ring-1 ring-slate-100">
            <table class="min-w-full divide-y divide-slate-100 text-left text-sm">
                <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">
                    <tr>
                        <th class="px-4 py-3">ID</th>
                        <th class="px-4 py-3">Title</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Escrow</th>
                        <th class="px-4 py-3">Client</th>
                        <th class="px-4 py-3">Freelancer</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    <tr v-for="q in quests.data" :key="q.id" class="hover:bg-primary-50/50">
                        <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ q.id }}</td>
                        <td class="px-4 py-3 font-semibold text-slate-950">
                            <Link
                                :href="route('quests.show', q.slug ?? q.uuid)"
                                class="text-primary-700 underline decoration-primary-400/40 underline-offset-2"
                            >
                                {{ q.title }}
                            </Link>
                        </td>
                        <td class="px-4 py-3 text-slate-700">{{ q.status }}</td>
                        <td class="px-4 py-3 text-xs text-slate-500">{{ q.escrow_status ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs text-slate-500">{{ q.client?.email ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs text-slate-500">{{ q.freelancer?.email ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav v-if="quests.links?.length > 3" class="mt-6 flex flex-wrap justify-center gap-2">
            <component
                :is="link.url ? Link : 'span'"
                v-for="link in quests.links"
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
import { Link, router } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';

const props = defineProps({
    quests: { type: Object, required: true },
    filters: { type: Object, required: true },
    status_options: { type: Array, required: true },
});

const form = reactive({
    q: props.filters.q ?? '',
    status: props.filters.status ?? '',
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
    route('operations.quests.export', {
        q: form.q || undefined,
        status: form.status || undefined,
    }),
);

function apply() {
    router.get(
        route('operations.quests.index'),
        { q: form.q || undefined, status: form.status || undefined, per_page: props.filters.per_page },
        { preserveState: true, replace: true },
    );
}
</script>
