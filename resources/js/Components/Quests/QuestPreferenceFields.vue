<template>
    <div v-if="profile?.show_preferences" class="space-y-5 rounded-2xl border border-primary-100 bg-primary-50/40 p-5 ring-1 ring-primary-100">
        <div>
            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-primary-800">
                Your preferences
            </p>
            <p class="mt-1 text-sm font-semibold text-slate-700">
                {{ profile.profile_hint }}
            </p>
            <p class="mt-1 text-xs font-medium text-slate-500">
                All optional — freelancers can propose alternatives if needed.
            </p>
        </div>

        <div v-for="(field, key) in visibleFields" :key="key" class="space-y-2">
            <div class="flex items-center gap-1">
                <p class="text-sm font-bold text-slate-900">
                    {{ field.label }}
                </p>
            </div>
            <p v-if="field.hint" class="text-xs font-semibold leading-relaxed text-slate-500">
                {{ field.hint }}
            </p>

            <input
                v-if="field.type === 'text'"
                v-model="localValues[key]"
                type="text"
                :maxlength="field.max"
                :placeholder="field.placeholder || ''"
                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 shadow-sm"
            />

            <textarea
                v-else-if="field.type === 'textarea'"
                v-model="localValues[key]"
                rows="3"
                :maxlength="field.max"
                :placeholder="field.placeholder || ''"
                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 shadow-sm"
            />

            <input
                v-else-if="field.type === 'number'"
                v-model.number="localValues[key]"
                type="number"
                :min="field.min"
                :max="field.max"
                :placeholder="field.placeholder || ''"
                class="w-full max-w-xs rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 shadow-sm"
            />

            <div v-else-if="field.type === 'radio'" class="space-y-2">
                <label
                    v-for="(label, optionKey) in field.options"
                    :key="optionKey"
                    class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-100 bg-white px-3 py-2.5 text-sm font-semibold text-slate-800"
                >
                    <input v-model="localValues[key]" type="radio" :value="optionKey" class="mt-1" />
                    <span>{{ label }}</span>
                </label>
            </div>

            <div v-else-if="field.type === 'checkbox_group'" class="flex flex-wrap gap-2">
                <label
                    v-for="(label, optionKey) in field.options"
                    :key="optionKey"
                    class="inline-flex cursor-pointer items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-800"
                >
                    <input v-model="localValues[key]" type="checkbox" :value="optionKey" class="rounded" />
                    {{ label }}
                </label>
            </div>

            <p v-if="field.max && (field.type === 'text' || field.type === 'textarea')" class="text-xs font-semibold text-slate-500">
                {{ remainingChars(key, field.max) }} characters remaining
            </p>
        </div>
    </div>

    <p
        v-else-if="profile?.catch_all_message"
        class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-600"
    >
        {{ profile.catch_all_message }}
    </p>
</template>

<script setup>
import { computed, nextTick, reactive, watch } from 'vue';

const props = defineProps({
    profile: { type: Object, default: () => ({}) },
    modelValue: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:modelValue']);

const localValues = reactive({});
let syncingFromParent = false;

const normalizedFields = computed(() => {
    const fields = props.profile?.fields;
    if (!fields || typeof fields !== 'object' || Array.isArray(fields)) {
        return {};
    }

    return fields;
});

const visibleFields = computed(() => {
    const fields = normalizedFields.value;
    const visible = {};

    for (const [key, field] of Object.entries(fields)) {
        const when = field.show_when;
        if (when?.field && when?.value !== undefined) {
            if (localValues[when.field] !== when.value) {
                continue;
            }
        }
        visible[key] = field;
    }

    return visible;
});

function fieldIsVisible(field) {
    const when = field?.show_when;
    if (!when?.field) {
        return true;
    }

    return localValues[when.field] === when.value;
}

function defaultForField(field) {
    if (field.type === 'checkbox_group') {
        return [];
    }

    return field.default ?? '';
}

function normalizeValueForField(field, value) {
    if (field.type === 'checkbox_group') {
        if (Array.isArray(value)) {
            return [...value];
        }
        if (value == null || value === '') {
            return [];
        }

        return [value];
    }

    if (field.type === 'number') {
        if (value === '' || value == null) {
            return '';
        }

        const n = Number(value);

        return Number.isFinite(n) ? n : '';
    }

    return value ?? defaultForField(field);
}

function applyModelValue(source = {}) {
    syncingFromParent = true;

    for (const key of Object.keys(localValues)) {
        delete localValues[key];
    }

    const fields = normalizedFields.value;
    for (const [key, field] of Object.entries(fields)) {
        localValues[key] = normalizeValueForField(field, source[key]);
    }

    for (const [key, value] of Object.entries(source || {})) {
        if (localValues[key] === undefined) {
            const field = fields[key];
            localValues[key] = field ? normalizeValueForField(field, value) : value;
        }
    }

    nextTick(() => {
        syncingFromParent = false;
    });
}

function snapshotValues() {
    return { ...localValues };
}

function valuesEqual(a, b) {
    return JSON.stringify(a ?? {}) === JSON.stringify(b ?? {});
}

watch(
    () => props.modelValue,
    (value) => {
        const next = value && typeof value === 'object' ? value : {};
        if (valuesEqual(next, snapshotValues())) {
            return;
        }
        applyModelValue(next);
    },
    { deep: true, immediate: true },
);

watch(
    normalizedFields,
    (fields) => {
        for (const [key, field] of Object.entries(fields)) {
            if (localValues[key] === undefined) {
                localValues[key] = defaultForField(field);
            } else if (field.type === 'checkbox_group' && !Array.isArray(localValues[key])) {
                localValues[key] = normalizeValueForField(field, localValues[key]);
            }
        }
    },
    { immediate: true },
);

watch(
    () => localValues.session_frequency,
    (freq) => {
        if (freq !== 'weekly' && localValues.sessions_per_week !== undefined) {
            localValues.sessions_per_week = '';
        }
        if (freq !== 'monthly' && localValues.sessions_per_month !== undefined) {
            localValues.sessions_per_month = '';
        }
    },
);

watch(
    localValues,
    () => {
        if (syncingFromParent) {
            return;
        }

        const next = snapshotValues();
        if (valuesEqual(next, props.modelValue)) {
            return;
        }

        emit('update:modelValue', next);
    },
    { deep: true },
);

function remainingChars(key, max) {
    const len = String(localValues[key] || '').length;

    return Math.max(0, max - len);
}
</script>
