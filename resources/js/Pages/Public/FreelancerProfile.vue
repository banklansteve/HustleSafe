<template>
    <Head :title="profile.name + ' · Freelancer'" />

    <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-slate-50">
        <header class="border-b border-slate-200/90 bg-white/95 backdrop-blur">
            <div class="mx-auto flex max-w-4xl items-center justify-between gap-4 px-4 py-5 sm:px-6">
                <Link href="/" class="font-display text-lg font-bold text-slate-900">
                    HustleSafe
                </Link>
                <Link
                    :href="route('register', { intent: 'earn' })"
                    class="rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-bold text-white shadow-md shadow-primary-900/15"
                >
                    Join as freelancer
                </Link>
            </div>
        </header>

        <main class="mx-auto max-w-4xl px-4 py-10 sm:px-6 sm:py-14">
            <section class="overflow-hidden rounded-[2rem] border border-slate-100 bg-white shadow-lg shadow-slate-200/60 ring-1 ring-slate-100">
                <div class="bg-gradient-to-br from-primary-700 via-primary-800 to-slate-900 px-6 py-10 text-white sm:px-10 sm:py-12">
                    <div class="flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between">
                        <div class="flex gap-5">
                            <div
                                class="flex h-20 w-20 shrink-0 items-center justify-center rounded-2xl bg-white/15 text-2xl font-black ring-1 ring-white/25"
                            >
                                {{ initials }}
                            </div>
                            <div>
                                <h1 class="font-display text-3xl font-black tracking-tight sm:text-4xl">
                                    {{ profile.name }}
                                </h1>
                                <p v-if="profile.headline" class="mt-2 text-base font-semibold text-teal-100 sm:text-lg">
                                    {{ profile.headline }}
                                </p>
                                <p v-if="profile.profession" class="mt-2 text-sm font-bold uppercase tracking-wide text-teal-200/90">
                                    {{ profile.profession }}
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <span class="rounded-full bg-black/25 px-4 py-2 text-sm font-bold ring-1 ring-white/20">
                                Trust {{ profile.trust_score ?? 0 }}/100
                            </span>
                            <span v-if="profile.avg_rating != null" class="rounded-full bg-black/25 px-4 py-2 text-sm font-bold ring-1 ring-white/20">
                                ★ {{ Number(profile.avg_rating).toFixed(1) }} ({{ profile.rating_count }} reviews)
                            </span>
                            <span v-if="profile.years_experience != null" class="rounded-full bg-black/25 px-4 py-2 text-sm font-bold ring-1 ring-white/20">
                                {{ profile.years_experience }}+ yrs experience
                            </span>
                        </div>
                    </div>
                </div>

                <div class="space-y-10 px-6 py-10 sm:px-10 sm:py-12">
                    <div v-if="profile.state || profile.local_government" class="flex flex-wrap gap-3 text-base font-semibold text-slate-700">
                        <span v-if="profile.local_government" class="rounded-xl bg-slate-100 px-4 py-2">
                            {{ profile.local_government }} LGA
                        </span>
                        <span v-if="profile.state" class="rounded-xl bg-slate-100 px-4 py-2">
                            {{ profile.state }}
                        </span>
                    </div>

                    <div v-if="profile.bio" class="rounded-2xl border border-slate-100 bg-slate-50/80 p-6">
                        <h2 class="font-display text-lg font-bold text-slate-900">
                            About
                        </h2>
                        <p class="mt-3 whitespace-pre-line text-base font-medium leading-relaxed text-slate-700">
                            {{ profile.bio }}
                        </p>
                    </div>

                    <div v-if="profile.hourly_rate_min || profile.hourly_rate_max" class="rounded-2xl border border-primary-100 bg-primary-50/60 p-6">
                        <h2 class="font-display text-lg font-bold text-primary-950">
                            Rate guide
                        </h2>
                        <p class="mt-2 text-base font-semibold text-primary-900">
                            <template v-if="profile.hourly_rate_min && profile.hourly_rate_max">
                                ₦{{ formatMoney(profile.hourly_rate_min) }} – ₦{{ formatMoney(profile.hourly_rate_max) }} / hr
                            </template>
                            <template v-else>
                                Open to discussion
                            </template>
                        </p>
                    </div>

                    <div v-if="profile.cac && profile.cac.registration_number" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 class="font-display text-lg font-bold text-slate-900">
                            Business registration (CAC)
                        </h2>
                        <p class="mt-2 text-base font-semibold text-slate-700">
                            RC: {{ profile.cac.registration_number }}
                        </p>
                        <p class="mt-2 text-sm font-semibold uppercase tracking-wide text-slate-500">
                            Status: {{ formatCac(profile.cac.status) }}
                        </p>
                    </div>

                    <div v-if="profile.credentials && profile.credentials.length">
                        <h2 class="font-display text-xl font-bold text-slate-900">
                            Certifications & credentials
                        </h2>
                        <p class="mt-2 text-base font-semibold text-slate-600">
                            Licences, insurance, and professional qualifications this freelancer chose to show publicly.
                        </p>
                        <ul class="mt-6 space-y-4">
                            <li
                                v-for="(c, i) in profile.credentials"
                                :key="i"
                                class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm"
                            >
                                <p class="text-sm font-bold uppercase tracking-wide text-primary-700">
                                    {{ formatCredType(c.type) }}
                                </p>
                                <p class="mt-2 font-display text-lg font-bold text-slate-900">
                                    {{ c.title }}
                                </p>
                                <p v-if="c.issuing_authority" class="mt-2 text-base font-semibold text-slate-600">
                                    {{ c.issuing_authority }}
                                </p>
                                <p v-if="c.reference_number" class="mt-1 text-sm font-medium text-slate-500">
                                    Ref: {{ c.reference_number }}
                                </p>
                                <p v-if="c.issued_on || c.expires_on" class="mt-2 text-sm font-semibold text-slate-600">
                                    <span v-if="c.issued_on">Issued {{ c.issued_on }}</span>
                                    <span v-if="c.expires_on"> · Valid until {{ c.expires_on }}</span>
                                </p>
                                <p v-if="c.coverage_summary" class="mt-3 text-sm font-medium leading-relaxed text-slate-600">
                                    {{ c.coverage_summary }}
                                </p>
                                <p v-if="c.is_verified" class="mt-3 inline-flex rounded-full bg-emerald-100 px-3 py-1 text-sm font-bold text-emerald-900">
                                    Verified on HustleSafe
                                </p>
                            </li>
                        </ul>
                    </div>
                </div>
            </section>
        </main>
    </div>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    profile: {
        type: Object,
        required: true,
    },
});

const initials = computed(() => {
    const n = props.profile.name || '';
    const parts = n.trim().split(/\s+/);

    return (parts[0]?.[0] || 'H') + (parts[1]?.[0] || '');
});

function formatMoney(v) {
    return Number(v).toLocaleString('en-NG');
}

function formatCac(s) {
    return String(s || '').replaceAll('_', ' ');
}

function formatCredType(t) {
    return String(t || '').replaceAll('_', ' ');
}
</script>
