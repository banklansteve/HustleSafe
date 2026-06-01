<template>
    <aside
        class="rounded-2xl border border-slate-200 bg-gradient-to-br from-slate-50 to-white p-4 text-sm shadow-sm ring-1 ring-slate-100 sm:p-5"
        :class="compact ? 'space-y-2' : 'space-y-3'"
    >
        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-primary-800">Fees &amp; charges</p>
        <ul class="space-y-2 text-xs font-semibold leading-relaxed text-slate-700">
            <li>Paystack fee (client escrow funding): {{ disclosure.paystack_funding_fee }}</li>
            <li>Paystack payout fee (freelancer withdrawal): {{ disclosure.paystack_payout_fee }}</li>
            <li>{{ disclosure.vat_line }}</li>
            <li>{{ disclosure.platform_fee_line }}</li>
        </ul>
        <p class="text-xs font-semibold leading-relaxed text-slate-600">
            This covers: all gateway fees, VAT, and platform operation.
        </p>
    </aside>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    disclosure: { type: Object, default: null },
    platformFeePercent: { type: [Number, String], default: null },
    compact: { type: Boolean, default: false },
});

const page = usePage();

const disclosure = computed(() => {
    if (props.disclosure) {
        return props.disclosure;
    }
    const shared = page.props.platform_fee_disclosure;
    if (shared) {
        if (props.platformFeePercent !== null && props.platformFeePercent !== '') {
            const pct = Number(props.platformFeePercent);
            const label = String(pct).replace(/\.0+$/, '').replace(/(\.\d*?)0+$/, '$1');

            return {
                ...shared,
                platform_fee_percent: pct,
                platform_fee_percent_label: label,
                platform_fee_line: `Platform fee: ${label}% of the job amount`,
            };
        }

        return shared;
    }

    const pct = Number(page.props.platform_fee_percent ?? 12);
    const label = String(pct).replace(/\.0+$/, '');

    return {
        paystack_funding_fee: '1.5% of escrow fund + ₦100, capped at ₦2,000',
        paystack_payout_fee: '₦10–₦50 depending on bank',
        vat_line: 'VAT on platform fee: 7.5% (applies to platform fee only)',
        platform_fee_line: `Platform fee: ${label}% of the job amount`,
    };
});
</script>
