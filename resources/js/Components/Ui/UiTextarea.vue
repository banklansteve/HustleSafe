<template>
    <textarea
        :id="id"
        ref="root"
        :name="name"
        :value="modelValue"
        :disabled="disabled"
        :placeholder="placeholder"
        :rows="minRows"
        :required="required"
        :aria-invalid="invalid ? 'true' : 'false'"
        class="block w-full resize-none overflow-y-auto rounded-xl border border-slate-200/95 bg-white px-3.5 py-3 text-sm font-medium leading-relaxed text-slate-900 shadow-sm ring-1 ring-slate-100/80 transition placeholder:text-slate-400 focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/25 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-500"
        :class="invalid ? 'border-rose-300 ring-rose-100 focus:border-rose-400 focus:ring-rose-500/20' : ''"
        @input="onInput"
    />
</template>

<script setup>
import { nextTick, onMounted, ref, watch } from 'vue';

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

const emit = defineEmits(['update:modelValue']);

const root = ref(null);

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
