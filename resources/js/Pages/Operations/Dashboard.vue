<template>
    <OperationsShell
        title="Operations overview"
        subtitle="Read-only KPIs with CSV export. Mutations live on dedicated screens (users, portfolios, disputes in the main app)."
    >
        <div class="mb-6 flex flex-col gap-3 rounded-2xl border border-white/10 bg-slate-900/40 p-4 ring-1 ring-white/5 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Export</p>
                <p class="mt-1 text-sm font-semibold text-slate-400">Snapshot metrics for reporting (no imports).</p>
            </div>
            <a
                :href="route('operations.dashboard.export')"
                class="inline-flex items-center justify-center rounded-xl bg-amber-500 px-4 py-2 text-sm font-black uppercase tracking-wide text-slate-950 shadow-lg shadow-amber-900/30 transition hover:bg-amber-400"
            >
                Export CSV
            </a>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="tile in kpiTiles"
                :key="tile.label"
                class="rounded-2xl border border-white/10 bg-slate-900/60 p-4 shadow-lg shadow-black/30 ring-1 ring-white/5"
            >
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">
                    {{ tile.label }}
                </p>
                <p class="mt-3 font-display text-3xl font-black tracking-tight text-white">
                    {{ tile.value }}
                </p>
                <p class="mt-2 text-xs font-semibold text-slate-400">
                    {{ tile.hint }}
                </p>
            </article>
        </div>

        <p class="mt-8 text-center text-xs font-semibold text-slate-500">
            Snapshot {{ generatedAtLabel }} ·
            <Link :href="route('operations.dashboard')" class="font-bold text-amber-300 underline decoration-amber-500/60 underline-offset-2">
                {{ publicUrl }}/operations
            </Link>
        </p>
    </OperationsShell>
</template>

<script setup>
import OperationsShell from '@/Layouts/OperationsShell.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    kpi: { type: Object, required: true },
    generated_at: { type: String, required: true },
});

const publicUrl = computed(() => {
    if (typeof window !== 'undefined' && window.location?.origin) {
        return window.location.origin;
    }

    return '';
});

const generatedAtLabel = computed(() => {
    try {
        return new Date(props.generated_at).toLocaleString('en-NG', {
            dateStyle: 'medium',
            timeStyle: 'short',
            timeZone: 'Africa/Lagos',
        });
    } catch {
        return props.generated_at;
    }
});

const kpiTiles = computed(() => [
    { label: 'Registered users', value: props.kpi.users_total, hint: `${props.kpi.users_new_30d} joined in the last 30 days` },
    { label: 'Open quests', value: props.kpi.quests_open, hint: 'Accepting proposals' },
    { label: 'In progress', value: props.kpi.quests_in_progress, hint: `${props.kpi.escrow_funded_active} with funded escrow` },
    { label: 'Completed', value: props.kpi.quests_completed, hint: 'Recorded completions' },
    { label: 'Active disputes', value: props.kpi.disputes_open, hint: 'Mediation / ruling queue' },
]);
</script>
