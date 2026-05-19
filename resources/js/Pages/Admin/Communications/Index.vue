<template>
    <AdminShell title="Email Broadcasts" subtitle="Compose, target, schedule, and audit branded email campaigns from the super admin dashboard.">
        <div class="space-y-6">
            <section class="grid gap-4 md:grid-cols-3">
                <div v-for="tile in statTiles" :key="tile.label" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm ring-1 ring-slate-100 dark:border-slate-700 dark:bg-slate-800">
                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">{{ tile.label }}</p>
                    <p class="mt-2 font-display text-3xl font-black text-slate-950 dark:text-white">{{ tile.value }}</p>
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm ring-1 ring-slate-100 dark:border-slate-700 dark:bg-slate-800">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-primary-700 dark:text-primary-300">Audience builder</p>
                        <h2 class="font-display text-2xl font-black text-slate-950 dark:text-white">{{ audienceCount }} users will receive this email</h2>
                        <p class="mt-1 text-sm font-semibold text-slate-500">{{ audienceDescription }}</p>
                    </div>
                    <button type="button" class="primary-button" :disabled="audienceLoading" @click="refreshAudience">
                        <span v-if="audienceLoading" class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"></span>
                        Refresh count
                    </button>
                </div>

                <div class="mt-5 grid gap-4 xl:grid-cols-5">
                    <AudienceCard title="User group">
                        <label v-for="group in groupOptions" :key="group.value" class="choice-pill">
                            <input v-model="audience.groups" type="checkbox" :value="group.value" />
                            {{ group.label }}
                        </label>
                    </AudienceCard>
                    <AudienceCard title="Nigerian states">
                        <label v-for="state in options.states" :key="state.value" class="choice-pill">
                            <input v-model="audience.state_ids" type="checkbox" :value="state.value" />
                            {{ state.label }}
                        </label>
                    </AudienceCard>
                    <AudienceCard title="Categories">
                        <label v-for="category in options.categories" :key="category.value" class="choice-pill">
                            <input v-model="audience.category_ids" type="checkbox" :value="category.value" />
                            {{ category.label }}
                        </label>
                    </AudienceCard>
                    <AudienceCard title="Verification tiers">
                        <label v-for="tier in tierOptions" :key="tier.value" class="choice-pill">
                            <input v-model="audience.verification_tiers" type="checkbox" :value="tier.value" />
                            {{ tier.label }}
                        </label>
                    </AudienceCard>
                    <AudienceCard title="Status & activity">
                        <UiSelect v-model="audience.account_status" :options="accountStatusOptions" placeholder="Account status" />
                        <UiSelect v-model="audience.activity" :options="activityOptions" placeholder="Activity" class="mt-3" />
                    </AudienceCard>
                </div>
            </section>

            <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_24rem]">
                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm ring-1 ring-slate-100 dark:border-slate-700 dark:bg-slate-800">
                    <div class="inline-flex rounded-2xl border border-slate-200 bg-slate-50 p-1 dark:border-slate-700 dark:bg-slate-900">
                        <button v-for="mode in composeModes" :key="mode.value" type="button" class="rounded-xl px-4 py-2 text-xs font-black uppercase tracking-wide transition" :class="composeMode === mode.value ? 'bg-primary-600 text-white' : 'text-slate-600 dark:text-slate-300'" @click="composeMode = mode.value">
                            {{ mode.label }}
                        </button>
                    </div>

                    <div v-if="composeMode === 'template'" class="mt-5">
                        <UiSelect v-model="selectedTemplateKey" :options="templateOptions" placeholder="Select a broadcast template" @update:model-value="applyTemplate" />
                    </div>

                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <label class="field-label md:col-span-2">
                            <span>Subject line</span>
                            <input v-model="form.subject" class="panel-input w-full" maxlength="150" placeholder="Email subject" />
                            <small :class="form.subject.length > 60 ? 'text-amber-700' : 'text-slate-500'">{{ form.subject.length }}/150 characters</small>
                        </label>
                        <label class="field-label md:col-span-2">
                            <span>Preview text</span>
                            <input v-model="form.preview_text" class="panel-input w-full" maxlength="180" placeholder="Inbox preview snippet" />
                            <small :class="form.preview_text.length > 90 ? 'text-amber-700' : 'text-slate-500'">{{ form.preview_text.length }}/180 characters</small>
                        </label>
                        <label class="field-label">
                            <span>Reply-to address</span>
                            <input v-model="form.reply_to" class="panel-input w-full" type="email" />
                        </label>
                        <label class="field-label">
                            <span>Send from name</span>
                            <input v-model="form.from_name" class="panel-input w-full" />
                        </label>
                    </div>

                    <div class="mt-5 rounded-3xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-900/70">
                        <div class="flex flex-wrap gap-2">
                            <button v-for="tool in editorTools" :key="tool.label" type="button" class="secondary-button" @click="insertBody(tool.html)">{{ tool.label }}</button>
                        </div>
                        <UiTextarea v-model="form.body_html" class="mt-4" label="Email-safe HTML body" :min-rows="12" :maxlength="120000" placeholder="<h2>Hello {{user.first_name}}</h2><p>Write your email...</p>" />
                    </div>
                </div>

                <aside class="space-y-5">
                    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm ring-1 ring-slate-100 dark:border-slate-700 dark:bg-slate-800">
                        <div class="flex items-center justify-between gap-2">
                            <p class="font-display text-lg font-black text-slate-950 dark:text-white">Live preview</p>
                            <UiSelect v-model="previewDevice" class="w-32" :options="previewDeviceOptions" />
                        </div>
                        <div class="mt-4 rounded-3xl border border-slate-200 bg-slate-100 p-3 dark:border-slate-700 dark:bg-slate-900">
                            <div class="mx-auto rounded-2xl bg-white shadow-sm" :class="previewDevice === 'mobile' ? 'max-w-[320px]' : 'max-w-full'">
                                <div class="border-b border-slate-100 px-4 py-3">
                                    <p class="text-xs font-bold text-slate-500">Subject</p>
                                    <p class="font-black text-slate-950">{{ renderedSubject }}</p>
                                </div>
                                <div class="quest-description-html p-4 text-sm leading-6 text-slate-700" v-html="renderedBody"></div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm ring-1 ring-slate-100 dark:border-slate-700 dark:bg-slate-800">
                        <p class="font-display text-lg font-black text-slate-950 dark:text-white">Personalisation tokens</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button v-for="token in options.tokens" :key="token" type="button" class="rounded-full border border-primary-200 bg-primary-50 px-3 py-1.5 text-xs font-black text-primary-800" @click="insertBody(token)">
                                {{ token }}
                            </button>
                        </div>
                    </div>
                </aside>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm ring-1 ring-slate-100 dark:border-slate-700 dark:bg-slate-800">
                <div class="grid gap-4 lg:grid-cols-[1fr_22rem]">
                    <div class="space-y-3">
                        <p class="font-display text-xl font-black text-slate-950 dark:text-white">Scheduling</p>
                        <div class="flex flex-wrap gap-3">
                            <label class="choice-pill"><input v-model="form.send_mode" type="radio" value="now" /> Send now</label>
                            <label class="choice-pill"><input v-model="form.send_mode" type="radio" value="schedule" /> Schedule for later</label>
                        </div>
                        <PremiumDatePicker v-if="form.send_mode === 'schedule'" v-model="form.scheduled_for" placeholder="Pick send date and time" :min="today" include-time />
                        <p v-if="form.send_mode === 'schedule'" class="text-sm font-semibold text-slate-500">This email will be sent to {{ audienceCount }} users on {{ form.scheduled_for || 'the selected date and time' }} WAT.</p>
                    </div>
                    <div class="rounded-3xl border border-primary-100 bg-primary-50 p-4 text-primary-950 dark:border-primary-400/20 dark:bg-primary-500/10 dark:text-primary-100">
                        <p class="text-sm font-black">Sending to {{ audienceCount }} users</p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button type="button" class="secondary-button" :disabled="busy" @click="sendTest">Test send</button>
                            <button type="button" class="primary-button" :disabled="busy || audienceCount < 1 || !form.subject || !form.body_html" @click="sendBroadcast">
                                <span v-if="busy" class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"></span>
                                {{ busy ? sendProgress : (form.send_mode === 'schedule' ? 'Schedule broadcast' : 'Send broadcast') }}
                            </button>
                        </div>
                        <p v-if="sendProgress" class="mt-3 text-xs font-bold">{{ sendProgress }}</p>
                    </div>
                </div>
            </section>

            <section class="grid gap-5 xl:grid-cols-2">
                <AdminList title="Template Manager" :items="templates" empty="No templates yet.">
                    <template #default="{ item }">
                        <p class="font-black text-slate-950 dark:text-white">{{ item.name }} <span class="text-xs text-slate-500">({{ item.is_system ? 'System' : 'Custom' }})</span></p>
                        <p class="text-sm font-semibold text-slate-500">{{ item.category }} · {{ item.suggested_audience }}</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button class="secondary-button" type="button" @click="loadTemplate(item)">Preview/Edit</button>
                            <button class="secondary-button" type="button" @click="duplicateTemplate(item)">Duplicate</button>
                            <button v-if="!item.is_system" class="danger-button" type="button" @click="deleteTemplate(item)">Delete</button>
                        </div>
                    </template>
                </AdminList>
                <AdminList title="Send History & Scheduled Queue" :items="[...scheduled, ...history]" empty="No broadcasts yet.">
                    <template #default="{ item }">
                        <p class="font-black text-slate-950 dark:text-white">{{ item.subject }}</p>
                        <p class="text-sm font-semibold text-slate-500">{{ item.audience_description }} · {{ item.total_recipients }} recipients · {{ item.status || 'scheduled' }}</p>
                        <div v-if="item.stats" class="mt-2 grid grid-cols-3 gap-2 text-xs font-bold text-slate-600">
                            <span>Sent {{ item.stats.sent }}</span>
                            <span>Opened {{ item.stats.opened }}</span>
                            <span>Clicked {{ item.stats.clicked }}</span>
                        </div>
                        <button v-if="item.replay" class="secondary-button mt-3" type="button" @click="replayBroadcast(item)">Resend</button>
                    </template>
                </AdminList>
            </section>
        </div>

        <div class="fixed bottom-5 right-5 z-50 space-y-2">
            <div v-for="toast in toasts" :key="toast.id" class="flex max-w-sm items-start gap-3 rounded-2xl border px-4 py-3 text-sm font-bold shadow-2xl" :class="toast.type === 'error' ? 'border-rose-200 bg-rose-50 text-rose-900' : 'border-emerald-200 bg-emerald-50 text-emerald-900'">
                <span>{{ toast.message }}</span>
                <button type="button" class="ml-auto" @click="dismissToast(toast.id)">×</button>
            </div>
        </div>
    </AdminShell>
