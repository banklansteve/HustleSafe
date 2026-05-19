<template>
    <OperationsShell title="Messaging, CS Chat & Tickets" subtitle="Contact users, manage assigned support chats, create tickets, and request Super Admin approval for bulk messages.">
        <div
            v-if="!support_tables_ready"
            class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-950"
            role="alert"
        >
            Support ticketing tables are not available yet. Ask a developer to run database migrations, then refresh this page.
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
            <main class="space-y-6">
                <section class="rounded-[2rem] border border-primary-100 bg-gradient-to-r from-primary-50 via-white to-sky-50 p-5 shadow-sm ring-1 ring-primary-100">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <p class="text-xs font-black uppercase tracking-[0.25em] text-primary-700">Customer support queue</p>
                            <h2 class="font-display mt-2 text-2xl font-black text-slate-950">Assigned chats</h2>
                            <p class="mt-2 max-w-3xl text-sm font-semibold leading-relaxed text-slate-700">
                                New customer-support chats are assigned round-robin to logged-in staff admins and logged with admin identity and timestamps.
                            </p>
                        </div>
                        <button type="button" class="rounded-xl bg-primary-700 px-4 py-2.5 text-sm font-black text-white" @click="openTicket()">
                            Open manual ticket
                        </button>
                    </div>
                </section>

                <section class="grid gap-3 md:grid-cols-2">
                    <article v-for="chat in assignedChats" :key="chat.id" class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-sky-100 px-3 py-1 text-[10px] font-black uppercase tracking-wide text-sky-800">{{ chat.messages_count || 0 }} messages</span>
                            <span class="rounded-full bg-emerald-100 px-3 py-1 text-[10px] font-black uppercase tracking-wide text-emerald-800">Assigned {{ dateLabel(chat.assigned_at) }}</span>
                        </div>
                        <h3 class="mt-3 font-display text-lg font-black text-slate-950">{{ chat.quest || 'Support conversation' }}</h3>
                        <p class="mt-1 text-sm font-bold text-slate-600">{{ chat.client?.name || 'Client' }} ↔ {{ chat.freelancer?.name || 'Freelancer' }}</p>
                        <p class="mt-2 text-xs font-bold text-slate-500">Last message {{ dateLabel(chat.last_message_at) }}</p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button type="button" class="rounded-xl bg-primary-700 px-4 py-2 text-xs font-black uppercase text-white hover:bg-primary-800" @click="openTicket(chat)">
                                Create ticket
                            </button>
                            <button type="button" class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-black uppercase text-slate-700 hover:bg-slate-50" @click="openContact(chat.client || chat.freelancer)">
                                Contact user
                            </button>
                        </div>
                    </article>
                    <EmptyState v-if="!assignedChats?.length" message="No chats are currently assigned to you." />
                </section>

                <section class="rounded-[1.75rem] border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100">
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h2 class="font-display text-xl font-black text-slate-950">My support tickets</h2>
                            <p class="mt-1 text-sm font-semibold text-slate-600">Color-coded by status, with age, details, audit trail, and slide-in status updates.</p>
                        </div>
                    </div>
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
                                <span class="mt-1 block text-sm font-semibold text-slate-600">{{ ticket.customer?.name || 'No customer linked' }} · {{ ticket.category }}</span>
                            </span>
                            <span class="text-xs font-bold text-slate-500 md:text-right">
                                Opened {{ dateLabel(ticket.opened_at) }}
                            </span>
                        </button>
                        <EmptyState v-if="!tickets.data?.length" message="No tickets opened or assigned to you yet." />
                    </div>
                </section>
            </main>

            <aside class="space-y-6">
                <section class="rounded-[1.75rem] border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100">
                    <h2 class="font-display text-xl font-black text-slate-950">Contact user</h2>
                    <p class="mt-1 text-sm font-semibold text-slate-600">Open a trackable support ticket from a user contact or reply context.</p>
                    <form class="mt-4 space-y-3" @submit.prevent="submitTicket">
                        <input v-model="ticketForm.user_id" class="form-input" type="number" min="1" placeholder="User ID" />
                        <input v-model="ticketForm.subject" class="form-input" placeholder="Subject" />
                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                            <select v-model="ticketForm.category" class="form-input">
                                <option value="general">General</option>
                                <option value="account">Account</option>
                                <option value="quest">Quest</option>
                                <option value="payment">Payment</option>
                                <option value="dispute">Dispute</option>
                            </select>
                            <select v-model="ticketForm.priority" class="form-input">
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                        <textarea v-model="ticketForm.description" class="form-input min-h-28" placeholder="Message, reply, or issue details" />
                        <button type="submit" class="w-full rounded-xl bg-primary-700 px-4 py-3 text-sm font-black text-white disabled:opacity-50" :disabled="ticketForm.processing">
                            {{ ticketForm.processing ? 'Opening...' : 'Open ticket and notify customer' }}
                        </button>
                    </form>
                </section>

                <section class="rounded-[1.75rem] border border-amber-100 bg-amber-50 p-5 shadow-sm ring-1 ring-amber-100">
                    <h2 class="font-display text-xl font-black text-slate-950">Bulk message request</h2>
                    <p class="mt-1 text-sm font-semibold text-slate-700">Bulk in-app/mail messages are held until a Super Admin authorises them.</p>
                    <form class="mt-4 space-y-3" @submit.prevent="submitBulk">
                        <select v-model="bulkForm.audience" class="form-input">
                            <option value="all_users">All users</option>
                            <option value="clients">Clients</option>
                            <option value="freelancers">Freelancers</option>
                            <option value="verified_users">Verified users</option>
                        </select>
                        <div class="flex flex-wrap gap-2">
                            <label class="inline-flex items-center gap-2 rounded-xl border border-amber-200 bg-white px-3 py-2 text-xs font-black text-slate-700">
                                <input v-model="bulkForm.channels" type="checkbox" value="mail" /> Mail
                            </label>
                            <label class="inline-flex items-center gap-2 rounded-xl border border-amber-200 bg-white px-3 py-2 text-xs font-black text-slate-700">
                                <input v-model="bulkForm.channels" type="checkbox" value="in_app" /> In-app
                            </label>
                        </div>
                        <input v-model="bulkForm.subject" class="form-input" placeholder="Subject" />
                        <textarea v-model="bulkForm.body" class="form-input min-h-32" placeholder="Message body" />
                        <button type="submit" class="w-full rounded-xl bg-primary-700 px-4 py-3 text-sm font-black text-white hover:bg-primary-800 disabled:opacity-50" :disabled="bulkForm.processing || !support_tables_ready">
                            {{ bulkForm.processing ? 'Submitting...' : 'Request Super Admin approval' }}
                        </button>
                    </form>
                </section>

                <section class="rounded-[1.75rem] border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100">
                    <h2 class="font-display text-xl font-black text-slate-950">My bulk requests</h2>
                    <div class="mt-4 space-y-3">
                        <article v-for="request in bulkRequests" :key="request.id" class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                            <span class="rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-wide" :class="statusClass(request.status)">{{ labelize(request.status) }}</span>
                            <p class="mt-2 font-black text-slate-950">{{ request.subject }}</p>
                            <p class="mt-1 text-xs font-bold text-slate-500">{{ request.recipients_count }} recipients · {{ request.channels?.join(', ') }}</p>
                        </article>
                        <p v-if="!bulkRequests?.length" class="text-sm font-bold text-slate-500">No bulk requests yet.</p>
                    </div>
                </section>
            </aside>
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
                    <div class="flex flex-wrap gap-2">
                        <span class="rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-wide" :class="statusClass(selectedTicket.status)">{{ labelize(selectedTicket.status) }}</span>
                        <span class="rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-wide" :class="priorityClass(selectedTicket.priority)">{{ selectedTicket.priority }}</span>
                    </div>
                    <p class="mt-4 whitespace-pre-line text-sm font-semibold leading-relaxed text-slate-700">{{ selectedTicket.description }}</p>
                    <div class="mt-5 space-y-3">
                        <article v-for="message in selectedTicket.messages" :key="message.id" class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-xs font-black uppercase tracking-wide text-slate-500">{{ message.sender }} · {{ message.sender_type }}</p>
                                <p class="text-xs font-bold text-slate-400">{{ dateLabel(message.created_at) }}</p>
                            </div>
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
    </OperationsShell>
