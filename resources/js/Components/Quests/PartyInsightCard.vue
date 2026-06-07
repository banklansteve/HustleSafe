<template>
    <aside
        v-if="hasContent"
        class="rounded-2xl border border-slate-100 bg-white p-4 shadow-md shadow-slate-900/5 ring-1 ring-slate-100 sm:p-5"
    >
        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">
            {{ heading }}
        </p>
        <div class="mt-3 flex items-center gap-3">
            <UserProfileAvatar
                v-if="insight.avatar_url || insight.name"
                :href="profileUrl"
                :src="insight.avatar_url"
                :name="insight.name"
                :alt="insight.name"
                frame-class="h-12 w-12 text-sm shadow-md"
            />
            <div class="min-w-0 flex-1">
                <p class="truncate font-bold text-slate-900">{{ insight.name }}</p>
                <p v-if="insight.location" class="truncate text-xs font-semibold text-slate-500">
                    {{ insight.location }}
                </p>
                <p v-if="insight.tier_label" class="mt-0.5 text-xs font-bold leading-snug text-primary-800">
                    {{ insight.tier_label }}
                </p>
            </div>
        </div>
        <ul class="mt-3 space-y-1.5 text-xs font-semibold leading-relaxed text-slate-700">
            <li v-for="(line, idx) in displayLines" :key="idx" class="flex gap-2">
                <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-primary-500" aria-hidden="true" />
                <span>{{ line }}</span>
            </li>
        </ul>
        <Link
            v-if="profileUrl"
            :href="profileUrl"
            class="mt-3 inline-flex text-xs font-black uppercase tracking-wide text-primary-800 underline decoration-primary-300 underline-offset-2"
        >
            View profile
        </Link>
    </aside>
</template>

<script setup>
import UserProfileAvatar from '@/Components/Ui/UserProfileAvatar.vue';
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    insight: { type: Object, default: () => ({}) },
    heading: { type: String, default: 'Profile' },
});

const hasContent = computed(() => Boolean(props.insight?.name));

const displayLines = computed(() => {
    if (Array.isArray(props.insight?.highlights) && props.insight.highlights.length) {
        return props.insight.highlights;
    }

    const lines = [];
    const i = props.insight;
    if (i?.rating != null) {
        lines.push(`★ ${i.rating}${i.rating_count ? ` (${i.rating_count} reviews)` : ''}`);
    }
    if (i?.quests_posted_90_days > 0) {
        lines.push(`Posted ${i.quests_posted_90_days} quests in the last 90 days`);
    }
    if (i?.jobs_completed_30_days > 0) {
        lines.push(`Completed ${i.jobs_completed_30_days} jobs in the last 30 days`);
    }
    if (i?.jobs_completed_year > 0) {
        lines.push(`Completed ${i.jobs_completed_year} jobs in the last year`);
    }
    if (i?.payment_rate_percent != null) {
        lines.push(`Payment rate: ${i.payment_rate_percent}%`);
    }
    if (i?.completion_rate_percent != null) {
        lines.push(`${i.completion_rate_percent}% completion rate`);
    }
    if (i?.dispute_free_90_days) {
        lines.push('No disputes in the last 90 days');
    } else if (i?.disputes_90_days > 0) {
        lines.push(`${i.disputes_90_days} dispute(s) in the last 90 days`);
    }

    return lines.length ? lines : ['Building track record on HustleSafe'];
});

const profileUrl = computed(() => {
    if (!props.insight?.slug || !props.insight?.profile_route) {
        return null;
    }

    try {
        return route(props.insight.profile_route, props.insight.slug);
    } catch {
        return null;
    }
});
</script>
