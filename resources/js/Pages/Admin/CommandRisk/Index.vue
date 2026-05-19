<template>
    <AdminShell :title="pageTitle" :subtitle="pageSubtitle">
        <div class="space-y-5">
            <section v-if="mode === 'notifications'" class="space-y-5">
                <CriticalBanner v-for="alert in payload.critical_alerts" :key="alert.id" :alert="alert" />
                <div class="grid gap-3 md:grid-cols-3">
                    <MetricCard label="Unread alerts" :value="payload.summary.unread" empty="0 / You currently have no unread admin alerts." />
                    <MetricCard label="Critical alerts" :value="payload.summary.critical" empty="0 / No critical alerts require action." tone="rose" />
                    <MetricCard label="Snoozed alerts" :value="payload.summary.snoozed" empty="0 / No alerts are snoozed." tone="amber" />
                </div>
                <Panel title="Notification inbox" description="Categorised operational alerts with priority, read state and direct actions.">
                    <div class="flex justify-end">
                        <button type="button" class="btn-secondary" @click="router.post(route('admin.alerts.read-all'))">Mark all read</button>
                    </div>
                    <EmptyState v-if="!payload.items.length" title="0 / No admin notifications yet" body="Critical disputes, payout failures, verification escalations and system alerts will appear here." />
                    <div v-else class="mt-3 space-y-3">
                        <article v-for="item in payload.items" :key="item.id" class="rounded-2xl border p-4" :class="[surfaceBorder, item.read ? 'opacity-70' : '']">
                            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <Badge :label="item.category" />
                                        <Badge :label="item.priority" :tone="item.priority === 'critical' ? 'rose' : 'primary'" />
                                    </div>
                                    <h3 class="mt-2 font-display text-lg font-black" :class="titleClass">{{ item.title }}</h3>
                                    <p class="mt-1 text-sm font-semibold" :class="mutedClass">{{ item.body || 'No extra context provided.' }}</p>
                                </div>
                                <div class="flex gap-2">
                                    <button v-if="!item.read" type="button" class="btn-secondary" @click="router.post(route('admin.alerts.read', item.id))">Read</button>
                                    <button type="button" class="btn-primary" @click="router.post(route('admin.alerts.action', item.id))">{{ item.action_label || 'Action' }}</button>
                                </div>
                            </div>
                        </article>
                    </div>
                </Panel>
                <Panel title="Notification preferences" description="Category-level routing for in-app, email and SMS alerts.">
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        <div v-for="pref in payload.preferences" :key="pref.category" class="rounded-2xl border p-4" :class="surfaceBorder">
                            <p class="font-black capitalize" :class="titleClass">{{ pref.category }}</p>
                            <p class="mt-2 text-xs font-bold" :class="mutedClass">In-app: {{ pref.in_app ? 'On' : 'Off' }} · Email: {{ pref.email ? 'On' : 'Off' }} · SMS: {{ pref.sms ? 'On' : 'Off' }}</p>
                        </div>
                    </div>
                </Panel>
            </section>

            <section v-else-if="mode === 'tasks'" class="space-y-5">
                <div class="grid gap-3 md:grid-cols-3">
                    <MetricCard label="My task inbox" :value="payload.summary.mine" empty="0 / You currently have no assigned open tasks." />
                    <MetricCard label="Overdue" :value="payload.summary.overdue" empty="0 / No overdue work is waiting." tone="rose" />
                    <MetricCard label="Team open tasks" :value="payload.summary.team_open" empty="0 / The team task board is clear." tone="amber" />
                </div>
                <Panel title="Create assignment" description="Turn any operational follow-up into a tracked task.">
                    <form class="grid gap-3 lg:grid-cols-[1fr_1fr_10rem_10rem_auto]" @submit.prevent="createTask">
                        <input v-model="taskForm.title" class="input" placeholder="Task title" required />
                        <select v-model="taskForm.assigned_to_admin_id" class="input">
                            <option value="">Unassigned</option>
                            <option v-for="admin in payload.admins" :key="admin.id" :value="admin.id">{{ admin.name }}</option>
                        </select>
                        <select v-model="taskForm.priority" class="input">
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                        <input v-model="taskForm.due_at" class="input" type="date" />
                        <button class="btn-primary" type="submit">Create</button>
                    </form>
                </Panel>
                <div class="grid gap-4 xl:grid-cols-3">
                    <Panel v-for="column in payload.columns" :key="column.status" :title="column.label" :description="`${column.items.length} task(s)`">
                        <EmptyState v-if="!column.items.length" :title="`0 / No ${column.label.toLowerCase()} tasks`" body="Tasks will appear here when assigned or moved into this stage." />
                        <div v-else class="space-y-3">
                            <article v-for="task in column.items" :key="task.id" class="rounded-2xl border p-4" :class="[surfaceBorder, task.overdue ? 'border-rose-300 bg-rose-50 dark:border-rose-500/40 dark:bg-rose-500/10' : '']">
                                <div class="flex justify-between gap-3">
                                    <h3 class="font-black" :class="titleClass">{{ task.title }}</h3>
                                    <Badge :label="task.priority" :tone="task.priority === 'critical' ? 'rose' : 'primary'" />
                                </div>
                                <p class="mt-2 text-sm" :class="mutedClass">{{ task.description || 'No additional description.' }}</p>
                                <p class="mt-2 text-xs font-bold" :class="mutedClass">Assignee: {{ task.assignee || 'Unassigned' }} · Due: {{ task.due_at || 'No due date' }}</p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <button v-for="status in taskStatuses" :key="status.value" type="button" class="btn-secondary" @click="setTaskStatus(task.id, status.value)">
                                        {{ status.label }}
                                    </button>
                                </div>
                            </article>
                        </div>
                    </Panel>
                </div>
            </section>

            <section v-else-if="mode === 'intelligence'" class="grid gap-5 xl:grid-cols-2">
                <Panel title="Freelancer intelligence profiles" description="Performance radar, earnings, win rate and churn risk signals.">
                    <EmptyState v-if="!payload.freelancers.length" title="0 / No freelancer intelligence yet" body="Freelancer profiles appear once freelancer accounts exist." />
                    <ProfileCard v-for="user in payload.freelancers" :key="user.id" :user="user" type="freelancer" />
                </Panel>
                <Panel title="Client intelligence profiles" description="Commercial value, posting patterns and rehire behaviour.">
                    <EmptyState v-if="!payload.clients.length" title="0 / No client intelligence yet" body="Client profiles appear once client accounts exist." />
                    <ProfileCard v-for="user in payload.clients" :key="user.id" :user="user" type="client" />
                </Panel>
            </section>

            <section v-else-if="mode === 'treasury'" class="space-y-5">
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <MetricCard v-for="tile in payload.tiles" :key="tile.label" :label="tile.label" :value="tile.value" :empty="tile.empty" />
                </div>
                <Panel title="Platform treasury health" :description="`Treasury status: ${payload.health}`">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="rounded-3xl bg-primary-50 p-5 dark:bg-primary-500/10">
                            <p class="text-xs font-black uppercase tracking-wide text-primary-700 dark:text-primary-200">Paystack bank balance</p>
                            <p class="mt-3 font-display text-3xl font-black" :class="titleClass">{{ payload.paystack_balance.value }}</p>
                            <p class="mt-2 text-sm font-semibold" :class="mutedClass">{{ payload.paystack_balance.note }}</p>
                        </div>
                        <EmptyState title="0 / No live bank sync yet" body="Add Paystack balance API credentials to turn this card into the CFO daily cash position view." />
                    </div>
                </Panel>
            </section>

            <section v-else-if="mode === 'fraud'" class="space-y-5">
                <Panel title="Real-time fraud alert feed" description="Automated and manually-created fraud cases.">
                    <EmptyState v-if="!payload.alerts.length" title="0 / No active fraud cases" body="Fraud alerts will appear when rules, disputes or manual reviews create a case." />
                    <div class="grid gap-3 md:grid-cols-2">
                        <article v-for="alert in payload.alerts" :key="alert.case_number" class="rounded-2xl border p-4" :class="surfaceBorder">
                            <Badge :label="alert.status" />
                            <h3 class="mt-2 font-black" :class="titleClass">{{ alert.case_number }} · {{ alert.risk_type }}</h3>
                            <p class="mt-1 text-sm" :class="mutedClass">{{ alert.summary }}</p>
                            <p class="mt-2 text-xs font-black text-rose-600">Risk score {{ alert.risk_score }}/100</p>
                        </article>
                    </div>
                </Panel>
                <div class="grid gap-5 xl:grid-cols-2">
                    <Panel title="Highest-risk accounts" description="Top 20 active accounts by computed risk signals.">
                        <EmptyState v-if="!payload.risk_leaderboard.length" title="0 / No risk-ranked accounts yet" body="Risk scores populate as accounts build platform history." />
                        <div v-for="user in payload.risk_leaderboard" :key="user.id" class="mb-3 rounded-2xl bg-slate-50 p-3 dark:bg-white/5">
                            <div class="flex justify-between gap-3">
                                <p class="font-black" :class="titleClass">{{ user.name }}</p>
                                <p class="font-black text-rose-600">{{ user.risk_score }}</p>
                            </div>
                            <p class="text-xs" :class="mutedClass">{{ user.email }} · {{ user.signals.join(', ') || 'No major signal' }}</p>
                        </div>
                    </Panel>
                    <Panel title="Risk rules" description="Configurable rules admins can tune without code changes.">
                        <div v-for="rule in payload.rules" :key="rule.id" class="mb-3 rounded-2xl border p-3" :class="surfaceBorder">
                            <div class="flex justify-between">
                                <p class="font-black" :class="titleClass">{{ rule.name }}</p>
                                <Badge :label="rule.is_active ? 'Active' : 'Paused'" />
                            </div>
                            <p class="text-xs" :class="mutedClass">{{ rule.description }}</p>
                        </div>
                    </Panel>
                </div>
            </section>

            <section v-else-if="mode === 'compliance'" class="space-y-5">
                <div class="grid gap-3 md:grid-cols-3">
                    <MetricCard label="Open requests" :value="payload.summary.open" empty="0 / No open NDPR requests." />
                    <MetricCard label="Due soon" :value="payload.summary.due_soon" empty="0 / No requests are due soon." tone="amber" />
                    <MetricCard label="Completed" :value="payload.summary.completed" empty="0 / No completed compliance requests yet." />
                </div>
                <Panel title="Open data governance request" description="Create user data export, deletion, access-log, or retention exception workflows.">
                    <form class="grid gap-3 lg:grid-cols-[1fr_14rem_1fr_auto]" @submit.prevent="createComplianceRequest">
                        <select v-model="complianceForm.user_id" class="input" required>
                            <option value="">Select user</option>
                            <option v-for="user in payload.users" :key="user.id" :value="user.id">{{ user.name }} · {{ user.email }}</option>
                        </select>
                        <select v-model="complianceForm.request_type" class="input">
                            <option value="data_export">Data export</option>
                            <option value="data_deletion">Data deletion</option>
                            <option value="access_log_review">Access log review</option>
                            <option value="retention_exception">Retention exception</option>
                        </select>
                        <input v-model="complianceForm.requester_note" class="input" placeholder="Request context" />
                        <button class="btn-primary" type="submit">Open</button>
                    </form>
                </Panel>
                <Panel title="Compliance requests" description="NDPR portability, deletion and access request log.">
                    <EmptyState v-if="!payload.requests.length" title="0 / No compliance requests yet" body="Create a data request above to begin tracking NDPR workflows." />
                    <div v-else class="space-y-3">
                        <article v-for="request in payload.requests" :key="request.id" class="rounded-2xl border p-4" :class="surfaceBorder">
                            <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                                <div>
                                    <Badge :label="request.type" />
                                    <h3 class="mt-2 font-black" :class="titleClass">{{ request.reference }} · {{ request.user || 'Unknown user' }}</h3>
                                    <p class="text-sm" :class="mutedClass">{{ request.email }} · Due {{ formatDate(request.due_at) }}</p>
                                </div>
                                <Badge :label="request.status" />
                            </div>
                        </article>
                    </div>
                </Panel>
                <Panel title="Retention policy matrix" description="Configured data retention periods and actions.">
                    <div class="grid gap-3 md:grid-cols-2">
                        <div v-for="row in payload.retention" :key="row.data" class="rounded-2xl border p-4" :class="surfaceBorder">
                            <p class="font-black" :class="titleClass">{{ row.data }}</p>
                            <p class="text-sm" :class="mutedClass">{{ row.period }} · {{ row.action }}</p>
                        </div>
                    </div>
                </Panel>
            </section>
        </div>
    </AdminShell>
