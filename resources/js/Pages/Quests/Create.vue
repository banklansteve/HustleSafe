<template>
    <AppShell>
        <Head title="Create quest" />

        <div class="mx-auto w-full max-w-3xl">
            <div class="rounded-[2rem] bg-gradient-to-br from-primary-800 via-slate-900 to-slate-950 px-6 py-10 text-white shadow-xl ring-1 ring-white/10 sm:px-10">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.25em] text-teal-200/90">
                            Guided quest studio
                        </p>
                        <h1 class="font-display mt-3 text-3xl font-black tracking-tight sm:text-4xl">
                            Create a quest
                        </h1>
                        <p class="mt-3 max-w-xl text-sm font-semibold leading-relaxed text-teal-50/95">
                            Seven quick steps plus a review screen — nothing is saved until you submit. Your progress is kept in this browser until then.
                        </p>
                    </div>
                    <Link
                        :href="route('quests.index')"
                        class="inline-flex items-center rounded-full border border-white/25 bg-white/10 px-5 py-2.5 text-sm font-bold text-white backdrop-blur-sm transition hover:bg-white/20"
                    >
                        My quests
                    </Link>
                </div>
            </div>

            <div class="mt-8 flex flex-wrap gap-2">
                <button
                    v-for="(s, i) in stepTitles"
                    :key="s"
                    type="button"
                    class="rounded-full px-3 py-1.5 text-xs font-black uppercase tracking-wide transition"
                    :class="stepPillClass(i + 1)"
                    :disabled="i + 1 === 7 && maxReachedStep < 6"
                    @click="goToStep(i + 1)"
                >
                    {{ i + 1 }}. {{ s }}
                </button>
            </div>

            <form class="mt-8" @submit.prevent="submit">
                <Transition name="fade-slide" mode="out-in">
                    <div :key="step" class="rounded-2xl border border-slate-100 bg-white p-6 shadow-lg shadow-slate-900/5 ring-1 ring-slate-100 sm:p-8">
                        <!-- Step 1 -->
                        <section v-if="step === 1">
                            <h2 class="font-display text-xl font-bold text-slate-900">
                                Category & story
                            </h2>
                            <p class="mt-1 text-sm text-slate-500">
                                Pick the closest subcategory — we tune follow-up questions from here.
                            </p>
                            <div class="mt-6 grid gap-5 sm:grid-cols-2">
                                <div>
                                    <div class="flex items-center gap-1">
                                        <InputLabel for="parent_cat" value="Domain" />
                                        <FieldHint :text="hintDomain" />
                                    </div>
                                    <UiSelect
                                        id="parent_cat"
                                        v-model="parentCategoryId"
                                        class="mt-2"
                                        :options="parentSelectOptions"
                                        placeholder="Select domain"
                                    />
                                </div>
                                <div>
                                    <div class="flex items-center gap-1">
                                        <InputLabel for="quest_category_id" value="Subcategory" />
                                        <FieldHint text="Leaf categories power matching and which optional fields appear." />
                                    </div>
                                    <UiSelect
                                        id="quest_category_id"
                                        v-model="form.quest_category_id"
                                        class="mt-2"
                                        :options="leafSelectOptions"
                                        :placeholder="leafOptions.length ? 'Choose subcategory' : 'Pick domain first'"
                                        :disabled="!leafOptions.length"
                                        :invalid="!!form.errors.quest_category_id"
                                    />
                                    <InputError class="mt-2" :message="form.errors.quest_category_id" />
                                </div>
                            </div>
                            <div class="mt-6 space-y-4">
                                <div>
                                    <InputLabel for="title" value="Title" />
                                    <TextInput id="title" v-model="form.title" type="text" class="mt-2 w-full rounded-xl border-slate-200 font-semibold shadow-sm" required />
                                    <InputError class="mt-2" :message="form.errors.title" />
                                </div>
                                <div>
                                    <div class="flex items-center gap-1">
                                        <InputLabel for="description" value="Description" />
                                        <FieldHint
                                            text="The more context you share, the better proposals you will get. Aim for enough detail that a freelancer could start without guessing."
                                        />
                                    </div>
                                    <div
                                        class="mt-3 rounded-2xl border border-primary-100/90 bg-gradient-to-br from-primary-50/95 via-white to-teal-50/50 p-4 shadow-sm ring-1 ring-primary-100/60"
                                        role="note"
                                    >
                                        <div class="flex gap-3">
                                            <InformationCircleIcon class="mt-0.5 h-5 w-5 shrink-0 text-primary-600" aria-hidden="true" />
                                            <div class="min-w-0">
                                                <p class="text-sm font-bold text-slate-900">Give freelancers enough to go on</p>
                                                <ul class="mt-2 list-inside list-disc space-y-1.5 text-xs font-semibold leading-relaxed text-slate-600">
                                                    <li>What outcome you need and how you will measure success</li>
                                                    <li>Deliverables, formats, brand or technical constraints</li>
                                                    <li>Timeline expectations, meetings, and collaboration style</li>
                                                    <li>Links, references, or files you will attach on the last step</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    <UiTextarea
                                        id="description"
                                        v-model="form.description"
                                        class="mt-3"
                                        placeholder="Describe the quest in detail…"
                                        :min-rows="2"
                                        :max-rows="22"
                                        :invalid="!!form.errors.description"
                                        required
                                    />
                                    <InputError class="mt-2" :message="form.errors.description" />
                                </div>
                            </div>
                        </section>

                        <!-- Step 2 -->
                        <section v-else-if="step === 2">
                            <h2 class="font-display text-xl font-bold text-slate-900">
                                Visibility & targeting
                            </h2>
                            <p class="mt-1 text-sm text-slate-500">
                                Control who discovers this quest and how freelancers should think about location.
                            </p>
                            <div class="mt-6 space-y-5">
                                <div>
                                    <div class="flex items-center gap-1">
                                        <InputLabel value="Job visibility" />
                                        <FieldHint :text="hintVisibility" learn-more-url="/dashboard/guides/trust" />
                                    </div>
                                    <UiSelect v-model="form.visibility" class="mt-2" :options="visibilityOptions" />
                                    <InputError class="mt-2" :message="form.errors.visibility" />
                                </div>
                                <div>
                                    <div class="flex items-center gap-1">
                                        <InputLabel value="Preferred freelancer location" />
                                        <FieldHint text="Remote-friendly welcomes nationwide talent; local-only signals on-site expectations." />
                                    </div>
                                    <UiSelect v-model="form.freelancer_location_pref" class="mt-2" :options="locationPrefOptions" />
                                    <InputError class="mt-2" :message="form.errors.freelancer_location_pref" />
                                </div>
                                <div v-if="fieldProfile.show_availability">
                                    <div class="flex items-center gap-1">
                                        <InputLabel value="Availability expectation" />
                                        <FieldHint text="Helps freelancers gauge capacity for your cadence." />
                                    </div>
                                    <UiSelect v-model="form.availability_need" class="mt-2" :options="availabilityOptions" placeholder="Select availability" />
                                    <InputError class="mt-2" :message="form.errors.availability_need" />
                                </div>
                                <div>
                                    <InputLabel for="traffic_source" value="Traffic source (optional)" />
                                    <TextInput id="traffic_source" v-model="form.traffic_source" type="text" class="mt-2 w-full rounded-xl border-slate-200 shadow-sm" placeholder="e.g. instagram, newsletter" />
                                </div>
                                <div class="grid gap-3 sm:grid-cols-3">
                                    <div>
                                        <InputLabel for="utm_s" value="utm_source" />
                                        <TextInput id="utm_s" v-model="utm.utm_source" type="text" class="mt-2 w-full rounded-xl border-slate-200 text-sm shadow-sm" />
                                    </div>
                                    <div>
                                        <InputLabel for="utm_m" value="utm_medium" />
                                        <TextInput id="utm_m" v-model="utm.utm_medium" type="text" class="mt-2 w-full rounded-xl border-slate-200 text-sm shadow-sm" />
                                    </div>
                                    <div>
                                        <InputLabel for="utm_c" value="utm_campaign" />
                                        <TextInput id="utm_c" v-model="utm.utm_campaign" type="text" class="mt-2 w-full rounded-xl border-slate-200 text-sm shadow-sm" />
                                    </div>
                                </div>
                                <p v-if="form.visibility === 'invite_only'" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-semibold text-amber-950">
                                    Invite-only quests need at least one tagged freelancer on the final step before publishing.
                                </p>
                            </div>
                        </section>

                        <!-- Step 3 -->
                        <section v-else-if="step === 3">
                            <h2 class="font-display text-xl font-bold text-slate-900">
                                Location
                            </h2>
                            <div class="mt-6 grid gap-5 sm:grid-cols-2">
                                <div>
                                    <InputLabel for="state_id" value="State" />
                                    <UiSelect
                                        id="state_id"
                                        v-model="form.state_id"
                                        class="mt-2"
                                        :options="stateSelectOptions"
                                        placeholder="Select state"
                                        :invalid="!!form.errors.state_id"
                                    />
                                    <InputError class="mt-2" :message="form.errors.state_id" />
                                </div>
                                <div>
                                    <InputLabel for="local_government_id" value="LGA" />
                                    <UiSelect
                                        id="local_government_id"
                                        v-model="form.local_government_id"
                                        class="mt-2"
                                        :options="lgaSelectOptions"
                                        :placeholder="form.state_id ? 'Select LGA' : 'State first'"
                                        :disabled="!form.state_id"
                                        :invalid="!!form.errors.local_government_id"
                                    />
                                    <InputError class="mt-2" :message="form.errors.local_government_id" />
                                </div>
                                <div class="sm:col-span-2">
                                    <InputLabel for="city" value="City / area" />
                                    <TextInput id="city" v-model="form.city" type="text" class="mt-2 w-full rounded-xl border-slate-200 shadow-sm" required />
                                    <InputError class="mt-2" :message="form.errors.city" />
                                </div>
                            </div>
                        </section>

                        <!-- Step 4 -->
                        <section v-else-if="step === 4">
                            <h2 class="font-display text-xl font-bold text-slate-900">
                                Schedule & budget
                            </h2>
                            <div class="mt-6 space-y-5">
                                <div>
                                    <div class="flex items-center gap-1">
                                        <InputLabel value="When work should start" />
                                        <FieldHint text="Window shopping is for early planning without a firm kickoff date." />
                                    </div>
                                    <UiSelect v-model="form.start_timing" class="mt-2" :options="startTimingOptionsUi" />
                                    <InputError class="mt-2" :message="form.errors.start_timing" />
                                </div>
                                <div v-if="form.start_timing === 'scheduled'">
                                    <InputLabel for="sched" value="Start date" />
                                    <TextInput id="sched" v-model="form.scheduled_start_date" type="date" class="mt-2 w-full rounded-xl border-slate-200 shadow-sm" />
                                    <InputError class="mt-2" :message="form.errors.scheduled_start_date" />
                                </div>
                                <div>
                                    <InputLabel for="ecd" value="Estimated completion (days)" />
                                    <UiSelect
                                        id="ecd"
                                        v-model="form.estimated_completion_days"
                                        class="mt-2"
                                        :options="completionDayOptions"
                                        placeholder="Select duration"
                                    />
                                </div>
                                <div>
                                    <div class="flex items-center gap-1">
                                        <InputLabel for="edd" value="Target delivery date (optional)" />
                                        <FieldHint text="Aim date for the full deliverable — helps freelancers schedule." />
                                    </div>
                                    <TextInput id="edd" v-model="form.estimated_delivery_date" type="date" class="mt-2 w-full rounded-xl border-slate-200 shadow-sm" />
                                    <InputError class="mt-2" :message="form.errors.estimated_delivery_date" />
                                </div>
                                <div>
                                    <div class="flex items-end justify-between gap-3">
                                        <InputLabel for="budget" value="Budget ceiling" />
                                        <p class="text-sm font-black text-primary-800">{{ formatNgn(form.budget_amount_minor) }}</p>
                                    </div>
                                    <p class="text-xs font-semibold text-slate-500">Maximum ₦1,000,000</p>
                                    <input id="budget" v-model.number="form.budget_amount_minor" type="range" min="10000" :max="maxBudgetMinor" step="50000" class="mt-3 h-2 w-full cursor-pointer accent-primary-600" />
                                    <InputError class="mt-2" :message="form.errors.budget_amount_minor" />
                                </div>
                            </div>
                        </section>

                        <!-- Step 5 -->
                        <section v-else-if="step === 5">
                            <h2 class="font-display text-xl font-bold text-slate-900">
                                Scope & requirements
                            </h2>
                            <div class="mt-6 space-y-5">
                                <div v-if="fieldProfile.show_site_visit">
                                    <div class="flex items-center gap-1">
                                        <InputLabel value="Site visits before proposals?" />
                                        <FieldHint text="Great for trades, property, or on-site assessments. Digital-only work usually skips this." />
                                    </div>
                                    <UiSelect v-model="siteVisitChoice" class="mt-2" :options="yesNoOptions" />
                                </div>
                                <div>
                                    <div class="flex items-center gap-1">
                                        <InputLabel value="Project type" />
                                        <FieldHint text="Hourly unlocks estimated hours below." />
                                    </div>
                                    <UiSelect v-model="form.project_type" class="mt-2" :options="projectTypeOptions" placeholder="Optional" />
                                </div>
                                <div v-if="fieldProfile.show_hourly_fields && form.project_type === 'hourly'">
                                    <InputLabel for="eh" value="Estimated hours" />
                                    <input id="eh" v-model.number="form.estimated_hours" type="number" min="1" max="2000" class="mt-2 w-full rounded-xl border-slate-200 text-sm font-semibold shadow-sm" />
                                    <InputError class="mt-2" :message="form.errors.estimated_hours" />
                                </div>
                                <div v-if="fieldProfile.show_team_size">
                                    <InputLabel value="Freelancers needed" />
                                    <UiSelect v-model="form.team_size" class="mt-2" :options="teamSizeOptions" />
                                    <InputError class="mt-2" :message="form.errors.team_size" />
                                </div>
                            </div>
                        </section>

                        <!-- Step 6 -->
                        <section v-else-if="step === 6">
                            <h2 class="font-display text-xl font-bold text-slate-900">
                                Trust, discovery & launch
                            </h2>
                            <p class="mt-1 text-sm text-slate-500">
                                Tags, files, and listing options — you will choose publish vs draft on the review step.
                            </p>
                            <div class="mt-6 space-y-5">
                                <div>
                                    <InputLabel value="Listing promotion" />
                                    <UiSelect v-model="form.promotion_tier" class="mt-2" :options="promotionOptions" />
                                </div>
                                <div>
                                    <div class="flex items-center gap-1">
                                        <InputLabel for="exp" value="Auto-close if unfilled (days, optional)" />
                                        <FieldHint text="We will mark the listing closed after this many days from publish." />
                                    </div>
                                    <input id="exp" v-model.number="form.auto_listing_expiry_days" type="number" min="1" max="90" class="mt-2 w-full rounded-xl border-slate-200 text-sm font-semibold shadow-sm" placeholder="Leave blank to keep open" />
                                </div>
                                <div>
                                    <InputLabel for="maxo" value="Max proposals (optional)" />
                                    <input id="maxo" v-model.number="form.max_offers" type="number" min="1" max="200" class="mt-2 w-full rounded-xl border-slate-200 text-sm font-semibold shadow-sm" />
                                </div>
                                <div>
                                    <div class="flex items-center gap-1">
                                        <InputLabel for="slug" value="Public slug (optional)" />
                                        <FieldHint text="SEO-friendly path segment. Leave blank and we generate one from your title." />
                                    </div>
                                    <TextInput id="slug" v-model="form.slug" type="text" class="mt-2 w-full rounded-xl border-slate-200 font-mono text-sm shadow-sm" placeholder="e.g. brand-refresh-lagos" />
                                    <InputError class="mt-2" :message="form.errors.slug" />
                                </div>
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                        Tag freelancers
                                    </p>
                                    <TextInput v-model="tagQuery" type="search" class="mt-2 w-full rounded-xl border-slate-200 text-sm shadow-sm" placeholder="Search by name…" />
                                    <ul v-if="tagResults.length" class="mt-2 max-h-40 overflow-auto rounded-xl border border-slate-100 bg-white shadow-md">
                                        <li v-for="u in tagResults" :key="u.id">
                                            <button type="button" class="flex w-full px-3 py-2 text-left text-sm font-semibold hover:bg-primary-50" @click="addTag(u)">{{ u.name }}</button>
                                        </li>
                                    </ul>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <span v-for="t in taggedDisplay" :key="t.id" class="inline-flex items-center gap-2 rounded-full bg-primary-50 px-3 py-1 text-xs font-bold text-primary-950 ring-1 ring-primary-100">
                                            {{ t.label }}
                                            <button type="button" class="text-primary-700" @click="removeTag(t.id)">✕</button>
                                        </span>
                                    </div>
                                </div>
                                <div>
                                    <InputLabel value="Reference files" />
                                    <div
                                        class="mt-2 flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed px-4 py-8 text-center transition"
                                        :class="dragOver ? 'border-primary-400 bg-primary-50/80' : 'border-slate-200 bg-slate-50/80 hover:border-primary-300'"
                                        role="button"
                                        tabindex="0"
                                        @click="fileInput?.click()"
                                        @keydown.enter.prevent="fileInput?.click()"
                                        @dragover.prevent="dragOver = true"
                                        @dragleave.prevent="dragOver = false"
                                        @drop.prevent="onDrop"
                                    >
                                        <p class="text-sm font-bold text-slate-800">Drop files or tap to browse</p>
                                        <input ref="fileInput" type="file" class="hidden" multiple accept=".jpg,.jpeg,.png,.webp,.gif,.pdf" @change="onFileInput" />
                                    </div>
                                    <InputError class="mt-2" :message="form.errors.files" />
                                </div>
                            </div>
                        </section>

                        <!-- Step 7: Review -->
                        <section v-else class="space-y-6">
                            <div>
                                <h2 class="font-display text-xl font-bold text-slate-900">
                                    Review & submit
                                </h2>
                                <p class="mt-1 text-sm text-slate-500">
                                    Nothing is saved to the server until you submit. Edit any section below, or use Back to adjust step-by-step.
                                </p>
                                <p class="mt-3 rounded-xl border border-amber-100 bg-amber-50/80 px-4 py-2 text-xs font-semibold text-amber-950 ring-1 ring-amber-100/80">
                                    Drafts in this browser do not include attached files. If you refreshed the page, re-add references before publishing.
                                </p>
                            </div>

                            <div class="space-y-3">
                                <article
                                    v-for="block in previewBlocks"
                                    :key="block.id"
                                    class="rounded-2xl border border-slate-100 bg-slate-50/40 p-4 ring-1 ring-slate-100/80"
                                >
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <h3 class="text-sm font-black uppercase tracking-wide text-slate-500">
                                            {{ block.title }}
                                        </h3>
                                        <button
                                            type="button"
                                            class="rounded-full border border-primary-200 bg-white px-3 py-1 text-xs font-bold text-primary-800 shadow-sm transition hover:bg-primary-50"
                                            @click="goToStep(block.id)"
                                        >
                                            Edit section
                                        </button>
                                    </div>
                                    <dl class="mt-3 space-y-2 text-sm">
                                        <div v-for="row in block.rows" :key="row.label" class="flex flex-wrap gap-x-2 gap-y-1">
                                            <dt class="min-w-[7rem] font-bold text-slate-500">{{ row.label }}</dt>
                                            <dd class="min-w-0 flex-1 font-semibold text-slate-900">{{ row.value }}</dd>
                                        </div>
                                    </dl>
                                </article>
                            </div>

                            <label class="flex items-start gap-3 rounded-2xl border border-emerald-100 bg-emerald-50/70 p-4 ring-1 ring-emerald-100/80">
                                <input v-model="form.publish_now" type="checkbox" class="mt-1 rounded border-slate-300 text-emerald-600" />
                                <span class="text-sm font-bold text-emerald-950">Publish immediately (otherwise save as draft)</span>
                            </label>
                        </section>
                    </div>
                </Transition>

                <div class="mt-8 flex flex-wrap items-center justify-between gap-4">
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-if="fromPreview"
                            type="button"
                            class="rounded-full border border-primary-200 bg-primary-50 px-6 py-2.5 text-sm font-bold text-primary-900 shadow-sm hover:bg-primary-100"
                            @click="exitEditToPreview"
                        >
                            Back to review
                        </button>
                        <button
                            v-else-if="step > 1"
                            type="button"
                            class="rounded-full border border-slate-200 bg-white px-6 py-2.5 text-sm font-bold text-slate-800 shadow-sm hover:bg-slate-50"
                            @click="prev"
                        >
                            Back
                        </button>
                    </div>
                    <div class="flex flex-wrap items-center justify-end gap-3">
                        <button
                            v-if="step < 7"
                            type="button"
                            class="rounded-full bg-gradient-to-r from-primary-600 to-teal-600 px-8 py-3 text-sm font-black text-white shadow-lg"
                            @click="next"
                        >
                            {{ continueLabel }}
                        </button>
                        <button
                            v-if="step === 7"
                            type="submit"
                            class="rounded-full bg-slate-900 px-8 py-3 text-sm font-black text-white shadow-lg disabled:opacity-50"
                            :disabled="form.processing"
                        >
                            {{ form.processing ? 'Saving…' : 'Submit quest' }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </AppShell>
</template>

<script setup>
import FieldHint from '@/Components/Ui/FieldHint.vue';
import UiSelect from '@/Components/Ui/UiSelect.vue';
import UiTextarea from '@/Components/Ui/UiTextarea.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import AppShell from '@/Layouts/AppShell.vue';
import { InformationCircleIcon } from '@heroicons/vue/24/outline';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, reactive, ref, watch } from 'vue';
import { useQuestCreateDraft } from '@/composables/useQuestCreateDraft';
import { validateQuestCreateStep } from '@/utils/questCreateClientValidation';

