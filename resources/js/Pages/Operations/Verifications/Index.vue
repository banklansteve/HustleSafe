<template>
    <OperationsShell title="Verification Queue" subtitle="KYC, BVN, NIN, utility, identity, and credential reviews. Settings and tier configuration remain Super Admin-only.">
        <QueueSearch v-model="form.q" placeholder="Search by user name or email" @submit="apply" />
        <div class="mt-5 space-y-3">
            <article v-for="item in verifications.data" :key="item.id" class="queue-card">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="pill-primary">{{ labelize(item.type) }}</span>
                            <span class="pill-muted">{{ labelize(item.status) }}</span>
                            <span class="text-xs font-bold text-slate-500">L{{ item.user?.level ?? 0 }}</span>
                        </div>
                        <h2 class="mt-3 font-display text-lg font-black text-slate-950">{{ item.user?.name || 'Unknown user' }}</h2>
                        <p class="mt-1 text-sm font-bold text-slate-600">{{ item.user?.email }}</p>
                        <p v-if="item.concern || item.reason" class="mt-3 rounded-2xl border border-amber-100 bg-amber-50 p-3 text-sm font-bold text-amber-950">{{ item.concern || item.reason }}</p>
                    </div>
                    <Link :href="route('operations.dashboard')" class="primary-button">Escalate if needed</Link>
                </div>
            </article>
            <EmptyState v-if="!verifications.data?.length" message="The verification queue is clear." />
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
    verifications: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const form = reactive({ q: props.filters.q || '' });

function apply() {
    router.get(route('operations.verifications.index'), form, { preserveState: true, preserveScroll: true });
}

function labelize(value) {
    return String(value || '').replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());
}
</script>

<style scoped>
.queue-card { @apply rounded-3xl border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100; }
.pill-primary { @apply rounded-full bg-emerald-100 px-3 py-1 text-[10px] font-black uppercase tracking-wide text-emerald-800; }
.pill-muted { @apply rounded-full bg-slate-100 px-3 py-1 text-[10px] font-black uppercase tracking-wide text-slate-700; }
.primary-button { @apply inline-flex rounded-xl bg-primary-700 px-4 py-2 text-xs font-black uppercase text-white hover:bg-primary-800; }
</style>
