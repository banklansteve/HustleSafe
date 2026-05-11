<template>
    <section
        class="relative isolate flex min-h-[min(88vh,900px)] items-center overflow-hidden pt-[5.25rem] sm:min-h-[min(90vh,940px)] sm:pt-28 lg:min-h-[min(92vh,960px)] lg:pt-32"
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
            class="absolute inset-0 bg-gradient-to-br from-primary-700/35 via-transparent to-secondary-500/22 mix-blend-soft-light"
            aria-hidden="true"
        />
        <div class="pointer-events-none absolute inset-0 ring-1 ring-inset ring-white/10" aria-hidden="true" />

        <div
            class="relative z-10 mx-auto w-full max-w-7xl px-4 pb-16 pt-6 sm:px-6 sm:pb-20 sm:pt-8 lg:flex lg:items-center lg:px-8 lg:pb-28 lg:pt-4 xl:px-10"
        >
            <div class="w-full lg:flex lg:justify-start xl:pl-0">
                <div
                    class="mx-auto w-full max-w-[36rem] text-center motion-safe:animate-fade-in-up sm:max-w-[40rem] lg:mx-0 lg:max-w-[38rem] lg:-translate-x-1 lg:text-left xl:max-w-[46rem] 2xl:max-w-[52rem] xl:-translate-x-2 xl:pr-8"
                >
                    <p
                        class="inline-flex items-center gap-2 rounded-full border border-white/25 bg-black/45 px-5 py-2.5 text-[0.7rem] font-bold uppercase tracking-[0.22em] text-white shadow-lg shadow-black/30 backdrop-blur-md sm:text-xs"
                    >
                        {{ hero.badge }}
                    </p>
                    <h1
                        id="hero-heading"
                        class="font-display mt-6 text-[2.35rem] font-black leading-[1.08] tracking-[0.03em] text-white drop-shadow-[0_4px_28px_rgba(0,0,0,0.55)] sm:mt-7 sm:text-5xl sm:leading-[1.1] lg:mt-8 lg:text-[3.65rem] lg:leading-[1.08] xl:text-[4.35rem] xl:leading-[1.06] 2xl:text-[4.85rem]"
                    >
                        {{ hero.headline }}
                    </h1>
                    <p
                        class="mt-4 max-w-none text-base font-semibold leading-relaxed tracking-wide text-white/95 sm:mt-5 sm:text-lg sm:leading-relaxed lg:mt-6 lg:text-xl lg:leading-relaxed"
                    >
                        {{ hero.subhead }}
                    </p>
                    <p
                        v-if="hero.lead"
                        class="mt-3 max-w-none text-sm font-medium leading-relaxed tracking-wide text-secondary-300 sm:text-base lg:mt-4"
                    >
                        {{ hero.lead }}
                    </p>

                    <div
                        class="mt-9 flex flex-col items-stretch gap-3.5 sm:mt-10 sm:flex-row sm:flex-wrap sm:items-center sm:justify-center sm:gap-4 lg:justify-start"
                    >
                        <Link
                            v-if="canRegister"
                            :href="route('register', { intent: 'hire' })"
                            class="group inline-flex min-h-[3.25rem] w-full items-center justify-center rounded-2xl bg-white px-8 py-4 text-base font-extrabold tracking-wide text-primary-900 shadow-xl shadow-black/25 ring-1 ring-white/45 transition duration-300 ease-out hover:-translate-y-0.5 hover:bg-teal-50 hover:text-primary-950 focus:outline-none focus-visible:ring-4 focus-visible:ring-teal-200 sm:w-auto sm:px-10 sm:text-lg"
                            @click="onCta('start_quest')"
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
                            class="inline-flex min-h-[3.25rem] w-full items-center justify-center rounded-2xl border-2 border-secondary-400/85 bg-secondary-500/15 px-8 py-4 text-base font-extrabold tracking-wide text-white shadow-lg shadow-secondary-900/25 backdrop-blur-md transition duration-300 ease-out hover:-translate-y-0.5 hover:border-secondary-300 hover:bg-secondary-500/25 focus:outline-none focus-visible:ring-4 focus-visible:ring-secondary-300/50 sm:w-auto sm:px-10 sm:text-lg"
                            @click="onCta('create_offer')"
                        >
                            {{ hero.cta_earn }}
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { ArrowRightIcon } from '@heroicons/vue/24/solid';
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
