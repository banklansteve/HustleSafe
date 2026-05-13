<template>
    <div ref="rootRef" data-ui-select-root :class="['relative', attrs.class]">
        <button
            :id="id"
            ref="triggerRef"
            type="button"
            class="group flex w-full items-center justify-between gap-3 rounded-2xl border bg-white text-left shadow-sm ring-1 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/35 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:opacity-70"
            :class="triggerClasses"
            :aria-expanded="open"
            :aria-haspopup="true"
            :aria-invalid="invalid ? 'true' : 'false'"
            :disabled="disabled"
            @click.stop="toggle"
            @keydown.down.prevent="onTriggerArrow"
            @keydown.up.prevent="onTriggerArrow"
        >
            <span class="min-w-0 flex-1 truncate text-[15px] font-semibold tracking-tight text-slate-900">{{ displayLabel }}</span>
            <ChevronDownIcon
                class="h-5 w-5 shrink-0 text-slate-400 transition duration-200 group-hover:text-primary-600"
                :class="open ? 'rotate-180 text-primary-600' : ''"
                aria-hidden="true"
            />
        </button>

        <Teleport to="body">
            <Transition name="ui-sel-dim">
                <div
                    v-if="open"
                    class="fixed inset-0 z-[280] bg-slate-900/20 backdrop-blur-[1px]"
                    aria-hidden="true"
                    @click="open = false"
                />
            </Transition>
            <Transition name="ui-sel-panel">
                <ul
                    v-if="open"
                    ref="panelRef"
                    tabindex="-1"
                    class="fixed z-[290] overflow-y-auto overflow-x-hidden rounded-2xl border border-slate-200/90 bg-white py-2 shadow-2xl shadow-primary-900/15 ring-1 ring-primary-100/70 outline-none"
                    role="listbox"
                    :style="panelStyle"
                    @keydown.esc.prevent="open = false"
                    @keydown.down.prevent="moveActive(1)"
                    @keydown.up.prevent="moveActive(-1)"
                    @keydown.enter.prevent="commitActive"
                    @keydown.home.prevent="activeIndex = 0"
                    @keydown.end.prevent="activeIndex = Math.max(0, options.length - 1)"
                >
                    <li v-for="(opt, idx) in options" :key="String(opt.value)">
                        <button
                            type="button"
                            role="option"
                            class="flex w-full items-start gap-3 px-4 py-3.5 text-left text-[15px] font-semibold leading-snug tracking-tight transition"
                            :class="optionClasses(opt, idx)"
                            :aria-selected="isSelected(opt)"
                            @click="select(opt.value)"
                            @mouseenter="activeIndex = idx"
                        >
                            <span
                                class="mt-1.5 inline-block h-2 w-2 shrink-0 rounded-full ring-2 ring-offset-1 ring-offset-white transition"
                                :class="isSelected(opt) ? 'bg-primary-600 ring-primary-200' : 'bg-slate-200 ring-transparent'"
                                aria-hidden="true"
                            />
                            <span class="min-w-0 flex-1 text-slate-900">{{ opt.label }}</span>
                        </button>
                    </li>
                </ul>
            </Transition>
        </Teleport>
    </div>
</template>

<script setup>
import { ChevronDownIcon } from '@heroicons/vue/20/solid';
import { computed, nextTick, onUnmounted, ref, useAttrs, watch } from 'vue';

defineOptions({
    inheritAttrs: false,
});

const attrs = useAttrs();

const props = defineProps({
    modelValue: {
        type: [String, Number, Boolean, null],
        default: null,
    },
    options: {
        type: Array,
        required: true,
    },
    placeholder: {
        type: String,
        default: 'Select…',
    },
    invalid: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    id: {
        type: String,
        default: undefined,
    },
});

const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const rootRef = ref(null);
const triggerRef = ref(null);
const panelRef = ref(null);
const panelStyle = ref({});
const activeIndex = ref(-1);

function sameValue(a, b) {
    return String(a) === String(b);
}

const displayLabel = computed(() => {
    const m = props.options.find((o) => sameValue(o.value, props.modelValue));

    return m?.label ?? props.placeholder;
});

