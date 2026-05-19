<template>
    <OperationsShell title="Proposal Queue" subtitle="Staff moderation view for flagged proposals, proposal risk triage, and escalation boundaries.">
        <QueueSearch v-model="form.q" placeholder="Search proposal, freelancer, Quest, or ID" @submit="apply" />
        <div class="mt-5 space-y-3">
            <article v-for="proposal in proposals.data" :key="proposal.id" class="queue-card">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="pill-primary">Proposal #{{ proposal.id }}</span>
                            <span class="pill-muted">{{ labelize(proposal.admin_status || 'clear') }}</span>
                            <span class="pill-muted">{{ labelize(proposal.status) }}</span>
                        </div>
                        <h2 class="mt-3 font-display text-lg font-black text-slate-950">{{ proposal.quest?.title || 'Untitled Quest' }}</h2>
                        <p class="mt-1 text-sm font-bold text-slate-600">{{ proposal.freelancer?.name || 'Unknown freelancer' }} · {{ proposal.freelancer?.email }}</p>
                        <p class="mt-3 text-sm font-semibold leading-relaxed text-slate-700">{{ proposal.excerpt }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Link :href="route('operations.tasks.index')" class="action-button">Create follow-up</Link>
                        <Link :href="route('operations.dashboard')" class="primary-button">Escalate</Link>
                    </div>
                </div>
            </article>
            <EmptyState v-if="!proposals.data?.length" message="No proposals matched this queue." />
        </div>
    </OperationsShell>
</template>

<script setup>
import EmptyState from '@/Pages/Operations/Shared/EmptyState.vue';
import QueueSearch from '@/Pages/Operations/Shared/QueueSearch.vue';
import OperationsShell from '@/Layouts/OperationsShell.vue';
import { Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    proposals: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const form = reactive({ q: props.filters.q || '' });

function apply() {
    router.get(route('operations.proposals.index'), form, { preserveState: true, preserveScroll: true });
}

function labelize(value) {
    return String(value || '').replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());
}
</script>

<style scoped>
.queue-card { @apply rounded-3xl border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100; }
.pill-primary { @apply rounded-full bg-primary-100 px-3 py-1 text-[10px] font-black uppercase tracking-wide text-primary-800; }
.pill-muted { @apply rounded-full bg-slate-100 px-3 py-1 text-[10px] font-black uppercase tracking-wide text-slate-700; }
.action-button { @apply inline-flex rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-black uppercase text-slate-800 hover:bg-slate-50; }
.primary-button { @apply inline-flex rounded-xl bg-primary-700 px-4 py-2 text-xs font-black uppercase text-white hover:bg-primary-800; }
</style>
