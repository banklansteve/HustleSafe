<template>
    <form class="rounded-[1.75rem] border border-slate-100 bg-white p-4 shadow-sm ring-1 ring-slate-100" @submit.prevent="emit('submit')">
        <div class="flex flex-col gap-3 sm:flex-row">
            <input
                :value="modelValue"
                type="search"
                class="min-h-12 flex-1 rounded-2xl border border-slate-200 px-4 text-sm font-semibold text-slate-900 outline-none focus:border-primary-400 focus:ring-2 focus:ring-primary-100"
                :placeholder="placeholder"
                @input="emit('update:modelValue', $event.target.value)"
            />
            <button type="submit" class="rounded-2xl bg-primary-700 px-5 py-3 text-sm font-black text-white hover:bg-primary-800">
                Search
            </button>
        </div>
    </form>
</template>

<script setup>
defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'Search queue' },
});

const emit = defineEmits(['update:modelValue', 'submit']);
</script>
