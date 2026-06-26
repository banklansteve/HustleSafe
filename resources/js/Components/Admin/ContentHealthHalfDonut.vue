<template>
    <div class="flex flex-col items-center">
        <div class="relative w-full" :class="fat ? 'max-w-[300px]' : 'max-w-[260px]'">
            <svg
                class="w-full drop-shadow-sm"
                viewBox="0 0 240 148"
                role="img"
                :aria-label="ariaLabel"
            >
                <defs>
                    <linearGradient id="health-green" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" stop-color="#059669" />
                        <stop offset="100%" stop-color="#34d399" />
                    </linearGradient>
                    <linearGradient id="health-amber" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" stop-color="#d97706" />
                        <stop offset="100%" stop-color="#fbbf24" />
                    </linearGradient>
                    <linearGradient id="health-red" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" stop-color="#dc2626" />
                        <stop offset="100%" stop-color="#f87171" />
                    </linearGradient>
                    <linearGradient :id="needleSurfaceId" x1="0%" y1="100%" x2="100%" y2="0%">
                        <stop offset="0%" stop-color="#0f172a" />
                        <stop offset="35%" stop-color="#334155" />
                        <stop offset="70%" stop-color="#64748b" />
                        <stop offset="100%" stop-color="#f8fafc" />
                    </linearGradient>
                    <radialGradient :id="hubFaceId" cx="35%" cy="35%" r="65%">
                        <stop offset="0%" stop-color="#ffffff" />
                        <stop offset="55%" stop-color="#e2e8f0" />
                        <stop offset="100%" stop-color="#0f172a" />
                    </radialGradient>
                    <filter :id="segmentGlowId" x="-20%" y="-20%" width="140%" height="140%">
                        <feGaussianBlur stdDeviation="2" result="blur" />
                        <feMerge>
                            <feMergeNode in="blur" />
                            <feMergeNode in="SourceGraphic" />
                        </feMerge>
                    </filter>
                </defs>

                <path
                    pathLength="100"
                    d="M 16 118 A 104 104 0 0 1 224 118"
                    fill="none"
                    stroke="currentColor"
                    :stroke-width="trackWidth"
                    stroke-linecap="butt"
                    class="text-slate-200/90 dark:text-white/10"
                />

                <path
                    v-for="segment in segments"
                    :key="segment.key"
                    pathLength="100"
                    d="M 16 118 A 104 104 0 0 1 224 118"
                    fill="none"
                    :stroke="segment.stroke"
                    :stroke-width="arcWidth"
                    stroke-linecap="butt"
                    :stroke-dasharray="`${segment.size} ${100 - segment.size}`"
                    :stroke-dashoffset="segment.offset"
                    :filter="activeBand === segment.key ? `url(#${segmentGlowId})` : undefined"
                    class="cursor-pointer transition-opacity duration-200"
                    :class="segment.size > 0 ? 'opacity-100 hover:opacity-90' : 'opacity-0 pointer-events-none'"
                    @click="segment.size > 0 && emit('select-band', segment.key)"
                    @mouseenter="hoverBand = segment.key"
                    @mouseleave="hoverBand = null"
                >
                    <title>{{ segment.label }} · {{ segment.count }} ({{ segment.percent }}%)</title>
                </path>

                <path
                    :d="needleBlade.d"
                    fill="#64748b"
                    fill-opacity="0.28"
                    pointer-events="none"
                />
                <path
                    :d="needleBlade.d"
                    :fill="`url(#${needleSurfaceId})`"
                    stroke="#f8fafc"
                    stroke-width="0.5"
                    stroke-opacity="0.9"
                    pointer-events="none"
                />

                <circle
                    cx="120"
                    cy="118"
                    r="16"
                    :fill="`url(#${hubFaceId})`"
                    stroke="#cbd5e1"
                    stroke-width="1.25"
                />
                <circle cx="120" cy="118" r="6" fill="#0f172a" opacity="0.88" />
                <circle cx="118" cy="116" r="1.8" fill="#ffffff" opacity="0.35" />
            </svg>
        </div>

        <div class="mt-1 text-center">
            <p class="font-display text-4xl font-black tracking-tight text-slate-900 tabular-nums dark:text-white sm:text-5xl">
                {{ displayScore }}<span class="text-xl font-extrabold text-slate-400 sm:text-2xl">%</span>
            </p>
            <p class="mt-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">
                {{ label }}
            </p>
            <p
                class="mt-2 inline-flex rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide ring-1"
                :class="statusPillClass"
            >
                {{ statusLabel }}
            </p>
        </div>

        <div class="mt-4 grid w-full grid-cols-3 gap-2 text-center">
            <button
                v-for="legend in legendItems"
                :key="legend.key"
                type="button"
                class="rounded-xl border px-2 py-2 text-[10px] font-bold uppercase tracking-wide transition"
                :class="legendButtonClass(legend.key)"
                @click="legend.count > 0 && emit('select-band', legend.key)"
            >
                <span class="block text-base font-black tabular-nums text-slate-900 dark:text-white">{{ legend.count }}</span>
                <span class="mt-0.5 block">{{ legend.label }}</span>
            </button>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';

const svgId = `health-${Math.random().toString(36).slice(2, 11)}`;

