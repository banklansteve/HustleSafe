<template>
    <AppShell>
        <Head title="Explore quests" />

        <div class="rounded-[2rem] bg-gradient-to-br from-primary-800 via-slate-900 to-slate-950 px-6 py-10 text-white shadow-xl ring-1 ring-white/10 sm:px-10">
            <p class="text-xs font-bold uppercase tracking-[0.25em] text-teal-200/90">
                For you
            </p>
            <h1 class="font-display mt-3 text-3xl font-black tracking-tight sm:text-4xl">
                Matched open quests
            </h1>
            <p class="mt-4 max-w-2xl text-base font-semibold leading-relaxed text-teal-50">
                Ranked by your categories, distance, and how fresh the brief is. More signals (exact coordinates on quests) make this sharper over time.
            </p>
        </div>

        <div class="mt-10 space-y-5">
            <div
                v-for="q in quests"
                :key="q.id"
                class="rounded-[1.5rem] border border-slate-100 bg-white p-6 shadow-sm ring-1 ring-slate-100 transition hover:border-primary-200 hover:shadow-md sm:p-8"
            >
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <p class="font-display text-lg font-bold text-slate-900 sm:text-xl">
                            {{ q.title }}
                        </p>
                        <div class="mt-3 flex flex-wrap gap-2 text-sm font-semibold text-slate-600">
                            <span
                                v-if="q.category"
                                class="rounded-full bg-primary-50 px-3 py-1 text-xs font-bold uppercase tracking-wide text-primary-900 ring-1 ring-primary-100"
                            >
                                {{ q.category }}
                            </span>
                            <span v-if="q.city || q.state" class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">
                                {{ [q.city, q.state].filter(Boolean).join(' · ') }}
                            </span>
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">
                                Budget {{ formatBudget(q.budget_minor) }}
                            </span>
                        </div>
                        <ul class="mt-4 space-y-1.5">
                            <li
                                v-for="(r, i) in q.reasons"
                                :key="i"
                                class="flex gap-2 text-sm font-medium text-slate-600"
                            >
                                <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-teal-500" />
                                <span>{{ r }}</span>
                            </li>
                        </ul>
                    </div>
                    <div
                        class="flex h-20 w-20 shrink-0 flex-col items-center justify-center rounded-2xl bg-gradient-to-br from-primary-600 to-teal-600 text-center shadow-lg shadow-primary-900/25 ring-1 ring-white/20"
                    >
                        <p class="text-xs font-bold uppercase tracking-wide text-white/80">
                            Match
                        </p>
                        <p class="font-display text-2xl font-black text-white">
                            {{ q.match_score }}
                        </p>
                    </div>
                </div>
                <p class="mt-4 text-xs font-semibold text-slate-500">
                    Posted {{ formatWhen(q.posted_at) }}
                </p>
            </div>

            <p
                v-if="quests.length === 0"
                class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-6 py-12 text-center text-base font-semibold text-slate-600"
            >
                No open quests right now — check back soon or widen your categories in profile.
            </p>
        </div>
    </AppShell>
</template>

<script setup>
import AppShell from '@/Layouts/AppShell.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    quests: {
        type: Array,
        default: () => [],
    },
});

function formatBudget(minor) {
    const n = Number(minor) / 100;

    return `₦${n.toLocaleString('en-NG', { maximumFractionDigits: 0 })}`;
}

function formatWhen(iso) {
    if (!iso) {
        return '';
    }
    try {
        return new Date(iso).toLocaleString('en-NG', {
            dateStyle: 'medium',
            timeStyle: 'short',
            timeZone: 'Africa/Lagos',
        });
    } catch {
        return iso;
    }
}
</script>
