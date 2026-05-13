<template>
    <AppShell>
        <Head title="Your quests" />

        <div class="rounded-[2rem] bg-gradient-to-br from-primary-800 via-slate-900 to-slate-950 px-6 py-10 text-white shadow-xl ring-1 ring-white/10 sm:px-10">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.25em] text-teal-200/90">
                        Client workspace
                    </p>
                    <h1 class="font-display mt-3 text-3xl font-black tracking-tight sm:text-4xl">
                        Your quests
                    </h1>
                    <p class="mt-3 max-w-xl text-sm font-semibold leading-relaxed text-teal-50/95 sm:text-base">
                        Track drafts and live briefs. Open a quest to edit details, manage files, or tag freelancers.
                    </p>
                </div>
                <Link
                    :href="route('quests.create')"
                    class="inline-flex items-center rounded-full bg-white px-5 py-2.5 text-sm font-black text-primary-900 shadow-lg shadow-slate-950/30 ring-1 ring-white/40 transition hover:bg-teal-50"
                >
                    New quest
                </Link>
            </div>
        </div>

        <div class="mt-10">
            <div v-if="quests.data.length" class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="q in quests.data"
                    :key="q.uuid"
                    :href="route('quests.show', q.uuid)"
                    class="group flex flex-col rounded-2xl border border-slate-100 bg-white p-6 shadow-md shadow-slate-900/5 ring-1 ring-slate-100 transition hover:border-primary-200 hover:shadow-lg"
                >
                    <div class="flex items-start justify-between gap-3">
                        <p class="font-display min-w-0 text-lg font-bold text-slate-900 group-hover:text-primary-800">
                            {{ q.title }}
                        </p>
                        <span
                            class="shrink-0 rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wide ring-1"
                            :class="statusClass(q.status)"
                        >
                            {{ q.status }}
                        </span>
                    </div>
                    <p class="mt-2 text-xs font-bold uppercase tracking-wide text-slate-400">
                        {{ q.reference_code }}
                    </p>
                    <div class="mt-4 flex flex-wrap gap-2 text-xs font-semibold text-slate-600">
                        <span v-if="q.category" class="rounded-full bg-primary-50 px-2.5 py-1 text-primary-900 ring-1 ring-primary-100">
                            {{ q.category }}
                        </span>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-700">
                            {{ [q.city, q.state].filter(Boolean).join(' · ') }}
                        </span>
                    </div>
                    <p class="mt-4 text-sm font-bold text-slate-800">
                        {{ formatBudget(q.budget_minor) }}
                    </p>
                    <p class="mt-auto pt-4 text-xs font-semibold text-slate-400">
                        Updated {{ formatWhen(q.updated_at) }}
                    </p>
                </Link>
            </div>
            <p
                v-else
                class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-6 py-14 text-center text-base font-semibold text-slate-600"
            >
                No quests yet — start with a polished brief and we will alert the right freelancers.
            </p>

            <div v-if="quests.meta && quests.meta.last_page > 1" class="mt-10 flex flex-wrap justify-center gap-3">
                <Link
                    v-if="quests.prev_page_url"
                    :href="quests.prev_page_url"
                    preserve-scroll
                    class="rounded-full border border-slate-200 bg-white px-5 py-2 text-sm font-bold text-slate-800 shadow-sm hover:border-primary-200 hover:bg-primary-50"
                >
                    Previous
                </Link>
                <span class="self-center text-xs font-bold text-slate-500">
                    Page {{ quests.meta.current_page }} / {{ quests.meta.last_page }}
                </span>
                <Link
                    v-if="quests.next_page_url"
                    :href="quests.next_page_url"
                    preserve-scroll
                    class="rounded-full border border-slate-200 bg-white px-5 py-2 text-sm font-bold text-slate-800 shadow-sm hover:border-primary-200 hover:bg-primary-50"
                >
                    Next
                </Link>
            </div>
        </div>
    </AppShell>
</template>

<script setup>
import AppShell from '@/Layouts/AppShell.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    quests: {
        type: Object,
        required: true,
    },
});

function statusClass(s) {
    if (s === 'open') {
        return 'bg-emerald-50 text-emerald-800 ring-emerald-200';
    }
    if (s === 'draft') {
        return 'bg-amber-50 text-amber-900 ring-amber-200';
    }

    return 'bg-slate-100 text-slate-700 ring-slate-200';
}

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