const props = defineProps({
    locations: { type: Array, required: true },
    categoryTree: { type: Array, required: true },
    startTimingOptions: { type: Array, required: true },
    maxBudgetMinor: { type: Number, default: 100_000_000 },
    fieldProfileUrl: { type: String, required: true },
});

const page = usePage();
const draftStorageKey = computed(() => `hustlesafe:quest-create:v1:${page.props.auth?.user?.id ?? 'guest'}`);

const stepTitles = ['Story', 'Audience', 'Location', 'Schedule', 'Scope', 'Launch', 'Review'];
const step = ref(1);
const maxReachedStep = ref(1);
const fromPreview = ref(false);
const dragOver = ref(false);
const fileInput = ref(null);
const parentCategoryId = ref(0);
const fieldProfile = reactive({
    show_site_visit: false,
    show_availability: true,
    show_hourly_fields: true,
    show_team_size: true,
    default_site_visits: false,
});
const tagQuery = ref('');
const tagResults = ref([]);
const tagLabelById = ref({});
let tagTimer = null;
const utm = reactive({ utm_source: '', utm_medium: '', utm_campaign: '' });

const completionPresets = [3, 5, 7, 10, 14, 21, 30, 45, 60, 90];

const visibilityOptions = [
    { value: 'public', label: 'Public — discoverable in Explore' },
    { value: 'invite_only', label: 'Invite-only — tagged + followers only' },
    { value: 'private', label: 'Private — only you (no marketplace offers)' },
];
const locationPrefOptions = [
    { value: 'remote_friendly', label: 'Remote-friendly' },
    { value: 'local_only', label: 'Local only' },
];
const availabilityOptions = [
    { value: 'full_time', label: 'Full-time cadence' },
    { value: 'part_time', label: 'Part-time' },
    { value: 'as_needed', label: 'As-needed / flexible' },
];
const projectTypeOptions = [
    { value: 'fixed_price', label: 'Fixed price' },
    { value: 'hourly', label: 'Hourly rate' },
];
const teamSizeOptions = [
    { value: 'solo', label: 'Solo freelancer' },
    { value: 'small_team', label: 'Small team (2–5)' },
];
const promotionOptions = [
    { value: 'standard', label: 'Standard listing' },
    { value: 'featured', label: 'Featured / boost (paid promotion)' },
];
const yesNoOptions = [
    { value: 'yes', label: 'Yes, visits may be needed' },
    { value: 'no', label: 'No site visits' },
];

