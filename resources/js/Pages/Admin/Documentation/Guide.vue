<template>
    <AdminShell
        title="Admin Documentation"
        subtitle="Internal dashboard guide for modules, statuses, workflows, risk signals, and troubleshooting."
    >
        <div class="documentation-page space-y-5">
            <section class="rounded-[2rem] border border-primary-100 bg-gradient-to-r from-primary-50 via-white to-teal-50 p-5 shadow-sm ring-1 ring-primary-100 sm:p-7">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                    <div class="max-w-3xl">
                        <p class="text-xs font-black uppercase tracking-[0.25em] text-primary-700">Knowledge Center</p>
                        <h2 class="font-display mt-2 text-2xl font-black tracking-tight text-slate-950 sm:text-3xl">
                            Dashboard Guide
                        </h2>
                        <p class="mt-3 text-sm font-semibold leading-relaxed text-slate-700">
                            Search every admin module, status, workflow, risk signal, and troubleshooting note. Use this guide while working inside the dashboard.
                        </p>
                    </div>
                    <div class="grid gap-2 sm:grid-cols-3 lg:min-w-[28rem]">
                        <div v-for="card in heroCards" :key="card.label" class="rounded-2xl border border-white bg-white/90 p-4 shadow-sm">
                            <p class="text-[10px] font-black uppercase tracking-wide text-slate-500">{{ card.label }}</p>
                            <p class="mt-1 text-xl font-black text-slate-950">{{ card.value }}</p>
                        </div>
                    </div>
                </div>

                <div class="relative mt-6">
                    <input
                        v-model="query"
                        type="search"
                        class="h-14 w-full rounded-2xl border border-primary-100 bg-white px-5 pl-12 text-sm font-bold text-slate-900 shadow-sm outline-none ring-2 ring-transparent placeholder:text-slate-400 focus:border-primary-300 focus:ring-primary-100"
                        placeholder="Search modules, actions, statuses, risk signals, troubleshooting..."
                        autocomplete="off"
                    />
                    <span class="pointer-events-none absolute left-5 top-1/2 -translate-y-1/2 text-slate-400">⌕</span>
                </div>

                <div v-if="query.trim()" class="mt-4 rounded-3xl border border-slate-100 bg-white p-3 shadow-sm">
                    <div class="flex items-center justify-between gap-3 px-2 py-2">
                        <p class="text-xs font-black uppercase tracking-wide text-slate-500">
                            {{ searchResults.length }} result{{ searchResults.length === 1 ? '' : 's' }}
                        </p>
                        <button type="button" class="text-xs font-black text-primary-700 hover:underline" @click="query = ''">Clear search</button>
                    </div>
                    <div class="max-h-[28rem] space-y-2 overflow-y-auto">
                        <Link
                            v-for="result in searchResults"
                            :key="`${result.topic}:${result.section}:${result.title}`"
                            :href="result.href"
                            class="block rounded-2xl border border-slate-100 bg-slate-50/70 p-4 transition hover:border-primary-200 hover:bg-primary-50"
                        >
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full bg-primary-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-primary-800">{{ result.module }}</span>
                                <p class="font-black text-slate-950" v-html="highlight(result.title)" />
                            </div>
                            <p class="mt-2 line-clamp-2 text-sm font-semibold text-slate-700" v-html="highlight(result.body)" />
                        </Link>
                        <p v-if="!searchResults.length" class="rounded-2xl border border-dashed border-slate-200 p-5 text-center text-sm font-bold text-slate-500">
                            No documentation result matched that search yet.
                        </p>
                    </div>
                </div>
            </section>

            <div class="grid gap-5 xl:grid-cols-[18rem_minmax(0,1fr)]">
                <aside class="rounded-[1.75rem] border border-slate-100 bg-white p-4 shadow-sm ring-1 ring-slate-100 xl:sticky xl:top-4 xl:self-start">
                    <p class="px-2 text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Modules</p>
                    <nav class="mt-3 space-y-1">
                        <Link
                            v-for="topic in topics"
                            :key="topic.slug"
                            :href="route('admin.documentation.guide', { topic: topic.slug })"
                            class="block rounded-2xl px-3 py-3 transition"
                            :class="topic.slug === activeSlug ? 'bg-primary-700 text-white shadow-md' : 'text-slate-700 hover:bg-primary-50 hover:text-primary-900'"
                        >
                            <span class="block text-sm font-black">{{ topic.title }}</span>
                            <span class="mt-1 block text-[11px] font-bold opacity-75">{{ topic.category }}</span>
                        </Link>
                    </nav>
                </aside>

                <section class="min-w-0 rounded-[1.75rem] border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-7">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <p class="text-xs font-black uppercase tracking-[0.2em] text-primary-700">{{ activeTopic.category }}</p>
                            <h2 class="font-display mt-2 text-2xl font-black text-slate-950">{{ activeTopic.title }}</h2>
                            <p class="mt-2 max-w-3xl text-sm font-semibold leading-relaxed text-slate-700">{{ activeTopic.summary }}</p>
                        </div>
                        <Link
                            v-if="moduleLink(activeTopic.module)"
                            :href="moduleLink(activeTopic.module)"
                            class="inline-flex shrink-0 items-center justify-center rounded-xl border border-primary-200 bg-primary-50 px-4 py-2 text-xs font-black uppercase tracking-wide text-primary-800 hover:bg-primary-100"
                        >
                            Open {{ activeTopic.module }}
                        </Link>
                    </div>

                    <AdminTabs v-model="activeDocTab" :tabs="docTabs" id-prefix="documentation-guide" aria-label="Documentation sections" class="mt-6" />
                    <AdminTabPanel :current-tab="activeDocTab" value="guide" id-prefix="documentation-guide">
                        <div class="mt-6 space-y-4">
                            <details
                                v-for="section in activeTopic.sections"
                                :id="section.id"
                                :key="section.id"
                                class="group rounded-3xl border border-slate-100 bg-slate-50/80 p-4 open:bg-white open:shadow-sm scroll-mt-24"
                                open
                            >
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-3">
                                    <span class="font-display text-lg font-black text-slate-950">{{ section.title }}</span>
                                    <span class="rounded-full bg-white px-3 py-1 text-xs font-black text-slate-600 ring-1 ring-slate-200 group-open:bg-primary-50 group-open:text-primary-800">
                                        Toggle
                                    </span>
                                </summary>
                                <div class="mt-4 space-y-3">
                                    <div v-for="(item, index) in section.items" :key="index" class="rounded-2xl border border-slate-100 bg-white p-4">
                                        <p class="text-sm font-semibold leading-relaxed text-slate-800">{{ item }}</p>
                                    </div>
                                </div>
                                <div v-if="section.keywords?.length" class="mt-4 flex flex-wrap gap-2">
                                    <span v-for="keyword in section.keywords" :key="keyword" class="rounded-full bg-primary-50 px-3 py-1 text-[11px] font-black text-primary-800 ring-1 ring-primary-100">
                                        {{ keyword }}
                                    </span>
                                </div>
                            </details>

                            <section class="rounded-3xl border border-primary-100 bg-primary-50/70 p-5">
                                <h3 class="font-display text-lg font-black text-slate-950">Example Workflow</h3>
                                <ol class="mt-4 space-y-3">
                                    <li v-for="(step, index) in workflowFor(activeTopic.slug)" :key="step" class="flex gap-3 rounded-2xl bg-white p-4">
                                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary-700 text-xs font-black text-white">{{ index + 1 }}</span>
                                        <span class="text-sm font-semibold leading-relaxed text-slate-800">{{ step }}</span>
                                    </li>
                                </ol>
                            </section>

                            <section class="rounded-3xl border border-slate-100 bg-white p-5">
                                <h3 class="font-display text-lg font-black text-slate-950">Screenshot Placeholder</h3>
                                <div class="mt-4 rounded-3xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center">
                                    <p class="text-sm font-black text-slate-700">{{ activeTopic.title }} UI screenshot placeholder</p>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">Add image or annotated component capture here when needed.</p>
                                </div>
                            </section>

                            <section class="rounded-3xl border border-slate-100 bg-slate-50 p-5">
                                <h3 class="font-display text-lg font-black text-slate-950">Related Topics</h3>
                                <div class="mt-4 flex flex-wrap gap-2">
                                    <Link
                                        v-for="related in relatedTopics"
                                        :key="related.slug"
                                        :href="route('admin.documentation.guide', { topic: related.slug })"
                                        class="rounded-full border border-primary-100 bg-white px-4 py-2 text-sm font-black text-primary-800 hover:bg-primary-50"
                                    >
                                        {{ related.title }}
                                    </Link>
                                </div>
                            </section>
                        </div>
                    </AdminTabPanel>

                    <AdminTabPanel :current-tab="activeDocTab" value="definitions" id-prefix="documentation-guide">
                        <div class="mt-6 grid gap-4 lg:grid-cols-2">
                            <article v-for="group in definitionGroups" :key="group.title" class="rounded-3xl border border-slate-100 bg-slate-50 p-5">
                                <h3 class="font-display text-lg font-black text-slate-950">{{ group.title }}</h3>
                                <ul class="mt-4 space-y-2">
                                    <li v-for="item in group.items" :key="item" class="rounded-2xl bg-white p-3 text-sm font-semibold text-slate-800">{{ item }}</li>
                                </ul>
                            </article>
                        </div>
                    </AdminTabPanel>

                    <AdminTabPanel :current-tab="activeDocTab" value="all" id-prefix="documentation-guide">
                        <div class="mt-6 space-y-3">
                            <Link
                                v-for="topic in topics"
                                :key="topic.slug"
                                :href="route('admin.documentation.guide', { topic: topic.slug })"
                                class="block rounded-3xl border border-slate-100 bg-slate-50 p-5 hover:border-primary-200 hover:bg-primary-50"
                            >
                                <p class="text-xs font-black uppercase tracking-wide text-primary-700">{{ topic.category }}</p>
                                <h3 class="font-display mt-1 text-lg font-black text-slate-950">{{ topic.title }}</h3>
                                <p class="mt-2 text-sm font-semibold text-slate-700">{{ topic.summary }}</p>
                            </Link>
                        </div>
                    </AdminTabPanel>
                </section>
            </div>
        </div>
    </AdminShell>
