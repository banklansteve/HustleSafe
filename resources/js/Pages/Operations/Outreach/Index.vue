<template>
    <component :is="shellComponent" title="Proactive outreach queue" subtitle="Retention and trust situations that benefit from a human touch — work with templated, personalised outreach.">
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <div v-if="counts.total" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700">
                {{ counts.total }} open · {{ counts.urgent }} urgent · {{ counts.unassigned }} unassigned
            </div>
            <button v-for="f in situations" :key="f.key" type="button" class="rounded-lg px-3 py-2 text-xs font-black uppercase transition active:scale-[0.98]" :class="activeSituation === f.key ? 'bg-primary-700 text-white shadow-md' : 'border border-slate-200 bg-white text-slate-700'" @click="setSituation(f.key)">{{ f.label }}</button>
            <select v-model="activePriority" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-800" @change="reload">
                <option v-for="p in priorities" :key="p.key" :value="p.key">{{ p.label }}</option>
            </select>
            <Link v-if="can_manage_templates" :href="templatesHref" class="ml-auto rounded-lg border border-primary-200 bg-primary-50 px-3 py-2 text-xs font-black uppercase text-primary-900 active:scale-[0.98]">Template library</Link>
        </div>

        <OperationsQueueTable
            :columns="columns"
            :rows="queue.pageItems.value"
            :loading="loading"
            v-model:search="queue.search.value"
            v-model:per-page="queue.perPage.value"
            :page="queue.page.value"
            :total="queue.total.value"
            :total-pages="queue.totalPages.value"
            :sort-key="queue.sortKey.value"
            :sort-dir="queue.sortDir.value"
            empty-message="No open outreach items. The hourly scan will surface new situations."
            @sort="queue.setSort"
            @page="(p) => (queue.page.value = p)"
            @open="openItem"
        >
            <template #cell-priority="{ row }">
                <span class="rounded-full px-2 py-0.5 text-[10px] font-black uppercase" :class="priorityClass(row.priority)">{{ row.priority }}</span>
            </template>
            <template #cell-user="{ row }">
                <span class="font-semibold text-slate-950">{{ row.user?.name }}</span>
                <span class="block text-xs text-slate-500">{{ row.user?.email }}</span>
            </template>
            <template #cell-situation_label="{ row }">
                <span class="text-sm font-semibold text-slate-900">{{ row.situation_label }}</span>
                <span class="block text-xs text-slate-500">{{ row.situation_hint }}</span>
            </template>
            <template #cell-quest="{ row }">
                <span v-if="row.quest" class="text-sm font-semibold text-slate-800">{{ row.quest.title }}</span>
                <span v-else class="text-xs text-slate-400">—</span>
            </template>
            <template #cell-detected_at="{ row }">
                <span class="text-sm font-semibold text-slate-600">{{ formatHumanDateTime(row.detected_at) }}</span>
            </template>
            <template #actions="{ row }">
                <button type="button" class="rounded-lg bg-primary-700 px-2 py-1 text-[10px] font-black uppercase text-white active:scale-[0.98]" @click.stop="openItem(row)">Work</button>
            </template>
        </OperationsQueueTable>

        <OperationsSlideOver :open="slideOpen" :title="detail?.user?.name || 'Outreach'" :subtitle="detail?.item?.situation_label" eyebrow="Proactive outreach" @close="slideOpen = false">
            <div v-if="detail" class="space-y-3">
                <div class="grid grid-cols-2 gap-2 rounded-xl border border-slate-100 bg-slate-50 p-3 text-sm">
                    <div><p class="text-[10px] font-black uppercase text-slate-400">Priority</p><p class="font-black capitalize text-slate-900">{{ detail.item.priority }}</p></div>
                    <div><p class="text-[10px] font-black uppercase text-slate-400">Status</p><p class="font-black capitalize text-slate-900">{{ detail.item.status }}</p></div>
                    <div v-if="detail.quest" class="col-span-2"><p class="text-[10px] font-black uppercase text-slate-400">Quest</p><p class="font-semibold text-slate-800">{{ detail.quest.title }} · {{ detail.quest.reference_code }}</p></div>
                </div>

                <div v-if="detail.history?.length" class="rounded-xl border border-slate-100 bg-white p-3">
                    <p class="text-[10px] font-black uppercase text-slate-400">Previous outreach</p>
                    <ul class="mt-2 space-y-1 text-xs text-slate-600">
                        <li v-for="h in detail.history" :key="h.id">{{ formatHumanDateTime(h.sent_at) }} · {{ h.staff }} · {{ h.subject }}</li>
                    </ul>
                </div>

                <OperationsExpandableAction title="Send outreach" icon="✉" submit-label="Send message" :busy="busy.contact" @submit="sendOutreach">
                    <div class="space-y-2">
                        <label class="block text-xs font-bold text-slate-600">
                            Template
                            <select v-model="selectedTemplateId" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-semibold text-slate-900" @change="applyTemplate">
                                <option value="">Custom message</option>
                                <option v-for="t in detail.templates" :key="t.id" :value="t.id">{{ t.title }}</option>
                            </select>
                        </label>
                        <OperationsFormField v-model="outreachForm.subject" label="Subject" />
                        <OperationsFormField v-model="outreachForm.body" label="Message" multiline :rows="8" />
                        <label class="block text-xs font-bold text-slate-600">
                            Channel
                            <select v-model="outreachForm.channel" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-semibold text-slate-900">
                                <option value="both">Email + in-app</option>
                                <option value="email">Email only</option>
                                <option value="in_app">In-app only</option>
                            </select>
                        </label>
                    </div>
                </OperationsExpandableAction>

                <div class="grid grid-cols-2 gap-2">
                    <button type="button" class="rounded-xl border border-slate-200 bg-white py-2.5 text-sm font-black text-slate-800 active:scale-[0.98]" :disabled="busy.assign" @click="assignToMe">Assign to me</button>
                    <button type="button" class="rounded-xl border border-amber-200 bg-amber-50 py-2.5 text-sm font-black text-amber-900 active:scale-[0.98]" :disabled="busy.snooze" @click="snoozeItem">Snooze 3d</button>
                </div>
                <button type="button" class="w-full rounded-xl border border-emerald-200 bg-emerald-50 py-2.5 text-sm font-black text-emerald-900 active:scale-[0.98]" :disabled="busy.resolve" @click="resolveItem">Mark resolved</button>
            </div>
        </OperationsSlideOver>
    </component>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AdminShell from '@/Layouts/AdminShell.vue';