const siteVisitChoice = ref('no');

const form = useForm({
    quest_category_id: 0,
    title: '',
    description: '',
    visibility: 'public',
    freelancer_location_pref: 'remote_friendly',
    availability_need: 'as_needed',
    state_id: 0,
    local_government_id: 0,
    city: '',
    start_timing: 'flexible',
    scheduled_start_date: '',
    estimated_completion_days: 14,
    estimated_delivery_date: '',
    budget_amount_minor: 2_000_000,
    project_type: 'fixed_price',
    estimated_hours: null,
    team_size: 'solo',
    site_visits_allowed: false,
    promotion_tier: 'standard',
    auto_listing_expiry_days: null,
    max_offers: null,
    slug: '',
    traffic_source: '',
    publish_now: true,
    tagged_freelancer_ids: [],
    files: [],
});

const startTimingOptionsUi = computed(() => props.startTimingOptions.map((o) => ({ value: o.value, label: o.label })));

const leafOptions = computed(() => {
    const p = props.categoryTree.find((c) => c.id === parentCategoryId.value);

    return p?.children ?? [];
});

const lgaOptions = computed(() => {
    const s = props.locations.find((x) => x.id === form.state_id);

    return s?.local_governments ?? [];
});

const parentSelectOptions = computed(() => props.categoryTree.map((p) => ({ value: p.id, label: p.name })));

