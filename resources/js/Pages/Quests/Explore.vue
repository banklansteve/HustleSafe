<template>
    <AppShell>
        <Head title="Explore quests" />

        <div
            v-if="workspace.enabled && workspacePanelItems.length"
            class="mb-8 rounded-2xl border border-secondary-200/80 bg-gradient-to-r from-secondary-50 via-amber-50/90 to-secondary-50 p-5 shadow-sm ring-1 ring-secondary-100 sm:p-6"
            role="status"
        >
            <p class="text-xs font-black uppercase tracking-[0.2em] text-secondary-800">
                Profile & verification
            </p>
            <ul class="mt-3 space-y-3">
                <li
                    v-for="(item, i) in workspacePanelItems"
                    :key="i"
                    class="list-none rounded-xl border border-secondary-100/80 bg-white/60 px-3 py-2.5 text-sm font-semibold text-secondary-950 ring-1 ring-white/60"
                >
                    <p class="leading-snug">
                        {{ item.message }}
                    </p>
                    <Link
                        v-if="item.action_url"
                        :href="item.action_url"
                        class="mt-2 inline-flex items-center gap-1 text-xs font-black uppercase tracking-wide text-secondary-900 underline decoration-secondary-400 underline-offset-2 hover:text-secondary-700"
                    >
                        {{ item.action_label || 'Fix this' }}
                        <span aria-hidden="true">→</span>
                    </Link>
                </li>
            </ul>
            <p
                v-if="workspace.tier === 'limited' && workspace.can_submit_proposals"
                class="mt-3 text-xs font-semibold text-secondary-900/90"
            >
                Until your ID is approved you can send up to {{ workspace.limited_slots_remaining }} more modest
                proposals (see budget cap on each quest).
            </p>
        </div>

        <div class="rounded-[2rem] bg-gradient-to-br from-primary-800 via-slate-900 to-slate-950 px-6 py-10 text-white shadow-xl ring-1 ring-white/10 sm:px-10">
            <p class="text-xs font-bold uppercase tracking-[0.25em] text-teal-200/90">
                {{ explore_mode === 'client' ? 'Market pulse' : 'For you' }}
            </p>
            <h1 class="font-display mt-3 text-3xl font-black tracking-tight sm:text-4xl">
                {{ explore_mode === 'client' ? 'Open quests on the marketplace' : 'Matched open quests' }}
            </h1>
            <p class="mt-4 max-w-2xl text-base font-semibold leading-relaxed text-teal-50">
                <template v-if="explore_mode === 'client'">
                    Browse live public briefs for inspiration on how sponsors scope work, budgets, and timelines — without leaving HustleSafe.
                </template>
                <template v-else>
                    Sorted by match score: location first, then skills fit, budget alignment, tier quality, and recent activity.
                    Jobs in your LGA appear before wider state and national listings.
                </template>
            </p>
        </div>

        <div class="mt-10 space-y-5">
            <ListSearchSortBar
                v-if="rawQuests.length"
                v-model:search="search"
                v-model:sort="sortKey"
                class="mb-2"
                placeholder="Search title, category, location, match…"
                :sort-options="sortOptions"
            />

            <div
                v-for="q in visibleQuests"
                :key="q.slug || q.uuid"
                class="overflow-hidden rounded-[1.5rem] border border-slate-100 bg-white shadow-sm ring-1 ring-slate-100 transition hover:border-primary-200 hover:shadow-md"
            >
                <div class="relative aspect-[21/9] w-full overflow-hidden bg-slate-100 sm:aspect-[24/9]">
                    <img :src="q.cover_url" alt="" class="h-full w-full object-cover" loading="lazy" />
                </div>
                <div class="space-y-4 p-6 sm:p-8">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <p class="font-display text-lg font-bold text-slate-900 sm:text-xl">
                            {{ q.title }}
                        </p>
                        <span
                            v-if="q.is_boosted"
                            class="mt-2 inline-flex rounded-full bg-amber-100 px-3 py-1 text-[10px] font-black uppercase tracking-wider text-amber-900 ring-1 ring-amber-200"
                        >
                            Boosted
                        </span>
                        <div class="mt-3 flex flex-wrap gap-2 text-sm font-semibold text-slate-600">
                            <span
                                v-if="q.parent_category || q.category"
                                class="rounded-full bg-primary-50 px-3 py-1 text-xs font-bold uppercase tracking-wide text-primary-900 ring-1 ring-primary-100"
                            >
                                <template v-if="q.parent_category && q.category">{{ q.parent_category }} · {{ q.category }}</template>
                                <template v-else>{{ q.category }}</template>
                            </span>
                            <span v-if="q.city || q.state" class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">
                                {{ [q.city, q.state].filter(Boolean).join(' · ') }}
                            </span>
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">
                                Budget {{ formatBudget(q.budget_minor) }}
                            </span>
                        </div>
                        <ul class="mt-4 space-y-1.5">
                            <li
                                v-for="(r, i) in q.reasons"
                                :key="i"
                                class="flex gap-2 text-sm font-medium text-slate-600"
                            >
                                <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-teal-500" />
                                <span>{{ r }}</span>
                            </li>
                        </ul>
                    </div>
                    <div
                        class="group relative flex h-24 w-24 shrink-0 flex-col items-center justify-center rounded-2xl bg-gradient-to-br from-primary-600 to-teal-600 text-center shadow-lg shadow-primary-900/25 ring-1 ring-white/20"
                        :title="matchBreakdownTitle(q)"
                    >
                        <p class="text-xs font-bold uppercase tracking-wide text-white/80">
                            Match
                        </p>
                        <p class="font-display text-2xl font-black text-white">
                            {{ q.match_score }}
                        </p>
                        <p
                            v-if="q.match_quality?.label"
                            class="mt-1 max-w-[5.5rem] text-center text-[9px] font-bold leading-tight text-white/90"
                        >
                            {{ matchStars(q.match_quality?.stars) }}
                            <span class="block">{{ q.match_quality.label }}</span>
                        </p>
                        <div
                            class="pointer-events-none absolute right-0 top-full z-20 mt-2 hidden w-56 rounded-xl border border-slate-200 bg-white p-3 text-left text-xs font-semibold text-slate-700 shadow-xl group-hover:block sm:group-focus-within:block"
                            role="tooltip"
                        >
                            <p class="font-black text-slate-900">{{ q.match_quality?.label || 'Match breakdown' }}</p>
                            <ul class="mt-2 space-y-1">
                                <li v-for="(line, bi) in q.match_breakdown || []" :key="bi">
                                    {{ line }}
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                <p class="mt-4 text-xs font-semibold text-slate-500">
                    Posted {{ formatWhen(q.posted_at) }}
                </p>
                <div class="mt-5 flex flex-col gap-3">
                    <div class="flex flex-wrap items-center gap-3">
                    <Link
                        :href="route('quests.show', q.slug || q.uuid)"
                        class="inline-flex items-center rounded-full border border-slate-200 bg-white px-5 py-2.5 text-sm font-bold text-slate-800 shadow-sm hover:border-primary-200 hover:bg-primary-50"
                    >
                        View quest
                    </Link>
                    <template v-if="explore_mode === 'freelancer' && !hasExistingProposal(q)">
                        <Link
                            v-if="workspace.can_submit_proposals && q.category_match && q.budget_within_limit"
                            :href="route('quests.proposals.create', q.slug || q.uuid)"
                            class="inline-flex items-center rounded-full bg-primary-600 px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-primary-900/20 hover:bg-primary-700"
                        >
                            Send proposal
                        </Link>
                        <button
                            v-else
                            type="button"
                            disabled
                            class="inline-flex cursor-not-allowed items-center rounded-full bg-slate-200 px-5 py-2.5 text-sm font-bold text-slate-500 opacity-70"
                        >
                            Send proposal
                        </button>
                        <p v-if="!workspace.can_submit_proposals" class="self-center text-xs font-semibold text-rose-700">
                            Complete the checklist above to unlock proposals.
                        </p>
                        <p v-else-if="!q.category_match" class="self-center text-xs font-semibold text-amber-800">
                            Add this quest’s subcategory to your profile to send a proposal.
                        </p>
                        <p v-else-if="!q.budget_within_limit" class="self-center text-xs font-semibold text-violet-900">
                            {{ verificationLimitMessage(q.budget_minor) }}
                            <Link
                                v-if="verification_access?.verifications_url"
                                :href="verification_access.verifications_url"
                                class="ml-1 font-black text-violet-950 underline decoration-violet-400 underline-offset-2"
                            >
                                Open verifications
                            </Link>
                        </p>
                    </template>
                    </div>
                    <div
                        v-if="explore_mode === 'freelancer' && hasExistingProposal(q)"
                        class="w-full rounded-2xl border border-emerald-200/90 bg-gradient-to-br from-emerald-50 via-white to-teal-50/60 p-4 shadow-sm ring-1 ring-emerald-100 sm:p-5"
                        role="status"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0 flex-1 space-y-2">
                                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-emerald-800">
                                    Proposal already sent
                                </p>
                                <p class="text-sm font-semibold leading-relaxed text-emerald-950">
                                    You have already submitted a proposal on this quest. The client can review it in their inbox — you cannot send another one while this version is active.
                                </p>
                                <p class="text-xs font-bold text-emerald-900/90">
                                    Status:
                                    <span class="font-black">{{ proposalStatusLabel(existingProposal(q)?.status) }}</span>
                                    <template v-if="existingProposal(q)?.submitted_at">
                                        · Submitted {{ formatWhen(existingProposal(q).submitted_at) }}
                                    </template>
                                </p>
                            </div>
                            <span
                                class="inline-flex shrink-0 items-center rounded-full bg-emerald-600 px-3 py-1.5 text-[10px] font-black uppercase tracking-wide text-white shadow-sm"
                            >
                                {{ proposalStatusLabel(existingProposal(q)?.status) }}
                            </span>
                        </div>
                        <Link
                            v-if="existingProposal(q)?.show_url"
                            :href="existingProposal(q).show_url"
                            class="mt-4 inline-flex items-center gap-1 text-xs font-black uppercase tracking-wide text-emerald-900 underline decoration-emerald-400 underline-offset-2 hover:text-emerald-950"
                        >
                            Open your proposal
                            <span aria-hidden="true">→</span>
                        </Link>
                    </div>
                </div>
                </div>
            </div>

            <p
                v-if="rawQuests.length === 0"
                class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-6 py-12 text-center text-base font-semibold text-slate-600"
            >
                No open quests right now — check back soon or widen your categories in profile.
            </p>
            <p
                v-else-if="rawQuests.length && !sortedFiltered.length"
                class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-6 py-10 text-center text-sm font-semibold text-slate-600"
            >
                No quests match your search.
            </p>

            <div v-if="sortedFiltered.length > visibleLimit" class="flex justify-center pt-2">
                <button
                    type="button"
                    class="inline-flex min-h-[48px] w-full max-w-sm items-center justify-center rounded-full border border-slate-200 bg-white px-6 py-3 text-sm font-black text-slate-800 shadow-sm transition hover:border-primary-300 hover:bg-primary-50 sm:w-auto"
                    @click="loadMoreChunk"
                >
                    Load more ({{ visibleLimit }} / {{ sortedFiltered.length }})
                </button>
            </div>
        </div>
    </AppShell>
