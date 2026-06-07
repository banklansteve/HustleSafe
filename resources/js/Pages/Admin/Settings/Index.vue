<template>
    <AdminShell
        title="Platform Settings"
        subtitle="The master control centre for every configurable behaviour, threshold, integration, policy, and preference on HustleSafe."
    >
        <div class="space-y-5">
            <section class="rounded-[2rem] border border-slate-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-slate-950/80">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div class="min-w-0 flex-1">
                        <label for="settings-search" class="text-xs font-black uppercase tracking-[0.22em] text-primary-600 dark:text-primary-300">
                            Settings Search
                        </label>
                        <div class="mt-2 flex min-h-12 items-center rounded-2xl border border-slate-200 bg-slate-50 px-4 ring-primary-500/20 transition focus-within:ring-4 dark:border-white/10 dark:bg-white/5">
                            <MagnifyingGlassIcon class="h-5 w-5 text-slate-400" aria-hidden="true" />
                            <input
                                id="settings-search"
                                v-model="searchQuery"
                                type="search"
                                class="min-h-12 w-full border-0 bg-transparent px-3 text-sm font-bold text-slate-950 placeholder:text-slate-400 focus:ring-0 dark:text-white"
                                placeholder="Search escrow, email, proposal limit, payout, API key..."
                            />
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="secondary-button" @click="changelogOpen = true">
                            View recent changes
                        </button>
                        <a :href="route('admin.settings.export')" class="primary-button">Export snapshot</a>
                    </div>
                </div>
                <p class="mt-3 text-sm font-semibold text-slate-500 dark:text-slate-400">
                    {{ resultSummary }}
                </p>
            </section>

            <section class="sticky top-16 z-20 max-w-full overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white/95 p-3 shadow-sm backdrop-blur-xl dark:border-white/10 dark:bg-slate-950/90">
                <div class="grid grid-cols-[6.75rem_minmax(0,1fr)] gap-3 sm:hidden">
                    <nav class="mobile-settings-rail max-h-72 space-y-1 overflow-y-auto rounded-2xl bg-slate-50 p-1 dark:bg-white/5" aria-label="Settings sections">
                        <button
                            v-for="section in filteredSections"
                            :key="section.key"
                            type="button"
                            class="relative min-h-10 w-full rounded-xl px-2 py-2 text-left text-[11px] font-black uppercase tracking-wide transition"
                            :class="activeSection === section.key ? 'bg-primary-600 text-white shadow-sm' : 'text-slate-500 hover:bg-white hover:text-primary-700 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-primary-100'"
                            @click="goToSection(section.key)"
                        >
                            <span class="block truncate">{{ compactLabel(section.label) }}</span>
                            <span
                                v-if="isDirty(section.key)"
                                class="absolute right-1.5 top-1.5 h-2 w-2 rounded-full bg-amber-400"
                                aria-label="Unsaved changes"
                            ></span>
                        </button>
                    </nav>

                    <div class="min-w-0 rounded-2xl border border-slate-200 bg-white p-3 dark:border-white/10 dark:bg-slate-950/80">
                        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-primary-600 dark:text-primary-300">Active section</p>
                        <h3 class="mt-1 truncate text-base font-black text-slate-950 dark:text-white">{{ activeTabSection?.label }}</h3>
                        <p class="mt-2 line-clamp-4 text-xs font-semibold leading-5 text-slate-500 dark:text-slate-400">
                            {{ activeTabSection?.description }}
                        </p>
                        <div class="mt-3 flex items-center justify-between gap-2">
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-slate-500 dark:bg-white/10 dark:text-slate-300">
                                {{ activeSectionPosition }}
                            </span>
                            <span v-if="activeTabSection && isDirty(activeTabSection.key)" class="rounded-full bg-amber-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-amber-800 dark:bg-amber-500/15 dark:text-amber-200">
                                Unsaved
                            </span>
                        </div>
                    </div>
                </div>

                <div class="settings-tabs hidden max-w-full grid-cols-2 gap-2 sm:grid lg:grid-cols-4 2xl:grid-cols-6" aria-label="Settings sections" role="tablist">
                    <button
                        v-for="section in filteredSections"
                        :key="section.key"
                        type="button"
                        role="tab"
                        :aria-selected="activeSection === section.key"
                        class="group relative min-h-12 min-w-0 rounded-2xl border px-3 py-2 text-left transition"
                        :class="activeSection === section.key ? 'border-primary-400 bg-primary-600 text-white shadow-lg shadow-primary-950/10 dark:border-primary-300 dark:bg-primary-500' : 'border-transparent bg-slate-50 text-slate-600 hover:border-primary-200 hover:bg-primary-50 hover:text-primary-800 dark:bg-white/5 dark:text-slate-300 dark:hover:border-primary-400/40 dark:hover:bg-primary-500/10 dark:hover:text-primary-100'"
                        @click="goToSection(section.key)"
                    >
                        <span class="block truncate text-sm font-black">{{ section.label }}</span>
                        <span class="mt-0.5 block truncate text-[11px] font-bold opacity-70">{{ compactLabel(section.label) }}</span>
                        <span
                            v-if="isDirty(section.key)"
                            class="absolute -right-1 -top-1 h-3 w-3 rounded-full bg-amber-400 ring-2 ring-white dark:ring-slate-950"
                            aria-label="Unsaved changes"
                        ></span>
                    </button>
                </div>
                <div v-if="activeTabSection" class="mt-3 hidden rounded-2xl bg-slate-50 px-4 py-3 dark:bg-white/5 sm:block">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-bold text-slate-600 dark:text-slate-300">
                            <span class="font-black text-slate-950 dark:text-white">{{ activeTabSection.label }}:</span>
                            {{ activeTabSection.description }}
                        </p>
                        <span v-if="isDirty(activeTabSection.key)" class="shrink-0 rounded-full bg-amber-100 px-3 py-1 text-[10px] font-black uppercase tracking-wide text-amber-800 dark:bg-amber-500/15 dark:text-amber-200">
                            Unsaved changes
                        </span>
                    </div>
                </div>
            </section>

            <MaintenanceControlPanel v-if="activeSection === 'maintenance' && (!searchQuery || filteredSections.some((s) => s.key === 'maintenance'))" />

            <div>
                <main class="space-y-5">
                    <section
                        v-for="section in filteredSections"
                        :id="`settings-${section.key}`"
                        :key="section.key"
                        class="settings-section rounded-[2rem] border bg-white p-4 shadow-sm dark:bg-slate-950/80 md:p-6"
                        :class="[
                            section.layout === 'danger' ? 'border-rose-300 ring-4 ring-rose-500/10 dark:border-rose-500/40' : 'border-slate-200 dark:border-white/10',
                            !searchQuery && activeSection !== section.key ? 'hidden' : '',
                        ]"
                    >
                        <div class="flex flex-col gap-3 border-b border-slate-200 pb-4 dark:border-white/10 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <p class="text-xs font-black uppercase tracking-[0.22em]" :class="section.layout === 'danger' ? 'text-rose-600 dark:text-rose-300' : 'text-primary-600 dark:text-primary-300'">
                                    {{ section.layout === 'danger' ? 'Proceed with extreme caution' : 'Configuration section' }}
                                </p>
                                <h2 class="mt-2 font-display text-2xl font-black text-slate-950 dark:text-white">{{ section.label }}</h2>
                                <p class="mt-1 max-w-3xl text-sm font-semibold text-slate-500 dark:text-slate-400">{{ section.description }}</p>
                            </div>
                            <button type="button" class="primary-button" :disabled="saving[section.key]" @click="saveSection(section)">
                                <span v-if="saved[section.key]">Settings saved</span>
                                <span v-else-if="isDirty(section.key)" class="text-amber-100">Unsaved changes · Save</span>
                                <span v-else>Save section</span>
                            </button>
                        </div>

                        <div v-if="section.key === 'verification'" class="mt-5 space-y-5">
                            <details class="rounded-2xl border border-primary-200 bg-primary-50 p-4 dark:border-primary-400/20 dark:bg-primary-500/10">
                                <summary class="cursor-pointer text-sm font-black text-primary-800 dark:text-primary-100">What each verification tier unlocks</summary>
                                <p class="mt-2 text-sm font-semibold text-primary-900/70 dark:text-primary-100/70">
                                    Tier 0 is unverified. Higher tiers progressively unlock larger Quest values, escrow limits, payouts, proposal volume, featured visibility, private Quests, and priority placement. When a limit is hit, users see an upgrade prompt explaining the next verification step.
                                </p>
                            </details>
                            <div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-white/10">
                                <table class="min-w-[980px] divide-y divide-slate-200 text-sm dark:divide-white/10">
                                    <thead class="bg-slate-50 dark:bg-white/5">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-black uppercase tracking-wide text-slate-500">Limit type</th>
                                            <th v-for="tier in [0, 1, 2, 3, 4]" :key="tier" class="px-3 py-3 text-left text-xs font-black uppercase tracking-wide text-slate-500">
                                                Tier {{ tier }}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-white/10">
                                        <tr v-for="group in matrixGroups(section)" :key="group.key">
                                            <td class="max-w-64 px-4 py-3 font-black text-slate-900 dark:text-white">{{ group.label }}</td>
                                            <td v-for="setting in group.settings" :key="setting.key" class="px-3 py-3 align-top">
                                                <SettingControl v-model="values[setting.key]" :setting="setting" compact />
                                                <p class="mt-1 text-[11px] font-semibold text-slate-400">{{ setting.current_note }}</p>
                                                <p class="mt-1 text-[11px] font-black text-primary-600 dark:text-primary-300">
                                                    Affects approx. {{ setting.impact_count }} users
                                                </p>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div v-else class="mt-5 space-y-5">
                            <div
                                v-if="section.key === 'financial'"
                                class="rounded-2xl border border-primary-200 bg-gradient-to-br from-primary-50/90 to-white p-4 dark:border-primary-400/25 dark:from-primary-500/10 dark:to-slate-950/80 sm:p-5"
                            >
                                <p class="text-xs font-black uppercase tracking-[0.18em] text-primary-800 dark:text-primary-200">Fee disclosure (shown to customers)</p>
                                <p class="mt-2 text-sm font-semibold text-slate-700 dark:text-slate-300">
                                    Changing <strong>Platform fee (%)</strong> below updates proposals, contracts, emails, legal pages, and every customer-facing surface after you save.
                                </p>
                                <ul class="mt-4 space-y-2 text-sm font-semibold leading-relaxed text-slate-800 dark:text-slate-200">
                                    <li>Paystack fee (client escrow funding): 1.5% of escrow fund + ₦100, capped at ₦2,000</li>
                                    <li>Paystack payout fee (freelancer withdrawal): ₦10–₦50 depending on bank</li>
                                    <li>VAT on platform fee: {{ liveVatPercent }}% (applies to platform fee only)</li>
                                    <li>Platform fee: <strong>{{ livePlatformFeePercent }}%</strong> of the job amount</li>
                                </ul>
                                <p class="mt-3 text-sm font-semibold text-slate-600 dark:text-slate-400">
                                    This covers: all gateway fees, VAT, and platform operation.
                                </p>
                            </div>
                            <p
                                v-if="section.key === 'maintenance'"
                                class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-950"
                            >
                                Use the <strong>Site maintenance switch</strong> above to turn workshop mode on or off. It saves immediately — you do not need to click Save section for the on/off state.
                            </p>
                            <div class="divide-y divide-slate-100 dark:divide-white/10">
                                <SettingRow
                                    v-for="setting in sectionSettings(section)"
                                    :key="setting.key"
                                    v-model="values[setting.key]"
                                    :setting="setting"
                                    :query="searchQuery"
                                />
                            </div>
                        </div>

                        <div v-if="errors[section.key]" class="mt-4 rounded-2xl border border-rose-200 bg-rose-50 p-3 text-sm font-bold text-rose-800 dark:border-rose-500/30 dark:bg-rose-950/40 dark:text-rose-100">
                            {{ errors[section.key] }}
                        </div>
                    </section>

                    <div v-if="!filteredSections.length" class="rounded-[2rem] border border-slate-200 bg-white p-10 text-center dark:border-white/10 dark:bg-slate-950/80">
                        <p class="font-display text-2xl font-black text-slate-950 dark:text-white">0 / No settings matched your search.</p>
                        <p class="mt-2 text-sm font-semibold text-slate-500 dark:text-slate-400">Try a broader term like escrow, payout, email, proposal, KYC, or security.</p>
                    </div>
                </main>
            </div>
        </div>

        <AdminSlideOver :open="changelogOpen" title="Settings Changelog" eyebrow="Last 50 changes" width-class="max-w-full sm:max-w-2xl" @close="changelogOpen = false">
            <div class="space-y-3">
                <article v-for="change in changelog" :key="change.id" class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-white/5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-black text-slate-950 dark:text-white">{{ change.label || change.key }}</p>
                            <p class="mt-1 text-xs font-semibold text-slate-500 dark:text-slate-400">Changed by {{ change.actor }} · {{ formatDate(change.created_at) }}</p>
                        </div>
                    </div>
                    <p class="mt-3 rounded-xl bg-slate-50 p-3 text-xs font-semibold text-slate-600 dark:bg-slate-950 dark:text-slate-300">
                        From <span class="font-black">{{ display(change.from) }}</span> to <span class="font-black">{{ display(change.to) }}</span>
                    </p>
                </article>
                <p v-if="!changelog.length" class="rounded-2xl p-4 text-sm font-bold text-slate-500 dark:text-slate-400">
                    0 / No settings changes have been recorded yet.
                </p>
            </div>
        </AdminSlideOver>
    </AdminShell>
