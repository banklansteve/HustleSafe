<template>
    <div class="pointer-events-none absolute inset-0 flex items-end justify-center overflow-visible" aria-hidden="true">
        <span
            v-for="(h, i) in hearts"
            :key="h.id"
            class="portfolio-heart-particle absolute text-lg"
            :style="{
                '--dx': h.dx + 'px',
                '--peak': h.peak + 'px',
                '--rot': h.rot + 'deg',
                '--delay': h.delay + 'ms',
                left: '50%',
                bottom: '40%',
            }"
        >
            {{ h.char }}
        </span>
    </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue';

const props = defineProps({
    trigger: {
        type: Number,
        default: 0,
    },
});

const hearts = ref([]);

let seq = 0;

function burst() {
    const chars = ['❤', '♥', '💗'];
    const batch = [];
    const count = 12;
    for (let i = 0; i < count; i++) {
        batch.push({
            id: ++seq,
            char: chars[i % chars.length],
            dx: Math.round((Math.random() - 0.5) * 90),
            peak: 48 + Math.round(Math.random() * 52),
            rot: Math.round((Math.random() - 0.5) * 40),
            delay: Math.round(Math.random() * 120),
        });
    }
    hearts.value = batch;
    window.setTimeout(() => {
        hearts.value = [];
    }, 900);
}

onMounted(() => {
    if (props.trigger > 0) {
        burst();
    }
});

watch(
    () => props.trigger,
    (v, prev) => {
        if (v > 0 && v !== prev) {
            burst();
        }
    },
);
</script>

<style scoped>
.portfolio-heart-particle {
    animation: heart-fountain 0.85s cubic-bezier(0.22, 1, 0.36, 1) forwards;
    animation-delay: var(--delay);
    opacity: 0;
    transform: translate(-50%, 0) rotate(0deg) scale(0.6);
    filter: drop-shadow(0 2px 4px rgba(244, 63, 94, 0.35));
}

@keyframes heart-fountain {
    0% {
        opacity: 0;
        transform: translate(-50%, 0) rotate(var(--rot)) scale(0.5);
    }
    18% {
        opacity: 1;
        transform: translate(calc(-50% + var(--dx) * 0.35), calc(-1 * var(--peak) * 0.55)) rotate(var(--rot))
            scale(1.05);
    }
    45% {
        opacity: 1;
        transform: translate(calc(-50% + var(--dx) * 0.75), calc(-1 * var(--peak))) rotate(calc(var(--rot) * 1.2))
            scale(1.1);
    }
    100% {
        opacity: 0;
        transform: translate(calc(-50% + var(--dx)), calc(var(--peak) * 0.35)) rotate(calc(var(--rot) * 1.5)) scale(0.65);
    }
}
</style>
