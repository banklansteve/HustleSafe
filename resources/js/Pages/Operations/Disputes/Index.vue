<template>
    <OperationsShell
        title="Disputes (registry)"
        subtitle="Read-only list for triage. Open a row in the member disputes workspace to take action."
    >
        <div class="mb-6 flex flex-col gap-3 rounded-2xl border border-white/10 bg-slate-900/40 p-4 ring-1 ring-white/5 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm font-semibold text-slate-400">Export respects the status filter.</p>
            <a :href="exportUrl" class="inline-flex rounded-xl bg-amber-500 px-4 py-2 text-sm font-black uppercase tracking-wide text-slate-950 hover:bg-amber-400">
                Export CSV
            </a>
        </div>

        <form class="flex flex-wrap items-end gap-3 rounded-2xl border border-white/10 bg-slate-900/50 p-4" @submit.prevent="apply">
            <div>
                <label class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500" for="od-st">Status</label>
                <select
                    id="od-st"
                    v-model="form.status"
                    class="mt-1 rounded-xl border border-white/10 bg-slate-950 px-3 py-2 text-sm font-semibold text-white"
                >
                    <option value="">Any</option>
                    <option v-for="o in status_options" :key="o.value" :value="o.value">{{ o.label }}</option>
                </select>
            </div>
            <button type="submit" class="rounded-xl bg-amber-500 px-4 py-2 text-sm font-black uppercase tracking-wide text-slate-950 hover:bg-amber-400">
                Apply
            </button>
            <Link :href="route('disputes.index')" class="ml-auto text-sm font-bold text-sky-300 underline decoration-sky-500/50 underline-offset-4">
                Open member disputes UI →
            </Link>
        </form>

        <div class="mt-6 overflow-x-auto rounded-2xl border border-white/10 bg-slate-900/40 ring-1 ring-white/5">
            <table class="min-w-full divide-y divide-white/10 text-left text-sm">
                <thead class="bg-slate-900/80 text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">
                    <tr>
                        <th class="px-4 py-3">ID</th>
                        <th class="px-4 py-3">Quest</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Opened by</th>
                        <th class="px-4 py-3">Created</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    <tr v-for="d in disputes.data" :key="d.id" class="hover:bg-white/5">
                        <td class="px-4 py-3 font-mono text-xs text-slate-400">{{ d.id }}</td>
                        <td class="px-4 py-3">
                            <Link :href="route('disputes.show', d.uuid)" class="font-semibold text-amber-200 underline decoration-amber-500/40 underline-offset-2">
                                {{ d.quest?.title ?? '—' }}
                            </Link>
                        </td>
                        <td class="px-4 py-3 text-slate-200">{{ String(d.status).replace(/_/g, ' ') }}</td>
                        <td class="px-4 py-3 text-xs text-slate-400">{{ d.opened_by?.email ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs text-slate-500">{{ formatDate(d.created_at) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav v-if="disputes.links?.length > 3" class="mt-6 flex flex-wrap justify-center gap-2">
            <component
                :is="link.url ? Link : 'span'"
                v-for="link in disputes.links"
                :key="String(link.label) + (link.url || 'x')"
                :href="link.url || undefined"
                prefetch="false"
                class="min-w-[2.5rem] rounded-lg px-3 py-1.5 text-center text-xs font-bold transition"
                :class="[link.active ? 'bg-amber-500 text-slate-950' : 'border border-white/10 text-slate-200', !link.url ? 'pointer-events-none opacity-40' : '']"
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
    disputes: { type: Object, required: true },
    filters: { type: Object, required: true },
    status_options: { type: Array, required: true },
});

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
