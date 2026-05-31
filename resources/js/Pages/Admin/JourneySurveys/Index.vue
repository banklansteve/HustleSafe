<template>
    <AdminShell
        title="Journey survey responses"
        subtitle="Full feedback per user and quest — attributed in admin only."
    >
        <AdminPanel eyebrow="Filters" title="Find responses">
            <form class="flex flex-wrap gap-3" @submit.prevent="applyFilters">
                <input
                    v-model="filterForm.search"
                    type="search"
                    placeholder="Name, email, or quest title"
                    class="min-w-[200px] flex-1 rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold"
                />
                <select
                    v-model="filterForm.cohort"
                    class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold"
                >
                    <option value="">All cohorts</option>
                    <option v-for="c in cohorts" :key="c.value" :value="c.value">{{ c.label }}</option>
                </select>
                <input
                    v-model.number="filterForm.user_id"
                    type="number"
                    min="1"
                    placeholder="User ID"
                    class="w-28 rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold"
                />
                <button type="submit" class="rounded-xl bg-primary-700 px-4 py-2 text-xs font-black uppercase text-white">
                    Apply
                </button>
                <Link
                    :href="route('admin.journey-surveys.insights')"
                    class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-black uppercase text-slate-700"
                >
                    Survey insights
                </Link>
            </form>
        </AdminPanel>

        <div class="space-y-4">
            <article
                v-for="row in responses.data"
                :key="row.id"
                class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-black text-slate-900">{{ row.user.name }}</p>
                        <p class="text-xs font-semibold text-slate-500">{{ row.user.email }} · User #{{ row.user.id }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs font-black uppercase text-primary-700">{{ cohortLabel(row.cohort) }}</p>
                        <p class="text-[11px] font-semibold text-slate-400">{{ formatDate(row.submitted_at) }}</p>
                    </div>
                </div>
                <p class="mt-3 text-sm font-bold text-slate-800">
                    {{ row.quest.title }}
                    <span v-if="row.quest.category" class="font-semibold text-slate-500">· {{ row.quest.category }}</span>
                </p>
                <dl class="mt-4 grid gap-2 sm:grid-cols-2">
                    <div
                        v-for="(value, key) in row.answers"
                        :key="key"
                        class="rounded-lg bg-slate-50 px-3 py-2"
                    >
                        <dt class="text-[10px] font-black uppercase tracking-wide text-slate-500">{{ humanKey(key) }}</dt>
                        <dd class="mt-0.5 text-sm font-semibold text-slate-800">{{ formatAnswer(value) }}</dd>
                    </div>
                </dl>
            </article>
        </div>

        <div v-if="responses.links?.length > 3" class="mt-6 flex flex-wrap gap-2">
            <Link
                v-for="link in responses.links"
                :key="link.label"
                :href="link.url || '#'"
                class="rounded-lg px-3 py-1.5 text-xs font-bold"
                :class="link.active ? 'bg-primary-700 text-white' : 'bg-slate-100 text-slate-700'"
                v-html="link.label"
            />
        </div>
    </AdminShell>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import AdminShell from '@/Layouts/AdminShell.vue';
import AdminPanel from '@/Components/Admin/AdminPanel.vue';

const props = defineProps({
    responses: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    cohorts: { type: Array, default: () => [] },
});

const filterForm = reactive({
    search: props.filters.search ?? '',
    cohort: props.filters.cohort ?? '',
    user_id: props.filters.user_id ?? '',
});

function applyFilters() {
    router.get(route('admin.journey-surveys.index'), {
        search: filterForm.search || undefined,
        cohort: filterForm.cohort || undefined,
        user_id: filterForm.user_id || undefined,
    }, { preserveState: true });
}

function cohortLabel(value) {
    return props.cohorts.find((c) => c.value === value)?.label ?? value;
}

function humanKey(key) {
    return String(key).replace(/_/g, ' ');
}

function formatAnswer(value) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return String(value).replace(/_/g, ' ');
}

function formatDate(iso) {
    if (!iso) {
        return '';
    }

    return new Date(iso).toLocaleString();
}
</script>
