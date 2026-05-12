<template>
    <AppShell>
        <Head title="Home · Freelancer" />

        <div class="space-y-5">
            <div
                class="lg:grid lg:grid-cols-[minmax(0,1fr)_19.5rem] xl:grid-cols-[minmax(0,1fr)_21.5rem] lg:items-start lg:gap-5"
            >
                <div class="min-w-0 space-y-5">
                    <!-- Hero -->
                    <section
                        class="relative overflow-hidden rounded-xl border border-slate-200/90 bg-gradient-to-br from-white via-slate-50 to-primary-50/50 px-5 py-8 shadow-sm ring-1 ring-slate-100 sm:px-8 sm:py-9"
                    >
                        <div
                            class="pointer-events-none absolute -right-16 top-0 h-40 w-40 rounded-full bg-primary-200/25 blur-3xl"
                            aria-hidden="true"
                        />
                        <div
                            class="pointer-events-none absolute -bottom-20 left-1/3 h-44 w-44 rounded-full bg-secondary-200/20 blur-3xl"
                            aria-hidden="true"
                        />
                        <div class="relative">
                            <p class="text-xs font-bold uppercase tracking-[0.2em] text-primary-700">
                                Safe Hustler
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
                                    <template v-else> Awaiting first review </template>
                                </span>
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-white/90 px-3 py-1.5 text-xs font-bold text-slate-800 ring-1 ring-slate-200/80 shadow-sm"
                                >
                                    Profile {{ trust.profile_percent ?? 0 }}%
                                </span>
                            </div>
                        </div>
                    </section>

                    <!-- Stats -->
                    <section>
                        <div class="flex flex-wrap items-end justify-between gap-3">
                            <div>
                                <h2 class="font-display text-lg font-bold tracking-tight text-slate-900 sm:text-xl">
                                    Your snapshot
                                </h2>
                                <p class="mt-1 text-sm font-medium text-slate-600">
                                    Tap a tile for the full list — numbers refresh as quests move.
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

                    <div
                        v-if="skillCategoriesCount === 0"
                        class="rounded-xl border border-amber-200/90 bg-amber-50/90 px-4 py-3 text-sm font-semibold text-amber-950 ring-1 ring-amber-100 sm:px-5"
                        role="status"
                    >
                        <span class="font-bold">Tip:</span>
                        add quest categories in your profile so we can rank better matches for you.
                        <Link :href="route('profile.edit')" class="ml-1 font-bold text-amber-900 underline decoration-amber-400 underline-offset-2">
                            Open profile
                        </Link>
                    </div>

                    <MiniBarChart
                        title="Escrow paid out (₦)"
                        subtitle="Completed and archived quests — switch between 6 and 12 months."
                        :six="incomeCharts.six"
                        :twelve="incomeCharts.twelve"
                    />

                    <!-- Top matches -->
                    <div class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="flex gap-3">
                                <span
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-primary-600/10 text-primary-700 ring-1 ring-primary-100"
                                >
                                    <SparklesIcon class="h-5 w-5" aria-hidden="true" />
                                </span>
                                <div>
                                    <h3 class="font-display text-base font-bold text-slate-900 sm:text-lg">
                                        Top matches for you
                                    </h3>
                                    <p class="mt-1 text-sm font-medium text-slate-600">
                                        Open quests scored from your categories, location, and recency.
                                    </p>
                                </div>
                            </div>
                            <Link
                                :href="route('quests.explore')"
                                class="inline-flex items-center rounded-lg bg-primary-700 px-4 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-primary-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 sm:text-sm"
                            >
                                Explore &amp; send offers
                            </Link>
                        </div>
                        <ul class="mt-5 space-y-3">
                            <li v-for="m in matchingQuests" :key="m.id">
                                <div
                                    class="flex flex-wrap items-start justify-between gap-3 rounded-xl border border-slate-100 bg-slate-50/70 px-4 py-3.5 transition hover:border-primary-100 hover:bg-white sm:px-4"
                                >
                                    <div class="min-w-0 flex-1">
                                        <p class="font-display text-sm font-bold text-slate-900 sm:text-base">
                                            {{ m.title }}
                                        </p>
                                        <div class="mt-2 flex flex-wrap gap-1.5 text-[11px] font-bold uppercase tracking-wide text-slate-600">
                                            <span
                                                v-if="m.category"
                                                class="rounded-md bg-white px-2 py-0.5 text-primary-800 ring-1 ring-primary-100"
                                            >
                                                {{ m.category }}
                                            </span>
                                            <span v-if="m.city || m.state" class="rounded-md bg-white px-2 py-0.5 ring-1 ring-slate-100">
                                                {{ [m.city, m.state].filter(Boolean).join(' · ') }}
                                            </span>
                                            <span class="rounded-md bg-white px-2 py-0.5 ring-1 ring-slate-100">
                                                {{ m.budget_display }}
                                            </span>
                                        </div>
                                        <ul class="mt-2 space-y-0.5">
                                            <li v-for="(r, ri) in m.reasons" :key="ri" class="text-xs font-medium text-slate-600 sm:text-sm">
                                                · {{ r }}
                                            </li>
                                        </ul>
                                        <p class="mt-1.5 text-xs font-medium text-slate-500">
                                            Posted {{ formatWhen(m.posted_at) }}
                                        </p>
                                    </div>
                                    <div
                                        class="flex h-14 w-14 shrink-0 flex-col items-center justify-center rounded-xl bg-gradient-to-br from-primary-600 to-teal-600 text-white shadow-md"
                                    >
                                        <span class="text-[9px] font-bold uppercase tracking-wide text-white/85">Match</span>
                                        <span class="font-display text-lg font-black">{{ m.match_score }}</span>
                                    </div>
                                </div>
                            </li>
                            <li
                                v-if="matchingQuests.length === 0"
                                class="rounded-xl border border-dashed border-slate-200 bg-slate-50/50 px-4 py-8 text-center text-sm font-semibold text-slate-600"
                            >
                                No open quests yet — when sponsors post, ranked briefs will appear here.
                            </li>
                        </ul>
                    </div>

                    <!-- Recent quests (main column only — keeps vertical rhythm tight) -->
                    <div class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="flex gap-3">
                                <span
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-slate-700 ring-1 ring-slate-200/80"
                                >
                                    <BriefcaseIcon class="h-5 w-5" aria-hidden="true" />
                                </span>
                                <div>
                                    <h3 class="font-display text-base font-bold text-slate-900 sm:text-lg">
                                        Recent quests
                                    </h3>
                                    <p class="mt-1 text-sm font-medium text-slate-600">
                                        Milestones you are carrying — newest updates first.
                                    </p>
                                </div>
                            </div>
                            <Link
                                :href="route('dashboard.lists.show', { list: 'freelancer-active-quests' })"
                                class="text-xs font-bold text-primary-700 hover:text-primary-800 sm:text-sm"
                            >
                                View active →
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
                                        <span>Payout {{ q.payout_display }}</span>
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
                                No quests yet — accept work from matched briefs to see them here.
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
                                        What changed around your account lately.
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
                                    Quiet for now — reviews and quests will populate this feed.
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
                                        Approvals, escrow releases, and feedback requests.
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
                                    All caught up — we will ping you when something needs attention.
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Aside -->
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
                        <TrustHalfDonut :score="trust.freelancer ?? 0" label="Freelancer trust" variant="freelancer" />
                        <p class="mt-4 text-xs font-medium leading-relaxed text-slate-600">
                            {{ trust.explainer }}
                        </p>
                        <Link
                            :href="trustGuideUrl"
                            class="mt-4 inline-flex w-full items-center justify-center rounded-lg bg-slate-900 px-4 py-2.5 text-center text-xs font-bold text-white transition hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                        >
                            Ways to improve your score
                        </Link>
                    </div>

                    <div
                        v-if="scoreOpportunities.length"
                        class="rounded-xl border border-primary-100 bg-gradient-to-br from-primary-50/80 to-white p-4 ring-1 ring-primary-100/80"
                    >
                        <p class="text-xs font-bold uppercase tracking-wider text-primary-800">
                            Quick wins
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
                                Offers sent
                            </h3>
                            <Link
                                :href="route('dashboard.lists.show', { list: 'freelancer-offers-sent' })"
                                class="text-[11px] font-bold text-primary-700 hover:text-primary-800"
                            >
                                See all
                            </Link>
                        </div>
                        <ul class="mt-3 space-y-2">
                            <li
                                v-for="o in recentOffers"
                                :key="o.id"
                                class="rounded-lg border border-slate-100 bg-slate-50/60 px-3 py-2"
                            >
                                <p class="text-xs font-bold text-slate-900 line-clamp-2">
                                    {{ o.quest_title ?? 'Quest' }}
                                </p>
                                <p class="mt-0.5 text-[10px] font-bold uppercase tracking-wide text-primary-800">
                                    {{ o.status.replace(/_/g, ' ') }}
                                </p>
                                <p class="mt-0.5 text-[10px] font-medium text-slate-500">
                                    {{ formatWhen(o.updated_at) }}
                                </p>
                            </li>
                            <li v-if="recentOffers.length === 0" class="text-xs font-semibold text-slate-600">
                                No offers yet — explore matches and send a pitch.
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
                            Recognisable device names — not technical browser strings.
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
    BriefcaseIcon,
    ClockIcon,
    DevicePhoneMobileIcon,
    InformationCircleIcon,
    SparklesIcon,
    StarIcon,
} from '@heroicons/vue/24/outline';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    copy: {
        type: Object,
        required: true,
    },
    incomeCharts: {
        type: Object,
        required: true,
    },
    matchingQuests: {
        type: Array,
        required: true,
    },
    recentOffers: {
        type: Array,
        required: true,
    },
    homeShortcuts: {
        type: Array,
        required: true,
    },
    skillCategoriesCount: {
        type: Number,
        default: 0,
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