</template>

<script setup>
import MaintenanceControlPanel from '@/Components/Admin/MaintenanceControlPanel.vue';
import AdminSlideOver from '@/Components/Admin/AdminSlideOver.vue';
import AdminShell from '@/Layouts/AdminShell.vue';
import { router } from '@inertiajs/vue3';
import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline';
import { computed, defineComponent, h, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';

const props = defineProps({
    sections: { type: Array, required: true },
    changelog: { type: Array, default: () => [] },
    meta: { type: Object, default: () => ({}) },
});

const searchQuery = ref('');
const activeSection = ref(props.sections[0]?.key || 'general');
const changelogOpen = ref(false);
const values = reactive({});
const initialValues = reactive({});
const saving = reactive({});
const saved = reactive({});
const errors = reactive({});

props.sections.forEach((section) => {
    section.settings.forEach((setting) => {
        if (setting.key === 'maintenance.enabled') {
            return;
        }
        values[setting.key] = setting.value;
        initialValues[setting.key] = setting.value;
    });
});

const searchableSections = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) {
        return props.sections;
    }

    return props.sections
        .map((section) => ({
            ...section,
            settings: section.settings.filter((setting) => searchableText(section, setting).includes(q)),
        }))
        .filter((section) => section.settings.length);
});

const filteredSections = computed(() => searchableSections.value);
const activeTabSection = computed(() => filteredSections.value.find((section) => section.key === activeSection.value) || filteredSections.value[0] || null);
const activeSectionPosition = computed(() => {
    const index = filteredSections.value.findIndex((section) => section.key === activeSection.value);
    if (index === -1) {
        return '0 / 0';
    }

    return `${index + 1} / ${filteredSections.value.length}`;
});
const resultSummary = computed(() => {
    const count = filteredSections.value.reduce((total, section) => total + section.settings.length, 0);
    if (!searchQuery.value.trim()) {
        return `${props.sections.length} sections and ${count} settings available.`;
    }

    return `${count} matching settings across ${filteredSections.value.length} sections.`;
});

