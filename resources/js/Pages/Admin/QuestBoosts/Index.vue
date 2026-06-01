<template>
    <AdminShell title="Quest Boosts" subtitle="Admin-granted promotional boosts with fixed tiers, audit trails, and investment tracking.">
        <div class="space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap gap-2">
                    <Link :href="route('admin.quest-boosts.report')" class="rounded-xl border px-4 py-2 text-xs font-black uppercase" :class="shell.btnGhost">Revenue report</Link>
                </div>
                <button type="button" class="rounded-xl bg-amber-600 px-4 py-2.5 text-xs font-black uppercase text-white" @click="showGrant = !showGrant">
                    Grant quest boost
                </button>
            </div>

            <section v-if="showGrant" class="rounded-3xl border p-5" :class="shell.card">
                <h2 class="text-sm font-black uppercase tracking-wide" :class="shell.title">Grant boost</h2>
                <form class="mt-4 grid gap-4 lg:grid-cols-2" @submit.prevent="submitGrant">
                    <div class="lg:col-span-2">
                        <label class="text-xs font-bold uppercase text-slate-500">Find quest</label>
                        <input v-model="questSearch" type="search" placeholder="Search by title, ID, or reference…" class="mt-1 w-full rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" @input="searchQuests" />
                        <ul v-if="questResults.length" class="mt-2 max-h-48 overflow-auto rounded-2xl border" :class="shell.card">
                            <li v-for="quest in questResults" :key="quest.id">
                                <button type="button" class="w-full px-4 py-3 text-left text-sm hover:bg-slate-50 dark:hover:bg-slate-800" @click="selectQuest(quest)">
                                    <span class="font-bold">{{ quest.title }}</span>
                                    <span class="mt-1 block text-xs text-slate-500">#{{ quest.id }} · {{ quest.client?.name }} · {{ quest.category }}</span>
                                </button>
                            </li>
                        </ul>
                        <p v-if="grantForm.quest_id" class="mt-2 text-xs font-semibold text-emerald-700">Selected quest #{{ grantForm.quest_id }}</p>
                    </div>
                    <div class="lg:col-span-2">
                        <p class="text-xs font-bold uppercase text-slate-500">Boost tier</p>
                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                            <label v-for="tier in tiers" :key="tier.value" class="flex cursor-pointer items-start gap-3 rounded-2xl border p-4" :class="grantForm.tier === tier.value ? 'border-amber-400 bg-amber-50/50' : shell.card">
                                <input v-model="grantForm.tier" type="radio" :value="tier.value" class="mt-1" />
                                <span>
                                    <span class="block text-sm font-black">{{ tier.label }}</span>
                                    <span class="text-xs font-semibold text-slate-500">{{ tier.price_display }}</span>
                                </span>
                            </label>
                        </div>
                    </div>
                    <div class="lg:col-span-2">
                        <label class="text-xs font-bold uppercase text-slate-500">Grant reason (required)</label>
                        <textarea v-model="grantForm.grant_reason" required maxlength="500" rows="3" class="mt-1 w-full rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" placeholder="Why is this boost being granted?" />
                    </div>
                    <div>
                        <label class="text-xs font-bold uppercase text-slate-500">Custom start (optional)</label>
                        <input v-model="grantForm.starts_at" type="datetime-local" class="mt-1 w-full rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                    </div>
                    <div>
                        <label class="text-xs font-bold uppercase text-slate-500">Override end (optional)</label>
                        <input v-model="grantForm.ends_at" type="datetime-local" class="mt-1 w-full rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                    </div>
                    <div class="lg:col-span-2 flex gap-2">
                        <button type="submit" class="rounded-xl bg-slate-900 px-5 py-3 text-xs font-black uppercase text-white disabled:opacity-50 dark:bg-white dark:text-slate-900" :disabled="grantForm.processing || !grantForm.quest_id">Grant boost</button>
                        <button type="button" class="rounded-xl border px-5 py-3 text-xs font-black uppercase" :class="shell.btnGhost" @click="showGrant = false">Cancel</button>
                    </div>
                </form>
            </section>

            <div class="grid gap-3 sm:grid-cols-2">
                <div v-for="tile in metricTiles" :key="tile.label" class="rounded-2xl border p-4" :class="shell.card">
                    <p class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">{{ tile.label }}</p>
                    <p class="mt-2 text-2xl font-black" :class="shell.title">{{ tile.value }}</p>
                </div>
            </div>

            <section class="rounded-3xl border overflow-hidden" :class="shell.card">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b text-[10px] font-black uppercase tracking-wider" :class="shell.label">
                            <tr>
                                <th class="px-4 py-3">Boost ID</th>
                                <th class="px-4 py-3">Quest</th>
                                <th class="px-4 py-3">Client</th>
                                <th class="px-4 py-3">Tier</th>
                                <th class="px-4 py-3">Cost</th>
                                <th class="px-4 py-3">Window</th>
                                <th class="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in boosts.data" :key="row.id" class="border-b cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/50" @click="router.visit(route('admin.quest-boosts.show', row.id))">
                                <td class="px-4 py-3 font-mono text-xs">{{ row.reference }}</td>
                                <td class="px-4 py-3 font-semibold">{{ row.quest_title }}</td>
                                <td class="px-4 py-3">{{ row.client_name }}</td>
                                <td class="px-4 py-3">{{ row.tier_label }}</td>
                                <td class="px-4 py-3">{{ row.planned_cost_display }}</td>
                                <td class="px-4 py-3 text-xs">{{ formatWhen(row.starts_at) }} → {{ formatWhen(row.ends_at) }}</td>
                                <td class="px-4 py-3"><span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-black uppercase">{{ row.status_label }}</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="!boosts.data?.length" class="p-8 text-center text-sm font-semibold text-slate-500">No boosts yet.</div>
            </section>
        </div>
    </AdminShell>
</template>

<script setup>
import AdminShell from '@/Layouts/AdminShell.vue';
import { useAdminShell } from '@/Composables/useAdminShell';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    boosts: { type: Object, required: true },
    tiers: { type: Array, default: () => [] },
    metrics: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const { shell } = useAdminShell();
const showGrant = ref(false);
const questSearch = ref('');
const questResults = ref([]);
let searchTimer = null;

const grantForm = useForm({
    quest_id: '',
    tier: '7_day',
    grant_reason: '',
    starts_at: '',
    ends_at: '',
});

const metricTiles = computed(() => [
    { label: 'Active boosts', value: props.metrics.active ?? 0 },
    { label: 'Investment this month', value: formatMoney(props.metrics.investment_month_minor) },
]);

function formatMoney(minor) {
    if (!minor) return '₦0.00';
    return `₦${(minor / 100).toLocaleString('en-NG', { minimumFractionDigits: 2 })}`;
}

function formatWhen(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('en-NG', { dateStyle: 'short', timeStyle: 'short' });
}

function searchQuests() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(async () => {
        if (questSearch.value.length < 2) {
            questResults.value = [];
            return;
        }
        const { data } = await window.axios.get(route('admin.quest-boosts.quests.search'), { params: { q: questSearch.value } });
        questResults.value = data.data ?? [];
    }, 300);
}

function selectQuest(quest) {
    grantForm.quest_id = quest.id;
    questSearch.value = quest.title;
    questResults.value = [];
}

function submitGrant() {
    grantForm.post(route('admin.quest-boosts.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showGrant.value = false;
            grantForm.reset();
        },
    });
}
</script>
