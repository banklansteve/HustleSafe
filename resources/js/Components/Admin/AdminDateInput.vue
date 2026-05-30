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
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';
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

const { shell } = useInjectedAdminTheme();

const resolvedButtonClass = computed(() => {
    const base = props.buttonClass || 'w-full text-left';

    return `${base} ${shell.input}`.trim();
});

function onUpdate(value) {
    emit('update:modelValue', value);
    emit('change', value);
}
</script>
