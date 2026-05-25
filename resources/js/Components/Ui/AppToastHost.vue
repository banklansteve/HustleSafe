<template>
    <Teleport to="body">
        <div class="pointer-events-none fixed inset-x-0 bottom-0 z-[500] flex justify-center px-4 pb-6 sm:justify-end sm:px-6">
            <Transition
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="translate-y-3 opacity-0 scale-[0.98]"
                enter-to-class="translate-y-0 opacity-100 scale-100"
                leave-active-class="transition duration-200 ease-in"
                leave-from-class="translate-y-0 opacity-100"
                leave-to-class="translate-y-2 opacity-0"
            >
                <div
                    v-if="visible && message"
                    role="status"
                    class="pointer-events-auto w-full max-w-sm rounded-2xl border border-emerald-200/90 bg-white px-4 py-3.5 shadow-2xl shadow-emerald-900/10 ring-1 ring-emerald-100/80"
                >
                    <div class="flex items-start gap-3">
                        <span
                            class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 text-sm font-black text-white shadow-md shadow-emerald-900/20"
                            aria-hidden="true"
                        >
                            ✓
                        </span>
                        <p class="min-w-0 flex-1 text-sm font-bold leading-snug text-slate-900">
                            {{ message }}
                        </p>
                        <button
                            type="button"
                            class="shrink-0 rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                            aria-label="Dismiss"
                            @click="dismiss"
                        >
                            <span class="block text-lg leading-none">&times;</span>
                        </button>
                    </div>
                </div>
            </Transition>
        </div>
    </Teleport>
</template>

<script setup>
import { useFlashToastWatcher, useToastAutoHide } from '@/composables/useFlashToast';
import { ref } from 'vue';

const visible = ref(false);
const message = ref('');
const { present, dismiss } = useToastAutoHide(visible, message);

useFlashToastWatcher(present);
</script>
