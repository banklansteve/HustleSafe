<template>
    <div v-if="renewal.show_panel" class="mt-4 space-y-4 rounded-2xl border border-amber-200 bg-amber-50/70 p-4">
        <div>
            <p class="text-[10px] font-black uppercase tracking-wide text-amber-900">Contract ending soon</p>
            <p class="mt-1 text-sm font-black text-amber-950">
                Ends {{ renewal.contract_ends_label || '—' }}
            </p>
            <p class="mt-2 text-xs font-semibold leading-relaxed text-amber-950/90">
                {{ renewal.plain_english }}
            </p>
        </div>

        <div class="grid gap-3 lg:grid-cols-3">
            <form class="rounded-xl border border-white/80 bg-white/90 p-3" @submit.prevent="submitExtend">
                <p class="text-[10px] font-black uppercase tracking-wide text-slate-600">Extend</p>
                <p class="mt-1 text-xs font-semibold text-slate-700">Add months to this contract with the same worker.</p>
                <select
                    v-model.number="extendForm.additional_months"
                    required
                    class="mt-3 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-bold text-slate-900"
                >
                    <option v-for="opt in renewal.duration_options" :key="`extend-${opt.value}`" :value="opt.value">
                        + {{ opt.label }}
                    </option>
                </select>
                <label class="mt-3 flex items-start gap-2 text-[11px] font-semibold text-slate-700">
                    <input v-model="extendForm.confirm" type="checkbox" class="mt-0.5 rounded border-slate-300 text-amber-600" />
                    <span>I will fund any extra escrow needed for the added periods.</span>
                </label>
                <button
                    type="submit"
                    class="mt-3 inline-flex w-full justify-center rounded-full bg-amber-700 px-4 py-2 text-xs font-black uppercase tracking-wide text-white hover:bg-amber-800 disabled:opacity-50"
                    :disabled="extendForm.processing || !extendForm.confirm"
                >
                    Extend contract
                </button>
            </form>

            <form class="rounded-xl border border-white/80 bg-white/90 p-3" @submit.prevent="submitContinue">
                <p class="text-[10px] font-black uppercase tracking-wide text-slate-600">Continue with worker</p>
                <p class="mt-1 text-xs font-semibold text-slate-700">Start a fresh cycle with your current freelancer.</p>
                <select
                    v-model.number="continueForm.contract_duration_months"
                    required
                    class="mt-3 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-bold text-slate-900"
                >
                    <option v-for="opt in renewal.duration_options" :key="`continue-${opt.value}`" :value="opt.value">
                        {{ opt.label }}
                    </option>
                </select>
                <label class="mt-3 flex items-start gap-2 text-[11px] font-semibold text-slate-700">
                    <input v-model="continueForm.confirm" type="checkbox" class="mt-0.5 rounded border-slate-300 text-emerald-600" />
                    <span>I will fund escrow again for the new cycle.</span>
                </label>
                <button
                    type="submit"
                    class="mt-3 inline-flex w-full justify-center rounded-full bg-emerald-700 px-4 py-2 text-xs font-black uppercase tracking-wide text-white hover:bg-emerald-800 disabled:opacity-50"
                    :disabled="continueForm.processing || !continueForm.confirm"
                >
                    Continue cycle
                </button>
            </form>

            <form class="rounded-xl border border-white/80 bg-white/90 p-3" @submit.prevent="submitRepublish">
                <p class="text-[10px] font-black uppercase tracking-wide text-slate-600">Republish</p>
                <p class="mt-1 text-xs font-semibold text-slate-700">Close this cycle and open a fresh quest for new proposals.</p>
                <label class="mt-6 flex items-start gap-2 text-[11px] font-semibold text-slate-700">
                    <input v-model="republishForm.confirm" type="checkbox" class="mt-0.5 rounded border-slate-300 text-sky-600" />
                    <span>I understand this ends the current engagement and creates a new listing.</span>
                </label>
                <button
                    type="submit"
                    class="mt-3 inline-flex w-full justify-center rounded-full border border-sky-300 bg-sky-50 px-4 py-2 text-xs font-black uppercase tracking-wide text-sky-950 hover:bg-sky-100 disabled:opacity-50"
                    :disabled="republishForm.processing || !republishForm.confirm"
                >
                    Republish quest
                </button>
            </form>
        </div>
    </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    renewal: { type: Object, default: () => ({ show_panel: false }) },
});

const renewal = computed(() => props.renewal ?? { show_panel: false });
const defaultMonths = computed(() => renewal.value.duration_options?.[0]?.value ?? 3);

const extendForm = useForm({
    additional_months: defaultMonths.value,
    confirm: false,
});

const continueForm = useForm({
    contract_duration_months: defaultMonths.value,
    confirm: false,
});

const republishForm = useForm({
    confirm: false,
});

function submitExtend() {
    extendForm.post(renewal.value.extend_url, { preserveScroll: true });
}

function submitContinue() {
    continueForm.post(renewal.value.continue_url, { preserveScroll: true });
}

function submitRepublish() {
    republishForm.post(renewal.value.republish_url);
}
</script>
