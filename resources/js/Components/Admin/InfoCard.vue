<template>
    <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-slate-950/60">
        <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">{{ label }}</p>
        <p class="mt-1 break-words text-sm font-black text-slate-900 dark:text-white">{{ value }}</p>
    </div>
</template>

<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], default: '—' },
});
</script>
