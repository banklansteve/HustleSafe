<template>
    <section
        v-if="clientPreferences.length"
        class="space-y-5 rounded-2xl border border-violet-100 bg-violet-50/40 p-5 ring-1 ring-violet-100"
    >
        <div>
            <h3 class="font-display text-lg font-bold text-slate-900">
                Respond to the client&apos;s preferences
            </h3>
            <p class="mt-1 text-sm font-semibold text-slate-600">
                Each item below is a question the client answered when posting the quest. Review what they chose, then say whether you can meet it, propose an alternative, or ask for clarification.
            </p>
        </div>

        <div
            v-for="pref in clientPreferences.filter((p) => p.is_specified)"
            :key="pref.key"
            class="rounded-2xl border border-white bg-white p-4 shadow-sm ring-1 ring-slate-100"
        >
            <p class="text-sm font-black text-slate-900">{{ pref.label }}</p>
            <p v-if="pref.hint" class="mt-1 text-xs font-semibold leading-relaxed text-slate-500">
                {{ pref.hint }}
            </p>
            <p class="mt-2 rounded-xl bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700 ring-1 ring-slate-100">
                <span class="font-black text-slate-900">Client specified:</span>
                {{ pref.display_value }}
            </p>

            <div class="mt-3 space-y-2">
                <label
                    v-for="opt in responseOptions"
                    :key="opt.value"
                    class="flex cursor-pointer items-start gap-2 rounded-xl border border-slate-100 px-3 py-2 text-sm font-semibold"
                >
                    <input
                        v-model="localResponses[pref.key].response_type"
                        type="radio"
                        :value="opt.value"
                        class="mt-1"
                    />
                    {{ opt.label }}
                </label>
            </div>

            <textarea
                v-if="needsText(localResponses[pref.key]?.response_type)"
                v-model="localResponses[pref.key].response_text"
                rows="3"
                class="mt-3 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold"
                :placeholder="textPlaceholder(localResponses[pref.key]?.response_type)"
                :maxlength="maxChars(localResponses[pref.key]?.response_type)"
            />
            <p
                v-if="needsText(localResponses[pref.key]?.response_type)"
                class="mt-1 text-xs font-semibold text-slate-500"
            >
                {{ remaining(localResponses[pref.key]) }} characters remaining
            </p>
        </div>
    </section>
</template>

<script setup>
import { reactive, watch } from 'vue';

const props = defineProps({
    clientPreferences: { type: Array, default: () => [] },
    modelValue: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:modelValue']);

const responseOptions = [
    { value: 'accept', label: 'Accept — I will meet this preference' },
    { value: 'propose_alternative', label: 'Propose alternative' },
    { value: 'clarify', label: 'Clarify — I need more information' },
    { value: 'custom', label: 'Custom response' },
];

const localResponses = reactive({ ...props.modelValue });

watch(
    () => props.modelValue,
    (v) => {
        Object.keys(localResponses).forEach((k) => delete localResponses[k]);
        Object.assign(localResponses, v || {});
    },
    { deep: true },
);

watch(
    () => props.clientPreferences,
    (prefs) => {
        for (const pref of prefs.filter((p) => p.is_specified)) {
            if (!localResponses[pref.key]) {
                localResponses[pref.key] = { response_type: 'accept', response_text: '' };
            }
        }
    },
    { immediate: true },
);

watch(
    localResponses,
    () => emit('update:modelValue', { ...localResponses }),
    { deep: true },
);

function needsText(type) {
    return ['propose_alternative', 'clarify', 'custom'].includes(type);
}

function maxChars(type) {
    return type === 'accept' ? 300 : 500;
}

function textPlaceholder(type) {
    if (type === 'propose_alternative') return 'Explain what you recommend instead and why.';
    if (type === 'clarify') return 'Ask your specific question.';
    if (type === 'custom') return 'Your full response to this preference.';

    return 'Optional details confirming how you will meet this.';
}

function remaining(row) {
    const type = row?.response_type;
    const max = maxChars(type);
    const len = String(row?.response_text || '').length;

    return Math.max(0, max - len);
}
</script>
