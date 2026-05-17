<template>
    <AppShell>
        <Head title="Disputes · HustleSafe" />

        <div class="mx-auto max-w-4xl space-y-6">
            <header class="space-y-2">
                <p class="text-xs font-black uppercase tracking-[0.2em] text-primary-700">Trust operations</p>
                <h1 class="font-display text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">
                    Your dispute files
                </h1>
                <p class="text-sm font-semibold text-slate-600">
                    Evidence-first, time-boxed, and mirrored for both parties. Read the
                    <a :href="workflow_doc_url" class="font-black text-primary-800 underline underline-offset-2" target="_blank" rel="noopener noreferrer">operator workflow</a>.
                </p>
            </header>

            <section class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6">
                <h2 class="font-display text-sm font-black uppercase tracking-wide text-slate-500">
                    Philosophy
                </h2>
                <ul class="mt-3 space-y-2 text-sm font-semibold text-slate-700">
                    <li v-for="(text, key) in philosophy" :key="key" class="flex gap-2">
                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-primary-500" aria-hidden="true" />
                        <span>{{ text }}</span>
                    </li>
                </ul>
            </section>

            <section v-if="!disputes.length" class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/80 px-5 py-10 text-center text-sm font-semibold text-slate-600">
                No disputes yet — when something needs structured mediation, open a file from the quest or proposal screen.
            </section>

            <ul v-else class="space-y-3">
                <li v-for="d in disputes" :key="d.uuid">
                    <Link
                        :href="d.url"
                        class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-100 bg-white px-4 py-4 shadow-sm ring-1 ring-slate-100 transition hover:border-primary-200 hover:shadow-md"
                    >
                        <div class="min-w-0">
                            <p class="truncate font-bold text-slate-900">
                                {{ d.quest_title }}
                            </p>
                            <p class="mt-1 text-xs font-semibold text-slate-500">
                                {{ d.reason_label }}
                            </p>
                        </div>
                        <div class="flex shrink-0 flex-col items-end gap-1 text-xs font-black uppercase tracking-wide text-slate-600">
                            <span class="rounded-full bg-slate-100 px-3 py-1 ring-1 ring-slate-200">{{ d.status }}</span>
                            <span class="text-[10px] font-semibold text-slate-400">{{ formatWhen(d.updated_at) }}</span>
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
    disputes: { type: Array, default: () => [] },
    philosophy: { type: Object, default: () => ({}) },
    workflow_doc_url: { type: String, default: '/docs/dispute-workflow.md' },
});

function formatWhen(iso) {
    if (!iso) {
        return '—';
    }
    try {
        return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso));
    } catch {
        return iso;
    }
}
</script>
