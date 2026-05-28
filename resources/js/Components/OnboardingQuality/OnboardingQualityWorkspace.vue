<template>
    <div>
        <div class="mb-4 flex flex-wrap items-end gap-3">
            <label class="text-xs font-bold text-slate-600">
                Status
                <select v-model="filters.status" class="mt-1 block rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold" @change="reload">
                    <option value="">All</option>
                    <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
            </label>
            <label class="text-xs font-bold text-slate-600">
                User type
                <select v-model="filters.user_type" class="mt-1 block rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold" @change="reload">
                    <option value="">All</option>
                    <option value="client">Client</option>
                    <option value="freelancer">Freelancer</option>
                </select>
            </label>
            <label class="text-xs font-bold text-slate-600">
                Sort
                <select v-model="filters.sort" class="mt-1 block rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold" @change="reload">
                    <option value="signup_desc">Newest signup</option>
                    <option value="signup_asc">Oldest signup</option>
                    <option value="completeness_asc">Lowest score</option>
                    <option value="completeness_desc">Highest score</option>
                    <option value="deadline_asc">Deadline soonest</option>
                </select>
            </label>
            <label class="flex items-center gap-2 text-xs font-bold text-slate-600">
                <input v-model="filters.within_window" type="checkbox" class="rounded border-slate-300" @change="reload" />
                48-hour window only
            </label>
            <Link
                v-if="flaggedHref"
                :href="flaggedHref"
                class="ml-auto rounded-xl border border-amber-200 bg-amber-50 px-4 py-2 text-xs font-black uppercase text-amber-900 hover:bg-amber-100"
            >
                Flagged profiles →
            </Link>
        </div>

        <OperationsQueueTable
            :columns="columns"
            :rows="rows"
            :loading="loading"
            v-model:search="search"
            v-model:per-page="perPage"
            :page="page"
            :total="total"
            :total-pages="totalPages"
            :sort-key="sortKey"
            :sort-dir="sortDir"
            empty-message="No onboarding reviews in this queue."
            @sort="onSort"
            @page="(p) => { page = p; reload(); }"
            @open="openRow"
        >
            <template #cell-user_type="{ row }">
                <span class="text-xs font-black uppercase text-primary-800">{{ row.user_type }}</span>
            </template>
            <template #cell-user="{ row }">
                <span class="font-semibold text-slate-950">{{ row.user?.name }}</span>
                <span class="block text-xs text-slate-500">{{ row.user?.email }}</span>
            </template>
            <template #cell-signup_at="{ row }">
                <span class="text-sm font-semibold text-slate-600">{{ formatWhen(row.signup_at) }}</span>
            </template>
            <template #cell-completeness_score="{ row }">
                <span class="rounded-full px-2 py-0.5 text-xs font-black" :class="scoreClass(row.completeness_score)">{{ row.completeness_score }}%</span>
            </template>
            <template #cell-flags="{ row }">
                <div class="flex flex-wrap gap-1">
                    <span v-for="f in row.flags" :key="f.key" class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-black text-amber-900">{{ f.label }}</span>
                    <span v-if="!row.flags?.length" class="text-xs text-slate-400">—</span>
                </div>
            </template>
            <template #cell-status="{ row }">
                <span class="rounded-full px-2 py-0.5 text-[10px] font-black uppercase" :class="statusClass(row.status)">{{ row.status_label }}</span>
            </template>
            <template #actions="{ row }">
                <button type="button" class="rounded-lg bg-primary-700 px-2 py-1 text-[10px] font-black uppercase text-white" @click.stop="openRow(row)">Review</button>
            </template>
        </OperationsQueueTable>

        <OperationsSlideOver
            :open="slideOpen"
            :title="detail?.profile?.user?.name || 'Profile review'"
            :subtitle="detail?.review?.user_type ? `${detail.review.user_type} · ${detail.review.status_label}` : ''"
            eyebrow="Onboarding QC"
            @close="slideOpen = false"
        >
            <div v-if="detail" class="space-y-4 pb-8">
                <div class="grid grid-cols-2 gap-2 rounded-xl border border-slate-100 bg-slate-50 p-3 text-sm">
                    <div>
                        <p class="text-[10px] font-black uppercase text-slate-400">Completeness</p>
                        <p class="font-black text-slate-900">{{ detail.review.completeness_score }}%</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-black uppercase text-slate-400">Deadline</p>
                        <p class="font-semibold text-slate-700">{{ formatWhen(detail.review.review_deadline_at) }}</p>
                    </div>
                    <div v-if="detail.review.blocks_posting" class="col-span-2 rounded-lg bg-rose-50 px-3 py-2 text-xs font-black text-rose-900">
                        Posting blocked (escalated or suspended pending review).
                    </div>
                </div>

                <section v-if="detail.profile?.user?.avatar_url" class="rounded-xl border border-slate-100 p-3">
                    <p class="text-[10px] font-black uppercase text-slate-500">Profile photo</p>
                    <img :src="detail.profile.user.avatar_url" alt="" class="mt-2 h-32 w-32 rounded-2xl object-cover ring-2 ring-slate-100" />
                </section>

                <section class="rounded-xl border border-slate-100 p-3 text-sm">
                    <p class="text-[10px] font-black uppercase text-slate-500">Bio & headline</p>
                    <p class="mt-2 font-semibold text-slate-800">{{ detail.profile.user.headline || '—' }}</p>
                    <p class="mt-2 whitespace-pre-wrap text-slate-700">{{ detail.profile.user.bio || '—' }}</p>
                </section>

                <section class="rounded-xl border border-slate-100 p-3">
                    <p class="mb-2 text-[10px] font-black uppercase text-slate-500">Automated flags</p>
                    <ul class="space-y-2">
                        <li v-for="flag in detail.profile.auto_flags" :key="flag.key" class="flex items-start justify-between gap-2 rounded-lg bg-slate-50 p-2 text-sm">
                            <div>
                                <p class="font-black text-slate-900">{{ flag.label }}</p>
                                <p class="text-xs text-slate-600">{{ flag.message }}</p>
                            </div>
                            <label class="flex shrink-0 items-center gap-1 text-[10px] font-bold text-slate-500">
                                <input type="checkbox" :checked="flag.dismissed" @change="toggleFlag(flag.key, $event.target.checked)" />
                                Dismiss
                            </label>
                        </li>
                    </ul>
                    <button type="button" class="mt-2 text-xs font-black uppercase text-primary-700" :disabled="busy.flags" @click="saveFlagOverrides">Save flag overrides</button>
                </section>

                <section v-if="detail.profile.kyc?.length" class="rounded-xl border border-slate-100 p-3 text-sm">
                    <p class="text-[10px] font-black uppercase text-slate-500">KYC / verifications</p>
                    <ul class="mt-2 space-y-1">
                        <li v-for="v in detail.profile.kyc" :key="v.id" class="font-semibold text-slate-700">{{ v.category }} · {{ v.verification_type || '—' }} · {{ v.status }}</li>
                    </ul>
                </section>

                <section v-if="detail.profile.categories?.length" class="rounded-xl border border-slate-100 p-3 text-sm">
                    <p class="text-[10px] font-black uppercase text-slate-500">Work categories</p>
                    <p class="mt-2 font-semibold text-slate-700">{{ detail.profile.categories.map((c) => c.name).join(', ') }}</p>
                </section>

                <section v-if="detail.profile.portfolios?.length" class="rounded-xl border border-slate-100 p-3 text-sm">
                    <p class="text-[10px] font-black uppercase text-slate-500">Portfolio</p>
                    <article v-for="p in detail.profile.portfolios" :key="p.id" class="mt-2 rounded-lg bg-slate-50 p-2">
                        <p class="font-black text-slate-900">{{ p.title }}</p>
                        <p class="text-xs text-slate-600 line-clamp-3">{{ p.description }}</p>
                    </article>
                </section>

                <section class="rounded-xl border border-slate-100 p-3 text-sm">
                    <p class="text-[10px] font-black uppercase text-slate-500">Login / device history</p>
                    <ul class="mt-2 max-h-40 space-y-1 overflow-y-auto">
                        <li v-for="ev in detail.profile.login_history" :key="ev.id" class="text-xs text-slate-600">
                            <span class="font-bold text-slate-800">{{ ev.ip_address }}</span> · {{ formatWhen(ev.logged_in_at) }}
                        </li>
                    </ul>
                </section>

                <section class="rounded-xl border border-slate-100 p-3">
                    <p class="mb-2 text-[10px] font-black uppercase text-slate-500">Admin action history</p>
                    <ul class="max-h-36 space-y-2 overflow-y-auto text-xs">
                        <li v-for="a in detail.actions" :key="a.id" class="rounded-lg bg-slate-50 p-2">
                            <span class="font-black text-slate-900">{{ a.action }}</span>
                            <span class="text-slate-500"> · {{ a.admin?.name }}</span>
                            <p v-if="a.notes" class="mt-1 text-slate-600">{{ a.notes }}</p>
                            <p class="mt-1 text-slate-400">{{ formatWhen(a.created_at) }}</p>
                        </li>
                    </ul>
                </section>

                <OperationsExpandableAction title="Send guided nudge" icon="✉" submit-label="Send nudge" :busy="busy.nudge" @submit="sendNudge">
                    <div class="space-y-2">
                        <label class="block text-xs font-bold text-slate-600">
                            Template
                            <select v-model="nudgeTemplateKey" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" @change="applyNudgeTemplate">
                                <option value="">Custom message</option>
                                <optgroup v-for="(items, group) in nudgeTemplates" :key="group" :label="group">
                                    <option v-for="t in items" :key="t.key" :value="`${group}:${t.key}`">{{ t.label }}</option>
                                </optgroup>
                            </select>
                        </label>
                        <OperationsFormField v-model="nudgeForm.subject" label="Subject" />
                        <OperationsFormField v-model="nudgeForm.body" label="Message" multiline :rows="5" />
                    </div>
                </OperationsExpandableAction>

                <div class="grid gap-2 sm:grid-cols-2">
                    <button type="button" class="rounded-xl bg-emerald-600 py-2.5 text-sm font-black text-white" :disabled="busy.action" @click="runAction('approve')">Approve profile</button>
                    <button v-if="!isSuperAdmin" type="button" class="rounded-xl border border-rose-200 bg-rose-50 py-2.5 text-sm font-black text-rose-900" :disabled="busy.action" @click="runAction('escalate', { notes: actionNotes.escalate })">Escalate to Trust & Safety</button>
                    <button v-else type="button" class="rounded-xl border border-primary-200 bg-primary-50 py-2.5 text-sm font-black text-primary-900" :disabled="busy.action" @click="runAction('resolve_escalation')">Resolve escalation</button>
                    <button type="button" class="rounded-xl border border-amber-200 bg-amber-50 py-2.5 text-sm font-black text-amber-900" :disabled="busy.action" @click="runAction('flag_monitoring', { notes: actionNotes.monitoring })">Flag for monitoring</button>
                    <button type="button" class="rounded-xl border border-slate-200 py-2.5 text-sm font-black text-slate-800" :disabled="busy.action" @click="runAction('request_verification')">Request verification</button>
                    <button type="button" class="rounded-xl border border-rose-300 py-2.5 text-sm font-black text-rose-950" :disabled="busy.action" @click="runAction('suspend', { notes: actionNotes.suspend })">Suspend pending review</button>
                    <button v-if="detail.review.status === 'suspended_pending_review'" type="button" class="rounded-xl border border-slate-200 py-2.5 text-sm font-black text-slate-800" :disabled="busy.action" @click="runAction('lift_suspension')">Lift suspension</button>
                </div>

                <OperationsFormField v-model="actionNotes.escalate" label="Escalation / suspend notes (optional)" multiline :rows="2" />
            </div>
        </OperationsSlideOver>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import OperationsExpandableAction from '@/Pages/Operations/Components/OperationsExpandableAction.vue';
