<template>
    <AppShell>
        <Head title="Find quests · HustleSafe" />

        <div class="rounded-[2rem] bg-gradient-to-br from-primary-800 via-slate-900 to-slate-950 px-6 py-10 text-white shadow-xl ring-1 ring-white/10 sm:px-10">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-2xl space-y-5">
                    <QuestDiscoveryTabs />
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.25em] text-teal-200/90">Marketplace</p>
                        <h1 class="font-display mt-3 text-3xl font-black tracking-tight sm:text-4xl">Browse quests</h1>
                        <p class="mt-4 text-base font-semibold leading-relaxed text-teal-50">
                            Search every open public quest with filters. We start with your profile — categories, state, and tier budget — then you can widen or clear anytime.
                        </p>
                    </div>
                </div>
                <p v-if="meta.total !== undefined" class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3 text-sm font-bold text-teal-50 backdrop-blur-sm">
                    {{ meta.total.toLocaleString('en-NG') }} quest{{ meta.total === 1 ? '' : 's' }}
                </p>
            </div>
        </div>

        <div class="mt-8 grid gap-6 lg:grid-cols-[18rem_minmax(0,1fr)] lg:items-start">
            <aside class="lg:sticky lg:top-24">
                <form
                    class="rounded-[1.35rem] border border-slate-200/90 bg-white p-4 shadow-sm ring-1 ring-slate-100 sm:p-5"
                    @submit.prevent="applyFilters"
                >
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Filters</p>
                        <button
                            v-if="activeFilterCount || usingSmartDefaults"
                            type="button"
                            class="text-[10px] font-black uppercase tracking-wide text-primary-700 hover:text-primary-900"
                            @click="clearFilters"
                        >
                            Clear all
                        </button>
                    </div>

                    <div class="mt-4 space-y-4">
                        <div>
                            <label for="browse-q" class="text-xs font-bold text-slate-700">Keywords</label>
                            <input
                                id="browse-q"
                                v-model="draft.q"
                                type="search"
                                placeholder="Title, place, category…"
                                class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50/80 px-3 py-2.5 text-sm font-semibold text-slate-900 outline-none focus:border-primary-400 focus:bg-white focus:ring-2 focus:ring-primary-100"
                            />
                        </div>

                        <div>
                            <label class="text-xs font-bold text-slate-700">State</label>
                            <UiSelect
                                v-model="draft.state_id"
                                class="mt-1.5"
                                :options="stateOptions"
                                placeholder="Any state"
                                @update:model-value="onStateChange"
                            />
                        </div>

                        <div>
                            <label class="text-xs font-bold text-slate-700">Local government</label>
                            <UiSelect
                                v-model="draft.local_government_id"
                                class="mt-1.5"
                                :options="lgaOptions"
                                :disabled="!draft.state_id"
                                placeholder="Any LGA"
                            />
                        </div>

                        <div>
                            <label class="text-xs font-bold text-slate-700">Category</label>
                            <UiSelect
                                v-model="draft.parent_category_id"
                                class="mt-1.5"
                                :options="parentCategoryOptions"
                                placeholder="Any category"
                                @update:model-value="onParentCategoryChange"
                            />
                        </div>

                        <div>
                            <label class="text-xs font-bold text-slate-700">Subcategory</label>
                            <UiSelect
                                v-model="draft.quest_category_id"
                                class="mt-1.5"
                                :options="leafCategoryOptions"
                                :disabled="!draft.parent_category_id"
                                placeholder="Any subcategory"
                                @update:model-value="onLeafCategoryChange"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="budget-min" class="text-xs font-bold text-slate-700">Min budget (₦)</label>
                                <input
                                    id="budget-min"
                                    v-model.number="draft.budget_min_ngn"
                                    type="number"
                                    min="0"
                                    step="1000"
                                    placeholder="0"
                                    class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50/80 px-3 py-2.5 text-sm font-semibold text-slate-900 outline-none focus:border-primary-400 focus:bg-white focus:ring-2 focus:ring-primary-100"
                                />
                            </div>
                            <div>
                                <label for="budget-max" class="text-xs font-bold text-slate-700">Max budget (₦)</label>
                                <input
                                    id="budget-max"
                                    v-model.number="draft.budget_max_ngn"
                                    type="number"
                                    min="0"
                                    step="1000"
                                    placeholder="Any"
                                    class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50/80 px-3 py-2.5 text-sm font-semibold text-slate-900 outline-none focus:border-primary-400 focus:bg-white focus:ring-2 focus:ring-primary-100"
                                />
                            </div>
                        </div>

                        <div>
                            <label for="browse-skill" class="text-xs font-bold text-slate-700">Skill</label>
                            <input
                                id="browse-skill"
                                v-model="draft.skill"
                                type="text"
                                list="browse-skill-suggestions"
                                placeholder="e.g. Laravel, plumbing…"
                                class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50/80 px-3 py-2.5 text-sm font-semibold text-slate-900 outline-none focus:border-primary-400 focus:bg-white focus:ring-2 focus:ring-primary-100"
                            />
                            <datalist id="browse-skill-suggestions">
                                <option v-for="skill in filterOptions.popular_skills || []" :key="skill" :value="skill" />
                            </datalist>
                        </div>

                        <div>
                            <label class="text-xs font-bold text-slate-700">Sort by</label>
                            <UiSelect
                                v-model="draft.sort"
                                class="mt-1.5"
                                :options="sortOptions"
                            />
                        </div>
                    </div>

                    <button
                        type="submit"
                        class="mt-5 inline-flex w-full items-center justify-center rounded-full bg-primary-700 px-5 py-3 text-xs font-black uppercase tracking-wide text-white shadow-md shadow-primary-900/20 hover:bg-primary-800 disabled:opacity-60"
                        :disabled="loading"
                    >
                        <ReLoader4Line v-if="loading" class="mr-2 h-4 w-4 animate-spin" aria-hidden="true" />
                        Apply filters
                    </button>
                </form>
            </aside>

            <section class="min-w-0">
                <div
                    v-if="usingSmartDefaults"
                    class="mb-4 rounded-2xl border border-teal-200/80 bg-teal-50/80 px-4 py-3 text-sm font-semibold text-teal-950 ring-1 ring-teal-100"
                >
                    Showing quests tailored to your profile — categories, state, and verification budget. Adjust filters or
                    <button type="button" class="font-black text-primary-800 underline decoration-primary-300 underline-offset-2" @click="clearFilters">
                        browse everything
                    </button>.
                </div>

                <div v-if="activeFilterChips.length" class="mb-4 flex flex-wrap gap-2">
                    <span
                        v-for="chip in activeFilterChips"
                        :key="chip.key"
                        class="inline-flex items-center gap-1 rounded-full bg-primary-50 px-3 py-1 text-[11px] font-bold text-primary-900 ring-1 ring-primary-100"
                    >
                        {{ chip.label }}
                    </span>
                </div>

                <div v-if="questRows.length" class="grid gap-5 sm:grid-cols-2">
                    <QuestMarketplaceCard
                        v-for="quest in questRows"
                        :key="quest.id"
                        :quest="quest"
                        :workspace="workspace"
                        :verification-access="verification_access"
                        compact
                        :show-proposal-action="false"
                    />
                </div>

                <p
                    v-else
                    class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-6 py-14 text-center text-base font-semibold text-slate-600"
                >
                    No quests match these filters. Try widening location or budget, or
                    <button type="button" class="font-black text-primary-700 underline decoration-primary-300 underline-offset-2" @click="clearFilters">
                        clear filters
                    </button>.
                </p>

                <div v-if="meta.last_page > 1" class="mt-8 flex flex-col items-center justify-between gap-3 sm:flex-row">
                    <p class="text-xs font-semibold text-slate-500">
                        Showing {{ meta.from || 0 }}–{{ meta.to || 0 }} of {{ meta.total }}
                    </p>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="rounded-full border border-slate-200 bg-white px-4 py-2 text-xs font-black uppercase text-slate-700 disabled:opacity-40"
                            :disabled="meta.current_page <= 1 || loading"
                            @click="goPage(meta.current_page - 1)"
                        >
                            Previous
                        </button>
                        <span class="px-2 text-xs font-bold text-slate-500">Page {{ meta.current_page }} / {{ meta.last_page }}</span>
                        <button
                            type="button"
                            class="rounded-full border border-slate-200 bg-white px-4 py-2 text-xs font-black uppercase text-slate-700 disabled:opacity-40"
                            :disabled="meta.current_page >= meta.last_page || loading"
                            @click="goPage(meta.current_page + 1)"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </AppShell>
