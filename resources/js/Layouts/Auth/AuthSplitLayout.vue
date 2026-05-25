<template>
    <div class="min-h-screen bg-slate-100">
        <div class="grid min-h-screen lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
            <!-- Illustration panel -->
            <aside
                class="relative hidden flex-col justify-center overflow-hidden border-r border-slate-200/80 bg-gradient-to-br from-white via-teal-50/50 to-slate-50 px-8 py-14 xl:px-14 lg:flex"
            >
                <div class="pointer-events-none absolute inset-0">
                    <div class="absolute -right-16 top-24 h-72 w-72 rounded-full bg-primary-200/25 blur-3xl" />
                    <div class="absolute -left-10 bottom-32 h-80 w-80 rounded-full bg-teal-200/30 blur-3xl" />
                </div>

                <div class="relative mx-auto flex w-full max-w-lg flex-col justify-center">
                    <Link
                        href="/"
                        class="mb-10 inline-flex w-fit rounded-xl text-slate-900 transition hover:bg-white/80 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                    >
                        <HustleSafeLogo variant="lockup" theme="light" lockup-class="h-8 w-auto max-w-[9.5rem]" />
                    </Link>

                    <div class="animate-fade-in-up">
                        <p
                            v-if="asideEyebrow"
                            class="text-xs font-bold uppercase tracking-[0.2em] text-primary-700"
                        >
                            {{ asideEyebrow }}
                        </p>
                        <p class="font-display mt-3 text-2xl font-extrabold leading-tight text-slate-900 xl:text-3xl">
                            {{ asideTitle }}
                        </p>
                        <p class="mt-4 text-sm leading-relaxed text-slate-600 xl:text-base">
                            {{ asideSubtitle }}
                        </p>
                    </div>

                    <div class="relative mt-10 flex justify-center animate-fade-in-up [animation-delay:120ms]">
                        <div
                            class="rounded-[2rem] bg-white p-4 shadow-xl shadow-slate-300/40 ring-1 ring-slate-200/90 transition duration-500 hover:shadow-2xl hover:shadow-primary-900/10"
                        >
                            <img
                                :src="illustrationSrc"
                                :alt="illustrationAlt"
                                class="mx-auto h-auto max-h-[min(52vh,520px)] w-full max-w-md object-contain select-none"
                                width="560"
                                height="560"
                                loading="eager"
                                decoding="async"
                            />
                        </div>
                    </div>

                    <p class="relative mt-12 text-center text-xs font-medium text-slate-400">
                        © {{ year }} HustleSafe
                    </p>
                </div>
            </aside>

            <!-- Form panel -->
            <main
                class="flex min-h-screen flex-col justify-start px-4 pb-10 pt-8 sm:justify-center sm:px-8 sm:py-12 lg:justify-center lg:px-14 lg:py-14 xl:px-20 2xl:px-24"
            >
                <div
                    class="mx-auto w-full max-w-md animate-fade-in-up sm:max-w-lg lg:max-w-xl xl:max-w-[30rem]"
                >
                    <div class="mb-6 lg:mb-8 lg:hidden">
                        <Link
                            href="/"
                            class="inline-flex rounded-lg text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                        >
                            <HustleSafeLogo variant="lockup" theme="light" lockup-class="h-7 w-auto max-w-[8.5rem]" />
                        </Link>
                    </div>

                    <slot />

                    <!-- Mobile: illustration below the form card so the card is in view first -->
                    <div class="mt-8 flex justify-center pb-2 lg:hidden">
                        <div
                            class="rounded-2xl bg-white/80 p-3 shadow-md shadow-slate-300/40 ring-1 ring-slate-200/80 backdrop-blur-sm"
                        >
                            <img
                                :src="illustrationSrc"
                                :alt="illustrationAlt"
                                class="mx-auto max-h-36 w-auto max-w-[min(100%,280px)] object-contain opacity-90"
                                loading="lazy"
                                decoding="async"
                            />
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</template>

<script setup>
import HustleSafeLogo from '@/Components/Brand/HustleSafeLogo.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    illustrationSrc: {
        type: String,
        required: true,
    },
    illustrationAlt: {
        type: String,
        default: '',
    },
    asideEyebrow: {
        type: String,
        default: '',
    },
    asideTitle: {
        type: String,
        required: true,
    },
    asideSubtitle: {
        type: String,
        required: true,
    },
});

const year = computed(() => new Date().getFullYear());
</script>