const leafSelectOptions = computed(() => leafOptions.value.map((c) => ({ value: c.id, label: c.name })));

const stateSelectOptions = computed(() => props.locations.map((s) => ({ value: s.id, label: s.name })));

const lgaSelectOptions = computed(() => lgaOptions.value.map((lg) => ({ value: lg.id, label: lg.name })));

const completionDayOptions = computed(() => completionPresets.map((d) => ({ value: d, label: `${d} days` })));

const taggedDisplay = computed(() => form.tagged_freelancer_ids.map((id) => ({ id, label: tagLabelById.value[id] || `#${id}` })));

watch(step, (s) => {
    maxReachedStep.value = Math.max(maxReachedStep.value, s);
});

const continueLabel = computed(() => {
    if (fromPreview.value) {
        return 'Save & return to review';
    }
    if (step.value === 6) {
        return 'Continue to review';
    }

    return 'Continue';
});

function stepPillClass(i) {
    const active = i === step.value;
    const done = i < step.value;
    const locked = i === 7 && maxReachedStep.value < 6;

    if (active) {
        return 'bg-primary-600 text-white shadow-md';
    }
    if (done) {
        return 'bg-emerald-100 text-emerald-900 ring-1 ring-emerald-200';
    }
    if (locked) {
        return 'cursor-not-allowed bg-slate-100 text-slate-400 ring-1 ring-slate-200 opacity-60';
    }

    return 'bg-slate-100 text-slate-500 ring-1 ring-slate-200 hover:bg-slate-200';
}

