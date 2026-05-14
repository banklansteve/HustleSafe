<template>
    <Head :title="profile.name + ' · Freelancer on HustleSafe'" />

    <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-slate-50 text-slate-900">
        <header class="sticky top-0 z-30 border-b border-slate-200/90 bg-white/90 backdrop-blur-lg">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                <Link href="/" class="flex items-center gap-2 font-display text-lg font-bold text-slate-900">
                    <span
                        class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-primary-600 to-primary-800 text-xs font-black text-white shadow-sm"
                    >
                        HS
                    </span>
                    HustleSafe
                </Link>
                <div class="flex items-center gap-2">
                    <Link
                        v-if="!is_authenticated"
                        :href="route('login')"
                        class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-800 shadow-sm transition hover:border-primary-200 hover:bg-primary-50"
                    >
                        Log in
                    </Link>
                    <Link
                        v-if="show_freelancer_join_cta"
                        :href="route('register', { intent: 'earn' })"
                        class="rounded-xl bg-primary-600 px-4 py-2 text-sm font-bold text-white shadow-md shadow-primary-900/15 ring-1 ring-primary-500/30"
                    >
                        Join as freelancer
                    </Link>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-6xl space-y-10 px-4 py-10 sm:px-6 sm:py-14 lg:space-y-12 lg:py-16">
            <!-- Hero -->
            <section
                class="overflow-hidden rounded-[2rem] border border-slate-100 bg-white shadow-xl shadow-slate-200/50 ring-1 ring-slate-100"
            >
                <div class="relative bg-gradient-to-br from-primary-800 via-primary-900 to-slate-950 px-6 py-10 text-white sm:px-10 sm:py-12">
                    <div
                        class="pointer-events-none absolute -right-24 top-0 h-64 w-64 rounded-full bg-teal-400/15 blur-3xl"
                        aria-hidden="true"
                    />
                    <div
                        class="pointer-events-none absolute -bottom-32 left-1/4 h-72 w-72 rounded-full bg-rose-400/10 blur-3xl"
                        aria-hidden="true"
                    />
                    <div class="relative flex flex-col gap-8 lg:flex-row lg:items-start lg:justify-between">
                        <div class="flex flex-col gap-6 sm:flex-row sm:items-start">
                            <div class="relative shrink-0">
                                <img
                                    v-if="profile.avatar_url"
                                    :src="profile.avatar_url"
                                    :alt="profile.name"
                                    class="h-24 w-24 rounded-3xl border-2 border-white/20 object-cover shadow-lg shadow-black/20 ring-2 ring-white/10 sm:h-28 sm:w-28"
                                />
                                <div
                                    v-else
                                    class="flex h-24 w-24 items-center justify-center rounded-3xl border-2 border-white/20 bg-white/10 text-2xl font-black tracking-tight ring-2 ring-white/10 sm:h-28 sm:w-28"
                                >
                                    {{ initials }}
                                </div>
                                <span
                                    v-if="profile.verification_tier"
                                    class="absolute -bottom-2 -right-2 rounded-full bg-emerald-400 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wide text-emerald-950 shadow-md"
                                >
                                    Verified
                                </span>
                            </div>
                            <div class="min-w-0 space-y-3">
                                <div class="flex flex-wrap items-center gap-3">
                                    <h1 class="font-display text-3xl font-black tracking-tight sm:text-4xl lg:text-[2.35rem]">
                                        {{ profile.name }}
                                    </h1>
                                    <span
                                        v-if="presence.show_indicator"
                                        class="inline-flex items-center gap-1.5 rounded-full bg-emerald-400/95 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-emerald-950 shadow-md ring-1 ring-emerald-700/20"
                                    >
                                        <span class="relative flex h-2 w-2">
                                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-800 opacity-60"></span>
                                            <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-900"></span>
                                        </span>
                                        Online
                                    </span>
                                </div>
                                <p v-if="profile.username" class="mt-1 text-sm font-semibold text-teal-100/90">
                                    @{{ profile.username }}
                                </p>
                                <p v-if="profile.member_since" class="mt-2 text-xs font-bold uppercase tracking-wider text-teal-200/80">
                                    Member since {{ profile.member_since }}
                                </p>
                                <p
                                    v-if="profile.headline"
                                    class="max-w-2xl text-base font-semibold leading-relaxed text-teal-50 sm:text-lg"
                                >
                                    {{ profile.headline }}
                                </p>
                                <div class="flex flex-wrap gap-2">
                                    <span
                                        v-if="profile.profession"
                                        class="inline-flex items-center gap-1.5 rounded-full bg-black/25 px-3 py-1.5 text-xs font-bold ring-1 ring-white/15"
                                    >
                                        <BriefcaseIcon class="h-4 w-4 opacity-90" aria-hidden="true" />
                                        {{ profile.profession }}
                                    </span>
                                    <span
                                        v-if="profile.years_experience != null"
                                        class="rounded-full bg-black/25 px-3 py-1.5 text-xs font-bold ring-1 ring-white/15"
                                    >
                                        {{ profile.years_experience }}+ yrs experience
                                    </span>
                                    <span
                                        v-if="social.followers_count != null"
                                        class="rounded-full bg-black/25 px-3 py-1.5 text-xs font-bold ring-1 ring-white/15"
                                    >
                                        {{ formatCountPair(social.followers_count, 'followers') }}
                                    </span>
                                    <span
                                        v-if="social.following_count != null"
                                        class="rounded-full bg-black/25 px-3 py-1.5 text-xs font-bold ring-1 ring-white/15"
                                    >
                                        {{ formatCountPair(social.following_count, 'following') }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col items-stretch gap-4 sm:flex-row sm:items-start lg:flex-col lg:items-end">
                            <UserFollowButton
                                v-if="social.viewer_can_follow"
                                :user-slug="profile.slug"
                                :initial-following="social.is_following"
                                :initial-followers-count="social.followers_count"
                                :initial-following-count="social.following_count"
                                :is-authenticated="is_authenticated"
                                :viewer-can-follow="social.viewer_can_follow"
                            />
                            <div
                                v-else
                                class="rounded-2xl border border-white/15 bg-black/20 px-5 py-4 text-center backdrop-blur-sm sm:text-left"
                            >
                                <p class="text-xs font-bold uppercase tracking-wider text-teal-100/80">
                                    Community
                                </p>
                                <p class="mt-1 font-display text-2xl font-black tabular-nums text-white">
                                    {{ formatCountPair(social.followers_count, 'followers') }}
                                </p>
                                <p class="mt-2 font-display text-lg font-black tabular-nums text-teal-50">
                                    {{ formatCountPair(social.following_count, 'following') }}
                                </p>
                                <p v-if="!is_authenticated" class="mt-3 text-[11px] font-medium leading-snug text-teal-100/70">
                                    Clients can follow freelancers they trust — log in to follow.
                                </p>
                                <p v-else class="mt-3 text-[11px] font-medium leading-snug text-teal-100/75">
                                    Follows are private — the other party is not notified. You only see updates (like new quests) based on your own settings and categories.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Trust + reviews snapshot -->
                <div class="grid gap-8 border-t border-slate-100 px-6 py-10 sm:px-10 lg:grid-cols-[minmax(0,280px)_1fr] lg:items-center">
                    <div class="flex justify-center lg:justify-start">
                        <TrustHalfDonut
                            :score="profile.trust_score ?? 0"
                            label="Trust score"
                            variant="freelancer"
                            :animate-on-mount="true"
                        />
                    </div>
                    <div class="min-w-0 space-y-6">
                        <div class="flex flex-wrap items-end justify-between gap-4">
                            <div>
                                <h2 class="font-display text-xl font-bold text-slate-900">
                                    Reviews &amp; reputation
                                </h2>
                                <p class="mt-1 text-sm font-medium text-slate-600">
                                    Live feedback from completed quests on HustleSafe.
                                </p>
                            </div>
                            <Link
                                :href="links.reviews_index"
                                class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white shadow-md transition hover:bg-slate-800"
                            >
                                All reviews
                                <ArrowRightIcon class="h-4 w-4" aria-hidden="true" />
                            </Link>
                        </div>
                        <div class="flex flex-wrap gap-4">
                            <div
                                class="rounded-2xl border border-slate-100 bg-gradient-to-br from-slate-50 to-white px-5 py-4 shadow-sm ring-1 ring-slate-100"
                            >
                                <p class="text-xs font-bold uppercase tracking-wider text-slate-500">
                                    Average
                                </p>
                                <p class="mt-1 font-display text-3xl font-black text-slate-900">
                                    <template v-if="profile.avg_rating != null">
                                        {{ Number(profile.avg_rating).toFixed(1) }}
                                        <span class="text-lg font-bold text-secondary-500">★</span>
                                    </template>
                                    <template v-else>—</template>
                                </p>
                            </div>
                            <div
                                class="rounded-2xl border border-slate-100 bg-gradient-to-br from-slate-50 to-white px-5 py-4 shadow-sm ring-1 ring-slate-100"
                            >
                                <p class="text-xs font-bold uppercase tracking-wider text-slate-500">
                                    Total reviews
                                </p>
                                <p class="mt-1 font-display text-3xl font-black tabular-nums text-slate-900">
                                    {{ reviewSnapshot.total }}
                                </p>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <p class="text-xs font-bold uppercase tracking-wider text-slate-500">
                                Rating mix
                            </p>
                            <div class="space-y-2">
                                <div v-for="lvl in 5" :key="lvl" class="flex items-center gap-3">
                                    <span class="w-8 text-xs font-bold text-slate-500">{{ lvl }}★</span>
                                    <div class="h-2.5 min-w-0 flex-1 overflow-hidden rounded-full bg-slate-100">
                                        <div
                                            class="h-full rounded-full bg-gradient-to-r from-secondary-400 to-secondary-600 transition-all duration-700"
                                            :style="{ width: barWidth(lvl) }"
                                        />
                                    </div>
                                    <span class="w-8 text-right text-xs font-semibold tabular-nums text-slate-600">
                                        {{ reviewSnapshot.distribution[String(lvl)] ?? 0 }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Location & rates -->
            <div
                v-if="profile.state || profile.local_government || profile.city || profile.hourly_rate_min || profile.hourly_rate_max"
                class="grid gap-4 sm:grid-cols-2"
            >
                <div
                    v-if="profile.state || profile.local_government || profile.city"
                    class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm ring-1 ring-slate-100"
                >
                    <h2 class="flex items-center gap-2 font-display text-lg font-bold text-slate-900">
                        <MapPinIcon class="h-5 w-5 text-primary-600" aria-hidden="true" />
                        Location
                    </h2>
                    <ul class="mt-4 flex flex-wrap gap-2 text-sm font-semibold text-slate-700">
                        <li v-if="profile.city" class="rounded-xl bg-slate-100 px-3 py-1.5">
                            {{ profile.city }}
                        </li>
                        <li v-if="profile.local_government" class="rounded-xl bg-slate-100 px-3 py-1.5">
                            {{ profile.local_government }}
                        </li>
                        <li v-if="profile.state" class="rounded-xl bg-slate-100 px-3 py-1.5">
                            {{ profile.state }}
                        </li>
                    </ul>
                </div>
                <div
                    v-if="profile.hourly_rate_min || profile.hourly_rate_max"
                    class="rounded-2xl border border-primary-100 bg-gradient-to-br from-primary-50/80 to-white p-6 shadow-sm ring-1 ring-primary-100/80"
                >
                    <h2 class="font-display text-lg font-bold text-primary-950">
                        Rate guide
                    </h2>
                    <p class="mt-3 text-base font-semibold text-primary-900">
                        <template v-if="profile.hourly_rate_min && profile.hourly_rate_max">
                            ₦{{ formatMoney(profile.hourly_rate_min) }} – ₦{{ formatMoney(profile.hourly_rate_max) }} / hr
                        </template>
                        <template v-else> Open to discussion </template>
                    </p>
                </div>
            </div>

            <!-- Contact (only when exposed) -->
            <div
                v-if="profile.phone || profile.email"
                class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
            >
                <h2 class="font-display text-lg font-bold text-slate-900">
                    Contact
                </h2>
                <dl class="mt-4 space-y-2 text-sm font-semibold text-slate-700">
                    <div v-if="profile.phone" class="flex gap-2">
                        <dt class="text-slate-500">
                            Phone
                        </dt>
                        <dd>{{ profile.phone }}</dd>
                    </div>
                    <div v-if="profile.email" class="flex gap-2">
                        <dt class="text-slate-500">
                            Email
                        </dt>
                        <dd>{{ profile.email }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Bio -->
            <section v-if="profile.bio" class="rounded-[1.75rem] border border-slate-100 bg-white p-8 shadow-sm ring-1 ring-slate-100 sm:p-10">
                <h2 class="font-display text-xl font-bold text-slate-900">
                    About
                </h2>
                <p class="mt-4 whitespace-pre-line text-base font-medium leading-relaxed text-slate-700">
                    {{ profile.bio }}
                </p>
            </section>

            <!-- Portfolio -->
            <section v-if="portfolioPreview.length && links.portfolios_index" class="space-y-6">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h2 class="font-display text-2xl font-bold text-slate-900">
                            Portfolio
                        </h2>
                        <p class="mt-1 text-sm font-medium text-slate-600">
                            Recent work — tap through for full case studies.
                        </p>
                    </div>
                    <Link
                        :href="links.portfolios_index"
                        class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-800 shadow-sm transition hover:border-primary-200 hover:bg-primary-50"
                    >
                        Browse all
                        <ArrowRightIcon class="h-4 w-4" aria-hidden="true" />
                    </Link>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Link
                        v-for="p in portfolioPreview"
                        :key="p.slug"
                        :href="route('portfolio.show', p.slug)"
                        class="group overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm ring-1 ring-slate-100 transition hover:-translate-y-0.5 hover:shadow-lg"
                    >
                        <div class="aspect-[16/10] overflow-hidden bg-slate-100">
                            <img
                                v-if="p.cover_url"
                                :src="p.cover_url"
                                :alt="p.title"
                                class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]"
                            />
                            <div v-else class="flex h-full items-center justify-center text-slate-400">
                                <PhotoIcon class="h-10 w-10" aria-hidden="true" />
                            </div>
                        </div>
                        <div class="p-4">
                            <p class="font-display text-base font-bold text-slate-900 line-clamp-2">
                                {{ p.title }}
                            </p>
                            <p class="mt-2 text-xs font-semibold text-slate-500">
                                {{ formatCompact(p.favorites_count) }} likes
                            </p>
                        </div>
                    </Link>
                </div>
            </section>

            <!-- Recent reviews -->
            <section v-if="recentReviews.length" class="space-y-6">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <h2 class="font-display text-2xl font-bold text-slate-900">
                        Recent reviews
                    </h2>
                    <Link :href="links.reviews_index" class="text-sm font-bold text-primary-700 hover:underline">
                        View all
                    </Link>
                </div>
                <ul class="space-y-4">
                    <li
                        v-for="r in recentReviews"
                        :key="r.id"
                        class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm ring-1 ring-slate-50"
                    >
                        <div class="flex flex-wrap items-center gap-3">
                            <span
                                class="rounded-full bg-secondary-50 px-2.5 py-0.5 text-xs font-black text-secondary-800 ring-1 ring-secondary-100"
                            >
                                {{ r.rating ?? '—' }}/5
                            </span>
                            <span class="text-sm font-bold text-slate-900">{{ r.reviewer_label }}</span>
                            <span class="text-xs font-semibold text-slate-500">{{ formatWhen(r.created_at) }}</span>
                        </div>
                        <p v-if="r.title" class="mt-2 font-display text-lg font-bold text-slate-900">
                            {{ r.title }}
                        </p>
                        <p v-if="r.quest_title" class="mt-1 text-xs font-bold uppercase tracking-wide text-primary-700">
                            Quest: {{ r.quest_title }}
                        </p>
                        <p v-if="r.comment" class="mt-3 text-sm font-medium leading-relaxed text-slate-700">
                            {{ r.comment }}
                        </p>
                        <ul v-if="r.attachments?.length" class="mt-4 flex flex-wrap gap-2">
                            <li v-for="(a, idx) in r.attachments" :key="idx">
                                <a
                                    :href="a.url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center gap-1 rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-800 ring-1 ring-slate-200/80 hover:bg-slate-200"
                                >
                                    <PaperClipIcon class="h-3.5 w-3.5" aria-hidden="true" />
                                    {{ a.original_name }}
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </section>

            <!-- CAC -->
            <section v-if="profile.cac && profile.cac.registration_number" class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
                <h2 class="font-display text-xl font-bold text-slate-900">
                    Business registration (CAC)
                </h2>
                <p class="mt-3 text-base font-semibold text-slate-700">
                    RC: {{ profile.cac.registration_number }}
                </p>
                <p class="mt-2 text-sm font-bold uppercase tracking-wide text-slate-500">
                    Status: {{ formatCac(profile.cac.status) }}
                </p>
            </section>

            <!-- Credentials -->
            <section v-if="profile.credentials && profile.credentials.length" class="space-y-4">
                <div>
                    <h2 class="font-display text-2xl font-bold text-slate-900">
                        Certifications &amp; coverage
                    </h2>
                    <p class="mt-2 text-sm font-medium text-slate-600">
                        Licences, insurance, and professional credentials this freelancer chose to display.
                    </p>
                </div>
                <ul class="grid gap-4 md:grid-cols-2">
                    <li
                        v-for="(c, i) in profile.credentials"
                        :key="i"
                        class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm ring-1 ring-slate-100"
                    >
                        <p class="text-xs font-bold uppercase tracking-wide text-primary-700">
                            {{ formatCredType(c.type) }}
                        </p>
                        <p class="mt-2 font-display text-lg font-bold text-slate-900">
                            {{ c.title }}
                        </p>
                        <p v-if="c.issuing_authority" class="mt-2 text-sm font-semibold text-slate-600">
                            {{ c.issuing_authority }}
                        </p>
                        <p v-if="c.reference_number" class="mt-1 text-xs font-medium text-slate-500">
                            Ref: {{ c.reference_number }}
                        </p>
                        <p v-if="c.issued_on || c.expires_on" class="mt-2 text-xs font-semibold text-slate-600">
                            <span v-if="c.issued_on">Issued {{ c.issued_on }}</span>
                            <span v-if="c.expires_on"> · Valid until {{ c.expires_on }}</span>
                        </p>
                        <p v-if="c.coverage_summary" class="mt-3 text-sm font-medium leading-relaxed text-slate-600">
                            {{ c.coverage_summary }}
                        </p>
                        <p v-if="c.is_verified" class="mt-4 inline-flex rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-900">
                            Verified on HustleSafe
                        </p>
                        <p
                            v-else
                            class="mt-4 inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-950 ring-1 ring-amber-200"
                        >
                            Not verified on HustleSafe
                        </p>
                    </li>
                </ul>
            </section>
        </main>
    </div>
</template>

<script setup>
import TrustHalfDonut from '@/Components/Home/TrustHalfDonut.vue';
import UserFollowButton from '@/Components/Profile/UserFollowButton.vue';
import { formatCompactCount, formatCompactCountWithFull } from '@/utils/formatCompactCount';
import {
    ArrowRightIcon,
    BriefcaseIcon,
    MapPinIcon,
    PaperClipIcon,
    PhotoIcon,
} from '@heroicons/vue/24/solid';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    profile: { type: Object, required: true },
    reviewSnapshot: { type: Object, required: true },
    recentReviews: { type: Array, default: () => [] },
    portfolioPreview: { type: Array, default: () => [] },
    links: { type: Object, required: true },
    social: { type: Object, required: true },
    is_authenticated: { type: Boolean, default: false },
    presence: {
        type: Object,
        default: () => ({
            show_indicator: false,
            online: false,
        }),
    },
});

