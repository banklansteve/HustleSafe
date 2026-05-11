<template>
    <!-- Abstract mesh + faux 3D planes — theme primary (+ sparing secondary accents) -->
    <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
        <template v-if="variant === 'categories'">
            <!-- Categories: deeper “3D” stack — primary-forward -->
            <div
                class="absolute inset-0 opacity-[0.55]"
                style="
                    background-image:
                        radial-gradient(ellipse 90% 65% at 15% 25%, rgb(13 148 136 / 0.34), transparent 55%),
                        radial-gradient(ellipse 70% 50% at 85% 75%, rgb(20 184 166 / 0.22), transparent 50%),
                        radial-gradient(circle at 50% 50%, rgb(204 251 241 / 0.35), transparent 62%);
                "
            />
            <div
                class="absolute left-[6%] top-[14%] h-56 w-[min(52vw,22rem)] origin-center rounded-[2rem] border border-primary-400/25 bg-gradient-to-br from-primary-600/35 via-primary-700/25 to-primary-900/45 opacity-90 shadow-[0_28px_90px_-24px_rgba(13,148,136,0.55)]"
                style="transform: rotateX(52deg) rotateY(-18deg) rotateZ(-8deg); transform-style: preserve-3d"
            />
            <div
                class="absolute right-[4%] top-[38%] h-44 w-[min(48vw,18rem)] origin-center rounded-3xl border border-primary-300/20 bg-gradient-to-tr from-primary-500/28 to-teal-600/18 opacity-75 shadow-[0_22px_70px_-20px_rgba(15,118,110,0.45)]"
                style="transform: rotateX(42deg) rotateY(22deg) rotateZ(6deg); transform-style: preserve-3d"
            />
            <div
                class="absolute bottom-[10%] left-[18%] h-36 w-[min(60vw,26rem)] origin-center rounded-2xl border border-white/25 bg-gradient-to-r from-white/40 via-primary-100/35 to-white/15 opacity-55 shadow-[0_-30px_80px_-28px_rgba(13,148,136,0.35)]"
                style="transform: rotateX(58deg) skewX(-10deg); transform-style: preserve-3d"
            />
            <div
                class="absolute bottom-[22%] right-[12%] h-28 w-48 rounded-2xl bg-gradient-to-bl from-secondary-400/22 to-transparent opacity-60 blur-[1px]"
                style="transform: rotate(-8deg) skewX(8deg)"
            />
            <div
                class="absolute left-1/2 top-[42%] h-72 w-72 -translate-x-1/2 rounded-full bg-gradient-to-r from-primary-400/22 via-teal-300/14 to-secondary-400/10 blur-3xl motion-safe:animate-subtle-float"
            />
        </template>

        <template v-else>
            <div
                class="absolute h-[min(32rem,85vw)] w-[min(32rem,85vw)] rounded-[2.75rem] bg-gradient-to-br blur-3xl motion-safe:animate-subtle-float"
                :class="
                    '-right-12 top-12 rotate-[-10deg] from-secondary-500/22 via-primary-400/28 to-transparent'
                "
            />
            <div
                class="absolute h-[26rem] w-[26rem] rounded-[40%] bg-gradient-to-tl blur-3xl motion-safe:animate-subtle-float [animation-delay:0.8s]"
                :class="'-bottom-24 left-[-8%] from-primary-600/22 to-secondary-400/14'"
            />
            <div
                class="absolute left-1/2 top-1/2 h-72 w-72 -translate-x-1/2 -translate-y-1/2 rounded-full bg-gradient-to-r blur-3xl from-secondary-400/14 to-primary-300/16"
            />
            <div
                class="absolute bottom-0 left-[8%] h-24 w-[52%] skew-x-12 rounded-2xl bg-gradient-to-tr from-white/45 via-primary-100/30 to-transparent opacity-50 shadow-[0_-24px_60px_-12px_rgba(79,70,229,0.12)]"
            />
            <div
                class="absolute right-[10%] top-[18%] h-16 w-40 -skew-x-6 rounded-xl bg-gradient-to-bl from-primary-400/28 to-transparent opacity-40 shadow-lg"
            />
        </template>
    </div>
</template>

<script setup>
defineProps({
    variant: {
        type: String,
        default: 'categories',
        validator: (v) => ['categories', 'popular_jobs'].includes(v),
    },
});
</script>
