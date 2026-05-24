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
            @keydown="onTriggerKeydown"
        >
            <span class="min-w-0 flex-1 truncate text-[15px] font-semibold tracking-tight text-slate-900">{{ displayLabel }}</span>
            <ChevronDownIcon
                class="h-5 w-5 shrink-0 text-slate-400 transition duration-200 group-hover:text-primary-600"
                :class="open ? 'rotate-180 text-primary-600' : ''"
                aria-hidden="true"
            />
        </button>

        <Teleport to="body">
            <div
                v-if="open"
                class="fixed inset-0 z-[10040] bg-slate-900/25 backdrop-blur-[1px]"
                aria-hidden="true"
                @click="open = false"
            />
            <ul
                v-if="open"
                ref="panelRef"
                tabindex="-1"
                class="fixed z-[10041] min-h-[3rem] overflow-y-auto overflow-x-hidden rounded-2xl border border-slate-200/90 bg-white py-2 shadow-2xl shadow-primary-900/20 ring-1 ring-primary-100/70 outline-none"
                role="listbox"
                :style="panelStyle"
                @mousedown.stop
                @click.stop
                @keydown="onPanelKeydown"
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
const typeAheadBuffer = ref('');
let typeAheadResetTimer = null;
let lastTypeAheadKeyAt = 0;

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

function getTriggerEl() {
    return triggerRef.value ?? rootRef.value?.querySelector?.('button') ?? null;
}

function updatePanelPosition() {
    const tr = getTriggerEl();
    if (!tr) {
        return false;
    }
    const r = tr.getBoundingClientRect();
    if (r.width === 0 && r.height === 0 && r.top === 0 && r.left === 0) {
        return false;
    }
    const vw = window.innerWidth;
    const vh = window.innerHeight;
    const margin = 12;
    const gap = 10;
    const width = Math.min(Math.max(r.width, 220), vw - margin * 2);
    const left = Math.min(Math.max(margin, r.left), vw - width - margin);

    const spaceBelow = vh - r.bottom - margin - gap;
    const spaceAbove = r.top - margin - gap;
    const preferBelow = spaceBelow >= 120 || spaceBelow >= spaceAbove;
    const maxH = Math.min(400, Math.max(120, preferBelow ? spaceBelow : spaceAbove));

    if (preferBelow) {
        panelStyle.value = {
            top: `${r.bottom + gap}px`,
            left: `${left}px`,
            width: `${width}px`,
            maxHeight: `${maxH}px`,
        };
    } else {
        panelStyle.value = {
            top: `${Math.max(margin, r.top - gap - maxH)}px`,
            left: `${left}px`,
            width: `${width}px`,
            maxHeight: `${maxH}px`,
        };
    }

    return true;
}

function schedulePanelPosition() {
    if (updatePanelPosition()) {
        return;
    }
    requestAnimationFrame(() => {
        if (!updatePanelPosition()) {
            requestAnimationFrame(updatePanelPosition);
        }
    });
}

function openPanel() {
    clearTypeAheadState();
    syncActiveToSelection();
    updatePanelPosition();
    open.value = true;
    nextTick(() => {
        schedulePanelPosition();
        panelRef.value?.focus?.();
    });
}

function syncActiveToSelection() {
    const i = props.options.findIndex((o) => isSelected(o));
    activeIndex.value = i >= 0 ? i : 0;
}

function clearTypeAheadState() {
    clearTimeout(typeAheadResetTimer);
    typeAheadResetTimer = null;
    typeAheadBuffer.value = '';
}

function scheduleTypeAheadReset() {
    clearTimeout(typeAheadResetTimer);
    typeAheadResetTimer = setTimeout(() => {
        typeAheadBuffer.value = '';
        typeAheadResetTimer = null;
    }, 850);
}

function scrollOptionIntoView(idx) {
    nextTick(() => {
        const el = panelRef.value?.querySelector?.(`li:nth-child(${idx + 1}) button`);
        el?.scrollIntoView?.({ block: 'nearest' });
    });
}

/** @returns {number} index or -1 */
function idxStartingWith(prefix, startFrom = 0) {
    const p = String(prefix).toLowerCase();
    const n = props.options.length;
    if (!n || !p) {
        return -1;
    }
    for (let step = 0; step < n; step += 1) {
        const i = (startFrom + step) % n;
        if (String(props.options[i].label).toLowerCase().startsWith(p)) {
            return i;
        }
    }

    return -1;
}

