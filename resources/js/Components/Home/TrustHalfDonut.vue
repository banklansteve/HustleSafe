<template>
    <div class="flex flex-col items-center">
        <div class="relative w-full" :class="compact ? 'max-w-[220px]' : 'max-w-[280px]'">
            <svg
                class="w-full text-slate-200 drop-shadow-sm"
                viewBox="0 0 240 140"
                role="img"
                :aria-label="ariaLabel"
            >
                <defs>
                    <linearGradient :id="gradientId" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" :stop-color="band.stops[0]" />
                        <stop offset="45%" :stop-color="band.stops[1]" />
                        <stop offset="100%" :stop-color="band.stops[2]" />
                    </linearGradient>
                    <linearGradient :id="needleSurfaceId" x1="0%" y1="100%" x2="100%" y2="0%">
                        <stop offset="0%" stop-color="#78350f" />
                        <stop offset="28%" stop-color="#b45309" />
                        <stop offset="58%" stop-color="#fbbf24" />
                        <stop offset="88%" stop-color="#fde68a" />
                        <stop offset="100%" stop-color="#fffbeb" />
                    </linearGradient>
                    <radialGradient :id="hubFaceId" cx="35%" cy="35%" r="65%">
                        <stop offset="0%" stop-color="#f8fafc" />
                        <stop offset="55%" stop-color="#e2e8f0" />
                        <stop offset="100%" stop-color="#0f172a" />
                    </radialGradient>
                    <filter :id="glowId" x="-40%" y="-40%" width="180%" height="180%">
                        <feGaussianBlur stdDeviation="3" result="blur" />
                        <feMerge>
                            <feMergeNode in="blur" />
                            <feMergeNode in="SourceGraphic" />
                        </feMerge>
                    </filter>
                    <filter :id="needleBloomId" x="-80%" y="-80%" width="260%" height="260%">
                        <feGaussianBlur stdDeviation="5" result="b" />
                        <feColorMatrix
                            in="b"
                            type="matrix"
                            values="0 0 0 0 0.98
                                    0 0 0 0 0.72
                                    0 0 0 0 0.12
                                    0 0 0 0.95 0"
                            result="g"
                        />
                        <feMerge>
                            <feMergeNode in="g" />
                            <feMergeNode in="SourceGraphic" />
                        </feMerge>
                    </filter>
                </defs>
                <!-- background arc (track) -->
                <path
                    pathLength="100"
                    d="M 20 112 A 100 100 0 0 1 220 112"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="22"
                    stroke-linecap="round"
                    class="text-slate-200/95"
                />
                <!-- value arc -->
                <path
                    pathLength="100"
                    d="M 20 112 A 100 100 0 0 1 220 112"
                    fill="none"
                    :stroke="`url(#${gradientId})`"
                    stroke-width="26"
                    stroke-linecap="round"
                    :stroke-dasharray="100"
                    :stroke-dashoffset="dashOffset"
                    :filter="`url(#${glowId})`"
                    :class="animateOnMount ? '' : 'transition-[stroke-dashoffset] duration-700 ease-out'"
                />
                <!-- Digital needle: soft bloom (reads well on dark UI) -->
                <path
                    :d="needleBlade.d"
                    fill="#f59e0b"
                    fill-opacity="0.35"
                    :filter="`url(#${needleBloomId})`"
                    pointer-events="none"
                />
                <!-- Tapered blade -->
                <path
                    :d="needleBlade.d"
                    :fill="`url(#${needleSurfaceId})`"
                    stroke="#fffbeb"
                    stroke-width="0.45"
                    stroke-opacity="0.95"
                    pointer-events="none"
                />
                <!-- Pivot cap — hides shaft join, premium dial centre -->
                <circle cx="120" cy="112" r="14" :fill="`url(#${hubFaceId})`" stroke="#cbd5e1" stroke-width="1.25" />
                <circle cx="120" cy="112" r="5.5" fill="#0f172a" opacity="0.88" />
                <circle cx="118" cy="110" r="1.6" fill="#ffffff" opacity="0.35" />
            </svg>
        </div>
        <div class="mt-1 text-center">
            <p
                class="font-display font-black tracking-tight text-slate-900 tabular-nums"
                :class="compact ? 'text-4xl sm:text-5xl' : 'text-5xl sm:text-6xl'"
            >
                {{ displayScore }}<span class="text-2xl font-extrabold text-primary-600 sm:text-3xl">%</span>
            </p>
            <p class="mt-2 text-xs font-semibold uppercase tracking-wider text-slate-500">
                {{ label }}
            </p>
            <p
                class="mt-2 inline-flex rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide ring-1"
                :class="band.pillClass"
            >
                {{ band.label }}
            </p>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';

