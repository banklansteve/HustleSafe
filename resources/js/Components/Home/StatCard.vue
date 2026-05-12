<template>
    <article
        class="relative overflow-hidden rounded-2xl border border-slate-100 bg-white p-6 shadow-sm shadow-slate-200/60 ring-1 ring-slate-100 transition hover:shadow-md hover:shadow-slate-200/80"
    >
        <div
            class="pointer-events-none absolute -right-10 -top-10 h-28 w-28 rounded-full bg-gradient-to-br from-primary-100/80 to-secondary-100/50 blur-2xl"
            aria-hidden="true"
        />
        <p class="relative text-sm font-semibold uppercase tracking-wide text-slate-500">
            {{ label }}
        </p>
        <p class="relative mt-3 font-display text-3xl font-black tracking-tight text-slate-900 sm:text-[2rem]">
            {{ value }}
        </p>
        <p v-if="hint" class="relative mt-3 text-sm font-medium leading-relaxed text-slate-600">
            {{ hint }}
        </p>
    </article>
</template>

<script setup>
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
});
</script>