const show_freelancer_join_cta = computed(
    () => !props.is_authenticated || props.viewer_role_slug === 'freelancer',
);

const initials = computed(() => {
    const n = props.profile.name || '';
    const parts = n.trim().split(/\s+/);

    return ((parts[0]?.[0] || 'H') + (parts[1]?.[0] || '')).toUpperCase();
});

const maxDist = computed(() => {
    const d = props.reviewSnapshot.distribution || {};

    return Math.max(1, ...Object.values(d).map((n) => Number(n) || 0));
});

function barWidth(star) {
    const n = Number(props.reviewSnapshot.distribution?.[String(star)] ?? 0);

    return `${Math.round((n / maxDist.value) * 100)}%`;
}

function formatMoney(v) {
    return Number(v).toLocaleString('en-NG');
}

function formatCac(s) {
    return String(s || '').replaceAll('_', ' ');
}

function formatCredType(t) {
    return String(t || '').replaceAll('_', ' ');
}

function formatCountPair(n, label) {
    return `${formatCompactCountWithFull(n)} ${label}`;
}

function formatCompact(n) {
    return formatCompactCount(Number(n) || 0);
}

function formatWhen(iso) {
    if (!iso) {
        return '';
    }
    try {
        return new Date(iso).toLocaleDateString('en-NG', { day: 'numeric', month: 'short', year: 'numeric' });
    } catch {
        return '';
    }
}
</script>