const livePlatformFeePercent = computed(() => {
    const raw = values['financial.platform_fee_percent'];
    const n = Number(raw);
    if (!Number.isFinite(n)) {
        return props.meta?.platform_fee_disclosure?.platform_fee_percent_label ?? '12';
    }

    return String(n).replace(/\.0+$/, '').replace(/(\.\d*?)0+$/, '$1');
});

const liveVatPercent = computed(() => {
    const raw = values['financial.vat_percent'];
    const n = Number(raw);
    if (!Number.isFinite(n)) {
        return props.meta?.platform_fee_disclosure?.vat_percent_label ?? '7.5';
    }

    return String(n).replace(/\.0+$/, '').replace(/(\.\d*?)0+$/, '$1');
});

watch(filteredSections, (sections) => {
    if (sections.length && !sections.some((section) => section.key === activeSection.value)) {
        activeSection.value = sections[0].key;
    }
});

function syncMaintenanceSetting() {
    /* maintenance.enabled is controlled only via MaintenanceControlPanel — never via Save section */
}

function onMaintenanceChanged() {
    syncMaintenanceSetting();
}

function sectionSettings(section) {
    if (section.key === 'maintenance') {
        return section.settings.filter((setting) => setting.key !== 'maintenance.enabled');
    }

    return section.settings;
}