const props = defineProps({
    label: { type: String, default: 'Content health' },
    score: { type: Number, default: 100 },
    statusLabel: { type: String, default: 'Healthy' },
    healthy: { type: Number, default: 0 },
    warning: { type: Number, default: 0 },
    critical: { type: Number, default: 0 },
    total: { type: Number, default: 0 },
    activeBand: { type: String, default: null },
    animateOnMount: { type: Boolean, default: true },
    fat: { type: Boolean, default: true },
});

const emit = defineEmits(['select-band']);

const arcWidth = computed(() => (props.fat ? 34 : 28));
const trackWidth = computed(() => (props.fat ? 36 : 30));

const targetScore = computed(() => {
    const n = Number(props.score ?? 0);

    return Math.min(100, Math.max(0, Number.isFinite(n) ? Math.round(n) : 0));
});

const displayScore = ref(0);
const hoverBand = ref(null);

const totalCount = computed(() => Math.max(0, Number(props.total || props.healthy + props.warning + props.critical)));

const segments = computed(() => {
    const total = Math.max(1, totalCount.value);
    const greenSize = (props.healthy / total) * 100;
    const amberSize = (props.warning / total) * 100;
    const redSize = (props.critical / total) * 100;

    return [
        {
            key: 'healthy',
            size: greenSize,
            offset: 0,
            stroke: 'url(#health-green)',
            count: props.healthy,
            percent: Math.round((props.healthy / total) * 100),
            label: 'Healthy',
        },
        {
            key: 'warning',
            size: amberSize,
            offset: -greenSize,
            stroke: 'url(#health-amber)',
            count: props.warning,
            percent: Math.round((props.warning / total) * 100),
            label: 'Review',
        },
        {
            key: 'critical',
            size: redSize,
            offset: -(greenSize + amberSize),
            stroke: 'url(#health-red)',
            count: props.critical,
            percent: Math.round((props.critical / total) * 100),
            label: 'At risk',
        },
    ];
});

const legendItems = computed(() => [
    { key: 'healthy', label: 'Healthy', count: props.healthy },
    { key: 'warning', label: 'Review', count: props.warning },
    { key: 'critical', label: 'At risk', count: props.critical },
]);

function bladeGeometry(score) {
    const s = Math.min(100, Math.max(0, score));
    const phi = Math.PI * (1 - s / 100);
    const cx = 120;
    const cy = 118;
    const tipLen = 92;
    const tipX = cx + tipLen * Math.cos(phi);
    const tipY = cy - tipLen * Math.sin(phi);
    const ux = Math.cos(phi);
    const uy = -Math.sin(phi);
    const px = -Math.sin(phi);
    const py = -Math.cos(phi);
    const baseHalf = props.fat ? 5.2 : 4.2;
    const baseInset = 12;
    const bx = cx - ux * baseInset;
    const by = cy - uy * baseInset;

    return {
        d: `M ${tipX.toFixed(2)} ${tipY.toFixed(2)} L ${(bx + px * baseHalf).toFixed(2)} ${(by + py * baseHalf).toFixed(2)} L ${(bx - px * baseHalf).toFixed(2)} ${(by - py * baseHalf).toFixed(2)} Z`,
    };
}

const needleBlade = computed(() => bladeGeometry(displayScore.value));

const needleSurfaceId = computed(() => `health-needle-${svgId}`);
const hubFaceId = computed(() => `health-hub-${svgId}`);
const segmentGlowId = computed(() => `health-glow-${svgId}`);

const ariaLabel = computed(() => `${props.label}: ${displayScore.value} percent health`);

const statusPillClass = computed(() => {
    const s = targetScore.value;
    if (s >= 82) {
        return 'bg-emerald-50 text-emerald-800 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-100 dark:ring-emerald-500/30';
    }
    if (s >= 58) {
        return 'bg-amber-50 text-amber-900 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-100 dark:ring-amber-500/30';
    }

    return 'bg-rose-50 text-rose-800 ring-rose-200 dark:bg-rose-500/10 dark:text-rose-100 dark:ring-rose-500/30';
});

function legendButtonClass(key) {
    const active = props.activeBand === key || hoverBand.value === key;
    const base = {
        healthy: 'border-emerald-200 bg-emerald-50/80 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-100',
        warning: 'border-amber-200 bg-amber-50/80 text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100',
        critical: 'border-rose-200 bg-rose-50/80 text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-100',
    }[key];

    return `${base} ${active ? 'ring-2 ring-primary-500/40' : 'border-slate-200/80 dark:border-white/10'}`;
}

function animateScore() {
    if (!props.animateOnMount) {
        displayScore.value = targetScore.value;

        return;
    }

    displayScore.value = 0;
    const target = targetScore.value;
    const duration = 1200;
    const start = performance.now();

    function tick(now) {
        const t = Math.min(1, (now - start) / duration);
        const eased = 1 - (1 - t) ** 3;
        displayScore.value = Math.round(target * eased);
        if (t < 1) {
            requestAnimationFrame(tick);
        } else {
            displayScore.value = target;
        }
    }

    requestAnimationFrame(tick);
}

onMounted(animateScore);

watch(targetScore, (v) => {
    if (!props.animateOnMount) {
        displayScore.value = v;
    }
});
</script>
