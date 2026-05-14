<template>
    <div ref="rootRef" data-ui-multi-select class="relative" :class="attrs.class">
        <button
            type="button"
            class="group flex w-full min-h-[3.25rem] items-center justify-between gap-3 rounded-2xl border border-slate-200/95 bg-white px-4 py-3 text-left shadow-sm ring-1 ring-slate-100/90 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/35 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:opacity-70"
            :class="open ? 'border-primary-300 ring-primary-100' : 'hover:border-primary-200'"
            :disabled="disabled"
            :aria-expanded="open"
            @click.stop="toggle"
        >
            <span class="min-w-0 flex-1 text-[15px] font-semibold leading-snug text-slate-900">
                <span v-if="!selectedCount" class="text-slate-400">{{ placeholder }}</span>
                <span v-else>{{ summaryText }}</span>
            </span>
            <ChevronDownIcon class="h-5 w-5 shrink-0 text-slate-400 transition group-hover:text-primary-600" :class="open ? 'rotate-180 text-primary-600' : ''" />
        </button>

        <Teleport to="body">
            <Transition name="ui-ms-dim">
                <div v-if="open" class="fixed inset-0 z-[280] bg-slate-900/25 backdrop-blur-[1px]" @click="open = false" />
            </Transition>
            <Transition name="ui-ms-panel">
                <div
                    v-if="open"
                    ref="panelRef"
                    tabindex="-1"
                    class="fixed z-[290] flex max-h-[min(70vh,28rem)] flex-col overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-2xl shadow-primary-900/15 ring-1 ring-primary-100/70 outline-none sm:max-h-[min(60vh,24rem)]"
                    :style="panelStyle"
                    @keydown.esc.prevent="open = false"
                >
                    <div v-if="searchable" class="shrink-0 border-b border-slate-100 p-3">
                        <input
                            v-model="q"
                            type="search"
                            autocomplete="off"
                            :placeholder="searchPlaceholder"
                            class="w-full rounded-xl border border-slate-200 bg-slate-50/80 px-3 py-2.5 text-sm font-semibold text-slate-900 placeholder:text-slate-400 focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
                        />
                    </div>
                    <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain py-2">
                        <template v-for="(grp, gi) in filteredGroups" :key="grp.label + gi">
                            <p class="sticky top-0 z-[1] bg-white/95 px-4 pb-1 pt-2 text-[10px] font-black uppercase tracking-wider text-slate-400 backdrop-blur-sm">
                                {{ grp.label }}
                            </p>
                            <ul role="listbox" class="px-2">
                                <li v-for="opt in grp.options" :key="String(opt.value)">
                                    <label
                                        class="flex cursor-pointer items-start gap-3 rounded-xl px-3 py-3.5 transition active:bg-primary-50"
                                        :class="isSelected(opt.value) ? 'bg-primary-50/90 ring-1 ring-inset ring-primary-100' : 'hover:bg-slate-50'"
                                    >
                                        <input
                                            type="checkbox"
                                            class="mt-0.5 h-5 w-5 shrink-0 rounded-md border-slate-300 text-primary-600 focus:ring-primary-500"
                                            :checked="isSelected(opt.value)"
                                            @change="toggleValue(opt.value)"
                                        />
                                        <span class="min-w-0 flex-1">
                                            <span class="block text-[15px] font-bold text-slate-900">{{ opt.label }}</span>
                                            <span v-if="opt.hint" class="mt-0.5 block text-xs font-semibold text-slate-500">{{ opt.hint }}</span>
                                        </span>
                                    </label>
                                </li>
                            </ul>
                        </template>
                        <p v-if="!totalFiltered" class="px-4 py-8 text-center text-sm font-semibold text-slate-500">
                            No matches
                        </p>
                    </div>
                    <div class="shrink-0 border-t border-slate-100 bg-slate-50/90 p-3">
                        <button
                            type="button"
                            class="w-full rounded-xl bg-primary-600 py-3 text-sm font-black text-white shadow-md shadow-primary-900/15 transition hover:bg-primary-700"
                            @click="open = false"
                        >
                            Done
                        </button>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<script setup>
