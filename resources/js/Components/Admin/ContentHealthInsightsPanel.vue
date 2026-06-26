<template>
    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-xl shadow-slate-200/60 ring-1 ring-slate-100 dark:border-white/10 dark:bg-slate-900/70 dark:shadow-black/20 dark:ring-white/5 sm:p-6">
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.22em] text-primary-600 dark:text-primary-300">
                    Moderation vitals
                </p>
                <h2 class="mt-1 font-display text-xl font-black text-slate-950 dark:text-white">
                    Quest, proposal &amp; contract health
                </h2>
                <p class="mt-1 max-w-2xl text-sm font-medium text-slate-500 dark:text-slate-400">
                    Live patrol posture across marketplace content. Click a coloured band to see why records are flagged, then open the moderation workspace for full detail and actions.
                </p>
            </div>
            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">
                {{ modules.quests.total + modules.proposals.total + modules.contracts.total }} active records monitored
            </p>
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            <article
                v-for="module in moduleCards"
                :key="module.key"
                class="rounded-[1.75rem] border border-slate-100 bg-gradient-to-b from-slate-50/90 to-white p-4 ring-1 ring-slate-100 dark:border-white/10 dark:from-white/[0.04] dark:to-transparent dark:ring-white/5 sm:p-5"
            >
                <ContentHealthHalfDonut
                    :label="module.data.label"
                    :score="module.data.score"
                    :status-label="module.data.status_label"
                    :healthy="module.data.healthy"
                    :warning="module.data.warning"
                    :critical="module.data.critical"
                    :total="module.data.total"
                    :active-band="selection.module === module.key ? selection.band : null"
                    :animate-on-mount="true"
                    @select-band="(band) => openDrillDown(module.key, band)"
                />
            </article>
        </div>

        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-2"
        >
            <div
                v-if="selection.module && selection.band"
                class="mt-6 overflow-hidden rounded-[1.5rem] border border-slate-200 bg-slate-50/80 dark:border-white/10 dark:bg-white/[0.03]"
            >
                <div class="flex flex-col gap-3 border-b border-slate-200/80 px-4 py-4 dark:border-white/10 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.18em]" :class="bandTone.text">
                            {{ bandTone.label }} · {{ moduleLabel(selection.module) }}
                        </p>
                        <h3 class="mt-1 font-display text-lg font-black text-slate-950 dark:text-white">
                            {{ drillDown.total.toLocaleString() }} record{{ drillDown.total === 1 ? '' : 's' }}
                        </h3>
                    </div>
                    <div class="flex items-center gap-2">
                        <Link
                            v-if="moduleIndexUrl(selection.module)"
                            :href="moduleIndexUrl(selection.module)"
                            class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-3 py-2 text-xs font-bold text-white transition hover:bg-slate-800 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-100"
                        >
                            Open moderation
                        </Link>
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600 transition hover:bg-white dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5"
                            @click="clearSelection"
                        >
                            Close
                        </button>
                    </div>
                </div>

                <div v-if="loading" class="px-5 py-8 text-center text-sm font-semibold text-slate-500">
                    Loading flagged records…
                </div>
                <div v-else-if="drillDown.items.length === 0" class="px-5 py-8 text-center text-sm font-semibold text-slate-500">
                    No records in this band right now.
                </div>
                <ul v-else class="divide-y divide-slate-200/80 dark:divide-white/10">
                    <li v-for="item in drillDown.items" :key="`${selection.module}-${item.id}`">
                        <Link
                            :href="item.url"
                            class="flex flex-col gap-2 px-4 py-4 transition hover:bg-white/80 dark:hover:bg-white/[0.04] sm:flex-row sm:items-center sm:justify-between sm:px-5"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-display text-sm font-black text-slate-950 dark:text-white">
                                    {{ item.title }}
                                </p>
                                <p class="mt-1 text-xs font-semibold text-slate-500 dark:text-slate-400">
                                    {{ item.reference }} · {{ item.signal }}
                                </p>
                                <ul v-if="item.reasons?.length" class="mt-2 space-y-1">
                                    <li
                                        v-for="(reason, reasonIndex) in item.reasons.slice(0, 2)"
                                        :key="`${item.id}-reason-${reasonIndex}`"
                                        class="text-xs font-medium leading-relaxed text-slate-600 dark:text-slate-300"
                                    >
                                        {{ reason.reason }}
                                    </li>
                                </ul>
                                <p v-else-if="item.reason" class="mt-2 text-xs font-medium leading-relaxed text-slate-600 dark:text-slate-300">
                                    {{ item.reason }}
                                </p>
                            </div>
                            <span class="inline-flex shrink-0 items-center gap-1 text-xs font-bold text-primary-700 dark:text-primary-300">
                                View details
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L10.94 10 7.23 6.29a.75.75 0 111.06-1.06l4.25 4.25a.75.75 0 010 1.06l-4.25 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                                </svg>
                            </span>
                        </Link>
                    </li>
                </ul>
                <p
                    v-if="!loading && drillDown.total > drillDown.items.length"
                    class="border-t border-slate-200/80 px-5 py-3 text-center text-xs font-semibold text-slate-500 dark:border-white/10"
                >
                    Showing {{ drillDown.items.length }} of {{ drillDown.total.toLocaleString() }} — use Open workspace for the full queue.
                </p>
            </div>
        </Transition>
    </section>
