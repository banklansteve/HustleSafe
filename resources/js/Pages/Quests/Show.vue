<template>
    <AppShell>
        <Head :title="quest.title" />

        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs font-black uppercase tracking-[0.2em] text-primary-700">
                    Quest · {{ quest.reference_code }}
                </p>
                <h1 class="font-display mt-2 text-3xl font-black tracking-tight text-slate-900 sm:text-4xl">
                    {{ quest.title }}
                </h1>
                <div class="mt-3 flex flex-wrap gap-2 text-xs font-bold">
                    <span
                        class="rounded-full px-3 py-1 uppercase tracking-wide ring-1"
                        :class="statusPill(quest.status)"
                    >
                        {{ quest.status }}
                    </span>
                    <span v-if="quest.category" class="rounded-full bg-primary-50 px-3 py-1 text-primary-900 ring-1 ring-primary-100">
                        {{ quest.category.parent_name ? `${quest.category.parent_name} · ` : '' }}{{ quest.category.name }}
                    </span>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-slate-700 ring-1 ring-slate-200">
                        {{ [quest.location.city, quest.location.lga, quest.location.state].filter(Boolean).join(' · ') }}
                    </span>
                    <span v-if="quest.visibility" class="rounded-full bg-violet-50 px-3 py-1 text-violet-900 ring-1 ring-violet-100">
                        {{ visibilityLabel(quest.visibility) }}
                    </span>
                    <span v-if="Number(quest.views_count) > 0" class="rounded-full bg-slate-50 px-3 py-1 text-slate-600 ring-1 ring-slate-200">
                        {{ quest.views_count }} views
                    </span>
                    <span v-if="Number(quest.saves_count) > 0" class="rounded-full bg-slate-50 px-3 py-1 text-slate-600 ring-1 ring-slate-200">
                        {{ quest.saves_count }} saves
                    </span>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <Link
                    :href="route('quests.index')"
                    class="inline-flex items-center rounded-full border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-800 shadow-sm hover:border-primary-200 hover:bg-primary-50"
                >
                    All quests
                </Link>
                <Link
                    v-if="can_edit"
                    :href="route('quests.create')"
                    class="inline-flex items-center rounded-full border border-primary-200 bg-primary-50 px-4 py-2 text-xs font-bold text-primary-900 shadow-sm hover:bg-primary-100"
                >
                    New quest
                </Link>
                <button
                    v-if="isFreelancer && workspace.enabled"
                    type="button"
                    class="inline-flex items-center rounded-full border px-4 py-2 text-xs font-bold shadow-sm transition"
                    :class="is_bookmarked ? 'border-emerald-200 bg-emerald-50 text-emerald-900 hover:bg-emerald-100' : 'border-slate-200 bg-white text-slate-800 hover:border-primary-200 hover:bg-primary-50'"
                    @click="toggleBookmark"
                >
                    {{ is_bookmarked ? 'Saved' : 'Save quest' }}
                </button>
            </div>
        </div>

        <div v-if="workspace.enabled && workspacePanelLines.length" class="mt-8 rounded-2xl border border-secondary-200/80 bg-gradient-to-r from-secondary-50 via-amber-50/90 to-secondary-50 p-5 shadow-sm ring-1 ring-secondary-100 sm:p-6">
            <p class="text-xs font-black uppercase tracking-[0.2em] text-secondary-800">
                Freelancer workspace
            </p>
            <ul class="mt-3 list-inside list-disc space-y-1.5 text-sm font-semibold text-secondary-950">
                <li v-for="(line, i) in workspacePanelLines" :key="i">
                    {{ line }}
                </li>
            </ul>
        </div>

        <div class="mt-10 grid gap-8 lg:grid-cols-12 lg:gap-10">
            <div class="space-y-8 lg:col-span-8">
                <section class="rounded-2xl border border-slate-100 bg-white p-6 shadow-md shadow-slate-900/5 ring-1 ring-slate-100 sm:p-8">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <h2 class="font-display text-lg font-bold text-slate-900">
                            Brief
                        </h2>
                        <p class="text-sm font-black text-primary-800">
                            {{ formatBudget(quest.budget_minor) }}
                        </p>
                    </div>
                    <p class="mt-4 whitespace-pre-wrap text-sm font-medium leading-relaxed text-slate-700">
                        {{ quest.description }}
                    </p>
                    <dl class="mt-8 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-xl bg-slate-50/90 p-4 ring-1 ring-slate-100">
                            <dt class="text-[10px] font-black uppercase tracking-wide text-slate-500">
                                Start timing
                            </dt>
                            <dd class="mt-1 text-sm font-bold text-slate-900">
                                {{ timingLabel(quest.start_timing) }}
                            </dd>
                        </div>
                        <div class="rounded-xl bg-slate-50/90 p-4 ring-1 ring-slate-100">
                            <dt class="text-[10px] font-black uppercase tracking-wide text-slate-500">
                                Est. completion
                            </dt>
                            <dd class="mt-1 text-sm font-bold text-slate-900">
                                {{ quest.estimated_completion_days }} days
                            </dd>
                        </div>
                        <div class="rounded-xl bg-slate-50/90 p-4 ring-1 ring-slate-100">
                            <dt class="text-[10px] font-black uppercase tracking-wide text-slate-500">
                                Site visits
                            </dt>
                            <dd class="mt-1 text-sm font-bold text-slate-900">
                                {{ quest.site_visits_allowed ? 'Allowed before proposals' : 'Not requested' }}
                            </dd>
                        </div>
                        <div class="rounded-xl bg-slate-50/90 p-4 ring-1 ring-slate-100">
                            <dt class="text-[10px] font-black uppercase tracking-wide text-slate-500">
                                Due target
                            </dt>
                            <dd class="mt-1 text-sm font-bold text-slate-900">
                                {{ formatWhen(quest.due_at) }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <section class="rounded-2xl border border-slate-100 bg-white p-6 shadow-md shadow-slate-900/5 ring-1 ring-slate-100 sm:p-8">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="font-display text-lg font-bold text-slate-900">
                            Gallery
                        </h2>
                        <span class="text-xs font-semibold text-slate-500">{{ quest.files.length }} / 10</span>
                    </div>
                    <QuestFileGallery class="mt-5" :files="quest.files" :can-delete="can_edit" @delete="removeFile" />
                    <div v-if="can_edit" class="mt-6">
                        <label class="block text-xs font-black uppercase tracking-wide text-slate-500">Add file</label>
                        <input
                            type="file"
                            accept=".jpg,.jpeg,.png,.webp,.gif,.pdf"
                            class="mt-2 block w-full text-sm font-semibold text-slate-700 file:mr-4 file:rounded-full file:border-0 file:bg-primary-600 file:px-4 file:py-2 file:text-xs file:font-black file:text-white hover:file:bg-primary-700"
                            @change="uploadFile"
                        />
                        <InputError class="mt-2" :message="uploadForm.errors.file" />
                    </div>
                </section>

                <section v-if="similar_quests.length" class="rounded-2xl border border-slate-100 bg-white p-6 shadow-md shadow-slate-900/5 ring-1 ring-slate-100 sm:p-8">
                    <h2 class="font-display text-lg font-bold text-slate-900">
                        Similar quests nearby
                    </h2>
                    <ul class="mt-5 space-y-3">
                        <li v-for="s in similar_quests" :key="s.uuid">
                            <Link
                                :href="route('quests.show', s.uuid)"
                                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-100 bg-slate-50/80 px-4 py-3 text-sm font-bold text-slate-900 ring-1 ring-slate-100 transition hover:border-primary-200 hover:bg-white"
                            >
                                <span class="min-w-0 flex-1 truncate">{{ s.title }}</span>
                                <span class="text-xs font-semibold text-primary-800">{{ formatBudget(s.budget_minor) }}</span>
                            </Link>
                        </li>
                    </ul>
                </section>
            </div>

            <aside class="space-y-6 lg:col-span-4">
                <section class="rounded-2xl border border-slate-100 bg-white p-6 shadow-md shadow-slate-900/5 ring-1 ring-slate-100">
                    <h2 class="font-display text-sm font-bold uppercase tracking-wide text-slate-500">
                        Client
                    </h2>
                    <div class="mt-4 flex items-center gap-3">
                        <span class="flex h-12 w-12 shrink-0 overflow-hidden rounded-2xl bg-gradient-to-br from-primary-600 to-teal-700 text-sm font-black text-white ring-2 ring-white shadow-md">
                            <img v-if="quest.client.avatar_url" :src="quest.client.avatar_url" alt="" class="h-full w-full object-cover" />
                            <span v-else class="flex h-full w-full items-center justify-center">{{ initials(quest.client.name) }}</span>
                        </span>
                        <div class="min-w-0">
                            <p class="truncate font-bold text-slate-900">
                                {{ quest.client.name }}
                            </p>
                            <p v-if="quest.client.username" class="truncate text-xs font-semibold text-slate-500">
                                @{{ quest.client.username }}
                            </p>
                            <Link
                                v-if="quest.client.slug"
                                :href="route('freelancers.public', quest.client.slug)"
                                class="mt-0.5 inline-block text-xs font-bold text-primary-700 hover:underline"
                            >
                                View profile
                            </Link>
                        </div>
                    </div>
                </section>

                <section v-if="isFreelancer" class="rounded-2xl border border-slate-100 bg-gradient-to-br from-primary-50 via-white to-teal-50 p-6 shadow-md ring-1 ring-primary-100">
                    <h2 class="font-display text-lg font-bold text-slate-900">
                        Proposals
                    </h2>
                    <div v-if="my_offer" class="mt-4 rounded-xl border border-emerald-100 bg-white/90 p-4 text-sm font-semibold text-slate-800 ring-1 ring-emerald-50">
                        <p class="text-xs font-black uppercase tracking-wide text-emerald-700">
                            Your offer · {{ my_offer.status }}
                        </p>
                        <p class="mt-2 line-clamp-4 text-sm">
                            {{ my_offer.pitch }}
                        </p>
                        <p v-if="my_offer.quoted_amount_minor" class="mt-2 text-xs font-bold text-slate-600">
                            Quoted {{ formatBudget(my_offer.quoted_amount_minor) }}
                        </p>
                    </div>
                    <button
                        v-else-if="can_offer"
                        type="button"
                        class="mt-4 w-full rounded-full bg-primary-600 px-5 py-3 text-sm font-black text-white shadow-lg shadow-primary-900/20 hover:bg-primary-700"
                        @click="openOffer"
                    >
                        Send proposal
                    </button>
                    <p v-else class="mt-3 text-xs font-semibold text-amber-900">
                        <span v-if="!workspace.can_submit_offers">Complete your freelancer workspace checklist to unlock proposals.</span>
                        <span v-else>Add this quest’s subcategory to your profile so we know you are qualified for this brief.</span>
                    </p>
                </section>

                <section v-if="can_edit && form_options" class="rounded-2xl border border-slate-100 bg-white p-6 shadow-md shadow-slate-900/5 ring-1 ring-slate-100">
                    <h2 class="font-display text-lg font-bold text-slate-900">
                        Edit quest
                    </h2>
                    <form class="mt-5 space-y-4" @submit.prevent="submitEdit">
                        <div>
                            <InputLabel for="e_title" value="Title" />
                            <TextInput id="e_title" v-model="editForm.title" type="text" class="mt-1 w-full rounded-xl border-slate-200 shadow-sm" />
                            <InputError class="mt-1" :message="editForm.errors.title" />
                        </div>
                        <div>
                            <InputLabel for="e_desc" value="Description" />
                            <textarea
                                id="e_desc"
                                v-model="editForm.description"
                                rows="5"
                                class="mt-1 w-full rounded-xl border-slate-200 text-sm font-medium shadow-sm"
                            />
                            <InputError class="mt-1" :message="editForm.errors.description" />
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <InputLabel for="e_parent" value="Domain" />
                                <select
                                    id="e_parent"
                                    v-model.number="editParentId"
                                    class="mt-1 w-full rounded-xl border-slate-200 text-sm font-semibold shadow-sm"
                                    @change="onEditParent"
                                >
                                    <option v-for="p in form_options.category_tree" :key="p.id" :value="p.id">
                                        {{ p.name }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <InputLabel for="e_cat" value="Subcategory" />
                                <select id="e_cat" v-model.number="editForm.quest_category_id" class="mt-1 w-full rounded-xl border-slate-200 text-sm font-semibold shadow-sm">
                                    <option v-for="c in editLeafOptions" :key="c.id" :value="c.id">
                                        {{ c.name }}
                                    </option>
                                </select>
                                <InputError class="mt-1" :message="editForm.errors.quest_category_id" />
                            </div>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <InputLabel for="e_state" value="State" />
                                <select
                                    id="e_state"
                                    v-model.number="editForm.state_id"
                                    class="mt-1 w-full rounded-xl border-slate-200 text-sm font-semibold shadow-sm"
                                    @change="editForm.local_government_id = 0"
                                >
                                    <option v-for="s in form_options.locations" :key="s.id" :value="s.id">
                                        {{ s.name }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <InputLabel for="e_lga" value="LGA" />
                                <select id="e_lga" v-model.number="editForm.local_government_id" class="mt-1 w-full rounded-xl border-slate-200 text-sm font-semibold shadow-sm">
                                    <option v-for="lg in editLgas" :key="lg.id" :value="lg.id">
                                        {{ lg.name }}
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <InputLabel for="e_city" value="City" />
                            <TextInput id="e_city" v-model="editForm.city" type="text" class="mt-1 w-full rounded-xl border-slate-200 shadow-sm" />
                        </div>
                        <div>
                            <InputLabel for="e_budget" value="Budget (₦)" />
                            <input
                                id="e_budget"
                                v-model.number="editBudgetNgn"
                                type="range"
                                min="100"
                                max="500000"
                                step="500"
                                class="mt-2 h-2 w-full cursor-pointer accent-primary-600"
                            />
                            <p class="mt-1 text-xs font-bold text-primary-800">
                                {{ formatBudget(editForm.budget_amount_minor) }}
                            </p>
                            <InputError class="mt-1" :message="editForm.errors.budget_amount_minor" />
                        </div>
                        <div>
                            <InputLabel for="e_timing" value="Start timing" />
                            <select id="e_timing" v-model="editForm.start_timing" class="mt-1 w-full rounded-xl border-slate-200 text-sm font-semibold shadow-sm">
                                <option v-for="o in start_timing_options" :key="o.value" :value="o.value">
                                    {{ o.label }}
                                </option>
                            </select>
                        </div>
                        <div v-if="editForm.start_timing === 'scheduled'">
                            <InputLabel for="e_sched" value="Scheduled date" />
                            <TextInput id="e_sched" v-model="editForm.scheduled_start_date" type="date" class="mt-1 w-full rounded-xl border-slate-200 shadow-sm" />
                            <InputError class="mt-1" :message="editForm.errors.scheduled_start_date" />
                        </div>
                        <div>
                            <InputLabel for="e_days" value="Est. completion (days)" />
                            <input
                                id="e_days"
                                v-model.number="editForm.estimated_completion_days"
                                type="number"
                                min="1"
                                max="365"
                                class="mt-1 w-full rounded-xl border-slate-200 text-sm font-semibold shadow-sm"
                            />
                        </div>
                        <label class="flex items-center gap-2 text-sm font-bold text-slate-800">
                            <input v-model="editForm.site_visits_allowed" type="checkbox" class="rounded border-slate-300 text-primary-600 focus:ring-primary-500" />
                            Site visits allowed
                        </label>
                        <button
                            type="submit"
                            class="w-full rounded-full bg-slate-900 py-3 text-sm font-black text-white shadow-md hover:bg-slate-800 disabled:opacity-50"
                            :disabled="editForm.processing"
                        >
                            Save changes
                        </button>
                    </form>
                </section>

                <section v-if="can_edit" class="rounded-2xl border border-slate-100 bg-white p-6 shadow-md shadow-slate-900/5 ring-1 ring-slate-100">
                    <h2 class="font-display text-lg font-bold text-slate-900">
                        Tag freelancers
                    </h2>
                    <p class="mt-1 text-xs font-semibold text-slate-500">
                        They get a spotlight ping with your quest link (deduped against category alerts).
                    </p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <span
                            v-for="id in inviteIds"
                            :key="id"
                            class="inline-flex items-center gap-2 rounded-full bg-secondary-50 px-3 py-1 text-xs font-bold text-secondary-950 ring-1 ring-secondary-100"
                        >
                            {{ inviteLabel(id) }}
                            <button type="button" class="font-black text-secondary-700 hover:text-secondary-900" @click="removeInvite(id)">
                                ✕
                            </button>
                        </span>
                    </div>
                    <TextInput
                        v-model="inviteQuery"
                        type="search"
                        class="mt-4 w-full rounded-xl border-slate-200 text-sm font-semibold shadow-sm"
                        placeholder="Search freelancers…"
                    />
                    <ul v-if="inviteHits.length" class="mt-2 max-h-40 overflow-auto rounded-xl border border-slate-100 bg-white shadow-md">
                        <li v-for="u in inviteHits" :key="u.id">
                            <button type="button" class="flex w-full px-3 py-2 text-left text-sm font-semibold hover:bg-primary-50" @click="addInvite(u)">
                                {{ u.name }}
                            </button>
                        </li>
                    </ul>
                    <button
                        type="button"
                        class="mt-4 w-full rounded-full border border-primary-200 bg-primary-50 py-2.5 text-xs font-black uppercase tracking-wide text-primary-900 hover:bg-primary-100"
                        @click="syncInvites"
                    >
                        Sync tags
                    </button>
                </section>

                <section v-if="top_freelancers.length" class="rounded-2xl border border-slate-100 bg-white p-6 shadow-md shadow-slate-900/5 ring-1 ring-slate-100">
                    <h2 class="font-display text-lg font-bold text-slate-900">
                        Top freelancers here
                    </h2>
                    <ul class="mt-4 space-y-3">
                        <li v-for="f in top_freelancers" :key="f.id">
                            <Link
                                :href="route('freelancers.public', f.slug)"
                                class="flex items-center justify-between gap-3 rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-2 text-sm font-bold text-slate-900 ring-1 ring-slate-100 hover:border-primary-200"
                            >
                                <span class="flex min-w-0 items-center gap-2">
                                    <span class="flex h-9 w-9 shrink-0 overflow-hidden rounded-full bg-slate-200">
                                        <img v-if="f.avatar_url" :src="f.avatar_url" alt="" class="h-full w-full object-cover" />
                                    </span>
                                    <span class="truncate">{{ f.name }}</span>
                                </span>
                                <span class="shrink-0 text-[10px] font-black text-primary-700">★ {{ f.trust }}</span>
                            </Link>
                        </li>
                    </ul>
                </section>

                <section v-if="can_edit" class="rounded-2xl border border-rose-100 bg-rose-50/60 p-6 shadow-sm ring-1 ring-rose-100">
                    <h2 class="font-display text-lg font-bold text-rose-900">
                        Danger zone
                    </h2>
                    <p class="mt-2 text-xs font-semibold text-rose-800/90">
                        Deletes the quest and all uploaded files. Only available while the quest is unassigned and open or draft.
                    </p>
                    <button
                        type="button"
                        class="mt-4 w-full rounded-full bg-rose-600 py-3 text-sm font-black text-white shadow-md hover:bg-rose-700"
                        @click="confirmDelete"
                    >
                        Delete quest
                    </button>
                </section>
            </aside>
        </div>

        <Teleport to="body">
            <div
                v-if="offerOpen"
                class="fixed inset-0 z-[80] flex items-end justify-center bg-slate-950/50 p-4 sm:items-center"
                role="dialog"
                aria-modal="true"
                @click.self="offerOpen = false"
            >
                <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl sm:p-8" @click.stop>
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                Proposal
                            </p>
                            <p class="font-display text-lg font-bold text-slate-900">
                                {{ quest.title }}
                            </p>
                        </div>
                        <button type="button" class="rounded-full p-2 text-slate-500 hover:bg-slate-100" aria-label="Close" @click="offerOpen = false">
                            ✕
                        </button>
                    </div>
                    <form class="mt-6 space-y-4" @submit.prevent="submitOffer">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Pitch</label>
                            <textarea
                                v-model="offerForm.pitch"
                                required
                                rows="5"
                                class="mt-1 w-full rounded-xl border-slate-200 text-sm font-medium shadow-sm focus:border-primary-500 focus:ring-primary-500"
                            />
                            <InputError class="mt-1" :message="offerForm.errors.pitch" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Quote (₦, optional)</label>
                            <input
                                v-model.number="offerForm.quoted_ngn"
                                type="number"
                                min="0"
                                step="1"
                                class="mt-1 w-full rounded-xl border-slate-200 text-sm font-medium shadow-sm"
                            />
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
                                Submit
                            </button>
                            <button type="button" class="rounded-full border border-slate-200 px-6 py-2.5 text-sm font-bold text-slate-800" @click="offerOpen = false">
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
import QuestFileGallery from '@/Components/Quests/QuestFileGallery.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import AppShell from '@/Layouts/AppShell.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    quest: { type: Object, required: true },
    can_edit: { type: Boolean, default: false },
    can_offer: { type: Boolean, default: false },
    workspace: { type: Object, required: true },
    my_offer: { type: Object, default: null },
    is_bookmarked: { type: Boolean, default: false },
    similar_quests: { type: Array, default: () => [] },
    top_freelancers: { type: Array, default: () => [] },
    start_timing_options: { type: Array, default: () => [] },
    form_options: { type: Object, default: null },
});

const page = usePage();
const isFreelancer = computed(() => page.props.auth?.user?.role?.slug === 'freelancer');

const offerOpen = ref(false);
const inviteQuery = ref('');
const inviteHits = ref([]);
const inviteIds = ref([]);
const inviteLabels = ref({});
let inviteTimer = null;

const uploadForm = useForm({ file: null });

const editForm = useForm({
    title: props.quest.title,
    description: props.quest.description,
    quest_category_id: props.quest.quest_category_id,
    state_id: props.quest.state_id,
    local_government_id: props.quest.local_government_id,
    city: props.quest.city,
    budget_amount_minor: props.quest.budget_minor,
    start_timing: props.quest.start_timing,
    scheduled_start_date: props.quest.scheduled_start_date || '',
    estimated_completion_days: props.quest.estimated_completion_days,
    site_visits_allowed: !!props.quest.site_visits_allowed,
});

const editParentId = ref(findParentForLeaf(props.quest.quest_category_id));
const editBudgetNgn = ref(Math.round(props.quest.budget_minor / 100));

watch(
    () => props.quest,
    (q) => {
        inviteIds.value = (q.invited || []).map((u) => u.id);
        inviteLabels.value = Object.fromEntries((q.invited || []).map((u) => [u.id, u.name]));
        editForm.title = q.title;
        editForm.description = q.description;
        editForm.quest_category_id = q.quest_category_id;
        editForm.state_id = q.state_id;
        editForm.local_government_id = q.local_government_id;
        editForm.city = q.city;
        editForm.budget_amount_minor = q.budget_minor;
        editForm.start_timing = q.start_timing;
        editForm.scheduled_start_date = q.scheduled_start_date || '';
        editForm.estimated_completion_days = q.estimated_completion_days;
        editForm.site_visits_allowed = !!q.site_visits_allowed;
        editParentId.value = findParentForLeaf(q.quest_category_id);
        editBudgetNgn.value = Math.round(q.budget_minor / 100);
    },
    { deep: true, immediate: true },
);

watch(editBudgetNgn, (n) => {
    editForm.budget_amount_minor = Math.max(10000, Math.round(Number(n) || 0) * 100);
});

const editLeafOptions = computed(() => {
    const p = props.form_options?.category_tree?.find((c) => c.id === editParentId.value);

    return p?.children ?? [];
});

const editLgas = computed(() => {
    const s = props.form_options?.locations?.find((x) => x.id === editForm.state_id);

    return s?.local_governments ?? [];
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

const offerForm = useForm({
    pitch: '',
    quoted_ngn: null,
});

watch(inviteQuery, (q) => {
    window.clearTimeout(inviteTimer);
    if (!props.can_edit || !q || q.trim().length < 2) {
        inviteHits.value = [];

        return;
    }
    inviteTimer = window.setTimeout(async () => {
        try {
            const { data } = await axios.get(route('quests.freelancers.search', props.quest.uuid), { params: { q: q.trim() } });
            inviteHits.value = (data.users || []).filter((u) => !inviteIds.value.includes(u.id));
        } catch {
            inviteHits.value = [];
        }
    }, 280);
});

function findParentForLeaf(leafId) {
    for (const p of props.form_options?.category_tree || []) {
        if ((p.children || []).some((c) => c.id === leafId)) {
            return p.id;
        }
    }

    const first = props.form_options?.category_tree?.[0];

    return first?.id ?? 0;
}

function onEditParent() {
    const first = editLeafOptions.value[0];
    editForm.quest_category_id = first?.id ?? editForm.quest_category_id;
}

function initials(name) {
    const p = (name || 'H').trim().split(/\s+/);

    return ((p[0]?.[0] || 'H') + (p[1]?.[0] || '')).toUpperCase();
}

function statusPill(s) {
    if (s === 'open') {
        return 'bg-emerald-50 text-emerald-800 ring-emerald-200';
    }
    if (s === 'draft') {
        return 'bg-amber-50 text-amber-900 ring-amber-200';
    }

    return 'bg-slate-100 text-slate-700 ring-slate-200';
}

function timingLabel(v) {
    const o = props.start_timing_options.find((x) => x.value === v);

    return o?.label || v;
}

function visibilityLabel(v) {
    const map = {
        public: 'Public listing',
        invite_only: 'Invite-only',
        private: 'Private',
    };

    return map[v] || v;
}

function toggleBookmark() {
    if (props.is_bookmarked) {
        router.delete(route('quests.bookmark.destroy', props.quest.uuid), { preserveScroll: true });
    } else {
        router.post(route('quests.bookmark.store', props.quest.uuid), {}, { preserveScroll: true });
    }
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

function removeFile(id) {
    if (!window.confirm('Remove this file?')) {
        return;
    }
    router.delete(route('quests.files.destroy', [props.quest.uuid, id]), { preserveScroll: true });
}

function uploadFile(e) {
    const f = e.target.files?.[0];
    e.target.value = '';
    if (!f) {
        return;
    }
    uploadForm.file = f;
    uploadForm.post(route('quests.files.store', props.quest.uuid), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => uploadForm.reset('file'),
    });
}

function submitEdit() {
    editForm.transform((data) => ({
        ...data,
        site_visits_allowed: !!data.site_visits_allowed,
    })).patch(route('quests.update', props.quest.uuid), { preserveScroll: true });
}

function inviteLabel(id) {
    return inviteLabels.value[id] || `#${id}`;
}

function addInvite(u) {
    if (inviteIds.value.includes(u.id)) {
        return;
    }
    inviteIds.value = [...inviteIds.value, u.id];
    inviteLabels.value = { ...inviteLabels.value, [u.id]: u.name };
    inviteHits.value = inviteHits.value.filter((x) => x.id !== u.id);
    inviteQuery.value = '';
}

function removeInvite(id) {
    inviteIds.value = inviteIds.value.filter((x) => x !== id);
}

function syncInvites() {
    router.post(
        route('quests.invites.store', props.quest.uuid),
        { freelancer_ids: inviteIds.value },
        { preserveScroll: true },
    );
}

function confirmDelete() {
    if (!window.confirm('Permanently delete this quest and all files?')) {
        return;
    }
    router.delete(route('quests.destroy', props.quest.uuid));
}

function openOffer() {
    offerOpen.value = true;
    offerForm.clearErrors();
    offerForm.reset();
}

function submitOffer() {
    offerForm
        .transform((data) => ({
            pitch: data.pitch,
            quoted_amount_minor:
                data.quoted_ngn !== null && data.quoted_ngn !== '' && !Number.isNaN(Number(data.quoted_ngn))
                    ? Math.round(Number(data.quoted_ngn) * 100)
                    : null,
        }))
        .post(route('quests.offers.store', props.quest.uuid), {
            preserveScroll: true,
            onSuccess: () => {
                offerOpen.value = false;
            },
        });
}
</script>
