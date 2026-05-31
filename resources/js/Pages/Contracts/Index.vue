<template>
    <AppShell>
        <Head title="My contracts · HustleSafe" />

        <div class="mx-auto max-w-4xl space-y-6">
            <header class="space-y-2">
                <p class="text-xs font-black uppercase tracking-[0.2em] text-primary-700">Engagements</p>
                <h1 class="font-display text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">
                    My contracts
                </h1>
                <p class="text-sm font-semibold text-slate-600">
                    Every awarded quest with mutual confirmation generates a frozen contract snapshot — scope, payment, timeline, and signatures.
                </p>
            </header>

            <section v-if="!contracts.length" class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/80 px-5 py-10 text-center text-sm font-semibold text-slate-600">
                No contracts yet. When you award a proposal and both parties confirm, your contract appears here automatically.
            </section>

            <ul v-else class="space-y-3">
                <li v-for="c in contracts" :key="c.reference_code">
                    <Link
                        :href="c.show_url"
                        class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-100 bg-white px-4 py-4 shadow-sm ring-1 ring-slate-100 transition hover:border-primary-200 hover:shadow-md"
                    >
                        <div class="min-w-0">
                            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">{{ c.reference_code }}</p>
                            <p class="truncate font-bold text-slate-900">{{ c.quest_title }}</p>
                            <p class="mt-1 text-xs font-semibold text-slate-500">
                                With {{ c.counterparty_name }} · {{ c.total_label }}
                            </p>
                        </div>
                        <div class="flex shrink-0 flex-col items-end gap-1">
                            <span class="rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-wide ring-1" :class="statusClass(c.status)">
                                {{ c.status_label }}
                            </span>
                            <span class="text-[10px] font-semibold text-slate-400">{{ formatWhen(c.generated_at) }}</span>
                        </div>
                    </Link>
                </li>
            </ul>
        </div>
    </AppShell>
</template>

<script setup>
import AppShell from '@/Layouts/AppShell.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    contracts: { type: Array, default: () => [] },
});

function formatWhen(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
}

function statusClass(status) {
    return {
        pending_escrow: 'bg-amber-50 text-amber-900 ring-amber-200',
        active: 'bg-emerald-50 text-emerald-900 ring-emerald-200',
        amendment_pending: 'bg-sky-50 text-sky-900 ring-sky-200',
        completed: 'bg-slate-100 text-slate-700 ring-slate-200',
        disputed: 'bg-rose-50 text-rose-900 ring-rose-200',
        cancelled: 'bg-slate-50 text-slate-500 ring-slate-200',
    }[status] || 'bg-slate-100 text-slate-700 ring-slate-200';
}
</script>
