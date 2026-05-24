<template>
    <AdminShell
        title="SEO & Content Management"
        subtitle="A calmer editorial workspace for email templates, announcements, help content, and future SEO publishing tools."
    >
        <div class="space-y-6">
            <section class="rounded-[2rem] border border-amber-100 bg-gradient-to-br from-amber-50 via-white to-orange-50 p-5 shadow-sm dark:border-amber-300/20 dark:from-amber-400/10 dark:via-slate-950 dark:to-orange-400/10 md:p-7">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-3xl">
                        <p class="text-xs font-black uppercase tracking-[0.2em] text-amber-700 dark:text-amber-200">Editorial control room</p>
                        <h2 class="mt-3 font-display text-3xl font-black tracking-tight text-slate-950 dark:text-white md:text-4xl">Write, preview, publish, and improve user-facing content without touching code.</h2>
                        <p class="mt-3 text-sm font-semibold leading-7 text-slate-600 dark:text-slate-300">Autosave-ready editors, friendly checklists, warm review panels, and last-10 version history are built into the workspace.</p>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-3 lg:w-[28rem]">
                        <div v-for="item in seoChecklist" :key="item.label" class="rounded-3xl border bg-white/80 p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
                            <p class="text-xl">{{ item.done ? '✓' : '!' }}</p>
                            <p class="mt-2 text-sm font-black text-slate-900 dark:text-white">{{ item.label }}</p>
                            <p class="mt-1 text-xs font-semibold text-slate-500 dark:text-slate-400">{{ item.note }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <AdminTabbedPage v-model="activeTab" :tabs="tabs" id-prefix="content-tab" aria-label="Content management sections">
            <AdminTabPanel :current-tab="activeTab" value="email" id-prefix="content-tab" class="grid gap-5 xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.4fr)]">
                <AdminPanel title="System email templates" description="Every transactional email, its trigger, subject line, and last editor. Amber rows need subject-line attention because open rate is below 20%.">
                    <div class="space-y-3">
                        <button
                            v-for="template in email.templates"
                            :key="template.id"
                            type="button"
                            class="w-full rounded-3xl border p-4 text-left transition hover:-translate-y-0.5 hover:shadow-md"
                            :class="selectedTemplate?.id === template.id ? 'border-amber-300 bg-amber-50 dark:border-amber-300/40 dark:bg-amber-400/10' : 'border-slate-200 bg-white dark:border-white/10 dark:bg-white/5'"
                            @click="loadTemplate(template.id)"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-black text-slate-950 dark:text-white">{{ template.name }}</p>
                                    <p class="mt-1 text-xs font-bold text-slate-500 dark:text-slate-400">{{ template.trigger_event }}</p>
                                </div>
                                <span v-if="template.analytics?.needs_attention" class="rounded-full bg-amber-100 px-3 py-1 text-[10px] font-black uppercase text-amber-800">Improve</span>
                            </div>
                            <p class="mt-3 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ template.subject }}</p>
                            <div class="mt-3 flex flex-wrap gap-2 text-[11px] font-black text-slate-500 dark:text-slate-400">
                                <span>Edited by {{ template.last_edited_by }}</span>
                                <span>{{ dateLabel(template.last_edited_at) }}</span>
                                <span>Open {{ template.analytics?.open_rate ?? 0 }}%</span>
                            </div>
                        </button>
                    </div>
                </AdminPanel>

                <div class="space-y-5">
                    <AdminPanel title="Visual email editor" description="Live preview updates as you edit. Dynamic variables stay available beside the editor so admins can write confidently.">
                        <template #actions>
                            <span class="rounded-full px-3 py-1 text-xs font-black" :class="saveIndicatorClass">{{ saveIndicator }}</span>
                        </template>
                        <div v-if="selectedTemplate" class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(22rem,0.9fr)]">
                            <form class="space-y-4" @submit.prevent="saveTemplate">
                                <label class="block">
                                    <span class="text-xs font-black uppercase tracking-wide text-slate-500">Subject line</span>
                                    <input v-model="templateForm.subject" class="mt-2 w-full rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" @input="markUnsaved" />
                                </label>
                                <label class="block">
                                    <span class="text-xs font-black uppercase tracking-wide text-slate-500">Preheader</span>
                                    <input v-model="templateForm.preheader" class="mt-2 w-full rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" @input="markUnsaved" />
                                </label>
                                <div class="grid gap-3 sm:grid-cols-3">
                                    <label class="block">
                                        <span class="text-xs font-black uppercase tracking-wide text-slate-500">Logo text</span>
                                        <input v-model="templateForm.theme.logo" class="mt-2 w-full rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" @input="markUnsaved" />
                                    </label>
                                    <label class="block">
                                        <span class="text-xs font-black uppercase tracking-wide text-slate-500">Primary colour</span>
                                        <input v-model="templateForm.theme.primary_color" type="color" class="mt-2 h-12 w-full rounded-2xl border p-1" :class="shell.input" @input="markUnsaved" />
                                    </label>
                                    <label class="block">
                                        <span class="text-xs font-black uppercase tracking-wide text-slate-500">Footer</span>
                                        <input v-model="templateForm.theme.footer" class="mt-2 w-full rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" @input="markUnsaved" />
                                    </label>
                                </div>

                                <div class="space-y-3">
                                    <div class="flex items-center justify-between">
                                        <p class="text-xs font-black uppercase tracking-wide text-slate-500">Section blocks</p>
                                        <div class="flex flex-wrap gap-2">
                                            <button v-for="type in blockTypes" :key="type" type="button" class="rounded-full bg-amber-100 px-3 py-1 text-xs font-black text-amber-900" @click="addBlock(type)">+ {{ type }}</button>
                                        </div>
                                    </div>
                                    <div v-for="(block, index) in templateForm.blocks" :key="index" class="rounded-3xl border border-slate-200 bg-slate-50/70 p-4 dark:border-white/10 dark:bg-white/5">
                                        <div class="mb-3 flex items-center justify-between gap-2">
                                            <span class="rounded-full bg-white px-3 py-1 text-[11px] font-black uppercase text-slate-600 shadow-sm dark:bg-white/10 dark:text-slate-200">{{ block.type }}</span>
                                            <div class="flex gap-2">
                                                <button type="button" class="text-xs font-black text-slate-500" @click="moveBlock(index, -1)">Up</button>
                                                <button type="button" class="text-xs font-black text-slate-500" @click="moveBlock(index, 1)">Down</button>
                                                <button type="button" class="text-xs font-black text-rose-600" @click="removeBlock(index)">Remove</button>
                                            </div>
                                        </div>
                                        <textarea v-if="block.type === 'text'" v-model="block.content" rows="4" class="w-full rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" @input="markUnsaved" />
                                        <div v-else-if="block.type === 'button'" class="grid gap-3 sm:grid-cols-2">
                                            <input v-model="block.label" placeholder="Button text" class="rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" @input="markUnsaved" />
                                            <input v-model="block.url" placeholder="Button URL or variable" class="rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" @input="markUnsaved" />
                                        </div>
                                        <input v-else-if="block.type === 'image'" v-model="block.url" placeholder="Image URL" class="w-full rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" @input="markUnsaved" />
                                        <p v-else class="text-sm font-semibold text-slate-500">Divider block</p>
                                    </div>
                                </div>

                                <div class="flex flex-col gap-3 sm:flex-row">
                                    <button type="submit" class="rounded-2xl px-5 py-3 text-sm font-black text-white" :class="warmBtn">Save template</button>
                                    <button type="button" class="rounded-2xl border px-5 py-3 text-sm font-black" :class="shell.btnGhost" @click="sendTest">Send test email</button>
                                    <input v-model="testEmail" type="email" placeholder="test@example.com" class="min-w-0 flex-1 rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                                </div>
                            </form>

                            <aside class="space-y-4">
                                <div class="rounded-3xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-white/5">
                                    <p class="text-xs font-black uppercase tracking-wide text-slate-500">Live preview</p>
                                    <iframe title="Email preview" class="mt-3 h-[520px] w-full rounded-2xl border border-slate-200 bg-white" :srcdoc="localPreviewHtml"></iframe>
                                </div>
                                <div class="rounded-3xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-white/5">
                                    <p class="text-xs font-black uppercase tracking-wide text-slate-500">Variables reference</p>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <span v-for="variable in selectedTemplate.variables" :key="variable.token" class="rounded-full bg-teal-50 px-3 py-1 text-xs font-black text-teal-800" :title="variable.description">{{ variable.token }}</span>
                                    </div>
                                </div>
                            </aside>
                        </div>
                        <p v-else class="rounded-3xl bg-slate-50 p-6 text-sm font-semibold text-slate-500 dark:bg-white/5">Choose a template to start editing.</p>
                    </AdminPanel>

                    <AdminPanel v-if="selectedTemplate" title="Delivery analytics and versions" description="Analytics use the configured provider when imported. Versions keep the last 10 saved snapshots for one-click restore.">
                        <div class="grid gap-3 md:grid-cols-3">
                            <div v-for="metric in emailMetrics" :key="metric.label" class="rounded-3xl border p-4" :class="metric.warn ? 'border-amber-200 bg-amber-50 text-amber-950' : shell.card">
                                <p class="text-xs font-black uppercase tracking-wide">{{ metric.label }}</p>
                                <p class="mt-2 text-2xl font-black">{{ metric.value }}</p>
                                <p class="mt-1 text-xs font-semibold opacity-75">{{ metric.note }}</p>
                            </div>
                        </div>
                        <div class="mt-5 grid gap-3 md:grid-cols-2">
                            <button v-for="version in selectedTemplate.versions" :key="version.id" type="button" class="rounded-3xl border p-4 text-left text-sm" :class="shell.card" @click="restoreTemplate(version)">
                                <p class="font-black">{{ version.subject }}</p>
                                <p class="mt-1 text-xs font-bold" :class="shell.cardMuted">{{ dateLabel(version.created_at) }} by {{ version.created_by || 'System' }}</p>
                                <p class="mt-2 text-xs font-semibold" :class="shell.cardMuted">{{ version.change_note }}</p>
                            </button>
                        </div>
                    </AdminPanel>
                </div>
            </AdminTabPanel>

            <AdminTabPanel :current-tab="activeTab" value="announcements" id-prefix="content-tab" class="grid gap-5 xl:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)]">
                <AdminPanel title="Create announcement" description="Only one active banner can overlap for the same user segment. Critical notices can be non-dismissible.">
                    <form class="space-y-4" @submit.prevent="saveAnnouncement">
                        <textarea v-model="announcementForm.message" required rows="4" placeholder="🎉 We’re now accepting Quests in the Legal category!" class="w-full rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                        <div class="grid gap-3 sm:grid-cols-2">
                            <input v-model="announcementForm.link_text" placeholder="Link text" class="rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                            <input v-model="announcementForm.link_url" placeholder="https://..." class="rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                            <select v-model="announcementForm.color" class="rounded-2xl border px-4 py-3 text-sm font-black" :class="shell.input">
                                <option value="info">Info blue</option>
                                <option value="success">Success green</option>
                                <option value="warning">Warning amber</option>
                                <option value="alert">Alert red</option>
                                <option value="brand">Brand colour</option>
                            </select>
                            <select v-model="announcementForm.segment" class="rounded-2xl border px-4 py-3 text-sm font-black" :class="shell.input">
                                <option value="all">All users</option>
                                <option value="clients">Clients only</option>
                                <option value="freelancers">Freelancers only</option>
                                <option value="unverified">Unverified users only</option>
                            </select>
                            <input v-model="announcementForm.starts_at" type="datetime-local" class="rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                            <input v-model="announcementForm.ends_at" type="datetime-local" class="rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                        </div>
                        <label class="flex items-center gap-3 rounded-2xl bg-slate-50 p-3 text-sm font-bold dark:bg-white/5">
                            <input v-model="announcementForm.dismissible" type="checkbox" class="rounded border-slate-300 text-amber-600" />
                            Users can dismiss this banner
                        </label>
                        <button type="submit" class="w-full rounded-2xl px-5 py-3 text-sm font-black text-white" :class="warmBtn">Save announcement</button>
                    </form>
                </AdminPanel>
                <AdminPanel title="Announcement banners" description="Scheduled, active, expired, and archived site-wide messages.">
                    <div class="space-y-3">
                        <article v-for="banner in announcements.banners?.data || []" :key="banner.id" class="rounded-3xl border p-4" :class="shell.card">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="font-black" :class="shell.title">{{ banner.message }}</p>
                                    <p class="mt-1 text-xs font-bold capitalize" :class="shell.cardMuted">{{ banner.segment }} · {{ banner.color }} · {{ banner.status }}</p>
                                    <p class="mt-2 text-xs font-semibold" :class="shell.cardMuted">{{ dateLabel(banner.starts_at) }} → {{ dateLabel(banner.ends_at) || 'No expiry' }}</p>
                                </div>
                                <button type="button" class="rounded-2xl bg-rose-50 px-4 py-2 text-xs font-black text-rose-700" @click="archiveAnnouncement(banner)">Archive</button>
                            </div>
                        </article>
                    </div>
                </AdminPanel>
            </AdminTabPanel>

            <AdminTabPanel :current-tab="activeTab" value="help" id-prefix="content-tab" class="grid gap-5 xl:grid-cols-[minmax(0,1.2fr)_minmax(20rem,0.7fr)]">
                <AdminPanel title="FAQ & help content" description="Organise sections, edit rich answers, add search keywords, and archive stale answers without deleting history.">
                    <form class="mb-5 grid gap-3 rounded-3xl border border-amber-100 bg-amber-50/60 p-4 dark:border-amber-300/20 dark:bg-amber-400/10" @submit.prevent="saveFaq">
                        <div class="grid gap-3 md:grid-cols-2">
                            <select v-model="faqForm.help_section_id" required class="rounded-2xl border px-4 py-3 text-sm font-black" :class="shell.input">
                                <option value="">Choose section</option>
                                <option v-for="sectionItem in help.sections" :key="sectionItem.id" :value="sectionItem.id">{{ sectionItem.title }}</option>
                            </select>
                            <select v-model="faqForm.audience" class="rounded-2xl border px-4 py-3 text-sm font-black" :class="shell.input">
                                <option value="all">All users</option>
                                <option value="clients">Clients only</option>
                                <option value="freelancers">Freelancers only</option>
                            </select>
                        </div>
                        <input v-model="faqForm.question" required placeholder="Question" class="rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                        <textarea v-model="faqForm.answer" required rows="7" placeholder="Rich text answer. Supports bold, links, lists, and inline code in the public renderer." class="rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                        <input v-model="keywordsText" placeholder="Search keywords separated by commas" class="rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                        <div class="flex flex-col gap-3 sm:flex-row">
                            <button type="submit" class="rounded-2xl px-5 py-3 text-sm font-black text-white" :class="warmBtn">{{ editingFaq ? 'Update FAQ' : 'Add FAQ' }}</button>
                            <button v-if="editingFaq" type="button" class="rounded-2xl border px-5 py-3 text-sm font-black" :class="shell.btnGhost" @click="resetFaq">Cancel edit</button>
                        </div>
                    </form>

                    <div class="space-y-4">
                        <section v-for="sectionItem in help.sections" :key="sectionItem.id" class="rounded-3xl border p-4" :class="shell.card">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="font-black" :class="shell.title">{{ sectionItem.title }}</p>
                                    <p class="text-xs font-bold" :class="shell.cardMuted">{{ sectionItem.faqs.length }} questions</p>
                                </div>
                                <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-black text-amber-800">Drag-ready</span>
                            </div>
                            <div class="mt-4 space-y-3">
                                <article v-for="faq in sectionItem.faqs" :key="faq.id" class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-white/5">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div>
                                            <p class="font-black text-slate-950 dark:text-white">{{ faq.question }}</p>
                                            <p class="mt-1 text-xs font-bold capitalize text-slate-500">{{ faq.audience }} · {{ faq.status }} · edited {{ dateLabel(faq.updated_at) }}</p>
                                            <div class="mt-2 flex flex-wrap gap-1">
                                                <span v-for="keyword in faq.search_keywords" :key="keyword" class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-black text-slate-600">{{ keyword }}</span>
                                            </div>
                                        </div>
                                        <div class="flex gap-2">
                                            <button type="button" class="rounded-xl bg-amber-100 px-3 py-2 text-xs font-black text-amber-800" @click="editFaq(faq)">Edit</button>
                                            <button type="button" class="rounded-xl bg-rose-50 px-3 py-2 text-xs font-black text-rose-700" @click="archiveFaq(faq)">Archive</button>
                                        </div>
                                    </div>
                                    <details v-if="faq.versions?.length" class="mt-3">
                                        <summary class="cursor-pointer text-xs font-black text-slate-500">Version history</summary>
                                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                            <button v-for="version in faq.versions" :key="version.id" type="button" class="rounded-2xl border p-3 text-left text-xs font-semibold" :class="shell.card" @click="restoreFaq(faq, version)">
                                                {{ dateLabel(version.created_at) }} · {{ version.change_note }}
                                            </button>
                                        </div>
                                    </details>
                                </article>
                            </div>
                        </section>
                    </div>
                </AdminPanel>
                <AdminPanel title="Search effectiveness" description="Queries with no results are content gaps. Add or improve FAQs to answer them.">
                    <div class="space-y-3">
                        <article v-for="gap in searchGaps" :key="gap.query" class="rounded-3xl border border-amber-100 bg-amber-50 p-4 text-amber-950">
                            <p class="font-black">“{{ gap.query }}”</p>
                            <p class="mt-1 text-xs font-bold">{{ gap.total }} no-result searches · last seen {{ dateLabel(gap.last_seen_at) }}</p>
                        </article>
                        <p v-if="!searchGaps.length" class="rounded-3xl bg-emerald-50 p-5 text-sm font-bold text-emerald-900">No search gaps in the last 30 days.</p>
                    </div>
                </AdminPanel>
            </AdminTabPanel>
            </AdminTabbedPage>
        </div>
    </AdminShell>
