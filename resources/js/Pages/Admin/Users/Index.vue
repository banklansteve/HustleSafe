<template>
    <AdminShell
        title="Advanced User Management"
        subtitle="Search, segment, inspect, sanction, communicate, and audit every user from one fast workspace."
    >
        <div class="space-y-5">
            <AdminPanel eyebrow="User intelligence" title="Member directory">
                <template #actions>
                    <div class="flex flex-wrap gap-2">
                        <a :href="exportUrl" class="rounded-xl px-4 py-2 text-xs font-black uppercase tracking-wide" :class="shell.btnGhost">
                            Export CSV
                        </a>
                        <Link :href="route('admin.management.index', { resource: 'user_verifications' })" class="rounded-xl px-4 py-2 text-xs font-black uppercase tracking-wide" :class="shell.btnGhost">
                            Verification queue
                        </Link>
                        <button type="button" class="rounded-xl px-4 py-2 text-xs font-black uppercase tracking-wide" :class="shell.btnPrimary" @click="saveCurrentSegment">
                            Save segment
                        </button>
                    </div>
                </template>

                <div class="grid gap-3 lg:grid-cols-[1.5fr_repeat(4,minmax(0,1fr))]">
                    <input
                        v-model="localFilters.q"
                        type="search"
                        placeholder="Search name, company, email, phone, NIN…"
                        class="rounded-2xl border px-4 py-3 text-sm font-semibold"
                        :class="shell.input"
                        @input="debouncedApply"
                    />
                    <select v-model="localFilters.role" class="rounded-2xl border px-3 py-3 text-sm font-bold" :class="shell.input" @change="applyFilters">
                        <option value="">All roles</option>
                        <option value="client">Clients</option>
                        <option value="freelancer">Freelancers</option>
                        <option value="admin">Admins</option>
                    </select>
                    <select v-model="localFilters.status" class="rounded-2xl border px-3 py-3 text-sm font-bold" :class="shell.input" @change="applyFilters">
                        <option value="">All statuses</option>
                        <option value="active">Active</option>
                        <option value="under_review">Under review</option>
                        <option value="suspended">Suspended</option>
                        <option value="banned">Banned</option>
                        <option value="closed">Closed</option>
                    </select>
                    <select v-model="localFilters.state_id" class="rounded-2xl border px-3 py-3 text-sm font-bold" :class="shell.input" @change="applyFilters">
                        <option value="">All states</option>
                        <option v-for="state in meta.states" :key="state.id" :value="state.id">{{ state.name }}</option>
                    </select>
                    <select v-model="localFilters.category_id" class="rounded-2xl border px-3 py-3 text-sm font-bold" :class="shell.input" @change="applyFilters">
                        <option value="">All freelancer categories</option>
                        <option v-for="category in meta.categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                    </select>
                </div>

                <div class="mt-3 grid gap-3 md:grid-cols-4">
                    <input v-model="localFilters.trust_min" type="number" min="0" max="100" placeholder="Trust min" class="rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" @change="applyFilters" />
                    <input v-model="localFilters.trust_max" type="number" min="0" max="100" placeholder="Trust max" class="rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" @change="applyFilters" />
                    <input v-model="localFilters.joined_from" type="date" class="rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" @change="applyFilters" />
                    <input v-model="localFilters.joined_to" type="date" class="rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" @change="applyFilters" />
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <label v-for="toggle in booleanFilters" :key="toggle.key" class="flex items-center gap-2 rounded-full border px-3 py-2 text-xs font-black" :class="shell.btnGhost">
                        <input v-model="localFilters[toggle.key]" type="checkbox" class="rounded border-slate-300 text-primary-600" @change="applyFilters" />
                        <span>{{ toggle.label }}</span>
                    </label>
                    <select class="rounded-full border px-3 py-2 text-xs font-black" :class="shell.input" @change="loadSegment">
                        <option value="">Recall saved segment</option>
                        <option v-for="segment in meta.segments" :key="segment.id" :value="segment.id">{{ segment.name }}</option>
                    </select>
                </div>

                <div v-if="activePills.length" class="mt-4 flex flex-wrap gap-2">
                    <button
                        v-for="pill in activePills"
                        :key="pill.key"
                        type="button"
                        class="rounded-full bg-primary-100 px-3 py-1 text-xs font-black text-primary-800 dark:bg-primary-400/15 dark:text-primary-100"
                        @click="clearFilter(pill.key)"
                    >
                        {{ pill.label }} ×
                    </button>
                </div>
            </AdminPanel>

            <AdminPanel v-if="selectedIds.length" eyebrow="Bulk actions" :title="`${selectedIds.length} selected`">
                <div class="grid gap-3 lg:grid-cols-[1fr_1fr_2fr_auto]">
                    <select v-model="bulk.action" class="rounded-2xl border px-3 py-3 text-sm font-bold" :class="shell.input">
                        <option value="notification">Send in-app notification</option>
                        <option value="email">Send email</option>
                        <option value="apply_tag">Apply tag</option>
                        <option value="remove_tag">Remove tag</option>
                        <option value="suspend">Suspend accounts</option>
                        <option value="badge">Assign badge</option>
                        <option value="export">Export selected CSV</option>
                    </select>
                    <input v-model="bulk.subject" type="text" placeholder="Subject, tag, badge, or reason" class="rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                    <textarea v-model="bulk.message" rows="1" placeholder="Message or extra context" class="rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                    <button type="button" class="rounded-2xl px-5 py-3 text-sm font-black uppercase" :class="shell.btnPrimary" @click="runBulkAction">
                        Apply
                    </button>
                </div>
            </AdminPanel>

            <AdminPanel title="Users" :description="`${users.total ?? 0} accounts found`">
                <div class="hidden overflow-x-auto lg:block">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10">
                        <thead>
                            <tr class="text-left text-[10px] font-black uppercase tracking-wider text-slate-500">
                                <th class="px-3 py-3"><input type="checkbox" :checked="allVisibleSelected" @change="toggleVisible" /></th>
                                <th class="px-3 py-3"><button type="button" @click="toggleSort('name')">User</button></th>
                                <th class="px-3 py-3">Role</th>
                                <th class="px-3 py-3">Location</th>
                                <th class="px-3 py-3">Trust</th>
                                <th class="px-3 py-3">Activity</th>
                                <th class="px-3 py-3">Status</th>
                                <th class="px-3 py-3">Disputes</th>
                                <th class="px-3 py-3"><button type="button" @click="toggleSort('created_at')">Joined</button></th>
                                <th class="px-3 py-3"><button type="button" @click="toggleSort('last_active_at')">Last active</button></th>
                                <th class="px-3 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-white/10">
                            <tr v-for="user in users.data" :key="user.id" class="hover:bg-primary-50/60 dark:hover:bg-white/[0.03]">
                                <td class="px-3 py-4"><input v-model="selectedIds" type="checkbox" :value="user.id" /></td>
                                <td class="px-3 py-4">
                                    <button type="button" class="flex items-center gap-3 text-left" @click="openProfile(user)">
                                        <img :src="user.avatar_url || defaultAvatar(user)" alt="" class="h-10 w-10 rounded-2xl object-cover ring-1 ring-slate-200 dark:ring-white/10" />
                                        <span>
                                            <span class="block font-black text-slate-900 dark:text-white">{{ user.name }}</span>
                                            <span class="block text-xs font-semibold text-slate-500">{{ user.email }}</span>
                                            <span v-if="user.company_name" class="block text-xs font-semibold text-primary-600">{{ user.company_name }}</span>
                                        </span>
                                    </button>
                                </td>
                                <td class="px-3 py-4"><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black capitalize dark:bg-white/10">{{ user.role_label }}</span></td>
                                <td class="px-3 py-4 text-xs font-bold text-slate-600 dark:text-slate-300">{{ [user.city, user.state].filter(Boolean).join(', ') || '—' }}</td>
                                <td class="px-3 py-4">
                                    <div class="w-28">
                                        <div class="flex justify-between text-xs font-black"><span>{{ user.trust_score }}</span><span>/100</span></div>
                                        <div class="mt-1 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-white/10">
                                            <div class="h-full rounded-full" :class="trustColor(user.trust_band)" :style="{ width: `${user.trust_score}%` }" />
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-4 font-black">{{ user.activity_label }}</td>
                                <td class="px-3 py-4"><span class="rounded-full px-3 py-1 text-xs font-black capitalize" :class="statusClass(user.account_status)">{{ user.account_status.replace('_', ' ') }}</span></td>
                                <td class="px-3 py-4 font-black" :class="user.open_disputes_count ? 'text-rose-600' : 'text-slate-500'">{{ user.open_disputes_count }}</td>
                                <td class="px-3 py-4 text-xs font-bold">{{ dateLabel(user.joined_at) }}</td>
                                <td class="px-3 py-4 text-xs font-bold">{{ dateLabel(user.last_active_at) }}</td>
                                <td class="px-3 py-4">
                                    <div class="flex gap-2">
                                        <button type="button" class="text-xs font-black text-primary-700 underline dark:text-primary-300" @click="openProfile(user)">View</button>
                                        <button type="button" class="text-xs font-black text-amber-600 underline" @click="quickSuspend(user)">Suspend</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="grid gap-3 lg:hidden">
                    <button
                        v-for="user in users.data"
                        :key="user.id"
                        type="button"
                        class="rounded-3xl border p-4 text-left shadow-sm"
                        :class="shell.card"
                        @click="openProfile(user)"
                    >
                        <div class="flex items-start gap-3">
                            <input v-model="selectedIds" type="checkbox" :value="user.id" class="mt-2" @click.stop />
                            <img :src="user.avatar_url || defaultAvatar(user)" alt="" class="h-12 w-12 rounded-2xl object-cover" />
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="truncate font-black">{{ user.name }}</p>
                                    <span class="rounded-full px-2 py-1 text-[10px] font-black capitalize" :class="statusClass(user.account_status)">{{ user.account_status.replace('_', ' ') }}</span>
                                </div>
                                <p class="truncate text-xs font-semibold text-slate-500">{{ user.email }}</p>
                                <p class="mt-2 text-xs font-bold">{{ user.role_label }} · {{ [user.city, user.state].filter(Boolean).join(', ') || 'No location' }}</p>
                                <div class="mt-3 flex items-center justify-between text-xs font-black">
                                    <span>Trust {{ user.trust_score }}/100</span>
                                    <span>{{ user.activity_label }}</span>
                                </div>
                            </div>
                        </div>
                    </button>
                </div>
            </AdminPanel>

            <nav v-if="users.links?.length > 3" class="flex flex-wrap justify-center gap-2">
                <component
                    :is="link.url ? Link : 'span'"
                    v-for="link in users.links"
                    :key="String(link.label) + (link.url || 'x')"
                    :href="link.url || undefined"
                    preserve-state
                    preserve-scroll
                    class="min-w-[2.5rem] rounded-lg px-3 py-1.5 text-center text-xs font-bold transition"
                    :class="[link.active ? shell.btnPrimary : shell.btnGhost, !link.url ? 'pointer-events-none opacity-40' : '']"
                >
                    <span v-html="link.label" />
                </component>
            </nav>
        </div>

        <AdminSlideOver :open="slideOpen" :title="profileTitle" eyebrow="User profile" @close="slideOpen = false">
            <div v-if="profileLoading" class="rounded-3xl border p-6 text-sm font-bold" :class="shell.card">Loading profile…</div>
            <template v-else-if="profile">
                <div class="space-y-5">
                    <div class="flex items-start gap-4">
                        <img :src="profile.overview.user.avatar_url || defaultAvatar(profile.overview.user)" alt="" class="h-16 w-16 rounded-3xl object-cover" />
                        <div class="min-w-0 flex-1">
                            <h3 class="text-xl font-black">{{ profile.overview.user.name }}</h3>
                            <p class="text-sm font-semibold text-slate-500">{{ profile.overview.user.email }}</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <span v-for="tag in profile.overview.user.tags" :key="tag.id" class="rounded-full bg-primary-100 px-3 py-1 text-xs font-black text-primary-800 dark:bg-primary-400/15 dark:text-primary-100">{{ tag.name }}</span>
                                <span v-for="badge in profile.overview.user.badges" :key="badge.id" class="rounded-full bg-amber-100 px-3 py-1 text-xs font-black text-amber-800 dark:bg-amber-400/15 dark:text-amber-100">{{ badge.name }}</span>
                            </div>
                        </div>
                    </div>

                    <AdminTabs :model-value="activeTab" :tabs="tabs" id-prefix="user-profile-tab" aria-label="User profile sections" @update:model-value="loadTab" />

                    <AdminTabPanel v-model="activeTab" value="overview" id-prefix="user-profile-tab" class="space-y-5">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <InfoCard label="Phone" :value="profile.overview.user.phone || '—'" />
                            <InfoCard label="Location" :value="[profile.overview.user.city, profile.overview.user.state].filter(Boolean).join(', ') || '—'" />
                            <InfoCard label="Last login" :value="dateLabel(profile.overview.profile.last_login_at)" />
                            <InfoCard label="Device" :value="profile.overview.profile.last_login_device || '—'" />
                        </div>
                        <div class="rounded-3xl border p-4" :class="shell.card">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <h4 class="font-black">Verification Engine</h4>
                                    <p class="mt-1 text-sm font-bold text-slate-500">
                                        Earned L{{ profile.overview.verification_engine?.earned_level ?? 0 }} · Effective L{{ profile.overview.verification_engine?.effective_level ?? 0 }}
                                    </p>
                                    <p v-if="profile.overview.verification_engine?.cooldown?.active" class="mt-2 text-xs font-black text-amber-600">
                                        Cool-down active until {{ dateLabel(profile.overview.verification_engine.cooldown.expires_at) }}
                                    </p>
                                    <p v-if="profile.overview.verification_engine?.restriction?.active" class="mt-2 text-xs font-black text-rose-600">
                                        Restricted: {{ profile.overview.verification_engine.restriction.reason }}
                                    </p>
                                </div>
                                <div class="grid gap-2 text-xs font-black sm:grid-cols-2">
                                    <span class="rounded-2xl bg-primary-50 px-3 py-2 text-primary-700 dark:bg-primary-400/15 dark:text-primary-100">Post {{ money(profile.overview.verification_engine?.client_posting_limit_minor) }}</span>
                                    <span class="rounded-2xl bg-primary-50 px-3 py-2 text-primary-700 dark:bg-primary-400/15 dark:text-primary-100">Propose {{ money(profile.overview.verification_engine?.freelancer_proposal_limit_minor) }}</span>
                                </div>
                            </div>
                            <div v-if="profile.overview.verification_engine?.anomaly_flags?.length" class="mt-4 grid gap-2">
                                <p class="text-[10px] font-black uppercase tracking-wider text-rose-600">Anomaly flags</p>
                                <div v-for="flag in profile.overview.verification_engine.anomaly_flags" :key="flag.id" class="rounded-2xl border p-3 text-xs font-bold" :class="shell.card">
                                    {{ flag.type.replace(/_/g, ' ') }} · {{ flag.status }} · {{ dateLabel(flag.created_at) }}
                                </div>
                            </div>
                        </div>
                        <div class="rounded-3xl border p-4" :class="shell.card">
                            <h4 class="font-black">Trust breakdown</h4>
                            <div class="mt-3 space-y-3">
                                <div v-for="item in profile.overview.trust" :key="item.label">
                                    <div class="flex justify-between text-xs font-black"><span>{{ item.label }} · {{ item.weight }}%</span><span>{{ item.score }}/100</span></div>
                                    <div class="mt-1 h-2 rounded-full bg-slate-100 dark:bg-white/10">
                                        <div class="h-full rounded-full bg-primary-600" :style="{ width: `${item.score}%` }" />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <ListBlock title="Verification checks" :items="profile.overview.verification" empty="No verification records yet." />
                        <ListBlock title="Sanctions" :items="profile.overview.sanctions" empty="No sanctions on this user." />
                    </AdminTabPanel>

                    <AdminTabPanel v-model="activeTab" value="notes" id-prefix="user-profile-tab" class="space-y-4">
                        <form class="space-y-3 rounded-3xl border p-4" :class="shell.card" @submit.prevent="addNote">
                            <textarea v-model="noteBody" rows="4" class="w-full rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" placeholder="Write a private append-only admin note…" required />
                            <label class="flex items-center gap-2 text-xs font-bold"><input v-model="shareNote" type="checkbox" /> Share context with regular admins</label>
                            <button type="submit" class="rounded-xl px-4 py-2 text-xs font-black uppercase" :class="shell.btnPrimary">Add note</button>
                        </form>
                        <ListBlock title="Admin notes" :items="profile.tabData" empty="No admin notes yet." />
                    </AdminTabPanel>

                    <AdminTabPanel v-for="tab in detailTabs" :key="tab.key" v-model="activeTab" :value="tab.key" id-prefix="user-profile-tab" class="space-y-4">
                        <div v-if="activeTab === 'activity'" class="grid gap-3 sm:grid-cols-2">
                            <input v-model="tabSearch" type="search" placeholder="Search this tab…" class="rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                            <select v-model="tabCategory" class="rounded-2xl border px-3 py-3 text-sm font-bold" :class="shell.input">
                                <option value="">All event types</option>
                                <option value="admin">Admin</option>
                                <option value="users">Users</option>
                                <option value="quests">Quests</option>
                                <option value="financial">Financial</option>
                                <option value="disputes">Disputes</option>
                            </select>
                        </div>
                        <ListBlock :title="tabTitle" :items="filteredTabData" empty="Nothing to show here yet." />
                    </AdminTabPanel>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <button type="button" class="rounded-2xl px-4 py-3 text-sm font-black" :class="shell.btnGhost" @click="openCommunication('notification')">Send message</button>
                        <button type="button" class="rounded-2xl px-4 py-3 text-sm font-black" :class="shell.btnGhost" @click="openCommunication('email')">Send email</button>
                        <button type="button" class="rounded-2xl px-4 py-3 text-sm font-black text-amber-700 ring-1 ring-amber-300 dark:text-amber-200" @click="openSanction">Apply sanction</button>
                        <button v-if="$page.props.auth.user?.role?.slug === 'super_admin'" type="button" class="rounded-2xl bg-slate-950 px-4 py-3 text-sm font-black text-white dark:bg-white dark:text-slate-950" @click="startImpersonation">
                            Impersonate user
                        </button>
                    </div>
                </div>
            </template>
        </AdminSlideOver>
    </AdminShell>
