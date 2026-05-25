<template>
    <AppShell>
        <Head :title="proposal_edit?.offer_id ? `Edit proposal · ${quest.title}` : `Proposal · ${quest.title}`" />

        <div class="mx-auto max-w-4xl space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <BackChevronLink :href="route('quests.show', quest.route_key)" aria-label="Back to quest" />
                <span class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-black uppercase tracking-wide text-slate-600">
                    One proposal per quest
                </span>
            </div>

            <section class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-7">
                <p class="text-[10px] font-black uppercase tracking-wide text-primary-800">You are proposing on</p>
                <h1 class="font-display mt-1 text-2xl font-black text-slate-900 sm:text-3xl">
                    {{ quest.title }}
                </h1>
                <div class="mt-3 flex flex-wrap gap-2 text-xs font-semibold text-slate-600">
                    <span v-if="quest.category" class="rounded-full bg-slate-50 px-3 py-1 ring-1 ring-slate-100">
                        {{ quest.category.parent_name ? `${quest.category.parent_name} · ` : '' }}{{ quest.category.name }}
                    </span>
                    <span class="rounded-full bg-slate-50 px-3 py-1 ring-1 ring-slate-100">
                        {{ [quest.location.city, quest.location.lga, quest.location.state].filter(Boolean).join(' · ') }}
                    </span>
                    <span class="rounded-full bg-primary-50 px-3 py-1 font-bold text-primary-900 ring-1 ring-primary-100">
                        Budget {{ formatBudget(quest.budget_minor) }}
                    </span>
                    <span v-if="quest.estimated_completion_days" class="rounded-full bg-slate-50 px-3 py-1 ring-1 ring-slate-100">
                        Client timeline ~ {{ quest.estimated_completion_days }} days
                    </span>
                </div>
            </section>

            <section
                v-if="submitFeedback"
                ref="errorBannerRef"
                class="rounded-xl border border-rose-300 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-950 ring-1 ring-rose-200"
                role="alert"
            >
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-rose-800">Could not save proposal</p>
                <p class="mt-2 leading-relaxed">{{ submitFeedback }}</p>
            </section>

            <section v-if="hintLine" class="rounded-xl border border-sky-200 bg-sky-50/90 px-4 py-3 text-sm font-semibold text-sky-950 ring-1 ring-sky-100">
                {{ hintLine }}
            </section>

            <section
                v-if="pricingHintsBlock"
                class="rounded-xl border border-violet-200 bg-violet-50/90 px-4 py-3 text-sm font-semibold text-violet-950 ring-1 ring-violet-100"
            >
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-violet-900">Suggested pricing (from history)</p>
                <p v-if="pricing_hints.summary" class="mt-2 text-xs font-semibold leading-relaxed text-violet-950/95">
                    {{ pricing_hints.summary }}
                </p>
                <ul class="mt-2 space-y-1 text-xs font-bold text-violet-950">
                    <li v-if="pricing_hints.professional_fee_ngn">
                        Professional fee ≈ {{ formatNgn(pricing_hints.professional_fee_ngn) }}
                    </li>
                    <li v-if="pricing_hints.materials_total_ngn">
                        Typical materials / parts subtotal ≈ {{ formatNgn(pricing_hints.materials_total_ngn) }}
                    </li>
                    <li v-if="pricing_hints.travel_cost_ngn">
                        Typical travel / site line ≈ {{ formatNgn(pricing_hints.travel_cost_ngn) }}
                    </li>
                </ul>
                <p class="mt-2 text-[11px] font-semibold leading-relaxed text-violet-900/90">
                    These averages are hints only — adjust for this brief, risk, and your margin.
                </p>
                <button
                    type="button"
                    class="mt-3 inline-flex items-center rounded-full bg-violet-700 px-4 py-2 text-xs font-black uppercase tracking-wide text-white shadow-sm hover:bg-violet-800"
                    @click="applyPricingHints"
                >
                    Apply suggestions to form
                </button>
            </section>

            <section
                v-if="!proposal_edit?.offer_id"
                class="rounded-2xl border border-teal-200/90 bg-gradient-to-r from-teal-50 via-white to-emerald-50 px-4 py-4 text-sm font-semibold text-slate-800 shadow-sm ring-1 ring-teal-100 sm:px-5"
            >
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-teal-800">Payouts, escrow & disputes</p>
                <p class="mt-2 leading-relaxed text-slate-700">
                    Client funds stay in escrow until they mark the job completed. Start billable work only after escrow is funded and confirmed — never accept off-platform prepayment. If something goes wrong after funding, both sides use the same dispute tools:
                    <Link :href="route('disputes.index')" class="font-black text-teal-900 underline decoration-teal-400 underline-offset-2">Disputes centre</Link>,
                    <a href="/docs/dispute-workflow.md" target="_blank" rel="noopener noreferrer" class="font-black text-teal-900 underline decoration-teal-400 underline-offset-2">workflow doc</a>,
                    and our
                    <a :href="route('legal.terms')" target="_blank" rel="noopener noreferrer" class="font-black text-teal-900 underline decoration-teal-400 underline-offset-2">Terms</a>.
                </p>
            </section>

            <section class="rounded-2xl border border-amber-200/90 bg-amber-50/80 px-4 py-3 text-xs font-semibold text-amber-950 ring-1 ring-amber-100 sm:px-5">
                <p class="font-black uppercase tracking-wide text-amber-900">On-platform only</p>
                <p class="mt-1 leading-relaxed">
                    Do not include phone numbers, email, or social handles. Messages are filtered — repeated attempts to move off-platform may result in a ban.
                </p>
            </section>

            <form class="space-y-8" @submit.prevent="submit">
                <section class="space-y-2 rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6">
                    <h2 class="font-display text-sm font-black uppercase tracking-wide text-slate-500">
                        Narrative
                    </h2>
                    <div class="space-y-2">
                        <InputLabel value="Executive pitch" />
                        <textarea
                            v-model="form.pitch"
                            rows="4"
                            class="w-full rounded-xl border-slate-200 text-sm font-medium shadow-sm focus:border-primary-500 focus:ring-primary-500"
                            placeholder="Why you, why now, and the outcome you will own."
                        />
                        <InputError :message="form.errors.pitch" />
                    </div>
                    <div class="space-y-2">
                        <InputLabel value="Scope, deliverables & approach" />
                        <textarea
                            v-model="form.scope_detail"
                            rows="7"
                            class="w-full rounded-xl border-slate-200 text-sm font-medium shadow-sm focus:border-primary-500 focus:ring-primary-500"
                            placeholder="Break down milestones, assumptions, exclusions, and how you de-risk delivery."
                        />
                        <InputError :message="form.errors.scope_detail" />
                    </div>
                    <div class="space-y-2">
                        <InputLabel value="Warranty / assurance (optional)" />
                        <textarea
                            v-model="form.warranty_terms"
                            rows="2"
                            class="w-full rounded-xl border-slate-200 text-sm font-medium shadow-sm focus:border-primary-500 focus:ring-primary-500"
                            placeholder="e.g. 14-day defect fix window on implemented work."
                        />
                        <InputError :message="form.errors.warranty_terms" />
                    </div>
                </section>

                <section class="space-y-2 rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6">
                    <h2 class="font-display text-sm font-black uppercase tracking-wide text-slate-500">
                        Timeline
                    </h2>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <div class="space-y-2">
                            <InputLabel for="psd" value="Planned start" />
                            <PremiumDatePicker id="psd" v-model="form.planned_start_date" placeholder="Start date" />
                            <InputError :message="form.errors.planned_start_date" />
                        </div>
                        <div class="space-y-2">
                            <InputLabel for="pfd" value="Planned finish" />
                            <PremiumDatePicker
                                id="pfd"
                                v-model="form.planned_finish_date"
                                placeholder="Finish date"
                                :min="form.planned_start_date || ''"
                            />
                            <InputError :message="form.errors.planned_finish_date" />
                        </div>
                        <div class="space-y-2 sm:col-span-2">
                            <InputLabel for="eddur" value="Estimated duration (days, optional)" />
                            <TextInput
                                id="eddur"
                                v-model.number="form.estimated_duration_days"
                                type="number"
                                min="1"
                                max="730"
                                class="w-full max-w-xs"
                                placeholder="Auto from dates if left blank"
                            />
                            <p class="text-xs font-semibold text-slate-500">If empty, we derive duration from your planned start and finish.</p>
                            <InputError :message="form.errors.estimated_duration_days" />
                        </div>
                    </div>
                </section>

                <section class="space-y-3 rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6">
                    <h2 class="font-display text-sm font-black uppercase tracking-wide text-slate-500">
                        Corrections & reporting
                    </h2>
                    <label class="flex cursor-pointer items-start gap-3 text-sm font-semibold text-slate-800">
                        <input v-model="form.corrections_included" type="checkbox" class="mt-1 rounded border-slate-300 text-primary-600 focus:ring-primary-500" />
                        <span>Include correction / redo rounds in this quote</span>
                    </label>
                    <div v-if="form.corrections_included" class="space-y-2">
                        <InputLabel for="crc" value="How many rounds?" />
                        <TextInput id="crc" v-model.number="form.corrections_rounds" type="number" min="1" max="50" class="w-full max-w-xs" />
                        <InputError :message="form.errors.corrections_rounds" />
                    </div>
                    <div class="space-y-2">
                        <InputLabel value="How often you will report progress" />
                        <UiSelect
                            v-model="form.progress_report_frequency"
                            class="w-full max-w-md"
                            :options="progressReportOptions"
                            placeholder="Select…"
                            :invalid="!!form.errors.progress_report_frequency"
                        />
                        <InputError :message="form.errors.progress_report_frequency" />
                    </div>
                </section>

                <section class="space-y-2 rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h2 class="font-display text-sm font-black uppercase tracking-wide text-slate-500">
                                Materials & parts
                                <span class="ml-1.5 text-[10px] font-bold normal-case tracking-normal text-slate-400">(optional)</span>
                            </h2>
                            <p class="mt-1 text-xs font-semibold text-slate-500">
                                Add lines only if this quote includes physical parts, licences, or pass-through costs.
                            </p>
                        </div>
                        <button
                            type="button"
                            class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-[11px] font-black uppercase tracking-wide text-slate-800 hover:border-primary-200"
                            @click="addMaterialRow"
                        >
                            + Add line
                        </button>
                    </div>
                    <p
                        v-if="!form.materials.length"
                        class="rounded-xl border border-dashed border-slate-200 bg-slate-50/80 px-4 py-3 text-xs font-semibold text-slate-600"
                    >
                        No materials added — your quote can be professional fee, travel, and taxes only.
                    </p>
                    <div v-else class="space-y-2">
                        <div
                            v-for="(row, idx) in form.materials"
                            :key="idx"
                            class="flex flex-wrap items-end gap-2 rounded-xl border border-slate-100 bg-slate-50/60 p-3 ring-1 ring-slate-100"
                        >
                            <div class="min-w-[7rem] flex-1 space-y-1">
                                <InputLabel :value="`Item ${idx + 1}`" />
                                <TextInput v-model="row.label" type="text" class="w-full" placeholder="e.g. CDN bundle" />
                                <InputError :message="form.errors[`materials.${idx}.label`]" />
                            </div>
                            <div class="w-24 space-y-1">
                                <InputLabel value="Qty" />
                                <TextInput v-model="row.quantity" type="text" class="w-full" placeholder="1" />
                                <InputError :message="form.errors[`materials.${idx}.quantity`]" />
                            </div>
                            <div class="w-32 space-y-1">
                                <InputLabel value="Unit (₦)" />
                                <TextInput v-model.number="row.unit_price_ngn" type="number" min="0" step="1" class="w-full" />
                                <InputError :message="form.errors[`materials.${idx}.unit_price_ngn`]" />
                            </div>
                            <div class="w-36 space-y-1">
                                <InputLabel value="Line total" />
                                <div
                                    class="flex w-full items-center rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-black text-slate-900 shadow-inner ring-1 ring-slate-100"
                                >
                                    {{ formatNgn(materialLineNgn(row)) }}
                                </div>
                            </div>
                            <button
                                type="button"
                                class="mb-1 rounded-full p-2 text-rose-600 hover:bg-rose-50"
                                aria-label="Remove row"
                                @click="removeMaterialRow(idx)"
                            >
                                ✕
                            </button>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-primary-100 bg-primary-50/50 px-4 py-3 ring-1 ring-primary-100/80">
                        <span class="text-xs font-black uppercase tracking-wide text-primary-900">Materials / parts subtotal</span>
                        <span class="text-base font-black text-primary-950">{{ formatNgn(materialsSubtotalNgn) }}</span>
                    </div>
                    <InputError :message="form.errors.materials" />
                </section>

                <section class="space-y-2 rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6">
                    <h2 class="font-display text-sm font-black uppercase tracking-wide text-slate-500">
                        Fees & taxes (₦)
                    </h2>
                    <p class="text-xs font-semibold text-slate-600">
                        Totals are calculated in kobo on the server to match your quote exactly. VAT (when enabled) is
                        <span class="font-black text-primary-800">{{ vat_preset_percent }}%</span>
                        of professional fee + materials subtotal + travel. Withholding tax uses the same base.
                    </p>
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-100 bg-slate-50/80 px-4 py-3 text-sm font-semibold text-slate-800 ring-1 ring-slate-100">
                        <input v-model="form.pricing.vat_applies" type="checkbox" class="mt-0.5 rounded border-slate-300 text-primary-600 focus:ring-primary-500" />
                        <span>Apply VAT to this proposal</span>
                    </label>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <div class="space-y-2">
                            <InputLabel value="Professional fee" />
                            <TextInput v-model.number="form.pricing.professional_fee_ngn" type="number" min="0" step="1" class="w-full" />
                            <InputError :message="form.errors['pricing.professional_fee_ngn']" />
                        </div>
                        <div class="space-y-2">
                            <InputLabel value="VAT (computed)" />
                            <div
                                class="flex w-full items-center rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-black text-slate-800 shadow-inner ring-1 ring-slate-100"
                            >
                                <template v-if="form.pricing.vat_applies">
                                    {{ formatNgn(Math.round(breakdown.vatMinor / 100)) }} ({{ vat_preset_percent }}%)
                                </template>
                                <template v-else>₦0 — not applied</template>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <InputLabel value="Withholding tax (%)" />
                            <TextInput
                                v-model.number="form.pricing.withholding_tax_percent"
                                type="number"
                                min="0"
                                max="100"
                                step="0.1"
                                class="w-full"
                            />
                            <p class="text-xs font-semibold text-slate-600">≈ {{ formatNgn(Math.round(breakdown.whtMinor / 100)) }}</p>
                            <InputError :message="form.errors['pricing.withholding_tax_percent']" />
                        </div>
                        <div class="space-y-2 sm:col-span-2">
                            <InputLabel value="Travel / site visits (₦)" />
                            <TextInput v-model.number="form.pricing.travel_cost_ngn" type="number" min="0" step="1" class="w-full max-w-xs" />
                            <InputError :message="form.errors['pricing.travel_cost_ngn']" />
                        </div>
                        <div class="space-y-2">
                            <InputLabel value="Stamp duty" />
                            <TextInput v-model.number="form.pricing.stamp_duty_ngn" type="number" min="0" step="1" class="w-full" />
                        </div>
                        <div class="space-y-2">
                            <InputLabel :value="`Platform fee (${platformFeePercent}% of subtotal)`" />
                            <TextInput v-model.number="form.pricing.platform_fee_ngn" type="number" min="0" step="1" class="w-full bg-slate-50" readonly />
                            <p class="text-xs font-semibold text-slate-600">
                                Auto-calculated from professional fee + materials + travel. Rate is set in Super Admin → Platform settings → Financial & Escrow.
                            </p>
                        </div>
                        <div class="space-y-2">
                            <InputLabel value="Discount" />
                            <TextInput v-model.number="form.pricing.discount_ngn" type="number" min="0" step="1" class="w-full" />
                        </div>
                    </div>
                    <div class="space-y-2">
                        <InputLabel value="Grand total (auto from breakdown)" />
                        <div
                            class="flex w-full items-center rounded-xl border border-primary-200 bg-primary-50/80 px-4 py-3 text-lg font-black text-primary-950 shadow-inner ring-1 ring-primary-100"
                        >
                            {{ formatNgn(computedGrandNgn) }}
                        </div>
                        <p class="text-xs font-semibold text-slate-600">
                            Subtotal before VAT/WHT: {{ formatNgn(Math.round(breakdown.baseMinor / 100)) }} · Updates when you change any fee or material line.
                        </p>
                        <InputError :message="form.errors['pricing.grand_total_ngn']" />
                    </div>
                    <InputError :message="form.errors.proposal" />
                    <InputError :message="form.errors.workspace" />
                </section>

                <section v-if="!proposal_edit?.offer_id" class="space-y-4 rounded-2xl border border-slate-200/90 bg-gradient-to-br from-slate-50 to-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6">
                    <h2 class="font-display text-sm font-black uppercase tracking-wide text-slate-500">
                        Agreement
                    </h2>
                    <p class="text-xs font-semibold leading-relaxed text-slate-600">
                        Your proposal, pricing snapshot, and this acknowledgement are stored for audit and appear on PDF exports.
                    </p>
                    <details class="group rounded-2xl border border-slate-200 bg-white shadow-sm ring-1 ring-slate-100">
                        <summary
                            class="cursor-pointer list-none px-4 py-3 text-sm font-bold text-slate-900 marker:hidden [&::-webkit-details-marker]:hidden"
                        >
                            <span class="flex items-center justify-between gap-2">
                                <span>Preview terms in-page</span>
                                <span class="text-xs font-black uppercase tracking-wide text-primary-700 group-open:hidden">Open</span>
                                <span class="hidden text-xs font-black uppercase tracking-wide text-primary-700 group-open:inline">Close</span>
                            </span>
                        </summary>
                        <div class="border-t border-slate-100 p-3">
                            <iframe
                                title="Terms of Service"
                                class="h-64 w-full rounded-xl border border-slate-200 bg-white sm:h-80"
                                :src="termsFrameSrc"
                            />
                        </div>
                    </details>
                    <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-emerald-200/90 bg-emerald-50/60 px-4 py-3 text-sm font-semibold text-emerald-950 ring-1 ring-emerald-100">
                        <input v-model="form.accepted_terms" type="checkbox" class="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                        <span>
                            I agree to the
                            <a :href="route('legal.terms')" target="_blank" rel="noopener noreferrer" class="font-black text-emerald-800 underline decoration-emerald-400 underline-offset-2">
                                Terms of Service
                            </a>
                            and
                            <a :href="route('legal.privacy')" target="_blank" rel="noopener noreferrer" class="font-black text-emerald-800 underline decoration-emerald-400 underline-offset-2">
                                Privacy Policy
                            </a>
                            . I understand quotes must stay on-platform unless HustleSafe allows otherwise.
                        </span>
                    </label>
                    <InputError :message="form.errors.accepted_terms" />
                </section>

                <section v-else class="space-y-3 rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6">
                    <h2 class="font-display text-sm font-black uppercase tracking-wide text-slate-500">
                        Save changes
                    </h2>
                    <p class="text-xs font-semibold text-slate-600">
                        The client will be notified and can review the updated quote. Keep everything accurate and on-platform.
                    </p>
                    <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-primary-200/90 bg-primary-50/60 px-4 py-3 text-sm font-semibold text-primary-950 ring-1 ring-primary-100">
                        <input v-model="form.confirm_revision" type="checkbox" class="mt-1 rounded border-slate-300 text-primary-600 focus:ring-primary-500" />
                        <span>I confirm this revision is accurate and I want to notify the client.</span>
                    </label>
                    <InputError :message="form.errors.confirm_revision" />
                </section>

                <div class="flex flex-wrap gap-3">
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center rounded-full bg-primary-600 px-8 py-3 text-sm font-black text-white shadow-lg shadow-primary-900/15 hover:bg-primary-700 disabled:opacity-50"
                        :disabled="form.processing || (!proposal_edit?.offer_id && !form.accepted_terms) || (proposal_edit?.offer_id && !form.confirm_revision)"
                    >
                        <ReLoader4Line v-if="form.processing" class="mr-2 h-5 w-5 shrink-0 animate-spin" aria-hidden="true" />
                        {{ proposal_edit?.offer_id ? 'Save changes' : 'Submit proposal' }}
                    </button>
                    <Link
                        :href="route('quests.show', quest.route_key)"
                        class="rounded-full border border-slate-200 px-8 py-3 text-sm font-bold text-slate-800 hover:bg-slate-50"
                    >
                        Cancel
                    </Link>
                </div>
            </form>
        </div>
    </AppShell>
