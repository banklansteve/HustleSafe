<template>
    <AppShell>
        <Head title="Home · Client" />

        <div class="space-y-5">
            <div
                class="lg:grid lg:grid-cols-[minmax(0,1fr)_19.5rem] xl:grid-cols-[minmax(0,1fr)_21.5rem] lg:items-start lg:gap-5"
            >
                <div class="min-w-0 space-y-5">
                    <section
                        class="relative overflow-hidden rounded-xl border border-slate-200/90 bg-gradient-to-br from-white via-slate-50 to-secondary-50/40 px-5 py-8 shadow-sm ring-1 ring-slate-100 sm:px-8 sm:py-9"
                    >
                        <div
                            class="pointer-events-none absolute -left-12 top-0 h-36 w-36 rounded-full bg-primary-200/20 blur-3xl"
                            aria-hidden="true"
                        />
                        <div
                            class="pointer-events-none absolute -bottom-16 right-0 h-40 w-40 rounded-full bg-secondary-200/25 blur-3xl"
                            aria-hidden="true"
                        />
                        <div class="relative">
                            <p class="text-xs font-bold uppercase tracking-[0.2em] text-primary-700">
                                Project sponsor
                            </p>
                            <h1
                                class="font-display mt-2 max-w-2xl text-2xl font-black leading-tight tracking-tight text-slate-900 sm:text-3xl"
                            >
                                {{ copy.welcome }}
                            </h1>
                            <p class="mt-3 max-w-xl text-sm font-medium leading-relaxed text-slate-600 sm:text-base">
                                {{ copy.tagline }}
                            </p>
                            <div class="mt-6 flex flex-wrap gap-2">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-white/90 px-3 py-1.5 text-xs font-bold text-slate-800 ring-1 ring-slate-200/80 shadow-sm"
                                >
                                    <StarIcon class="h-3.5 w-3.5 text-secondary-500" aria-hidden="true" />
                                    <template v-if="trust.avg_rating != null">
                                        {{ Number(trust.avg_rating).toFixed(1) }} · {{ trust.rating_count }} reviews
                                    </template>
                                    <template v-else> Ratings after completed quests </template>
                                </span>
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-white/90 px-3 py-1.5 text-xs font-bold text-slate-800 ring-1 ring-slate-200/80 shadow-sm"
                                >
                                    Profile {{ trust.profile_percent ?? 0 }}%
                                </span>
                            </div>
                        </div>
                    </section>

                    <section>
                        <div class="flex flex-wrap items-end justify-between gap-3">
                            <div>
                                <h2 class="font-display text-lg font-bold tracking-tight text-slate-900 sm:text-xl">
                                    Your overview
                                </h2>
                                <p class="mt-1 text-sm font-medium text-slate-600">
                                    Open a tile to see the full list with load-more as you scroll.
                                </p>
                            </div>
                        </div>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            <DashboardStatTile
                                v-for="(card, idx) in stats"
                                :key="idx"
                                :label="card.label"
                                :value="card.value"
                                :hint="card.hint"
                                :href="card.href"
                                :icon="card.icon"
                            />
                        </div>
                    </section>

                    <MiniBarChart
                        title="Escrow released (₦)"
                        subtitle="Spend across delivery and completed quests — 6 vs 12 month lens."
                        :six="spendCharts.six"
                        :twelve="spendCharts.twelve"
                    />

                    <div class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="flex gap-3">
                                <span
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-amber-100 text-amber-800 ring-1 ring-amber-200/80"
                                >
                                    <ExclamationTriangleIcon class="h-5 w-5" aria-hidden="true" />
                                </span>
                                <div>
                                    <h3 class="font-display text-base font-bold text-slate-900 sm:text-lg">
                                        Needs your attention
                                    </h3>
                                    <p class="mt-1 text-sm font-medium text-slate-600">
                                        Reviews, disputes, and pauses — clear these to keep escrow flowing.
                                    </p>
                                </div>
                            </div>
                            <Link
                                :href="route('dashboard.lists.show', { list: 'client-live-quests' })"
                                class="text-xs font-bold text-primary-700 hover:text-primary-800 sm:text-sm"
                            >
                                View live quests →
                            </Link>
                        </div>
                        <ul class="mt-4 space-y-3">
                            <li v-for="q in attentionQuests" :key="q.id">
                                <div class="rounded-xl border border-amber-100 bg-amber-50/50 px-4 py-3 ring-1 ring-amber-100/60">
                                    <p class="font-display text-sm font-bold text-slate-900">
                                        {{ q.title }}
                                    </p>
                                    <div class="mt-2 flex flex-wrap gap-2 text-xs font-semibold text-slate-700">
                                        <span
                                            class="rounded-md bg-white px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-900 ring-1 ring-amber-200"
                                        >
                                            {{ q.status.replace(/_/g, ' ') }}
                                        </span>
                                        <span>Budget {{ q.budget_display }}</span>
                                    </div>
                                    <p class="mt-1.5 text-xs font-medium text-slate-600">
                                        Updated {{ formatWhen(q.updated_at) }}
                                    </p>
                                </div>
                            </li>
                            <li
                                v-if="attentionQuests.length === 0"
                                class="rounded-xl border border-dashed border-slate-200 bg-slate-50/50 px-4 py-8 text-center text-sm font-semibold text-slate-600"
                            >
                                Nothing urgent — your live quests are moving smoothly.
                            </li>
                        </ul>
                    </div>

                    <div class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="flex gap-3">
                                <span
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-slate-700 ring-1 ring-slate-200/80"
                                >
                                    <ClipboardDocumentListIcon class="h-5 w-5" aria-hidden="true" />
                                </span>
                                <div>
                                    <h3 class="font-display text-base font-bold text-slate-900 sm:text-lg">
                                        Recent quests
                                    </h3>
                                    <p class="mt-1 text-sm font-medium text-slate-600">
                                        Approvals and escrow releases as work lands.
                                    </p>
                                </div>
                            </div>
                            <Link
                                :href="route('dashboard.lists.show', { list: 'client-all-quests' })"
                                class="text-xs font-bold text-primary-700 hover:text-primary-800 sm:text-sm"
                            >
                                View all →
                            </Link>
                        </div>
                        <ul class="mt-4 space-y-3">
                            <li v-for="q in recentQuests" :key="q.id">
                                <div
                                    class="rounded-xl border border-slate-100 bg-slate-50/60 px-4 py-3 transition hover:border-primary-100 hover:bg-white"
                                >
                                    <p class="font-display text-sm font-bold text-slate-900">
                                        {{ q.title }}
                                    </p>
                                    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs font-semibold text-slate-600">
                                        <span
                                            class="rounded-md bg-white px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-primary-800 ring-1 ring-primary-100"
                                        >
                                            {{ q.status.replace(/_/g, ' ') }}
                                        </span>
                                        <span>Budget {{ q.budget_display }}</span>
                                    </div>
                                    <p class="mt-1.5 text-xs font-medium text-slate-500">
                                        Updated {{ formatWhen(q.updated_at) }}
                                    </p>
                                </div>
                            </li>
                            <li
                                v-if="recentQuests.length === 0"
                                class="rounded-xl border border-dashed border-slate-200 bg-slate-50/50 px-4 py-8 text-center text-sm font-semibold text-slate-600"
                            >
                                Post your first quest — verified freelancers are ready when you are.
                            </li>
                        </ul>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-2">
                        <div class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6">
                            <div class="flex gap-3">
                                <span
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-slate-700 ring-1 ring-slate-200/80"
                                >
                                    <ClockIcon class="h-5 w-5" aria-hidden="true" />
                                </span>
                                <div>
                                    <h3 class="font-display text-base font-bold text-slate-900">
                                        Activity
                                    </h3>
                                    <p class="mt-1 text-sm font-medium text-slate-600">
                                        Milestones across your quests and payouts.
                                    </p>
                                </div>
                            </div>
                            <ul class="mt-4 max-h-[28rem] space-y-3 overflow-y-auto pr-1">
                                <li
                                    v-for="(a, i) in activities"
                                    :key="i"
                                    class="rounded-xl border border-slate-100 bg-slate-50/50 px-3 py-3"
                                >
                                    <p class="text-sm font-bold text-slate-900">
                                        {{ a.title }}
                                    </p>
                                    <p v-if="a.body" class="mt-1 text-xs font-medium leading-relaxed text-slate-600">
                                        {{ a.body }}
                                    </p>
                                    <p class="mt-1.5 text-xs font-medium text-slate-500">
                                        {{ formatWhen(a.created_at) }}
                                    </p>
                                </li>
                                <li v-if="activities.length === 0" class="text-sm font-semibold text-slate-600">
                                    Nothing here yet — activity appears as soon as quests move.
                                </li>
                            </ul>
                        </div>

                        <div
                            id="notifications"
                            class="scroll-mt-28 rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6"
                        >
                            <div class="flex gap-3">
                                <span
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-secondary-100 text-secondary-800 ring-1 ring-secondary-200/80"
                                >
                                    <BellAlertIcon class="h-5 w-5" aria-hidden="true" />
                                </span>
                                <div>
                                    <h3 class="font-display text-base font-bold text-slate-900">
                                        Notifications
                                    </h3>
                                    <p class="mt-1 text-sm font-medium text-slate-600">
                                        Submissions, disputes, and payout confirmations.
                                    </p>
                                </div>
                            </div>
                            <ul class="mt-4 max-h-[28rem] space-y-3 overflow-y-auto pr-1">
                                <li
                                    v-for="n in notifications"
                                    :key="n.id"
                                    class="rounded-xl border px-3 py-3"
                                    :class="n.read ? 'border-slate-100 bg-slate-50/60' : 'border-secondary-200 bg-secondary-50/70'"
                                >
                                    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">
                                        {{ n.type }}
                                    </p>
                                    <p class="mt-1 text-sm font-semibold text-slate-900">
                                        {{ summarizeNotification(n.data) }}
                                    </p>
                                    <p class="mt-1.5 text-xs font-medium text-slate-500">
                                        {{ formatWhen(n.created_at) }}
                                    </p>
                                </li>
                                <li
                                    v-if="notifications.length === 0"
                                    class="rounded-xl border border-dashed border-slate-200 bg-slate-50/50 px-4 py-8 text-center text-sm font-semibold text-slate-600"
                                >
                                    Inbox zero — we will alert you when freelancers submit work.
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <aside class="mt-5 min-w-0 space-y-4 lg:mt-0">
                    <div class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm ring-1 ring-slate-100">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-xs font-bold uppercase tracking-wider text-slate-500">
                                Trust overview
                            </p>
                            <InformationCircleIcon
                                class="h-5 w-5 shrink-0 text-slate-400"
                                :title="trust.explainer"
                                aria-hidden="true"
                            />
                        </div>
                        <TrustHalfDonut :score="trust.client ?? 0" label="Client trust" variant="client" />
                        <p class="mt-4 text-xs font-medium leading-relaxed text-slate-600">
                            {{ trust.explainer }}
                        </p>
                        <Link
                            :href="trustGuideUrl"
                            class="mt-4 inline-flex w-full items-center justify-center rounded-lg bg-slate-900 px-4 py-2.5 text-center text-xs font-bold text-white transition hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                        >
                            Strengthen your sponsor profile
                        </Link>
                    </div>

                    <div
                        v-if="scoreOpportunities.length"
                        class="rounded-xl border border-primary-100 bg-gradient-to-br from-primary-50/80 to-white p-4 ring-1 ring-primary-100/80"
                    >
                        <p class="text-xs font-bold uppercase tracking-wider text-primary-800">
                            Build credibility
                        </p>
                        <ul class="mt-3 space-y-2">
                            <li v-for="(op, oi) in scoreOpportunities" :key="oi">
                                <Link
                                    :href="op.href"
                                    class="flex items-center justify-between rounded-lg bg-white/90 px-3 py-2 text-xs font-bold text-slate-800 ring-1 ring-slate-100 transition hover:ring-primary-200"
                                >
                                    {{ op.label }}
                                    <span aria-hidden="true" class="text-primary-600">→</span>
                                </Link>
                            </li>
                        </ul>
                    </div>

                    <div class="rounded-xl border border-slate-200/80 bg-white p-4 shadow-sm ring-1 ring-slate-100 sm:p-5">
                        <h3 class="font-display text-sm font-bold text-slate-900">
                            Shortcuts
                        </h3>
                        <ul class="mt-3 space-y-2">
                            <li v-for="(s, si) in homeShortcuts" :key="si">
                                <Link
                                    :href="s.href"
                                    class="flex gap-3 rounded-lg border border-slate-100 bg-slate-50/70 px-3 py-2.5 transition hover:border-primary-100 hover:bg-white"
                                >
                                    <span
                                        class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-primary-700 ring-1 ring-slate-100"
                                    >
                                        <PanelIcon :name="s.icon" size="sm" />
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block text-xs font-bold text-slate-900">{{ s.label }}</span>
                                        <span class="mt-0.5 block text-[11px] font-medium leading-snug text-slate-600">
                                            {{ s.description }}
                                        </span>
                                    </span>
                                </Link>
                            </li>
                        </ul>
                    </div>

                    <div class="rounded-xl border border-slate-200/80 bg-white p-4 shadow-sm ring-1 ring-slate-100 sm:p-5">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="font-display text-sm font-bold text-slate-900">
                                Offers on your quests
                            </h3>
                            <Link
                                :href="route('dashboard.lists.show', { list: 'client-offers-inbox' })"
                                class="text-[11px] font-bold text-primary-700 hover:text-primary-800"
                            >
                                See all
                            </Link>
                        </div>
                        <ul class="mt-3 space-y-2">
                            <li
                                v-for="o in inboundOffers"
                                :key="o.id"
                                class="rounded-lg border border-slate-100 bg-slate-50/60 px-3 py-2"
                            >
                                <p class="text-xs font-bold text-slate-900 line-clamp-2">
                                    {{ o.quest_title ?? 'Quest' }}
                                </p>
                                <p class="mt-0.5 text-[11px] font-semibold text-slate-600">
                                    From {{ o.freelancer_label ?? 'Freelancer' }}
                                </p>
                                <p class="mt-0.5 text-[10px] font-bold uppercase tracking-wide text-primary-800">
                                    {{ o.status.replace(/_/g, ' ') }}
                                </p>
                                <p class="mt-0.5 text-[10px] font-medium text-slate-500">
                                    {{ formatWhen(o.updated_at) }}
                                </p>
                            </li>
                            <li v-if="inboundOffers.length === 0" class="text-xs font-semibold text-slate-600">
                                Offers appear when freelancers respond to your briefs.
                            </li>
                        </ul>
                    </div>

                    <div class="rounded-xl border border-slate-200/80 bg-white p-4 shadow-sm ring-1 ring-slate-100 sm:p-5">
                        <div class="flex items-center gap-2">
                            <DevicePhoneMobileIcon class="h-4 w-4 text-slate-500" aria-hidden="true" />
                            <h3 class="font-display text-sm font-bold text-slate-900">
                                Recent log-ins
                            </h3>
                        </div>
                        <p class="mt-1 text-[11px] font-medium text-slate-600">
                            Plain-language device names instead of raw browser text.
                        </p>
                        <ul class="mt-3 space-y-2">
                            <li
                                v-for="(row, i) in recentLogins"
                                :key="i"
                                class="rounded-lg border border-slate-100 bg-slate-50/60 px-3 py-2"
                            >
                                <p class="text-xs font-bold text-slate-900">
                                    {{ formatWhen(row.at) }}
                                </p>
                                <p class="text-[11px] font-semibold text-slate-600">
                                    {{ row.device }}
                                </p>
                                <p class="text-[10px] font-medium text-slate-500">
                                    {{ row.ip ?? 'IP unknown' }}
                                </p>
                            </li>
                            <li v-if="recentLogins.length === 0" class="text-xs font-semibold text-slate-600">
                                Your next login appears here.
                            </li>
                        </ul>
                    </div>
                </aside>
            </div>
        </div>
    </AppShell>