</template>

<script setup>
import InfoCard from '@/Components/Admin/InfoCard.vue';
import ListBlock from '@/Components/Admin/ListBlock.vue';
import AdminPanel from '@/Components/Admin/AdminPanel.vue';
import AdminSlideOver from '@/Components/Admin/AdminSlideOver.vue';
import AdminTabPanel from '@/Components/Admin/AdminTabPanel.vue';
import AdminTabs from '@/Components/Admin/AdminTabs.vue';
import { useTabState } from '@/composables/useTabState';
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';
import AdminShell from '@/Layouts/AdminShell.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref, watch } from 'vue';

const props = defineProps({
    users: { type: Object, required: true },
    filters: { type: Object, required: true },
    meta: { type: Object, required: true },
});

const { shell } = useInjectedAdminTheme();

const localFilters = reactive({
    q: props.filters.q || '',
    role: props.filters.role || '',
    status: props.filters.status || '',
    state_id: props.filters.state_id || '',
    category_id: props.filters.category_id || '',
    trust_min: props.filters.trust_min || '',
    trust_max: props.filters.trust_max || '',
    joined_from: props.filters.joined_from || '',
    joined_to: props.filters.joined_to || '',
    verified: Boolean(props.filters.verified),
    open_disputes: Boolean(props.filters.open_disputes),
    flagged: Boolean(props.filters.flagged),
    per_page: props.filters.per_page || 15,
});