</template>

<script setup>
import ListSearchSortBar from '@/Components/Ui/ListSearchSortBar.vue';
import AppShell from '@/Layouts/AppShell.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    quests: {
        type: Array,
        default: () => [],
    },
    workspace: {
        type: Object,
        required: true,
    },
    explore_mode: {
        type: String,
        default: 'freelancer',
    },
    verification_access: {
        type: Object,
        default: null,
    },
    freelancer_proposals: {
        type: Object,
        default: () => ({}),
    },
});

const rawQuests = ref([...props.quests]);
const search = ref('');
const sortKey = ref('match_desc');
const visibleLimit = ref(12);

const sortOptions = [
    { value: 'match_desc', label: 'Best match' },
    { value: 'posted_desc', label: 'Newest posted' },
    { value: 'budget_desc', label: 'Highest budget' },
    { value: 'title_asc', label: 'Title A–Z' },
];

watch(
    () => props.quests,
    (v) => {
        rawQuests.value = [...(v || [])];
    },
    { deep: true },
);

watch([search, sortKey], () => {
    visibleLimit.value = 12;
});

const sortedFiltered = computed(() => {
    const q = search.value.trim().toLowerCase();
    let rows = rawQuests.value.slice();
    if (q) {
        rows = rows.filter((row) => {
            const blob = [
                row.title,
                row.category,
                row.parent_category,
                row.city,
                row.state,
                String(row.match_score ?? ''),
                ...(row.reasons || []),
            ]
                .filter(Boolean)
                .join(' ')
                .toLowerCase();

            return blob.includes(q);
        });
    }
    const sk = sortKey.value;
    rows.sort((a, b) => {
        if (sk === 'title_asc') {
            return String(a.title || '').localeCompare(String(b.title || ''));
        }
        if (sk === 'budget_desc') {
            return (Number(b.budget_minor) || 0) - (Number(a.budget_minor) || 0);
        }
        if (sk === 'posted_desc') {
            return ts(b.posted_at) - ts(a.posted_at);
        }

        return (Number(b.match_score) || 0) - (Number(a.match_score) || 0);
    });

    return rows;
});

