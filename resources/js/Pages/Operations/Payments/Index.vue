<template>
    <OperationsShell
        title="Escrow & payouts"
        subtitle="Read-only ledger-style view of quests with budget, paid-out, and escrow markers. Export for finance; lifecycle changes stay in the main product flows."
    >
        <div class="mb-6 flex flex-col gap-3 rounded-2xl border border-white/10 bg-slate-900/40 p-4 ring-1 ring-white/5 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm font-semibold text-slate-400">Export respects the escrow filter.</p>
            <a :href="exportUrl" class="inline-flex rounded-xl bg-amber-500 px-4 py-2 text-sm font-black uppercase tracking-wide text-slate-950 hover:bg-amber-400">
                Export CSV
            </a>
        </div>

        <form class="flex flex-wrap items-end gap-3 rounded-2xl border border-white/10 bg-slate-900/50 p-4" @submit.prevent="apply">
            <div>
                <label class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500" for="pay-es">Escrow status</label>
                <select
                    id="pay-es"
                    v-model="form.escrow"
                    class="mt-1 rounded-xl border border-white/10 bg-slate-950 px-3 py-2 text-sm font-semibold text-white"
                >
                    <option value="">Any</option>
                    <option v-for="e in escrow_options" :key="e" :value="e">{{ e }}</option>
                </select>
            </div>
            <button type="submit" class="rounded-xl bg-amber-500 px-4 py-2 text-sm font-black uppercase tracking-wide text-slate-950 hover:bg-amber-400">
                Apply
            </button>
        </form>

        <div class="mt-6 overflow-x-auto rounded-2xl border border-white/10 bg-slate-900/40 ring-1 ring-white/5">
            <table class="min-w-full divide-y divide-white/10 text-left text-sm">
                <thead class="bg-slate-900/80 text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">
                    <tr>
                        <th class="px-4 py-3">ID</th>
                        <th class="px-4 py-3">Reference</th>
                        <th class="px-4 py-3">Title</th>
                        <th class="px-4 py-3">Quest status</th>
                        <th class="px-4 py-3">Escrow</th>
                        <th class="px-4 py-3">Budget (minor)</th>
                        <th class="px-4 py-3">Paid out (minor)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    <tr v-for="q in quests.data" :key="q.id" class="hover:bg-white/5">
                        <td class="px-4 py-3 font-mono text-xs text-slate-400">{{ q.id }}</td>
                        <td class="px-4 py-3 text-xs text-slate-400">{{ q.reference_code }}</td>
                        <td class="px-4 py-3 font-semibold text-white">{{ q.title }}</td>
                        <td class="px-4 py-3 text-slate-300">{{ q.status }}</td>
                        <td class="px-4 py-3 text-xs text-amber-200">{{ q.escrow_status ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs text-slate-400">{{ q.budget_amount_minor ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs text-slate-400">{{ q.paid_out_minor ?? '0' }}</td>
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
    quests: { type: Object, required: true },
    filters: { type: Object, required: true },
    escrow_options: { type: Array, required: true },
});

const form = reactive({ escrow: props.filters.escrow ?? '' });

watch(
    () => props.filters,
    (f) => {
        form.escrow = f.escrow ?? '';
    },
    { deep: true },
);

const exportUrl = computed(() => route('operations.payments.export', { escrow: form.escrow || undefined }));

function apply() {
    router.get(
        route('operations.payments.index'),
        { escrow: form.escrow || undefined, per_page: props.filters.per_page },
        { preserveState: true, replace: true },
    );
}
</script>