const svgId = `trust-${Math.random().toString(36).slice(2, 11)}`;

const props = defineProps({
    score: {
        type: [Number, String],
        default: 0,
    },
    label: {
        type: String,
        default: 'Trust score',
    },
    variant: {
        type: String,
        default: 'freelancer',
    },
    animateOnMount: {
        type: Boolean,
        default: false,
    },
    compact: {
        type: Boolean,
        default: false,
    },
});

const targetScore = computed(() => {
    const n = Number(props.score ?? 0);

    return Math.min(100, Math.max(0, Number.isFinite(n) ? Math.round(n) : 0));
});

const displayScore = ref(0);

/**
 * Tapered blade: sharp tip on arc, wide base opposite (hidden under hub).
 * Upper semicircle centre (120,112), φ = π → left, 0 → right.
 */
function bladeGeometry(score) {
    const s = Math.min(100, Math.max(0, score));
    const phi = Math.PI * (1 - s / 100);
    const cx = 120;
    const cy = 112;
    const tipLen = 88;
    const tipX = cx + tipLen * Math.cos(phi);
    const tipY = cy - tipLen * Math.sin(phi);
    const ux = Math.cos(phi);
    const uy = -Math.sin(phi);
    const px = -Math.sin(phi);
    const py = -Math.cos(phi);
    const baseHalf = 4.2;
    const baseInset = 11;
    const bx = cx - ux * baseInset;
    const by = cy - uy * baseInset;

    return {
        d: `M ${tipX.toFixed(2)} ${tipY.toFixed(2)} L ${(bx + px * baseHalf).toFixed(2)} ${(by + py * baseHalf).toFixed(2)} L ${(bx - px * baseHalf).toFixed(2)} ${(by - py * baseHalf).toFixed(2)} Z`,
    };
}

const needleBlade = computed(() => bladeGeometry(displayScore.value));

const glowId = computed(() => `trust-glow-${svgId}`);
const needleBloomId = computed(() => `trust-needle-bloom-${svgId}`);
const needleSurfaceId = computed(() => `trust-needle-surface-${svgId}`);
const hubFaceId = computed(() => `trust-hub-${svgId}`);

onMounted(() => {
    if (!props.animateOnMount) {
        displayScore.value = targetScore.value;

        return;
    }

    displayScore.value = 0;
    const target = targetScore.value;
    const duration = 1150;
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
});

watch(
    targetScore,
    (v) => {
        if (!props.animateOnMount) {
            displayScore.value = v;
        }
    },
);

const dashOffset = computed(() => 100 - displayScore.value);

const gradientId = computed(() => `trust-arc-${svgId}-${props.variant}-${targetScore.value}`);

const ariaLabel = computed(() => `${props.label}: ${displayScore.value} percent`);

const band = computed(() => {
    const s = targetScore.value;
    if (s < 20) {
        return {
            stops: ['#dc2626', '#ef4444', '#f87171'],
            label: 'Needs attention',
            pillClass: 'bg-red-50 text-red-800 ring-red-200',
        };
    }
    if (s < 35) {
        return {
            stops: ['#ea580c', '#f97316', '#fb923c'],
            label: 'Building up',
            pillClass: 'bg-orange-50 text-orange-900 ring-orange-200',
        };
    }
    if (s < 50) {
        return {
            stops: ['#d97706', '#f59e0b', '#fbbf24'],
            label: 'Getting there',
            pillClass: 'bg-amber-50 text-amber-900 ring-amber-200',
        };
    }
    if (s < 65) {
        return {
            stops: ['#ca8a04', '#eab308', '#fde047'],
            label: 'Solid',
            pillClass: 'bg-yellow-50 text-yellow-900 ring-yellow-200',
        };
    }
    if (s < 80) {
        return {
            stops: ['#65a30d', '#84cc16', '#a3e635'],
            label: 'Strong',
            pillClass: 'bg-lime-50 text-lime-900 ring-lime-200',
        };
    }
    if (s < 92) {
        return {
            stops: ['#0f766e', '#14b8a6', '#2dd4bf'],
            label: 'Excellent',
            pillClass: 'bg-primary-50 text-primary-900 ring-primary-200',
        };
    }
    return {
        stops: ['#0f766e', '#14b8a6', '#f59e0b'],
        label: 'Outstanding',
        pillClass: 'bg-gradient-to-r from-primary-50 to-secondary-50 text-primary-900 ring-primary-200',
    };
});
</script>