</template>

<script setup>
import AdminTabPanel from '@/Components/Admin/AdminTabPanel.vue';
import AdminTabs from '@/Components/Admin/AdminTabs.vue';
import AdminShell from '@/Layouts/AdminShell.vue';
import { Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    topics: { type: Array, default: () => [] },
    activeTopic: { type: String, default: 'overview' },
    searchIndex: { type: Array, default: () => [] },
});

const activeSlug = computed(() => props.activeTopic || 'overview');
const activeTopic = computed(() => props.topics.find((topic) => topic.slug === activeSlug.value) || props.topics[0] || { sections: [], related: [] });
const query = ref('');
const activeDocTab = ref('guide');
const docTabs = [
    { key: 'guide', label: 'Guide' },
    { key: 'definitions', label: 'Definitions' },
    { key: 'all', label: 'All Topics' },
];
const heroCards = computed(() => [
    { label: 'Topics', value: props.topics.length },
    { label: 'Sections', value: props.topics.reduce((sum, topic) => sum + (topic.sections?.length || 0), 0) },
    { label: 'Search Index', value: props.searchIndex.length },
]);
const relatedTopics = computed(() => (activeTopic.value.related || [])
    .map((slug) => props.topics.find((topic) => topic.slug === slug))
    .filter(Boolean));

