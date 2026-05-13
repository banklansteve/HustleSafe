<template>
    <AppShell>
        <Head title="Explore quests" />

        <div
            v-if="workspace.enabled && workspacePanelLines.length"
            class="mb-8 rounded-2xl border border-secondary-200/80 bg-gradient-to-r from-secondary-50 via-amber-50/90 to-secondary-50 p-5 shadow-sm ring-1 ring-secondary-100 sm:p-6"
            role="status"
        >
            <p class="text-xs font-black uppercase tracking-[0.2em] text-secondary-800">
                Profile & verification
            </p>
            <ul class="mt-3 list-inside list-disc space-y-1.5 text-sm font-semibold text-secondary-950">
                <li v-for="(line, i) in workspacePanelLines" :key="i">
                    {{ line }}
                </li>
            </ul>
            <div class="mt-4 flex flex-wrap gap-3">
                <Link
                    :href="route('account.show', { tab: 'overview' })"
                    class="inline-flex items-center rounded-full bg-secondary-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-secondary-700"
                >
                    Account settings
                </Link>
                <Link
                    :href="route('verifications.index')"
                    class="inline-flex items-center rounded-full border border-secondary-300 bg-white px-4 py-2 text-xs font-bold text-secondary-900 shadow-sm hover:bg-secondary-50"
                >
                    Trust & verifications
                </Link>
            </div>
            <p
                v-if="workspace.tier === 'limited' && workspace.can_submit_offers"
                class="mt-3 text-xs font-semibold text-secondary-900/90"
            >
                Until your ID is approved you can send up to {{ workspace.limited_slots_remaining }} more modest
                offers (see budget cap on each quest).
            </p>
        </div>

        <div class="rounded-[2rem] bg-gradient-to-br from-primary-800 via-slate-900 to-slate-950 px-6 py-10 text-white shadow-xl ring-1 ring-white/10 sm:px-10">
            <p class="text-xs font-bold uppercase tracking-[0.25em] text-teal-200/90">
                For you
            </p>
            <h1 class="font-display mt-3 text-3xl font-black tracking-tight sm:text-4xl">
                Matched open quests
            </h1>
            <p class="mt-4 max-w-2xl text-base font-semibold leading-relaxed text-teal-50">
                Ranked by your categories, distance, and how fresh the brief is. More signals (exact coordinates on quests)
                make this sharper over time.
            </p>
        </div>

        <div class="mt-10 space-y-5">
            <div
                v-for="q in quests"
                :key="q.uuid"
                class="rounded-[1.5rem] border border-slate-100 bg-white p-6 shadow-sm ring-1 ring-slate-100 transition hover:border-primary-200 hover:shadow-md sm:p-8"
            >
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <p class="font-display text-lg font-bold text-slate-900 sm:text-xl">
                            {{ q.title }}
                        </p>
                        <div class="mt-3 flex flex-wrap gap-2 text-sm font-semibold text-slate-600">
                            <span
                                v-if="q.category"
                                class="rounded-full bg-primary-50 px-3 py-1 text-xs font-bold uppercase tracking-wide text-primary-900 ring-1 ring-primary-100"
                            >
                                {{ q.category }}
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
                        class="flex h-20 w-20 shrink-0 flex-col items-center justify-center rounded-2xl bg-gradient-to-br from-primary-600 to-teal-600 text-center shadow-lg shadow-primary-900/25 ring-1 ring-white/20"
                    >
                        <p class="text-xs font-bold uppercase tracking-wide text-white/80">
                            Match
                        </p>
                        <p class="font-display text-2xl font-black text-white">
                            {{ q.match_score }}
                        </p>
                    </div>
                </div>
                <p class="mt-4 text-xs font-semibold text-slate-500">
                    Posted {{ formatWhen(q.posted_at) }}
                </p>
                <div class="mt-5 flex flex-wrap items-center gap-3">
                    <Link
                        :href="route('quests.show', q.uuid)"
                        class="inline-flex items-center rounded-full border border-slate-200 bg-white px-5 py-2.5 text-sm font-bold text-slate-800 shadow-sm hover:border-primary-200 hover:bg-primary-50"
                    >
                        View quest
                    </Link>
                    <button
                        type="button"
                        class="inline-flex items-center rounded-full bg-primary-600 px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-primary-900/20 hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="!workspace.can_submit_offers || !q.category_match"
                        @click="openOfferModal(q)"
                    >
                        Send offer
                    </button>
                    <p v-if="!workspace.can_submit_offers" class="self-center text-xs font-semibold text-rose-700">
                        Complete the checklist above to unlock offers.
                    </p>
                    <p v-else-if="!q.category_match" class="self-center text-xs font-semibold text-amber-800">
                        Add this quest’s subcategory to your profile to send an offer.
                    </p>
                </div>
            </div>

            <p
                v-if="quests.length === 0"
                class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-6 py-12 text-center text-base font-semibold text-slate-600"
            >
                No open quests right now — check back soon or widen your categories in profile.
            </p>
        </div>

        <Teleport to="body">
            <div
                v-if="offerTarget"
                class="fixed inset-0 z-[80] flex items-end justify-center bg-slate-950/50 p-4 sm:items-center"
                role="dialog"
                aria-modal="true"
                :aria-label="`Offer: ${offerTarget.title}`"
                @click.self="closeOfferModal"
            >
                <div
                    class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl sm:p-8"
                    @click.stop
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Offer for</p>
                            <p class="font-display text-lg font-bold text-slate-900">
                                {{ offerTarget.title }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="rounded-full p-2 text-slate-500 hover:bg-slate-100"
                            aria-label="Close"
                            @click="closeOfferModal"
                        >
                            ✕
                        </button>
                    </div>
                    <form class="mt-6 space-y-4" @submit.prevent="submitOffer">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Your pitch</label>
                            <textarea
                                v-model="offerForm.pitch"
                                required
                                rows="5"
                                class="mt-1 w-full rounded-xl border-slate-200 text-sm font-medium shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                placeholder="How you will deliver, timeline, and what is included."
                            />
                            <InputError class="mt-1" :message="offerForm.errors.pitch" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">
                                Quote (₦, optional)
                            </label>
                            <input
                                v-model.number="offerForm.quoted_ngn"
                                type="number"
                                min="0"
                                step="1"
                                class="mt-1 w-full rounded-xl border-slate-200 text-sm font-medium shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                placeholder="e.g. 75000"
                            />
                            <p class="mt-1 text-xs font-medium text-slate-500">Whole naira. Leave blank if you want to align in chat first.</p>
                            <InputError class="mt-1" :message="offerForm.errors.quoted_amount_minor" />
                        </div>
                        <InputError class="mt-1" :message="offerForm.errors.offer" />
                        <InputError class="mt-1" :message="offerForm.errors.workspace" />
                        <div class="flex flex-wrap gap-3 pt-2">
                            <button
                                type="submit"
                                class="rounded-full bg-primary-600 px-6 py-2.5 text-sm font-bold text-white shadow-md hover:bg-primary-700 disabled:opacity-50"
                                :disabled="offerForm.processing"
                            >
                                Submit offer
                            </button>
                            <button
                                type="button"
                                class="rounded-full border border-slate-200 px-6 py-2.5 text-sm font-bold text-slate-800 hover:bg-slate-50"
                                @click="closeOfferModal"
                            >
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AppShell>
</template>

<script setup>
import InputError from '@/Components/InputError.vue';
import AppShell from '@/Layouts/AppShell.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
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
});

