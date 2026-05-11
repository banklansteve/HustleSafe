<template>
    <section
        id="how-it-works"
        ref="target"
        class="relative scroll-mt-24 bg-slate-50 py-14 sm:py-20 lg:py-24"
        aria-labelledby="how-heading"
        data-landing-section="how-it-works"
    >
        <div class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-primary-200 to-transparent" />
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div
                class="max-w-3xl transition duration-700"
                :class="isVisible ? 'translate-y-0 opacity-100' : 'translate-y-6 opacity-0'"
            >
                <h2 id="how-heading" class="font-display text-3xl font-extrabold tracking-[0.03em] text-slate-900 sm:text-4xl lg:text-5xl">
                    {{ block.title }}
                </h2>
                <p class="mt-4 text-lg text-slate-600 sm:text-xl">
                    {{ block.subtitle }}
                </p>
            </div>

            <div class="mt-10 grid gap-8 sm:mt-14 sm:gap-10 lg:grid-cols-2 lg:gap-12">
                <article
                    class="rounded-3xl bg-white p-5 shadow-lg shadow-slate-200/60 ring-1 ring-slate-100 transition duration-700 sm:p-8"
                    :class="isVisible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0'"
                    :style="{ transitionDelay: isVisible ? '80ms' : '0ms' }"
                >
                    <h3 class="font-display text-xl font-bold text-slate-900 sm:text-2xl">
                        {{ block.clients_title }}
                    </h3>
                    <ol class="mt-8 space-y-8">
                        <li
                            v-for="(step, index) in block.clients_steps"
                            :key="step.title"
                            class="flex gap-4"
                        >
                            <div
                                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-primary-50 text-primary-700 ring-1 ring-primary-100"
                                aria-hidden="true"
                            >
                                <component :is="clientIcons[index]" class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="font-display text-lg font-bold text-slate-900">
                                    {{ step.title }}
                                </p>
                                <p class="mt-2 text-sm leading-relaxed text-slate-600 sm:text-base">
                                    {{ step.body }}
                                </p>
                            </div>
                        </li>
                    </ol>
                </article>

                <article
                    class="rounded-3xl bg-gradient-to-br from-white via-teal-50/70 to-primary-50/50 p-6 text-slate-900 shadow-lg shadow-slate-200/90 ring-1 ring-slate-200/90 transition duration-700 sm:p-8"
                    :class="isVisible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0'"
                    :style="{ transitionDelay: isVisible ? '160ms' : '0ms' }"
                >
                    <h3 class="font-display text-xl font-bold text-slate-900 sm:text-2xl">
                        {{ block.freelancers_title }}
                    </h3>
                    <ol class="mt-8 space-y-8">
                        <li
                            v-for="(step, index) in block.freelancer_steps"
                            :key="step.title"
                            class="flex gap-4"
                        >
                            <div
                                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-primary-50 text-primary-700 ring-1 ring-primary-100"
                                aria-hidden="true"
                            >
                                <component :is="freelancerIcons[index]" class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="font-display text-lg font-bold text-slate-900">
                                    {{ step.title }}
                                </p>
                                <p class="mt-2 text-sm leading-relaxed text-slate-600 sm:text-base">
                                    {{ step.body }}
                                </p>
                            </div>
                        </li>
                    </ol>
                </article>
            </div>
        </div>
    </section>
</template>

<script setup>
import {
    BanknotesIcon,
    CheckBadgeIcon,
    ClipboardDocumentCheckIcon,
    ClipboardDocumentListIcon,
    ShieldCheckIcon,
    UserGroupIcon,
    WrenchScrewdriverIcon,
} from '@heroicons/vue/24/outline';
import { useScrollReveal } from '@/composables/useScrollReveal.js';

defineProps({
    block: {
        type: Object,
        required: true,
    },
});

const clientIcons = [ClipboardDocumentListIcon, UserGroupIcon, CheckBadgeIcon];
const freelancerIcons = [WrenchScrewdriverIcon, ClipboardDocumentCheckIcon, BanknotesIcon];

const { target, isVisible } = useScrollReveal();
</script>