</template>

<script setup>
import AdminShell from '@/Layouts/AdminShell.vue';
import { router, useForm } from '@inertiajs/vue3';
import { computed, defineComponent, h } from 'vue';

const props = defineProps({
    mode: { type: String, required: true },
    payload: { type: Object, required: true },
});

const pageMeta = {
    notifications: ['Notification & Alert Centre', 'Prioritised admin inbox for disputes, payments, verifications, flags, security and system alerts.'],
    tasks: ['Task & Assignment Management', 'Lightweight internal task board for admin ownership, due dates and team visibility.'],
    intelligence: ['Freelancer & Client Intelligence', 'Deep user profiles that turn platform history into proactive retention signals.'],
    treasury: ['Escrow Wallet & Platform Treasury', 'CFO-grade view of earned fees, pending collections, disbursements and treasury health.'],
    fraud: ['Fraud & Risk Intelligence', 'Aggregated risk alerts, account scoring, network signals and configurable fraud rules.'],
    compliance: ['Data Export & Compliance Centre', 'NDPR data governance workflows, access request logs and retention management.'],
};

const pageTitle = computed(() => pageMeta[props.mode]?.[0] || 'Command Centre');
const pageSubtitle = computed(() => pageMeta[props.mode]?.[1] || 'Admin operations workspace.');
const titleClass = 'text-slate-950 dark:text-white';
const mutedClass = 'text-slate-500 dark:text-slate-400';
const surfaceBorder = 'border-slate-200 bg-white dark:border-white/10 dark:bg-slate-900/60';
const taskStatuses = [
    { value: 'todo', label: 'To Do' },
    { value: 'in_progress', label: 'In Progress' },
    { value: 'done', label: 'Done' },
];

