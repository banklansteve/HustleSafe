<template>
    <AdminShell title="Knowledge base" subtitle="Maintain policies, precedents, and staff procedures for the operations console.">
        <div class="mb-4 rounded-2xl border border-primary-100 bg-primary-50/60 px-4 py-3 text-sm font-semibold text-slate-700">
            <p>Operations staff read <strong>published</strong> articles at <code class="rounded bg-white px-1 text-xs">/operations/knowledge-base</code>. Bulk refresh seeded content: <code class="rounded bg-white px-1 text-xs">php artisan knowledge-base:seed --force</code></p>
        </div>
        <div class="grid gap-6 lg:grid-cols-2">
            <form class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" @submit.prevent="createArticle">
                <p class="text-[10px] font-black uppercase text-primary-700">New article</p>
                <input v-model="form.title" required class="mt-3 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Title" />
                <input v-model="form.category" required class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Category" />
                <textarea v-model="form.body" required rows="8" class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Body (HTML supported)" />
                <button type="submit" class="mt-3 w-full rounded-xl bg-primary-700 py-2.5 text-sm font-black text-white active:scale-[0.98]">Publish article</button>
            </form>

            <div class="space-y-3">
                <article v-for="a in articles" :key="a.id" class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                    <p class="text-[10px] font-black uppercase text-slate-400">{{ a.category }} · {{ a.status }}</p>
                    <h3 class="font-display text-lg font-black text-slate-950">{{ a.title }}</h3>
                    <p class="mt-1 text-xs text-slate-500">Updated {{ a.updated_at }}</p>
                </article>
            </div>
        </div>
    </AdminShell>
</template>

<script setup>
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminShell from '@/Layouts/AdminShell.vue';

const props = defineProps({
    articles: { type: Array, default: () => [] },
});

const form = reactive({ title: '', category: 'Moderation', body: '' });

function createArticle() {
    router.post(route('admin.knowledge-base.store'), { ...form }, { preserveScroll: true, onSuccess: () => { form.title = ''; form.body = ''; } });
}
</script>