</template>

<script setup>
import BackChevronLink from '@/Components/Ui/BackChevronLink.vue';
import PremiumDatePicker from '@/Components/Ui/PremiumDatePicker.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import UiSelect from '@/Components/Ui/UiSelect.vue';
import AppShell from '@/Layouts/AppShell.vue';
import { ReLoader4Line } from '@kalimahapps/vue-icons/re';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';

const errorBannerRef = ref(null);
const submitFeedback = ref('');

const progressReportOptions = [
    { value: '', label: 'Select…' },
    { value: 'daily', label: 'Daily' },
    { value: 'twice_weekly', label: 'Twice weekly' },
    { value: 'weekly', label: 'Weekly' },
    { value: 'biweekly', label: 'Bi-weekly' },
    { value: 'milestone_based', label: 'At milestones only' },
    { value: 'on_request', label: 'On request' },
];

const props = defineProps({
    quest: { type: Object, required: true },
    workspace: { type: Object, default: () => ({}) },
    market_hints: { type: Object, default: () => ({}) },
    pricing_hints: {
        type: Object,
        default: () => ({
            sample_size: 0,
            scope: 'none',
            professional_fee_ngn: null,
            materials_total_ngn: null,
            travel_cost_ngn: null,
            summary: null,
        }),
    },
    vat_preset_percent: { type: Number, default: 7.5 },
    platform_fee_percent: { type: Number, default: 12 },
    proposal_edit: { type: Object, default: null },
});