</template>

<script setup>
import AdminShell from '@/Layouts/AdminShell.vue';
import PremiumDatePicker from '@/Components/Ui/PremiumDatePicker.vue';
import UiSelect from '@/Components/Ui/UiSelect.vue';
import UiTextarea from '@/Components/Ui/UiTextarea.vue';
import axios from 'axios';
import { computed, defineComponent, h, reactive, ref, watch } from 'vue';

const props = defineProps({
    templates: { type: Array, default: () => [] },
    history: { type: Array, default: () => [] },
    scheduled: { type: Array, default: () => [] },
    options: { type: Object, required: true },
    stats: { type: Object, default: () => ({}) },
});

const templates = computed(() => props.templates);
const history = computed(() => props.history);
const scheduled = computed(() => props.scheduled);
const options = computed(() => props.options);

const groupOptions = [
    { value: 'all_users', label: 'All Users' },
    { value: 'clients', label: 'All Clients' },
    { value: 'freelancers', label: 'All Freelancers' },
    { value: 'admins', label: 'All Admins' },
    { value: 'super_admins', label: 'Super Admins Only' },
];
const tierOptions = [0, 1, 2, 3, 4, 5].map((tier) => ({ value: `Tier ${tier}`, label: `Tier ${tier}` }));
const accountStatusOptions = [
    { value: 'active', label: 'Active accounts only' },
    { value: 'suspended', label: 'Suspended accounts' },
    { value: 'pending_verification', label: 'Pending verification' },
];
const activityOptions = [
    { value: '', label: 'Any activity' },
    { value: 'active_30', label: 'Active in last 30 days' },
    { value: 'inactive_60', label: 'Inactive over 60 days' },
    { value: 'completed_contract', label: 'Completed at least one contract' },
    { value: 'never_started_contract', label: 'Never started a contract' },
];
const composeModes = [{ value: 'template', label: 'Template Mode' }, { value: 'custom', label: 'Custom Mode' }];
const previewDeviceOptions = [{ value: 'desktop', label: 'Desktop' }, { value: 'mobile', label: 'Mobile' }];
const editorTools = [
    { label: 'H2', html: '<h2>Heading</h2>' },
    { label: 'H3', html: '<h3>Subheading</h3>' },
    { label: 'Bold', html: '<strong>Bold text</strong>' },
    { label: 'List', html: '<ul><li>First point</li><li>Second point</li></ul>' },
    { label: 'Link', html: '<a href="https://">Link text</a>' },
    { label: 'Button', html: '<p><a href="{{platform.url}}" style="display:inline-block;padding:12px 18px;border-radius:12px;text-decoration:none;font-weight:700;">Button label</a></p>' },
    { label: 'Divider', html: '<hr />' },
];

