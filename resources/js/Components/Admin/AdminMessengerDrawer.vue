<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-[90] flex justify-end bg-slate-900/50 backdrop-blur-sm"
            role="presentation"
            @click.self="emit('close')"
        >
            <aside
                class="flex h-full w-full max-w-2xl flex-col border-l border-slate-200 bg-white shadow-2xl dark:border-white/10 dark:bg-slate-950"
                role="dialog"
                aria-label="Direct messages"
            >
                <header class="flex shrink-0 items-center justify-between gap-3 border-b border-slate-200 px-4 py-3 dark:border-white/10">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-wide text-primary-700 dark:text-primary-300">Team messenger</p>
                        <h2 class="font-display text-lg font-black text-slate-950 dark:text-white">Direct messages</h2>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg px-3 py-2 text-sm font-bold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/10"
                        @click="emit('close')"
                    >
                        Close
                    </button>
                </header>
                <div class="min-h-0 flex-1 overflow-hidden p-3 sm:p-4">
                    <AdminMessengerWorkspace
                        :route-namespace="routeNamespace"
                        :event-prefix="eventPrefix"
                        @unread-changed="emit('unread-changed', $event)"
                    />
                </div>
            </aside>
        </div>
    </Teleport>
</template>

<script setup>
import AdminMessengerWorkspace from '@/Components/Admin/AdminMessengerWorkspace.vue';

defineProps({
    open: { type: Boolean, default: false },
    routeNamespace: { type: String, default: 'operations' },
    eventPrefix: { type: String, default: 'operations' },
});

const emit = defineEmits(['close', 'unread-changed']);
</script>