const taskForm = useForm({
    title: '',
    description: '',
    priority: 'medium',
    assigned_to_admin_id: '',
    due_at: '',
});

const complianceForm = useForm({
    user_id: '',
    request_type: 'data_export',
    requester_note: '',
});

function createTask() {
    taskForm.post(route('admin.tasks.store'), {
        preserveScroll: true,
        onSuccess: () => taskForm.reset('title', 'description', 'assigned_to_admin_id', 'due_at'),
    });
}

function setTaskStatus(id, status) {
    router.patch(route('admin.tasks.status', id), { status }, { preserveScroll: true });
}

function createComplianceRequest() {
    complianceForm.post(route('admin.compliance.requests.store'), {
        preserveScroll: true,
        onSuccess: () => complianceForm.reset('user_id', 'requester_note'),
    });
}

function formatDate(value) {
    return value ? new Intl.DateTimeFormat('en-NG', { dateStyle: 'medium' }).format(new Date(value)) : 'No due date';
}

const Panel = defineComponent({
    props: { title: String, description: String },
    setup(panelProps, { slots }) {
        return () => h('section', { class: 'rounded-3xl border border-slate-200 bg-white p-5 shadow-xl shadow-slate-200/50 ring-1 ring-slate-100 dark:border-white/10 dark:bg-slate-900/70 dark:shadow-black/20 dark:ring-white/5' }, [
            h('div', { class: 'mb-4' }, [
                h('h2', { class: 'font-display text-xl font-black text-slate-950 dark:text-white' }, panelProps.title),
                panelProps.description ? h('p', { class: 'mt-1 text-sm font-semibold text-slate-500 dark:text-slate-400' }, panelProps.description) : null,
            ]),
            slots.default?.(),
        ]);
    },
});