const selectedIds = ref([]);
const sort = ref([]);
const slideOpen = ref(false);
const profileLoading = ref(false);
const selectedUser = ref(null);
const profile = ref(null);
const profileCache = ref({});
const tabSearch = ref('');
const tabCategory = ref('');
const noteBody = ref('');
const shareNote = ref(false);
const bulk = reactive({ action: 'notification', subject: '', message: '' });

const tabs = [
    { key: 'overview', label: 'Overview' },
    { key: 'activity', label: 'Activity' },
    { key: 'financials', label: 'Financials' },
    { key: 'contracts', label: 'Contracts' },
    { key: 'disputes', label: 'Disputes' },
    { key: 'reviews', label: 'Reviews' },
    { key: 'notes', label: 'Admin notes' },
];
const detailTabs = tabs.filter((tab) => !['overview', 'notes'].includes(tab.key));
const { activeTab, setTab } = useTabState(tabs.map((tab) => tab.key), 'overview', {
    extraParams: () => ({ user: selectedUser.value?.id }),
    writeDefault: false,
});

const booleanFilters = [
    { key: 'verified', label: 'Verified' },
    { key: 'open_disputes', label: 'Has open disputes' },
    { key: 'flagged', label: 'Flagged' },
];

const exportUrl = computed(() => route('admin.users.export', cleanFilters()));
const profileTitle = computed(() => selectedUser.value?.name || 'User profile');
const allVisibleSelected = computed(() => (props.users.data || []).length > 0 && (props.users.data || []).every((user) => selectedIds.value.includes(user.id)));
const tabTitle = computed(() => tabs.find((tab) => tab.key === activeTab.value)?.label || 'Details');
const activePills = computed(() => Object.entries(localFilters)
    .filter(([key, value]) => key !== 'per_page' && value !== '' && value !== false && value !== null && value !== undefined)
    .map(([key, value]) => ({ key, label: `${key.replace(/_/g, ' ')}: ${value === true ? 'yes' : value}` })));

