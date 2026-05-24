<template>
    <div class="flex min-h-screen flex-col items-center justify-center bg-gradient-to-b from-primary-50 to-white px-4 py-12">
        <div class="w-full max-w-md rounded-[1.75rem] border border-primary-100 bg-white p-8 shadow-lg">
            <p class="text-xs font-black uppercase tracking-[0.2em] text-primary-700">HustleSafe Support</p>
            <h1 class="mt-2 font-display text-2xl font-black text-slate-950">Rate your experience</h1>
            <p class="mt-2 text-sm font-semibold text-slate-600">{{ ticket.subject }}</p>

            <div v-if="ticket.already_rated" class="mt-8 rounded-xl bg-emerald-50 px-4 py-6 text-center">
                <p class="text-sm font-black text-emerald-900">Thank you — you already rated this chat.</p>
                <p v-if="ticket.stars" class="mt-2 text-2xl">{{ '★'.repeat(ticket.stars) }}{{ '☆'.repeat(5 - ticket.stars) }}</p>
            </div>

            <form v-else class="mt-8 space-y-6" @submit.prevent="submit">
                <div class="flex justify-center gap-2">
                    <button
                        v-for="n in 5"
                        :key="n"
                        type="button"
                        class="text-3xl transition hover:scale-110"
                        :class="stars >= n ? 'text-amber-400' : 'text-slate-200'"
                        @click="stars = n"
                    >
                        ★
                    </button>
                </div>
                <textarea
                    v-model="comment"
                    rows="3"
                    class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold focus:border-primary-400 focus:ring-2 focus:ring-primary-100"
                    placeholder="Optional comment…"
                />
                <button
                    type="submit"
                    class="w-full rounded-xl bg-primary-700 py-3 text-sm font-black uppercase text-white hover:bg-primary-800 disabled:opacity-50"
                    :disabled="!stars || processing"
                >
                    Submit rating
                </button>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    ticket: { type: Object, required: true },
    submitUrl: { type: String, required: true },
});

const page = usePage();
const stars = ref(0);
const comment = ref('');
const processing = ref(false);

function submit() {
    processing.value = true;
    router.post(props.submitUrl, { stars: stars.value, comment: comment.value }, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false;
        },
    });
}
</script>
