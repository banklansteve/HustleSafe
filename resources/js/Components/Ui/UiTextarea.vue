<template>
    <div class="space-y-1.5">
        <label
            v-if="label"
            :for="id"
            class="block text-[11px] font-black uppercase tracking-[0.16em]"
            :class="computedInvalid ? 'text-rose-700' : 'text-slate-600'"
        >
            {{ label }}
            <span v-if="required" class="text-rose-600">*</span>
        </label>

        <div class="relative">
            <textarea
                v-bind="attrs"
                :id="id"
                ref="root"
                :name="name"
                :value="modelValue"
                :disabled="disabled"
                :placeholder="placeholder"
                :rows="minRows"
                :required="required"
                :maxlength="maxlength || undefined"
                :aria-invalid="computedInvalid ? 'true' : 'false'"
                class="block w-full resize-none overflow-y-auto rounded-2xl border bg-slate-50/80 px-4 py-3 text-sm font-semibold leading-relaxed text-slate-900 shadow-sm ring-1 transition placeholder:text-slate-400 focus:outline-none focus:ring-2 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500"
                :class="[
                    computedInvalid
                        ? 'border-rose-300 ring-rose-100 focus:border-rose-400 focus:ring-rose-500/20'
                        : 'border-slate-200/95 ring-slate-100/80 focus:border-primary-400 focus:ring-primary-500/25',
                    counterVisible ? 'pb-8' : '',
                ]"
                @blur="emit('blur', $event)"
                @input="onInput"
            />
            <p
                v-if="counterVisible"
                class="pointer-events-none absolute bottom-2 right-3 rounded-full bg-white/85 px-2 py-0.5 text-[10px] font-black tabular-nums"
                :class="overLimit ? 'text-rose-700' : 'text-slate-500'"
            >
                {{ valueLength }} / {{ maxlength }}
            </p>
        </div>

        <p v-if="error" class="text-xs font-semibold text-rose-700">{{ error }}</p>
        <p v-else-if="hint" class="text-xs font-semibold text-slate-500">{{ hint }}</p>
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, useAttrs, watch } from 'vue';

defineOptions({
    inheritAttrs: false,
});

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    id: {
        type: String,
        default: undefined,
    },
    name: {
        type: String,
        default: undefined,
    },
    placeholder: {
        type: String,
        default: '',
    },
    label: {
        type: String,
        default: '',
    },
    hint: {
        type: String,
        default: '',
    },
    error: {
        type: String,
        default: '',
    },
    maxlength: {
        type: [Number, String],
        default: null,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    invalid: {
        type: Boolean,
        default: false,
    },
    required: {
        type: Boolean,
        default: false,
    },
    minRows: {
        type: Number,
        default: 2,
    },
    maxRows: {
        type: Number,
        default: 18,
    },
});

const emit = defineEmits(['update:modelValue', 'blur']);
const attrs = useAttrs();

const root = ref(null);
const valueLength = computed(() => String(props.modelValue || '').length);
const counterVisible = computed(() => props.maxlength !== null && props.maxlength !== undefined && props.maxlength !== '');
const overLimit = computed(() => counterVisible.value && valueLength.value > Number(props.maxlength));
const computedInvalid = computed(() => props.invalid || Boolean(props.error) || overLimit.value);

function lineHeightPx() {
    const el = root.value;
    if (!el) {
        return 22;
    }
    const lh = window.getComputedStyle(el).lineHeight;

    if (lh === 'normal') {
        return 22;
    }
    const n = parseFloat(lh);

    return Number.isFinite(n) ? n : 22;
}

function verticalPaddingPx() {
    const el = root.value;
    if (!el) {
        return 24;
    }
    const cs = window.getComputedStyle(el);
    const pt = parseFloat(cs.paddingTop) || 0;
    const pb = parseFloat(cs.paddingBottom) || 0;

    return pt + pb;
}

function autoGrow() {
    const el = root.value;
    if (!el) {
        return;
    }
    const lh = lineHeightPx();
    const pad = verticalPaddingPx();
    const minH = Math.ceil(props.minRows * lh + pad);
    const maxH = Math.ceil(props.maxRows * lh + pad);

    el.style.height = '0px';
    const next = Math.min(maxH, Math.max(minH, el.scrollHeight));
    el.style.height = `${next}px`;
}

function onInput(e) {
    emit('update:modelValue', e.target.value);
    nextTick(autoGrow);
}

watch(
    () => props.modelValue,
    () => {
        nextTick(autoGrow);
    },
);

watch(
    () => [props.minRows, props.maxRows],
    () => {
        nextTick(autoGrow);
    },
);

onMounted(() => {
    nextTick(autoGrow);
});
</script>
