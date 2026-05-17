<template>
    <OperationsShell
        title="Published portfolios"
        subtitle="Discovery moderation only — hide a portfolio from public directories without deleting the owner’s work."
    >
        <form class="flex flex-col gap-3 rounded-2xl border border-white/10 bg-slate-900/50 p-4 sm:flex-row sm:items-end" @submit.prevent="apply">
            <div class="min-w-[12rem] flex-1">
                <label class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500" for="pf-q">Search</label>
                <input
                    id="pf-q"
                    v-model="form.q"
                    type="search"
                    class="mt-1 w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2 text-sm font-semibold text-white outline-none ring-2 ring-transparent focus:border-amber-400/60 focus:ring-amber-500/40"
                    placeholder="Title, slug, owner"
                />
            </div>
            <button type="submit" class="rounded-xl bg-amber-500 px-4 py-2 text-sm font-black uppercase tracking-wide text-slate-950 hover:bg-amber-400">
                Apply
            </button>
        </form>

        <div class="mt-6 overflow-x-auto rounded-2xl border border-white/10 bg-slate-900/40 ring-1 ring-white/5">
            <table class="min-w-full divide-y divide-white/10 text-left text-sm">
                <thead class="bg-slate-900/80 text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">
                    <tr>
                        <th class="px-4 py-3">ID</th>
                        <th class="px-4 py-3">Title</th>
                        <th class="px-4 py-3">Owner</th>
                        <th class="px-4 py-3">Hidden</th>
                        <th class="px-4 py-3">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    <tr v-for="p in portfolios.data" :key="p.id" class="hover:bg-white/5">
                        <td class="px-4 py-3 font-mono text-xs text-slate-400">{{ p.id }}</td>
                        <td class="px-4 py-3 font-semibold text-white">{{ p.title }}</td>
                        <td class="px-4 py-3 text-xs text-slate-400">{{ p.user?.email ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs text-slate-400">{{ p.admin_hidden ? 'Yes' : 'No' }}</td>
                        <td class="px-4 py-3">
                            <button
                                type="button"
                                class="rounded-lg border border-white/15 px-3 py-1.5 text-xs font-black uppercase tracking-wide text-white transition hover:bg-white/10 disabled:opacity-40"
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
                :class="[link.active ? 'bg-amber-500 text-slate-950' : 'border border-white/10 text-slate-200', !link.url ? 'pointer-events-none opacity-40' : '']"
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
