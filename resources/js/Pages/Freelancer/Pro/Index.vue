<template>
    <AppShell>
        <Head title="Pro membership" />

        <div class="mx-auto max-w-3xl space-y-6 pb-10">
            <BackChevronLink :href="route('account.show')" aria-label="Back to account" class="inline-flex" />

            <div
                v-if="flashSuccess"
                class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-950 ring-1 ring-emerald-100"
                role="status"
            >
                {{ flashSuccess }}
            </div>
            <div
                v-if="flashError"
                class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm font-semibold text-rose-950 ring-1 ring-rose-100"
                role="alert"
            >
                {{ flashError }}
            </div>

            <div class="rounded-[2rem] bg-gradient-to-br from-primary-800 via-slate-900 to-slate-950 px-6 py-10 text-white shadow-xl ring-1 ring-white/10 sm:px-10">
                <p class="text-xs font-bold uppercase tracking-[0.25em] text-teal-200/90">Freelancer Pro</p>
                <h1 class="font-display mt-3 text-3xl font-black tracking-tight sm:text-4xl">
                    {{ pro.subscription.is_pro ? 'Your Pro membership' : 'Upgrade to Pro' }}
                </h1>
                <p class="mt-4 max-w-xl text-base font-semibold leading-relaxed text-teal-50">
                    <template v-if="pro.subscription.is_pro">
                        Premium visibility and convenience on top of your trust tier — verification level and job value limits are unchanged.
                    </template>
                    <template v-else>
                        Accelerate legitimate growth with visibility, unlimited proposals, and faster verification reviews — without bypassing trust requirements.
                    </template>
                </p>
            </div>

            <section class="rounded-3xl border border-slate-200 bg-slate-50 p-6 ring-1 ring-slate-100">
                <h2 class="text-sm font-black uppercase tracking-wide text-slate-700">What Pro does not change</h2>
                <p class="mt-2 text-sm font-semibold leading-relaxed text-slate-600">
                    Premium is orthogonal to the trust system. Paying for Pro never bypasses verification, account age, or tier job limits.
                </p>
                <ul class="mt-4 space-y-3">
                    <li
                        v-for="(item, i) in pro.trust_exclusions"
                        :key="'ex-' + i"
                        class="rounded-2xl border border-white bg-white px-4 py-3 text-sm font-semibold text-slate-700"
                    >
                        <span class="font-black text-slate-900">{{ item.title }}.</span>
                        {{ item.description }}
                    </li>
                </ul>
            </section>

            <!-- Active Pro -->
            <template v-if="pro.subscription.is_pro">
                <section class="rounded-3xl border border-emerald-200 bg-emerald-50/80 p-6 ring-1 ring-emerald-100">
                    <p class="text-xs font-black uppercase text-emerald-800">Active Pro membership</p>
                    <p class="mt-2 text-lg font-black text-emerald-950">
                        Renews {{ formatWhen(pro.subscription.renewal_date) }}
                        <span v-if="pro.subscription.billing_cycle_label" class="font-semibold text-emerald-800">
                            · {{ pro.subscription.billing_cycle_label }}
                        </span>
                    </p>
                    <p class="mt-2 text-sm font-semibold text-emerald-900">
                        Manual renewal — auto-renew is off. You will need to pay again before your renewal date to keep Pro benefits.
                        Pro fees are non-refundable.
                    </p>
                    <div class="mt-4 flex flex-wrap gap-3 text-xs font-bold text-emerald-900">
                        <span v-if="pro.subscription.total_spent_display" class="rounded-full bg-white/80 px-3 py-1 ring-1 ring-emerald-200">
                            Total spent: {{ pro.subscription.total_spent_display }}
                        </span>
                        <span v-if="pro.subscription.started_at" class="rounded-full bg-white/80 px-3 py-1 ring-1 ring-emerald-200">
                            Member since {{ formatWhen(pro.subscription.started_at) }}
                        </span>
                    </div>
                    <button
                        type="button"
                        class="mt-5 rounded-xl border border-rose-200 bg-white px-4 py-2 text-xs font-black uppercase text-rose-700 hover:bg-rose-50"
                        @click="cancelOpen = true"
                    >
                        Cancel subscription
                    </button>
                </section>

                <section class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm ring-1 ring-slate-100">
                    <h2 class="font-display text-lg font-black text-slate-900">Your Pro benefits</h2>
                    <p class="mt-1 text-sm font-semibold text-slate-600">
                        These perks are active on your account right now.
                    </p>
                    <ul class="mt-5 space-y-4">
                        <li
                            v-for="benefit in pro.benefits"
                            :key="benefit.key"
                            class="flex gap-4 rounded-2xl border border-slate-100 bg-slate-50/60 p-4"
                        >
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-100 text-primary-800">
                                <CheckIcon class="h-5 w-5" aria-hidden="true" />
                            </span>
                            <div>
                                <p class="text-sm font-black text-slate-900">{{ benefit.title }}</p>
                                <p class="mt-1 text-sm font-semibold leading-relaxed text-slate-600">{{ benefit.description }}</p>
                            </div>
                        </li>
                    </ul>
                </section>

                <section class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm ring-1 ring-slate-100">
                    <h2 class="font-display text-lg font-black text-slate-900">Custom profile sections</h2>
                    <p class="mt-1 text-sm font-semibold text-slate-600">
                        Add testimonials, external links, and media highlights to your public profile. These appear only while Pro is active.
                    </p>
                    <form class="mt-6 space-y-6" @submit.prevent="submitProfileSections">
                        <div>
                            <p class="text-xs font-black uppercase tracking-wide text-slate-500">Testimonials</p>
                            <div v-for="(row, i) in profileSectionsForm.testimonials" :key="'t-' + i" class="mt-3 space-y-2 rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                <textarea v-model="row.quote" rows="2" class="w-full rounded-xl border-slate-200 text-sm" placeholder="Quote" />
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <input v-model="row.author" type="text" class="rounded-xl border-slate-200 text-sm" placeholder="Author name" />
                                    <input v-model="row.role" type="text" class="rounded-xl border-slate-200 text-sm" placeholder="Role / company" />
                                </div>
                                <button type="button" class="text-xs font-bold text-rose-700" @click="profileSectionsForm.testimonials.splice(i, 1)">Remove</button>
                            </div>
                            <button type="button" class="mt-2 text-xs font-black uppercase text-primary-700" @click="addTestimonial">Add testimonial</button>
                        </div>
                        <div>
                            <p class="text-xs font-black uppercase tracking-wide text-slate-500">External links</p>
                            <div v-for="(row, i) in profileSectionsForm.external_links" :key="'e-' + i" class="mt-3 grid gap-2 rounded-2xl border border-slate-100 bg-slate-50 p-4 sm:grid-cols-2">
                                <input v-model="row.label" type="text" class="rounded-xl border-slate-200 text-sm" placeholder="Label" />
                                <input v-model="row.url" type="url" class="rounded-xl border-slate-200 text-sm" placeholder="https://" />
                                <button type="button" class="text-xs font-bold text-rose-700 sm:col-span-2" @click="profileSectionsForm.external_links.splice(i, 1)">Remove</button>
                            </div>
                            <button type="button" class="mt-2 text-xs font-black uppercase text-primary-700" @click="addExternalLink">Add link</button>
                        </div>
                        <div>
                            <p class="text-xs font-black uppercase tracking-wide text-slate-500">Media links</p>
                            <div v-for="(row, i) in profileSectionsForm.media_links" :key="'m-' + i" class="mt-3 grid gap-2 rounded-2xl border border-slate-100 bg-slate-50 p-4 sm:grid-cols-2">
                                <input v-model="row.label" type="text" class="rounded-xl border-slate-200 text-sm" placeholder="Label" />
                                <input v-model="row.url" type="url" class="rounded-xl border-slate-200 text-sm" placeholder="Video or gallery URL" />
                                <button type="button" class="text-xs font-bold text-rose-700 sm:col-span-2" @click="profileSectionsForm.media_links.splice(i, 1)">Remove</button>
                            </div>
                            <button type="button" class="mt-2 text-xs font-black uppercase text-primary-700" @click="addMediaLink">Add media link</button>
                        </div>
                        <button
                            type="submit"
                            class="rounded-xl bg-primary-700 px-5 py-3 text-sm font-black uppercase tracking-wide text-white disabled:opacity-50"
                            :disabled="profileSectionsForm.processing"
                        >
                            Save profile sections
                        </button>
                    </form>
                </section>
            </template>

            <!-- Upgrade flow -->
            <template v-else>
                <section class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm ring-1 ring-slate-100">
                    <p class="text-xs font-black uppercase tracking-wide text-slate-500">Free plan usage</p>
                    <p class="mt-2 text-sm font-semibold text-slate-700">
                        {{ pro.quota.used }} of {{ pro.quota.limit }} proposals used this month
                        <span v-if="pro.quota.verification_level != null" class="text-slate-500">
                            (L{{ pro.quota.verification_level }} allowance)
                        </span>
                    </p>
                    <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100">
                        <div
                            class="h-full rounded-full transition-all"
                            :class="pro.quota.percent_used >= 90 ? 'bg-rose-500' : 'bg-primary-600'"
                            :style="{ width: `${Math.min(100, pro.quota.percent_used)}%` }"
                        />
                    </div>
                    <p v-if="pro.quota.percent_used >= 80" class="mt-2 text-xs font-bold text-amber-800">
                        You are close to your monthly submission cap. Pro removes this cap — your verification tier job limits still apply per quest.
                    </p>
                    <p v-if="pro.portfolio_limit?.max_items" class="mt-2 text-xs font-semibold text-slate-600">
                        Portfolio items: {{ pro.portfolio_limit.current_count }} / {{ pro.portfolio_limit.max_items }} (Pro = unlimited).
                    </p>
                </section>

                <nav class="flex gap-2 overflow-x-auto pb-1" aria-label="Upgrade steps">
                    <button
                        v-for="s in steps"
                        :key="s.id"
                        type="button"
                        class="shrink-0 rounded-2xl px-4 py-2 text-xs font-black uppercase tracking-wide transition"
                        :class="step === s.id ? 'bg-primary-700 text-white shadow-md' : 'border border-slate-200 bg-white text-slate-600'"
                        @click="step = s.id"
                    >
                        {{ s.id }}. {{ s.label }}
                    </button>
                </nav>

                <!-- Step 1: Benefits -->
                <section v-show="step === 1" class="space-y-6">
                    <div class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm ring-1 ring-slate-100">
                        <h2 class="font-display text-lg font-black text-slate-900">What you gain as a Pro member</h2>
                        <p class="mt-2 text-sm font-semibold leading-relaxed text-slate-600">
                            Pro is designed for freelancers who want more visibility and fewer limits. Here is exactly what changes on your account.
                        </p>
                        <ul class="mt-6 space-y-4">
                            <li
                                v-for="benefit in pro.benefits"
                                :key="benefit.key"
                                class="flex gap-4 rounded-2xl border border-primary-100/80 bg-primary-50/40 p-4"
                            >
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white text-primary-700 ring-1 ring-primary-100">
                                    <SparklesIcon class="h-5 w-5" aria-hidden="true" />
                                </span>
                                <div>
                                    <p class="text-sm font-black text-slate-900">{{ benefit.title }}</p>
                                    <p class="mt-1 text-sm font-semibold leading-relaxed text-slate-600">{{ benefit.description }}</p>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-slate-50 p-6">
                        <h3 class="text-sm font-black uppercase tracking-wide text-slate-500">Free vs Pro</h3>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                                <p class="text-xs font-black uppercase text-slate-500">Free account</p>
                                <ul class="mt-3 space-y-3">
                                    <li
                                        v-for="(item, i) in pro.free_tier_highlights"
                                        :key="'free-' + i"
                                        class="text-sm font-semibold text-slate-600"
                                    >
                                        <span class="font-black text-slate-800">{{ item.title }}.</span>
                                        {{ item.description }}
                                    </li>
                                </ul>
                            </div>
                            <div class="rounded-2xl border-2 border-amber-300 bg-amber-50/50 p-4">
                                <p class="text-xs font-black uppercase text-amber-800">Pro account</p>
                                <ul class="mt-3 space-y-2">
                                    <li v-for="benefit in pro.benefits" :key="'pro-' + benefit.key" class="text-sm font-semibold text-amber-950">
                                        ✓ {{ benefit.title }}
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button
                            type="button"
                            class="rounded-xl bg-primary-700 px-6 py-3 text-sm font-black uppercase tracking-wide text-white shadow-sm hover:bg-primary-800"
                            @click="step = 2"
                        >
                            Choose a plan
                        </button>
                    </div>
                </section>

                <!-- Step 2: Plan selection -->
                <section v-show="step === 2" class="space-y-6">
                    <div class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm ring-1 ring-slate-100">
                        <h2 class="font-display text-lg font-black text-slate-900">Select your billing cycle</h2>
                        <p class="mt-2 text-sm font-semibold text-slate-600">
                            Pick monthly for flexibility or annual for the best value. You can cancel anytime — Pro fees are non-refundable.
                        </p>

                        <div class="mt-6 grid gap-4 sm:grid-cols-2">
                            <button
                                type="button"
                                class="rounded-3xl border p-6 text-left ring-1 transition"
                                :class="selectedCycle === 'month' ? 'border-primary-400 bg-primary-50/60 ring-primary-200' : 'border-slate-200 bg-white ring-slate-100 hover:border-primary-200'"
                                @click="selectedCycle = 'month'"
                            >
                                <p class="text-xs font-black uppercase text-slate-500">Monthly</p>
                                <p class="mt-2 text-3xl font-black text-slate-900">{{ pro.pricing.monthly_display }}</p>
                                <p class="mt-2 text-sm font-semibold text-slate-600">Billed every month · cancel anytime</p>
                            </button>
                            <button
                                type="button"
                                class="rounded-3xl border-2 p-6 text-left transition"
                                :class="selectedCycle === 'year' ? 'border-amber-400 bg-amber-50/60 ring-2 ring-amber-200' : 'border-amber-200 bg-amber-50/30 hover:border-amber-300'"
                                @click="selectedCycle = 'year'"
                            >
                                <p class="text-xs font-black uppercase text-amber-800">Annual · save {{ pro.pricing.annual_savings_percent }}%</p>
                                <p class="mt-2 text-3xl font-black text-slate-900">{{ pro.pricing.annual_display }}</p>
                                <p class="mt-2 text-sm font-semibold text-slate-600">12 months of Pro · best value</p>
                            </button>
                        </div>
                    </div>

                    <div class="flex flex-wrap justify-between gap-3">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-black uppercase text-slate-700 hover:bg-slate-50"
                            @click="step = 1"
                        >
                            Back
                        </button>
                        <button
                            type="button"
                            class="rounded-xl bg-primary-700 px-6 py-3 text-sm font-black uppercase tracking-wide text-white shadow-sm hover:bg-primary-800"
                            @click="step = 3"
                        >
                            Review & pay
                        </button>
                    </div>
                </section>

                <!-- Step 3: Payment -->
                <section v-show="step === 3" class="space-y-6">
                    <div class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm ring-1 ring-slate-100">
                        <h2 class="font-display text-lg font-black text-slate-900">Review and pay securely</h2>
                        <p class="mt-2 text-sm font-semibold text-slate-600">
                            You are about to upgrade to Pro. Payment is processed by Paystack — we never store your card details.
                        </p>

                        <dl class="mt-6 space-y-3 rounded-2xl border border-slate-100 bg-slate-50/80 p-5">
                            <div class="flex items-center justify-between gap-4">
                                <dt class="text-sm font-semibold text-slate-600">Plan</dt>
                                <dd class="text-sm font-black text-slate-900">
                                    {{ selectedCycle === 'year' ? 'Annual Pro' : 'Monthly Pro' }}
                                </dd>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                                <dt class="text-sm font-semibold text-slate-600">Amount due today</dt>
                                <dd class="text-xl font-black text-primary-800">
                                    {{ selectedCycle === 'year' ? pro.pricing.annual_display : pro.pricing.monthly_display }}
                                </dd>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                                <dt class="text-sm font-semibold text-slate-600">Access period</dt>
                                <dd class="text-sm font-black text-slate-900">
                                    {{ selectedCycle === 'year' ? '12 months from payment' : '1 month from payment' }}
                                </dd>
                            </div>
                        </dl>

                        <div class="mt-6">
                            <p class="text-xs font-black uppercase tracking-wide text-slate-500">What happens after you pay</p>
                            <ol class="mt-3 space-y-2">
                                <li
                                    v-for="(line, i) in pro.payment_steps"
                                    :key="i"
                                    class="flex gap-3 text-sm font-semibold text-slate-700"
                                >
                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary-100 text-xs font-black text-primary-800">
                                        {{ i + 1 }}
                                    </span>
                                    {{ line }}
                                </li>
                            </ol>
                        </div>

                        <p class="mt-6 rounded-2xl border border-amber-100 bg-amber-50/80 px-4 py-3 text-xs font-semibold leading-relaxed text-amber-950">
                            By continuing, you agree that Pro subscription fees are non-refundable. Cancellation stops future renewals but does not refund the current period.
                        </p>

                        <button
                            type="button"
                            class="mt-6 flex w-full items-center justify-center gap-2 rounded-xl bg-primary-700 px-4 py-4 text-sm font-black uppercase tracking-wide text-white shadow-lg shadow-primary-900/20 hover:bg-primary-800 disabled:opacity-50"
                            :disabled="upgradeForm.processing"
                            @click="submitUpgrade"
                        >
                            <CreditCardIcon class="h-5 w-5" aria-hidden="true" />
                            {{ upgradeForm.processing ? 'Redirecting to Paystack…' : 'Continue to secure payment' }}
                        </button>
                    </div>

                    <div class="flex justify-start">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-black uppercase text-slate-700 hover:bg-slate-50"
                            @click="step = 2"
                        >
                            Back
                        </button>
                    </div>
                </section>
            </template>

            <div v-if="cancelOpen" class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 p-4 sm:items-center">
                <div class="w-full max-w-md rounded-3xl bg-white p-6 shadow-xl">
                    <h2 class="text-lg font-black text-slate-900">Cancel Pro?</h2>
                    <p class="mt-2 text-sm font-semibold text-slate-600">
                        Cancellation takes effect immediately. You revert to the free tier and lose Pro visibility benefits.
                        Pro fees are non-refundable.
                    </p>
                    <textarea
                        v-model="cancelForm.reason"
                        rows="3"
                        class="mt-4 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500"
                        placeholder="Reason (optional)"
                    />
                    <div class="mt-4 flex gap-2">
                        <button
                            type="button"
                            class="flex-1 rounded-xl bg-rose-600 py-2.5 text-xs font-black uppercase text-white disabled:opacity-50"
                            :disabled="cancelForm.processing"
                            @click="submitCancel"
                        >
                            Confirm cancel
                        </button>
                        <button
                            type="button"
                            class="flex-1 rounded-xl border border-slate-200 py-2.5 text-xs font-black uppercase text-slate-700"
                            @click="cancelOpen = false"
                        >
                            Keep Pro
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppShell>
</template>