import OperationsFormField from '@/Pages/Operations/Components/OperationsFormField.vue';
import OperationsQueueTable from '@/Pages/Operations/Components/OperationsQueueTable.vue';
import OperationsSlideOver from '@/Pages/Operations/Components/OperationsSlideOver.vue';
import { useOperationsAction } from '@/composables/useOperationsAction';
import { formatHumanDateTime } from '@/utils/formatHumanDateTime';

const props = defineProps({
    listingRoute: { type: String, required: true },
    detailRouteName: { type: String, required: true },
    actionRouteName: { type: String, required: true },
    flaggedHref: { type: String, default: '' },
    isSuperAdmin: { type: Boolean, default: false },
});

const columns = [
    { key: 'user_type', label: 'Type' },
    { key: 'user', label: 'User' },
    { key: 'signup_at', label: 'Signup' },
    { key: 'completeness_score', label: 'Score' },
    { key: 'flags', label: 'Flags' },
    { key: 'status', label: 'Status' },
];

const rows = ref([]);
const loading = ref(false);
const search = ref('');
const perPage = ref(25);
const page = ref(1);
const total = ref(0);
const totalPages = ref(1);
const sortKey = ref('signup_at');
const sortDir = ref('desc');
const statusOptions = ref([]);
const nudgeTemplates = ref({});

