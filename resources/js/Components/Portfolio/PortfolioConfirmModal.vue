<template>
    <Teleport to="body">
        <Transition name="fade">
            <div
                v-if="open"
                class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm"
                role="dialog"
                aria-modal="true"
                :aria-labelledby="titleId"
                @click.self="$emit('cancel')"
            >
                <div class="w-full max-w-md rounded-xl border border-slate-200 bg-white p-6 shadow-2xl ring-1 ring-slate-100">
                    <h2 :id="titleId" class="font-display text-lg font-bold text-slate-900">
                        {{ title }}
                    </h2>
                    <p class="mt-2 text-sm font-medium leading-relaxed text-slate-600">
                        {{ message }}
                    </p>
                    <div class="mt-6 flex flex-wrap justify-end gap-3">
                        <button
                            type="button"
                            class="rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                            @click="$emit('cancel')"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-lg bg-rose-600 px-4 py-2.5 text-sm font-bold text-white shadow-md transition hover:bg-rose-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500 disabled:opacity-60"
                            :disabled="processing"
                            @click="$emit('confirm')"
                        >
                            <ReLoader4Line v-if="processing" class="h-4 w-4 animate-spin" />
                            {{ confirmLabel }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ReLoader4Line } from '@kalimahapps/vue-icons/re';

defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: 'Are you sure?',
    },
    message: {
        type: String,
        default: '',
    },
    confirmLabel: {
        type: String,
        default: 'Delete',
    },
    processing: {
        type: Boolean,
        default: false,
    },
    titleId: {
        type: String,
        default: 'confirm-modal-title',
    },
});

defineEmits(['cancel', 'confirm']);
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
