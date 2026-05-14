<script setup>
import { computed, onMounted, ref, useAttrs } from 'vue';

const model = defineModel({
    type: [String, Number],
    default: '',
});

const attrs = useAttrs();
const input = ref(null);

/** Native `<input>` must use a string value; parents often use `v-model.number` and pass numbers (e.g. fees). */
const inputStr = computed({
    get() {
        const v = model.value;
        if (v === null || v === undefined || v === '') {
            return '';
        }

        return String(v);
    },
    set(v) {
        model.value = v;
    },
});

onMounted(() => {
    if (input.value?.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value?.focus() });
</script>

<template>
    <input
        v-model="inputStr"
        ref="input"
        class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-indigo-600 dark:focus:ring-indigo-600"
        v-bind="attrs"
    />
</template>