const filteredTabData = computed(() => {
    const items = Array.isArray(profile.value?.tabData) ? profile.value.tabData : [];
    const q = tabSearch.value.trim().toLowerCase();

    return items.filter((item) => {
        const matchesSearch = !q || JSON.stringify(item).toLowerCase().includes(q);
        const matchesCategory = !tabCategory.value || item.category === tabCategory.value || item.type === tabCategory.value;

        return matchesSearch && matchesCategory;
    });
});

let debounceTimer = null;

function cleanFilters() {
    const data = { ...localFilters };
    Object.keys(data).forEach((key) => {
        if (data[key] === '' || data[key] === false || data[key] === null || data[key] === undefined) {
            delete data[key];
        }
    });
    if (sort.value.length) {
        data.sort = sort.value;
    }

    return data;
}

function applyFilters() {
    router.get(route('admin.users.index'), cleanFilters(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['users', 'filters', 'meta'],
    });
}

function debouncedApply() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(applyFilters, 250);
}

function clearFilter(key) {
    localFilters[key] = typeof localFilters[key] === 'boolean' ? false : '';
    applyFilters();
}

function loadSegment(event) {
    const segment = props.meta.segments.find((item) => String(item.id) === String(event.target.value));
    if (!segment) return;

    Object.keys(localFilters).forEach((key) => {
        localFilters[key] = segment.filters[key] ?? (typeof localFilters[key] === 'boolean' ? false : '');
    });
    applyFilters();
}