const filters = reactive({
    status: '',
    user_type: '',
    sort: 'signup_desc',
    within_window: true,
});

const slideOpen = ref(false);
const selected = ref(null);
const detail = ref(null);
const flagOverrides = ref({});
const nudgeForm = reactive({ subject: '', body: '' });
const nudgeTemplateKey = ref('');
const actionNotes = reactive({ escalate: '', monitoring: '', suspend: '' });
const { busy, runAction: runOpsAction } = useOperationsAction();

onMounted(reload);

watch(search, () => {
    page.value = 1;
    reload();
});

async function reload() {
    loading.value = true;
    try {
        const { data } = await window.axios.get(props.listingRoute, {
            params: {
                q: search.value,
                page: page.value,
                per_page: perPage.value,
                status: filters.status,
                user_type: filters.user_type,
                sort: filters.sort,
                within_window: filters.within_window ? 1 : 0,
            },
        });
        rows.value = data.items ?? [];
        total.value = data.meta?.total ?? 0;
        totalPages.value = data.meta?.last_page ?? 1;
        statusOptions.value = data.status_options ?? [];
        nudgeTemplates.value = data.nudge_templates ?? {};
    } finally {
        loading.value = false;
    }
}

function onSort(key, dir) {
    sortKey.value = key;
    sortDir.value = dir;
}