const workspacePanelLines = computed(() => {
    const ws = props.workspace;
    if (!ws?.enabled) {
        return [];
    }
    const lines = [];
    for (const b of ws.blockers || []) {
        if (b?.message) {
            lines.push(b.message);
        }
    }
    for (const h of ws.hints || []) {
        if (h?.message) {
            lines.push(h.message);
        }
    }

    return lines;
});

const offerTarget = ref(null);

const offerForm = useForm({
    pitch: '',
    quoted_ngn: null,
});

watch(offerTarget, (q) => {
    if (!q) {
        offerForm.reset();
        offerForm.clearErrors();
    }
});

function openOfferModal(q) {
    if (!props.workspace.can_submit_offers || !q.category_match) {
        return;
    }
    offerTarget.value = q;
    offerForm.clearErrors();
}

function closeOfferModal() {
    offerTarget.value = null;
}

function submitOffer() {
    if (!offerTarget.value) {
        return;
    }
    offerForm
        .transform((data) => ({
            pitch: data.pitch,
            quoted_amount_minor:
                data.quoted_ngn !== null && data.quoted_ngn !== '' && !Number.isNaN(Number(data.quoted_ngn))
                    ? Math.round(Number(data.quoted_ngn) * 100)
                    : null,
        }))
        .post(route('quests.offers.store', offerTarget.value.uuid), {
            preserveScroll: true,
            onSuccess: () => closeOfferModal(),
        });
}

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
</script>
