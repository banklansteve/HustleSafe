<template>
    <section
        id="boost-quest"
        class="rounded-xl border border-amber-200/90 bg-gradient-to-br from-amber-50/95 via-white to-orange-50/80 p-5 shadow-md ring-1 ring-amber-100 sm:p-6"
    >
        <div
            v-if="!upsell.has_active_boost && upsell.show_panel && !tipDismissed"
            class="mb-4 rounded-xl border border-amber-200 bg-amber-100/50 px-4 py-3"
        >
            <div class="flex flex-wrap items-start justify-between gap-2">
                <p class="text-xs font-semibold leading-relaxed text-amber-950">
                    New quest tip: optional boost packages put you in front of matching freelancers faster.
                </p>
                <button
                    type="button"
                    class="shrink-0 text-[10px] font-black uppercase tracking-wide text-amber-900 underline"
                    @click="dismissTip"
                >
                    Dismiss
                </button>
            </div>
        </div>

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-amber-900">
                    Boost visibility
                </p>
                <h2 class="font-display mt-1 text-lg font-bold text-slate-900">
                    {{ upsell.has_active_boost ? 'Your quest is boosted' : 'Reach more matching pros' }}
                </h2>
                <p class="mt-2 text-sm font-semibold leading-relaxed text-slate-700">
                    <template v-if="upsell.has_active_boost">
                        Freelancers see a Boosted badge and your quest ranks higher in Explore while active.
                    </template>
                    <template v-else>
                        Optional paid boost — your quest appears higher in search and Explore so the right freelancers find you sooner.
                    </template>
                </p>
            </div>
        </div>

        <div
            v-if="!upsell.has_active_boost && upsell.remaining_listing_label"
            class="mt-4 rounded-xl border border-sky-100 bg-sky-50/80 px-4 py-3 text-xs font-semibold text-sky-950"
        >
            Listing accepts proposals for {{ upsell.remaining_listing_label }}.
            Boost duration cannot extend past that deadline.
        </div>

        <div v-if="upsell.has_active_boost && quest.featured_boost" class="mt-4 flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center rounded-full bg-amber-500 px-3 py-1 text-[10px] font-black uppercase tracking-wide text-white shadow-sm">
                {{ quest.featured_boost.badge_label || 'Boosted' }}
            </span>
            <span class="text-xs font-semibold text-slate-600">
                Active until {{ formatWhen(quest.featured_boost.expires_at) }}
            </span>
        </div>

        <form v-else-if="upsell.can_purchase" class="mt-5 space-y-4" @submit.prevent="submitCheckout">
            <fieldset class="space-y-3">
                <legend class="sr-only">Choose boost duration</legend>
                <label
                    v-for="tier in upsell.tiers"
                    :key="tier.value"
                    class="flex cursor-pointer items-start gap-3 rounded-xl border px-4 py-3 transition"
                    :class="[
                        !tier.available ? 'cursor-not-allowed border-slate-100 bg-slate-50/80 opacity-70' : '',
                        tier.available && selectedTier === tier.value ? 'border-amber-400 bg-amber-50/60 ring-2 ring-amber-200' : '',
                        tier.available && selectedTier !== tier.value ? 'border-slate-200 bg-white hover:border-amber-200 hover:bg-amber-50/30' : '',
                    ]"
                >
                    <input
                        v-model="selectedTier"
                        class="mt-1 h-4 w-4 border-slate-300 text-amber-600 focus:ring-amber-500"
                        type="radio"
                        name="boost_tier"
                        :value="tier.value"
                        :disabled="!tier.available"
                    />
                    <span class="min-w-0 flex-1">
                        <span class="flex flex-wrap items-center justify-between gap-2">
                            <span class="text-sm font-bold text-slate-900">{{ tier.label }}</span>
                            <span class="text-sm font-black text-amber-900">{{ tier.price_display }}</span>
                        </span>
                        <span v-if="tier.available" class="mt-1 block text-[11px] font-semibold text-slate-600">
                            Higher ranking until {{ formatWhen(tier.effective_ends_at) }}
                        </span>
                        <span v-else class="mt-1 block text-[11px] font-semibold text-rose-800">
                            {{ tier.unavailable_reason }}
                        </span>
                    </span>
                </label>
            </fieldset>

            <InputError :message="checkoutForm.errors.tier" />

            <div class="rounded-xl border border-slate-100 bg-white/90 px-4 py-3 text-xs font-semibold leading-relaxed text-slate-600">
                Pay with Paystack. Boost starts immediately after payment is confirmed — no admin approval needed.
                You'll receive email confirmation and matching freelancers will see a Boosted badge.
            </div>

            <button
                type="submit"
                class="inline-flex w-full items-center justify-center rounded-full bg-amber-600 px-5 py-3 text-xs font-black uppercase tracking-wide text-white shadow-sm hover:bg-amber-700 disabled:opacity-60 sm:w-auto"
                :disabled="checkoutForm.processing || !selectedTier || !selectedTierAvailable"
            >
                {{ checkoutForm.processing ? 'Redirecting…' : `Pay ${selectedTierPrice}` }}
            </button>
        </form>

        <p v-else-if="!upsell.has_active_boost" class="mt-4 text-sm font-semibold text-slate-600">
            Boosting is not available for this quest right now.
        </p>
    </section>
</template>

<script setup>
import InputError from '@/Components/InputError.vue';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    upsell: { type: Object, required: true },
    quest: { type: Object, required: true },
    checkoutUrl: { type: String, required: true },
    dismissUrl: { type: String, default: null },
});

const tipDismissed = ref(!props.upsell.show_panel);

const selectedTier = ref(props.upsell.available_tiers?.[0]?.value ?? '');

watch(
    () => props.upsell.available_tiers,
    (tiers) => {
        if (!tiers?.some((t) => t.value === selectedTier.value)) {
            selectedTier.value = tiers?.[0]?.value ?? '';
        }
    },
    { immediate: true },
);

const checkoutForm = useForm({ tier: '' });

const selectedTierRow = computed(() => props.upsell.tiers?.find((t) => t.value === selectedTier.value) ?? null);
const selectedTierAvailable = computed(() => Boolean(selectedTierRow.value?.available));
const selectedTierPrice = computed(() => selectedTierRow.value?.price_display ?? '');

function formatWhen(iso) {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
    } catch {
        return iso;
    }
}

function submitCheckout() {
    checkoutForm.tier = selectedTier.value;
    checkoutForm.post(props.checkoutUrl, { preserveScroll: true });
}

function dismissTip() {
    tipDismissed.value = true;
    if (props.dismissUrl) {
        router.post(props.dismissUrl, {}, { preserveScroll: true, preserveState: true });
    }
}
</script>