</template>

<script setup>
import ContentHealthHalfDonut from '@/Components/Admin/ContentHealthHalfDonut.vue';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    modules: {
        type: Object,
        required: true,
    },
    drillDownUrl: {
        type: String,
        required: true,
    },
});

const selection = reactive({ module: null, band: null });
const loading = ref(false);
const drillDown = ref({ items: [], total: 0 });

const moduleCards = computed(() => [
    { key: 'quests', data: props.modules.quests ?? emptyModule('Quest health') },
    { key: 'proposals', data: props.modules.proposals ?? emptyModule('Proposal health') },
    { key: 'contracts', data: props.modules.contracts ?? emptyModule('Contract health') },
]);

const bandTone = computed(() => {
    const map = {
        healthy: { label: 'Healthy', text: 'text-emerald-700 dark:text-emerald-300' },
        warning: { label: 'Needs review', text: 'text-amber-700 dark:text-amber-300' },
        critical: { label: 'At risk', text: 'text-rose-700 dark:text-rose-300' },
    };

    return map[selection.band] ?? map.critical;
});

function emptyModule(label) {
    return {
        label,
        total: 0,
        healthy: 0,
        warning: 0,
        critical: 0,
        score: 100,
        status_label: 'Healthy',
    };
}

function moduleLabel(module) {
    return {
        quests: 'Quests',
        proposals: 'Proposals',
        contracts: 'Contracts',
    }[module] ?? module;
}

function moduleIndexUrl(module) {
    if (typeof route !== 'function') {
        return null;
    }

    return {
        quests: route('admin.moderation.index', { module: 'quests' }),
        proposals: route('admin.moderation.index', { module: 'proposals' }),
        contracts: route('admin.contract-management.index'),
    }[module] ?? null;
}

function clearSelection() {
    selection.module = null;
    selection.band = null;
    drillDown.value = { items: [], total: 0 };
}

async function openDrillDown(module, band) {
    if (selection.module === module && selection.band === band) {
        clearSelection();

        return;
    }

    selection.module = module;
    selection.band = band;
    loading.value = true;

    try {
        const { data } = await axios.get(props.drillDownUrl, {
            params: { module, band, limit: 15 },
        });
        drillDown.value = {
            items: data.items ?? [],
            total: data.total ?? 0,
        };
    } catch {
        drillDown.value = { items: [], total: 0 };
    } finally {
        loading.value = false;
    }
}

watch(
    () => props.modules,
    () => {
        if (selection.module && selection.band) {
            openDrillDown(selection.module, selection.band);
        }
    },
);
</script>