</template>

<script setup>
import AdminPanel from '@/Components/Admin/AdminPanel.vue';
import AdminTabPanel from '@/Components/Admin/AdminTabPanel.vue';
import AdminTabbedPage from '@/Components/Admin/AdminTabbedPage.vue';
import { useTabState } from '@/composables/useTabState';
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';
import AdminShell from '@/Layouts/AdminShell.vue';
import { router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    section: { type: String, required: true },
    content: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const { shell } = useInjectedAdminTheme();
const warmBtn = 'bg-gradient-to-r from-amber-600 to-orange-500 text-white shadow-sm shadow-amber-500/20';
const tabs = [
    { key: 'email', label: 'Email Templates' },
    { key: 'announcements', label: 'Announcement Banners' },
    { key: 'help', label: 'FAQ & Help Content' },
];
const { activeTab } = useTabState(tabs.map((tab) => tab.key), props.section || 'email');
watch(
    () => props.section,
    (section) => {
        if (section && tabs.some((tab) => tab.key === section)) {
            activeTab.value = section;
        }
    },
);
const blockTypes = ['text', 'button', 'image', 'divider'];
const seoChecklist = [
    { label: 'Clear title', done: true, note: 'Helps people know what the page is about.' },
    { label: 'Helpful summary', done: true, note: 'Sets expectations before they read.' },
    { label: 'Improve links', done: false, note: 'Add next steps so readers keep moving.' },
];

const email = computed(() => props.content.email || { templates: [], provider: 'mail' });
const announcements = computed(() => props.content.announcements || { banners: { data: [] } });
const help = computed(() => props.content.help || { sections: [] });
const searchGaps = computed(() => props.content.searchGaps || []);

const selectedTemplate = ref(null);
const saveIndicator = ref('Saved');
const testEmail = ref('');
const templateForm = reactive({ subject: '', preheader: '', blocks: [], theme: {} });
const announcementForm = useForm({
    message: '',
    link_url: '',
    link_text: '',
    color: 'brand',
    segment: 'all',
    starts_at: '',
    ends_at: '',
    dismissible: true,
    status: 'active',
});
const faqForm = useForm({
    help_section_id: '',
    question: '',
    answer: '',
    audience: 'all',
    search_keywords: [],
    display_order: 0,
    status: 'active',
});
const editingFaq = ref(null);
const keywordsText = ref('');

const saveIndicatorClass = computed(() => ({
    Saved: 'bg-emerald-100 text-emerald-800',
    Saving: 'bg-amber-100 text-amber-800',
    Unsaved: 'bg-slate-100 text-slate-700',
}[saveIndicator.value] || 'bg-slate-100 text-slate-700'));

const emailMetrics = computed(() => {
    const analytics = selectedTemplate.value?.analytics || {};
    return [
        { label: 'Open rate', value: `${analytics.open_rate || 0}%`, warn: analytics.needs_attention, note: analytics.needs_attention ? 'Below 20%, try a clearer subject.' : 'Healthy engagement.' },
        { label: 'Click-through', value: `${analytics.click_rate || 0}%`, note: 'Shows whether calls to action are working.' },
        { label: 'Unsubscribe', value: `${analytics.unsubscribe_rate || 0}%`, note: 'Lower is better for trust.' },
    ];
});

const localPreviewHtml = computed(() => {
    const theme = templateForm.theme || {};
    const primary = theme.primary_color || '#0f766e';
    const body = (templateForm.blocks || []).map((block) => {
        if (block.type === 'button') {
            return `<p style="margin:24px 0"><a style="display:inline-block;background:${primary};color:#fff;padding:12px 18px;border-radius:12px;text-decoration:none;font-weight:800">${escapeHtml(replaceDemo(block.label || 'Open'))}</a></p>`;
        }
        if (block.type === 'divider') {
            return '<hr style="border:0;border-top:1px solid #e2e8f0;margin:24px 0" />';
        }
        if (block.type === 'image') {
            return block.url ? `<img src="${escapeHtml(block.url)}" style="max-width:100%;border-radius:16px" />` : '';
        }

        return `<p style="font-size:15px;line-height:1.7">${escapeHtml(replaceDemo(block.content || '')).replace(/\n/g, '<br>')}</p>`;
    }).join('');

    return `<div style="background:#f8fafc;padding:32px;font-family:Inter,Arial,sans-serif;color:#0f172a"><div style="max-width:640px;margin:0 auto;background:#fff;border-radius:24px;overflow:hidden;border:1px solid #e2e8f0"><div style="padding:24px;background:${primary};color:#fff;font-weight:900;font-size:20px">${escapeHtml(theme.logo || 'HS')}</div><div style="padding:28px">${body}</div><div style="padding:20px 28px;border-top:1px solid #e2e8f0;color:#64748b;font-size:12px">${escapeHtml(theme.footer || 'HustleSafe')}</div></div></div>`;
});

async function loadTemplate(id) {
    saveIndicator.value = 'Saving';
    const { data } = await window.axios.get(route('admin.content.email-templates.show', id));
    selectedTemplate.value = data;
    Object.assign(templateForm, {
        subject: data.subject || '',
        preheader: data.preheader || '',
        blocks: JSON.parse(JSON.stringify(data.blocks || [])),
        theme: { logo: 'HS', primary_color: '#0f766e', footer: 'HustleSafe', ...(data.theme || {}) },
    });
    saveIndicator.value = 'Saved';
}

function markUnsaved() {
    saveIndicator.value = 'Unsaved';
}

async function saveTemplate() {
    if (!selectedTemplate.value) {
        return;
    }
    saveIndicator.value = 'Saving';
    const { data } = await window.axios.patch(route('admin.content.email-templates.update', selectedTemplate.value.id), {
        subject: templateForm.subject,
        preheader: templateForm.preheader,
        blocks: templateForm.blocks,
        theme: templateForm.theme,
        change_note: 'Saved from visual editor',
    });
    selectedTemplate.value = data;
    saveIndicator.value = 'Saved';
}

async function restoreTemplate(version) {
    if (!selectedTemplate.value || !window.confirm('Restore this email version?')) {
        return;
    }
    const { data } = await window.axios.post(route('admin.content.email-templates.versions.restore', [selectedTemplate.value.id, version.id]));
    selectedTemplate.value = data;
    Object.assign(templateForm, {
        subject: data.subject || '',
        preheader: data.preheader || '',
        blocks: JSON.parse(JSON.stringify(data.blocks || [])),
        theme: { ...(data.theme || {}) },
    });
}

async function sendTest() {
    if (!selectedTemplate.value || !testEmail.value) {
        return;
    }
    await window.axios.post(route('admin.content.email-templates.test', selectedTemplate.value.id), { email: testEmail.value });
    saveIndicator.value = 'Saved';
}

function addBlock(type) {
    templateForm.blocks.push(type === 'button' ? { type, label: 'Open', url: '{{app.url}}' } : type === 'image' ? { type, url: '' } : type === 'divider' ? { type } : { type, content: '' });
    markUnsaved();
}

function removeBlock(index) {
    templateForm.blocks.splice(index, 1);
    markUnsaved();
}

function moveBlock(index, direction) {
    const next = index + direction;
    if (next < 0 || next >= templateForm.blocks.length) {
        return;
    }
    const [item] = templateForm.blocks.splice(index, 1);
    templateForm.blocks.splice(next, 0, item);
    markUnsaved();
}

function saveAnnouncement() {
    announcementForm.post(route('admin.content.announcements.store'), {
        preserveScroll: true,
        onSuccess: () => announcementForm.reset(),
    });
}

function archiveAnnouncement(banner) {
    if (window.confirm('Archive this announcement?')) {
        router.delete(route('admin.content.announcements.archive', banner.id), { preserveScroll: true });
    }
}

function saveFaq() {
    faqForm.search_keywords = keywordsText.value.split(',').map((item) => item.trim()).filter(Boolean);
    const options = { preserveScroll: true, onSuccess: resetFaq };
    if (editingFaq.value) {
        faqForm.patch(route('admin.content.help.faqs.update', editingFaq.value.id), options);
    } else {
        faqForm.post(route('admin.content.help.faqs.store'), options);
    }
}

function editFaq(faq) {
    editingFaq.value = faq;
    faqForm.help_section_id = faq.help_section_id;
    faqForm.question = faq.question;
    faqForm.answer = faq.answer;
    faqForm.audience = faq.audience;
    faqForm.display_order = faq.display_order;
    faqForm.status = faq.status;
    keywordsText.value = (faq.search_keywords || []).join(', ');
}

function resetFaq() {
    editingFaq.value = null;
    faqForm.reset();
    faqForm.audience = 'all';
    faqForm.status = 'active';
    keywordsText.value = '';
}

function archiveFaq(faq) {
    if (window.confirm('Archive this FAQ?')) {
        router.delete(route('admin.content.help.faqs.archive', faq.id), { preserveScroll: true });
    }
}

function restoreFaq(faq, version) {
    if (window.confirm('Restore this FAQ version?')) {
        router.post(route('admin.content.help.faqs.versions.restore', [faq.id, version.id]), {}, { preserveScroll: true });
    }
}

function dateLabel(value) {
    if (!value) {
        return '';
    }
    return new Date(value).toLocaleString('en-NG', { dateStyle: 'medium', timeStyle: 'short' });
}

function replaceDemo(value) {
    return String(value)
        .replaceAll('{{user.first_name}}', 'Ada')
        .replaceAll('{{user.name}}', 'Ada Okonkwo')
        .replaceAll('{{quest.title}}', 'Paint a three-bedroom apartment')
        .replaceAll('{{payout.amount}}', '₦125,000.00')
        .replaceAll('{{app.name}}', 'HustleSafe');
}

function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
}
</script>
