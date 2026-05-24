<template>
    <AdminShell
        title="Content Moderation"
        subtitle="Fast, fair moderation for quests, proposals, profiles, portfolios, reviews, and flagged messages."
    >
        <div class="space-y-5">
            <div class="grid gap-3 md:grid-cols-4">
                <div v-for="tile in summary" :key="tile.key" class="rounded-3xl border p-4 shadow-sm" :class="[shell.card, queueTone(tile.count)]">
                    <p class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">{{ tile.label }}</p>
                    <p class="mt-2 text-3xl font-black" :class="shell.title">{{ tile.count }}</p>
                    <p class="mt-1 text-xs font-bold" :class="shell.cardMuted">{{ tile.count > 50 ? 'Critical backlog' : tile.count > 20 ? 'Needs attention' : 'Healthy' }}</p>
                </div>
            </div>

            <AdminTabbedPage v-model="activeTab" :tabs="tabs" id-prefix="moderation-tab" aria-label="Content moderation sections">
            <AdminTabPanel v-for="queueTab in queueTabs" :key="queueTab.key" :current-tab="activeTab" :value="queueTab.key" id-prefix="moderation-tab" class="space-y-5">
                <AdminPanel :title="activeTabLabel" description="Oldest flagged content appears first by default. Use severity sorting for triage.">
                    <div class="mb-4 grid gap-3 md:grid-cols-[1fr_12rem_12rem_auto]">
                        <input v-model="filtersState.q" type="search" placeholder="Search title, excerpt, or trigger…" class="rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" @input="debouncedApply" />
                        <select v-model="filtersState.severity" class="rounded-2xl border px-3 py-3 text-sm font-bold" :class="shell.input" @change="applyFilters">
                            <option value="">All severity</option>
                            <option value="critical">Critical</option>
                            <option value="warning">Warning</option>
                        </select>
                        <select v-model="filtersState.sort" class="rounded-2xl border px-3 py-3 text-sm font-bold" :class="shell.input" @change="applyFilters">
                            <option value="">Oldest first</option>
                            <option value="severity">Severity first</option>
                        </select>
                        <button type="button" class="rounded-2xl px-4 py-3 text-sm font-black uppercase" :class="bulkMode ? shell.btnPrimary : shell.btnGhost" @click="bulkMode = !bulkMode">
                            Bulk mode
                        </button>
                    </div>

                    <div v-if="bulkMode" class="mb-4 rounded-3xl border p-4" :class="shell.card">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <p class="text-sm font-black">{{ activeQueue.data?.length || 0 }} items in this queue session</p>
                            <p class="text-xs font-bold" :class="shell.cardMuted">Keyboard shortcuts: A approve, R remove, W warning, N next.</p>
                        </div>
                    </div>

                    <div class="hidden overflow-x-auto lg:block">
                        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10">
                            <thead>
                                <tr class="text-left text-[10px] font-black uppercase tracking-wider" :class="shell.label">
                                    <th class="px-3 py-3">Content</th>
                                    <th class="px-3 py-3">Subject</th>
                                    <th class="px-3 py-3">Reason</th>
                                    <th class="px-3 py-3">Confidence</th>
                                    <th class="px-3 py-3">Visibility</th>
                                    <th class="px-3 py-3">Waiting</th>
                                    <th class="px-3 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-white/10">
                                <tr v-for="item in activeQueue.data" :key="item.id" class="cursor-pointer hover:bg-primary-50/60 dark:hover:bg-white/[0.03]" @click="openCase(item)">
                                    <td class="px-3 py-4">
                                        <p class="font-black">{{ item.title }}</p>
                                        <p class="mt-1 max-w-md text-xs font-semibold text-slate-500">{{ item.excerpt }}</p>
                                    </td>
                                    <td class="px-3 py-4">
                                        <p class="font-bold">{{ item.subject?.name || '—' }}</p>
                                        <p class="text-xs text-slate-500">{{ item.subject?.account_age || '' }}</p>
                                    </td>
                                    <td class="px-3 py-4">
                                        <span class="rounded-full px-3 py-1 text-xs font-black" :class="severityClass(item.severity)">{{ item.severity }}</span>
                                        <p class="mt-2 text-xs font-bold">{{ item.triggers?.[0]?.category || item.source }}</p>
                                    </td>
                                    <td class="px-3 py-4 font-black">{{ item.confidence }}%</td>
                                    <td class="px-3 py-4 text-xs font-bold capitalize">{{ item.visibility_state.replace(/_/g, ' ') }}</td>
                                    <td class="px-3 py-4 text-xs font-bold">{{ item.waiting_for }}</td>
                                    <td class="px-3 py-4"><button type="button" class="text-xs font-black text-primary-700 underline dark:text-primary-300">Review</button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="grid gap-3 lg:hidden">
                        <button v-for="item in activeQueue.data" :key="item.id" type="button" class="rounded-3xl border p-4 text-left" :class="shell.card" @click="openCase(item)">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-black">{{ item.title }}</p>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">{{ item.excerpt }}</p>
                                </div>
                                <span class="rounded-full px-2 py-1 text-[10px] font-black" :class="severityClass(item.severity)">{{ item.severity }}</span>
                            </div>
                            <p class="mt-3 text-xs font-bold">{{ item.subject?.name || 'Unknown user' }} · {{ item.waiting_for }}</p>
                        </button>
                    </div>
                </AdminPanel>
            </AdminTabPanel>

            <AdminTabPanel :current-tab="activeTab" value="history" id-prefix="moderation-tab">
                <AdminPanel title="Moderation history & audit" description="Immutable log of every approval, removal, warning, edit, and escalation.">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10">
                            <thead>
                                <tr class="text-left text-[10px] font-black uppercase tracking-wider" :class="shell.label">
                                    <th class="px-3 py-3">Content</th>
                                    <th class="px-3 py-3">Decision</th>
                                    <th class="px-3 py-3">Admin</th>
                                    <th class="px-3 py-3">Time to decision</th>
                                    <th class="px-3 py-3">Reason</th>
                                    <th class="px-3 py-3">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-white/10">
                                <tr v-for="row in history.data" :key="row.id">
                                    <td class="px-3 py-4 font-black">{{ row.content }}</td>
                                    <td class="px-3 py-4 capitalize">{{ row.decision.replace(/_/g, ' ') }}</td>
                                    <td class="px-3 py-4">{{ row.admin }}</td>
                                    <td class="px-3 py-4">{{ row.time_to_decision }}</td>
                                    <td class="px-3 py-4">{{ row.reason }}</td>
                                    <td class="px-3 py-4 text-xs">{{ dateLabel(row.created_at) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </AdminPanel>
            </AdminTabPanel>

            <AdminTabPanel :current-tab="activeTab" value="settings" id-prefix="moderation-tab" class="grid gap-5 xl:grid-cols-[1.1fr_0.9fr]">
                <AdminPanel title="Keyword management" description="Add, pause, and tune automated text flags.">
                    <form class="grid gap-3 md:grid-cols-[1fr_10rem_12rem_auto]" @submit.prevent="storeKeyword">
                        <input v-model="keywordForm.phrase" required placeholder="Phrase" class="rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                        <select v-model="keywordForm.severity" class="rounded-2xl border px-3 py-3 text-sm font-bold" :class="shell.input">
                            <option value="warning">Warning</option>
                            <option value="critical">Critical</option>
                        </select>
                        <input v-model="keywordForm.category" required placeholder="Category" class="rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                        <button type="submit" class="rounded-2xl px-4 py-3 text-sm font-black" :class="shell.btnPrimary">Add</button>
                    </form>
                    <div class="mt-4 space-y-2">
                        <div v-for="keyword in settings.keywords" :key="keyword.id" class="flex items-center justify-between gap-3 rounded-2xl border p-3" :class="shell.card">
                            <div>
                                <p class="font-black">{{ keyword.phrase }}</p>
                                <p class="text-xs font-bold" :class="shell.cardMuted">{{ keyword.category }} · {{ keyword.severity }} · {{ keyword.is_active ? 'active' : 'paused' }}</p>
                            </div>
                            <button type="button" class="text-xs font-black text-rose-600 underline" @click="pauseKeyword(keyword)">Pause</button>
                        </div>
                    </div>
                </AdminPanel>

                <AdminPanel title="Automation settings" description="Tune moderation thresholds without deployment.">
                    <form class="space-y-4" @submit.prevent="saveSettings">
                        <label class="block">
                            <span class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">New account review period (hours)</span>
                            <input v-model="settingsForm.new_account_review_hours" type="number" min="24" max="168" class="mt-1 w-full rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                        </label>
                        <label class="block">
                            <span class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">Approved external domains</span>
                            <textarea v-model="settingsDomains" rows="4" class="mt-1 w-full rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                        </label>
                        <label class="flex items-center gap-2 text-sm font-bold">
                            <input v-model="settingsForm.cloudinary_moderation_enabled" type="checkbox" />
                            <span>Enable Cloudinary moderation checks</span>
                        </label>
                        <button type="submit" class="w-full rounded-2xl px-4 py-3 text-sm font-black" :class="shell.btnPrimary">Save settings</button>
                    </form>
                </AdminPanel>
            </AdminTabPanel>
            </AdminTabbedPage>
        </div>

        <AdminSlideOver :open="caseOpen" :title="selectedCase?.title || 'Review content'" eyebrow="Moderation review" @close="caseOpen = false">
            <div v-if="selectedCase" class="space-y-5">
                <div class="rounded-3xl border p-4" :class="shell.card">
                    <p class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">Content preview</p>
                    <h3 class="mt-2 text-xl font-black" :class="shell.title">{{ selectedCase.title }}</h3>
                    <p class="mt-3 whitespace-pre-line text-sm font-semibold leading-6">{{ highlightedText }}</p>
                </div>

                <div class="rounded-3xl border p-4" :class="shell.card">
                    <h4 class="font-black">Moderation context</h4>
                    <div class="mt-3 space-y-3">
                        <div v-for="trigger in selectedCase.triggers" :key="trigger.rule_key + trigger.matched_text" class="rounded-2xl border p-3">
                            <div class="flex items-start justify-between gap-3">
                                <p class="font-black">{{ trigger.category || trigger.rule_type }}</p>
                                <span class="rounded-full px-2 py-1 text-[10px] font-black" :class="severityClass(trigger.severity)">{{ trigger.confidence }}%</span>
                            </div>
                            <p class="mt-1 text-xs font-semibold text-slate-500">{{ trigger.context }}</p>
                        </div>
                    </div>
                </div>

                <form class="space-y-3 rounded-3xl border p-4" :class="shell.card" @submit.prevent="submitDecision">
                    <h4 class="font-black">Decision</h4>
                    <select v-model="decisionForm.action" class="w-full rounded-2xl border px-3 py-3 text-sm font-bold" :class="shell.input">
                        <option value="approve">Approve / Disable flag</option>
                        <option value="approve_warning">Approve with warning</option>
                        <option value="edit_approve">Edit and approve</option>
                        <option value="remove">Remove</option>
                        <option value="remove_warn">Remove and warn</option>
                        <option value="remove_suspend">Remove and suspend</option>
                        <option value="request_revision">Request revision</option>
                        <option value="fraud_investigation">Flag for fraud investigation</option>
                    </select>
                    <select v-model="decisionForm.reason_code" class="w-full rounded-2xl border px-3 py-3 text-sm font-bold" :class="shell.input">
                        <option v-for="reason in reasonOptions" :key="reason" :value="reason">{{ reason.replace(/_/g, ' ') }}</option>
                    </select>
                    <div v-if="decisionForm.action === 'edit_approve'" class="space-y-3">
                        <input v-model="decisionForm.edited.title" type="text" placeholder="Edited title" class="w-full rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                        <textarea v-model="decisionForm.edited.description" rows="4" placeholder="Edited description / bio / text" class="w-full rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                        <textarea v-model="decisionForm.edited.comment" rows="3" placeholder="Edited review comment" class="w-full rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                    </div>
                    <textarea v-model="decisionForm.note" rows="3" placeholder="Supplementary note" class="w-full rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                    <button type="submit" class="w-full rounded-2xl px-4 py-3 text-sm font-black uppercase" :class="actionButtonClass">Submit decision</button>
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
import { router, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';

const props = defineProps({
    section: { type: String, required: true },
    summary: { type: Array, required: true },
    queues: { type: Object, default: () => ({}) },
    queue: { type: Object, default: () => ({ data: [] }) },
    history: { type: Object, default: () => ({ data: [] }) },
    settings: { type: Object, default: null },
    metrics: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
    reasonOptions: { type: Array, default: () => [] },
});

const { shell } = useInjectedAdminTheme();
const tabs = [
    { key: 'quests', label: 'Quest Moderation' },
    { key: 'profiles', label: 'Profile & Portfolio' },
    { key: 'reviews', label: 'Review & Rating' },
    { key: 'history', label: 'History & Audit' },
    { key: 'settings', label: 'Settings' },
];
const queueTabs = tabs.filter((tab) => ['quests', 'profiles', 'reviews'].includes(tab.key));
const { activeTab } = useTabState(tabs.map((tab) => tab.key), props.section || 'quests');
const isQueueSection = computed(() => ['quests', 'profiles', 'reviews'].includes(activeTab.value));
const activeTabLabel = computed(() => tabs.find((tab) => tab.key === activeTab.value)?.label || 'Moderation queue');
const activeQueue = computed(() => props.queues?.[activeTab.value] || props.queue || { data: [] });
const caseOpen = ref(false);
const selectedCase = ref(null);
const bulkMode = ref(false);
const filtersState = reactive({ q: props.filters.q || '', severity: props.filters.severity || '', sort: props.filters.sort || '' });
const keywordForm = useForm({ phrase: '', severity: 'warning', category: 'policy_violation', is_active: true, note: '' });
const settingsForm = useForm({
    new_account_review_hours: props.settings?.settings?.new_account_review_hours || 48,
    allowed_external_domains: props.settings?.settings?.allowed_external_domains || [],
    cloudinary_moderation_enabled: props.settings?.settings?.cloudinary_moderation_enabled ?? true,
    templates: props.settings?.templates || [],
});
const settingsDomains = ref((settingsForm.allowed_external_domains || []).join('\n'));
const decisionForm = reactive({
    action: 'approve',
    reason_code: props.reasonOptions[0] || 'policy_violation',
    note: '',
    edited: { title: '', description: '', comment: '', bio: '' },
});

let debounceTimer = null;

const highlightedText = computed(() => selectedCase.value?.snapshot?.text || selectedCase.value?.excerpt || '');
const actionButtonClass = computed(() => decisionForm.action.includes('remove') ? 'bg-rose-600 text-white' : shell.btnPrimary);

function queueTone(count) {
    if (count > 50) return 'ring-2 ring-rose-400';
    if (count > 20) return 'ring-2 ring-amber-400';
    return '';
}

function severityClass(severity) {
    return severity === 'critical'
        ? 'bg-rose-100 text-rose-700 dark:bg-rose-400/15 dark:text-rose-200'
        : 'bg-amber-100 text-amber-700 dark:bg-amber-400/15 dark:text-amber-200';
}

function applyFilters() {
    router.get(route('admin.content-moderation.index'), { tab: activeTab.value, ...clean(filtersState) }, { preserveScroll: true, preserveState: true });
}

function debouncedApply() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(applyFilters, 250);
}

async function openCase(item) {
    const { data } = await window.axios.get(route('admin.content-moderation.cases.show', item.id));
    selectedCase.value = data;
    caseOpen.value = true;
}

async function submitDecision() {
    if (!selectedCase.value) return;
    if (!window.confirm(`Submit ${decisionForm.action.replace(/_/g, ' ')} decision?`)) return;

    await window.axios.post(route('admin.content-moderation.cases.decision', selectedCase.value.id), decisionForm);
    caseOpen.value = false;
    selectedCase.value = null;
    router.reload({ only: ['summary', 'queue', 'history', 'metrics'] });
}

function storeKeyword() {
    keywordForm.post(route('admin.content-moderation.keywords.store'), {
        preserveScroll: true,
        onSuccess: () => keywordForm.reset('phrase', 'note'),
    });
}

function pauseKeyword(keyword) {
    router.delete(route('admin.content-moderation.keywords.destroy', keyword.id), { preserveScroll: true });
}

function saveSettings() {
    settingsForm.allowed_external_domains = settingsDomains.value.split('\n').map((line) => line.trim()).filter(Boolean);
    settingsForm.patch(route('admin.content-moderation.settings.update'), { preserveScroll: true });
}

function clean(obj) {
    return Object.fromEntries(Object.entries(obj).filter(([, value]) => value !== '' && value !== null && value !== undefined));
}

function dateLabel(value) {
    if (!value) return '—';
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}

function onKey(event) {
    if (!caseOpen.value || event.target?.tagName === 'TEXTAREA' || event.target?.tagName === 'INPUT') return;
    const key = event.key.toLowerCase();
    if (key === 'a') decisionForm.action = 'approve';
    if (key === 'r') decisionForm.action = 'remove';
    if (key === 'w') decisionForm.action = 'approve_warning';
    if (key === 'n') caseOpen.value = false;
}

onMounted(() => window.addEventListener('keydown', onKey));
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));
</script>
