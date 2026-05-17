<template>
    <AdminShell
        title="Promotions & Growth Tools"
        subtitle="Revenue, acquisition, referrals, recognition, and premium visibility controls for platform growth."
    >
        <div class="space-y-5">
            <div class="grid gap-3 md:grid-cols-6">
                <div v-for="tile in overviewTiles" :key="tile.label" class="rounded-3xl border border-orange-200/70 bg-gradient-to-br from-orange-50 to-amber-50 p-4 shadow-sm dark:border-orange-400/20 dark:from-orange-400/10 dark:to-amber-400/10">
                    <p class="text-[10px] font-black uppercase tracking-wider text-orange-700 dark:text-orange-200">{{ tile.label }}</p>
                    <p class="mt-2 text-2xl font-black" :class="shell.title">{{ tile.value }}</p>
                </div>
            </div>

            <div class="flex gap-2 overflow-x-auto rounded-3xl border p-2" :class="shell.card">
                <Link v-for="tab in tabs" :key="tab.key" :href="route('admin.promotions.index', { section: tab.key })" preserve-scroll preserve-state class="whitespace-nowrap rounded-2xl px-4 py-2 text-sm font-black" :class="section === tab.key ? warmBtn : shell.btnGhost">
                    {{ tab.label }}
                </Link>
            </div>

            <section v-if="section === 'featured'" class="space-y-5">
                <div class="grid gap-3 md:grid-cols-3">
                    <div v-for="tile in featured.tiles" :key="tile.label" class="rounded-3xl border p-4" :class="shell.card">
                        <p class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">{{ tile.label }}</p>
                        <p class="mt-2 text-2xl font-black" :class="shell.title">{{ tile.value }}</p>
                    </div>
                </div>

                <AdminPanel title="Featured listing management" description="Active, expiring, expired, manually granted, and cancelled featured quest placements.">
                    <template #actions>
                        <button type="button" class="rounded-2xl px-4 py-2 text-xs font-black uppercase text-white" :class="warmBtn" @click="showFeaturedForm = !showFeaturedForm">Grant featured</button>
                    </template>
                    <form v-if="showFeaturedForm" class="mb-5 grid gap-3 rounded-3xl border p-4 md:grid-cols-5" :class="shell.card" @submit.prevent="grantFeatured">
                        <input v-model="featuredForm.quest_id" required type="number" placeholder="Quest ID" class="rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                        <select v-model="featuredForm.tier" class="rounded-2xl border px-3 py-3 text-sm font-bold" :class="shell.input">
                            <option value="standard">Standard</option>
                            <option value="premium">Premium</option>
                            <option value="elite">Elite</option>
                        </select>
                        <input v-model="featuredForm.duration_days" required type="number" placeholder="Days" class="rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                        <input v-model="featuredForm.manual_grant_reason" required placeholder="Reason" class="rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                        <button type="submit" class="rounded-2xl px-4 py-3 text-sm font-black text-white" :class="warmBtn">Grant</button>
                    </form>

                    <div class="hidden overflow-x-auto lg:block">
                        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10">
                            <thead>
                                <tr class="text-left text-[10px] font-black uppercase tracking-wider" :class="shell.label">
                                    <th class="px-3 py-3">Quest</th>
                                    <th class="px-3 py-3">Client</th>
                                    <th class="px-3 py-3">Tier</th>
                                    <th class="px-3 py-3">Dates</th>
                                    <th class="px-3 py-3">Paid</th>
                                    <th class="px-3 py-3">Views</th>
                                    <th class="px-3 py-3">Status</th>
                                    <th class="px-3 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-white/10">
                                <tr v-for="row in featured.listings.data" :key="row.id">
                                    <td class="px-3 py-4 font-black">{{ row.quest?.title }}</td>
                                    <td class="px-3 py-4">{{ row.client?.name }}</td>
                                    <td class="px-3 py-4 capitalize">{{ row.tier }}</td>
                                    <td class="px-3 py-4 text-xs">{{ dateLabel(row.starts_at) }} → {{ dateLabel(row.expires_at) }}</td>
                                    <td class="px-3 py-4 font-black">{{ row.amount_paid }}</td>
                                    <td class="px-3 py-4">{{ row.proposal_views }}</td>
                                    <td class="px-3 py-4"><StatusPill :status="row.status" /></td>
                                    <td class="px-3 py-4"><button v-if="row.status === 'active' || row.status === 'expiring_soon'" type="button" class="text-xs font-black text-rose-600 underline" @click="cancelFeatured(row)">Cancel</button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="grid gap-3 lg:hidden">
                        <div v-for="row in featured.listings.data" :key="row.id" class="rounded-3xl border p-4" :class="shell.card">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-black">{{ row.quest?.title }}</p>
                                    <p class="text-xs font-bold" :class="shell.cardMuted">{{ row.client?.name }} · {{ row.tier }}</p>
                                </div>
                                <StatusPill :status="row.status" />
                            </div>
                            <p class="mt-3 text-sm font-black">{{ row.amount_paid }} · {{ row.proposal_views }} views</p>
                            <button v-if="row.status === 'active' || row.status === 'expiring_soon'" type="button" class="mt-3 rounded-2xl bg-rose-600 px-4 py-3 text-sm font-black text-white" @click="cancelFeatured(row)">Cancel</button>
                        </div>
                    </div>
                </AdminPanel>

                <AdminPanel title="Featured listing performance" description="Featured versus non-featured quest outcomes over the last 30 days.">
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        <div v-for="row in featured.performance" :key="row.category" class="rounded-3xl border p-4" :class="shell.card">
                            <p class="font-black">{{ row.category }}</p>
                            <MiniBars class="mt-4" :items="[{ label: 'Featured proposals', value: row.featured_proposals }, { label: 'Regular proposals', value: row.regular_proposals }, { label: 'Days to hire', value: row.time_to_hire_days || 0 }]" />
                            <p class="mt-3 text-xs font-bold" :class="shell.cardMuted">Featured value {{ row.featured_value }} vs regular {{ row.regular_value }}</p>
                        </div>
                    </div>
                </AdminPanel>
            </section>

            <section v-else-if="section === 'coupons'" class="space-y-5">
                <AdminPanel title="Coupon & discount engine" description="Create, pause, and review coupons for service fees, featured listings, or all payments.">
                    <template #actions>
                        <button type="button" class="rounded-2xl px-4 py-2 text-xs font-black uppercase text-white" :class="warmBtn" @click="showCouponForm = !showCouponForm">Create coupon</button>
                    </template>
                    <form v-if="showCouponForm" class="mb-5 grid gap-3 rounded-3xl border p-4 md:grid-cols-4" :class="shell.card" @submit.prevent="createCoupon">
                        <input v-model="couponForm.code" placeholder="Code or leave blank" class="rounded-2xl border px-4 py-3 text-sm font-semibold uppercase" :class="shell.input" />
                        <select v-model="couponForm.discount_type" class="rounded-2xl border px-3 py-3 text-sm font-bold" :class="shell.input">
                            <option value="percent">Percentage</option>
                            <option value="fixed">Fixed amount</option>
                        </select>
                        <input v-if="couponForm.discount_type === 'percent'" v-model="couponForm.discount_percent" type="number" min="1" max="100" placeholder="Percent" class="rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                        <input v-else v-model="couponForm.discount_value_minor" type="number" min="1" placeholder="Fixed minor amount" class="rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                        <select v-model="couponForm.applies_to" class="rounded-2xl border px-3 py-3 text-sm font-bold" :class="shell.input">
                            <option value="all">All payments</option>
                            <option value="service_fee">Service fee</option>
                            <option value="featured_listing">Featured listing</option>
                        </select>
                        <select v-model="couponForm.eligibility" class="rounded-2xl border px-3 py-3 text-sm font-bold" :class="shell.input">
                            <option value="all">All users</option>
                            <option value="new_users">New users</option>
                            <option value="clients">Clients</option>
                            <option value="freelancers">Freelancers</option>
                            <option value="specific_users">Specific users</option>
                        </select>
                        <input v-model="couponForm.usage_limit_total" type="number" placeholder="Total limit" class="rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                        <input v-model="couponForm.usage_limit_per_user" type="number" placeholder="Per-user limit" class="rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                        <button type="submit" class="rounded-2xl px-4 py-3 text-sm font-black text-white" :class="warmBtn">Create</button>
                    </form>

                    <div class="grid gap-3 xl:grid-cols-2">
                        <div v-for="coupon in coupons.coupons.data" :key="coupon.id" class="rounded-3xl border p-4" :class="shell.card">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-mono text-xl font-black">{{ coupon.code }}</p>
                                    <p class="text-xs font-bold" :class="shell.cardMuted">{{ coupon.discount }} · {{ coupon.applies_to }} · {{ coupon.usage }}</p>
                                </div>
                                <StatusPill :status="coupon.status" />
                            </div>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <button type="button" class="rounded-2xl px-4 py-2 text-xs font-black" :class="shell.btnGhost" @click="openCoupon(coupon)">Analytics</button>
                                <button v-if="coupon.status === 'active'" type="button" class="rounded-2xl bg-rose-600 px-4 py-2 text-xs font-black text-white" @click="pauseCoupon(coupon)">Pause</button>
                            </div>
                        </div>
                    </div>
                </AdminPanel>

                <AdminPanel title="Suspicious redemptions" description="Coupon abuse flags from multi-account, shared source, and velocity checks.">
                    <div class="grid gap-3 md:grid-cols-2">
                        <div v-for="flag in coupons.fraud_flags" :key="flag.id" class="rounded-3xl border border-rose-200 p-4 dark:border-rose-400/20">
                            <p class="font-black">{{ flag.reason.replace(/_/g, ' ') }}</p>
                            <p class="mt-1 text-sm font-bold" :class="shell.cardMuted">{{ flag.coupon }} · {{ flag.user || 'Unknown user' }}</p>
                        </div>
                    </div>
                </AdminPanel>
            </section>

            <section v-else-if="section === 'referrals'" class="space-y-5">
                <AdminPanel title="Referral programme analytics" description="Referral tree, conversion, rewards, and weekly volume.">
                    <div class="grid gap-3 md:grid-cols-4">
                        <div v-for="(value, label) in referrals.metrics" :key="label" class="rounded-3xl border p-4" :class="shell.card">
                            <p class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">{{ label.replace(/_/g, ' ') }}</p>
                            <p class="mt-2 text-2xl font-black" :class="shell.title">{{ value }}</p>
                        </div>
                    </div>
                    <MiniBars class="mt-5" :items="referrals.weekly_volume" />
                </AdminPanel>
                <div class="grid gap-5 xl:grid-cols-2">
                    <AdminPanel title="Top referrers" description="Ranked by referral volume.">
                        <div class="space-y-2">
                            <div v-for="referrer in referrals.top_referrers" :key="referrer.email" class="flex items-center justify-between rounded-2xl border p-3" :class="shell.card">
                                <div>
                                    <p class="font-black">{{ referrer.name }}</p>
                                    <p class="text-xs font-bold" :class="shell.cardMuted">{{ referrer.email }}</p>
                                </div>
                                <p class="text-xl font-black">{{ referrer.total }}</p>
                            </div>
                        </div>
                    </AdminPanel>
                    <AdminPanel title="Abuse investigation" description="Suspicious referral patterns and fraud network signals.">
                        <div class="space-y-2">
                            <div v-for="flag in referrals.abuse_flags" :key="flag.id" class="rounded-2xl border p-3" :class="shell.card">
                                <p class="font-black">{{ flag.reason?.replace(/_/g, ' ') }}</p>
                                <p class="text-xs font-bold" :class="shell.cardMuted">{{ flag.referrer?.name || 'Unknown referrer' }} · {{ flag.status }}</p>
                            </div>
                        </div>
                    </AdminPanel>
                </div>
            </section>

            <section v-else-if="section === 'badges'" class="space-y-5">
                <AdminPanel title="Badge management" description="Recognition badges, criteria, holder counts, and manual awards.">
                    <template #actions>
                        <button type="button" class="rounded-2xl px-4 py-2 text-xs font-black uppercase text-white" :class="warmBtn" @click="showBadgeForm = !showBadgeForm">Create badge</button>
                    </template>
                    <form v-if="showBadgeForm" class="mb-5 grid gap-3 rounded-3xl border p-4 md:grid-cols-4" :class="shell.card" @submit.prevent="createBadge">
                        <input v-model="badgeForm.name" required placeholder="Badge name" class="rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                        <input v-model="badgeForm.icon" placeholder="Icon name" class="rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                        <input v-model="badgeForm.description" required placeholder="Description" class="rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                        <button type="submit" class="rounded-2xl px-4 py-3 text-sm font-black text-white" :class="warmBtn">Create</button>
                    </form>
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        <div v-for="badge in badges.badges" :key="badge.id" class="rounded-3xl border p-4" :class="shell.card">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-black">{{ badge.name }}</p>
                                    <p class="mt-1 text-xs font-bold" :class="shell.cardMuted">{{ badge.description }}</p>
                                </div>
                                <span class="rounded-full bg-orange-100 px-3 py-1 text-xs font-black text-orange-700">{{ badge.holders_count }}</span>
                            </div>
                            <p class="mt-3 text-xs font-bold" :class="shell.cardMuted">{{ badge.is_automatic ? 'Auto-awarded' : 'Manual' }} · {{ badge.is_public ? 'Public' : 'Admin only' }}</p>
                            <button type="button" class="mt-3 rounded-2xl px-4 py-2 text-xs font-black" :class="shell.btnGhost" @click="assignBadge(badge)">Award manually</button>
                        </div>
                    </div>
                </AdminPanel>
                <AdminPanel title="Badge effectiveness" description="Directional lift indicators for recognition programmes.">
                    <MiniBars :items="badges.effectiveness.map((row) => ({ label: row.label, value: row.holders }))" />
                </AdminPanel>
            </section>

            <section v-else-if="section === 'settings'">
                <AdminPanel title="Promotion settings" description="Pricing, placements, referral reward structure, expiry, and qualifying event controls.">
                    <form class="space-y-5" @submit.prevent="saveSettings">
                        <div class="grid gap-3 xl:grid-cols-3">
                            <div v-for="(tier, key) in settingsForm.featured_tiers" :key="key" class="rounded-3xl border p-4" :class="shell.card">
                                <p class="text-sm font-black capitalize">{{ key }}</p>
                                <input v-model="tier.label" class="mt-3 w-full rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                                <textarea v-model="tierPlacements[key]" rows="3" class="mt-3 w-full rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                            </div>
                        </div>
                        <div class="grid gap-3 md:grid-cols-4">
                            <select v-model="settingsForm.referral_program.reward_type" class="rounded-2xl border px-3 py-3 text-sm font-bold" :class="shell.input">
                                <option value="wallet_credit">Wallet credit</option>
                                <option value="cash_payout">Cash payout</option>
                                <option value="coupon">Coupon code</option>
                            </select>
                            <input v-model="settingsForm.referral_program.client_reward_minor" type="number" class="rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                            <input v-model="settingsForm.referral_program.freelancer_reward_minor" type="number" class="rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                            <input v-model="settingsForm.referral_program.reward_expiry_days" type="number" class="rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                        </div>
                        <button type="submit" class="rounded-2xl px-5 py-3 text-sm font-black text-white" :class="warmBtn">Save settings</button>
                    </form>
                </AdminPanel>
            </section>
        </div>
    </AdminShell>
