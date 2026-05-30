<template>
    <label class="block">
        <span class="text-[10px] font-black uppercase tracking-wider" :class="shell.cardMuted">
            {{ schema?.label || field.replace(/_/g, ' ') }}
        </span>

        <input
            v-if="inputType === 'checkbox'"
            :id="fieldId"
            v-model="model"
            type="checkbox"
            class="mt-2 h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500"
        />

        <textarea
            v-else-if="inputType === 'textarea'"
            :id="fieldId"
            v-model="model"
            rows="4"
            class="mt-1 w-full rounded-xl border px-3 py-2 text-sm font-semibold"
            :class="shell.input"
        />

        <AdminDateInput
            v-else-if="inputType === 'date'"
            :id="fieldId"
            v-model="model"
            wrapper-class="mt-1"
            placeholder="DD/MM/YYYY"
        />

        <select
            v-else-if="inputType === 'select'"
            :id="fieldId"
            v-model="model"
            class="mt-1 w-full rounded-xl border px-3 py-2 text-sm font-semibold"
            :class="shell.input"
        >
            <option value="">Choose an option</option>
            <option v-for="option in schemaOptions" :key="option.value" :value="option.value">
                {{ option.label }}
            </option>
        </select>

        <div v-else-if="inputType === 'money'" class="relative mt-1">
            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm font-black" :class="shell.cardMuted">
                ₦
            </span>
            <input
                :id="fieldId"
                v-model="model"
                type="number"
                min="0"
                step="0.01"
                class="w-full rounded-xl border py-2 pl-8 pr-3 text-sm font-semibold"
                :class="shell.input"
            />
        </div>

        <div v-else-if="inputType === 'key_value'" class="mt-1 space-y-2">
            <div
                v-for="(row, index) in keyValueRows"
                :key="index"
                class="grid gap-2 sm:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)_auto]"
            >
                <input
                    v-model="row.key"
                    type="text"
                    placeholder="Label"
                    class="rounded-xl border px-3 py-2 text-sm font-semibold"
                    :class="shell.input"
                />
                <input
                    v-model="row.value"
                    type="text"
                    placeholder="Value"
                    class="rounded-xl border px-3 py-2 text-sm font-semibold"
                    :class="shell.input"
                />
                <button type="button" class="rounded-xl px-3 py-2 text-xs font-bold" :class="shell.btnGhost" @click="removeKeyValueRow(index)">
                    Remove
                </button>
            </div>
            <button type="button" class="rounded-xl px-3 py-2 text-xs font-bold" :class="shell.btnGhost" @click="addKeyValueRow">
                Add item
            </button>
        </div>

        <input
            v-else
            :id="fieldId"
            v-model="model"
            :type="inputType"
            class="mt-1 w-full rounded-xl border px-3 py-2 text-sm font-semibold"
            :class="shell.input"
        />
    </label>
</template>

<script setup>
import AdminDateInput from '@/Components/Admin/AdminDateInput.vue';
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';
import { computed } from 'vue';

const props = defineProps({
    field: { type: String, required: true },
    schema: { type: Object, default: null },
});

const model = defineModel({ type: [String, Number, Boolean, Array, Object], default: '' });

const { shell } = useInjectedAdminTheme();

const fieldId = computed(() => `mgmt-${props.field}`);
const schemaOptions = computed(() => props.schema?.options ?? []);
const keyValueRows = computed({
    get() {
        return Array.isArray(model.value) ? model.value : [];
    },
    set(value) {
        model.value = value;
    },
});

const inputType = computed(() => {
    const type = props.schema?.type ?? 'text';
    if (type === 'boolean') {
        return 'checkbox';
    }
    if (type === 'relation' || type === 'select') {
        return 'select';
    }
    if (type === 'money_minor') {
        return 'money';
    }
    if (type === 'key_value') {
        return 'key_value';
    }
    if (type === 'integer') {
        return 'number';
    }
    if (type === 'email') {
        return 'email';
    }
    if (type === 'textarea' || type === 'json') {
        return type === 'json' ? 'textarea' : 'textarea';
    }
    if (type === 'date') {
        return 'date';
    }

    return 'text';
});

function addKeyValueRow() {
    keyValueRows.value = [...keyValueRows.value, { key: '', value: '' }];
}

function removeKeyValueRow(index) {
    keyValueRows.value = keyValueRows.value.filter((_, i) => i !== index);
}
</script>
