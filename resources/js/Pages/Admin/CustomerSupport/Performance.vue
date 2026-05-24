<template>
    <AdminShell title="Support performance" subtitle="Ratings, volume, and resolution time per admin — Super Admin only.">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <button
                v-for="m in metrics"
                :key="m.admin_id"
                type="button"
                class="rounded-[1.5rem] border border-slate-200 bg-white p-5 text-left shadow-sm transition hover:border-primary-200 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40"
                @click="openFeedback(m)"
            >
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-sm font-black text-slate-900">{{ m.name }}</p>
                        <p class="text-xs font-semibold text-slate-500">{{ m.email }}</p>
                    </div>
                    <span
                        class="rounded-full px-2 py-0.5 text-[10px] font-black uppercase"
                        :class="m.online ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-500'"
                    >{{ m.online ? 'Online' : 'Away' }}</span>
                </div>
                <dl class="mt-4 grid grid-cols-2 gap-2 text-center sm:grid-cols-3">
                    <div class="rounded-xl bg-primary-50 px-2 py-3">
                        <dt class="text-[10px] font-black uppercase text-primary-700">Avg rating</dt>
                        <dd class="mt-1 text-lg font-black text-primary-900">{{ formatRating(m.average_rating) }}</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 px-2 py-3">
                        <dt class="text-[10px] font-black uppercase text-slate-500">Today</dt>
                        <dd class="mt-1 text-lg font-black text-slate-900">{{ m.chats_today }}</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 px-2 py-3">
                        <dt class="text-[10px] font-black uppercase text-slate-500">This week</dt>
                        <dd class="mt-1 text-lg font-black text-slate-900">{{ m.chats_week }}</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 px-2 py-3">
                        <dt class="text-[10px] font-black uppercase text-slate-500">This month</dt>
                        <dd class="mt-1 text-lg font-black text-slate-900">{{ m.chats_month }}</dd>
                    </div>
                    <div class="rounded-xl bg-emerald-50 px-2 py-3">
                        <dt class="text-[10px] font-black uppercase text-emerald-700">Live today</dt>
                        <dd class="mt-1 text-lg font-black text-emerald-900">{{ m.live_today }}</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 px-2 py-3">
                        <dt class="text-[10px] font-black uppercase text-slate-500">Closed today</dt>
                        <dd class="mt-1 text-lg font-black text-slate-900">{{ m.closed_today }}</dd>
                    </div>
                </dl>
                <p class="mt-3 flex items-center justify-between text-[10px] font-semibold text-slate-500">
                    <span>Avg resolve {{ m.average_resolution_minutes != null ? `${m.average_resolution_minutes}m` : '—' }}</span>
                    <span class="font-black uppercase text-primary-700">View feedback →</span>
                </p>
            </button>
        </div>

        <AdminSlideOver
            :open="feedbackOpen"
            :title="feedbackAdmin?.name || 'Feedback'"
            eyebrow="Session ratings"
            width-class="w-full max-w-3xl"
            panel-class="bg-white text-slate-950"
            @close="closeFeedback"
        >
            <div v-if="feedbackLoading" class="py-16 text-center text-sm font-semibold text-slate-500">Loading feedback…</div>
            <div v-else-if="feedbackError" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-900">
                {{ feedbackError }}
            </div>
            <template v-else-if="feedbackDetail">
                <div class="mb-4 flex flex-wrap items-center gap-3 rounded-2xl border border-primary-100 bg-primary-50/80 px-4 py-3">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-wide text-primary-700">Overall average</p>
                        <p class="text-2xl font-black text-primary-900">{{ formatRating(feedbackDetail.admin.average_rating) }}</p>
                    </div>
                    <div class="h-10 w-px bg-primary-200" aria-hidden="true" />
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-wide text-slate-500">Rated sessions</p>
                        <p class="text-xl font-black text-slate-900">{{ feedbackDetail.admin.sessions_rated }}</p>
                    </div>
                </div>

                <p v-if="!feedbackDetail.feedback.length" class="py-12 text-center text-sm font-semibold text-slate-500">No rated sessions yet.</p>

                <div v-else class="-mx-5 overflow-x-auto px-5">
                    <table class="w-full min-w-[640px] border-separate border-spacing-0 text-left text-sm">
                        <thead>
                            <tr class="text-[10px] font-black uppercase tracking-wide text-slate-500">
                                <th class="sticky top-0 bg-white pb-2 pr-3">Date</th>
                                <th class="sticky top-0 bg-white pb-2 pr-3">Customer</th>
                                <th class="sticky top-0 bg-white pb-2 pr-3">Rating</th>
                                <th
                                    v-for="step in surveySteps"
                                    :key="step.id"
                                    class="sticky top-0 bg-white pb-2 pr-3"
                                >
                                    {{ step.question }}
                                </th>
                                <th class="sticky top-0 bg-white pb-2">Note</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in feedbackDetail.feedback"
                                :key="row.ticket_id"
                                class="border-t border-slate-100"
                            >
                                <td class="py-3 pr-3 align-top text-xs font-semibold text-slate-600 whitespace-nowrap">
                                    {{ formatDate(row.rated_at) }}
                                </td>
                                <td class="py-3 pr-3 align-top">
                                    <p class="font-bold text-slate-900">{{ row.customer_name || 'Customer' }}</p>
                                    <p v-if="row.customer_username" class="text-xs text-slate-500">@{{ row.customer_username }}</p>
                                    <p class="mt-0.5 truncate text-xs text-slate-500">{{ row.subject }}</p>
                                </td>
                                <td class="py-3 pr-3 align-top whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-primary-50 px-2 py-1 text-xs font-black text-primary-900">
                                        <span v-if="row.reaction_emoji">{{ row.reaction_emoji }}</span>
                                        {{ row.rating_score }}/10
                                    </span>
                                </td>
                                <td
                                    v-for="step in surveySteps"
                                    :key="`${row.ticket_id}-${step.id}`"
                                    class="py-3 pr-3 align-top text-xs font-semibold text-slate-700"
                                >
                                    {{ answerFor(row, step.id) }}
                                </td>
                                <td class="py-3 align-top text-xs font-medium text-slate-600">
                                    {{ row.comment || '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </AdminSlideOver>
    </AdminShell>
</template>

<script setup>
import { ref } from 'vue';
import AdminShell from '@/Layouts/AdminShell.vue';
import AdminSlideOver from '@/Components/Admin/AdminSlideOver.vue';

defineProps({
    metrics: { type: Array, default: () => [] },
    surveySteps: { type: Array, default: () => [] },
});

const feedbackOpen = ref(false);
const feedbackLoading = ref(false);
const feedbackError = ref('');
const feedbackAdmin = ref(null);
const feedbackDetail = ref(null);

function formatRating(value) {
    return value != null ? `${value}/10` : '—';
}

function formatDate(iso) {
    if (!iso) {
        return '—';
    }
    return new Date(iso).toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function answerFor(row, stepId) {
    const hit = (row.answers || []).find((a) => a.id === stepId);

    return hit?.answer || '—';
}

function closeFeedback() {
    feedbackOpen.value = false;
    feedbackAdmin.value = null;
    feedbackDetail.value = null;
    feedbackError.value = '';
}

async function openFeedback(adminMetric) {
    feedbackAdmin.value = adminMetric;
    feedbackOpen.value = true;
    feedbackLoading.value = true;
    feedbackError.value = '';
    feedbackDetail.value = null;

    try {
        const { data } = await window.axios.get(
            window.route('admin.api.customer-support.performance-feedback', { admin: adminMetric.admin_id }),
        );
        feedbackDetail.value = data;
    } catch (err) {
        feedbackError.value = err?.response?.data?.message || 'Could not load feedback for this admin.';
    } finally {
        feedbackLoading.value = false;
    }
}
</script>