const today = new Date().toISOString().slice(0, 10);
const composeMode = ref('template');
const selectedTemplateKey = ref('');
const previewDevice = ref('desktop');
const audienceLoading = ref(false);
const busy = ref(false);
const sendProgress = ref('');
const audienceCount = ref(0);
const audienceDescription = ref('All Users');
const toasts = ref([]);
const audience = reactive({ groups: ['all_users'], state_ids: [], category_ids: [], verification_tiers: [], account_status: 'active', activity: '' });
const form = reactive({
    template_id: null,
    subject: '',
    preview_text: '',
    reply_to: '',
    from_name: 'HustleSafe',
    body_html: '',
    send_mode: 'now',
    scheduled_for: '',
});

const statTiles = computed(() => [
    { label: 'Broadcasts sent', value: props.stats.total_sent || 0 },
    { label: 'Scheduled sends', value: props.stats.scheduled || 0 },
    { label: 'Templates', value: props.stats.templates || props.templates.length },
]);
const templateOptions = computed(() => props.templates.map((template) => ({ value: template.key, label: `${template.name} · ${template.category}` })));
const renderedSubject = computed(() => renderTokens(form.subject || 'Your subject line'));
const renderedBody = computed(() => renderTokens(form.body_html || '<p>Your email preview will appear here.</p>'));

