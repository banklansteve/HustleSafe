<template>
    <p class="rounded-3xl border border-dashed border-slate-200 bg-white p-6 text-center text-sm font-bold text-slate-500">
        {{ message }}
    </p>
</template>

<script setup>
defineProps({
    message: { type: String, default: 'Nothing in this queue.' },
});
</script>