const MetricCard = defineComponent({
    props: { label: String, value: [String, Number], empty: String, tone: { type: String, default: 'primary' } },
    setup(cardProps) {
        return () => h('div', { class: 'rounded-3xl border border-slate-200 bg-white p-4 shadow-lg shadow-slate-200/50 dark:border-white/10 dark:bg-slate-900/70 dark:shadow-black/20' }, [
            h('p', { class: 'text-[10px] font-black uppercase tracking-[0.22em] text-slate-500' }, cardProps.label),
            h('p', { class: `mt-3 font-display text-3xl font-black ${cardProps.tone === 'rose' ? 'text-rose-600' : cardProps.tone === 'amber' ? 'text-amber-600' : 'text-primary-700 dark:text-primary-200'}` }, cardProps.value),
            (cardProps.value === 0 || cardProps.value === '₦0.00') && cardProps.empty ? h('p', { class: 'mt-2 text-xs font-bold text-slate-500' }, cardProps.empty) : null,
        ]);
    },
});

const EmptyState = defineComponent({
    props: { title: String, body: String },
    setup(emptyProps) {
        return () => h('div', { class: 'rounded-3xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center dark:border-white/10 dark:bg-white/5' }, [
            h('p', { class: 'font-display text-lg font-black text-slate-900 dark:text-white' }, emptyProps.title),
            h('p', { class: 'mx-auto mt-2 max-w-xl text-sm font-semibold text-slate-500 dark:text-slate-400' }, emptyProps.body),
        ]);
    },
});

