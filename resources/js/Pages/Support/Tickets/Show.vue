<template>
    <component :is="shellComponent" :title="ticket.ticket_reference || 'Support ticket'" subtitle="Ticket detail">
        <div class="mx-auto max-w-6xl space-y-6">
            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-primary-100 px-3 py-1 text-[10px] font-black uppercase tracking-wide text-primary-800">{{ ticket.ticket_reference }}</span>
                            <span class="rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-wide" :class="statusClass(ticket.status)">{{ labelize(ticket.status) }}</span>
                            <span class="rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-wide" :class="priorityClass(ticket.priority)">{{ ticket.priority }}</span>
                            <span v-if="ticket.sla_breached" class="rounded-full bg-rose-100 px-3 py-1 text-[10px] font-black uppercase text-rose-800">SLA breach</span>
                        <SlaCountdownBadge v-if="sla_clock" :clock="sla_clock" class="ml-2" />
                        </div>
                        <h2 v-if="!editing" class="mt-3 text-2xl font-black text-slate-950">{{ ticket.subject }}</h2>
                        <input v-else v-model="editForm.subject" type="text" class="mt-3 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-xl font-black text-slate-950" />
                        <p class="mt-2 text-sm font-semibold text-slate-600">{{ ticket.issue_group_label }} · {{ ticket.customer?.name || 'No customer' }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <Link :href="route(routeName('support-tickets.index'))" class="rounded-xl border border-slate-300 px-4 py-2.5 text-xs font-black uppercase text-slate-700">All tickets</Link>
                        <button v-if="!editing && !ticket.is_read_only" type="button" class="rounded-xl border border-primary-200 bg-primary-50 px-4 py-2.5 text-xs font-black uppercase text-primary-800" @click="startEdit">Edit</button>
                        <button v-if="editing" type="button" class="rounded-xl border border-slate-300 px-4 py-2.5 text-xs font-black uppercase text-slate-700" @click="cancelEdit">Cancel</button>
                        <button v-if="editing" type="button" class="rounded-xl bg-primary-700 px-4 py-2.5 text-xs font-black uppercase text-white disabled:opacity-60" :disabled="editForm.processing" @click="saveEdit">Save changes</button>
                        <button v-if="!editing && !ticket.is_read_only" type="button" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-2.5 text-xs font-black uppercase text-rose-800" @click="deleteOpen = true">Delete</button>
                    </div>
                </div>

                <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <article class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                        <p class="text-[10px] font-black uppercase tracking-wide text-slate-500">Customer</p>
                        <p class="mt-1 text-sm font-black text-slate-900">{{ ticket.customer?.name || '—' }}</p>
                        <p class="text-xs font-semibold text-slate-500">{{ ticket.customer?.email }}</p>
                    </article>
                    <article class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                        <p class="text-[10px] font-black uppercase tracking-wide text-slate-500">Assignee</p>
                        <p class="mt-1 text-sm font-black text-slate-900">{{ ticket.assigned_admin?.name || 'Unassigned' }}</p>
                    </article>
                    <article class="rounded-2xl border border-primary-100 bg-primary-50 p-4">
                        <p class="text-[10px] font-black uppercase tracking-wide text-primary-700">Expected resolution</p>
                        <p class="mt-1 text-sm font-black text-primary-950">{{ formatDate(ticket.expected_resolution_at) }}</p>
                    </article>
                    <article class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                        <p class="text-[10px] font-black uppercase tracking-wide text-slate-500">Opened</p>
                        <p class="mt-1 text-sm font-black text-slate-900">{{ formatDate(ticket.opened_at) }}</p>
                    </article>
                </div>
            </section>

            <section v-if="editing" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
                <h3 class="text-lg font-black text-slate-950">Edit ticket</h3>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-xs font-black uppercase text-slate-500">Issue group</label>
                        <select v-model="editForm.issue_group" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-semibold">
                            <option v-for="group in issueGroups" :key="group.key" :value="group.key">{{ group.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-black uppercase text-slate-500">Priority</label>
                        <select v-model="editForm.priority" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-semibold">
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="text-xs font-black uppercase text-slate-500">Description</label>
                    <QuestRichDescriptionEditor v-model="editForm.description" class="mt-2" />
                </div>
                <div>
                    <label class="text-xs font-black uppercase text-slate-500">Internal notes</label>
                    <textarea v-model="editForm.internal_notes" rows="4" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" />
                </div>
            </section>

            <section v-else class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
                <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="text-lg font-black text-slate-950">Issue description</h3>
                    <div class="prose prose-sm mt-4 max-w-none text-slate-700" v-html="ticket.description" />

                    <div v-if="ticket.internal_notes" class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-4">
                        <p class="text-xs font-black uppercase tracking-wide text-amber-800">Internal notes</p>
                        <p class="mt-2 whitespace-pre-wrap text-sm font-semibold text-amber-950">{{ ticket.internal_notes }}</p>
                    </div>
                </article>

                <aside class="space-y-4">
                    <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 class="text-sm font-black uppercase tracking-wide text-slate-500">Action checklist</h3>
                        <div class="mt-3 space-y-2">
                            <label v-for="item in actionItems" :key="item.id" class="flex items-start gap-3 rounded-xl border border-slate-100 p-3">
                                <input v-model="item.completed" type="checkbox" class="mt-1" :disabled="ticket.is_read_only" @change="saveActions" />
                                <span class="text-sm font-semibold" :class="item.completed ? 'text-slate-400 line-through' : 'text-slate-800'">{{ item.label }}</span>
                            </label>
                            <p v-if="!actionItems.length" class="text-sm font-semibold text-slate-500">No action items logged.</p>
                        </div>
                    </article>

                    <article v-if="!ticket.is_read_only" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm space-y-3">
                        <h3 class="text-sm font-black uppercase tracking-wide text-slate-500">Update status</h3>
                        <select v-model="statusForm.status" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold">
                            <option v-for="status in statuses" :key="status" :value="status">{{ labelize(status) }}</option>
                        </select>
                        <textarea v-model="statusForm.summary" rows="3" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" placeholder="Status note or resolution summary" />
                        <button type="button" class="w-full rounded-xl bg-primary-700 px-4 py-2.5 text-sm font-black text-white" @click="submitStatus">Update status</button>
                    </article>
                </aside>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-lg font-black text-slate-950">Comments & updates</h3>
                <div v-if="!ticket.is_read_only" class="mt-4 space-y-3">
                    <textarea v-model="commentForm.body" rows="4" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" placeholder="Add an internal or customer-facing update" />
                    <label class="flex items-center gap-2 text-sm font-semibold text-slate-700"><input v-model="commentForm.customer_facing" type="checkbox" /> Customer-facing (sends email)</label>
                    <button type="button" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-black text-white" @click="submitComment">Add comment</button>
                </div>

                <div v-if="internalComments.length" class="mt-6">
                    <h4 class="text-sm font-black uppercase tracking-wide text-slate-500">Internal comments</h4>
                    <div class="mt-3 space-y-3">
                        <article v-for="comment in internalComments" :key="comment.id" class="rounded-2xl border border-amber-100 bg-amber-50/60 p-4">
                            <p class="text-xs font-black uppercase tracking-wide text-slate-500">{{ comment.author_line }} · {{ formatDate(comment.created_at) }}</p>
                            <div class="prose prose-sm mt-2 max-w-none" v-html="comment.html || comment.body" />
                        </article>
                    </div>
                </div>

                <div v-if="customerComments.length" class="mt-6">
                    <h4 class="text-sm font-black uppercase tracking-wide text-slate-500">Customer updates</h4>
                    <div class="mt-3 space-y-3">
                        <article v-for="comment in customerComments" :key="comment.id" class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                            <p class="text-xs font-black uppercase tracking-wide text-slate-500">{{ comment.author_line }} · {{ formatDate(comment.created_at) }}</p>
                            <div class="prose prose-sm mt-2 max-w-none" v-html="comment.html || comment.body" />
                        </article>
                    </div>
                </div>

                <p v-if="!internalComments.length && !customerComments.length" class="mt-5 text-sm font-semibold text-slate-500">No comments yet.</p>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-lg font-black text-slate-950">Activity trail</h3>
                <ol class="mt-4 space-y-3">
                    <li v-for="activity in activityTrail" :key="activity.id" class="flex gap-3 rounded-2xl border border-slate-100 px-4 py-3">
                        <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-primary-600" />
                        <span class="min-w-0 flex-1">
                            <p class="text-sm font-black text-slate-900">{{ activity.summary }}</p>
                            <p class="mt-1 text-xs font-semibold text-slate-500">{{ activity.actor?.name }} · {{ labelize(activity.actor?.role || 'system') }} · {{ formatDate(activity.occurred_at) }}</p>
                        </span>
                    </li>
                </ol>
            </section>

            <section v-if="ticket.email_logs?.length" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-lg font-black text-slate-950">Customer emails</h3>
                <ul class="mt-4 divide-y divide-slate-100 text-sm">
                    <li v-for="log in ticket.email_logs" :key="log.id" class="py-3 font-semibold text-slate-700">{{ log.subject }} · {{ log.recipient_email }} · {{ formatDate(log.sent_at) }}</li>
                </ul>
            </section>
        </div>

        <div v-if="deleteOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4" @click.self="deleteOpen = false">
            <div class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl">
                <h3 class="text-xl font-black text-slate-950">Delete this ticket?</h3>
                <p class="mt-2 text-sm font-semibold text-slate-600">This permanently removes {{ ticket.ticket_reference }} and its activity trail. This cannot be undone.</p>
                <div class="mt-5 flex gap-2">
                    <button type="button" class="flex-1 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-black text-slate-700" @click="deleteOpen = false">Cancel</button>
                    <button type="button" class="flex-1 rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-black text-white disabled:opacity-60" :disabled="deleteForm.processing" @click="confirmDelete">Delete ticket</button>
                </div>
            </div>
        </div>
    </component>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminShell from '@/Layouts/AdminShell.vue';
import OperationsShell from '@/Layouts/OperationsShell.vue';
import QuestRichDescriptionEditor from '@/Components/Quests/QuestRichDescriptionEditor.vue';
import SlaCountdownBadge from '@/Components/Admin/SlaCountdownBadge.vue';
import { useFlashToastWatcher } from '@/composables/useFlashToast';
import { useOperationsToast } from '@/composables/useOperationsToast';
import { formatLeaveDateTime } from '@/utils/formatHumanDateTime';

const props = defineProps({
    ticket: { type: Object, required: true },
    issueGroups: { type: Array, default: () => [] },
    assignableAdmins: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    routePrefix: { type: String, default: 'admin' },
    isSuperAdmin: { type: Boolean, default: false },
    sla_clock: { type: Object, default: null },
});

const { toast } = useOperationsToast();

if (props.isSuperAdmin) {
    useFlashToastWatcher(toast);
}

const shellComponent = computed(() => (props.routePrefix === 'admin' ? AdminShell : OperationsShell));
const editing = ref(false);
const deleteOpen = ref(false);
const actionItems = ref(JSON.parse(JSON.stringify(props.ticket.action_items || [])));

const activityTrail = computed(() =>
    [...(props.ticket.activities || [])]
        .sort((left, right) => new Date(right.occurred_at).getTime() - new Date(left.occurred_at).getTime()),
);

const internalComments = computed(() =>
    [...(props.ticket.comments || [])]
        .filter((comment) => !comment.customer_facing)
        .sort((left, right) => new Date(right.created_at).getTime() - new Date(left.created_at).getTime()),
);

const customerComments = computed(() =>
    [...(props.ticket.comments || [])]
        .filter((comment) => comment.customer_facing)
        .sort((left, right) => new Date(right.created_at).getTime() - new Date(left.created_at).getTime()),
);

const editForm = useForm({
    subject: props.ticket.subject,
    issue_group: props.ticket.issue_group,
    priority: props.ticket.priority,
    description: props.ticket.description,
    internal_notes: props.ticket.internal_notes || '',
    action_items: props.ticket.action_items || [],
});

const statusForm = useForm({ status: props.ticket.status, summary: '' });
const commentForm = useForm({ body: '', customer_facing: false });
const deleteForm = useForm({});

watch(() => props.ticket, (ticket) => {
    actionItems.value = JSON.parse(JSON.stringify(ticket.action_items || []));
    editForm.defaults({
        subject: ticket.subject,
        issue_group: ticket.issue_group,
        priority: ticket.priority,
        description: ticket.description,
        internal_notes: ticket.internal_notes || '',
        action_items: ticket.action_items || [],
    }).reset();
}, { deep: true });

function routeName(name) {
    return `${props.routePrefix}.${name}`;
}

function labelize(value) {
    return String(value || '').replaceAll('_', ' ');
}

function formatDate(value) {
    return value ? formatLeaveDateTime(value) : '—';
}

function statusClass(status) {
    if (status === 'closed' || status === 'resolved') return 'bg-emerald-100 text-emerald-800';
    if (status === 'awaiting_customer') return 'bg-amber-100 text-amber-800';
    if (status === 'in_progress') return 'bg-sky-100 text-sky-800';
    return 'bg-slate-100 text-slate-700';
}

function priorityClass(priority) {
    if (priority === 'critical') return 'bg-rose-100 text-rose-800';
    if (priority === 'high') return 'bg-orange-100 text-orange-800';
    return 'bg-slate-100 text-slate-700';
}

function startEdit() {
    editing.value = true;
}

function cancelEdit() {
    editing.value = false;
    editForm.reset();
}

function superAdminFeedback(message, type = 'success') {
    if (props.isSuperAdmin) {
        toast(message, type);
    }
}

function saveEdit() {
    editForm.put(route(routeName('support-tickets.update'), props.ticket.uuid), {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = false;
        },
        onError: () => superAdminFeedback('Could not update ticket.', 'error'),
    });
}

function confirmDelete() {
    deleteForm.delete(route(routeName('support-tickets.destroy'), props.ticket.uuid));
}

function submitStatus() {
    statusForm.patch(route(routeName('support-tickets.status'), props.ticket.uuid), {
        preserveScroll: true,
        onSuccess: () => statusForm.reset('summary'),
        onError: () => superAdminFeedback('Could not update status.', 'error'),
    });
}

function submitComment() {
    commentForm.post(route(routeName('support-tickets.comments.store'), props.ticket.uuid), {
        preserveScroll: true,
        onSuccess: () => commentForm.reset('body', 'customer_facing'),
        onError: () => superAdminFeedback('Could not add comment.', 'error'),
    });
}

function saveActions() {
    router.patch(route(routeName('support-tickets.action-items'), props.ticket.uuid), { action_items: actionItems.value }, {
        preserveScroll: true,
        onError: () => superAdminFeedback('Could not update checklist.', 'error'),
    });
}
</script>
