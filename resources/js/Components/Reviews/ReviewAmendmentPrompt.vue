<template>
    <div
        v-if="prompts.length"
        class="fixed inset-x-4 bottom-4 z-40 mx-auto max-w-lg rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-lg sm:inset-x-auto sm:right-6 sm:left-auto"
    >
        <p class="text-[10px] font-black uppercase tracking-wide text-amber-900">Review update required</p>
        <p class="mt-1 text-sm font-semibold text-slate-900">{{ active.quest_title || 'Your review' }}</p>
        <p class="mt-2 text-sm text-slate-700">{{ active.instructions }}</p>
        <p v-if="active.expires_at" class="mt-2 text-xs font-semibold text-amber-800">
            Respond by {{ formatExpiry(active.expires_at) }}
        </p>
        <a
            :href="route('account.show', { amend_review: active.review_id })"
            class="mt-3 inline-flex rounded-xl bg-primary-700 px-4 py-2 text-xs font-black uppercase text-white"
        >
            Update review
        </a>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const prompts = computed(() => page.props.review_amendment_prompts ?? []);
const active = computed(() => prompts.value[0] ?? {});

function formatExpiry(iso) {
    try {
        return new Date(iso).toLocaleString();
    } catch {
        return iso;
    }
}
</script>