/** @returns {boolean} true if the event was handled */
function handleTypeAhead(e) {
    if (props.disabled || !props.options.length) {
        return false;
    }
    const k = e.key;
    if (k.length !== 1 || e.ctrlKey || e.metaKey || e.altKey) {
        return false;
    }

    const now = Date.now();
    const elapsed = now - lastTypeAheadKeyAt;
    lastTypeAheadKeyAt = now;

    if (elapsed > 850) {
        typeAheadBuffer.value = '';
    }

    const lower = k.toLowerCase();

    if (open.value && typeAheadBuffer.value.length === 1 && typeAheadBuffer.value === lower && elapsed < 850) {
        const start = activeIndex.value >= 0 ? (activeIndex.value + 1) % props.options.length : 0;
        const cycled = idxStartingWith(lower, start);
        if (cycled >= 0) {
            activeIndex.value = cycled;
            scrollOptionIntoView(cycled);
            scheduleTypeAheadReset();
            e.preventDefault();

            return true;
        }
        typeAheadBuffer.value = lower;
    } else if (elapsed > 850) {
        typeAheadBuffer.value = lower;
    } else {
        typeAheadBuffer.value = (typeAheadBuffer.value + lower).slice(0, 48);
    }

    let found = idxStartingWith(typeAheadBuffer.value, 0);
    if (found < 0 && typeAheadBuffer.value.length > 1) {
        typeAheadBuffer.value = lower;
        found = idxStartingWith(typeAheadBuffer.value, 0);
    }

    if (found < 0) {
        typeAheadBuffer.value = '';

        return false;
    }

    if (!open.value) {
        updatePanelPosition();
        open.value = true;
        activeIndex.value = found;
        nextTick(() => {
            schedulePanelPosition();
            panelRef.value?.focus?.();
            scrollOptionIntoView(found);
        });
    } else {
        activeIndex.value = found;
        scrollOptionIntoView(found);
    }
    scheduleTypeAheadReset();
    e.preventDefault();

    return true;
}

function onTriggerKeydown(e) {
    if (props.disabled) {
        return;
    }
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        clearTypeAheadState();
        if (!open.value) {
            openPanel();
        }

        return;
    }
    handleTypeAhead(e);
}

function onPanelKeydown(e) {
    if (e.key === 'Escape') {
        e.preventDefault();
        open.value = false;

        return;
    }
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        clearTypeAheadState();
        moveActive(1);

        return;
    }
    if (e.key === 'ArrowUp') {
        e.preventDefault();
        clearTypeAheadState();
        moveActive(-1);

        return;
    }
    if (e.key === 'Enter') {
        e.preventDefault();
        clearTypeAheadState();
        commitActive();

        return;
    }
    if (e.key === 'Home') {
        e.preventDefault();
        clearTypeAheadState();
        activeIndex.value = 0;

        return;
    }
    if (e.key === 'End') {
        e.preventDefault();
        clearTypeAheadState();
        activeIndex.value = Math.max(0, props.options.length - 1);

        return;
    }
    handleTypeAhead(e);
}

function toggle() {
    if (props.disabled) {
        return;
    }
    if (open.value) {
        open.value = false;

        return;
    }
    openPanel();
}

function select(v) {
    clearTypeAheadState();
    emit('update:modelValue', v);
    open.value = false;
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
    scrollOptionIntoView(i);
}

function commitActive() {
    const opt = props.options[activeIndex.value];
    if (opt) {
        select(opt.value);
    }
}

function onScrollOrResize() {
    if (open.value) {
        schedulePanelPosition();
    }
}

watch(
    open,
    (v) => {
    if (v) {
        schedulePanelPosition();
        window.addEventListener('scroll', onScrollOrResize, true);
        window.addEventListener('resize', onScrollOrResize);
    } else {
        panelStyle.value = {};
        window.removeEventListener('scroll', onScrollOrResize, true);
        window.removeEventListener('resize', onScrollOrResize);
        activeIndex.value = -1;
        clearTypeAheadState();
    }
    },
    { flush: 'post' },
);

watch(
    () => props.options,
    () => {
        if (open.value) {
            nextTick(schedulePanelPosition);
        }
    },
    { deep: true },
);

onUnmounted(() => {
    window.removeEventListener('scroll', onScrollOrResize, true);
    window.removeEventListener('resize', onScrollOrResize);
    clearTypeAheadState();
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
