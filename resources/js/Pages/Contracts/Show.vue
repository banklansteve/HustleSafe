<template>
    <AppShell>
        <Head :title="`Contract · ${contract.reference_code}`" />

        <div class="mx-auto max-w-4xl space-y-6 pb-10">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <BackChevronLink :href="contract.quest_url || route('contracts.index')" aria-label="Back" />
                <Link
                    :href="route('contracts.index')"
                    class="text-xs font-black uppercase tracking-wide text-primary-800 underline underline-offset-2"
                >
                    All contracts
                </Link>
            </div>

            <div
                v-if="contract.status === 'disputed'"
                class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-950 ring-1 ring-rose-100"
            >
                This contract is under dispute. Escrow is frozen while the case is reviewed.
                <Link v-if="contract.dispute_url" :href="contract.dispute_url" class="ml-1 font-black underline">Open dispute case</Link>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <section
                    v-if="role.is_client"
                    class="rounded-2xl border border-amber-200/90 bg-amber-50/90 p-4 ring-1 ring-amber-100 sm:p-5"
                >
                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-amber-900">Escrow held</p>
                    <p class="font-display mt-1 text-2xl font-black text-amber-950">{{ contract.financial.total_label }}</p>
                    <p class="mt-2 text-xs font-semibold leading-relaxed text-amber-950/90">
                        These funds are securely held and will only release when you mark the job complete or after the auto-release window expires.
                    </p>
                    <p v-if="disputeWindow.active" class="mt-2 text-xs font-bold text-amber-900">
                        Review window · {{ countdownLabel(disputeWindow.seconds_until_release) }} remaining
                    </p>
                </section>

                <section
                    v-if="role.is_freelancer"
                    class="rounded-2xl border border-emerald-200/90 bg-emerald-50/90 p-4 ring-1 ring-emerald-100 sm:p-5"
                >
                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-emerald-900">Your net payout</p>
                    <p class="font-display mt-1 text-2xl font-black text-emerald-950">{{ contract.financial.freelancer_net_label }}</p>
                    <p v-if="deliveryCountdown.active" class="mt-2 text-xs font-bold text-emerald-900">
                        Delivery deadline · {{ deliveryCountdown.deadline_label }} · {{ countdownLabel(deliveryCountdown.seconds_remaining) }} left
                    </p>
                    <p class="mt-2 text-xs font-semibold text-emerald-950/90">
                        Revisions · {{ contract.revisions_used }} of {{ contract.revisions_included }} used
                    </p>
                </section>
            </div>

            <header class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <HustleSafeLogo variant="lockup" theme="light" lockup-class="h-8 w-auto max-w-[10rem]" />
                        <p class="mt-3 text-[10px] font-black uppercase tracking-[0.22em] text-slate-500">Service agreement</p>
                        <h1 class="font-display mt-1 text-xl font-black text-slate-900 sm:text-2xl">{{ contract.reference_code }}</h1>
                    </div>
                    <span class="rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-wide ring-1" :class="statusClass(contract.status)">
                        {{ contract.status_label }}
                    </span>
                </div>
            </header>

            <article class="space-y-6 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-8">
                <section class="border-t border-slate-200 pt-6 first:border-t-0 first:pt-0">
                    <h2 class="font-display text-xs font-black uppercase tracking-[0.18em] text-slate-500">Parties</h2>
                    <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4">
                            <dt class="text-[10px] font-black uppercase tracking-wide text-slate-500">Client</dt>
                            <dd class="mt-1 text-sm font-bold text-slate-900">{{ contract.parties.client.full_name }}</dd>
                            <dd class="text-xs font-semibold text-slate-600">@{{ contract.parties.client.username }} · ID {{ contract.parties.client.user_id }}</dd>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-4">
                            <dt class="text-[10px] font-black uppercase tracking-wide text-slate-500">Freelancer</dt>
                            <dd class="mt-1 text-sm font-bold text-slate-900">{{ contract.parties.freelancer.full_name }}</dd>
                            <dd class="text-xs font-semibold text-slate-600">@{{ contract.parties.freelancer.username }} · ID {{ contract.parties.freelancer.user_id }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="border-t border-slate-200 pt-6">
                    <h2 class="font-display text-xs font-black uppercase tracking-[0.18em] text-slate-500">Quest details</h2>
                    <dl class="mt-4 space-y-2 text-sm font-semibold text-slate-800">
                        <div class="flex flex-wrap justify-between gap-2 border-b border-slate-100 py-2">
                            <dt class="text-slate-500">Title</dt>
                            <dd class="font-bold text-slate-900">{{ contract.quest.title }}</dd>
                        </div>
                        <div class="flex flex-wrap justify-between gap-2 border-b border-slate-100 py-2">
                            <dt class="text-slate-500">Reference</dt>
                            <dd>{{ contract.quest.reference_code }}</dd>
                        </div>
                        <div class="flex flex-wrap justify-between gap-2 border-b border-slate-100 py-2">
                            <dt class="text-slate-500">Category</dt>
                            <dd>{{ contract.quest.category || '—' }}</dd>
                        </div>
                    </dl>
                    <p class="mt-4 text-sm leading-relaxed text-slate-700">{{ contract.quest.scope_description }}</p>
                    <h3 class="mt-4 text-xs font-black uppercase tracking-wide text-slate-500">Deliverables</h3>
                    <ol class="mt-2 list-decimal space-y-2 pl-5 text-sm font-semibold text-slate-800">
                        <li v-for="(d, i) in contract.deliverables" :key="i">
                            {{ d.title }}
                            <span v-if="d.description" class="block text-xs font-medium text-slate-600">{{ d.description }}</span>
                        </li>
                    </ol>
                </section>

                <section class="border-t border-slate-200 pt-6">
                    <h2 class="font-display text-xs font-black uppercase tracking-[0.18em] text-slate-500">Financial terms</h2>
                    <dl class="mt-4 space-y-2 text-sm font-semibold text-slate-800">
                        <div class="flex justify-between border-b border-slate-100 py-2"><dt>Total contract value</dt><dd class="font-black">{{ contract.financial.total_label }}</dd></div>
                        <div class="flex justify-between border-b border-slate-100 py-2"><dt>Platform service fee</dt><dd>{{ contract.financial.platform_fee_label }} ({{ contract.financial.platform_fee_percent }}%)</dd></div>
                        <div class="flex justify-between border-b border-slate-100 py-2"><dt>Freelancer net payout</dt><dd class="font-black text-emerald-800">{{ contract.financial.freelancer_net_label }}</dd></div>
                    </dl>
                </section>

                <section class="border-t border-slate-200 pt-6">
                    <h2 class="font-display text-xs font-black uppercase tracking-[0.18em] text-slate-500">Timeline</h2>
                    <dl class="mt-4 space-y-2 text-sm font-semibold text-slate-800">
                        <div class="flex justify-between border-b border-slate-100 py-2"><dt>Contract generated</dt><dd>{{ formatWhen(contract.generated_at) }}</dd></div>
                        <div class="flex justify-between border-b border-slate-100 py-2"><dt>Contract start</dt><dd>{{ contract.activated_at ? formatWhen(contract.activated_at) : 'Pending escrow funding' }}</dd></div>
                        <div class="flex justify-between border-b border-slate-100 py-2"><dt>Agreed delivery date</dt><dd>{{ contract.timeline.agreed_delivery_label || '—' }}</dd></div>
                    </dl>
                    <p class="mt-3 rounded-xl border border-slate-100 bg-slate-50/80 p-3 text-xs font-semibold leading-relaxed text-slate-700">
                        {{ contract.timeline.auto_release_plain_english }}
                    </p>
                </section>

                <section class="border-t border-slate-200 pt-6">
                    <h2 class="font-display text-xs font-black uppercase tracking-[0.18em] text-slate-500">Revision policy</h2>
                    <p class="mt-4 text-sm font-semibold text-slate-800">{{ contract.revisions_included }} revisions included</p>
                    <p class="mt-2 text-sm leading-relaxed text-slate-700">{{ contract.revision_policy.revision_definition }}</p>
                </section>

                <section v-if="contract.amendments.length" class="border-t border-slate-200 pt-6">
                    <h2 class="font-display text-xs font-black uppercase tracking-[0.18em] text-slate-500">Amendment history</h2>
                    <div v-for="a in contract.amendments" :key="a.id" class="mb-4 mt-4 border-l-4 border-primary-400 pl-4">
                        <p class="text-xs font-black uppercase tracking-wide text-slate-500">Amendment #{{ a.amendment_number }} · {{ a.type_label }}</p>
                        <p class="mt-1 text-sm font-semibold text-slate-800">{{ a.description }}</p>
                        <p v-if="a.original_value || a.new_value" class="mt-1 text-xs text-slate-600">
                            Original: {{ a.original_value || '—' }} · New: {{ a.new_value || '—' }}
                        </p>
                    </div>
                </section>

                <section class="border-t border-slate-200 pt-6">
                    <h2 class="font-display text-xs font-black uppercase tracking-[0.18em] text-slate-500">Platform terms</h2>
                    <p class="mt-4 text-sm font-semibold text-slate-800">
                        <a :href="contract.platform_terms.terms_url" target="_blank" rel="noopener noreferrer" class="font-black text-primary-800 underline">Full Terms of Service</a>
                    </p>
                    <ul class="mt-3 list-disc space-y-2 pl-5 text-sm font-semibold text-slate-700">
                        <li v-for="(clause, i) in contract.platform_terms.clauses" :key="i">{{ clause }}</li>
                    </ul>
                </section>

                <section class="border-t border-slate-200 pt-6">
                    <h2 class="font-display text-xs font-black uppercase tracking-[0.18em] text-slate-500">Signatures</h2>
                    <div class="mt-4 grid gap-3 sm:grid-cols-3">
                        <div v-for="key in ['client', 'freelancer', 'platform']" :key="key" class="rounded-xl border border-slate-200 bg-slate-50/60 p-4">
                            <p class="text-[10px] font-black uppercase tracking-wide text-slate-500">{{ key }}</p>
                            <p class="mt-2 text-sm font-bold text-slate-900">{{ contract.signatures[key]?.name || '—' }}</p>
                            <p class="mt-1 text-xs font-semibold text-slate-600">{{ contract.signatures[key]?.action || '' }}</p>
                            <p class="mt-1 text-[10px] font-semibold text-slate-500">{{ formatWhen(contract.signatures[key]?.confirmed_at) }}</p>
                        </div>
                    </div>
                </section>
            </article>

            <section class="flex flex-wrap gap-3">
                <a
                    :href="route('contracts.pdf', contract.reference_code)"
                    class="inline-flex items-center rounded-full border-2 border-slate-200 bg-white px-4 py-2 text-xs font-black uppercase tracking-wide text-slate-800 shadow-sm transition hover:border-primary-200"
                >
                    Download PDF
                </a>
                <button
                    v-if="permissions.can_request_amendment"
                    type="button"
                    class="inline-flex items-center rounded-full bg-primary-700 px-4 py-2 text-xs font-black uppercase tracking-wide text-white hover:bg-primary-800"
                    @click="showAmendmentForm = true"
                >
                    Request amendment
                </button>
                <button
                    v-else-if="contract.amendment_count >= contract.amendment_limit && contract.status === 'active'"
                    type="button"
                    disabled
                    class="inline-flex cursor-not-allowed items-center rounded-full bg-slate-200 px-4 py-2 text-xs font-black uppercase tracking-wide text-slate-500"
                >
                    Amendment limit reached
                </button>
            </section>

            <section v-if="pending_amendment" class="rounded-2xl border border-sky-200 bg-sky-50/80 p-5 ring-1 ring-sky-100">
                <h2 class="font-display text-sm font-black uppercase tracking-wide text-sky-900">Amendment awaiting your response</h2>
                <p class="mt-2 text-sm font-semibold text-sky-950">{{ pending_amendment.type_label }} · {{ pending_amendment.description }}</p>
                <textarea v-if="showDeclineNote" v-model="respondForm.response_note" rows="3" class="mt-3 w-full rounded-xl border-slate-200 text-sm shadow-sm" placeholder="Mandatory note if declining" />
                <div class="mt-4 flex flex-wrap gap-2">
                    <button type="button" class="rounded-full bg-emerald-700 px-4 py-2 text-xs font-black uppercase text-white" :disabled="respondForm.processing" @click="respondAmendment('accept')">Accept</button>
                    <button type="button" class="rounded-full bg-rose-700 px-4 py-2 text-xs font-black uppercase text-white" :disabled="respondForm.processing" @click="respondAmendment('decline')">Decline</button>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6">
                <h2 class="font-display text-sm font-black uppercase tracking-wide text-slate-500">Contract timeline</h2>
                <ol class="mt-5 space-y-0">
                    <li v-for="(stage, i) in timeline_stages" :key="stage.key" class="relative flex gap-4 pb-8 last:pb-0">
                        <div class="flex flex-col items-center">
                            <span
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-black ring-2"
                                :class="stage.completed ? 'bg-primary-600 text-white ring-primary-600' : (stage.current ? 'bg-white text-primary-700 ring-primary-400' : 'bg-slate-100 text-slate-400 ring-slate-200')"
                            >{{ i + 1 }}</span>
                        </div>
                        <div class="min-w-0 pt-1">
                            <p class="text-sm font-bold" :class="stage.current ? 'text-primary-900' : 'text-slate-800'">{{ stage.label }}</p>
                            <p v-if="stage.at_label" class="text-xs font-semibold text-slate-500">{{ stage.at_label }}</p>
                        </div>
                    </li>
                </ol>
            </section>

            <section v-if="admin_panel" class="rounded-2xl border border-slate-200 bg-slate-50 p-5 ring-1 ring-slate-200 sm:p-6">
                <h2 class="font-display text-sm font-black uppercase tracking-wide text-slate-600">Admin panel</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-xl border border-slate-200 bg-white p-4 text-xs">
                        <p class="font-black uppercase text-slate-500">Client forensics</p>
                        <p class="mt-2 font-mono text-slate-700">IP: {{ admin_panel.parties_forensics.client.ip || '—' }}</p>
                        <p class="mt-1 break-all text-slate-600">{{ admin_panel.parties_forensics.client.user_agent || '—' }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-4 text-xs">
                        <p class="font-black uppercase text-slate-500">Freelancer forensics</p>
                        <p class="mt-2 font-mono text-slate-700">IP: {{ admin_panel.parties_forensics.freelancer.ip || '—' }}</p>
                        <p class="mt-1 break-all text-slate-600">{{ admin_panel.parties_forensics.freelancer.user_agent || '—' }}</p>
                    </div>
                </div>
                <form v-if="admin_panel.can_flag_for_review && !admin_panel.flagged_for_review" class="mt-4 space-y-2" @submit.prevent="submitFlag">
                    <textarea v-model="flagForm.reason" rows="3" class="w-full rounded-xl border-slate-200 text-sm shadow-sm" placeholder="Reason for financial review flag" required />
                    <button type="submit" class="rounded-full bg-rose-700 px-4 py-2 text-xs font-black uppercase text-white" :disabled="flagForm.processing">Flag contract</button>
                </form>
                <div class="mt-5 max-h-64 overflow-y-auto rounded-xl border border-slate-200 bg-white">
                    <table class="min-w-full text-left text-[11px] font-semibold text-slate-700">
                        <thead class="sticky top-0 bg-slate-50 text-[10px] uppercase text-slate-500">
                            <tr><th class="px-3 py-2">Event</th><th class="px-3 py-2">Actor</th><th class="px-3 py-2">When</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="(e, i) in admin_panel.event_log" :key="i" class="border-t border-slate-100">
                                <td class="px-3 py-2">{{ e.event_type }}</td>
                                <td class="px-3 py-2">{{ e.actor }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ e.at_label }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <Teleport to="body">
            <div v-if="showAmendmentForm" class="fixed inset-0 z-[60] flex items-end justify-center bg-slate-950/50 p-4 backdrop-blur-[2px] sm:items-center" @click.self="showAmendmentForm = false">
                <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl sm:p-6">
                    <h3 class="font-display text-lg font-black text-slate-900">Request amendment</h3>
                    <form class="mt-4 space-y-3" @submit.prevent="submitAmendment">
                        <select v-model="amendmentForm.amendment_type" class="w-full rounded-xl border-slate-200 text-sm font-semibold shadow-sm">
                            <option value="scope">Scope change</option>
                            <option value="price">Price adjustment</option>
                            <option value="delivery_date">Delivery date extension</option>
                        </select>
                        <textarea v-model="amendmentForm.description" rows="3" class="w-full rounded-xl border-slate-200 text-sm shadow-sm" placeholder="Describe the change" required />
                        <input v-if="amendmentForm.amendment_type !== 'scope'" v-model="amendmentForm.new_value" type="text" class="w-full rounded-xl border-slate-200 text-sm shadow-sm" placeholder="New value (NGN or date)" />
                        <textarea v-model="amendmentForm.reason" rows="2" class="w-full rounded-xl border-slate-200 text-sm shadow-sm" placeholder="Reason (required)" required />
                        <div class="flex gap-2">
                            <button type="submit" class="rounded-full bg-primary-700 px-4 py-2 text-xs font-black uppercase text-white" :disabled="amendmentForm.processing">Submit</button>
                            <button type="button" class="rounded-full border border-slate-200 px-4 py-2 text-xs font-black uppercase text-slate-700" @click="showAmendmentForm = false">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AppShell>
</template>

<script setup>
import AppShell from '@/Layouts/AppShell.vue';
import BackChevronLink from '@/Components/Ui/BackChevronLink.vue';
import HustleSafeLogo from '@/Components/Brand/HustleSafeLogo.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    contract: { type: Object, required: true },
    timeline_stages: { type: Array, default: () => [] },
    role: { type: Object, default: () => ({}) },
    permissions: { type: Object, default: () => ({}) },
    pending_amendment: { type: Object, default: null },
    admin_panel: { type: Object, default: null },
});