const definitionGroups = [
    {
        title: 'Statuses',
        items: [
            'Operational status: normal user-facing lifecycle such as Submitted, Shortlisted, Accepted, Open, Assigned, In Progress, Delivered, or Completed.',
            'Admin status: internal moderation state such as Clear, Flagged, Under Review, Referred, Restricted, Suspended, or Resolved.',
            'Flag status: active while a concern is open; resolved when an admin closes the concern.',
            'Notice type: info, warning, or urgent user-facing guidance.',
        ],
    },
    {
        title: 'Risk Levels',
        items: [
            'Low: context only.',
            'Medium: review before acting.',
            'High: needs staff attention.',
            'Critical: urgent safety, fraud, or integrity risk.',
        ],
    },
    {
        title: 'Admin Actions',
        items: [
            'Flag marks a concern.',
            'Restrict keeps visible but blocks sensitive actions.',
            'Suspend hides from users.',
            'Refer assigns staff follow-up.',
            'Resolve closes the active concern.',
        ],
    },
    {
        title: 'System Behaviours',
        items: [
            'Slide-overs keep context without navigating away.',
            'Audit logs capture actor, time, reason, before state, and after state.',
            'Bulk actions should use one shared reason across selected records.',
            'Verification decisions can change trust level and marketplace limits.',
        ],
    },
];

