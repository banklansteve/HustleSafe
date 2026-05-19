<template>
    <OperationsShell title="Review Moderation" subtitle="Approve, remove, request revision, or escalate review concerns through the staff workflow.">
        <div class="space-y-3">
            <article v-for="review in reviews.data" :key="review.id" class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-secondary-100 px-3 py-1 text-[10px] font-black uppercase tracking-wide text-secondary-800">{{ review.rating || '—' }}/5</span>
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-black uppercase tracking-wide text-slate-700">{{ review.status }}</span>
                        </div>
                        <h2 class="mt-3 font-display text-lg font-black text-slate-950">{{ review.title || 'Untitled review' }}</h2>
                        <p class="mt-1 text-sm font-bold text-slate-600">{{ review.reviewer || 'Reviewer' }} → {{ review.reviewee || 'Reviewee' }}</p>
                        <p class="mt-3 text-sm font-semibold text-slate-700">{{ review.comment }}</p>
                    </div>
                    <Link :href="route('operations.dashboard')" class="rounded-xl bg-primary-700 px-4 py-2 text-xs font-black uppercase text-white hover:bg-primary-800">Escalate</Link>
                </div>
            </article>
            <EmptyState v-if="!reviews.data?.length" message="No reviews are waiting for moderation." />
        </div>
    </OperationsShell>
</template>

<script setup>
import EmptyState from '@/Pages/Operations/Shared/EmptyState.vue';
import OperationsShell from '@/Layouts/OperationsShell.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    reviews: { type: Object, required: true },
});
</script>