</template>

<script setup>
import DashboardStatTile from '@/Components/Home/DashboardStatTile.vue';
import MiniBarChart from '@/Components/Home/MiniBarChart.vue';
import PanelIcon from '@/Components/Home/PanelIcon.vue';
import TrustHalfDonut from '@/Components/Home/TrustHalfDonut.vue';
import AppShell from '@/Layouts/AppShell.vue';
import {
    BellAlertIcon,
    ClipboardDocumentListIcon,
    ClockIcon,
    DevicePhoneMobileIcon,
    ExclamationTriangleIcon,
    InformationCircleIcon,
    StarIcon,
} from '@heroicons/vue/24/outline';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    copy: {
        type: Object,
        required: true,
    },
    spendCharts: {
        type: Object,
        required: true,
    },
    attentionQuests: {
        type: Array,
        required: true,
    },
    inboundOffers: {
        type: Array,
        required: true,
    },
    homeShortcuts: {
        type: Array,
        required: true,
    },
    scoreOpportunities: {
        type: Array,
        default: () => [],
    },
    trustGuideUrl: {
        type: String,
        required: true,
    },
    stats: {
        type: Array,
        required: true,
    },
    trust: {
        type: Object,
        required: true,
    },
    recentQuests: {
        type: Array,
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
    if (typeof data.message === 'string') {
        return data.message;
    }
    if (typeof data.title === 'string') {
        return data.title;
    }
    return 'Update';
}
</script>