const isEditMode = computed(() => Boolean(props.proposal_edit?.offer_id));

function defaultPlannedStart() {
    const d = new Date();

    return d.toISOString().slice(0, 10);
}

function defaultPlannedFinish() {
    const d = new Date();
    const days = Number(props.quest.estimated_completion_days) || 14;
    d.setDate(d.getDate() + days);

    return d.toISOString().slice(0, 10);
}

function buildInitialForm() {
    const e = props.proposal_edit;
    if (e?.offer_id) {
        return {
            pitch: e.pitch ?? '',
            scope_detail: e.scope_detail ?? '',
            warranty_terms: e.warranty_terms ?? '',
            planned_start_date: e.planned_start_date || defaultPlannedStart(),
            planned_finish_date: e.planned_finish_date || defaultPlannedFinish(),
            estimated_duration_days: e.estimated_duration_days ?? null,
            corrections_included: Boolean(e.corrections_included),
            corrections_rounds: e.corrections_rounds ?? 2,
            progress_report_frequency: e.progress_report_frequency || 'weekly',
            materials: Array.isArray(e.materials) && e.materials.length ? e.materials : [],
            pricing: {
                professional_fee_ngn: e.pricing?.professional_fee_ngn ?? 0,
                vat_applies: e.pricing?.vat_applies !== false,
                withholding_tax_percent: e.pricing?.withholding_tax_percent ?? 0,
                travel_cost_ngn: e.pricing?.travel_cost_ngn ?? 0,
                stamp_duty_ngn: e.pricing?.stamp_duty_ngn ?? 0,
                platform_fee_ngn: e.pricing?.platform_fee_ngn ?? 0,
                discount_ngn: e.pricing?.discount_ngn ?? 0,
                grand_total_ngn: e.pricing?.grand_total_ngn ?? 0,
            },
            accepted_terms: true,
            confirm_revision: false,
        };
    }

    return {
        pitch: '',
        scope_detail: '',
        warranty_terms: '',
        planned_start_date: defaultPlannedStart(),
        planned_finish_date: defaultPlannedFinish(),
        estimated_duration_days: null,
        corrections_included: false,
        corrections_rounds: 2,
        progress_report_frequency: 'weekly',
        materials: [],
        pricing: {
            professional_fee_ngn: Math.round((props.quest.budget_minor || 0) / 100 * 0.85),
            vat_applies: true,
            withholding_tax_percent: 0,
            travel_cost_ngn: 0,
            stamp_duty_ngn: 0,
            platform_fee_ngn: 0,
            discount_ngn: 0,
            grand_total_ngn: 0,
        },
        accepted_terms: false,
        confirm_revision: false,
    };
}

