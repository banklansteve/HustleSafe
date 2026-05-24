<template>
    <div class="error-scene relative mx-auto flex h-44 w-44 items-end justify-center sm:h-52 sm:w-52" aria-hidden="true">
        <div class="error-scene__glow absolute inset-6 rounded-full bg-primary-400/25 blur-2xl" />
        <div class="error-scene__box relative z-10 w-32 rounded-2xl border-2 border-slate-800/10 bg-gradient-to-br from-amber-100 via-amber-50 to-white p-3 shadow-xl shadow-amber-900/20 sm:w-36">
            <div class="flex items-center gap-1.5">
                <span class="h-2.5 w-2.5 rounded-full bg-rose-400" />
                <span class="h-2.5 w-2.5 rounded-full bg-amber-400" />
                <span class="h-2.5 w-2.5 rounded-full bg-emerald-400" />
            </div>
            <div class="mt-3 grid grid-cols-3 gap-1.5">
                <span class="error-scene__tool h-7 rounded-lg bg-primary-600/90 shadow-sm" />
                <span class="error-scene__tool h-7 rounded-lg bg-teal-600/90 shadow-sm [animation-delay:120ms]" />
                <span class="error-scene__tool h-7 rounded-lg bg-slate-700/90 shadow-sm [animation-delay:240ms]" />
                <span class="error-scene__tool col-span-2 h-5 rounded-lg bg-primary-200 shadow-inner [animation-delay:360ms]" />
                <span class="error-scene__tool h-5 rounded-lg bg-amber-300 shadow-inner [animation-delay:480ms]" />
            </div>
            <div class="mt-3 flex justify-center">
                <span class="error-scene__wrench inline-block h-8 w-8 rounded-full bg-slate-800 text-center text-lg leading-8 text-amber-200 shadow-lg">🔧</span>
            </div>
        </div>
        <span class="error-scene__spark absolute -right-1 top-6 text-2xl">✦</span>
        <span class="error-scene__spark absolute -left-2 bottom-10 text-xl [animation-delay:400ms]">✦</span>
    </div>
</template>

<style scoped>
.error-scene__box {
    animation: error-float 4s ease-in-out infinite;
}

.error-scene__tool {
    animation: error-pop 2.4s ease-in-out infinite;
}

.error-scene__wrench {
    animation: error-wrench 2.8s ease-in-out infinite;
}

.error-scene__spark {
    animation: error-spark 1.8s ease-in-out infinite;
}

.error-scene__glow {
    animation: error-glow 3s ease-in-out infinite;
}

@keyframes error-float {
    0%,
    100% {
        transform: translateY(0) rotate(-1deg);
    }
    50% {
        transform: translateY(-8px) rotate(1deg);
    }
}

@keyframes error-pop {
    0%,
    100% {
        transform: scale(1);
        opacity: 1;
    }
    50% {
        transform: scale(0.92);
        opacity: 0.85;
    }
}

@keyframes error-wrench {
    0%,
    100% {
        transform: rotate(-12deg);
    }
    50% {
        transform: rotate(18deg);
    }
}

@keyframes error-spark {
    0%,
    100% {
        opacity: 0.35;
        transform: scale(0.85);
    }
    50% {
        opacity: 1;
        transform: scale(1.1);
    }
}

@keyframes error-glow {
    0%,
    100% {
        opacity: 0.45;
    }
    50% {
        opacity: 0.85;
    }
}
</style>