<script setup>
import AppShell from '@/Layouts/AppShell.vue';
import BackChevronLink from '@/Components/Ui/BackChevronLink.vue';
import { CheckIcon, CreditCardIcon, SparklesIcon } from '@heroicons/vue/24/solid';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    pro: { type: Object, required: true },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success ?? '');
const flashError = computed(() => page.props.flash?.error ?? '');

const step = ref(1);
const selectedCycle = ref('month');
const cancelOpen = ref(false);

const steps = [
    { id: 1, label: 'Benefits' },
    { id: 2, label: 'Choose plan' },
    { id: 3, label: 'Pay' },
];

const upgradeForm = useForm({ billing_cycle: 'month' });
const cancelForm = useForm({ reason: '' });

const profileSectionsForm = useForm({
    testimonials: props.pro.pro_profile_sections?.testimonials?.length
        ? [...props.pro.pro_profile_sections.testimonials]
        : [],
    external_links: props.pro.pro_profile_sections?.external_links?.length
        ? [...props.pro.pro_profile_sections.external_links]
        : [],
    media_links: props.pro.pro_profile_sections?.media_links?.length
        ? [...props.pro.pro_profile_sections.media_links]
        : [],
});

function addTestimonial() {
    profileSectionsForm.testimonials.push({ quote: '', author: '', role: '' });
}

function addExternalLink() {
    profileSectionsForm.external_links.push({ label: '', url: '' });
}

function addMediaLink() {
    profileSectionsForm.media_links.push({ label: '', url: '' });
}

function submitProfileSections() {
    profileSectionsForm.patch(route('freelancer.pro.profile-sections'), { preserveScroll: true });
}

function formatWhen(iso) {
    if (!iso) {
        return '—';
    }
    try {
        return new Date(iso).toLocaleDateString('en-NG', { dateStyle: 'medium' });
    } catch {
        return '—';
    }
}

function submitUpgrade() {
    upgradeForm.billing_cycle = selectedCycle.value;
    upgradeForm.post(route('freelancer.pro.upgrade'));
}

function submitCancel() {
    cancelForm.post(route('freelancer.pro.cancel'), {
        onSuccess: () => {
            cancelOpen.value = false;
        },
    });
}
</script>