const form = useForm(buildInitialForm());

const page = usePage();

const termsFrameSrc = computed(() => {
    const z = page.props.ziggy;
    if (z?.location) {
        return `${String(z.location).replace(/\/$/, '')}/terms-of-service`;
    }

    return '/terms-of-service';
});

const cat = props.market_hints?.category || null;

const pricingHintsBlock = computed(() => {
    const h = props.pricing_hints;
    if (!h || Number(h.sample_size || 0) < 1) {
        return false;
    }

    return (
        Number(h.professional_fee_ngn) > 0
        || Number(h.materials_total_ngn) > 0
        || Number(h.travel_cost_ngn) > 0
    );
});

function applyPricingHints() {
    const h = props.pricing_hints || {};
    if (h.professional_fee_ngn > 0) {
        form.pricing.professional_fee_ngn = Math.round(Number(h.professional_fee_ngn));
    }
    if (h.travel_cost_ngn > 0) {
        form.pricing.travel_cost_ngn = Math.round(Number(h.travel_cost_ngn));
    }
    const mat = Math.round(Number(h.materials_total_ngn) || 0);
    if (mat > 0) {
        if (!form.materials.length) {
            form.materials.push({ label: 'Materials / parts (suggested)', quantity: '1', unit_price_ngn: mat });
        } else {
            const first = form.materials[0];
            const empty =
                !String(first.label || '').trim()
                && (!Number(first.unit_price_ngn) || Number(first.unit_price_ngn) === 0);
            if (empty) {
                first.label = 'Materials / parts (suggested)';
                first.quantity = '1';
                first.unit_price_ngn = mat;
            } else {
                form.materials.push({ label: 'Additional materials (suggested)', quantity: '1', unit_price_ngn: mat });
            }
        }
    }
}

