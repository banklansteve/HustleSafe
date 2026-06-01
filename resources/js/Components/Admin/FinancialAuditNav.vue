<template>
    <nav class="flex flex-wrap gap-2">
        <Link
            v-for="item in items"
            :key="item.href"
            :href="item.href"
            class="rounded-full px-4 py-2 text-xs font-black uppercase tracking-wide transition"
            :class="item.active ? 'bg-primary-600 text-white shadow-sm shadow-primary-900/20' : shell.btnGhost"
        >
            {{ item.label }}
            <span v-if="item.badge" class="ml-1 rounded-full bg-amber-400 px-1.5 py-0.5 text-[9px] text-amber-950">{{ item.badge }}</span>
        </Link>
    </nav>
</template>

<script setup>
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    active: { type: String, default: 'overview' },
    openExceptions: { type: Number, default: 0 },
});

const { shell } = useInjectedAdminTheme();
const page = usePage();
const exceptions = computed(() => props.openExceptions || page.props.financial_audit_nav?.open_exceptions || 0);

const items = computed(() => [
    { label: 'Overview', href: route('admin.financial-audit.index'), active: props.active === 'overview' },
    { label: 'Reconciliation', href: route('admin.financial-audit.reconciliation.index'), active: props.active === 'reconciliation' },
    { label: 'Escrow ledger', href: route('admin.financial-audit.escrow-ledger'), active: props.active === 'ledger' },
    {
        label: 'Exceptions',
        href: route('admin.financial-audit.exceptions.index'),
        active: props.active === 'exceptions',
        badge: exceptions.value > 0 ? exceptions.value : null,
    },
    { label: 'VAT report', href: route('admin.financial-audit.reports.vat'), active: props.active === 'vat' },
    { label: 'Platform fees', href: route('admin.financial-audit.reports.platform-fees'), active: props.active === 'fees' },
]);
</script>