watch(audience, debounce(refreshAudience, 450), { deep: true });

function applyTemplate(key) {
    const template = props.templates.find((item) => item.key === key);
    if (!template) return;
    form.template_id = template.id;
    form.subject = template.subject;
    form.preview_text = template.preview_text || '';
    form.body_html = template.body_html;
}

function loadTemplate(template) {
    selectedTemplateKey.value = template.key;
    applyTemplate(template.key);
    composeMode.value = 'template';
}

function duplicateTemplate(template) {
    form.subject = template.subject;
    form.preview_text = template.preview_text || '';
    form.body_html = template.body_html;
    toast('Template duplicated into the composer. Save as a custom template when ready.');
}

async function deleteTemplate(template) {
    if (!window.confirm(`Delete custom template "${template.name}"?`)) return;
    await axios.delete(route('admin.communications.email-broadcasts.templates.destroy', template.id));
    toast('Template deleted.');
    window.location.reload();
}

function replayBroadcast(item) {
    Object.assign(audience, item.replay.audience || audience);
    Object.assign(form, item.replay, { send_mode: 'now', scheduled_for: '' });
    toast('Previous broadcast loaded for reuse.');
}

async function refreshAudience() {
    audienceLoading.value = true;
    try {
        const { data } = await axios.post(route('admin.communications.email-broadcasts.audience'), { audience });
        audienceCount.value = data.count;
        audienceDescription.value = data.description;
    } finally {
        audienceLoading.value = false;
    }
}