</template>

<script setup>
import QuestDiscoveryTabs from '@/Components/Quests/QuestDiscoveryTabs.vue';
import QuestMarketplaceCard from '@/Components/Quests/QuestMarketplaceCard.vue';
import UiSelect from '@/Components/Ui/UiSelect.vue';
import AppShell from '@/Layouts/AppShell.vue';
import { Head, router } from '@inertiajs/vue3';
import { ReLoader4Line } from '@kalimahapps/vue-icons/re';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    quests: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    filter_options: { type: Object, default: () => ({}) },
    workspace: { type: Object, required: true },
    verification_access: { type: Object, default: null },
});

const loading = ref(false);

const draft = reactive(normalizeFilters(props.filters));
const filterOptions = computed(() => props.filter_options || {});
const meta = computed(() => props.quests?.meta || {});
const questRows = computed(() => props.quests?.data || []);
const usingSmartDefaults = computed(() => props.filters?.using_smart_defaults === true);

watch(
    () => props.filters,
    (value) => {
        Object.assign(draft, normalizeFilters(value));
    },
    { deep: true },
);

const stateOptions = computed(() => [
    { value: '', label: 'Any state' },
    ...(filterOptions.value.locations || []).map((state) => ({ value: state.id, label: state.name })),
]);

const lgaOptions = computed(() => {
    const state = (filterOptions.value.locations || []).find((item) => Number(item.id) === Number(draft.state_id));
    const options = (state?.local_governments || []).map((lga) => ({ value: lga.id, label: lga.name }));
    return [{ value: '', label: 'Any LGA' }, ...options];
});