</template>

<script setup>
import AdminPanel from '@/Components/Admin/AdminPanel.vue';
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';
import AdminShell from '@/Layouts/AdminShell.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, defineComponent, h, reactive, ref } from 'vue';

const props = defineProps({
    section: { type: String, required: true },
    overview: { type: Object, default: () => ({}) },
    featured: { type: Object, default: null },
    coupons: { type: Object, default: null },
    referrals: { type: Object, default: null },
    badges: { type: Object, default: null },
    settings: { type: Object, default: null },
    filters: { type: Object, default: () => ({}) },
});

const StatusPill = defineComponent({
    props: { status: String },
    setup(p) {
        const klass = computed(() => ({
            active: 'bg-emerald-100 text-emerald-700',
            expiring_soon: 'bg-amber-100 text-amber-700',
            expired: 'bg-slate-100 text-slate-700',
            cancelled: 'bg-rose-100 text-rose-700',
            paused: 'bg-amber-100 text-amber-700',
            scheduled: 'bg-blue-100 text-blue-700',
        }[p.status] || 'bg-slate-100 text-slate-700'));
        return () => h('span', { class: ['rounded-full px-3 py-1 text-xs font-black capitalize', klass.value] }, String(p.status || '').replace(/_/g, ' '));
    },
});

const MiniBars = defineComponent({
    props: { items: { type: Array, default: () => [] } },
    setup(p) {
        return () => h('div', { class: 'space-y-3' }, p.items.map((item) => {
            const max = Math.max(...p.items.map((i) => Number(i.value || 0)), 1);
            return h('div', { class: 'space-y-1' }, [
                h('div', { class: 'flex justify-between text-xs font-black' }, [h('span', item.label), h('span', String(item.value))]),
                h('div', { class: 'h-3 rounded-full bg-slate-100 dark:bg-white/10' }, [
                    h('div', { class: 'h-3 rounded-full bg-gradient-to-r from-orange-500 to-amber-400', style: { width: `${Math.min(100, (Number(item.value || 0) / max) * 100)}%` } }),
                ]),
            ]);
        }));
    },
});