function formatNgn(minor) {
    return `₦${(Number(minor) / 100).toLocaleString('en-NG', { maximumFractionDigits: 0 })}`;
}

function optionLabel(options, value) {
    const m = options.find((o) => String(o.value) === String(value));

    return m?.label ?? String(value ?? '—');
}

function leafCategoryName() {
    for (const p of props.categoryTree) {
        const c = (p.children || []).find((x) => Number(x.id) === Number(form.quest_category_id));
        if (c) {
            return c.name;
        }
    }

    return '—';
}

function parentDomainName() {
    const p = props.categoryTree.find((x) => x.id === parentCategoryId.value);

    return p?.name ?? '—';
}

function stateName() {
    return props.locations.find((s) => Number(s.id) === Number(form.state_id))?.name ?? '—';
}

function lgaName() {
    return lgaOptions.value.find((lg) => Number(lg.id) === Number(form.local_government_id))?.name ?? '—';
}

const previewBlocks = computed(() => {
    const timing = optionLabel(startTimingOptionsUi.value, form.start_timing);

    return [
        {
            id: 1,
            title: 'Story',
            rows: [
                { label: 'Domain', value: parentDomainName() },
                { label: 'Subcategory', value: leafCategoryName() },
                { label: 'Title', value: String(form.title || '').trim() || '—' },
                { label: 'Description', value: String(form.description || '').trim() ? `${String(form.description).slice(0, 220)}${String(form.description).length > 220 ? '…' : ''}` : '—' },
            ],
        },
        {
            id: 2,
            title: 'Audience',
            rows: [
                { label: 'Visibility', value: optionLabel(visibilityOptions, form.visibility) },
                { label: 'Location pref', value: optionLabel(locationPrefOptions, form.freelancer_location_pref) },
                ...(fieldProfile.show_availability
                    ? [{ label: 'Availability', value: optionLabel(availabilityOptions, form.availability_need) }]
                    : []),
                { label: 'Traffic source', value: String(form.traffic_source || '').trim() || '—' },
                { label: 'UTM', value: [utm.utm_source, utm.utm_medium, utm.utm_campaign].filter(Boolean).join(' · ') || '—' },
            ],
        },
        {
            id: 3,
            title: 'Location',
            rows: [
                { label: 'State', value: stateName() },
                { label: 'LGA', value: lgaName() },
                { label: 'City', value: String(form.city || '').trim() || '—' },
            ],
        },
        {
            id: 4,
            title: 'Schedule & budget',
            rows: [
                { label: 'Start', value: timing },
                ...(form.start_timing === 'scheduled'
                    ? [{ label: 'Start date', value: String(form.scheduled_start_date || '').trim() || '—' }]
                    : []),
                { label: 'Completion', value: `${form.estimated_completion_days} days` },
                { label: 'Delivery target', value: String(form.estimated_delivery_date || '').trim() || '—' },
                { label: 'Budget cap', value: formatNgn(form.budget_amount_minor) },
            ],
        },
        {
            id: 5,
            title: 'Scope',
            rows: [
                ...(fieldProfile.show_site_visit
                    ? [{ label: 'Site visits', value: form.site_visits_allowed ? 'Allowed / expected' : 'Not expected' }]
                    : []),
                { label: 'Project type', value: optionLabel(projectTypeOptions, form.project_type) },
                ...(fieldProfile.show_hourly_fields && form.project_type === 'hourly'
                    ? [{ label: 'Est. hours', value: String(form.estimated_hours ?? '—') }]
                    : []),
                ...(fieldProfile.show_team_size ? [{ label: 'Team', value: optionLabel(teamSizeOptions, form.team_size) }] : []),
            ],
        },
        {
            id: 6,
            title: 'Launch',
            rows: [
                { label: 'Promotion', value: optionLabel(promotionOptions, form.promotion_tier) },
                { label: 'Auto-expiry (days)', value: form.auto_listing_expiry_days != null && form.auto_listing_expiry_days !== '' ? String(form.auto_listing_expiry_days) : '—' },
                { label: 'Max offers', value: form.max_offers != null && form.max_offers !== '' ? String(form.max_offers) : '—' },
                { label: 'Slug', value: String(form.slug || '').trim() || 'Auto from title' },
                { label: 'Tagged freelancers', value: taggedDisplay.value.map((t) => t.label).join(', ') || '—' },
                { label: 'Files', value: form.files?.length ? `${form.files.length} attached` : 'None' },
            ],
        },
    ];
});

