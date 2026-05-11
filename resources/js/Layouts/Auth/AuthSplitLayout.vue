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
                        class="mb-10 inline-flex w-fit items-center gap-2 rounded-xl text-slate-900 transition hover:bg-white/80 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                    >
                        <span
                            class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-600 text-sm font-black text-white shadow-sm ring-1 ring-primary-500/30"
                        >
                            HS
                        </span>
                        <span class="font-display text-lg font-bold tracking-tight">HustleSafe</span>
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
                class="flex min-h-screen flex-col justify-center px-4 py-12 sm:px-8 lg:px-12 xl:px-16"
            >
                <div class="mx-auto w-full max-w-md animate-fade-in-up">
                    <div class="mb-8 lg:hidden">
                        <Link
                            href="/"
                            class="inline-flex items-center gap-2 rounded-lg text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                        >
                            <span
                                class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary-600 text-xs font-black text-white shadow-sm"
                            >
                                HS
                            </span>
                            <span class="font-display text-lg font-bold">HustleSafe</span>
                        </Link>
                    </div>

                    <!-- Mobile illustration -->
                    <div class="mb-8 flex justify-center lg:hidden">
                        <div class="rounded-3xl bg-white p-3 shadow-lg shadow-slate-300/50 ring-1 ring-slate-200">
                            <img
                                :src="illustrationSrc"
                                :alt="illustrationAlt"
                                class="max-h-44 w-auto object-contain"
                                loading="lazy"
                                decoding="async"
                            />
                        </div>
                    </div>

                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>

<script setup>
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