</template>

<script setup>
import EmptyState from '@/Pages/Operations/Shared/EmptyState.vue';
import OperationsShell from '@/Layouts/OperationsShell.vue';
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    threads: { type: Object, required: true },
    assignedChats: { type: Array, default: () => [] },
    tickets: { type: Object, required: true },
    bulkRequests: { type: Array, default: () => [] },
    support_tables_ready: { type: Boolean, default: true },
});

const selectedTicket = ref(null);
const ticketForm = useForm({
    user_id: '',
    quest_conversation_thread_id: '',
    subject: '',
    category: 'general',
    priority: 'medium',
    description: '',
});
const bulkForm = useForm({
    audience: 'all_users',
    channels: ['mail'],
    subject: '',
    body: '',
});
const statusForm = useForm({
    status: 'open',
    reason: '',
});

watch(selectedTicket, (ticket) => {
    statusForm.status = ticket?.status || 'open';
    statusForm.reason = '';
});

function openTicket(chat = null) {
    ticketForm.reset();
    ticketForm.category = 'general';
    ticketForm.priority = 'medium';
    if (chat) {
        ticketForm.quest_conversation_thread_id = chat.thread_id || '';
        ticketForm.user_id = chat.client?.id || chat.freelancer?.id || '';
        ticketForm.subject = `Support follow-up: ${chat.quest || 'Customer chat'}`;
        ticketForm.description = `Created from assigned support chat #${chat.thread_id}.`;
    }
}

function openContact(user) {
    ticketForm.reset();
    ticketForm.category = 'general';
    ticketForm.priority = 'medium';
    ticketForm.user_id = user?.id || '';
    ticketForm.subject = user ? `Support contact for ${user.name}` : '';
}

function submitTicket() {
    ticketForm.post(route('operations.communications.tickets.store'), {
        preserveScroll: true,
        onSuccess: () => ticketForm.reset(),
    });
}

function submitBulk() {
    bulkForm.post(route('operations.communications.bulk-messages.store'), {
        preserveScroll: true,
        onSuccess: () => bulkForm.reset('subject', 'body'),
    });
}

function submitStatus() {
    if (!selectedTicket.value) return;

    statusForm.patch(route('operations.communications.tickets.status', selectedTicket.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            selectedTicket.value = null;
            statusForm.reset('reason');
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
