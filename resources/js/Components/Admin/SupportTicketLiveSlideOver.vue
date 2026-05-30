<template>
    <AdminSlideOver :open="open" :title="ticket?.subject || 'Support ticket'" eyebrow="Support ticket detail" @close="$emit('close')">
        <div v-if="loading" class="text-sm font-semibold" :class="shell.cardMuted">Loading ticket details...</div>

        <div v-else-if="ticket" class="space-y-5">
            <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-full bg-indigo-100 px-3 py-1 text-[10px] font-black uppercase tracking-wide text-indigo-800">{{ ticket.ticket_reference }}</span>
                <span class="rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-wide" :class="statusClass(ticket.status)">{{ labelize(ticket.status) }}</span>
                <span class="rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-wide" :class="priorityClass(ticket.priority)">{{ ticket.priority }}</span>
            </div>

            <section class="rounded-2xl border p-4" :class="shell.card">
                <h3 class="text-xs font-black uppercase tracking-wide" :class="shell.cardMuted">Customer</h3>
                <p class="mt-2 text-sm font-black" :class="shell.cardTitle">{{ ticket.customer?.name || '—' }}</p>
                <p class="text-xs font-semibold" :class="shell.cardMuted">{{ ticket.customer?.email }}</p>
                <dl class="mt-3 grid gap-2 text-xs sm:grid-cols-2">
                    <div>
                        <dt class="font-black uppercase tracking-wide" :class="shell.cardMuted">Username</dt>
                        <dd class="mt-1 font-semibold" :class="shell.cardTitle">{{ ticket.customer?.username || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="font-black uppercase tracking-wide" :class="shell.cardMuted">Role</dt>
                        <dd class="mt-1 font-semibold" :class="shell.cardTitle">{{ ticket.customer?.role || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="font-black uppercase tracking-wide" :class="shell.cardMuted">Account status</dt>
                        <dd class="mt-1 font-semibold" :class="shell.cardTitle">{{ ticket.customer?.status || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="font-black uppercase tracking-wide" :class="shell.cardMuted">Joined</dt>
                        <dd class="mt-1 font-semibold" :class="shell.cardTitle">{{ formatDate(ticket.customer?.joined_at) }}</dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-2xl border p-4" :class="shell.card">
                <h3 class="text-xs font-black uppercase tracking-wide" :class="shell.cardMuted">Issue</h3>
                <p class="mt-2 text-sm font-semibold" :class="shell.cardMuted">{{ ticket.issue_group_label }}</p>
                <div class="prose prose-sm mt-3 max-w-none" :class="shell.cardTitle" v-html="ticket.description" />
                <div v-if="ticket.internal_notes" class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 dark:border-amber-500/30 dark:bg-amber-500/10">
                    <p class="text-[10px] font-black uppercase tracking-wide text-amber-800 dark:text-amber-200">Internal notes</p>
                    <p class="mt-2 whitespace-pre-wrap text-sm font-semibold text-amber-950 dark:text-amber-100">{{ ticket.internal_notes }}</p>
                </div>
            </section>

            <section class="grid gap-3 sm:grid-cols-2">
                <article class="rounded-2xl border p-4" :class="shell.card">
                    <p class="text-[10px] font-black uppercase tracking-wide" :class="shell.cardMuted">Assignee</p>
                    <p class="mt-1 text-sm font-black" :class="shell.cardTitle">{{ ticket.assigned_admin?.name || 'Unassigned' }}</p>
                </article>
                <article class="rounded-2xl border p-4" :class="shell.card">
                    <p class="text-[10px] font-black uppercase tracking-wide" :class="shell.cardMuted">Expected resolution</p>
                    <p class="mt-1 text-sm font-black" :class="shell.cardTitle">{{ formatDate(ticket.expected_resolution_at) }}</p>
                </article>
            </section>

            <section v-if="!ticket.is_read_only" class="rounded-2xl border p-4 space-y-3" :class="shell.card">
                <h3 class="text-xs font-black uppercase tracking-wide" :class="shell.cardMuted">Reassign ticket</h3>
                <select v-model="reassignId" class="w-full rounded-xl border px-3 py-2.5 text-sm font-semibold" :class="shell.input">
                    <option :value="null">Select admin</option>
                    <option v-for="admin in assignableAdmins" :key="admin.id" :value="admin.id">{{ admin.name }}</option>
                </select>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="rounded-xl px-4 py-2 text-xs font-black uppercase" :class="shell.btnGhost" :disabled="processing" @click="assignToMe">Assign to me</button>
                    <button type="button" class="rounded-xl px-4 py-2 text-xs font-black uppercase" :class="shell.btnPrimary" :disabled="!reassignId || processing" @click="submitReassign">Reassign</button>
                </div>
            </section>

            <section v-if="!ticket.is_read_only" class="rounded-2xl border p-4 space-y-3" :class="shell.card">
                <h3 class="text-xs font-black uppercase tracking-wide" :class="shell.cardMuted">Update status</h3>
                <select v-model="statusValue" class="w-full rounded-xl border px-3 py-2.5 text-sm font-semibold" :class="shell.input">
                    <option v-for="status in statuses" :key="status" :value="status">{{ labelize(status) }}</option>
                </select>
                <textarea v-model="statusSummary" rows="3" class="w-full rounded-xl border px-3 py-2 text-sm font-semibold" :class="shell.input" placeholder="Status note or resolution summary" />
                <button type="button" class="w-full rounded-xl px-4 py-2.5 text-sm font-black uppercase" :class="shell.btnPrimary" :disabled="processing" @click="submitStatus">Update status</button>
            </section>

            <section v-if="ticket.activities?.length" class="rounded-2xl border p-4" :class="shell.card">
                <h3 class="text-xs font-black uppercase tracking-wide" :class="shell.cardMuted">Recent activity</h3>
                <ol class="mt-3 space-y-2">
                    <li v-for="activity in ticket.activities.slice(0, 6)" :key="activity.id" class="rounded-xl border px-3 py-2 text-sm" :class="shell.card">
                        <p class="font-bold" :class="shell.cardTitle">{{ activity.summary }}</p>
                        <p class="mt-1 text-xs font-semibold" :class="shell.cardMuted">{{ activity.actor?.name }} · {{ formatDate(activity.occurred_at) }}</p>
                    </li>
                </ol>
            </section>

            <a :href="route('admin.support-tickets.show', ticket.uuid)" class="inline-flex rounded-xl px-4 py-2 text-xs font-black uppercase" :class="shell.btnGhost">
                Open full ticket page
            </a>
        </div>
    </AdminSlideOver>
</template>

<script setup>
import { ref, watch } from 'vue';
import AdminSlideOver from '@/Components/Admin/AdminSlideOver.vue';
import { formatLeaveDateTime } from '@/utils/formatHumanDateTime';

const props = defineProps({
    open: { type: Boolean, default: false },
    ticket: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    assignableAdmins: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    currentUserId: { type: Number, default: null },
    shell: { type: Object, required: true },
});

const emit = defineEmits(['close', 'updated']);

const reassignId = ref(null);
const statusValue = ref('');
const statusSummary = ref('');
const processing = ref(false);

watch(() => props.ticket, (ticket) => {
    reassignId.value = ticket?.assigned_admin?.id ?? null;
    statusValue.value = ticket?.status ?? '';
    statusSummary.value = '';
}, { immediate: true });

function labelize(value) {
    return String(value || '').replace(/_/g, ' ');
}

function formatDate(value) {
    return value ? formatLeaveDateTime(value) : '—';
}

function statusClass(status) {
    return {
        open: 'bg-sky-100 text-sky-800',
        in_progress: 'bg-amber-100 text-amber-800',
        awaiting_customer: 'bg-violet-100 text-violet-800',
        resolved: 'bg-emerald-100 text-emerald-800',
        closed: 'bg-slate-200 text-slate-700',
    }[status] || 'bg-slate-100 text-slate-700';
}

function priorityClass(priority) {
    return {
        low: 'bg-slate-100 text-slate-700',
        medium: 'bg-sky-100 text-sky-800',
        high: 'bg-amber-100 text-amber-800',
        critical: 'bg-rose-100 text-rose-800',
    }[priority] || 'bg-slate-100 text-slate-700';
}

function assignToMe() {
    if (!props.currentUserId) {
        return;
    }
    reassignId.value = props.currentUserId;
    submitReassign();
}

async function submitReassign() {
    if (!props.ticket?.uuid || !reassignId.value || processing.value) {
        return;
    }

    processing.value = true;
    try {
        const { data } = await window.axios.post(
            route('admin.support-tickets.reassign', props.ticket.uuid),
            { assignee_id: Number(reassignId.value) },
            { headers: { Accept: 'application/json' } },
        );
        emit('updated', data.ticket);
    } finally {
        processing.value = false;
    }
}

async function submitStatus() {
    if (!props.ticket?.uuid || processing.value) {
        return;
    }

    processing.value = true;
    try {
        const { data } = await window.axios.patch(
            route('admin.support-tickets.status', props.ticket.uuid),
            { status: statusValue.value, summary: statusSummary.value },
            { headers: { Accept: 'application/json' } },
        );
        emit('updated', data.ticket);
        statusSummary.value = '';
    } finally {
        processing.value = false;
    }
}
</script>
