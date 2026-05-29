<template>
    <Transition
        enter-active-class="transition-all duration-300 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-all duration-200 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div v-if="show" class="fixed inset-0 z-[80]">
            <div class="absolute inset-0 bg-slate-950/45 backdrop-blur-[1px]" @click="emit('cancel')" />
            <div class="relative flex min-h-full items-center justify-center p-4">
                <Transition
                    enter-active-class="transition-all duration-300 ease-out"
                    enter-from-class="opacity-0 translate-y-3 scale-[0.98]"
                    enter-to-class="opacity-100 translate-y-0 scale-100"
                    leave-active-class="transition-all duration-200 ease-in"
                    leave-from-class="opacity-100 translate-y-0 scale-100"
                    leave-to-class="opacity-0 translate-y-2 scale-[0.98]"
                >
                    <div v-if="show" class="w-full max-w-lg rounded-2xl border border-slate-200 bg-gradient-to-br from-white via-slate-50 to-primary-50/30 p-6 shadow-2xl">
                        <h3 class="text-lg font-black text-slate-900">{{ title }}</h3>
                        <p class="mt-2 text-sm font-semibold text-slate-600">{{ description }}</p>

                        <div class="mt-4">
                            <label v-if="showNote" class="mb-2 block text-xs font-black uppercase tracking-wide text-slate-600">{{ noteLabel }}</label>
                            <textarea
                                v-if="showNote"
                                :value="note"
                                rows="3"
                                class="w-full resize-none rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-200"
                                :placeholder="notePlaceholder"
                                @input="emit('update:note', $event.target.value)"
                            />
                        </div>

                        <div class="mt-5 flex flex-wrap justify-end gap-2">
                            <button
                                type="button"
                                class="rounded-full border border-slate-300 bg-white px-4 py-2.5 text-sm font-black text-slate-700"
                                :disabled="busy"
                                @click="emit('cancel')"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-full bg-primary-700 px-5 py-2.5 text-sm font-black text-white disabled:opacity-70"
                                :disabled="busy || (showNote && !String(note || '').trim())"
                                @click="emit('confirm')"
                            >
                                <span v-if="busy" class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                                <span>{{ confirmLabel }}</span>
                            </button>
                        </div>
                    </div>
                </Transition>
            </div>
        </div>
    </Transition>
</template>

<script setup>

defineProps({
    show: { type: Boolean, default: false },
    busy: { type: Boolean, default: false },
    title: { type: String, default: 'Confirm action' },
    description: { type: String, default: '' },
    confirmLabel: { type: String, default: 'Confirm' },
    showNote: { type: Boolean, default: false },
    note: { type: String, default: '' },
    noteLabel: { type: String, default: 'Note' },
    notePlaceholder: { type: String, default: 'Enter note...' },
});

const emit = defineEmits(['confirm', 'cancel', 'update:note']);
</script>
