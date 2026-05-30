<template>
    <AdminShell title="Issue groups" subtitle="Manage support ticket categories without a code deploy">
        <div class="mx-auto max-w-4xl space-y-6">
            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-xl font-black text-slate-950">Add issue group</h2>
                <form class="mt-4 grid gap-3 md:grid-cols-2" @submit.prevent="createGroup">
                    <input v-model="createForm.label" type="text" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold md:col-span-2" placeholder="Label" required />
                    <input v-model="createForm.key" type="text" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" placeholder="Key (optional)" />
                    <input v-model.number="createForm.sort_order" type="number" min="0" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" placeholder="Sort order" />
                    <textarea v-model="createForm.description" rows="3" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold md:col-span-2" placeholder="Description" />
                    <button type="submit" class="rounded-xl bg-primary-700 px-4 py-2.5 text-sm font-black text-white md:col-span-2">Save group</button>
                </form>
            </section>

            <section class="space-y-3">
                <article v-for="group in groups" :key="group.id" class="rounded-2xl border border-slate-200 bg-white p-4">
                    <form class="grid gap-3 md:grid-cols-2" @submit.prevent="updateGroup(group)">
                        <input v-model="group.label" type="text" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold md:col-span-2" required />
                        <input v-model="group.key" type="text" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" readonly />
                        <input v-model.number="group.sort_order" type="number" min="0" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" />
                        <label class="flex items-center gap-2 text-sm font-semibold md:col-span-2"><input v-model="group.is_active" type="checkbox" /> Active</label>
                        <button type="submit" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-black text-slate-700 md:col-span-2">Update</button>
                    </form>
                </article>
            </section>
        </div>
    </AdminShell>
</template>

<script setup>
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminShell from '@/Layouts/AdminShell.vue';

defineProps({
    groups: { type: Array, default: () => [] },
});

const createForm = reactive({
    label: '',
    key: '',
    description: '',
    sort_order: 0,
    is_active: true,
});

function createGroup() {
    router.post(route('admin.support-tickets.issue-groups.store'), createForm, {
        onSuccess: () => {
            createForm.label = '';
            createForm.key = '';
            createForm.description = '';
            createForm.sort_order = 0;
        },
    });
}

function updateGroup(group) {
    router.patch(route('admin.support-tickets.issue-groups.update', group.id), {
        label: group.label,
        description: group.description,
        sort_order: group.sort_order,
        is_active: group.is_active,
    });
}
</script>
