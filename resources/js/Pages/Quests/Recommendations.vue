<template>
    <AppShell>
        <Head :title="`Matches · ${quest.title}`" />

        <div class="mx-auto max-w-3xl space-y-4">
            <BackChevronLink :href="route('quests.show', quest.route_key)" aria-label="Back to quest" />

            <header class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6">
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-primary-700">Freelancer matches</p>
                <h1 class="font-display mt-1 text-2xl font-black text-slate-900">
                    More recommendations
                </h1>
                <p class="mt-2 text-sm font-semibold text-slate-600">
                    {{ quest.title }}
                </p>
                <p
                    v-if="freelancer_match_stats?.label"
                    class="mt-3 text-xs font-semibold leading-relaxed text-slate-600"
                >
                    {{ freelancer_match_stats.total }} freelancers match this category.
                    {{ freelancer_match_stats.label }}
                </p>
                <p class="mt-2 text-xs font-semibold leading-relaxed text-slate-500">
                    Know someone who’d be a great fit? Invite them — they’ll receive your quest by email and in-app notification.
                </p>
            </header>

            <ul class="space-y-3">
                <li
                    v-for="f in recommendations"
                    :key="f.id"
                    class="rounded-xl border border-slate-100 bg-white p-4 shadow-sm ring-1 ring-slate-100"
                >
                    <div class="flex items-start justify-between gap-3">
                        <Link
                            :href="route('freelancers.public', f.slug)"
                            class="flex min-w-0 flex-1 items-center gap-3 text-sm font-bold text-slate-900 hover:text-primary-800"
                        >
                            <UserProfileAvatar
                                :href="route('freelancers.public', f.slug)"
                                :src="f.avatar_url"
                                :name="f.name"
                                :alt="f.name"
                                frame-class="h-11 w-11 text-xs ring-2 ring-white"
                            />
                            <span class="min-w-0">
                                <span class="block truncate">{{ f.name }}</span>
                                <span v-if="f.location" class="block truncate text-[10px] font-semibold text-slate-500">
                                    {{ f.location }}
                                </span>
                            </span>
                        </Link>
                        <span class="shrink-0 text-right text-[10px] font-black text-primary-700">
                            {{ f.match_score }}%
                            <span v-if="f.match_quality?.label" class="mt-0.5 block font-semibold text-slate-600">
                                {{ f.match_quality.label }}
                            </span>
                        </span>
                    </div>
                    <p v-if="f.why_recommended" class="mt-2 text-xs font-semibold text-slate-600">
                        {{ f.why_recommended }}
                    </p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button
                            v-if="isTagged(f.id)"
                            type="button"
                            disabled
                            class="rounded-full border border-emerald-200 bg-emerald-50 px-4 py-2 text-xs font-black uppercase tracking-wide text-emerald-800"
                        >
                            Tagged
                        </button>
                        <button
                            v-else
                            type="button"
                            class="rounded-full bg-primary-600 px-4 py-2 text-xs font-black uppercase tracking-wide text-white shadow-sm hover:bg-primary-700 disabled:opacity-60"
                            :disabled="tagBusy === f.id"
                            @click="tagFreelancer(f)"
                        >
                            {{ tagBusy === f.id ? 'Inviting…' : 'Invite' }}
                        </button>
                    </div>
                </li>
            </ul>

            <p v-if="!recommendations.length" class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm font-semibold text-slate-600">
                No additional matches right now. Check back after more freelancers complete profiles in this category.
            </p>
        </div>
    </AppShell>
</template>

<script setup>
import BackChevronLink from '@/Components/Ui/BackChevronLink.vue';
import UserProfileAvatar from '@/Components/Ui/UserProfileAvatar.vue';
import AppShell from '@/Layouts/AppShell.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    quest: { type: Object, required: true },
    recommendations: { type: Array, default: () => [] },
    freelancer_match_stats: { type: Object, default: () => ({}) },
    invited_ids: { type: Array, default: () => [] },
});

const invitedIds = ref([...props.invited_ids.map((id) => Number(id))]);
const tagBusy = ref(null);

function isTagged(id) {
    return invitedIds.value.includes(Number(id));
}

function tagFreelancer(f) {
    const id = Number(f.id);
    if (isTagged(id)) {
        return;
    }

    const nextIds = [...invitedIds.value, id];
    tagBusy.value = id;
    router.post(
        route('quests.invites.store', props.quest.route_key),
        { freelancer_ids: nextIds },
        {
            preserveScroll: true,
            onSuccess: () => {
                invitedIds.value = nextIds;
            },
            onFinish: () => {
                tagBusy.value = null;
            },
        },
    );
}
</script>