const triggerClasses = computed(() =>
    props.invalid
        ? 'border-rose-300 py-3.5 pl-4 pr-3 ring-rose-100 hover:border-rose-400'
        : open.value
          ? 'border-primary-300 py-3.5 pl-4 pr-3 ring-primary-100'
          : 'border-slate-200/95 py-3.5 pl-4 pr-3 ring-slate-100/90 hover:border-primary-200 hover:ring-primary-100/80',
);

function isSelected(opt) {
    return sameValue(opt.value, props.modelValue);
}

function optionClasses(opt, idx) {
    const selected = isSelected(opt);
    const active = idx === activeIndex.value;

    if (selected) {
        return 'bg-gradient-to-r from-primary-600 to-teal-600 text-white shadow-inner shadow-primary-900/10';
    }
    if (active) {
        return 'bg-primary-50 text-primary-950 ring-1 ring-inset ring-primary-100/80';
    }

    return 'text-slate-800 hover:bg-primary-500/[0.08] hover:text-primary-950';
}

function updatePanelPosition() {
    const tr = triggerRef.value;
    if (!tr) {
        return;
    }
    const r = tr.getBoundingClientRect();
    const vw = window.innerWidth;
    const margin = 12;
    const gap = 10;
    const maxH = Math.min(400, Math.max(160, window.innerHeight - r.bottom - margin - gap));
    let left = r.left;
    const width = Math.min(Math.max(r.width, 220), vw - margin * 2);
    left = Math.min(Math.max(margin, left), vw - width - margin);

    panelStyle.value = {
        top: `${r.bottom + gap}px`,
        left: `${left}px`,
        width: `${width}px`,
        maxHeight: `${maxH}px`,
    };
}

function syncActiveToSelection() {
    const i = props.options.findIndex((o) => isSelected(o));
    activeIndex.value = i >= 0 ? i : 0;
}

function toggle() {
    if (props.disabled) {
        return;
    }
    open.value = !open.value;
    if (open.value) {
        syncActiveToSelection();
        nextTick(() => {
            updatePanelPosition();
            panelRef.value?.focus?.();
        });
    }
}

function select(v) {
    emit('update:modelValue', v);
    open.value = false;
}

function onTriggerArrow() {
    if (!open.value) {
        open.value = true;
        syncActiveToSelection();
        nextTick(() => {
            updatePanelPosition();
            panelRef.value?.focus?.();
        });
    }
}

function moveActive(delta) {
    if (!props.options.length) {
        return;
    }
    const len = props.options.length;
    let i = activeIndex.value;
    if (i < 0) {
        i = delta > 0 ? 0 : len - 1;
    } else {
        i = (i + delta + len) % len;
    }
    activeIndex.value = i;
    nextTick(() => {
        const el = panelRef.value?.querySelector?.(`li:nth-child(${i + 1}) button`);
        el?.scrollIntoView?.({ block: 'nearest' });
    });
}

function commitActive() {
    const opt = props.options[activeIndex.value];
    if (opt) {
        select(opt.value);
    }
}

function onScrollOrResize() {
    if (open.value) {
        updatePanelPosition();
    }
}

watch(open, (v) => {
    if (v) {
        window.addEventListener('scroll', onScrollOrResize, true);
        window.addEventListener('resize', onScrollOrResize);
    } else {
        window.removeEventListener('scroll', onScrollOrResize, true);
        window.removeEventListener('resize', onScrollOrResize);
        activeIndex.value = -1;
    }
});

watch(
    () => props.options,
    () => {
        if (open.value) {
            nextTick(updatePanelPosition);
        }
    },
    { deep: true },
);

onUnmounted(() => {
    window.removeEventListener('scroll', onScrollOrResize, true);
    window.removeEventListener('resize', onScrollOrResize);
});
</script>

<style scoped>
.ui-sel-dim-enter-active,
.ui-sel-dim-leave-active {
    transition: opacity 0.18s ease;
}
.ui-sel-dim-enter-from,
.ui-sel-dim-leave-to {
    opacity: 0;
}

.ui-sel-panel-enter-active,
.ui-sel-panel-leave-active {
    transition: opacity 0.16s ease, transform 0.16s ease;
}
.ui-sel-panel-enter-from,
.ui-sel-panel-leave-to {
    opacity: 0;
    transform: translateY(-6px);
}
</style>
