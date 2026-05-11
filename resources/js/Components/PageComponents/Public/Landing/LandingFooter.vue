<template>
    <footer class="border-t border-slate-200 bg-white" aria-labelledby="footer-heading">
        <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
            <div class="grid gap-10 lg:grid-cols-12 lg:gap-12">
                <div class="lg:col-span-4">
                    <p id="footer-heading" class="font-display text-2xl font-extrabold text-slate-900">
                        HustleSafe
                    </p>
                    <p class="mt-4 max-w-md text-sm leading-relaxed text-slate-600 sm:text-base">
                        {{ block.tagline }}
                    </p>
                </div>

                <div class="grid flex-1 gap-10 sm:grid-cols-3 lg:col-span-8 lg:gap-8">
                    <div>
                        <h3 class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">
                            {{ block.product }}
                        </h3>
                        <ul class="mt-4 space-y-3">
                            <li v-for="link in block.links_product" :key="link.label">
                                <a
                                    :href="link.href"
                                    class="text-sm font-semibold text-slate-700 underline-offset-4 hover:text-primary-700 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2"
                                >
                                    {{ link.label }}
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">
                            {{ block.company }}
                        </h3>
                        <ul class="mt-4 space-y-3">
                            <li v-for="link in block.links_company" :key="link.label">
                                <a
                                    :href="link.href"
                                    class="text-sm font-semibold text-slate-700 underline-offset-4 hover:text-primary-700 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2"
                                >
                                    {{ link.label }}
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">
                            {{ block.legal }}
                        </h3>
                        <ul class="mt-4 space-y-3">
                            <li v-for="link in block.links_legal" :key="link.label">
                                <a
                                    :href="link.href"
                                    class="text-sm font-semibold text-slate-700 underline-offset-4 hover:text-primary-700 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2"
                                >
                                    {{ link.label }}
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="mt-12 flex flex-col gap-6 border-t border-slate-200 pt-10 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-wrap items-center gap-4">
                    <a
                        v-for="(label, key) in block.social_labels"
                        :key="key"
                        href="#"
                        class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 ring-1 ring-slate-200 transition hover:bg-slate-50 hover:text-primary-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2"
                    >
                        {{ label }}
                    </a>
                </div>
                <p class="text-sm text-slate-500">
                    © {{ year }} HustleSafe. {{ block.rights }}
                </p>
            </div>

            <div class="mt-8 rounded-2xl bg-slate-50 px-4 py-4 text-center text-xs text-slate-500 ring-1 ring-slate-100 sm:text-sm">
                Built for Nigerian freelancers and clients · Pricing examples shown in ₦ (Naira)
            </div>
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
