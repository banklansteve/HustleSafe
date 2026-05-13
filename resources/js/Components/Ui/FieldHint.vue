<template>
    <div class="relative">
        <button
            type="button"
            class="ml-1 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-primary-200 bg-primary-50 text-[10px] font-black text-primary-800 shadow-sm ring-1 ring-primary-100/80 transition hover:bg-primary-100"
            :aria-expanded="open"
            aria-haspopup="true"
            :aria-label="ariaLabel"
            @click.stop="open = !open"
        >
            ?
        </button>
        <Teleport to="body">
            <div
                v-if="open"
                class="fixed inset-0 z-[200]"
                aria-hidden="true"
                @click="open = false"
            />
        </Teleport>
        <Transition name="hint-pop">
            <div
                v-if="open"
                class="absolute left-0 top-full z-[210] mt-2 w-[min(18rem,calc(100vw-2rem))] rounded-xl border border-slate-200/90 bg-white p-3 text-xs font-medium leading-relaxed text-slate-700 shadow-xl shadow-slate-900/15 ring-1 ring-slate-100 sm:left-auto sm:right-0"
                role="tooltip"
            >
                <slot>{{ text }}</slot>
                <a
                    v-if="learnMoreUrl"
                    :href="learnMoreUrl"
                    class="mt-2 inline-flex text-xs font-bold text-primary-700 underline-offset-2 hover:underline"
                >
                    Learn more
                </a>
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { onMounted, onUnmounted } from 'vue';
import { ref } from 'vue';

const props = defineProps({
    text: {
        type: String,
        default: '',
    },
    learnMoreUrl: {
        type: String,
        default: '',
    },
    ariaLabel: {
        type: String,
        default: 'More information',
    },
});

const open = ref(false);

function onKey(e) {
    if (e.key === 'Escape') {
        open.value = false;
    }
}

onMounted(() => document.addEventListener('keydown', onKey));
onUnmounted(() => document.removeEventListener('keydown', onKey));
</script>

<style scoped>
.hint-pop-enter-active,
.hint-pop-leave-active {
    transition: opacity 0.15s ease, transform 0.15s ease;
}
.hint-pop-enter-from,
.hint-pop-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}
</style>