async function saveCurrentSegment() {
    const name = window.prompt('Segment name');
    if (!name) return;

    await window.axios.post(route('admin.users.segments.store'), { name, filters: cleanFilters() });
    router.reload({ only: ['meta'] });
}

function toggleSort(column) {
    const existing = sort.value.find((item) => item.column === column);
    if (!existing) {
        sort.value.push({ column, direction: 'asc' });
    } else if (existing.direction === 'asc') {
        existing.direction = 'desc';
    } else {
        sort.value = sort.value.filter((item) => item.column !== column);
    }
    applyFilters();
}

function toggleVisible(event) {
    const ids = (props.users.data || []).map((user) => user.id);
    selectedIds.value = event.target.checked
        ? Array.from(new Set([...selectedIds.value, ...ids]))
        : selectedIds.value.filter((id) => !ids.includes(id));
}

async function openProfile(user) {
    selectedUser.value = user;
    profileCache.value = {};
    slideOpen.value = true;
    writeProfileUrl('overview');
    await loadProfile('overview');
}

async function loadProfile(tab) {
    if (!selectedUser.value) return;

    if (profileCache.value[tab]) {
        profile.value = profileCache.value[tab];
        setTab(tab);
        tabSearch.value = '';
        tabCategory.value = '';
        return;
    }

    profileLoading.value = true;
    const { data } = await window.axios.get(route('admin.api.users.profile-tab', selectedUser.value.id), { params: { tab } });
    profile.value = data;
    profileCache.value = { ...profileCache.value, [tab]: data };
    setTab(tab);
    tabSearch.value = '';
    tabCategory.value = '';
    profileLoading.value = false;
}