import OperationsExpandableAction from '@/Pages/Operations/Components/OperationsExpandableAction.vue';
import OperationsFormField from '@/Pages/Operations/Components/OperationsFormField.vue';
import OperationsQueueTable from '@/Pages/Operations/Components/OperationsQueueTable.vue';
import OperationsSlideOver from '@/Pages/Operations/Components/OperationsSlideOver.vue';
import OperationsShell from '@/Layouts/OperationsShell.vue';
import { useClientQueue } from '@/composables/useClientQueue';
import { useOperationsAction } from '@/composables/useOperationsAction';
import { formatHumanDateTime } from '@/utils/formatHumanDateTime';

const props = defineProps({
    can_manage_templates: { type: Boolean, default: false },
    route_prefix: { type: String, default: 'operations' },
    use_admin_shell: { type: Boolean, default: false },
});

const shellComponent = computed(() => (props.use_admin_shell ? AdminShell : OperationsShell));
const api = (name, params) => route(`${props.route_prefix}.${name}`, params);
const templatesHref = computed(() => route(`${props.route_prefix}.response-templates.index`));

const columns = [
    { key: 'priority', label: 'Priority' },
    { key: 'situation_label', label: 'Situation' },
    { key: 'user', label: 'User' },
    { key: 'quest', label: 'Quest' },
    { key: 'priority_score', label: 'Score' },
    { key: 'detected_at', label: 'Detected' },
    { key: 'status', label: 'Status' },
];

const rawItems = ref([]);
const situations = ref([{ key: '', label: 'All situations' }]);
const priorities = ref([{ key: '', label: 'All priorities' }]);
const counts = ref({ total: 0, urgent: 0, unassigned: 0 });
const loading = ref(false);
const activeSituation = ref('');
const activePriority = ref('');
const slideOpen = ref(false);
const detail = ref(null);
const selectedUuid = ref(null);
const selectedTemplateId = ref('');
const outreachForm = reactive({ subject: '', body: '', channel: 'both' });
const queue = useClientQueue(() => rawItems.value, {
    searchFields: ['user.name', 'user.email', 'situation_label', 'quest.title', 'quest.reference_code'],
    defaultSortKey: 'priority_score',
    defaultSortDir: 'desc',
});
const { busy, runAction } = useOperationsAction();

onMounted(reload);

function setSituation(key) {
    activeSituation.value = key;
    reload();
}

function priorityClass(priority) {
    if (priority === 'urgent') return 'bg-rose-100 text-rose-900';
    if (priority === 'high') return 'bg-amber-100 text-amber-900';
    if (priority === 'medium') return 'bg-sky-100 text-sky-900';
    return 'bg-slate-100 text-slate-700';
}

async function reload() {
    loading.value = true;
    try {
        const { data } = await window.axios.get(api('api.outreach.listing'), {
            params: {
                situation: activeSituation.value || undefined,
                priority: activePriority.value || undefined,
                q: queue.search.value || undefined,
            },
        });
        rawItems.value = data.items ?? [];
        situations.value = data.situations ?? situations.value;
        priorities.value = data.priorities ?? priorities.value;
        counts.value = data.counts ?? counts.value;
    } finally {
        loading.value = false;
    }
}

async function openItem(row) {
    selectedUuid.value = row.uuid;
    slideOpen.value = true;
    const { data } = await window.axios.get(api('api.outreach.detail', row.uuid));
    detail.value = data;
    selectedTemplateId.value = '';
    outreachForm.subject = data.default_template?.subject ?? '';
    outreachForm.body = data.default_template?.body ?? '';
}

function applyTemplate() {
    const template = detail.value?.templates?.find((t) => String(t.id) === String(selectedTemplateId.value));
    if (!template?.preview) return;
    outreachForm.subject = template.preview.subject;
    outreachForm.body = template.preview.body;
}

async function sendOutreach() {
    await runAction('contact', () => window.axios.post(api('api.outreach.contact', selectedUuid.value), {
        ...outreachForm,
        template_id: selectedTemplateId.value || undefined,
    }), 'Outreach sent.', () => {
        slideOpen.value = false;
        reload();
    });
}

async function assignToMe() {
    await runAction('assign', () => window.axios.post(api('api.outreach.assign', selectedUuid.value)), 'Assigned.');
}

async function snoozeItem() {
    await runAction('snooze', () => window.axios.post(api('api.outreach.snooze', selectedUuid.value), { days: 3 }), 'Snoozed.', () => {
        slideOpen.value = false;
        reload();
    });
}

async function resolveItem() {
    await runAction('resolve', () => window.axios.post(api('api.outreach.resolve', selectedUuid.value)), 'Resolved.', () => {
        slideOpen.value = false;
        reload();
    });
}
</script>
