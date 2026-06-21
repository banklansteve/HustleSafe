<template>
    <article
        class="group flex flex-col overflow-hidden rounded-[1.35rem] border border-slate-100 bg-white shadow-sm ring-1 ring-slate-100 transition-[border-color,box-shadow] duration-200 hover:border-primary-200 hover:shadow-md"
    >
        <Link
            :href="route('quests.show', quest.slug || quest.uuid)"
            class="relative block aspect-[21/9] w-full overflow-hidden bg-slate-100 sm:aspect-[24/9]"
        >
            <img
                :src="quest.cover_url"
                alt=""
                class="h-full w-full object-cover"
                loading="lazy"
            />
            <span
                v-if="quest.is_boosted"
                class="absolute left-3 top-3 inline-flex rounded-full bg-amber-100/95 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-amber-900 ring-1 ring-amber-200 backdrop-blur-sm"
            >
                Boosted
            </span>
        </Link>

        <div class="flex flex-1 flex-col space-y-3 p-5 sm:p-6">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <Link
                        :href="route('quests.show', quest.slug || quest.uuid)"
                        class="font-display line-clamp-2 text-base font-bold leading-snug text-slate-900 transition hover:text-primary-800 sm:text-lg"
                    >
                        {{ quest.title }}
                    </Link>
                    <div class="mt-2.5 flex flex-wrap gap-2 text-sm font-semibold text-slate-600">
                        <span
                            v-if="categoryLabel"
                            class="rounded-full bg-primary-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-primary-900 ring-1 ring-primary-100"
                        >
                            {{ categoryLabel }}
                        </span>
                        <span v-if="locationLabel" class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-slate-700">
                            {{ locationLabel }}
                        </span>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-slate-700">
                            {{ formatBudget(quest.budget_minor) }}
                        </span>
                    </div>
                    <div v-if="visibleSkills.length" class="mt-2 flex flex-wrap gap-1.5">
                        <span
                            v-for="skill in visibleSkills"
                            :key="skill"
                            class="rounded-full bg-violet-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-violet-900 ring-1 ring-violet-100"
                        >
                            {{ skill }}
                        </span>
                    </div>
                    <ul v-if="!compact && quest.reasons?.length" class="mt-3 space-y-1">
                        <li
                            v-for="(reason, index) in quest.reasons.slice(0, 2)"
                            :key="index"
                            class="flex gap-2 text-xs font-medium text-slate-600 sm:text-sm"
                        >
                            <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-teal-500" />
                            <span class="line-clamp-2">{{ reason }}</span>
                        </li>
                    </ul>
                </div>
                <div
                    v-if="showMatchScore"
                    class="group/match relative flex h-[4rem] w-[4rem] shrink-0 flex-col items-center justify-center rounded-2xl bg-gradient-to-br from-primary-600 to-teal-600 text-center shadow-lg shadow-primary-900/25 ring-1 ring-white/20 sm:h-[4.5rem] sm:w-[4.5rem]"
                    :title="matchBreakdownTitle"
                >
                    <p class="text-[9px] font-bold uppercase tracking-wide text-white/80">Match</p>
                    <p class="font-display text-lg font-black text-white sm:text-xl">{{ quest.match_score }}</p>
                </div>
            </div>

            <div class="mt-auto flex flex-col gap-2 pt-1">
                <p class="text-xs font-semibold text-slate-500">
                    Posted {{ formatWhen(quest.posted_at) }}
                    <span v-if="quest.delivery_deadline"> · Due {{ formatWhen(quest.delivery_deadline) }}</span>
                </p>

                <div v-if="showProposalAction" class="flex flex-col gap-3">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <Link
                            :href="route('quests.show', quest.slug || quest.uuid)"
                            class="inline-flex items-center rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-800 shadow-sm hover:border-primary-200 hover:bg-primary-50"
                        >
                            View quest
                        </Link>
                        <template v-if="!hasExistingProposal">
                            <Link
                                v-if="canSendProposal"
                                :href="route('quests.proposals.create', quest.slug || quest.uuid)"
                                class="inline-flex items-center rounded-full bg-primary-600 px-4 py-2 text-sm font-bold text-white shadow-md shadow-primary-900/20 hover:bg-primary-700"
                            >
                                Send proposal
                            </Link>
                            <button
                                v-else
                                type="button"
                                disabled
                                class="inline-flex cursor-not-allowed items-center rounded-full bg-slate-200 px-4 py-2 text-sm font-bold text-slate-500 opacity-70"
                            >
                                Send proposal
                            </button>
                        </template>
                    </div>
                    <p v-if="!workspace.can_submit_proposals" class="text-xs font-semibold text-rose-700">
                        Complete your profile checklist to unlock proposals.
                    </p>
                    <p v-else-if="!quest.category_match" class="text-xs font-semibold text-amber-800">
                        Add this quest’s subcategory to your profile to send a proposal.
                    </p>
                    <p v-else-if="!quest.budget_within_limit" class="text-xs font-semibold text-violet-900">
                        {{ verificationLimitMessage }}
                    </p>
                    <div
                        v-if="hasExistingProposal"
                        class="w-full rounded-2xl border border-emerald-200/90 bg-gradient-to-br from-emerald-50 via-white to-teal-50/60 p-3.5 shadow-sm ring-1 ring-emerald-100"
                    >
                        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-emerald-800">Proposal sent</p>
                        <Link
                            v-if="existingProposal?.show_url"
                            :href="existingProposal.show_url"
                            class="mt-2 inline-flex text-xs font-black uppercase tracking-wide text-emerald-900 underline decoration-emerald-400 underline-offset-2"
                        >
                            Open your proposal →
                        </Link>
                    </div>
                </div>

                <div
                    v-else-if="hasExistingProposal"
                    class="rounded-xl border border-emerald-200/90 bg-emerald-50/70 px-3 py-2 text-[11px] font-bold text-emerald-900"
                >
                    Proposal sent
                    <Link
                        v-if="existingProposal?.show_url"
                        :href="existingProposal.show_url"
                        class="ml-1 underline decoration-emerald-400 underline-offset-2"
                    >
                        View
                    </Link>
                </div>
            </div>
        </div>
    </article>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    quest: { type: Object, required: true },
    workspace: { type: Object, required: true },
    verificationAccess: { type: Object, default: null },
    compact: { type: Boolean, default: false },
    showProposalAction: { type: Boolean, default: true },
    showMatchScore: { type: Boolean, default: true },
    maxSkills: { type: Number, default: 2 },
});

