<template>
    <AdminShell
        title="Verification & KYC Centre"
        subtitle="Secure identity review, tier upgrades, fraud interception, and verification settings for the Nigerian marketplace."
    >
        <div class="space-y-5">
            <div class="grid gap-3 md:grid-cols-5">
                <div class="rounded-3xl border p-4 shadow-sm" :class="[shell.card, waitTone]">
                    <p class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">Avg wait</p>
                    <p class="mt-2 text-2xl font-black" :class="shell.title">{{ summary.average_wait_label }}</p>
                    <p class="mt-1 text-xs font-bold" :class="shell.cardMuted">Amber after 4h, red after 24h</p>
                </div>
                <div v-for="tile in priorityTiles" :key="tile.key" class="rounded-3xl border p-4 shadow-sm" :class="[shell.card, tile.ring]">
                    <p class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">{{ tile.label }}</p>
                    <p class="mt-2 text-3xl font-black" :class="shell.title">{{ tile.count }}</p>
                    <p class="mt-1 text-xs font-bold" :class="shell.cardMuted">{{ tile.caption }}</p>
                </div>
            </div>

            <AdminTabbedPage v-model="activeTab" :tabs="tabs" id-prefix="kyc-tab" aria-label="KYC sections">
            <AdminTabPanel :current-tab="activeTab" value="queue" id-prefix="kyc-tab">
                <AdminPanel title="Verification queue" description="Only cases needing human judgment appear here. Clear, high-confidence API matches can auto-approve outside this queue.">
                    <div class="mb-4 grid gap-3 md:grid-cols-[1fr_10rem_10rem_10rem_auto]">
                        <input v-model="filtersState.q" type="search" placeholder="Search name or email…" class="rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" @input="debouncedApply" />
                        <select v-model="filtersState.priority" class="rounded-2xl border px-3 py-3 text-sm font-bold" :class="shell.input" @change="applyFilters">
                            <option value="">All priority</option>
                            <option value="critical">Critical</option>
                            <option value="high">High</option>
                            <option value="medium">Medium</option>
                            <option value="standard">Standard</option>
                        </select>
                        <select v-model="filtersState.role" class="rounded-2xl border px-3 py-3 text-sm font-bold" :class="shell.input" @change="applyFilters">
                            <option value="">All roles</option>
                            <option value="client">Client</option>
                            <option value="freelancer">Freelancer</option>
                        </select>
                        <select v-model="filtersState.sort" class="rounded-2xl border px-3 py-3 text-sm font-bold" :class="shell.input" @change="applyFilters">
                            <option value="priority">Priority</option>
                            <option value="wait_time">Wait time</option>
                            <option value="role">Role</option>
                        </select>
                        <button type="button" class="rounded-2xl px-4 py-3 text-sm font-black" :class="shell.btnGhost" @click="applyFilters">Apply</button>
                    </div>

                    <div class="hidden overflow-x-auto lg:block">
                        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10">
                            <thead>
                                <tr class="text-left text-[10px] font-black uppercase tracking-wider" :class="shell.label">
                                    <th class="px-3 py-3">User</th>
                                    <th class="px-3 py-3">Role</th>
                                    <th class="px-3 py-3">Attempting</th>
                                    <th class="px-3 py-3">Queue reason</th>
                                    <th class="px-3 py-3">Priority</th>
                                    <th class="px-3 py-3">Waiting</th>
                                    <th class="px-3 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-white/10">
                                <tr
                                    v-for="item in queue.data"
                                    :key="item.id"
                                    class="cursor-pointer hover:bg-primary-50/60 dark:hover:bg-white/[0.03]"
                                    :class="item.queue_reason === 'duplicate_identity' ? 'bg-rose-50/80 ring-1 ring-inset ring-rose-200 dark:bg-rose-950/20' : ''"
                                    @click="openCase(item)"
                                >
                                    <td class="px-3 py-4">
                                        <div class="flex items-center gap-3">
                                            <img :src="item.user.avatar_url || '/images/default-avatar.png'" class="h-10 w-10 rounded-2xl object-cover" alt="" />
                                            <div>
                                                <p class="font-black">{{ item.user.name }}</p>
                                                <p class="text-xs font-semibold text-slate-500">{{ item.user.email }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-4 capitalize">{{ item.user.role }}</td>
                                    <td class="px-3 py-4 font-black">Tier {{ item.target_tier }}</td>
                                    <td class="px-3 py-4">
                                        <span
                                            class="inline-flex items-center gap-2 capitalize"
                                            :class="item.queue_reason === 'duplicate_identity' ? 'font-black text-rose-700 dark:text-rose-300' : ''"
                                        >
                                            <span
                                                v-if="item.queue_reason === 'duplicate_identity'"
                                                class="rounded-full bg-rose-600 px-2 py-0.5 text-[10px] font-black uppercase text-white"
                                            >
                                                Duplicate
                                            </span>
                                            {{ item.queue_reason.replace(/_/g, ' ') }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-4"><span class="rounded-full px-3 py-1 text-xs font-black" :class="priorityClass(item.priority)">{{ item.priority }}</span></td>
                                    <td class="px-3 py-4 text-xs font-bold">{{ item.waiting_for }}</td>
                                    <td class="px-3 py-4"><button type="button" class="text-xs font-black text-primary-700 underline dark:text-primary-300">Review</button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="grid gap-3 lg:hidden">
                        <button v-for="item in queue.data" :key="item.id" type="button" class="rounded-3xl border p-4 text-left" :class="shell.card" @click="openCase(item)">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <img :src="item.user.avatar_url || '/images/default-avatar.png'" class="h-12 w-12 rounded-2xl object-cover" alt="" />
                                    <div>
                                        <p class="font-black">{{ item.user.name }}</p>
                                        <p class="text-xs font-semibold text-slate-500">Tier {{ item.target_tier }} · {{ item.user.role }}</p>
                                    </div>
                                </div>
                                <span class="rounded-full px-2 py-1 text-[10px] font-black" :class="priorityClass(item.priority)">{{ item.priority }}</span>
                            </div>
                            <p class="mt-3 text-xs font-bold capitalize">{{ item.queue_reason.replace(/_/g, ' ') }} · {{ item.waiting_for }}</p>
                        </button>
                    </div>
                </AdminPanel>
            </AdminTabPanel>

            <AdminTabPanel :current-tab="activeTab" value="analytics" id-prefix="kyc-tab" class="grid gap-5 xl:grid-cols-[1fr_0.8fr]">
                <AdminPanel title="Verification funnel" description="How users move from signup to verified status.">
                    <div class="space-y-3">
                        <div v-for="step in analytics.funnel" :key="step.label" class="rounded-3xl border p-4" :class="shell.card">
                            <div class="flex items-center justify-between gap-3">
                                <p class="font-black">{{ step.label }}</p>
                                <p class="text-sm font-black">{{ step.count }} · {{ step.percent }}%</p>
                            </div>
                            <div class="mt-3 h-3 rounded-full bg-slate-100 dark:bg-white/10">
                                <div class="h-3 rounded-full bg-primary-500" :style="{ width: `${Math.min(100, step.percent)}%` }"></div>
                            </div>
                        </div>
                    </div>
                </AdminPanel>
                <AdminPanel title="System signals" description="Rejections, decision speed, fraud interception, and completion by role.">
                    <div class="space-y-5">
                        <div>
                            <p class="text-xs font-black uppercase tracking-wider" :class="shell.label">Common rejection reasons</p>
                            <div class="mt-2 space-y-2">
                                <div v-for="reason in analytics.rejection_reasons" :key="reason.reason_code" class="flex justify-between rounded-2xl border p-3 text-sm font-bold" :class="shell.card">
                                    <span>{{ reason.reason_code?.replace(/_/g, ' ') }}</span>
                                    <span>{{ reason.total }}</span>
                                </div>
                            </div>
                        </div>
                        <div>
                            <p class="text-xs font-black uppercase tracking-wider" :class="shell.label">Average time by tier</p>
                            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                <div v-for="row in analytics.avg_time_by_tier" :key="row.tier" class="rounded-2xl border p-3" :class="shell.card">
                                    <p class="text-sm font-black">{{ row.tier }}</p>
                                    <p class="text-xl font-black">{{ row.time }}</p>
                                </div>
                            </div>
                        </div>
                        <div>
                            <p class="text-xs font-black uppercase tracking-wider" :class="shell.label">Completion by user type</p>
                            <div class="mt-2 space-y-2">
                                <div v-for="row in analytics.completion_by_type" :key="row.role" class="rounded-2xl border p-3" :class="shell.card">
                                    <p class="text-sm font-black capitalize">{{ row.role }} · {{ row.rate }}%</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </AdminPanel>
            </AdminTabPanel>

            <AdminTabPanel :current-tab="activeTab" value="settings" id-prefix="kyc-tab">
                <AdminPanel title="KYC settings" description="Super-admin controls for providers, confidence thresholds, feature gates, resubmission rules, fees, and limits.">
                    <form class="grid gap-5 xl:grid-cols-2" @submit.prevent="saveSettings">
                        <div class="space-y-4">
                            <label class="block">
                                <span class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">Active provider</span>
                                <select v-model="settingsForm.active_provider" class="mt-1 w-full rounded-2xl border px-3 py-3 text-sm font-bold" :class="shell.input">
                                    <option value="manual">Manual / pending live API</option>
                                    <option value="dojah">Dojah</option>
                                    <option value="smile_identity">Smile Identity</option>
                                </select>
                            </label>
                            <label class="block">
                                <span class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">Fallback provider</span>
                                <input v-model="settingsForm.fallback_provider" class="mt-1 w-full rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                            </label>
                            <div class="rounded-3xl border p-4" :class="shell.card">
                                <p class="font-black">API health</p>
                                <p class="mt-1 text-sm font-semibold" :class="settings.api_health.status === 'ok' ? 'text-emerald-600' : 'text-amber-600'">{{ settings.api_health.label }}</p>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <div class="grid gap-3 sm:grid-cols-3">
                                <label v-for="key in ['nin', 'bvn', 'face_similarity']" :key="key" class="block">
                                    <span class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">{{ key.replace('_', ' ') }} threshold</span>
                                    <input v-model.number="settingsForm.thresholds[key]" type="number" min="50" max="100" class="mt-1 w-full rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                                </label>
                            </div>
                            <label class="block">
                                <span class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">Resubmission limit</span>
                                <input v-model.number="settingsForm.resubmission_limit" type="number" min="1" max="10" class="mt-1 w-full rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                            </label>
                            <label class="flex items-center gap-2 text-sm font-bold">
                                <input v-model="settingsForm.verification_fees.enabled" type="checkbox" />
                                <span>Charge verification fees</span>
                            </label>
                            <label class="block">
                                <span class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">CAC fee (₦)</span>
                                <input v-model.number="settingsForm.verification_fees.cac_fee_minor" type="number" min="0" step="1" class="mt-1 w-full rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                            </label>
                            <button type="submit" class="w-full rounded-2xl px-4 py-3 text-sm font-black uppercase" :class="shell.btnPrimary">Save settings</button>
                        </div>
                    </form>
                </AdminPanel>
            </AdminTabPanel>
            </AdminTabbedPage>
        </div>

        <AdminSlideOver :open="reviewOpen" :title="selectedCase?.user?.name || 'Verification review'" eyebrow="KYC review" @close="reviewOpen = false">
            <div v-if="selectedCase" class="space-y-5 pb-24 lg:pb-0">
                <div class="grid gap-4 lg:grid-cols-2">
                    <div class="rounded-3xl border p-4" :class="shell.card">
                        <h3 class="font-black">User submitted</h3>
                        <FieldRow v-for="(value, key) in selectedCase.submitted" :key="key" :label="key" :value="value" :tone="comparisonTone(key)" @reveal="revealField(key)" />
                        <div class="mt-4">
                            <button type="button" class="rounded-2xl px-4 py-2 text-xs font-black" :class="shell.btnGhost" @click="loadDocuments">Load documents</button>
                            <div v-if="selectedCase.documents?.length" class="mt-3 grid gap-3">
                                <a v-for="doc in selectedCase.documents" :key="doc.id" :href="doc.url" target="_blank" class="rounded-2xl border p-3 text-sm font-black underline" :class="shell.card">
                                    {{ doc.label }} · {{ doc.document_type }}
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="rounded-3xl border p-4" :class="shell.card">
                        <h3 class="font-black">API / record returned</h3>
                        <FieldRow v-for="(value, key) in selectedCase.provider" :key="key" :label="key" :value="value" :tone="comparisonTone(key)" @reveal="revealField(key)" />
                        <div class="mt-4 rounded-2xl border p-3" :class="shell.card">
                            <p class="text-xs font-black uppercase tracking-wider" :class="shell.label">Liveness / confidence</p>
                            <p class="mt-2 text-2xl font-black">{{ selectedCase.confidence_score ?? '—' }}%</p>
                            <p class="text-xs font-semibold" :class="shell.cardMuted">{{ selectedCase.queue_reason.replace(/_/g, ' ') }}</p>
                        </div>
                    </div>
                </div>

                <div v-if="selectedCase.duplicate_context?.length" class="rounded-3xl border-2 border-rose-500 bg-gradient-to-br from-rose-50 via-rose-100/70 to-amber-50 p-4 ring-2 ring-rose-200 dark:from-rose-950/40 dark:via-rose-950/20 dark:to-amber-950/10">
                    <p class="text-[10px] font-black uppercase tracking-[0.25em] text-rose-800 dark:text-rose-200">Duplicate identity alert</p>
                    <p class="mt-2 text-xs font-bold text-rose-950 dark:text-rose-100">These accounts share the same government ID. Review every profile before approving.</p>
                    <h3 class="font-black">Duplicate identity context</h3>
                    <div class="mt-3 grid gap-3 lg:grid-cols-2">
                        <div v-for="dupe in selectedCase.duplicate_context" :key="dupe.case_id" class="rounded-2xl border p-3" :class="shell.card">
                            <p class="font-black">{{ dupe.user?.name }}</p>
                            <p class="text-xs font-bold" :class="shell.cardMuted">{{ dupe.user?.email }} · {{ dupe.status }}</p>
                        </div>
                    </div>
                </div>

                <form class="sticky bottom-0 space-y-3 rounded-3xl border p-4 shadow-2xl lg:static" :class="shell.card" @submit.prevent="submitDecision">
                    <h3 class="font-black">Decision</h3>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <select v-model="decisionForm.action" class="rounded-2xl border px-3 py-3 text-sm font-bold" :class="shell.input">
                            <option value="approve">Approve verification</option>
                            <option value="approve_note">Approve with note</option>
                            <option value="request_correction">Request correction</option>
                            <option value="reject">Reject verification</option>
                            <option value="reject_investigate">Reject and flag investigation</option>
                            <option value="reject_suspend">Reject and suspend</option>
                            <option value="award_badge">Manual award badge</option>
                            <option value="revoke_badge">Revoke badge</option>
                        </select>
                        <select v-model="decisionForm.reason_code" class="rounded-2xl border px-3 py-3 text-sm font-bold" :class="shell.input">
                            <option v-for="reason in reasonOptions" :key="reason" :value="reason">{{ reason.replace(/_/g, ' ') }}</option>
                        </select>
                    </div>
                    <div v-if="decisionForm.action === 'request_correction'" class="grid gap-2 sm:grid-cols-2">
                        <label v-for="field in correctionOptions" :key="field" class="flex items-center gap-2 rounded-2xl border p-3 text-sm font-bold" :class="shell.card">
                            <input v-model="decisionForm.correction_fields" :value="field" type="checkbox" />
                            <span>{{ field.replace(/_/g, ' ') }}</span>
                        </label>
                    </div>
                    <div v-if="selectedCase.verification_type === 'qualification'" class="rounded-2xl border p-3" :class="shell.card">
                        <p class="font-black">Portfolio assessment rubric</p>
                        <div v-for="score in decisionForm.portfolio_scores" :key="score.criterion" class="mt-3 grid gap-2 sm:grid-cols-[1fr_8rem]">
                            <label class="text-sm font-bold">{{ score.criterion }}</label>
                            <input v-model.number="score.score" type="range" min="1" max="5" />
                        </div>
                        <p class="mt-2 text-sm font-black">Total: {{ portfolioTotal }}/25</p>
                    </div>
                    <textarea v-model="decisionForm.note" rows="4" placeholder="Decision note. Reject and suspend requires at least 100 characters." class="w-full rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                    <div class="grid gap-3 sm:grid-cols-3">
                        <button type="button" class="rounded-2xl bg-amber-500 px-4 py-3 text-sm font-black text-white" @click="decisionForm.action = 'request_correction'">Request correction</button>
                        <button type="button" class="rounded-2xl bg-rose-600 px-4 py-3 text-sm font-black text-white" @click="decisionForm.action = 'reject'">Reject</button>
                        <button type="submit" class="rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-black text-white">Submit decision</button>
                    </div>
                </form>
            </div>
        </AdminSlideOver>
    </AdminShell>
</template>

<script setup>
import AdminPanel from '@/Components/Admin/AdminPanel.vue';
import AdminSlideOver from '@/Components/Admin/AdminSlideOver.vue';
import AdminTabPanel from '@/Components/Admin/AdminTabPanel.vue';
import AdminTabbedPage from '@/Components/Admin/AdminTabbedPage.vue';
import { useTabState } from '@/composables/useTabState';
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';
import AdminShell from '@/Layouts/AdminShell.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, defineComponent, h, reactive, ref } from 'vue';
import { useVerificationQueueEcho } from '@/composables/useVerificationQueueEcho';

const props = defineProps({
    section: { type: String, required: true },
    summary: { type: Object, default: () => ({}) },
    queue: { type: Object, default: () => ({ data: [] }) },
    analytics: { type: Object, default: null },
    settings: { type: Object, default: null },
    filters: { type: Object, default: () => ({}) },
    reasonOptions: { type: Array, default: () => [] },
    correctionOptions: { type: Array, default: () => [] },
});

const FieldRow = defineComponent({
    props: { label: String, value: [String, Number, Boolean, Object], tone: String },
    emits: ['reveal'],
    setup(componentProps, { emit }) {
        return () => h('div', { class: ['mt-3 rounded-2xl border p-3', toneClass(componentProps.tone)] }, [
            h('div', { class: 'flex items-center justify-between gap-3' }, [
                h('p', { class: 'text-[10px] font-black uppercase tracking-wider text-slate-500' }, String(componentProps.label).replace(/_/g, ' ')),
                ['identifier_number', 'nin', 'bvn', 'phone'].includes(componentProps.label) ? h('button', { type: 'button', class: 'text-xs font-black text-primary-700 underline', onClick: () => emit('reveal') }, 'Reveal') : null,
            ]),
            h('p', { class: 'mt-1 break-words text-sm font-black' }, typeof componentProps.value === 'object' ? JSON.stringify(componentProps.value) : String(componentProps.value ?? '—')),
        ]);
    },
});

const { shell } = useInjectedAdminTheme();
const page = usePage();
const tabs = [
    { key: 'queue', label: 'Verification Queue' },
    { key: 'analytics', label: 'Analytics' },
    { key: 'settings', label: 'Settings' },
];
const { activeTab } = useTabState(tabs.map((tab) => tab.key), props.section || 'queue');
const filtersState = reactive({ q: props.filters.q || '', priority: props.filters.priority || '', role: props.filters.role || '', sort: props.filters.sort || 'priority' });
const reviewOpen = ref(false);
const selectedCase = ref(null);
const decisionForm = reactive({
    action: 'approve',
    reason_code: props.reasonOptions[0] || 'identity_mismatch',
    note: '',
    correction_fields: [],
    portfolio_scores: ['Composition', 'Originality', 'Technical execution', 'Relevance', 'Professional finish'].map((criterion) => ({ criterion, score: 3, feedback: '' })),
});
const settingsForm = useForm({
    active_provider: props.settings?.active_provider || 'manual',
    fallback_provider: props.settings?.fallback_provider || '',
    thresholds: props.settings?.thresholds || { nin: 85, bvn: 85, face_similarity: 85 },
    feature_gates: props.settings?.feature_gates || {},
    resubmission_limit: props.settings?.resubmission_limit || 3,
    verification_fees: props.settings?.verification_fees || { enabled: false, cac_fee_minor: 0 },
    limits: props.settings?.limits || {},
});

let debounceTimer = null;
const priorityTiles = computed(() => [
    { key: 'total', label: 'Total queue', count: props.summary.total || 0, caption: 'All pending', ring: '' },
    { key: 'critical', label: 'Critical', count: props.summary.critical || 0, caption: 'Duplicate identity', ring: 'ring-2 ring-rose-400' },
    { key: 'high', label: 'High', count: props.summary.high || 0, caption: 'Mismatch cases', ring: 'ring-2 ring-orange-400' },
    { key: 'medium', label: 'Medium', count: props.summary.medium || 0, caption: 'Low confidence', ring: 'ring-2 ring-amber-400' },
]);
const waitTone = computed(() => (props.summary.average_wait_seconds > 86400 ? 'ring-2 ring-rose-400' : props.summary.average_wait_seconds > 14400 ? 'ring-2 ring-amber-400' : ''));
const portfolioTotal = computed(() => decisionForm.portfolio_scores.reduce((sum, row) => sum + Number(row.score || 0), 0));

function toneClass(tone) {
    if (tone === 'match') return 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-100';
    if (tone === 'mismatch') return 'border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-400/30 dark:bg-rose-400/10 dark:text-rose-100';
    if (tone === 'partial') return 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-100';
    return '';
}

function priorityClass(priority) {
    return {
        critical: 'bg-rose-100 text-rose-700 dark:bg-rose-400/15 dark:text-rose-200',
        high: 'bg-orange-100 text-orange-700 dark:bg-orange-400/15 dark:text-orange-200',
        medium: 'bg-amber-100 text-amber-700 dark:bg-amber-400/15 dark:text-amber-200',
        standard: 'bg-slate-100 text-slate-700 dark:bg-white/10 dark:text-slate-200',
    }[priority] || 'bg-slate-100 text-slate-700';
}

function comparisonTone(key) {
    return selectedCase.value?.comparison?.[key] || 'unknown';
}

function applyFilters() {
    router.get(route('admin.kyc.index'), { tab: activeTab.value, ...clean(filtersState) }, { preserveScroll: true, preserveState: true });
}

function debouncedApply() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(applyFilters, 250);
}

useVerificationQueueEcho(
    computed(() => page.props.broadcast),
    () => {
        if (activeTab.value === 'queue') {
            router.reload({ only: ['summary', 'queue'], preserveScroll: true, preserveState: true });
        }
    },
);

async function openCase(item) {
    const { data } = await window.axios.get(route('admin.kyc.cases.show', item.id));
    selectedCase.value = data;
    reviewOpen.value = true;
}

async function loadDocuments() {
    const { data } = await window.axios.get(route('admin.kyc.cases.show', selectedCase.value.id), { params: { documents: true } });
    selectedCase.value = data;
}

async function revealField(field) {
    if (!window.confirm(`Reveal ${field.replace(/_/g, ' ')}? This will be logged.`)) return;
    const { data } = await window.axios.post(route('admin.kyc.cases.reveal', selectedCase.value.id), { field });
    selectedCase.value.submitted[field] = data.value || selectedCase.value.submitted[field];
}

async function submitDecision() {
    if (!selectedCase.value) return;
    if (!window.confirm(`Submit ${decisionForm.action.replace(/_/g, ' ')} decision?`)) return;
    await window.axios.post(route('admin.kyc.cases.decision', selectedCase.value.id), decisionForm);
    reviewOpen.value = false;
    selectedCase.value = null;
    router.reload({ only: ['summary', 'queue', 'analytics'] });
}

function saveSettings() {
    settingsForm.patch(route('admin.kyc.settings.update'), { preserveScroll: true });
}

function clean(obj) {
    return Object.fromEntries(Object.entries(obj).filter(([, value]) => value !== '' && value !== null && value !== undefined));
}
</script>