const hintDomain = 'Choose the broad industry so we can unlock the right optional questions.';
const hintVisibility = 'Public quests appear in Explore. Invite-only limits discovery to people you tag plus followers. Private hides the listing from others entirely.';

function validationDeps() {
    return {
        form,
        fieldProfile,
        categoryTree: props.categoryTree,
        locations: props.locations,
        maxBudgetMinor: props.maxBudgetMinor,
    };
}

function applyClientErrors(errors) {
    form.clearErrors();
    for (const [k, v] of Object.entries(errors)) {
        form.setError(k, v);
    }
}

const { clearDraft } = useQuestCreateDraft(
    draftStorageKey,
    () => ({
        step: step.value,
        parentCategoryId: parentCategoryId.value,
        fieldProfile: { ...fieldProfile },
        siteVisitChoice: siteVisitChoice.value,
        utm: { ...utm },
        tagLabelById: { ...tagLabelById.value },
        form,
    }),
    (data) => {
        if (typeof data.step === 'number') {
            step.value = Math.min(7, Math.max(1, data.step));
        }
        if (data.parentCategoryId != null) {
            parentCategoryId.value = data.parentCategoryId;
        }
        if (data.fieldProfile && typeof data.fieldProfile === 'object') {
            Object.assign(fieldProfile, data.fieldProfile);
        }
        if (data.utm && typeof data.utm === 'object') {
            Object.assign(utm, data.utm);
        }
        if (data.siteVisitChoice === 'yes' || data.siteVisitChoice === 'no') {
            siteVisitChoice.value = data.siteVisitChoice;
        }
        if (data.tagLabelById && typeof data.tagLabelById === 'object') {
            tagLabelById.value = { ...data.tagLabelById };
        }
        if (data.form && typeof data.form === 'object') {
            for (const [key, val] of Object.entries(data.form)) {
                if (key === 'files') {
                    continue;
                }
                if (key in form) {
                    form[key] = val;
                }
            }
        }
        maxReachedStep.value = Math.max(maxReachedStep.value, step.value, 1);
    },
);