import { ChevronDownIcon } from '@heroicons/vue/20/solid';
import { computed, nextTick, onUnmounted, ref, useAttrs, watch } from 'vue';

defineOptions({ inheritAttrs: false });
const attrs = useAttrs();

const props = defineProps({
    modelValue: {
        type: Array,
        default: () => [],
    },
    /** @type {{ label: string, options: { value: string|number, label: string, hint?: string }[] }[]} */
    groups: {
        type: Array,
        required: true,
    },
    placeholder: {
        type: String,
        default: 'Select…',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    searchable: {
        type: Boolean,
        default: true,
    },
    searchPlaceholder: {
        type: String,
        default: 'Filter by name…',
    },
    max: {
        type: Number,
        default: 20,
    },
});

const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const rootRef = ref(null);
const panelRef = ref(null);
const panelStyle = ref({});
const q = ref('');

const selectedCount = computed(() => (Array.isArray(props.modelValue) ? props.modelValue.length : 0));

const summaryText = computed(() => {
    const n = selectedCount.value;
    if (!n) {
        return '';
    }
    if (n === 1) {
        return '1 freelancer selected';
    }

    return `${n} freelancers selected`;
});

const needle = computed(() => q.value.trim().toLowerCase());

const filteredGroups = computed(() => {
    const nd = needle.value;
    const out = [];
    for (const grp of props.groups || []) {
        const opts = (grp.options || []).filter((o) => {
            if (!nd) {
                return true;
            }
            const a = String(o.label || '').toLowerCase();
            const b = String(o.hint || '').toLowerCase();

            return a.includes(nd) || b.includes(nd);
        });
        if (opts.length) {
            out.push({ ...grp, options: opts });
        }
    }

    return out;
});

const totalFiltered = computed(() => filteredGroups.value.reduce((acc, g) => acc + (g.options?.length || 0), 0));

function isSelected(v) {
    return (props.modelValue || []).some((x) => String(x) === String(v));
}

function toggleValue(v) {
    const cur = [...(props.modelValue || [])];
    const i = cur.findIndex((x) => String(x) === String(v));
    if (i >= 0) {
        cur.splice(i, 1);
        emit('update:modelValue', cur);

        return;
    }
    if (cur.length >= props.max) {
        return;
    }
    cur.push(typeof v === 'number' ? v : Number.isFinite(Number(v)) ? Number(v) : v);
    emit('update:modelValue', cur);
}

function updatePanelPosition() {
    const tr = rootRef.value;
    if (!tr) {
        return;
    }
    const r = tr.getBoundingClientRect();
    const vw = window.innerWidth;
    const margin = 12;
    const gap = 8;
    const maxH = Math.min(window.innerHeight * 0.72, 448);
    let left = r.left;
    const width = Math.min(Math.max(r.width, 280), vw - margin * 2);
    left = Math.min(Math.max(margin, left), vw - width - margin);

    panelStyle.value = {
        top: `${r.bottom + gap}px`,
        left: `${left}px`,
        width: `${width}px`,
        maxHeight: `${maxH}px`,
    };
}

function toggle() {
    if (props.disabled) {
        return;
    }
    open.value = !open.value;
    if (open.value) {
        q.value = '';
        nextTick(() => {
            updatePanelPosition();
            panelRef.value?.focus?.();
        });
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
    }
});

onUnmounted(() => {
    window.removeEventListener('scroll', onScrollOrResize, true);
    window.removeEventListener('resize', onScrollOrResize);
});
</script>

<style scoped>
.ui-ms-dim-enter-active,
.ui-ms-dim-leave-active {
    transition: opacity 0.18s ease;
}
.ui-ms-dim-enter-from,
.ui-ms-dim-leave-to {
    opacity: 0;
}

.ui-ms-panel-enter-active,
.ui-ms-panel-leave-active {
    transition: opacity 0.16s ease, transform 0.16s ease;
}
.ui-ms-panel-enter-from,
.ui-ms-panel-leave-to {
    opacity: 0;
    transform: translateY(8px);
}
</style>
