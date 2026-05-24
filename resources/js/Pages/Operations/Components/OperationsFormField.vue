<template>
    <label class="block w-full">
        <span v-if="label" class="mb-1.5 block text-xs font-black uppercase tracking-wide text-slate-500">{{ label }}</span>
        <span v-if="hint" class="mb-2 block text-xs font-semibold text-slate-500">{{ hint }}</span>
        <textarea
            v-if="multiline"
            :value="modelValue"
            :rows="rows"
            :placeholder="placeholder"
            :disabled="disabled"
            class="block w-full resize-y rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium leading-relaxed text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-primary-400 focus:ring-2 focus:ring-primary-100 disabled:cursor-not-allowed disabled:bg-slate-50"
            @input="$emit('update:modelValue', $event.target.value)"
        />
        <input
            v-else
            :type="type"
            :value="modelValue"
            :placeholder="placeholder"
            :disabled="disabled"
            class="block w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-primary-400 focus:ring-2 focus:ring-primary-100 disabled:cursor-not-allowed disabled:bg-slate-50"
            @input="$emit('update:modelValue', $event.target.value)"
        />
    </label>
</template>

<script setup>
defineProps({
    modelValue: { type: String, default: '' },
    label: { type: String, default: '' },
    hint: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    type: { type: String, default: 'text' },
    multiline: { type: Boolean, default: false },
    rows: { type: [Number, String], default: 4 },
    disabled: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);
</script>
