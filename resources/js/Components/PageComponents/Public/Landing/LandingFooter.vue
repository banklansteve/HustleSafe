<template>
    <footer class="border-t border-slate-200 bg-white" aria-labelledby="footer-heading">
        <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 sm:py-14 lg:px-8 lg:py-16">
            <div
                class="flex w-full flex-col gap-10 lg:flex-row lg:items-start lg:justify-between lg:gap-10 xl:gap-14"
            >
                <div class="shrink-0 lg:max-w-xs xl:max-w-sm">
                    <p id="footer-heading" class="font-display text-2xl font-extrabold tracking-wide text-slate-900">
                        HustleSafe
                    </p>
                    <p class="mt-4 text-sm leading-relaxed tracking-wide text-slate-600 sm:text-base">
                        {{ block.tagline }}
                    </p>
                </div>

                <div
                    class="flex min-w-0 flex-1 flex-col gap-10 sm:flex-row sm:justify-between sm:gap-6 lg:gap-8 xl:gap-10"
                >
                    <div class="flex min-h-full min-w-0 flex-1 flex-col px-1 py-1 sm:px-2">
                        <h3 class="text-xs font-bold uppercase tracking-[0.2em] text-slate-500">
                            {{ block.product }}
                        </h3>
                        <ul class="mt-4 flex flex-col gap-3">
                            <li v-for="link in block.links_product" :key="link.label">
                                <a
                                    :href="link.href"
                                    class="text-sm font-semibold tracking-wide text-slate-700 underline-offset-4 transition hover:text-primary-700 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2 sm:text-[0.9375rem]"
                                >
                                    {{ link.label }}
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="flex min-h-full min-w-0 flex-1 flex-col px-1 py-1 sm:px-2">
                        <h3 class="text-xs font-bold uppercase tracking-[0.2em] text-slate-500">
                            {{ block.company }}
                        </h3>
                        <ul class="mt-4 flex flex-col gap-3">
                            <li v-for="link in block.links_company" :key="link.label">
                                <a
                                    :href="link.href"
                                    class="text-sm font-semibold tracking-wide text-slate-700 underline-offset-4 transition hover:text-primary-700 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2 sm:text-[0.9375rem]"
                                >
                                    {{ link.label }}
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="flex min-h-full min-w-0 flex-1 flex-col px-1 py-1 sm:px-2">
                        <h3 class="text-xs font-bold uppercase tracking-[0.2em] text-slate-500">
                            {{ block.legal }}
                        </h3>
                        <ul class="mt-4 flex flex-col gap-3">
                            <li v-for="link in block.links_legal" :key="link.label">
                                <a
                                    :href="link.href"
                                    class="text-sm font-semibold tracking-wide text-slate-700 underline-offset-4 transition hover:text-primary-700 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2 sm:text-[0.9375rem]"
                                >
                                    {{ link.label }}
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <div
                class="mt-10 flex flex-col gap-6 border-t border-slate-200 pt-10 sm:mt-12 sm:flex-row sm:items-center sm:justify-between lg:mt-14"
            >
                <div class="flex flex-wrap items-center gap-3 sm:gap-4">
                    <a
                        v-for="(label, key) in block.social_labels"
                        :key="key"
                        href="#"
                        class="rounded-xl px-4 py-2.5 text-sm font-semibold tracking-wide text-slate-600 transition hover:bg-slate-50 hover:text-primary-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2"
                    >
                        {{ label }}
                    </a>
                </div>
                <p class="text-sm tracking-wide text-slate-500">
                    © {{ year }} HustleSafe. {{ block.rights }}
                </p>
            </div>

            <p class="mt-10 text-center text-xs tracking-wide text-slate-500 sm:text-sm">
                Built for Nigerian freelancers and clients · Escrow-first workflows
            </p>
        </div>
    </footer>
</template>

<script setup>
import { computed } from 'vue';

defineProps({
    block: {
        type: Object,
        required: true,
    },
});

const year = computed(() => new Date().getFullYear());
</script>
