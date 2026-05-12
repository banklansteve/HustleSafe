<template>
    <Link
        :href="href"
        class="group relative flex flex-col overflow-hidden rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm shadow-slate-200/50 ring-1 ring-slate-100/90 transition hover:border-primary-200/80 hover:shadow-md hover:shadow-primary-900/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
    >
        <div
            class="pointer-events-none absolute -right-8 -top-8 h-24 w-24 rounded-full bg-gradient-to-br from-primary-100/60 to-teal-100/40 blur-2xl transition group-hover:from-primary-100/80 group-hover:to-teal-100/60"
            aria-hidden="true"
        />
        <div class="relative flex items-start gap-3">
            <span
                class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-primary-600/10 to-teal-600/10 text-primary-700 ring-1 ring-primary-100/80"
            >
                <PanelIcon :name="icon" size="md" />
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                    {{ label }}
                </p>
                <p class="mt-1.5 font-display text-2xl font-black tracking-tight text-slate-900 sm:text-[1.65rem]">
                    {{ value }}
                </p>
                <p v-if="hint" class="mt-2 text-sm font-medium leading-relaxed text-slate-600">
                    {{ hint }}
                </p>
                <p class="mt-3 inline-flex items-center gap-1 text-xs font-bold text-primary-700">
                    View details
                    <span aria-hidden="true" class="transition group-hover:translate-x-0.5">→</span>
                </p>
            </div>
        </div>
    </Link>
</template>

<script setup>
import PanelIcon from '@/Components/Home/PanelIcon.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    label: {
        type: String,
        required: true,
    },
    value: {
        type: String,
        required: true,
    },
    hint: {
        type: String,
        default: '',
    },
    href: {
        type: String,
        required: true,
    },
    icon: {
        type: String,
        default: 'sparkles',
    },
});
</script>