const hintLine = computed(() => {
    if (cat?.budget?.avg_minor && props.quest.budget_minor) {
        const avg = Math.round(cat.budget.avg_minor / 100);
        const b = Math.round(props.quest.budget_minor / 100);
        return `Open quests in this category average about ₦${avg.toLocaleString('en-NG')} — client budget here is ₦${b.toLocaleString('en-NG')}.`;
    }
    if (cat?.completion?.avg_days && props.quest.estimated_completion_days) {
        return `Typical client timelines in this category run ~${cat.completion.avg_days} days (this brief: ${props.quest.estimated_completion_days} days).`;
    }
    if (props.market_hints?.global_budget?.avg_minor) {
        const g = Math.round(props.market_hints.global_budget.avg_minor / 100);

        return `Across the marketplace, live quests average about ₦${g.toLocaleString('en-NG')}.`;
    }

    return '';
});

function parseQty(raw) {
    if (raw === null || raw === undefined || raw === '') {
        return 1;
    }
    const s = String(raw).trim();
    if (s === '') {
        return 1;
    }
    const cleaned = s.replace(/,/g, '.');
    const n = Number.parseFloat(cleaned);

    return Number.isFinite(n) && n >= 0 ? n : 0;
}

function materialLineNgn(row) {
    const qty = parseQty(row.quantity);
    const unit = Math.max(0, Math.round(Number(row.unit_price_ngn) || 0));

    return Math.round(qty * unit);
}

