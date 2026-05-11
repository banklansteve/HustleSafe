<template>
    <section
        id="popular-jobs"
        ref="target"
        class="relative scroll-mt-24 overflow-hidden bg-slate-50 py-14 sm:py-20 lg:py-24"
        aria-labelledby="popular-jobs-heading"
        data-landing-section="popular-jobs"
    >
        <LandingAbstractBackdrop variant="popular_jobs" />

        <div class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-secondary-300/40 to-transparent" />

        <div class="relative z-10 mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <p
                        class="text-xs font-bold uppercase tracking-[0.2em] text-secondary-600"
                    >
                        {{ block.kicker }}
                    </p>
                    <h2
                        id="popular-jobs-heading"
                        class="font-display mt-4 text-3xl font-extrabold tracking-[0.03em] text-slate-900 sm:text-4xl lg:text-5xl"
                    >
                        {{ block.title }}
                    </h2>
                    <p class="mt-4 text-lg text-slate-600 sm:text-xl">
                        {{ block.subtitle }}
                    </p>
                </div>
                <Link
                    v-if="canRegister"
                    :href="route('register', { intent: 'hire' })"
                    class="inline-flex items-center justify-center rounded-2xl bg-primary-600 px-6 py-3.5 text-sm font-extrabold text-white shadow-lg shadow-primary-900/15 ring-1 ring-secondary-400/25 transition hover:-translate-y-0.5 hover:bg-primary-700 focus:outline-none focus-visible:ring-4 focus-visible:ring-primary-300"
                    @click="trackLanding('cta_click', { section: 'popular_jobs', id: 'post_quest' })"
                >
                    {{ block.cta }}
                </Link>
            </div>

            <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <article
                    v-for="(job, index) in block.items"
                    :key="job.title"
                    class="group relative overflow-hidden rounded-3xl bg-white/90 p-6 shadow-lg shadow-slate-300/40 ring-1 ring-white/80 backdrop-blur-sm transition duration-500 ease-out hover:-translate-y-1 hover:shadow-xl hover:ring-secondary-200/60"
                    :class="isVisible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0'"
                    :style="{ transitionDelay: `${index * 80}ms` }"
                >
                    <div
                        class="absolute -right-8 -top-8 h-24 w-24 rounded-full bg-gradient-to-br from-secondary-400/25 to-primary-400/15 blur-2xl transition group-hover:opacity-100"
                        aria-hidden="true"
                    />
                    <div class="relative">
                        <div class="flex items-start justify-between gap-3">
                            <span
                                class="rounded-full bg-primary-50 px-3 py-1 text-xs font-bold text-primary-800 ring-1 ring-primary-100"
                            >
                                {{ job.category }}
                            </span>
                            <span
                                class="inline-flex shrink-0 items-center gap-0.5 rounded-full bg-secondary-50 px-2.5 py-1 ring-1 ring-secondary-100"
                                role="img"
                                :aria-label="`${job.rating} out of 5 stars`"
                            >
                                <StarIcon
                                    v-for="n in job.rating"
                                    :key="n"
                                    class="h-3.5 w-3.5 text-amber-400"
                                    aria-hidden="true"
                                />
                            </span>
                        </div>
                        <h3 class="font-display mt-4 text-lg font-bold leading-snug text-slate-900">
                            {{ job.title }}
                        </h3>
                        <p class="mt-3 text-sm leading-relaxed text-slate-600">
                            “{{ job.review }}”
                        </p>
                        <p class="mt-5 text-xs font-semibold uppercase tracking-wider text-slate-400">
                            {{ job.label }}
                        </p>
                    </div>
                </article>
            </div>
        </div>
    </section>
</template>

<script setup>
import LandingAbstractBackdrop from '@/Components/PageComponents/Public/Landing/LandingAbstractBackdrop.vue';
import { Link } from '@inertiajs/vue3';
import { StarIcon } from '@heroicons/vue/24/solid';
import { trackLanding } from '@/composables/useLandingAnalytics.js';
import { useScrollReveal } from '@/composables/useScrollReveal.js';

defineProps({
    block: {
        type: Object,
        required: true,
    },
    canRegister: {
        type: Boolean,
        default: false,
    },
});

const { target, isVisible } = useScrollReveal();
</script>
