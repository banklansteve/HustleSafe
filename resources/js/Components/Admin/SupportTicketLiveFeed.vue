<template>
    <div class="space-y-2">
        <div v-if="loading && !tickets.length" class="rounded-2xl border px-4 py-10 text-center" :class="shell.card">
            <p class="text-sm font-black" :class="shell.cardTitle">Loading support tickets...</p>
        </div>

        <div v-else-if="!tickets.length" class="rounded-2xl border px-4 py-10 text-center" :class="shell.card">
            <p class="text-sm font-black" :class="shell.cardTitle">No support tickets yet.</p>
            <p class="mt-1 text-xs font-semibold" :class="shell.cardMuted">Managed tickets created by staff admins will appear here.</p>
        </div>

        <template v-else>
            <article
                v-for="ticket in tickets"
                :key="ticket.uuid"
                class="cursor-pointer rounded-2xl border p-4 transition hover:-translate-y-0.5 hover:shadow-md"
                :class="[shell.card, ticket.sla_breached ? 'border-l-4 border-l-rose-500' : '']"
                @click="$emit('inspect', ticket)"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-indigo-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-indigo-800 dark:bg-indigo-500/20 dark:text-indigo-200">
                                {{ ticket.ticket_reference }}
                            </span>
                            <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wide" :class="statusClass(ticket.status)">
                                {{ labelize(ticket.status) }}
                            </span>
                            <span v-if="ticket.sla_breached" class="rounded-full bg-rose-100 px-2.5 py-1 text-[10px] font-black uppercase text-rose-800">SLA breach</span>
                        </div>
                        <p class="mt-2 font-bold leading-6" :class="shell.cardTitle">{{ ticket.subject }}</p>
                        <p class="mt-1 text-sm font-semibold" :class="shell.cardMuted">
                            {{ ticket.issue_group_label }} · {{ ticket.customer?.name || 'No customer' }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs font-black uppercase tracking-wide" :class="shell.cardMuted">Assignee</p>
                        <p class="mt-1 text-sm font-bold" :class="shell.cardTitle">{{ ticket.assigned_admin?.name || 'Unassigned' }}</p>
                        <time class="mt-2 block text-xs font-bold" :class="shell.cardMuted" :title="absoluteTime(ticket.opened_at)">
                            {{ ticket.opened_at_label }}
                        </time>
                    </div>
                </div>
            </article>
        </template>

        <button
            v-if="hasMore"
            type="button"
            class="w-full rounded-2xl px-4 py-3 text-sm font-black"
            :class="shell.btnGhost"
            :disabled="loading"
            @click="$emit('load-more')"
        >
            {{ loading ? 'Loading...' : 'Load older tickets' }}
        </button>
    </div>
</template>

<script setup>
defineProps({
    tickets: { type: Array, default: () => [] },
    hasMore: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    shell: { type: Object, required: true },
});

defineEmits(['inspect', 'load-more']);

function labelize(value) {
    return String(value || '').replace(/_/g, ' ');
}

function statusClass(status) {
    return {
        open: 'bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-200',
        in_progress: 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-200',
        awaiting_customer: 'bg-violet-100 text-violet-800 dark:bg-violet-500/20 dark:text-violet-200',
        resolved: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-200',
        closed: 'bg-slate-200 text-slate-700 dark:bg-white/10 dark:text-slate-300',
    }[status] || 'bg-slate-100 text-slate-700';
}

function absoluteTime(value) {
    return value ? new Date(value).toLocaleString('en-NG', { dateStyle: 'medium', timeStyle: 'short' }) : '';
}
</script>
