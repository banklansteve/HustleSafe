<template>
    <AppShell>
        <Head title="Home · Admin" />

        <section class="relative overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white px-6 py-10 shadow-lg shadow-slate-300/40 ring-1 ring-slate-100 sm:px-10 sm:py-12">
            <div class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-primary-300/60 to-transparent" />
            <div class="relative">
                <p class="text-sm font-bold uppercase tracking-[0.22em] text-primary-700">
                    Operations
                </p>
                <h1 class="font-display mt-3 max-w-3xl text-3xl font-black tracking-tight text-slate-900 sm:text-4xl">
                    {{ copy.welcome }}
                </h1>
                <p class="mt-4 max-w-3xl text-base font-semibold leading-relaxed text-slate-600 sm:text-lg">
                    {{ copy.tagline }}
                </p>
            </div>
        </section>

        <section class="mt-10">
            <h2 class="font-display text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
                Platform pulse
            </h2>
            <p class="mt-2 text-base font-semibold text-slate-600">
                Snapshot across users and quests — charts deepen as traffic grows.
            </p>
            <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    v-for="(card, idx) in stats"
                    :key="idx"
                    :label="card.label"
                    :value="card.value"
                    :hint="card.hint"
                />
            </div>
        </section>

        <section class="mt-12 rounded-[1.75rem] border border-slate-100 bg-white p-6 shadow-sm ring-1 ring-slate-100 sm:p-10">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h3 class="font-display text-lg font-bold text-slate-900 sm:text-xl">
                        New registrations · last 7 days
                    </h3>
                    <p class="mt-2 text-sm font-semibold text-slate-600">
                        Lagos mornings spike — watch weekends when side-hustle traffic jumps.
                    </p>
                </div>
            </div>

            <div class="mt-8 flex h-52 items-end gap-2 sm:h-60 sm:gap-3">
                <div
                    v-for="(v, i) in chart.values"
                    :key="i"
                    class="flex flex-1 flex-col items-center gap-2"
                >
                    <div class="flex h-44 w-full items-end rounded-t-xl bg-slate-100/90 sm:h-52">
                        <div
                            class="w-full rounded-t-xl bg-gradient-to-t from-primary-800 to-primary-500 shadow-inner shadow-primary-900/20 transition hover:from-primary-700 hover:to-primary-400"
                            :style="{ height: barHeight(v) + '%' }"
                        />
                    </div>
                    <p class="text-center text-sm font-bold text-slate-700">
                        {{ chart.labels[i] }}
                    </p>
                    <p class="text-center text-sm font-semibold text-slate-500">
                        {{ v }}
                    </p>
                </div>
            </div>
        </section>

        <section class="mt-12 grid gap-8 lg:grid-cols-2 lg:gap-10">
            <div class="rounded-[1.75rem] border border-slate-100 bg-white p-6 shadow-sm ring-1 ring-slate-100 sm:p-8">
                <h3 class="font-display text-lg font-bold text-slate-900 sm:text-xl">
                    Recent log-ins (you)
                </h3>
                <p class="mt-2 text-sm font-semibold text-slate-600">
                    Audit-friendly trail for admin sessions.
                </p>
                <ul class="mt-6 space-y-4">
                    <li v-for="(row, i) in recentLogins" :key="i" class="rounded-2xl border border-slate-100 bg-slate-50/70 px-4 py-3">
                        <p class="text-base font-bold text-slate-900">
                            {{ formatWhen(row.at) }}
                        </p>
                        <p class="mt-1 text-sm font-semibold text-slate-600">
                            {{ row.ip ?? 'IP unknown' }}
                        </p>
                        <p class="mt-1 text-sm font-medium text-slate-500 line-clamp-2">
                            {{ row.device }}
                        </p>
                    </li>
                    <li v-if="recentLogins.length === 0" class="text-base font-semibold text-slate-600">
                        Admin login history starts from your next session.
                    </li>
                </ul>
            </div>

            <div id="notifications" class="scroll-mt-28 rounded-[1.75rem] border border-slate-100 bg-white p-6 shadow-sm ring-1 ring-slate-100 sm:p-8">
                <h3 class="font-display text-lg font-bold text-slate-900 sm:text-xl">
                    Notifications
                </h3>
                <p class="mt-2 text-sm font-semibold text-slate-600">
                    System notices — expand with queues & alerts later.
                </p>
                <ul class="mt-6 space-y-4">
                    <li
                        v-for="n in notifications"
                        :key="n.id"
                        class="rounded-2xl border px-0 py-0"
                        :class="n.read ? 'border-slate-100 bg-slate-50/60' : 'border-secondary-200 bg-secondary-50/70'"
                    >
                        <button
                            type="button"
                            class="block w-full rounded-2xl px-4 py-4 text-left transition hover:bg-white/80 disabled:cursor-wait disabled:opacity-70"
                            :disabled="notifBusyId === n.id"
                            @click="openDashboardNotification(n)"
                        >
                            <span class="inline-flex items-center gap-2">
                                <ReLoader4Line
                                    v-if="notifBusyId === n.id"
                                    class="h-4 w-4 shrink-0 animate-spin text-primary-600"
                                    aria-hidden="true"
                                />
                                <p class="text-sm font-bold uppercase tracking-wide text-primary-800">
                                    {{ n.label }}
                                </p>
                            </span>
                            <p class="mt-2 text-base font-semibold text-slate-900">
                                {{ n.line || summarizeNotification(n.data) }}
                            </p>
                            <p class="mt-2 text-sm font-medium text-slate-500">
                                {{ formatWhen(n.created_at) }}
                            </p>
                        </button>
                    </li>
                    <li v-if="notifications.length === 0" class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-base font-semibold text-slate-600">
                        No operational pings yet — hook queues & risk alerts here later.
                    </li>
                </ul>
            </div>
        </section>

        <section class="mt-12 rounded-[1.75rem] border border-slate-100 bg-white p-6 shadow-sm ring-1 ring-slate-100 sm:p-8">
            <h3 class="font-display text-lg font-bold text-slate-900 sm:text-xl">
                Activity (your account)
            </h3>
            <p class="mt-2 text-sm font-semibold text-slate-600">
                Cross-team audit trail rolls up here as workflows land.
            </p>
            <ul class="mt-6 space-y-4">
                <li v-for="(a, i) in activities" :key="i" class="rounded-2xl border border-slate-100 bg-white px-4 py-4 shadow-sm">
                    <p class="text-base font-bold text-slate-900">
                        {{ a.title }}
                    </p>
                    <p v-if="a.body" class="mt-2 text-sm font-semibold leading-relaxed text-slate-600">
                        {{ a.body }}
                    </p>
                    <p class="mt-2 text-sm font-medium text-slate-500">
                        {{ formatWhen(a.created_at) }}
                    </p>
                </li>
                <li v-if="activities.length === 0" class="text-base font-semibold text-slate-600">
                    Quiet console — actions will stream in from moderation tools soon.
                </li>
            </ul>
        </section>
    </AppShell>