onMounted(() => {
    const section = new URLSearchParams(window.location.search).get('section');
    if (section && props.sections.some((s) => s.key === section)) {
        activeSection.value = section;
        goToSection(section);
    }

    window.addEventListener('admin:maintenance-changed', onMaintenanceChanged);
});

onBeforeUnmount(() => {
    window.removeEventListener('admin:maintenance-changed', onMaintenanceChanged);
});

function searchableText(section, setting) {
    return [section.label, section.description, setting.label, setting.description, setting.key].join(' ').toLowerCase();
}

function goToSection(key) {
    activeSection.value = key;
    if (!searchQuery.value.trim()) {
        return;
    }

    document.getElementById(`settings-${key}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function compactLabel(label) {
    const labels = {
        'Verification Tiers & Limits': 'Tiers',
        'Financial & Escrow': 'Finance',
        'Notifications & Communications': 'Comms',
        'Security & Access': 'Security',
        'Quest Settings': 'Quests',
        'Proposal Settings': 'Proposals',
        'User & Profile Settings': 'Profiles',
        'Featured Listings & Promotions': 'Promos',
        'Dispute & Resolution': 'Disputes',
        'Payment Gateway': 'Payments',
        'Identity Verification APIs': 'KYC APIs',
        'Email & SMS Providers': 'Email/SMS',
        'Content & Moderation': 'Moderation',
        'Referral Programme': 'Referrals',
        'Analytics & Tracking': 'Analytics',
        'Maintenance & System': 'System',
        'Legal & Compliance': 'Legal',
        'Danger Zone': 'Danger',
    };

    return labels[label] || label;
}

function sectionChangedKeys(section) {
    return section.settings
        .map((setting) => setting.key)
        .filter((key) => JSON.stringify(values[key]) !== JSON.stringify(initialValues[key]));
}

function isDirty(sectionKey) {
    const section = props.sections.find((item) => item.key === sectionKey);
    return section ? sectionChangedKeys(section).length > 0 : false;
}

function saveSection(section) {
    const changed = sectionChangedKeys(section);
    const payload = Object.fromEntries(changed.map((key) => [key, values[key]]));

    saving[section.key] = true;
    saved[section.key] = false;
    errors[section.key] = '';

    router.patch(route('admin.settings.update', section.key), { settings: payload }, {
        preserveScroll: true,
        onSuccess: () => {
            changed.forEach((key) => {
                initialValues[key] = values[key];
            });
            saved[section.key] = true;
            window.setTimeout(() => {
                saved[section.key] = false;
            }, 3000);
        },
        onError: (response) => {
            errors[section.key] = Object.values(response)[0] || 'Please review the highlighted settings and try again.';
        },
        onFinish: () => {
            saving[section.key] = false;
        },
    });
}

function matrixGroups(section) {
    const groups = new Map();
    section.settings.forEach((setting) => {
        const key = setting.group || setting.key;
        if (!groups.has(key)) {
            groups.set(key, { key, label: setting.label.replace(/ · Tier \d$/, ''), settings: [] });
        }
        groups.get(key).settings.push(setting);
    });

    return Array.from(groups.values()).map((group) => ({
        ...group,
        settings: group.settings.sort((a, b) => Number(a.tier || 0) - Number(b.tier || 0)),
    }));
}

function display(value) {
    if (value === null || value === undefined || value === '') {
        return 'Not set';
    }
    if (typeof value === 'boolean') {
        return value ? 'Enabled' : 'Disabled';
    }
    if (Array.isArray(value)) {
        return value.join(', ');
    }

    return String(value);
}

function formatDate(value) {
    if (!value) {
        return 'Unknown date';
    }

    return new Intl.DateTimeFormat('en-NG', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}

function beforeUnload(event) {
    const dirty = props.sections.some((section) => isDirty(section.key));
    if (!dirty) {
        return;
    }

    event.preventDefault();
    event.returnValue = '';
}

window.addEventListener('beforeunload', beforeUnload);
onBeforeUnmount(() => window.removeEventListener('beforeunload', beforeUnload));

const SettingRow = defineComponent({
    props: {
        setting: { type: Object, required: true },
        modelValue: { required: false },
        query: { type: String, default: '' },
    },
    emits: ['update:modelValue'],
    setup(rowProps, { emit }) {
        return () => h('div', {
            class: [
                'grid gap-3 py-4 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,24rem)] lg:items-center',
                rowProps.setting.sensitive ? 'rounded-2xl bg-amber-50 px-4 dark:bg-amber-500/10' : '',
            ],
        }, [
            h('div', [
                h('div', { class: 'flex items-center gap-2' }, [
                    rowProps.setting.sensitive ? h('span', { class: 'rounded-full bg-amber-200 px-2 py-1 text-[10px] font-black uppercase tracking-wide text-amber-900 dark:bg-amber-400/20 dark:text-amber-100' }, 'Locked') : null,
                    h('p', { class: 'text-sm font-black text-slate-950 dark:text-white', innerHTML: highlight(rowProps.setting.label, rowProps.query) }),
                ]),
                h('p', { class: 'mt-1 text-sm font-semibold text-slate-500 dark:text-slate-400', innerHTML: highlight(rowProps.setting.description, rowProps.query) }),
                h('p', { class: 'mt-2 text-xs font-bold text-slate-400' }, rowProps.setting.current_note),
            ]),
            h(SettingControl, {
                modelValue: rowProps.modelValue,
                setting: rowProps.setting,
                'onUpdate:modelValue': (value) => emit('update:modelValue', value),
            }),
        ]);
    },
});

const SettingControl = defineComponent({
    props: {
        setting: { type: Object, required: true },
        modelValue: { required: false },
        compact: { type: Boolean, default: false },
    },
    emits: ['update:modelValue'],
    setup(controlProps, { emit }) {
        const inputClass = computed(() => [
            'min-h-11 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-bold text-slate-950 shadow-sm focus:border-primary-400 focus:ring-primary-400 dark:border-white/10 dark:bg-slate-950 dark:text-white',
            controlProps.compact ? 'min-w-28' : '',
        ].join(' '));

        return () => {
            const setting = controlProps.setting;
            if (setting.type === 'boolean') {
                return h('button', {
                    type: 'button',
                    class: ['inline-flex min-h-11 w-full items-center justify-between rounded-xl border px-3 text-sm font-black transition', controlProps.modelValue ? 'border-primary-300 bg-primary-50 text-primary-800 dark:border-primary-400/40 dark:bg-primary-500/10 dark:text-primary-100' : 'border-slate-200 bg-slate-50 text-slate-600 dark:border-white/10 dark:bg-white/5 dark:text-slate-300'],
                    onClick: () => emit('update:modelValue', !controlProps.modelValue),
                }, [h('span', controlProps.modelValue ? 'Enabled' : 'Disabled'), h('span', { class: ['h-6 w-11 rounded-full p-0.5 transition', controlProps.modelValue ? 'bg-primary-600' : 'bg-slate-300 dark:bg-slate-700'] }, [h('span', { class: ['block h-5 w-5 rounded-full bg-white transition', controlProps.modelValue ? 'translate-x-5' : 'translate-x-0'] })])]);
            }
            if (setting.type === 'select') {
                return h('select', {
                    class: inputClass.value,
                    value: controlProps.modelValue,
                    onChange: (event) => emit('update:modelValue', event.target.value),
                }, Object.entries(setting.options || {}).map(([value, label]) => h('option', { value }, label)));
            }
            if (setting.type === 'readonly') {
                return h('div', { class: 'min-h-11 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-black text-slate-500 dark:border-white/10 dark:bg-white/5 dark:text-slate-300' }, display(controlProps.modelValue));
            }
            if (setting.type === 'money') {
                return h('div', { class: 'flex min-h-11 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm focus-within:border-primary-400 focus-within:ring-1 focus-within:ring-primary-400 dark:border-white/10 dark:bg-slate-950' }, [
                    h('span', { class: 'flex items-center border-r border-slate-200 px-3 text-sm font-black text-slate-600 dark:border-white/10 dark:text-slate-300' }, '₦'),
                    h('input', {
                        class: 'min-w-0 flex-1 border-0 bg-transparent px-3 py-2 text-sm font-bold text-slate-950 focus:outline-none focus:ring-0 dark:text-white',
                        type: 'number',
                        min: 0,
                        step: 1,
                        value: controlProps.modelValue ?? '',
                        placeholder: 'Amount in naira',
                        onInput: (event) => emit('update:modelValue', event.target.value === '' ? 0 : event.target.valueAsNumber),
                    }),
                ]);
            }

            return h('input', {
                class: inputClass.value,
                type: inputType(setting.type),
                value: controlProps.modelValue,
                placeholder: setting.type === 'tags' ? 'Comma separated values' : '',
                onInput: (event) => emit('update:modelValue', setting.type === 'number' || setting.type === 'money' ? event.target.valueAsNumber : event.target.value),
            });
        };
    },
});

function inputType(type) {
    if (['email', 'url', 'password', 'date', 'datetime-local', 'time', 'color'].includes(type)) {
        return type;
    }
    if (['number', 'money'].includes(type)) {
        return 'number';
    }

    return 'text';
}

function escapeHtml(value) {
    return String(value || '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function highlight(text, query) {
    const safe = escapeHtml(text);
    const q = query.trim();
    if (!q) {
        return safe;
    }

    return safe.replace(new RegExp(`(${q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'ig'), '<mark class="rounded bg-amber-200 px-0.5 text-slate-950">$1</mark>');
}
</script>

<style scoped>
.settings-section {
    scroll-margin-top: 7rem;
}

.settings-tabs {
    scrollbar-width: none;
}

.settings-tabs::-webkit-scrollbar {
    display: none;
}

.mobile-settings-rail {
    scrollbar-width: thin;
    scrollbar-color: rgb(14 165 233 / 0.45) transparent;
}

.mobile-settings-rail::-webkit-scrollbar {
    width: 0.35rem;
}

.mobile-settings-rail::-webkit-scrollbar-thumb {
    border-radius: 999px;
    background: rgb(14 165 233 / 0.45);
}

.primary-button,
.secondary-button {
    min-height: 2.75rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.9rem;
    padding: 0.7rem 1rem;
    font-size: 0.75rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    transition: all 0.2s ease;
}

.primary-button {
    background: rgb(14 165 233);
    color: white;
}

.primary-button:disabled {
    opacity: 0.65;
}

.secondary-button {
    border: 1px solid rgb(226 232 240);
    background: white;
    color: rgb(15 23 42);
}

:global(.dark) .secondary-button {
    border-color: rgb(255 255 255 / 0.1);
    background: rgb(255 255 255 / 0.05);
    color: white;
}
</style>
