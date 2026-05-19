<template>
    <OperationsShell title="My Tasks" subtitle="Everything assigned to you: flags, referrals, disputes, KYC follow-ups, support items, and escalations.">
        <div class="space-y-5">
            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <Link v-for="tile in summary" :key="tile.key" :href="tile.href" class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm ring-1 ring-slate-100">
                    <p class="text-[10px] font-black uppercase tracking-wide text-slate-500">{{ tile.label }}</p>
                    <p class="mt-2 text-3xl font-black text-slate-950">{{ tile.value }}</p>
                    <p class="mt-1 text-xs font-semibold text-slate-600">{{ tile.hint }}</p>
                </Link>
            </section>

            <section class="rounded-[1.75rem] border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100">
                <div class="space-y-3">
                    <article v-for="task in tasks.data" :key="task.id" class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full bg-primary-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-primary-800">{{ task.priority }}</span>
                                    <span class="text-xs font-bold text-slate-500">{{ task.source_type || 'General' }} {{ task.source_id ? `#${task.source_id}` : '' }}</span>
                                </div>
                                <h2 class="mt-2 font-display text-lg font-black text-slate-950">{{ task.title }}</h2>
                                <p class="mt-1 text-sm font-semibold text-slate-600">{{ task.description || 'No description provided.' }}</p>
                                <p class="mt-2 text-xs font-bold text-slate-500">Due {{ task.due_at || 'not set' }} · {{ task.status.replace('_', ' ') }}</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="action-button" @click="setStatus(task, 'in_progress')">Start</button>
                                <button type="button" class="primary-button" @click="setStatus(task, 'done')">Complete</button>
                            </div>
                        </div>
                    </article>
                    <p v-if="!tasks.data?.length" class="rounded-2xl border border-dashed border-slate-200 p-6 text-center text-sm font-bold text-slate-500">No tasks in this queue.</p>
                </div>
            </section>
        </div>
    </OperationsShell>
</template>

<script setup>
import OperationsShell from '@/Layouts/OperationsShell.vue';
import { Link, router } from '@inertiajs/vue3';

defineProps({
    tasks: { type: Object, required: true },
    summary: { type: Array, default: () => [] },
    quick: { type: String, default: '' },
});

function setStatus(task, status) {
    router.patch(route('operations.tasks.status', task.id), { status }, { preserveScroll: true });
}
</script>

<style scoped>
.action-button {
    @apply inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-black uppercase tracking-wide text-slate-800 hover:bg-slate-50;
}

.primary-button {
    @apply inline-flex items-center justify-center rounded-xl bg-primary-700 px-4 py-2 text-xs font-black uppercase tracking-wide text-white hover:bg-primary-800;
}
</style>
