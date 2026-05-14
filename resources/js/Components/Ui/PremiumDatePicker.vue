<template>
    <div class="relative" :class="wrapperClass">
        <button
            type="button"
            :id="resolvedId"
            :disabled="disabled"
            class="flex w-full items-center justify-between gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-left text-sm font-semibold text-slate-900 shadow-sm transition hover:border-primary-300 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/25 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-400"
            :aria-expanded="open"
            aria-haspopup="dialog"
            @click="toggle"
        >
            <span :class="displayValue ? 'text-slate-900' : 'text-slate-400'">
                {{ displayValue || placeholder }}
            </span>
            <CalendarDaysIcon class="h-5 w-5 shrink-0 text-primary-600" aria-hidden="true" />
        </button>
        <InputError v-if="error" class="mt-1.5" :message="error" />

        <Teleport to="body">
            <div
                v-if="open"
                class="fixed inset-0 z-[100] flex items-end justify-center bg-slate-950/40 p-3 sm:items-center sm:p-6"
                role="presentation"
                @click.self="close"
            >
                <div
                    ref="panelRef"
                    class="max-h-[min(90vh,520px)] w-full max-w-sm overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-900/25 ring-1 ring-slate-100"
                    role="dialog"
                    :aria-label="placeholder"
                    @click.stop
                >
                    <div class="flex items-center justify-between border-b border-slate-100 bg-gradient-to-r from-primary-50/80 to-white px-4 py-3">
                        <button
                            type="button"
                            class="rounded-lg p-2 text-slate-600 transition hover:bg-white hover:text-slate-900"
                            aria-label="Previous month"
                            @click="shiftMonth(-1)"
                        >
                            <ChevronLeftIcon class="h-5 w-5" />
                        </button>
                        <p class="font-display text-sm font-black tracking-tight text-slate-900">
                            {{ monthTitle }}
                        </p>
                        <button
                            type="button"
                            class="rounded-lg p-2 text-slate-600 transition hover:bg-white hover:text-slate-900"
                            aria-label="Next month"
                            @click="shiftMonth(1)"
                        >
                            <ChevronRightIcon class="h-5 w-5" />
                        </button>
                    </div>
                    <div class="grid grid-cols-7 gap-1 px-3 pb-2 pt-3 text-center text-[10px] font-black uppercase tracking-wide text-slate-400">
                        <span v-for="d in weekDays" :key="d">{{ d }}</span>
                    </div>
                    <div class="grid grid-cols-7 gap-1 px-3 pb-4">
                        <button
                            v-for="(cell, idx) in gridCells"
                            :key="idx"
                            type="button"
                            :disabled="!cell.inMonth || cell.disabled"
                            class="relative flex min-h-[2.5rem] items-center justify-center rounded-xl text-sm font-bold tabular-nums transition"
                            :class="cellClass(cell)"
                            @click="pick(cell)"
                        >
                            <span v-if="cell.inMonth" class="pointer-events-none">{{ cell.d }}</span>
                        </button>
                    </div>
                    <div class="flex items-center justify-end gap-2 border-t border-slate-100 bg-slate-50/80 px-4 py-3">
                        <button
                            type="button"
                            class="rounded-full px-4 py-2 text-xs font-bold text-slate-600 hover:bg-white"
                            @click="close"
                        >
                            Close
                        </button>
                        <button
                            v-if="modelValue"
                            type="button"
                            class="rounded-full px-4 py-2 text-xs font-bold text-rose-700 hover:bg-rose-50"
                            @click="clear"
                        >
                            Clear
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import InputError from '@/Components/InputError.vue';
import { CalendarDaysIcon, ChevronLeftIcon, ChevronRightIcon } from '@heroicons/vue/24/outline';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    inputId: {
        type: String,
        default: '',
    },
    id: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Pick a date',
    },
    min: {
        type: String,
        default: '',
    },
    max: {
        type: String,
        default: '',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: '',
    },
    wrapperClass: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue']);

const resolvedId = computed(() => props.id || props.inputId || '');

const open = ref(false);
const panelRef = ref(null);
const cursor = ref(todayParts());
let ignoreNextDocumentClick = false;

const weekDays = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];

function todayParts() {
    const t = new Date();

    return { y: t.getFullYear(), m: t.getMonth() + 1 };
}

function parseYmd(s) {
    if (!s || typeof s !== 'string') {
        return null;
    }
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s.trim());
    if (!m) {
        return null;
    }
    const y = Number(m[1]);
    const mo = Number(m[2]);
    const d = Number(m[3]);
    const dt = new Date(y, mo - 1, d);

    if (dt.getFullYear() !== y || dt.getMonth() !== mo - 1 || dt.getDate() !== d) {
        return null;
    }

    return { y, m: mo, d };
}

function toYmd(y, m, d) {
    const mm = String(m).padStart(2, '0');
    const dd = String(d).padStart(2, '0');

    return `${y}-${mm}-${dd}`;
}

function minParts() {
    return props.min ? parseYmd(props.min) : null;
}

function maxParts() {
    return props.max ? parseYmd(props.max) : null;
}

