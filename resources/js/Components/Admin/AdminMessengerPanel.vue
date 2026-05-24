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
                class="fixed inset-0 z-[90] flex justify-end bg-slate-900/50 backdrop-blur-sm"
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
                        class="flex h-full w-full max-w-md flex-col border-l border-slate-200 bg-white shadow-2xl dark:border-white/10 dark:bg-slate-950"
                        role="dialog"
                        aria-label="Staff activity"
                    >
                        <header class="flex shrink-0 items-center justify-between gap-3 border-b border-slate-200 px-4 py-3 dark:border-white/10">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-wide text-primary-700 dark:text-primary-300">Staff insight</p>
                                <h2 class="font-display text-lg font-black text-slate-950 dark:text-white">{{ staff?.name || 'Admin' }}</h2>
                            </div>
                            <button type="button" class="rounded-lg px-3 py-2 text-sm font-bold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/10" @click="emit('close')">
                                Close
                            </button>
                        </header>
                        <div class="min-h-0 flex-1 overflow-y-auto p-4">
                            <AdminStaffActivityPanel :staff-id="staff?.id" :staff-name="staff?.name" />
                        </div>
                    </aside>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import AdminStaffActivityPanel from '@/Components/Admin/AdminStaffActivityPanel.vue';

defineProps({
    open: { type: Boolean, default: false },
    staff: { type: Object, default: null },
});

const emit = defineEmits(['close']);
</script>
