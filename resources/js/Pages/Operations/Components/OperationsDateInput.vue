<template>
    <PremiumDatePicker
        :id="id"
        :input-id="inputId"
        :model-value="modelValue"
        :placeholder="placeholder"
        :min="min"
        :max="max"
        :disabled="disabled"
        :error="error"
        :wrapper-class="wrapperClass"
        :button-class="resolvedButtonClass"
        @update:model-value="onUpdate"
    />
</template>

<script setup>
import PremiumDatePicker from '@/Components/Ui/PremiumDatePicker.vue';
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    id: { type: String, default: '' },
    inputId: { type: String, default: '' },
    placeholder: { type: String, default: 'DD/MM/YYYY' },
    min: { type: String, default: '' },
    max: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    error: { type: String, default: '' },
    wrapperClass: { type: String, default: '' },
    buttonClass: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue', 'change']);

const resolvedButtonClass = computed(() => {
    const base = props.buttonClass || 'w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-900 shadow-none';

    return `${base} hover:border-primary-300 focus:border-primary-500`.trim();
});

function onUpdate(value) {
    emit('update:modelValue', value);
    emit('change', value);
}
</script>
