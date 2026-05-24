<template>
    <Teleport to="body">
        <div class="pointer-events-none fixed inset-x-0 bottom-4 z-[200] flex flex-col items-center gap-2 px-4 sm:items-end sm:pr-6">
            <TransitionGroup
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="translate-y-2 opacity-0"
                enter-to-class="translate-y-0 opacity-100"
                leave-active-class="transition duration-200 ease-in"
                leave-from-class="translate-y-0 opacity-100"
                leave-to-class="translate-y-2 opacity-0"
            >
                <div
                    v-for="item in toasts"
                    :key="item.id"
                    role="status"
                    class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-2xl border px-4 py-3 text-sm font-bold shadow-xl"
                    :class="item.type === 'error' ? 'border-rose-200 bg-rose-50 text-rose-900' : 'border-emerald-200 bg-emerald-50 text-emerald-900'"
                >
                    <span class="min-w-0 flex-1">{{ item.message }}</span>
                    <button type="button" class="shrink-0 opacity-70 hover:opacity-100" @click="dismiss(item.id)">×</button>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>

<script setup>
import { useOperationsToast } from '@/composables/useOperationsToast';

const { toasts, dismiss } = useOperationsToast();
</script>