const visibleQuests = computed(() => sortedFiltered.value.slice(0, visibleLimit.value));

function ts(iso) {
    if (!iso) {
        return 0;
    }
    const n = Date.parse(iso);

    return Number.isFinite(n) ? n : 0;
}

function loadMoreChunk() {
    visibleLimit.value = Math.min(sortedFiltered.value.length, visibleLimit.value + 12);
}

const workspacePanelItems = computed(() => {
    const ws = props.workspace;
    if (!ws?.enabled) {
        return [];
    }
    const items = [];
    for (const b of ws.blockers || []) {
        if (b?.message) {
            items.push({
                message: b.message,
                action_label: b.action_label,
                action_url: b.action_url,
            });
        }
    }
    for (const h of ws.hints || []) {
        if (h?.message) {
            items.push({
                message: h.message,
                action_label: h.action_label,
                action_url: h.action_url,
            });
        }
    }

    return items.slice(0, 5);
});

function formatBudget(minor) {
    const n = Number(minor) / 100;

    return `₦${n.toLocaleString('en-NG', { maximumFractionDigits: 0 })}`;
}

function formatWhen(iso) {
    if (!iso) {
        return '';
    }
    try {
        return new Date(iso).toLocaleString('en-NG', {
            dateStyle: 'medium',
            timeStyle: 'short',
            timeZone: 'Africa/Lagos',
        });
    } catch {
        return iso;
    }
}

