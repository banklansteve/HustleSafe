<template>
    <AdminShell title="Support Tickets & Messaging Oversight">
        <div class="space-y-6">
            <section class="rounded-[2rem] border border-primary-100 bg-gradient-to-r from-primary-50 via-white to-sky-50 p-5 shadow-sm ring-1 ring-primary-100">
                <p class="text-xs font-black uppercase tracking-[0.25em] text-primary-700">Super Admin oversight</p>
                <h2 class="font-display mt-2 text-2xl font-black text-slate-950">Tickets, CS chat allocation, and bulk-message authorisations</h2>
                <p class="mt-2 max-w-4xl text-sm font-semibold leading-relaxed text-slate-700">
                    Review every staff support touch point, manage tickets without leaving the page, and approve bulk communications before dispatch.
                </p>
            </section>

            <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_24rem]">
                <div class="rounded-[1.75rem] border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100">
                    <h2 class="font-display text-xl font-black text-slate-950">All support tickets</h2>
                    <div class="mt-5 overflow-hidden rounded-2xl border border-slate-100">
                        <button
                            v-for="ticket in tickets.data"
                            :key="ticket.id"
                            type="button"
                            class="grid w-full gap-3 border-b border-slate-100 bg-white p-4 text-left transition last:border-b-0 hover:bg-primary-50/50 md:grid-cols-[1fr_auto]"
                            @click="selectedTicket = ticket"
                        >
                            <span>
                                <span class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-wide" :class="statusClass(ticket.status)">{{ labelize(ticket.status) }}</span>
                                    <span class="rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-wide" :class="priorityClass(ticket.priority)">{{ ticket.priority }}</span>
                                    <span class="text-xs font-bold text-slate-500">{{ ticket.age_hours }}h old</span>
                                </span>
                                <span class="mt-2 block font-black text-slate-950">{{ ticket.subject }}</span>
                                <span class="mt-1 block text-sm font-semibold text-slate-600">
                                    {{ ticket.customer?.name || 'No customer linked' }} · assigned to {{ ticket.assigned_admin || 'unassigned' }}
                                </span>
                            </span>
                            <span class="text-xs font-bold text-slate-500 md:text-right">Opened {{ dateLabel(ticket.opened_at) }}</span>
                        </button>
                        <p v-if="!tickets.data?.length" class="p-8 text-center text-sm font-bold text-slate-500">No tickets yet.</p>
                    </div>
                </div>

                <aside class="space-y-5">
                    <section class="rounded-[1.75rem] border border-amber-100 bg-amber-50 p-5 shadow-sm ring-1 ring-amber-100">
                        <h2 class="font-display text-xl font-black text-slate-950">Pending bulk approvals</h2>
                        <div class="mt-4 space-y-3">
                            <article v-for="request in bulkRequests" :key="request.id" class="rounded-2xl border border-amber-200 bg-white p-4">
                                <span class="rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-wide" :class="statusClass(request.status)">{{ labelize(request.status) }}</span>
                                <p class="mt-2 font-black text-slate-950">{{ request.subject }}</p>
                                <p class="mt-1 text-xs font-bold text-slate-500">{{ request.created_by }} · {{ request.recipients_count }} recipients · {{ request.channels?.join(', ') }}</p>
                                <p class="mt-2 line-clamp-3 text-sm font-semibold text-slate-600">{{ request.body }}</p>
                                <form v-if="request.status === 'pending_authorisation'" class="mt-3 space-y-2" @submit.prevent="approveBulk(request)">
                                    <textarea v-model="approvalNote" class="form-input min-h-20" placeholder="Optional approval note" />
                                    <button type="submit" class="w-full rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-black text-white" :disabled="approveForm.processing">
                                        Authorise & dispatch
                                    </button>
                                </form>
                            </article>
                            <p v-if="!bulkRequests?.length" class="text-sm font-bold text-slate-600">No bulk requests yet.</p>
                        </div>
                    </section>

                    <section class="rounded-[1.75rem] border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100">
                        <h2 class="font-display text-xl font-black text-slate-950">Chat assignment audit</h2>
                        <div class="mt-4 space-y-3">
                            <article v-for="chat in chatAssignments" :key="chat.id" class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                <p class="font-black text-slate-950">{{ chat.quest || 'Support conversation' }}</p>
                                <p class="mt-1 text-xs font-bold text-slate-500">Assigned to {{ chat.assigned_admin }} · {{ dateLabel(chat.assigned_at) }}</p>
                                <p class="mt-1 text-xs font-semibold text-slate-500">{{ chat.client?.name || 'Client' }} / {{ chat.freelancer?.name || 'Freelancer' }}</p>
                            </article>
                            <p v-if="!chatAssignments?.length" class="text-sm font-bold text-slate-500">No chat assignments yet.</p>
                        </div>
                    </section>
                </aside>
            </section>
        </div>

        <form v-if="selectedTicket" class="fixed inset-0 z-50 flex justify-end bg-slate-950/40 p-3 backdrop-blur-sm" @submit.prevent="submitStatus">
            <section class="flex h-full w-full max-w-xl flex-col rounded-[2rem] bg-white shadow-2xl">
                <header class="border-b border-slate-100 p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-black uppercase tracking-[0.2em] text-primary-700">Ticket #{{ selectedTicket.id }}</p>
                            <h2 class="font-display mt-1 text-xl font-black text-slate-950">{{ selectedTicket.subject }}</h2>
                            <p class="mt-1 text-sm font-semibold text-slate-600">{{ selectedTicket.customer?.name || 'No customer' }} · {{ selectedTicket.age_hours }}h old</p>
                        </div>
                        <button type="button" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-black text-slate-700" @click="selectedTicket = null">Close</button>
                    </div>
                </header>
                <div class="min-h-0 flex-1 overflow-y-auto p-5">
                    <p class="whitespace-pre-line text-sm font-semibold leading-relaxed text-slate-700">{{ selectedTicket.description }}</p>
                    <div class="mt-5 space-y-3">
                        <article v-for="message in selectedTicket.messages" :key="message.id" class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                            <p class="text-xs font-black uppercase tracking-wide text-slate-500">{{ message.sender }} · {{ dateLabel(message.created_at) }}</p>
                            <p class="mt-2 whitespace-pre-line text-sm font-semibold text-slate-700">{{ message.body }}</p>
                        </article>
                    </div>
                </div>
                <footer class="border-t border-slate-100 p-5">
                    <div class="grid gap-3">
                        <select v-model="statusForm.status" class="form-input">
                            <option value="open">Open</option>
                            <option value="waiting_on_customer">Waiting on customer</option>
                            <option value="waiting_on_internal">Waiting on internal</option>
                            <option value="in_review">In review</option>
                            <option value="resolved">Resolved</option>
                            <option value="closed">Closed</option>
                        </select>
                        <textarea v-model="statusForm.reason" class="form-input min-h-24" placeholder="Status update, detail, or reason" />
                        <button type="submit" class="rounded-xl bg-primary-700 px-4 py-3 text-sm font-black text-white disabled:opacity-50" :disabled="statusForm.processing">
                            {{ statusForm.processing ? 'Updating...' : 'Update status' }}
                        </button>
                    </div>
                </footer>
            </section>
        </form>
    </AdminShell>