const deliveryCountdown = computed(() => props.contract.delivery_countdown || { active: false });
const disputeWindow = computed(() => props.contract.dispute_window || { active: false });

const showAmendmentForm = ref(false);
const showDeclineNote = ref(false);

const amendmentForm = useForm({
    amendment_type: 'scope',
    description: '',
    reason: '',
    new_value: '',
});

const respondForm = useForm({ action: 'accept', response_note: '' });
const flagForm = useForm({ reason: '' });

function formatWhen(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
}

function countdownLabel(seconds) {
    const s = Math.max(0, Number(seconds) || 0);
    const days = Math.floor(s / 86400);
    const hours = Math.floor((s % 86400) / 3600);
    if (days > 0) return `${days}d ${hours}h`;
    const mins = Math.floor((s % 3600) / 60);
    return hours > 0 ? `${hours}h ${mins}m` : `${mins}m`;
}

function statusClass(status) {
    return {
        pending_escrow: 'bg-amber-50 text-amber-900 ring-amber-200',
        active: 'bg-emerald-50 text-emerald-900 ring-emerald-200',
        amendment_pending: 'bg-sky-50 text-sky-900 ring-sky-200',
        completed: 'bg-slate-100 text-slate-700 ring-slate-200',
        disputed: 'bg-rose-50 text-rose-900 ring-rose-200',
        cancelled: 'bg-slate-50 text-slate-500 ring-slate-200',
    }[status] || 'bg-slate-100 text-slate-700 ring-slate-200';
}

function submitAmendment() {
    amendmentForm.post(route('contracts.amendments.store', props.contract.reference_code), {
        preserveScroll: true,
        onSuccess: () => { showAmendmentForm.value = false; },
    });
}

function respondAmendment(action) {
    if (action === 'decline' && !showDeclineNote.value) {
        showDeclineNote.value = true;
        return;
    }
    if (action === 'decline' && !respondForm.response_note.trim()) {
        return;
    }
    respondForm.action = action;
    respondForm.post(route('contracts.amendments.respond', [props.contract.reference_code, props.pending_amendment.id]), {
        preserveScroll: true,
    });
}

function submitFlag() {
    flagForm.post(route('admin.contracts.flag', props.contract.reference_code), { preserveScroll: true });
}
</script>