watch(activeSlug, () => {
    activeDocTab.value = 'guide';
});

const moduleRoutes = {
    'Dashboard Home': () => route('admin.dashboard'),
    Proposals: () => route('admin.proposals.index'),
    Quests: () => route('admin.quests.index'),
    Users: () => route('admin.users.index'),
    'Verification Engine': () => route('admin.verification-engine.index'),
    'Financial Control': () => route('admin.financial.index'),
    'Fraud & Risk': () => route('admin.fraud.index'),
    'Email Broadcasts': () => route('admin.communications.email-broadcasts.index'),
    Disputes: () => route('admin.disputes.index'),
    'Reports & analytics': () => route('admin.reports.index'),
    'Audit log': () => route('admin.activity.index'),
};

function moduleLink(module) {
    return moduleRoutes[module]?.() || null;
}

const searchResults = computed(() => {
    const term = normalize(query.value);
    if (!term) return [];

    return props.searchIndex
        .map((row) => ({ ...row, score: score(row, term) }))
        .filter((row) => row.score > 0)
        .sort((a, b) => b.score - a.score)
        .slice(0, 12);
});

function score(row, term) {
    const haystack = normalize(`${row.title} ${row.module} ${row.body}`);
    if (haystack.includes(term)) return 100 + term.length;

    const tokens = term.split(/\s+/).filter(Boolean);
    const tokenHits = tokens.filter((token) => haystack.includes(token)).length;
    if (tokenHits) return 40 + tokenHits * 10;

    let cursor = 0;
    for (const char of term) {
        cursor = haystack.indexOf(char, cursor);
        if (cursor === -1) return 0;
        cursor += 1;
    }

    return 10;
}

function normalize(value) {
    return String(value || '').toLowerCase().replace(/[^a-z0-9\s-]/g, ' ').replace(/\s+/g, ' ').trim();
}

function highlight(value) {
    const raw = escapeHtml(String(value || ''));
    const terms = normalize(query.value).split(/\s+/).filter((term) => term.length > 1).slice(0, 4);
    if (!terms.length) return raw;

    return terms.reduce((html, term) => html.replace(new RegExp(`(${escapeRegex(term)})`, 'ig'), '<mark class="rounded bg-amber-200 px-1 text-amber-950">$1</mark>'), raw);
}

function escapeHtml(value) {
    return value.replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    }[char]));
}

function escapeRegex(value) {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function workflowFor(slug) {
    const workflows = {
        'proposal-management': [
            'Open a proposal from the table, card, or kanban view.',
            'Review statuses, risk signals, flags, notices, and audit context.',
            'Choose a moderation action, enter a reason, then save.',
            'Confirm the status, flags, and audit trail reflect the action.',
        ],
        'verification-trust': [
            'Open the Document Review Desk.',
            'Inspect submitted metadata and documents in the slide-over.',
            'Mark verified, unverified, or flagged.',
            'Refer to staff when regularisation is needed.',
        ],
        'quest-management': [
            'Search or filter for the quest.',
            'Open the detail slide-over.',
            'Review client, proposals, escrow, flags, notices, and audit context.',
            'Apply the smallest admin action that resolves the issue.',
        ],
    };

    return workflows[slug] || [
        'Find the relevant module from the sidebar or search results.',
        'Open the record detail panel or module settings.',
        'Read statuses, signals, and audit context before acting.',
        'Take the action with a clear reason and verify the result.',
    ];
}
</script>