</template>

<script setup>
import AdminShell from '@/Layouts/AdminShell.vue';
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    tickets: { type: Object, required: true },
    bulkRequests: { type: Array, default: () => [] },
    chatAssignments: { type: Array, default: () => [] },
});

const selectedTicket = ref(null);
const approvalNote = ref('');
const statusForm = useForm({ status: 'open', reason: '' });
const approveForm = useForm({ approval_note: '' });

watch(selectedTicket, (ticket) => {
    statusForm.status = ticket?.status || 'open';
    statusForm.reason = '';
});

function submitStatus() {
    if (!selectedTicket.value) return;
    statusForm.patch(route('admin.support-tickets.status', selectedTicket.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            selectedTicket.value = null;
            statusForm.reset('reason');
        },
    });
}

function approveBulk(request) {
    approveForm.approval_note = approvalNote.value;
    approveForm.post(route('admin.support-tickets.bulk-messages.approve', request.id), {
        preserveScroll: true,
        onSuccess: () => {
            approvalNote.value = '';
            approveForm.reset();
        },
    });
}

function dateLabel(value) {
    if (!value) return '—';
    return new Intl.DateTimeFormat('en-NG', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}

function labelize(value) {
    return String(value || '').replaceAll('_', ' ');
}

function statusClass(status) {
    return {
        open: 'bg-sky-100 text-sky-800 ring-1 ring-sky-200',
        pending_authorisation: 'bg-amber-100 text-amber-900 ring-1 ring-amber-200',
        approved: 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-200',
        dispatched: 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-200',
        waiting_on_customer: 'bg-amber-100 text-amber-900 ring-1 ring-amber-200',
        waiting_on_internal: 'bg-orange-100 text-orange-900 ring-1 ring-orange-200',
        in_review: 'bg-indigo-100 text-indigo-800 ring-1 ring-indigo-200',
        resolved: 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-200',
        closed: 'bg-slate-200 text-slate-700 ring-1 ring-slate-300',
    }[status] || 'bg-slate-100 text-slate-700 ring-1 ring-slate-200';
}

function priorityClass(priority) {
    return {
        critical: 'bg-rose-100 text-rose-800 ring-1 ring-rose-200',
        high: 'bg-orange-100 text-orange-900 ring-1 ring-orange-200',
        medium: 'bg-amber-100 text-amber-900 ring-1 ring-amber-200',
        low: 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
    }[priority] || 'bg-slate-100 text-slate-700 ring-1 ring-slate-200';
}
</script>

<style scoped>
.form-input {
    @apply w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 shadow-sm outline-none focus:border-primary-400 focus:ring-2 focus:ring-primary-100;
}
</style>