async function sendTest() {
    busy.value = true;
    try {
        await axios.post(route('admin.communications.email-broadcasts.test'), payload());
        toast('Test email sent.');
    } catch (error) {
        toast(error.response?.data?.message || 'Could not send test email.', 'error');
    } finally {
        busy.value = false;
    }
}

async function sendBroadcast() {
    busy.value = true;
    sendProgress.value = 'Queueing recipients...';
    try {
        const { data } = await axios.post(route('admin.communications.email-broadcasts.send'), payload());
        sendProgress.value = `Queued ${data.broadcast.total_recipients} recipients.`;
        toast(data.message || 'Broadcast queued.');
    } catch (error) {
        toast(error.response?.data?.message || 'Broadcast failed. Please review the form.', 'error');
    } finally {
        busy.value = false;
    }
}

function payload() {
    return { ...form, audience: { ...audience } };
}

function insertBody(value) {
    form.body_html = `${form.body_html || ''}${form.body_html ? '\n' : ''}${value}`;
}

function renderTokens(value) {
    return String(value || '')
        .replaceAll('{{user.first_name}}', 'Ada')
        .replaceAll('{{user.last_name}}', 'Okafor')
        .replaceAll('{{user.email}}', 'ada@example.com')
        .replaceAll('{{user.verification_tier}}', 'Tier 2')
        .replaceAll('{{platform.name}}', 'HustleSafe')
        .replaceAll('{{platform.url}}', 'https://hustlesafe.test')
        .replaceAll('{{unsubscribe_link}}', '#unsubscribe');
}

function toast(message, type = 'success') {
    const id = Date.now() + Math.random();
    toasts.value.push({ id, message, type });
    window.setTimeout(() => dismissToast(id), 4000);
}

function dismissToast(id) {
    toasts.value = toasts.value.filter((item) => item.id !== id);
}

function debounce(fn, wait) {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), wait);
    };
}

const AudienceCard = defineComponent({
    props: { title: String },
    setup(props, { slots }) {
        return () => h('div', { class: 'max-h-72 overflow-y-auto rounded-3xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-900/70' }, [
            h('p', { class: 'mb-3 text-[11px] font-black uppercase tracking-[0.18em] text-slate-500' }, props.title),
            h('div', { class: 'space-y-2' }, slots.default?.()),
        ]);
    },
});

const AdminList = defineComponent({
    props: { title: String, items: Array, empty: String },
    setup(props, { slots }) {
        return () => h('div', { class: 'rounded-3xl border border-slate-200 bg-white p-5 shadow-sm ring-1 ring-slate-100 dark:border-slate-700 dark:bg-slate-800' }, [
            h('p', { class: 'font-display text-xl font-black text-slate-950 dark:text-white' }, props.title),
            h('div', { class: 'mt-4 space-y-3' }, props.items?.length ? props.items.map((item) => h('article', { class: 'rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-900/70' }, slots.default?.({ item }))) : [h('p', { class: 'text-sm font-semibold text-slate-500' }, props.empty)]),
        ]);
    },
});

refreshAudience();
</script>

<style scoped>
.primary-button {
    @apply inline-flex min-h-11 items-center justify-center rounded-xl bg-primary-600 px-4 text-xs font-black uppercase tracking-wide text-white shadow-lg shadow-primary-900/20 transition hover:bg-primary-700 disabled:opacity-50;
}
.secondary-button {
    @apply inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-3 text-xs font-black uppercase tracking-wide text-slate-700 shadow-sm transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-800 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200;
}
.danger-button {
    @apply inline-flex min-h-11 items-center justify-center rounded-xl border border-rose-200 bg-rose-50 px-3 text-xs font-black uppercase tracking-wide text-rose-700 transition hover:bg-rose-100;
}
.panel-input {
    @apply min-h-11 rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-950 outline-none ring-2 ring-transparent transition placeholder:text-slate-400 focus:border-primary-400/60 focus:ring-primary-500/25;
}
.field-label {
    @apply flex flex-col gap-1 text-[11px] font-black uppercase tracking-[0.16em] text-slate-600;
}
.choice-pill {
    @apply flex min-h-11 items-center gap-2 rounded-2xl border border-slate-200 bg-white px-3 text-sm font-bold text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200;
}
</style>
