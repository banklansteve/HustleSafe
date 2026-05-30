<template>
    <div v-if="message" class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm font-semibold text-sky-900 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-100">
        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-sky-700 dark:text-sky-200">What to expect</p>
        <p class="mt-1 leading-6">{{ message }}</p>
    </div>
</template>

<script setup>
defineProps({
    message: { type: String, default: '' },
});
</script>
