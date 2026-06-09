<template>
    <section
        v-if="items?.length"
        class="rounded-xl border p-4 shadow-sm ring-1 sm:p-5"
        :class="panelClass"
    >
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.2em]" :class="eyebrowClass">
                    {{ eyebrow }}
                </p>
                <h2 class="font-display mt-1 text-lg font-bold text-slate-900">
                    {{ title }}
                </h2>
                <p v-if="subtitle" class="mt-1 text-xs font-semibold leading-relaxed" :class="subtitleClass">
                    {{ subtitle }}
                </p>
            </div>
            <span
                v-if="actionCount"
                class="rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-wide"
                :class="badgeClass"
            >
                {{ actionCount }} need{{ actionCount === 1 ? 's' : '' }} you
            </span>
        </div>

        <ul class="mt-4 space-y-2">
            <li
                v-for="item in items"
                :key="`${item.offer_id}-${item.thread_id}`"
                class="rounded-xl border bg-white/90 px-3 py-3 shadow-sm ring-1"
                :class="itemClass(item)"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-black text-slate-900">
                            {{ item.headline }}
                        </p>
                        <p class="mt-0.5 text-[11px] font-bold uppercase tracking-wide text-slate-500">
                            {{ item.counterparty_name }}
                            <span v-if="compact"> · {{ item.quest_title }}</span>
                        </p>
                        <p v-if="item.preview" class="mt-2 text-xs font-semibold leading-relaxed text-slate-600">
                            “{{ item.preview }}”
                        </p>
                        <p class="mt-1 text-[10px] font-semibold text-slate-400">
                            {{ formatWhen(item.latest_at) }}
                            <span v-if="item.message_count"> · {{ item.message_count }} message{{ item.message_count === 1 ? '' : 's' }}</span>
                        </p>
                    </div>
                    <div class="flex shrink-0 flex-col gap-2">
                        <Link
                            :href="item.clarify_url"
                            class="rounded-full px-3 py-1.5 text-center text-[10px] font-black uppercase tracking-wide text-white shadow-sm"
                            :class="ctaClass(item)"
                        >
                            {{ ctaLabel(item) }}
                        </Link>
                        <Link
                            v-if="showProposalLink && item.proposal_url"
                            :href="item.proposal_url"
                            class="text-center text-[10px] font-black uppercase tracking-wide text-slate-600 underline decoration-slate-300 underline-offset-2 hover:text-slate-900"
                        >
                            View proposal
                        </Link>
                    </div>
                </div>
            </li>
        </ul>
    </section>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    items: { type: Array, default: () => [] },
    eyebrow: { type: String, default: 'Pre-award clarifications' },
    title: { type: String, default: 'Clarifying questions' },
    subtitle: {
        type: String,
        default: 'Questions and answers stay on HustleSafe — open a thread even if you missed the notification.',
    },
    compact: { type: Boolean, default: false },
    showProposalLink: { type: Boolean, default: true },
    variant: { type: String, default: 'sky' },
});

const actionCount = computed(() => props.items.filter((item) => item.action_required).length);

const panelClass = computed(() => ({
    sky: 'border-sky-200 bg-gradient-to-br from-sky-50/90 via-white to-cyan-50/60 ring-sky-100',
    violet: 'border-violet-200 bg-gradient-to-br from-violet-50/90 via-white to-fuchsia-50/60 ring-violet-100',
    amber: 'border-amber-200 bg-gradient-to-br from-amber-50/90 via-white to-orange-50/50 ring-amber-100',
}[props.variant] || 'border-sky-200 bg-sky-50/80 ring-sky-100'));

const eyebrowClass = computed(() => ({
    sky: 'text-sky-800',
    violet: 'text-violet-800',
    amber: 'text-amber-900',
}[props.variant] || 'text-sky-800'));

const subtitleClass = computed(() => ({
    sky: 'text-sky-900/80',
    violet: 'text-violet-900/80',
    amber: 'text-amber-900/80',
}[props.variant] || 'text-sky-900/80'));

const badgeClass = computed(() => ({
    sky: 'bg-sky-700 text-white',
    violet: 'bg-violet-700 text-white',
    amber: 'bg-amber-700 text-white',
}[props.variant] || 'bg-sky-700 text-white'));

function itemClass(item) {
    if (item.action_required) {
        return item.tone === 'action'
            ? 'border-rose-200 ring-rose-100'
            : 'border-sky-200 ring-sky-100';
    }

    return 'border-slate-100 ring-slate-100';
}

function ctaClass(item) {
    if (item.action_required) {
        return item.tone === 'action' ? 'bg-rose-600 hover:bg-rose-700' : 'bg-sky-600 hover:bg-sky-700';
    }

    return 'bg-slate-800 hover:bg-slate-900';
}

function ctaLabel(item) {
    if (item.action_required && item.tone === 'action') {
        return item.unanswered_questions_count > 0 && !props.compact ? 'Answer now' : 'Open thread';
    }

    if (item.tone === 'waiting') {
        return 'View thread';
    }

    return 'Open thread';
}

function formatWhen(value) {
    if (!value) {
        return '—';
    }

    try {
        return new Date(value).toLocaleString('en-NG', {
            dateStyle: 'medium',
            timeStyle: 'short',
            timeZone: 'Africa/Lagos',
        });
    } catch {
        return value;
    }
}
</script>
