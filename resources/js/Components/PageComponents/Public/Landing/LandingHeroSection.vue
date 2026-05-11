<template>
    <section
        class="relative isolate flex min-h-[min(92vh,960px)] items-center overflow-hidden pt-24 sm:pt-28 lg:min-h-[min(90vh,920px)] lg:pt-32"
        aria-labelledby="hero-heading"
        data-landing-section="hero"
    >
        <!-- Photo banner — heavily dimmed for contrast -->
        <div class="absolute inset-0">
            <img
                src="/images/landing/banner.jpg"
                alt=""
                class="h-full w-full object-cover object-[52%_center] opacity-[0.28] lg:object-[58%_center]"
                width="1920"
                height="1080"
                loading="eager"
                fetchpriority="high"
                decoding="async"
            />
        </div>
        <div class="absolute inset-0 bg-primary-950/72" aria-hidden="true" />
        <div class="absolute inset-0 bg-black/35" aria-hidden="true" />

        <!-- Primary gradient overlays -->
        <div
            class="absolute inset-0 bg-gradient-to-r from-primary-950/[0.97] via-primary-900/92 to-teal-900/55"
            aria-hidden="true"
        />
        <div
            class="absolute inset-0 bg-gradient-to-t from-black/55 via-primary-950/25 to-teal-800/40"
            aria-hidden="true"
        />
        <div
            class="absolute inset-0 bg-gradient-to-br from-primary-700/35 via-transparent to-secondary-700/18 mix-blend-soft-light"
            aria-hidden="true"
        />
        <div class="pointer-events-none absolute inset-0 ring-1 ring-inset ring-white/10" aria-hidden="true" />

        <div
            class="relative z-10 mx-auto w-full max-w-7xl px-4 pb-20 pt-8 sm:px-6 sm:pb-24 lg:flex lg:items-center lg:px-8 lg:pb-28 xl:px-10"
        >
            <div class="w-full lg:flex lg:justify-start xl:pl-2">
                <div
                    class="mx-auto max-w-xl text-center motion-safe:animate-fade-in-up lg:mx-0 lg:max-w-[26rem] lg:-translate-x-1 lg:text-left xl:max-w-[28rem] xl:-translate-x-3 xl:pr-10"
                >
                    <p
                        class="inline-flex items-center gap-2 rounded-full border border-white/25 bg-black/45 px-5 py-2.5 text-[0.7rem] font-bold uppercase tracking-[0.22em] text-white shadow-lg shadow-black/30 backdrop-blur-md sm:text-xs"
                    >
                        {{ hero.badge }}
                    </p>
                    <h1
                        id="hero-heading"
                        class="font-display mt-7 text-[2.15rem] font-extrabold leading-[1.12] tracking-wide text-white drop-shadow-[0_4px_28px_rgba(0,0,0,0.55)] sm:text-5xl lg:text-[3.25rem] xl:text-6xl"
                    >
                        {{ hero.headline }}
                    </h1>
                    <p
                        class="mt-6 text-lg font-semibold leading-relaxed tracking-wide text-white sm:text-xl sm:leading-[1.65]"
                    >
                        {{ hero.subhead }}
                    </p>
                    <div
                        v-if="hero.mission_offer_definition"
                        class="mt-5 max-w-lg rounded-2xl border border-white/18 bg-black/40 px-5 py-4 text-sm leading-relaxed tracking-wide text-teal-50 shadow-inner shadow-black/20 backdrop-blur-md sm:text-[0.95rem] sm:leading-relaxed"
                    >
                        {{ hero.mission_offer_definition }}
                    </div>

                    <div
                        class="mt-10 flex flex-col items-center gap-4 sm:flex-row sm:flex-wrap sm:justify-center lg:justify-start"
                    >
                        <Link
                            v-if="canRegister"
                            :href="route('register', { intent: 'hire' })"
                            class="group inline-flex min-h-[3.25rem] w-full items-center justify-center rounded-2xl bg-white px-10 py-4 text-base font-extrabold tracking-wide text-primary-900 shadow-xl shadow-black/25 ring-1 ring-white/45 transition duration-300 ease-out hover:-translate-y-0.5 hover:bg-teal-50 hover:text-primary-950 focus:outline-none focus-visible:ring-4 focus-visible:ring-teal-200 sm:w-auto sm:text-lg"
                            @click="onCta('start_mission')"
                        >
                            {{ hero.cta_hire }}
                            <ArrowRightIcon
                                class="ml-2 h-5 w-5 transition duration-300 group-hover:translate-x-1"
                                aria-hidden="true"
                            />
                        </Link>
                        <Link
                            v-if="canRegister"
                            :href="route('register', { intent: 'earn' })"
                            class="inline-flex min-h-[3.25rem] w-full items-center justify-center rounded-2xl border-2 border-white/45 bg-black/25 px-10 py-4 text-base font-extrabold tracking-wide text-white shadow-lg shadow-black/30 backdrop-blur-md transition duration-300 ease-out hover:-translate-y-0.5 hover:border-white/65 hover:bg-black/35 focus:outline-none focus-visible:ring-4 focus-visible:ring-white/35 sm:w-auto sm:text-lg"
                            @click="onCta('create_offer')"
                        >
                            {{ hero.cta_earn }}
                        </Link>
                    </div>

                    <div
                        class="mt-12 flex flex-wrap items-center justify-center gap-x-6 gap-y-3 border-t border-white/20 pt-8 lg:justify-start"
                    >
                        <span
                            class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-black/35 px-4 py-2 text-sm font-semibold tracking-wide text-white shadow-md backdrop-blur-sm"
                        >
                            <ShieldCheckIcon class="h-5 w-5 shrink-0 text-teal-200" aria-hidden="true" />
                            Escrow until approval
                        </span>
                        <span
                            class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-black/35 px-4 py-2 text-sm font-semibold tracking-wide text-white shadow-md backdrop-blur-sm"
                        >
                            <WrenchScrewdriverIcon class="h-5 w-5 shrink-0 text-teal-200" aria-hidden="true" />
                            ₦ milestones, clean delivery
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import {
    ArrowRightIcon,
    ShieldCheckIcon,
    WrenchScrewdriverIcon,
} from '@heroicons/vue/24/solid';
import { trackLanding } from '@/composables/useLandingAnalytics.js';

defineProps({
    hero: {
        type: Object,
        required: true,
    },
    canRegister: {
        type: Boolean,
        default: false,
    },
});

function onCta(id) {
    trackLanding('cta_click', { section: 'hero', id });
}
</script>