const parentCategoryOptions = computed(() => [
    { value: '', label: 'Any category' },
    ...(filterOptions.value.category_tree || []).map((parent) => ({ value: parent.id, label: parent.name })),
]);

const leafCategoryOptions = computed(() => {
    const parent = (filterOptions.value.category_tree || []).find((item) => Number(item.id) === Number(draft.parent_category_id));
    const options = (parent?.children || []).map((leaf) => ({ value: leaf.id, label: leaf.name }));
    return [{ value: '', label: 'Any subcategory' }, ...options];
});

const sortOptions = computed(() => filterOptions.value.sort_options || []);

const activeFilterCount = computed(() => activeFilterChips.value.length);

const activeFilterChips = computed(() => {
    const chips = [];
    if (draft.q?.trim()) chips.push({ key: 'q', label: `Search: ${draft.q.trim()}` });
    if (draft.state_id) chips.push({ key: 'state', label: stateOptions.value.find((o) => Number(o.value) === Number(draft.state_id))?.label || 'State' });
    if (draft.local_government_id) chips.push({ key: 'lga', label: lgaOptions.value.find((o) => Number(o.value) === Number(draft.local_government_id))?.label || 'LGA' });
    if (draft.parent_category_id) chips.push({ key: 'parent', label: parentCategoryOptions.value.find((o) => Number(o.value) === Number(draft.parent_category_id))?.label || 'Category' });
    if (draft.quest_category_id) chips.push({ key: 'leaf', label: leafCategoryOptions.value.find((o) => Number(o.value) === Number(draft.quest_category_id))?.label || 'Subcategory' });
    if (draft.category_ids?.length) chips.push({ key: 'categories', label: `Your categories (${draft.category_ids.length})` });
    if (draft.budget_min_ngn) chips.push({ key: 'min', label: `Min ₦${Number(draft.budget_min_ngn).toLocaleString('en-NG')}` });
    if (draft.budget_max_ngn) chips.push({ key: 'max', label: `Max ₦${Number(draft.budget_max_ngn).toLocaleString('en-NG')}` });
    if (draft.skill?.trim()) chips.push({ key: 'skill', label: `Skill: ${draft.skill.trim()}` });
    return chips;
});

function normalizeFilters(value) {
    return {
        q: value?.q || '',
        state_id: value?.state_id || '',
        local_government_id: value?.local_government_id || '',
        parent_category_id: value?.parent_category_id || '',
        quest_category_id: value?.quest_category_id || '',
        category_ids: Array.isArray(value?.category_ids) ? [...value.category_ids] : [],
        budget_min_ngn: value?.budget_min_ngn ?? '',
        budget_max_ngn: value?.budget_max_ngn ?? '',
        skill: value?.skill || '',
        sort: value?.sort || 'posted_desc',
        page: value?.page || 1,
    };
}

function payloadFromDraft(page = 1) {
    const data = {
        q: draft.q?.trim() || undefined,
        state_id: draft.state_id || undefined,
        local_government_id: draft.local_government_id || undefined,
        parent_category_id: draft.parent_category_id || undefined,
        quest_category_id: draft.quest_category_id || undefined,
        category_ids: draft.category_ids?.length ? draft.category_ids : undefined,
        budget_min_ngn: draft.budget_min_ngn || undefined,
        budget_max_ngn: draft.budget_max_ngn || undefined,
        skill: draft.skill?.trim() || undefined,
        sort: draft.sort || 'posted_desc',
        page,
        cleared: undefined,
    };

    return Object.fromEntries(Object.entries(data).filter(([, v]) => v !== undefined && v !== ''));
}

function applyFilters() {
    visit(payloadFromDraft(1));
}

function clearFilters() {
    Object.assign(draft, normalizeFilters({ sort: 'posted_desc' }));
    visit({ cleared: 1, sort: 'posted_desc' });
}

function goPage(page) {
    if (usingSmartDefaults.value) {
        visit({ smart: 1, page });
        return;
    }

    const payload = payloadFromDraft(page);
    if (props.filters?.cleared) {
        payload.cleared = 1;
    }
    visit(payload);
}

function visit(params) {
    loading.value = true;
    router.get(route('quests.browse'), params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => {
            loading.value = false;
        },
    });
}

function onStateChange() {
    draft.local_government_id = '';
}

function onParentCategoryChange() {
    draft.quest_category_id = '';
    draft.category_ids = [];
}

function onLeafCategoryChange() {
    draft.category_ids = [];
}
</script>