function loadTab(tab) {
    loadProfile(tab);
}

function writeProfileUrl(tab) {
    const url = new URL(window.location.href);
    url.searchParams.set('user', selectedUser.value.id);
    url.searchParams.set('tab', tab);
    window.history.replaceState({ ...window.history.state, adminTabState: true }, '', url.toString());
}

async function addNote() {
    await window.axios.post(route('admin.users.notes.store', selectedUser.value.id), {
        body: noteBody.value,
        share_with_admins: shareNote.value,
    });
    noteBody.value = '';
    shareNote.value = false;
    await loadProfile('notes');
}

watch(slideOpen, (open) => {
    if (open) {
        return;
    }

    selectedUser.value = null;
    const url = new URL(window.location.href);
    url.searchParams.delete('user');
    url.searchParams.delete('tab');
    window.history.replaceState({ ...window.history.state, adminTabState: true }, '', url.toString());
});

onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    const userId = params.get('user');
    if (!userId) {
        return;
    }

    selectedUser.value = (props.users.data || []).find((user) => String(user.id) === String(userId)) || { id: userId, name: 'User profile' };
    slideOpen.value = true;
    loadProfile(params.get('tab') || 'overview');
});

async function runBulkAction() {
    const count = selectedIds.value.length;
    if (!count || !window.confirm(`Apply ${bulk.action} to ${count} user(s)?`)) return;

    const payload = {
        action: bulk.action,
        user_ids: selectedIds.value,
        subject: bulk.subject,
        message: bulk.message,
        reason: bulk.subject || bulk.message,
        tag: bulk.subject,
        badge: bulk.subject,
    };

    if (bulk.action === 'export') {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = route('admin.users.bulk');
        form.innerHTML = `<input type="hidden" name="_token" value="${document.querySelector('meta[name=csrf-token]')?.content || ''}">`;
        Object.entries(payload).forEach(([key, value]) => {
            if (Array.isArray(value)) {
                value.forEach((item) => form.insertAdjacentHTML('beforeend', `<input type="hidden" name="${key}[]" value="${item}">`));
            } else {
                form.insertAdjacentHTML('beforeend', `<input type="hidden" name="${key}" value="${String(value ?? '').replace(/"/g, '&quot;')}">`);
            }
        });
        document.body.appendChild(form);
        form.submit();
        return;
    }

    await window.axios.post(route('admin.users.bulk'), payload);
    selectedIds.value = [];
    applyFilters();
}

