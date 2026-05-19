<template>
    <OperationsShell
        title="Published portfolios"
        subtitle="Discovery moderation only — hide a portfolio from public directories without deleting the owner’s work."
    >
        <form class="flex flex-col gap-3 rounded-2xl border border-slate-100 bg-white p-4 shadow-sm sm:flex-row sm:items-end" @submit.prevent="apply">
            <div class="min-w-[12rem] flex-1">
                <label class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500" for="pf-q">Search</label>
                <input
                    id="pf-q"
                    v-model="form.q"
                    type="search"
                    class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-900 outline-none ring-2 ring-transparent focus:border-primary-400 focus:ring-primary-100"
                    placeholder="Title, slug, owner"
                />
            </div>
            <button type="submit" class="rounded-xl bg-primary-700 px-4 py-2 text-sm font-black uppercase tracking-wide text-white hover:bg-primary-800">
                Apply
            </button>
        </form>

        <div class="mt-6 overflow-x-auto rounded-2xl border border-slate-100 bg-white shadow-sm ring-1 ring-slate-100">
            <table class="min-w-full divide-y divide-slate-100 text-left text-sm">
                <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">
                    <tr>
                        <th class="px-4 py-3">ID</th>
                        <th class="px-4 py-3">Title</th>
                        <th class="px-4 py-3">Owner</th>
                        <th class="px-4 py-3">Hidden</th>
                        <th class="px-4 py-3">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    <tr v-for="p in portfolios.data" :key="p.id" class="hover:bg-primary-50/50">
                        <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ p.id }}</td>
                        <td class="px-4 py-3 font-semibold text-slate-950">{{ p.title }}</td>
                        <td class="px-4 py-3 text-xs text-slate-500">{{ p.user?.email ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs text-slate-500">{{ p.admin_hidden ? 'Yes' : 'No' }}</td>
                        <td class="px-4 py-3">
                            <button
                                type="button"
                                class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-black uppercase tracking-wide text-slate-800 transition hover:bg-primary-50 hover:text-primary-900 disabled:opacity-40"
                                :disabled="busyId === p.id"
                                @click="toggle(p)"
                            >
                                {{ p.admin_hidden ? 'Show' : 'Hide' }}
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav v-if="portfolios.links?.length > 3" class="mt-6 flex flex-wrap justify-center gap-2">
            <component
                :is="link.url ? Link : 'span'"
                v-for="link in portfolios.links"
                :key="String(link.label) + (link.url || 'x')"
                :href="link.url || undefined"
                prefetch="false"
                class="min-w-[2.5rem] rounded-lg px-3 py-1.5 text-center text-xs font-bold transition"
                :class="[link.active ? 'bg-primary-700 text-white' : 'border border-slate-200 bg-white text-slate-700', !link.url ? 'pointer-events-none opacity-40' : '']"
                preserve-state
            >
                <span v-html="link.label" />
            </component>
        </nav>
    </OperationsShell>
</template>

<script setup>
import OperationsShell from '@/Layouts/OperationsShell.vue';
import { Link, router } from '@inertiajs/vue3';
import { reactive, ref, watch } from 'vue';

const props = defineProps({
    portfolios: { type: Object, required: true },
    filters: { type: Object, required: true },
});

const busyId = ref(null);

const form = reactive({ q: props.filters.q ?? '' });

watch(
    () => props.filters,
    (f) => {
        form.q = f.q ?? '';
    },
    { deep: true },
);

function toggle(p) {
    busyId.value = p.id;
    router.patch(
        route('operations.portfolios.visibility.update', p.id),
        { admin_hidden: !p.admin_hidden },
        {
            preserveScroll: true,
            onFinish: () => {
                busyId.value = null;
            },
        },
    );
}

function apply() {
    router.get(route('operations.portfolios.index'), { q: form.q || undefined, per_page: props.filters.per_page }, { preserveState: true, replace: true });
}
</script>