function cmp(a, b) {
    if (!a || !b) {
        return 0;
    }
    if (a.y !== b.y) {
        return a.y - b.y;
    }
    if (a.m !== b.m) {
        return a.m - b.m;
    }

    return a.d - b.d;
}

const displayValue = computed(() => {
    const p = parseYmd(props.modelValue);
    if (!p) {
        return '';
    }
    try {
        const dt = new Date(p.y, p.m - 1, p.d);

        return dt.toLocaleDateString('en-NG', {
            weekday: 'short',
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            timeZone: 'Africa/Lagos',
        });
    } catch {
        return props.modelValue;
    }
});

const monthTitle = computed(() => {
    const { y, m } = cursor.value;
    try {
        return new Date(y, m - 1, 1).toLocaleDateString('en-NG', { month: 'long', year: 'numeric', timeZone: 'Africa/Lagos' });
    } catch {
        return `${y}-${m}`;
    }
});

const gridCells = computed(() => {
    const { y, m } = cursor.value;
    const first = new Date(y, m - 1, 1);
    const startPad = (first.getDay() + 6) % 7;
    const daysInMonth = new Date(y, m, 0).getDate();
    const cells = [];
    const mi = minParts();
    const ma = maxParts();

    for (let i = 0; i < startPad; i++) {
        cells.push({ inMonth: false, disabled: true, day: 0, ymd: '' });
    }
    for (let d = 1; d <= daysInMonth; d++) {
        const cell = { y, m, d, inMonth: true, ymd: toYmd(y, m, d), disabled: false };
        const cur = { y, m, d };
        if (mi && cmp(cur, mi) < 0) {
            cell.disabled = true;
        }
        if (ma && cmp(cur, ma) > 0) {
            cell.disabled = true;
        }
        cells.push(cell);
    }
    while (cells.length % 7 !== 0) {
        cells.push({ inMonth: false, disabled: true, day: 0, ymd: '' });
    }

    return cells;
});

function cellClass(cell) {
    if (!cell.inMonth) {
        return 'bg-transparent text-transparent';
    }
    if (cell.disabled) {
        return 'cursor-not-allowed bg-slate-50 text-slate-300 line-through decoration-slate-300';
    }
    const sel = parseYmd(props.modelValue);
    const isSel = sel && sel.y === cell.y && sel.m === cell.m && sel.d === cell.d;
    const t = new Date();
    const isToday = t.getFullYear() === cell.y && t.getMonth() + 1 === cell.m && t.getDate() === cell.d;
    if (isSel) {
        return 'bg-primary-600 text-white shadow-md shadow-primary-900/25 ring-2 ring-primary-400/40 hover:bg-primary-700';
    }
    if (isToday) {
        return 'bg-primary-50 text-primary-950 ring-1 ring-primary-200 hover:bg-primary-100';
    }

    return 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200/90 hover:bg-slate-50 hover:ring-primary-200/80';
}

function syncCursorFromValue() {
    const p = parseYmd(props.modelValue);
    if (p) {
        cursor.value = { y: p.y, m: p.m };

        return;
    }
    const mn = minParts();
    if (mn) {
        cursor.value = { y: mn.y, m: mn.m };

        return;
    }
    cursor.value = todayParts();
}

watch(
    () => props.modelValue,
    () => {
        syncCursorFromValue();
    },
    { immediate: true },
);

watch(
    () => [props.min, props.max],
    () => {
        syncCursorFromValue();
    },
);

function shiftMonth(delta) {
    let { y, m } = cursor.value;
    m += delta;
    if (m < 1) {
        m = 12;
        y -= 1;
    }
    if (m > 12) {
        m = 1;
        y += 1;
    }
    cursor.value = { y, m };
}

function pick(cell) {
    if (!cell.inMonth || cell.disabled) {
        return;
    }
    emit('update:modelValue', cell.ymd);
    close();
}

function clear() {
    emit('update:modelValue', '');
    close();
}

function toggle() {
    if (props.disabled) {
        return;
    }
    if (open.value) {
        close();

        return;
    }
    open.value = true;
    syncCursorFromValue();
    ignoreNextDocumentClick = true;
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            ignoreNextDocumentClick = false;
        });
    });
}

function close() {
    open.value = false;
}

function onKey(e) {
    if (e.key === 'Escape') {
        close();
    }
}

function onDocClick(e) {
    if (ignoreNextDocumentClick) {
        return;
    }
    if (!open.value || !panelRef.value) {
        return;
    }
    const t = e.target;
    if (t instanceof Node && panelRef.value.contains(t)) {
        return;
    }
    const bid = resolvedId.value;
    const btn = bid ? document.getElementById(bid) : null;
    if (btn && t instanceof Node && btn.contains(t)) {
        return;
    }
    close();
}

onMounted(() => {
    document.addEventListener('keydown', onKey);
    document.addEventListener('click', onDocClick);
});

onUnmounted(() => {
    document.removeEventListener('keydown', onKey);
    document.removeEventListener('click', onDocClick);
});
</script>
