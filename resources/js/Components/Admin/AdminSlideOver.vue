<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="fixed inset-0 z-[80] flex justify-end bg-slate-900/40 backdrop-blur-sm"
                role="presentation"
                @click.self="emit('close')"
            >
                <Transition
                    enter-active-class="transition duration-250 ease-out"
                    enter-from-class="translate-x-full"
                    enter-to-class="translate-x-0"
                    leave-active-class="transition duration-200 ease-in"
                    leave-from-class="translate-x-0"
                    leave-to-class="translate-x-full"
                >
                    <aside
                        v-if="open"
                        class="flex h-full w-full max-w-md flex-col border-l shadow-2xl sm:max-w-lg"
                        :class="[shell.card, 'border-l shadow-2xl']"
                        role="dialog"
                        :aria-label="title"
                    >
                        <header
                            class="flex items-start justify-between gap-3 border-b px-5 py-4"
                            :class="shell.tableDivide"
                        >
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-primary-600 dark:text-primary-300">
                                    {{ eyebrow }}
                                </p>
                                <h2 class="font-display text-lg font-bold" :class="shell.cardTitle">
                                    {{ title }}
                                </h2>
                            </div>
                            <button
                                type="button"
                                class="rounded-lg p-2 text-sm font-bold transition"
                                :class="shell.cardMuted"
                                @click="emit('close')"
                            >
                                Close
                            </button>
                        </header>
                        <div class="flex-1 overflow-y-auto px-5 py-4">
                            <slot />
                        </div>
                    </aside>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';

defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: 'Details' },
    eyebrow: { type: String, default: 'Profile' },
});

const emit = defineEmits(['close']);

const { shell } = useInjectedAdminTheme();
</script>
