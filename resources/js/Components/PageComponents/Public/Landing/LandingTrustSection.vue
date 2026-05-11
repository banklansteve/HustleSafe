<template>
    <section
        id="trust"
        ref="target"
        class="relative scroll-mt-24 overflow-hidden bg-white py-16 sm:py-20 lg:py-24"
        aria-labelledby="trust-heading"
        data-landing-section="trust"
    >
        <div class="pointer-events-none absolute -left-40 top-24 h-80 w-80 rounded-full bg-primary-100 blur-3xl" />
        <div class="pointer-events-none absolute -right-40 bottom-10 h-96 w-96 rounded-full bg-teal-100 blur-3xl" />

        <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl">
                <h2 id="trust-heading" class="font-display text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl lg:text-5xl">
                    {{ block.title }}
                </h2>
                <p class="mt-4 text-lg text-slate-600 sm:text-xl">
                    {{ block.lead }}
                </p>
            </div>

            <div class="mt-12 grid gap-6 lg:grid-cols-3">
                <article class="rounded-3xl bg-slate-50 p-6 ring-1 ring-slate-100 sm:p-7">
                    <div class="flex items-center gap-3">
                        <LockClosedIcon class="h-8 w-8 text-primary-700" aria-hidden="true" />
                        <h3 class="font-display text-lg font-bold text-slate-900">
                            {{ block.escrow_title }}
                        </h3>
                    </div>
                    <p class="mt-4 text-sm leading-relaxed text-slate-600 sm:text-base">
                        {{ block.escrow_body }}
                    </p>
                </article>
                <article class="rounded-3xl bg-slate-50 p-6 ring-1 ring-slate-100 sm:p-7">
                    <div class="flex items-center gap-3">
                        <IdentificationIcon class="h-8 w-8 text-primary-700" aria-hidden="true" />
                        <h3 class="font-display text-lg font-bold text-slate-900">
                            {{ block.verification_title }}
                        </h3>
                    </div>
                    <p class="mt-4 text-sm leading-relaxed text-slate-600 sm:text-base">
                        {{ block.verification_body }}
                    </p>
                </article>
                <article class="rounded-3xl bg-slate-50 p-6 ring-1 ring-slate-100 sm:p-7">
                    <div class="flex items-center gap-3">
                        <ScaleIcon class="h-8 w-8 text-primary-700" aria-hidden="true" />
                        <h3 class="font-display text-lg font-bold text-slate-900">
                            {{ block.disputes_title }}
                        </h3>
                    </div>
                    <p class="mt-4 text-sm leading-relaxed text-slate-600 sm:text-base">
                        {{ block.disputes_body }}
                    </p>
                </article>
            </div>

            <div
                class="mt-14 rounded-[2rem] border border-slate-200/80 bg-gradient-to-br from-white via-teal-50/50 to-primary-50/40 p-6 shadow-xl shadow-slate-300/40 ring-1 ring-slate-200/90 sm:p-10"
            >
                <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-xl">
                        <h3 class="font-display text-2xl font-bold text-slate-900 sm:text-3xl">
                            {{ block.microflow_title }}
                        </h3>
                        <p class="mt-3 text-sm text-slate-600 sm:text-base">
                            {{ block.microflow_subtitle }}
                        </p>
                    </div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-primary-700">
                        Escrow microflow
                    </p>
                </div>

                <ol
                    class="mt-10 grid gap-4 lg:grid-cols-4"
                    aria-label="Escrow microflow steps"
                >
                    <li
                        v-for="(step, index) in block.microflow_steps"
                        :key="step"
                        class="relative rounded-2xl bg-white p-4 shadow-md shadow-slate-200/70 ring-1 ring-slate-100 transition duration-500"
                        :class="isVisible ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0'"
                        :style="{ transitionDelay: `${120 + index * 110}ms` }"
                    >
                        <p class="text-xs font-bold uppercase tracking-wide text-primary-600">
                            Step {{ index + 1 }}
                        </p>
                        <p class="mt-3 text-sm font-semibold leading-snug text-slate-800 sm:text-base">
                            {{ step }}
                        </p>
                    </li>
                </ol>
            </div>

            <div class="mt-12 grid gap-4 sm:grid-cols-3">
                <div
                    v-for="(badge, index) in block.badges"
                    :key="badge.label"
                    class="flex items-center gap-4 rounded-2xl bg-white p-4 shadow-md shadow-slate-200/60 ring-1 ring-slate-100 transition duration-500"
                    :class="isVisible ? 'translate-y-0 opacity-100' : 'translate-y-6 opacity-0'"
                    :style="{ transitionDelay: `${200 + index * 120}ms` }"
                >
                    <span
                        class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary-600 text-sm font-black text-white shadow-inner"
                        aria-hidden="true"
                    >
                        {{ badge.abbr }}
                    </span>
                    <p class="text-sm font-semibold text-slate-800 sm:text-base">
                        {{ badge.label }}
                    </p>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { IdentificationIcon, LockClosedIcon, ScaleIcon } from '@heroicons/vue/24/solid';
import { useScrollReveal } from '@/composables/useScrollReveal.js';

defineProps({
    block: {
        type: Object,
        required: true,
    },
});

const { target, isVisible } = useScrollReveal({ threshold: 0.08 });
</script>
