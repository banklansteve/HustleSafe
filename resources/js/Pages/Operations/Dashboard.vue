<template>
    <OperationsShell
        title="Staff Admin Dashboard"
        subtitle="Daily operational workspace for moderation, support, disputes, KYC, communications, and escalations."
    >
        <div class="space-y-6">
            <section class="rounded-[2rem] border border-primary-100 bg-gradient-to-r from-primary-50 via-white to-teal-50 p-5 shadow-sm ring-1 ring-primary-100 sm:p-7">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.25em] text-primary-700">Welcome back</p>
                        <h2 class="font-display mt-2 text-2xl font-black text-slate-950 sm:text-3xl">
                            {{ greeting }}, {{ payload.staff?.name || 'Staff Admin' }}
                        </h2>
                        <p class="mt-3 max-w-3xl text-sm font-semibold leading-relaxed text-slate-700">
                            Your workspace focuses on assigned work, open queues, user support, and escalation. Platform settings, revenue controls, mass broadcasts, and permanent account actions remain Super Admin-only.
                        </p>
                    </div>
                    <button type="button" class="inline-flex shrink-0 items-center justify-center rounded-xl bg-primary-700 px-5 py-3 text-sm font-black text-white shadow-md hover:bg-primary-800" @click="escalationOpen = true">
                        Escalate to Super Admin
                    </button>
                </div>
                <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <Link v-for="tile in payload.workload_tiles" :key="tile.key" :href="tile.href" class="rounded-2xl border bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md" :class="tileBorder(tile.tone)">
                        <p class="text-[10px] font-black uppercase tracking-wide text-slate-500">{{ tile.label }}</p>
                        <p class="mt-2 text-3xl font-black text-slate-950">{{ tile.value }}</p>
                        <p class="mt-2 text-xs font-semibold leading-relaxed text-slate-600">{{ tile.hint }}</p>
                    </Link>
                </div>
            </section>

            <section>
                <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-display text-xl font-black text-slate-950">Team operational workload</h2>
                        <p class="mt-1 text-sm font-semibold text-slate-600">Tap a tile to jump into that queue and self-assign work where appropriate.</p>
                    </div>
                    <a :href="route('operations.dashboard.export')" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-black uppercase tracking-wide text-slate-800 hover:bg-slate-50">
                        Export snapshot
                    </a>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <Link v-for="tile in payload.team_tiles" :key="tile.key" :href="tile.href" class="rounded-2xl border bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md" :class="tileBorder(tile.tone)">
                        <p class="text-[10px] font-black uppercase tracking-wide text-slate-500">{{ tile.label }}</p>
                        <p class="mt-2 text-3xl font-black text-slate-950">{{ tile.value }}</p>
                        <p class="mt-2 text-xs font-semibold leading-relaxed text-slate-600">{{ tile.hint }}</p>
                    </Link>
                </div>
            </section>

            <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
                <div class="rounded-[1.75rem] border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="font-display text-xl font-black text-slate-950">Main tasks and queues</h2>
                            <p class="mt-1 text-sm font-semibold text-slate-600">Start from your assigned work, then jump into the operational queues that need attention.</p>
                        </div>
                        <Link :href="route('operations.tasks.index')" class="rounded-xl bg-primary-700 px-4 py-2 text-xs font-black uppercase tracking-wide text-white">Open tasks</Link>
                    </div>
                    <div class="mt-5 grid gap-3 md:grid-cols-2">
                        <article v-for="task in payload.my_tasks" :key="task.id" class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                            <div class="flex items-center justify-between gap-2">
                                <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wide" :class="severityClass(task.priority)">{{ task.priority }}</span>
                                <span class="text-xs font-bold text-slate-500">{{ task.due_at || 'No due date' }}</span>
                            </div>
                            <p class="mt-2 font-black text-slate-950">{{ task.title }}</p>
                            <p class="mt-1 line-clamp-2 text-sm font-semibold text-slate-600">{{ task.description }}</p>
                        </article>
                        <p v-if="!payload.my_tasks?.length" class="rounded-2xl border border-dashed border-slate-200 p-5 text-center text-sm font-bold text-slate-500 md:col-span-2">No assigned tasks. Use the quick queues below to self-assign work.</p>
                    </div>
                    <div class="mt-5">
                        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-primary-700">Moderation tools</p>
                        <div class="mt-3 grid gap-3 md:grid-cols-2">
                            <Link
                                :href="route('operations.onboarding-quality.index')"
                                class="rounded-2xl border border-primary-100 bg-primary-50/70 p-4 shadow-sm hover:border-primary-200 hover:bg-primary-50"
                            >
                                <p class="font-black text-slate-950">Onboarding quality control</p>
                                <p class="mt-1 text-sm font-semibold text-slate-600">Review new signups within 48 hours for profile quality and authenticity.</p>
                            </Link>
                            <Link
                                :href="route('operations.onboarding-quality.flagged')"
                                class="rounded-2xl border border-amber-100 bg-amber-50/60 p-4 shadow-sm hover:border-amber-200"
                            >
                                <p class="font-black text-slate-950">Flagged profiles</p>
                                <p class="mt-1 text-sm font-semibold text-slate-600">Accounts flagged for monitoring — not bannable, but worth watching.</p>
                            </Link>
                        </div>
                    </div>
                    <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        <Link v-for="action in payload.quick_actions" :key="action.label" :href="action.href" class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm hover:border-primary-200 hover:bg-primary-50">
                            <p class="font-black text-slate-950">{{ action.label }}</p>
                            <p class="mt-1 text-sm font-semibold text-slate-600">{{ action.description }}</p>
                        </Link>
                    </div>
                </div>

                <aside class="space-y-5">
                    <section class="rounded-[1.75rem] border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h2 class="font-display text-lg font-black text-slate-950">Live feed</h2>
                                <p class="mt-1 text-xs font-semibold text-slate-600">Latest touch points.</p>
                            </div>
                            <Link :href="route('operations.support.index')" prefetch="false" class="rounded-xl bg-primary-700 px-3 py-2 text-[10px] font-black uppercase tracking-wide text-white hover:bg-primary-800">CS</Link>
                        </div>
                        <div class="mt-4 space-y-3">
                            <article v-for="event in payload.live_feed" :key="event.id" class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wide" :class="severityClass(event.severity)">{{ event.severity }}</span>
                                    <span class="text-xs font-bold text-slate-500">{{ event.category }}</span>
                                </div>
                                <p class="mt-2 text-sm font-black text-slate-950">{{ event.title }}</p>
                                <p class="mt-1 line-clamp-2 text-xs font-semibold text-slate-600">{{ event.summary || 'No summary provided.' }}</p>
                                <p class="mt-2 text-[11px] font-bold text-slate-400">{{ dateLabel(event.occurred_at) }}</p>
                            </article>
                            <p v-if="!payload.live_feed?.length" class="rounded-2xl border border-dashed border-slate-200 p-5 text-center text-sm font-bold text-slate-500">No feed events yet.</p>
                        </div>
                    </section>
                </aside>
            </section>

            <section class="rounded-[1.75rem] border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100">
                <h2 class="font-display text-xl font-black text-slate-950">My audit entries</h2>
                <div class="mt-4 space-y-2">
                    <div v-for="entry in payload.my_audit" :key="entry.id" class="flex flex-col gap-1 rounded-2xl bg-slate-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <p class="font-bold text-slate-900">{{ entry.action }} <span class="text-slate-500">{{ entry.subject }}</span></p>
                        <span class="text-xs font-bold text-slate-500">{{ dateLabel(entry.created_at) }}</span>
                    </div>
                    <p v-if="!payload.my_audit?.length" class="rounded-2xl border border-dashed border-slate-200 p-5 text-center text-sm font-bold text-slate-500">Your audit activity will appear here.</p>
                </div>
            </section>

            <form v-if="escalationOpen" class="fixed inset-0 z-50 flex items-end bg-slate-950/40 p-3 backdrop-blur-sm sm:items-center sm:justify-center" @submit.prevent="submitEscalation">
                <div class="w-full max-w-xl rounded-[2rem] bg-white p-5 shadow-2xl">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-black uppercase tracking-[0.2em] text-primary-700">Escalation</p>
                            <h3 class="font-display mt-1 text-xl font-black text-slate-950">Escalate to Super Admin</h3>
                            <p class="mt-1 text-sm font-semibold text-slate-600">Use this when you hit a permission boundary or need ruling confirmation.</p>
                        </div>
                        <button type="button" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-black text-slate-700" @click="escalationOpen = false">Close</button>
                    </div>
                    <div class="mt-5 space-y-3">
                        <input v-model="escalationForm.title" class="form-input" placeholder="Escalation title" />
                        <select v-model="escalationForm.priority" class="form-input">
                            <option value="medium">Medium priority</option>
                            <option value="high">High priority</option>
                            <option value="critical">Critical priority</option>
                            <option value="low">Low priority</option>
                        </select>
                        <textarea v-model="escalationForm.recommendation" class="form-input min-h-28" placeholder="Context, decision boundary, and your recommendation" />
                        <div class="grid gap-3 sm:grid-cols-2">
                            <input v-model="escalationForm.subject_type" class="form-input" placeholder="Subject type e.g. Quest" />
                            <input v-model="escalationForm.subject_id" type="number" min="1" class="form-input" placeholder="Subject ID (optional)" />
                        </div>
                    </div>
                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-black text-slate-700" @click="escalationOpen = false">Cancel</button>
                        <button type="submit" class="rounded-xl bg-primary-700 px-4 py-2.5 text-sm font-black text-white disabled:opacity-50" :disabled="escalationForm.processing">
                            {{ escalationForm.processing ? 'Sending...' : 'Send escalation' }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </OperationsShell>
</template>

<script setup>
import OperationsShell from '@/Layouts/OperationsShell.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    kpi: { type: Object, required: true },
    payload: { type: Object, required: true },
    generated_at: { type: String, required: true },
});

