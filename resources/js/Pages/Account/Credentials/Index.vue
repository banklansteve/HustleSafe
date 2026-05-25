<template>
    <AppShell>
        <Head title="Credentials · HustleSafe" />

        <div class="mx-auto w-full max-w-4xl space-y-10">
            <div>
                <Link
                    :href="route('account.show', { tab: 'credentials' })"
                    class="text-xs font-bold uppercase tracking-wide text-primary-700 hover:text-primary-800"
                >
                    ← Account hub
                </Link>
                <h1 class="font-display mt-2 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">
                    Credentials &amp; proof
                </h1>
                <p class="mt-2 max-w-2xl text-sm font-medium text-slate-600">
                    Add as many entries as you need in each category. Each insurance policy, licence, qualification, or certification is saved separately with its own documents.
                </p>
            </div>

            <section
                v-for="section in sections"
                :key="section.type"
                class="rounded-[1.75rem] border border-slate-100 bg-white p-6 shadow-sm ring-1 ring-slate-100 sm:p-8"
            >
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="font-display text-lg font-bold text-slate-900">
                            {{ section.label }}
                        </h2>
                        <p class="mt-1 text-xs font-medium text-slate-600">
                            {{ sectionHint(section.type) }}
                        </p>
                    </div>
                    <Link
                        :href="route('account.credentials.create', { type: section.type })"
                        class="inline-flex shrink-0 items-center justify-center rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-bold text-white shadow-md hover:bg-primary-700"
                    >
                        Add {{ section.label }}
                    </Link>
                </div>

                <ul v-if="section.items.length" class="mt-6 space-y-3">
                    <li
                        v-for="c in section.items"
                        :key="c.id"
                        class="flex flex-col gap-4 rounded-2xl border border-slate-100 bg-slate-50/60 p-4 ring-1 ring-slate-100 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0">
                            <p class="mt-1 font-display text-base font-bold text-slate-900">
                                {{ c.title }}
                            </p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <span
                                    v-if="c.is_verified"
                                    class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-black uppercase text-emerald-900 ring-1 ring-emerald-200"
                                >
                                    Verified
                                </span>
                                <span
                                    v-else
                                    class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-black uppercase text-amber-950 ring-1 ring-amber-200"
                                >
                                    Not verified
                                </span>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <Link
                                v-if="c.document_url"
                                :href="c.document_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-800 hover:bg-slate-50"
                            >
                                Document
                            </Link>
                            <Link
                                :href="route('account.credentials.edit', { freelancerCredential: c.id })"
                                class="rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-primary-700"
                            >
                                Edit
                            </Link>
                            <button
                                type="button"
                                class="rounded-lg border border-rose-200 bg-white px-3 py-1.5 text-xs font-bold text-rose-700 hover:bg-rose-50"
                                @click="destroy(c)"
                            >
                                Delete
                            </button>
                        </div>
                    </li>
                </ul>
                <p
                    v-else
                    class="mt-4 rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-center text-sm font-semibold text-slate-600"
                >
                    No {{ section.label.toLowerCase() }} yet — use “Add {{ section.label }}”.
                </p>
            </section>
        </div>
    </AppShell>
</template>

<script setup>
import AppShell from '@/Layouts/AppShell.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    sections: { type: Array, default: () => [] },
});

function sectionHint(type) {
    const hints = {
        insurance: 'NAICOM-backed policies, PI cover, equipment insurance — one row per policy.',
        professional_licence: 'COREN, ARCON, NIM, MDCN, and similar — one row per qualification.',
        qualification: 'Degrees, diplomas, and formal programmes — one row each.',
        certification: 'Vendor badges, cloud certs, safety cards — one row each.',
    };

    return hints[type] ?? '';
}

function destroy(c) {
    if (!window.confirm('Remove this credential?')) {
        return;
    }
    router.delete(route('account.credentials.destroy', { freelancerCredential: c.id }), {
        preserveScroll: true,
    });
}
</script>