const { shell } = useInjectedAdminTheme();
const warmBtn = 'bg-gradient-to-r from-orange-600 to-amber-500 text-white shadow-sm shadow-orange-500/20';
const tabs = [
    { key: 'featured', label: 'Featured Listings' },
    { key: 'coupons', label: 'Coupons' },
    { key: 'referrals', label: 'Referrals' },
    { key: 'badges', label: 'Badges' },
    { key: 'settings', label: 'Settings' },
];
const showFeaturedForm = ref(false);
const showCouponForm = ref(false);
const showBadgeForm = ref(false);
const overviewTiles = computed(() => [
    { label: 'Active featured', value: props.overview.active_featured ?? 0 },
    { label: 'Featured revenue', value: props.overview.featured_revenue_month ?? '₦0.00' },
    { label: 'Active coupons', value: props.overview.active_coupons ?? 0 },
    { label: 'Referrals month', value: props.overview.referrals_month ?? 0 },
    { label: 'Rewards paid', value: props.overview.rewards_paid ?? '₦0.00' },
    { label: 'Badges awarded', value: props.overview.badges_awarded ?? 0 },
]);

const featuredForm = useForm({ quest_id: '', tier: 'standard', duration_days: 7, amount_paid_minor: 0, manual_grant_reason: '' });
const couponForm = useForm({ code: '', discount_type: 'percent', discount_percent: 20, discount_value_minor: 0, max_discount_minor: null, applies_to: 'all', eligibility: 'all', usage_limit_total: null, usage_limit_per_user: 1, minimum_transaction_minor: 0 });
const badgeForm = useForm({ name: '', icon: 'star', description: '', criteria: {}, is_automatic: false, requires_manual_review: true, is_public: true, is_time_limited: false });
const settingsForm = useForm({
    featured_tiers: props.settings?.featured_tiers || {},
    referral_program: props.settings?.referral_program || {},
});
const tierPlacements = reactive(Object.fromEntries(Object.entries(settingsForm.featured_tiers || {}).map(([key, tier]) => [key, (tier.placements || []).join('\n')])));