watch(siteVisitChoice, (v) => {
    form.site_visits_allowed = v === 'yes';
});

watch(parentCategoryId, () => {
    form.quest_category_id = 0;
});

watch(
    () => form.state_id,
    () => {
        form.local_government_id = 0;
    },
);

watch(
    () => form.quest_category_id,
    async (id) => {
        if (!id) {
            return;
        }
        try {
            const { data } = await axios.get(props.fieldProfileUrl, { params: { quest_category_id: id } });
            Object.assign(fieldProfile, data);
            if (!fieldProfile.show_site_visit) {
                siteVisitChoice.value = 'no';
                form.site_visits_allowed = false;
            } else if (fieldProfile.default_site_visits) {
                siteVisitChoice.value = 'yes';
                form.site_visits_allowed = true;
            }
            if (!fieldProfile.show_availability) {
                form.availability_need = null;
            } else if (!form.availability_need) {
                form.availability_need = 'as_needed';
            }
        } catch {
            /* ignore */
        }
    },
);

watch(tagQuery, (q) => {
    window.clearTimeout(tagTimer);
    if (!q || q.trim().length < 2) {
        tagResults.value = [];

        return;
    }
    tagTimer = window.setTimeout(async () => {
        try {
            const { data } = await axios.get(route('users.freelancers.search'), { params: { q: q.trim() } });
            tagResults.value = (data.users || []).filter((u) => !form.tagged_freelancer_ids.includes(u.id));
        } catch {
            tagResults.value = [];
        }
    }, 280);
});

