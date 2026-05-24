<template>
    <div class="space-y-4">
        <div class="flex flex-wrap gap-2">
            <button
                v-for="option in periods"
                :key="option.key"
                type="button"
                class="rounded-full px-3 py-1.5 text-xs font-black uppercase tracking-wide transition"
                :class="period === option.key ? 'bg-primary-700 text-white' : 'border border-slate-200 text-slate-600 dark:border-white/10 dark:text-slate-300'"
                @click="period = option.key; load()"
            >
                {{ option.label }}
            </button>
        </div>

        <div v-if="loading" class="rounded-2xl border border-slate-200 p-4 text-sm font-semibold text-slate-500 dark:border-white/10">Loading activity…</div>
        <div v-else-if="error" class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-semibold text-rose-800">{{ error }}</div>
        <template v-else-if="summary">
            <div class="grid gap-3 sm:grid-cols-2">
                <div v-for="tile in summary.tiles" :key="tile.label" class="rounded-2xl border border-slate-200 p-4 dark:border-white/10">
                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">{{ tile.label }}</p>
                    <p class="mt-2 text-2xl font-black text-slate-900 dark:text-white">{{ tile.value }}</p>
                </div>
            </div>
            <div v-if="summary.timeline?.length" class="space-y-2">
                <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Recent actions</p>
                <article
                    v-for="item in summary.timeline"
                    :key="item.id"
                    class="rounded-2xl border border-slate-100 px-3 py-2 text-sm dark:border-white/10"
                >
                    <p class="font-black text-slate-900 dark:text-white">{{ item.label }}</p>
                    <p class="text-xs font-semibold text-slate-500">{{ item.when }}</p>
                </article>
            </div>
            <p v-else class="rounded-2xl border border-slate-200 p-4 text-sm font-semibold text-slate-500 dark:border-white/10">No recorded activity in this period.</p>
        </template>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    staffId: { type: [Number, String], default: null },
    staffName: { type: String, default: '' },
});

const periods = [
    { key: 'day', label: 'Today' },
    { key: 'week', label: 'This week' },
    { key: 'month', label: 'This month' },
];

const period = ref('day');
const loading = ref(false);
const error = ref('');
const summary = ref(null);

watch(
    () => props.staffId,
    () => {
        if (props.staffId) {
            load();
        }
    },
    { immediate: true },
);

async function load() {
    if (!props.staffId) {
        return;
    }

    loading.value = true;
    error.value = '';
    try {
        const { data } = await window.axios.get(route('admin.api.staff-activity.summary', { user: props.staffId }), {
            params: { period: period.value },
        });
        summary.value = data;
    } catch {
        error.value = 'Could not load staff activity for this period.';
        summary.value = null;
    } finally {
        loading.value = false;
    }
}
</script>