</template>

<script setup>
import StatCard from '@/Components/Home/StatCard.vue';
import AppShell from '@/Layouts/AppShell.vue';
import { useNotificationVisit } from '@/composables/useNotificationVisit';
import { ReLoader4Line } from '@kalimahapps/vue-icons/re';
import { Head, Link } from '@inertiajs/vue3';

const { busyId: notifBusyId, visit: visitNotification } = useNotificationVisit();

function openDashboardNotification(n) {
    void visitNotification(n.id);
}

const props = defineProps({
    copy: {
        type: Object,
        required: true,
    },
    stats: {
        type: Array,
        required: true,
    },
    chart: {
        type: Object,
        required: true,
    },
    recentLogins: {
        type: Array,
        required: true,
    },
    activities: {
        type: Array,
        required: true,
    },
    notifications: {
        type: Array,
        required: true,
    },
});

function barHeight(value) {
    const peak = props.chart.peak > 0 ? props.chart.peak : 1;

    return Math.max(6, Math.round((value / peak) * 100));
}

function formatWhen(iso) {
    if (!iso) {
        return '';
    }
    try {
        const d = new Date(iso);

        return d.toLocaleString('en-NG', {
            weekday: 'short',
            day: 'numeric',
            month: 'short',
            hour: '2-digit',
            minute: '2-digit',
            timeZone: 'Africa/Lagos',
        });
    } catch {
        return iso;
    }
}

function summarizeNotification(data) {
    if (!data || typeof data !== 'object') {
        return 'Update';
    }
    if (typeof data.quest_title === 'string' && data.quest_title) {
        return data.quest_title;
    }
    if (typeof data.preview === 'string' && data.preview) {
        return data.preview;
    }
    if (typeof data.message === 'string') {
        return data.message;
    }
    if (typeof data.body === 'string') {
        return data.body;
    }
    if (typeof data.title === 'string') {
        return data.title;
    }

    return 'Update';
}
</script>