function buildPayload() {
    const raw = { ...form.data() };
    delete raw.files;

    const traffic_utm = {};
    if (utm.utm_source) {
        traffic_utm.utm_source = utm.utm_source;
    }
    if (utm.utm_medium) {
        traffic_utm.utm_medium = utm.utm_medium;
    }
    if (utm.utm_campaign) {
        traffic_utm.utm_campaign = utm.utm_campaign;
    }

    return {
        ...raw,
        traffic_utm: Object.keys(traffic_utm).length ? traffic_utm : null,
        tagged_freelancer_ids: form.tagged_freelancer_ids,
    };
}

function next() {
    const deps = validationDeps();
    if (fromPreview.value) {
        for (let s = 1; s <= 6; s += 1) {
            const { ok, errors } = validateQuestCreateStep(s, deps);
            if (!ok) {
                applyClientErrors(errors);
                step.value = s;
                return;
            }
        }
        form.clearErrors();
        fromPreview.value = false;
        step.value = 7;
        return;
    }

    const { ok, errors } = validateQuestCreateStep(step.value, deps);
    if (!ok) {
        applyClientErrors(errors);
        return;
    }

    form.clearErrors();
    if (step.value === 6) {
        step.value = 7;
        return;
    }
    if (step.value < 6) {
        step.value += 1;
    }
}

function prev() {
    if (step.value === 7) {
        step.value = 6;
        fromPreview.value = false;
        return;
    }
    if (step.value > 1) {
        step.value -= 1;
    }
}

function goToStep(n) {
    if (n < 1 || n > 7) {
        return;
    }
    if (n === 7 && maxReachedStep.value < 6) {
        return;
    }
    const wasPreview = step.value === 7;
    step.value = n;
    fromPreview.value = wasPreview && n >= 1 && n < 7;
    form.clearErrors();
}

function exitEditToPreview() {
    fromPreview.value = false;
    step.value = 7;
    form.clearErrors();
}

function addTag(u) {
    if (form.tagged_freelancer_ids.length >= 20) {
        return;
    }
    form.tagged_freelancer_ids.push(u.id);
    tagLabelById.value = { ...tagLabelById.value, [u.id]: u.label || u.name };
    tagResults.value = [];
    tagQuery.value = '';
}

function removeTag(id) {
    form.tagged_freelancer_ids = form.tagged_freelancer_ids.filter((x) => x !== id);
}

function pushFiles(list) {
    for (const file of Array.from(list || [])) {
        if (form.files.length >= 10) {
            break;
        }
        form.files.push(file);
    }
}

function onFileInput(e) {
    pushFiles(e.target.files);
    e.target.value = '';
}

function onDrop(e) {
    dragOver.value = false;
    pushFiles(e.dataTransfer?.files);
}

function submit() {
    if (step.value !== 7) {
        return;
    }
    const deps = validationDeps();
    for (let s = 1; s <= 6; s += 1) {
        const { ok, errors } = validateQuestCreateStep(s, deps);
        if (!ok) {
            applyClientErrors(errors);
            step.value = s;
            return;
        }
    }

    if (form.visibility === 'invite_only' && form.tagged_freelancer_ids.length < 1) {
        form.clearErrors();
        form.setError('tagged_freelancer_ids', 'Add at least one freelancer for invite-only quests.');
        step.value = 6;

        return;
    }
    form
        .transform((data) => ({
            ...data,
            site_visits_allowed: !!data.site_visits_allowed,
            publish_now: !!data.publish_now,
            traffic_utm: buildPayload().traffic_utm,
            tagged_freelancer_ids: data.tagged_freelancer_ids.length ? data.tagged_freelancer_ids : [],
            availability_need: data.availability_need || null,
            project_type: data.project_type || null,
            estimated_hours: data.estimated_hours || null,
            team_size: data.team_size || null,
            auto_listing_expiry_days: data.auto_listing_expiry_days || null,
            max_offers: data.max_offers || null,
            slug: data.slug?.trim() || null,
            traffic_source: data.traffic_source?.trim() || null,
            estimated_delivery_date: data.estimated_delivery_date || null,
            scheduled_start_date: data.scheduled_start_date || null,
        }))
        .post(route('quests.store'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => clearDraft(),
        });
}
</script>

<style scoped>
.fade-slide-enter-active,
.fade-slide-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}
.fade-slide-enter-from {
    opacity: 0;
    transform: translateX(12px);
}
.fade-slide-leave-to {
    opacity: 0;
    transform: translateX(-12px);
}
</style>
