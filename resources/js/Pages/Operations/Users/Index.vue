<template>
    <OperationsShell
        title="Users"
        subtitle="Directory, CSV export, and suspension toggles for member accounts. Staff and super admin accounts cannot be changed here."
    >
        <div class="mb-6 flex flex-col gap-3 rounded-2xl border border-white/10 bg-slate-900/40 p-4 ring-1 ring-white/5 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm font-semibold text-slate-400">Export respects the search filter.</p>
            <a :href="exportUrl" class="inline-flex rounded-xl bg-amber-500 px-4 py-2 text-sm font-black uppercase tracking-wide text-slate-950 hover:bg-amber-400">
                Export CSV
            </a>
        </div>

        <form class="flex flex-col gap-3 rounded-2xl border border-white/10 bg-slate-900/50 p-4 sm:flex-row sm:items-end" @submit.prevent="apply">
            <div class="min-w-[12rem] flex-1">
                <label class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500" for="ou-q">Search</label>
                <input
                    id="ou-q"
                    v-model="form.q"
                    type="search"
                    class="mt-1 w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2 text-sm font-semibold text-white outline-none ring-2 ring-transparent focus:border-amber-400/60 focus:ring-amber-500/40"
                    placeholder="Name, email, username"
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
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Email</th>
                        <th class="px-4 py-3">Role</th>
                        <th class="px-4 py-3">Suspended</th>
                        <th class="px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5 text-slate-100">
                    <tr v-for="u in users.data" :key="u.id" class="hover:bg-white/5">
                        <td class="px-4 py-3 font-mono text-xs text-slate-400">{{ u.id }}</td>
                        <td class="px-4 py-3 font-semibold text-white">{{ u.name }}</td>
                        <td class="px-4 py-3 text-slate-300">{{ u.email }}</td>
                        <td class="px-4 py-3 text-amber-200">{{ (u.role?.slug ?? '—').replace(/_/g, ' ') }}</td>
                        <td class="px-4 py-3 text-xs text-slate-400">{{ u.suspended_at ? 'Yes' : 'No' }}</td>
                        <td class="px-4 py-3">
                            <button
                                v-if="canToggle(u)"
                                type="button"
                                class="rounded-lg border border-white/15 px-3 py-1.5 text-xs font-black uppercase tracking-wide text-white transition hover:bg-white/10 disabled:opacity-40"
                                :disabled="busyId === u.id"
                                @click="toggle(u)"
                            >
                                {{ u.suspended_at ? 'Unsuspend' : 'Suspend' }}
                            </button>
                            <span v-else class="text-xs font-semibold text-slate-600">—</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav v-if="users.links?.length > 3" class="mt-6 flex flex-wrap justify-center gap-2">
            <component
                :is="link.url ? Link : 'span'"
                v-for="link in users.links"
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
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    users: { type: Object, required: true },
    filters: { type: Object, required: true },
});

const page = usePage();
const busyId = ref(null);

const form = reactive({ q: props.filters.q ?? '' });

watch(
    () => props.filters,
    (f) => {
        form.q = f.q ?? '';
    },
    { deep: true },
);

const exportUrl = computed(() => route('operations.users.export', { q: form.q || undefined }));

const myId = computed(() => page.props.auth?.user?.id);

function canToggle(u) {
    const slug = u.role?.slug ?? '';
    if (['super_admin', 'admin'].includes(slug)) {
        return false;
    }

    return u.id !== myId.value;
}

function toggle(u) {
    busyId.value = u.id;
    router.patch(
        route('operations.users.suspension.update', u.id),
        { suspended: !u.suspended_at },
        {
            preserveScroll: true,
            onFinish: () => {
                busyId.value = null;
            },
        },
    );
}

function apply() {
    router.get(route('operations.users.index'), { q: form.q || undefined, per_page: props.filters.per_page }, { preserveState: true, replace: true });
}
</script>