const materialsSubtotalNgn = computed(() =>
    form.materials.reduce((sum, row) => sum + materialLineNgn(row), 0),
);

const platformFeePercent = computed(() => {
    const fromPage = Number(page.props.platform_fee_percent);
    const fromProps = Number(props.platform_fee_percent);
    const pct = Number.isFinite(fromPage) && fromPage >= 0 ? fromPage : fromProps;

    return Math.max(0, Math.min(100, pct || 12));
});

const pricingSubtotalNgn = computed(() => {
    const prof = Math.max(0, Math.round(Number(form.pricing.professional_fee_ngn) || 0));
    const mat = materialsSubtotalNgn.value;
    const travel = Math.max(0, Math.round(Number(form.pricing.travel_cost_ngn) || 0));

    return prof + mat + travel;
});

const breakdown = computed(() => {
    const prof = Math.max(0, Math.round(Number(form.pricing.professional_fee_ngn) || 0)) * 100;
    const mat = materialsSubtotalNgn.value * 100;
    const travel = Math.max(0, Math.round(Number(form.pricing.travel_cost_ngn) || 0)) * 100;
    const stamp = Math.max(0, Math.round(Number(form.pricing.stamp_duty_ngn) || 0)) * 100;
    const platform = Math.max(0, Math.round(Number(form.pricing.platform_fee_ngn) || 0)) * 100;
    const discount = Math.max(0, Math.round(Number(form.pricing.discount_ngn) || 0)) * 100;
    const baseMinor = prof + mat + travel;
    const vatRate = Number(props.vat_preset_percent) || 0;
    const vatMinor = form.pricing.vat_applies ? Math.round(baseMinor * (vatRate / 100)) : 0;
    const whtPct = Math.max(0, Math.min(100, Number(form.pricing.withholding_tax_percent) || 0));
    const whtMinor = Math.round(baseMinor * (whtPct / 100));
    const grandMinor = baseMinor + vatMinor + whtMinor + stamp + platform - discount;

    return { baseMinor, vatMinor, whtMinor, grandMinor };
});