function verificationLimitMessage(questBudgetMinor) {
    const access = props.verification_access;
    if (!access) {
        return 'This quest is above your current verification limit.';
    }

    const limitLabel = formatBudget(access.proposal_limit_minor);
    const missing = (access.missing_for_next_level || []).filter(Boolean).join(', ');
    let message = `Budget ${formatBudget(questBudgetMinor)} exceeds your L${access.effective_level} limit (${limitLabel}).`;

    if (missing) {
        message += ` Complete ${missing} to unlock higher-value quests.`;
    } else {
        message += ' Complete more verification to unlock higher-value quests.';
    }

    return message;
}

const proposalStatusLabels = {
    submitted: 'Submitted',
    shortlisted: 'Shortlisted',
    pending_award: 'Awaiting confirmation',
    accepted: 'Accepted',
    declined: 'Declined',
    withdrawn: 'Withdrawn',
};

function proposalStatusLabel(status) {
    return proposalStatusLabels[status] || String(status || '').replace(/_/g, ' ');
}

function existingProposal(quest) {
    if (!quest) {
        return null;
    }

    const fromMap = props.freelancer_proposals?.[quest.id] ?? props.freelancer_proposals?.[String(quest.id)];
    if (fromMap) {
        return fromMap;
    }

    return quest.my_proposal ?? null;
}

function hasExistingProposal(quest) {
    if (quest?.has_my_proposal === true) {
        return true;
    }

    return Boolean(existingProposal(quest)?.show_url);
}

function matchStars(count) {
    const n = Math.max(0, Math.min(5, Number(count) || 0));

    return '★'.repeat(n) + (n < 5 ? '☆'.repeat(5 - n) : '');
}

function matchBreakdownTitle(quest) {
    const lines = quest?.match_breakdown || [];

    return lines.length ? lines.join(' · ') : quest?.match_quality?.label || '';
}
</script>