const Badge = defineComponent({
    props: { label: String, tone: { type: String, default: 'primary' } },
    setup(badgeProps) {
        const cls = badgeProps.tone === 'rose'
            ? 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-100'
            : 'bg-primary-100 text-primary-700 dark:bg-primary-500/15 dark:text-primary-100';
        return () => h('span', { class: `inline-flex rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wide ${cls}` }, String(badgeProps.label || '').replaceAll('_', ' '));
    },
});

const CriticalBanner = defineComponent({
    props: { alert: Object },
    setup(bannerProps) {
        return () => h('div', { class: 'rounded-3xl border border-rose-300 bg-rose-50 p-4 text-rose-900 shadow-lg shadow-rose-200/50 dark:border-rose-500/40 dark:bg-rose-950/40 dark:text-rose-100' }, [
            h('p', { class: 'text-xs font-black uppercase tracking-[0.22em]' }, 'Critical alert - action required'),
            h('div', { class: 'mt-2 flex flex-col gap-3 md:flex-row md:items-center md:justify-between' }, [
                h('div', [h('h3', { class: 'font-display text-lg font-black' }, bannerProps.alert.title), h('p', { class: 'text-sm font-semibold' }, bannerProps.alert.body)]),
                h('button', { type: 'button', class: 'btn-danger', onClick: () => router.post(route('admin.alerts.action', bannerProps.alert.id)) }, bannerProps.alert.action_label || 'Action now'),
            ]),
        ]);
    },
});

const ProfileCard = defineComponent({
    props: { user: Object, type: String },
    setup(profileProps) {
        return () => h('article', { class: 'mb-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-white/10 dark:bg-white/5' }, [
            h('div', { class: 'flex items-start justify-between gap-3' }, [
                h('div', [h('p', { class: 'font-black text-slate-950 dark:text-white' }, profileProps.user.name), h('p', { class: 'text-xs font-semibold text-slate-500' }, profileProps.user.email)]),
                h(Badge, { label: profileProps.type === 'freelancer' ? profileProps.user.churn_risk : `Score ${profileProps.user.value_score}` }),
            ]),
            profileProps.type === 'freelancer'
                ? h('div', { class: 'mt-3 grid gap-2 sm:grid-cols-3' }, [
                    h('p', { class: 'text-xs font-bold text-slate-500' }, `Earnings: ${profileProps.user.earnings}`),
                    h('p', { class: 'text-xs font-bold text-slate-500' }, `Win rate: ${profileProps.user.win_rate}%`),
                    h('p', { class: 'text-xs font-bold text-slate-500' }, `Trust: ${profileProps.user.score}`),
                ])
                : h('div', { class: 'mt-3 grid gap-2 sm:grid-cols-3' }, [
                    h('p', { class: 'text-xs font-bold text-slate-500' }, `Posted: ${profileProps.user.posted}`),
                    h('p', { class: 'text-xs font-bold text-slate-500' }, `Value: ${profileProps.user.value}`),
                    h('p', { class: 'text-xs font-bold text-slate-500' }, `Avg: ${profileProps.user.avg_contract}`),
                ]),
        ]);
    },
});
</script>

<style scoped>
.input {
    @apply min-h-11 rounded-2xl border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-900 outline-none ring-2 ring-transparent focus:border-primary-500 focus:ring-primary-500/20 dark:border-white/10 dark:bg-slate-950 dark:text-white;
}

.btn-primary {
    @apply inline-flex min-h-11 items-center justify-center rounded-2xl bg-primary-600 px-4 text-xs font-black uppercase tracking-wide text-white shadow-lg shadow-primary-900/10 transition hover:bg-primary-700;
}

.btn-secondary {
    @apply inline-flex min-h-11 items-center justify-center rounded-2xl border border-slate-200 bg-white px-3 text-xs font-black uppercase tracking-wide text-slate-700 transition hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-200 dark:hover:bg-white/10;
}

.btn-danger {
    @apply inline-flex min-h-11 items-center justify-center rounded-2xl bg-rose-600 px-4 text-xs font-black uppercase tracking-wide text-white transition hover:bg-rose-700;
}
</style>