const computedGrandNgn = computed(() => Math.round(breakdown.value.grandMinor / 100));

watch(
    [pricingSubtotalNgn, platformFeePercent],
    () => {
        const fee = Math.round(pricingSubtotalNgn.value * (platformFeePercent.value / 100));
        if (form.pricing.platform_fee_ngn !== fee) {
            form.pricing.platform_fee_ngn = fee;
        }
    },
    { immediate: true },
);

watch(
    computedGrandNgn,
    (next) => {
        if (!Number.isFinite(next) || next < 1) {
            return;
        }
        if (form.pricing.grand_total_ngn === next) {
            return;
        }
        form.pricing.grand_total_ngn = next;
    },
    { immediate: true },
);

watch(
    () => form.planned_start_date,
    (start) => {
        const fin = form.planned_finish_date;
        if (start && fin && fin < start) {
            form.planned_finish_date = start;
        }
    },
);

function addMaterialRow() {
    form.materials.push({ label: '', quantity: '1', unit_price_ngn: 0 });
}

function removeMaterialRow(i) {
    form.materials.splice(i, 1);
}

function materialRowsForSubmit(rows) {
    return (rows || []).filter((row) => {
        const label = String(row?.label ?? '').trim();
        const unit = Math.max(0, Math.round(Number(row?.unit_price_ngn) || 0));

        return label !== '' || unit > 0;
    });
}