const escalationOpen = ref(false);
const escalationForm = useForm({
    subject_type: 'Operations',
    subject_id: '',
    title: '',
    recommendation: '',
    priority: 'medium',
    context_url: typeof window !== 'undefined' ? window.location.href : '',
});

const greeting = computed(() => {
    const hour = new Date().getHours();
    if (hour < 12) return 'Good morning';
    if (hour < 17) return 'Good afternoon';

    return 'Good evening';
});

const generatedAtLabel = computed(() => {
    try {
        return new Date(props.generated_at).toLocaleString('en-NG', {
            dateStyle: 'medium',
            timeStyle: 'short',
            timeZone: 'Africa/Lagos',
        });
    } catch {
        return props.generated_at;
    }
});

function tileBorder(tone) {
    return {
        primary: 'border-primary-100 ring-1 ring-primary-50',
        rose: 'border-rose-100 ring-1 ring-rose-50',
        sky: 'border-sky-100 ring-1 ring-sky-50',
        amber: 'border-amber-100 ring-1 ring-amber-50',
        orange: 'border-orange-100 ring-1 ring-orange-50',
        emerald: 'border-emerald-100 ring-1 ring-emerald-50',
        slate: 'border-slate-100 ring-1 ring-slate-50',
    }[tone] || 'border-slate-100 ring-1 ring-slate-50';
}

function severityClass(severity) {
    return {
        critical: 'bg-rose-100 text-rose-800 ring-1 ring-rose-200',
        high: 'bg-orange-100 text-orange-900 ring-1 ring-orange-200',
        medium: 'bg-amber-100 text-amber-900 ring-1 ring-amber-200',
        low: 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
        info: 'bg-sky-100 text-sky-800 ring-1 ring-sky-200',
    }[severity] || 'bg-slate-100 text-slate-700 ring-1 ring-slate-200';
}

function dateLabel(value) {
    if (!value) return '—';
    return new Intl.DateTimeFormat('en-NG', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}

function submitEscalation() {
    escalationForm.post(route('operations.escalations.store'), {
        preserveScroll: true,
        onSuccess: () => {
            escalationOpen.value = false;
            escalationForm.reset('title', 'recommendation', 'subject_id');
        },
    });
}
</script>

<style scoped>
.form-input {
    @apply w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 shadow-sm outline-none focus:border-primary-400 focus:ring-2 focus:ring-primary-100;
}
</style>