const categoryLabel = computed(() => {
    if (props.compact) {
        return props.quest.category || props.quest.parent_category || '';
    }

    if (props.quest.parent_category && props.quest.category) {
        return `${props.quest.parent_category} · ${props.quest.category}`;
    }

    return props.quest.category || props.quest.parent_category || '';
});

const locationLabel = computed(() => {
    if (props.compact) {
        return [props.quest.city, props.quest.state].filter(Boolean).join(' · ');
    }

    return [props.quest.city, props.quest.lga, props.quest.state].filter(Boolean).join(' · ');
});

const visibleSkills = computed(() => (props.quest.required_skills || []).slice(0, Math.max(0, props.maxSkills)));

const existingProposal = computed(() => props.quest.my_proposal ?? null);

const hasExistingProposal = computed(() => props.quest.has_my_proposal === true || Boolean(existingProposal.value?.show_url));

const canSendProposal = computed(() =>
    props.workspace.can_submit_proposals
    && props.quest.category_match
    && props.quest.budget_within_limit
    && !hasExistingProposal.value,
);

const matchBreakdownTitle = computed(() => {
    const lines = props.quest.match_breakdown || [];
    return lines.length ? lines.join(' · ') : props.quest.match_quality?.label || '';
});

const verificationLimitMessage = computed(() => {
    const access = props.verificationAccess;
    const questBudgetMinor = props.quest.budget_minor;
    if (!access) {
        return 'This quest is above your current verification limit.';
    }
    const limitLabel = access.proposal_limit_formatted || formatBudget(access.proposal_limit_minor);
    return `Budget ${formatBudget(questBudgetMinor)} exceeds your limit (${limitLabel}).`;
});

function formatBudget(minor) {
    return `₦${(Number(minor) / 100).toLocaleString('en-NG', { maximumFractionDigits: 0 })}`;
}

function formatWhen(iso) {
    if (!iso) return '';
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
</script>