function formatBudget(minor) {
    const n = Math.round(Number(minor) || 0) / 100;

    return `₦${n.toLocaleString('en-NG')}`;
}

function formatNgn(n) {
    const v = Math.round(Number(n) || 0);

    return `₦${v.toLocaleString('en-NG')}`;
}

function firstError(errors) {
    if (!errors || typeof errors !== 'object') {
        return '';
    }

    for (const value of Object.values(errors)) {
        if (Array.isArray(value) && value.length) {
            return String(value[0]);
        }
        if (typeof value === 'string' && value.trim() !== '') {
            return value;
        }
    }

    return '';
}

function scrollToErrorBanner() {
    nextTick(() => {
        errorBannerRef.value?.scrollIntoView?.({ behavior: 'smooth', block: 'start' });
    });
}

function submit() {
    submitFeedback.value = '';

    const transform = (data) => ({
        ...data,
        materials: materialRowsForSubmit(data.materials),
        pricing: {
            ...data.pricing,
            vat_applies: !!data.pricing?.vat_applies,
            grand_total_ngn: computedGrandNgn.value,
            platform_fee_ngn: form.pricing.platform_fee_ngn,
        },
        accepted_terms: data.accepted_terms ? true : false,
        confirm_revision: data.confirm_revision ? true : false,
    });

    const visitOpts = {
        preserveScroll: true,
        timeout: 180000,
        onSuccess: () => {
            submitFeedback.value = '';
        },
        onError: (errors) => {
            submitFeedback.value =
                firstError(errors) ||
                'Your proposal could not be saved. Review the highlighted fields below (pitch, scope, materials, pricing, and agreement).';
            scrollToErrorBanner();
        },
        onFinish: () => {
            if (Object.keys(form.errors || {}).length > 0 && !submitFeedback.value) {
                submitFeedback.value = firstError(form.errors) || 'Please fix the highlighted fields and try again.';
                scrollToErrorBanner();
            }
        },
    };

    if (isEditMode.value) {
        form.transform(transform).put(route('quests.proposals.update', [props.quest.route_key, props.proposal_edit.offer_id]), visitOpts);

        return;
    }

    form.transform(transform).post(route('quests.proposals.store', props.quest.route_key), visitOpts);
}
</script>
