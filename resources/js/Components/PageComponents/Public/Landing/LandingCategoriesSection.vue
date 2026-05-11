<template>
    <section
        id="categories"
        ref="target"
        class="relative scroll-mt-24 overflow-hidden bg-slate-50 py-14 sm:py-20 lg:py-24"
        aria-labelledby="categories-heading"
        data-landing-section="categories"
    >
        <LandingAbstractBackdrop variant="categories" />
        <div class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-primary-300/50 to-transparent" />

        <div class="relative z-10 mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <h2 id="categories-heading" class="font-display text-3xl font-extrabold tracking-[0.03em] text-slate-900 sm:text-4xl lg:text-5xl">
                        {{ block.title }}
                    </h2>
                    <p class="mt-4 text-lg text-slate-600 sm:text-xl">
                        {{ block.subtitle }}
                    </p>
                </div>
                <Link
                    v-if="canRegister"
                    :href="route('register', { flow: 'browse' })"
                    class="inline-flex items-center justify-center rounded-2xl bg-primary-600 px-6 py-3.5 text-sm font-extrabold text-white shadow-lg shadow-primary-900/20 ring-1 ring-primary-500/40 transition hover:-translate-y-0.5 hover:bg-primary-700 hover:ring-secondary-400/35 focus:outline-none focus-visible:ring-4 focus-visible:ring-primary-300"
                    @click="trackLanding('cta_click', { section: 'categories', id: 'browse_freelancers' })"
                >
                    {{ block.browse }}
                </Link>
            </div>

            <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <article
                    v-for="(item, index) in block.items"
                    :key="item.name"
                    class="group rounded-3xl bg-white/95 p-6 shadow-md shadow-slate-200/70 ring-1 ring-slate-100 backdrop-blur-sm transition duration-500 ease-out hover:-translate-y-1 hover:shadow-xl"
                    :class="isVisible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0'"
                    :style="{ transitionDelay: `${index * 70}ms` }"
                >
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary-50 text-primary-700 ring-1 ring-primary-100 transition group-hover:bg-primary-600 group-hover:text-white"
                        aria-hidden="true"
                    >
                        <component :is="icons[index]" class="h-6 w-6" />
                    </div>
                    <h3 class="font-display mt-5 text-xl font-bold text-slate-900">
                        {{ item.name }}
                    </h3>
                    <p class="mt-2 text-sm text-slate-600">
                        {{ item.hint }}
                    </p>
                    <Link
                        v-if="canRegister"
                        :href="route('register', { interest: item.name })"
                        class="mt-5 inline-flex items-center text-sm font-bold text-primary-700 underline-offset-4 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2"
                        @click="trackLanding('cta_click', { section: 'categories', id: `category_${index}` })"
                    >
                        {{ block.browse }}
                    </Link>
                </article>
            </div>
        </div>
    </section>
</template>

<script setup>
import LandingAbstractBackdrop from '@/Components/PageComponents/Public/Landing/LandingAbstractBackdrop.vue';
import { Link } from '@inertiajs/vue3';
import {
    CodeBracketIcon,
    MegaphoneIcon,
    PaintBrushIcon,
    PencilSquareIcon,
    VideoCameraIcon,
    WrenchScrewdriverIcon,
} from '@heroicons/vue/24/outline';
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

const icons = [
    PaintBrushIcon,
    CodeBracketIcon,
    PencilSquareIcon,
    MegaphoneIcon,
    VideoCameraIcon,
    WrenchScrewdriverIcon,
];

const { target, isVisible } = useScrollReveal();
</script>