function grantFeatured() {
    featuredForm.post(route('admin.promotions.featured.store'), { preserveScroll: true, onSuccess: () => featuredForm.reset('quest_id', 'manual_grant_reason') });
}

function cancelFeatured(row) {
    const reason = window.prompt('Reason for cancelling this featured listing?');
    if (!reason || reason.length < 10) return;
    router.post(route('admin.promotions.featured.cancel', row.id), { reason }, { preserveScroll: true });
}

function createCoupon() {
    couponForm.post(route('admin.promotions.coupons.store'), { preserveScroll: true, onSuccess: () => couponForm.reset('code') });
}

function pauseCoupon(coupon) {
    router.patch(route('admin.promotions.coupons.pause', coupon.id), {}, { preserveScroll: true });
}

async function openCoupon(coupon) {
    const { data } = await window.axios.get(route('admin.promotions.coupons.analytics', coupon.id));
    window.alert(`${coupon.code}\nRedemptions: ${data.coupon.usage}\nFlags: ${data.coupon.fraud_flags_count}`);
}

function createBadge() {
    badgeForm.post(route('admin.promotions.badges.store'), { preserveScroll: true, onSuccess: () => badgeForm.reset('name', 'description') });
}

function assignBadge(badge) {
    const userId = window.prompt('User ID to award this badge to');
    const justification = window.prompt('Written justification');
    if (!userId || !justification || justification.length < 20) return;
    router.post(route('admin.promotions.badges.assign', badge.id), { user_id: userId, justification }, { preserveScroll: true });
}

function saveSettings() {
    Object.entries(tierPlacements).forEach(([key, value]) => {
        settingsForm.featured_tiers[key].placements = value.split('\n').map((line) => line.trim()).filter(Boolean);
    });
    settingsForm.patch(route('admin.promotions.settings.update'), { preserveScroll: true });
}

function dateLabel(value) {
    if (!value) return '—';
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}
</script>