async function quickSuspend(user) {
    if (!window.confirm(`Suspend ${user.name}?`)) return;
    await window.axios.post(route('admin.users.bulk'), {
        action: 'suspend',
        user_ids: [user.id],
        reason: 'Suspended from advanced user management quick action',
    });
    applyFilters();
}

function openCommunication(type) {
    bulk.action = type;
    bulk.subject = window.prompt(type === 'email' ? 'Email subject' : 'Notification title') || '';
    bulk.message = window.prompt('Message body') || '';
    selectedIds.value = [selectedUser.value.id];
    runBulkAction();
}

async function openSanction() {
    const type = window.prompt('Sanction type: warning, restriction, suspension, ban', 'warning');
    if (!['warning', 'restriction', 'suspension', 'ban'].includes(type)) return;
    const reason = window.prompt('Reason code: fraud_risk, abuse_or_harassment, payment_risk, identity_mismatch, policy_violation, dispute_pattern', 'policy_violation');
    if (!reason) return;
    const notes = window.prompt('Notes for the audit log') || '';
    await window.axios.post(route('admin.users.sanctions.store', selectedUser.value.id), { type, reason_code: reason, notes });
    await loadProfile('overview');
}

async function startImpersonation() {
    const reason = window.prompt('Reason for impersonating this user. This will be audited.');
    if (!reason) return;
    const { data } = await window.axios.post(route('admin.users.impersonate', selectedUser.value.id), { reason });
    window.location.href = data.redirect || route('dashboard');
}

function defaultAvatar(user) {
    return `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name || 'User')}&background=0f766e&color=fff`;
}

function trustColor(band) {
    return band === 'green' ? 'bg-emerald-500' : band === 'amber' ? 'bg-amber-500' : 'bg-rose-500';
}

function statusClass(status) {
    return {
        active: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/15 dark:text-emerald-200',
        under_review: 'bg-amber-100 text-amber-700 dark:bg-amber-400/15 dark:text-amber-200',
        suspended: 'bg-orange-100 text-orange-700 dark:bg-orange-400/15 dark:text-orange-200',
        banned: 'bg-rose-100 text-rose-700 dark:bg-rose-400/15 dark:text-rose-200',
        closed: 'bg-slate-100 text-slate-700 dark:bg-white/10 dark:text-slate-200',
    }[status] || 'bg-slate-100 text-slate-700';
}

function dateLabel(value) {
    if (!value) return '—';
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}

function money(minor) {
    return new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', maximumFractionDigits: 0 }).format(Number(minor || 0) / 100);
}
</script>