async function openRow(row) {
    selected.value = row;
    slideOpen.value = true;
    detail.value = null;
    const { data } = await window.axios.get(route(props.detailRouteName, row.id));
    detail.value = data;
    nudgeTemplates.value = data.nudge_templates ?? nudgeTemplates.value;
    flagOverrides.value = {};
    (data.profile?.auto_flags ?? []).forEach((f) => {
        flagOverrides.value[f.key] = { dismissed: !!f.dismissed };
    });
}

function toggleFlag(key, dismissed) {
    flagOverrides.value[key] = { dismissed };
}

async function saveFlagOverrides() {
    await runAction('override_flags', { flag_overrides: flagOverrides.value });
}

function applyNudgeTemplate() {
    if (!nudgeTemplateKey.value) return;
    const [group, key] = nudgeTemplateKey.value.split(':');
    const t = nudgeTemplates.value[group]?.find((x) => x.key === key);
    if (t) {
        nudgeForm.subject = t.subject;
        nudgeForm.body = t.body;
    }
}

async function sendNudge() {
    busy.nudge = true;
    try {
        await runAction('nudge', { subject: nudgeForm.subject, body: nudgeForm.body, template_key: nudgeTemplateKey.value });
    } finally {
        busy.nudge = false;
    }
}

async function runAction(action, extra = {}) {
    if (!selected.value) return;
    busy.action = true;
    try {
        const { data } = await window.axios.post(route(props.actionRouteName, selected.value.id), {
            action,
            notes: extra.notes ?? null,
            subject: nudgeForm.subject,
            body: nudgeForm.body,
            flag_overrides: extra.flag_overrides,
            ...extra,
        });
        detail.value = data.detail;
        await reload();
        const row = rows.value.find((r) => r.id === selected.value.id);
        if (row) selected.value = row;
    } finally {
        busy.action = false;
    }
}

function formatWhen(iso) {
    return iso ? formatHumanDateTime(iso) : '—';
}

function scoreClass(score) {
    if (score >= 75) return 'bg-emerald-100 text-emerald-900';
    if (score >= 50) return 'bg-amber-100 text-amber-900';
    return 'bg-rose-100 text-rose-900';
}

function statusClass(status) {
    const map = {
        pending: 'bg-slate-100 text-slate-800',
        approved: 'bg-emerald-100 text-emerald-900',
        nudged: 'bg-primary-100 text-primary-900',
        escalated: 'bg-rose-100 text-rose-900',
        suspended_pending_review: 'bg-rose-200 text-rose-950',
    };
    return map[status] || 'bg-slate-100 text-slate-700';
}
</script>
