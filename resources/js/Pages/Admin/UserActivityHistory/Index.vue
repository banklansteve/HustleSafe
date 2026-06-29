<template>
    <AdminShell
        title="User activity history"
        subtitle="Investigate sign-ins, device details, marketplace actions, contracts, portfolios, verification events, and staff admin activity for any member across a custom date range."
    >
        <div v-if="opened_from_registry" class="mb-2">
            <Link
                :href="route('admin.management.index', { resource: 'users' })"
                class="inline-flex items-center gap-2 text-xs font-black uppercase tracking-wide text-primary-600"
            >
                ← Back to users registry
            </Link>
        </div>

        <AdminPanel eyebrow="Investigation" title="Activity lookup">
            <p class="mb-4 text-sm font-semibold" :class="shell.cardMuted">
                Search filters {{ user_directory.length.toLocaleString() }} members instantly on this device. Only the timeline loads from the server when you click View activity.
            </p>
            <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <div ref="userSearchRoot" class="relative sm:col-span-2 xl:col-span-1">
                        <label class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">User</label>
                        <input
                            v-model="userQuery"
                            type="search"
                            autocomplete="off"
                            placeholder="Search name, email, or username"
                            class="mt-2 w-full rounded-xl border px-4 py-2.5 text-sm font-semibold"
                            :class="shell.input"
                            @focus="userDropdownOpen = true"
                            @input="userDropdownOpen = true"
                        />
                        <div
                            v-if="userDropdownOpen && userQuery.trim().length >= 2"
                            class="absolute z-20 mt-2 max-h-72 w-full overflow-y-auto rounded-2xl border shadow-xl"
                            :class="shell.card"
                        >
                            <p v-if="!filteredUsers.length" class="px-4 py-3 text-sm font-semibold" :class="shell.cardMuted">
                                No members match “{{ userQuery.trim() }}”.
                            </p>
                            <button
                                v-for="user in filteredUsers"
                                :key="user.id"
                                type="button"
                                class="flex w-full items-center gap-3 border-b px-4 py-3 text-left transition last:border-b-0 hover:bg-slate-50 dark:hover:bg-slate-900/40"
                                :class="shell.tableDivide"
                                @click="selectUser(user)"
                            >
                                <img
                                    v-if="user.avatar_url"
                                    :src="user.avatar_url"
                                    alt=""
                                    class="h-10 w-10 rounded-full object-cover ring-2 ring-white"
                                />
                                <div
                                    v-else
                                    class="flex h-10 w-10 items-center justify-center rounded-full bg-primary-100 text-sm font-black text-primary-700"
                                >
                                    {{ initials(user.name) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate font-bold">{{ user.name }}</p>
                                    <p class="truncate text-xs font-semibold" :class="shell.cardMuted">{{ user.email }}</p>
                                    <p class="mt-0.5 text-[10px] font-black uppercase tracking-wide text-primary-600">
                                        {{ user.role_label }} · L{{ user.verification_level }}
                                    </p>
                                </div>
                            </button>
                        </div>
                        <div
                            v-if="selectedUser"
                            class="mt-3 flex items-center gap-3 rounded-2xl border px-4 py-3"
                            :class="shell.card"
                        >
                            <div class="flex h-11 w-11 items-center justify-center rounded-full bg-primary-100 text-sm font-black text-primary-700">
                                {{ initials(selectedUser.name) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-bold">{{ selectedUser.name }}</p>
                                <p class="truncate text-xs font-semibold" :class="shell.cardMuted">{{ selectedUser.email }}</p>
                            </div>
                            <button type="button" class="text-xs font-black uppercase" :class="shell.cardMuted" @click="clearUser">Clear</button>
                        </div>
                    </div>

                    <div>
                        <label class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">From</label>
                        <AdminDateInput v-model="fromDate" class="mt-2" />
                    </div>
                    <div>
                        <label class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">To</label>
                        <AdminDateInput v-model="toDate" class="mt-2" />
                    </div>
                </div>

                <div class="flex flex-col gap-3">
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="preset in rangePresets"
                            :key="preset.days"
                            type="button"
                            class="rounded-xl px-3 py-2 text-[10px] font-black uppercase tracking-wide transition"
                            :class="activePreset === preset.days ? shell.btnPrimary : shell.btnGhost"
                            @click="applyPreset(preset.days)"
                        >
                            {{ preset.label }}
                        </button>
                    </div>
                    <button
                        type="button"
                        class="rounded-xl px-5 py-3 text-xs font-black uppercase tracking-wide transition disabled:cursor-not-allowed disabled:opacity-50"
                        :class="shell.btnPrimary"
                        :disabled="!selectedUser || loading"
                        @click="loadTimeline"
                    >
                        {{ loading ? 'Loading…' : 'View activity' }}
                    </button>
                </div>
            </div>

            <p v-if="error" class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">
                {{ error }}
            </p>
        </AdminPanel>

        <div v-if="timeline" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border p-4" :class="shell.card">
                <p class="text-[10px] font-black uppercase tracking-[0.2em]" :class="shell.cardMuted">Total events</p>
                <p class="mt-2 text-3xl font-black" :class="shell.cardTitle">{{ timeline.summary.total }}</p>
                <p class="mt-1 text-xs font-semibold" :class="shell.cardMuted">
                    {{ timeline.range.from_label }} — {{ timeline.range.to_label }}
                </p>
            </div>
            <div
                v-for="bucket in timeline.summary.by_category"
                :key="bucket.category"
                class="rounded-2xl border p-4"
                :class="shell.card"
            >
                <p class="text-[10px] font-black uppercase tracking-[0.2em]" :class="shell.cardMuted">{{ bucket.label }}</p>
                <p class="mt-2 text-2xl font-black" :class="shell.cardTitle">{{ bucket.count }}</p>
            </div>
        </div>

        <AdminPanel
            v-if="timeline"
            eyebrow="Preview"
            :title="`${timeline.user.name} · ${timeline.summary.total} events`"
        >
            <template #actions>
                <button type="button" class="rounded-xl px-4 py-2 text-xs font-black uppercase" :class="shell.btnPrimary" @click="slideOpen = true">
                    Open timeline
                </button>
            </template>

            <div class="space-y-4">
                <div
                    v-for="group in timeline.groups.slice(0, 3)"
                    :key="group.date"
                    class="rounded-2xl border p-4"
                    :class="shell.card"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-black uppercase tracking-wide text-primary-600">{{ group.date_label }}</p>
                            <p class="mt-1 text-sm font-semibold" :class="shell.cardMuted">{{ group.count }} events</p>
                        </div>
                        <button type="button" class="text-[10px] font-black uppercase" :class="shell.btnGhost" @click="openSlideForGroup(group)">
                            View day
                        </button>
                    </div>
                    <ul class="mt-4 space-y-3">
                        <li
                            v-for="item in group.items.slice(0, 3)"
                            :key="item.id"
                            class="flex gap-3 rounded-xl border px-3 py-3"
                            :class="shell.card"
                        >
                            <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full" :class="categoryDotClass(item.category)" />
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="text-sm font-bold">{{ item.title }}</p>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-black uppercase" :class="categoryBadgeClass(item.category)">
                                        {{ item.category_label }}
                                    </span>
                                </div>
                                <p v-if="item.summary" class="mt-1 text-sm font-semibold" :class="shell.cardMuted">{{ item.summary }}</p>
                                <p class="mt-2 text-xs font-bold text-slate-500">{{ item.occurred_at_label }}</p>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </AdminPanel>

        <AdminSlideOver
            :open="slideOpen"
            :title="slideTitle"
            eyebrow="Activity timeline"
            width-class="max-w-xl sm:max-w-2xl"
            @close="slideOpen = false"
        >
            <div v-if="timeline" class="space-y-6">
                <div class="rounded-2xl border p-4" :class="shell.card">
                    <div class="flex items-start gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-primary-100 text-sm font-black text-primary-700">
                            {{ initials(timeline.user.name) }}
                        </div>
                        <div class="min-w-0">
                            <p class="font-bold">{{ timeline.user.name }}</p>
                            <p class="text-sm font-semibold" :class="shell.cardMuted">{{ timeline.user.email }}</p>
                            <p class="mt-1 text-xs font-bold uppercase tracking-wide text-primary-600">
                                {{ timeline.user.role_label }} · L{{ timeline.user.verification_level }}
                            </p>
                            <p class="mt-2 text-xs font-semibold" :class="shell.cardMuted">
                                {{ timeline.range.from_label }} — {{ timeline.range.to_label }}
                            </p>
                        </div>
                    </div>
                </div>

                <div v-if="focusedGroup" class="rounded-xl border border-primary-200 bg-primary-50/60 px-4 py-3 text-sm font-semibold text-primary-800">
                    Showing {{ focusedGroup.count }} events for {{ focusedGroup.date_label }}.
                    <button type="button" class="ml-2 font-black uppercase underline" @click="focusedGroup = null">Show all</button>
                </div>

                <div class="space-y-8">
                    <section v-for="group in visibleGroups" :key="group.date">
                        <div class="sticky top-0 z-10 -mx-1 mb-4 border-b bg-white/90 px-1 py-2 backdrop-blur dark:bg-slate-950/90" :class="shell.tableDivide">
                            <p class="text-xs font-black uppercase tracking-[0.18em] text-primary-600">{{ group.date_label }}</p>
                            <p class="text-[11px] font-semibold" :class="shell.cardMuted">{{ group.count }} events</p>
                        </div>

                        <ol class="relative space-y-0 border-l pl-6" :class="shell.tableDivide">
                            <li
                                v-for="item in group.items"
                                :key="item.id"
                                class="relative pb-6 last:pb-0"
                            >
                                <span
                                    class="absolute -left-[1.84rem] top-1.5 flex h-3.5 w-3.5 items-center justify-center rounded-full ring-4 ring-white dark:ring-slate-950"
                                    :class="categoryDotClass(item.category)"
                                />
                                <article class="rounded-2xl border p-4" :class="shell.card">
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <div>
                                            <p class="text-sm font-bold">{{ item.title }}</p>
                                            <span class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[10px] font-black uppercase" :class="categoryBadgeClass(item.category)">
                                                {{ item.category_label }}
                                            </span>
                                        </div>
                                        <p class="text-right text-xs font-bold text-slate-500">{{ item.relative_label }}</p>
                                    </div>
                                    <p v-if="item.summary" class="mt-3 text-sm font-semibold leading-relaxed" :class="shell.cardMuted">
                                        {{ item.summary }}
                                    </p>
                                    <p class="mt-3 text-xs font-bold text-slate-600 dark:text-slate-300">
                                        {{ item.occurred_at_label }}
                                    </p>
                                    <dl v-if="detailRows(item).length" class="mt-4 grid gap-2 sm:grid-cols-2">
                                        <div
                                            v-for="row in detailRows(item)"
                                            :key="row.label"
                                            class="rounded-xl bg-slate-50/80 px-3 py-2 dark:bg-slate-900/40"
                                        >
                                            <dt class="text-[10px] font-black uppercase tracking-wide text-slate-500">{{ row.label }}</dt>
                                            <dd class="mt-1 whitespace-pre-line break-words text-xs font-semibold leading-relaxed">{{ row.value }}</dd>
                                        </div>
                                    </dl>
                                </article>
                            </li>
                        </ol>
                    </section>
                </div>

                <p v-if="!visibleGroups.length" class="text-sm font-semibold" :class="shell.cardMuted">
                    No activity found for this period.
                </p>
            </div>
        </AdminSlideOver>
    </AdminShell>
</template>

<script setup>
import AdminDateInput from '@/Components/Admin/AdminDateInput.vue';
import AdminPanel from '@/Components/Admin/AdminPanel.vue';
import AdminSlideOver from '@/Components/Admin/AdminSlideOver.vue';
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';
import AdminShell from '@/Layouts/AdminShell.vue';
import { Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    defaults: { type: Object, default: () => ({ from: '', to: '' }) },
    selected_user: { type: Object, default: null },
    user_directory: { type: Array, default: () => [] },
    opened_from_registry: { type: Boolean, default: false },
});

const { shell } = useInjectedAdminTheme();

const rangePresets = [
    { label: '7 days', days: 7 },
    { label: '30 days', days: 30 },
    { label: '90 days', days: 90 },
];

const userQuery = ref('');
const userDropdownOpen = ref(false);
const userSearchRoot = ref(null);
const selectedUser = ref(props.selected_user);
const fromDate = ref(props.defaults.from || '');
const toDate = ref(props.defaults.to || '');
const activePreset = ref(7);
const loading = ref(false);
const error = ref('');
const timeline = ref(null);
const slideOpen = ref(false);
const focusedGroup = ref(null);

const filteredUsers = computed(() => {
    const q = userQuery.value.trim().toLowerCase();
    if (q.length < 2) {
        return [];
    }

    return props.user_directory
        .filter((user) => [user.name, user.email, user.username]
            .some((field) => String(field || '').toLowerCase().includes(q)))
        .slice(0, 15);
});

const slideTitle = computed(() => (timeline.value ? `${timeline.value.user.name} activity` : 'Activity timeline'));
const visibleGroups = computed(() => {
    if (!timeline.value) {
        return [];
    }

    if (focusedGroup.value) {
        return [focusedGroup.value];
    }

    return timeline.value.groups;
});

watch(() => props.selected_user, (user) => {
    if (user) {
        selectedUser.value = user;
        userQuery.value = user.name;
    }
});

function initials(name) {
    return String(name || '?')
        .split(' ')
        .map((part) => part[0])
        .join('')
        .slice(0, 2)
        .toUpperCase();
}

function applyPreset(days) {
    activePreset.value = days;
    const end = new Date();
    const start = new Date();
    start.setDate(end.getDate() - days + 1);
    toDate.value = end.toISOString().slice(0, 10);
    fromDate.value = start.toISOString().slice(0, 10);
}

function selectUser(user) {
    selectedUser.value = user;
    userQuery.value = user.name;
    userDropdownOpen.value = false;
}

function clearUser() {
    selectedUser.value = null;
    userQuery.value = '';
    timeline.value = null;
    focusedGroup.value = null;
}

async function loadTimeline() {
    if (!selectedUser.value) {
        return;
    }

    loading.value = true;
    error.value = '';
    focusedGroup.value = null;

    try {
        const { data } = await window.axios.get(route('admin.api.user-activity-history.timeline'), {
            params: {
                user_id: selectedUser.value.id,
                from: fromDate.value,
                to: toDate.value,
            },
        });
        timeline.value = data;
        slideOpen.value = true;
    } catch (requestError) {
        timeline.value = null;
        error.value = requestError?.response?.data?.message || 'Could not load activity for this period.';
    } finally {
        loading.value = false;
    }
}

function openSlideForGroup(group) {
    focusedGroup.value = group;
    slideOpen.value = true;
}

function categoryDotClass(category) {
    return {
        security: 'bg-emerald-500',
        platform: 'bg-sky-500',
        admin: 'bg-violet-500',
        verification: 'bg-amber-500',
        operations: 'bg-fuchsia-500',
        marketplace: 'bg-primary-500',
        contracts: 'bg-indigo-500',
        portfolio: 'bg-teal-500',
        social: 'bg-rose-500',
        staff: 'bg-orange-500',
        finance: 'bg-lime-600',
        disputes: 'bg-red-500',
        moderation: 'bg-pink-500',
    }[category] || 'bg-slate-400';
}

function categoryBadgeClass(category) {
    return {
        security: 'bg-emerald-50 text-emerald-700',
        platform: 'bg-sky-50 text-sky-700',
        admin: 'bg-violet-50 text-violet-700',
        verification: 'bg-amber-50 text-amber-800',
        operations: 'bg-fuchsia-50 text-fuchsia-700',
        marketplace: 'bg-primary-50 text-primary-700',
        contracts: 'bg-indigo-50 text-indigo-700',
        portfolio: 'bg-teal-50 text-teal-800',
        social: 'bg-rose-50 text-rose-700',
        staff: 'bg-orange-50 text-orange-800',
        finance: 'bg-lime-50 text-lime-800',
        disputes: 'bg-red-50 text-red-800',
        moderation: 'bg-pink-50 text-pink-800',
    }[category] || 'bg-slate-100 text-slate-600';
}

function humanizeKey(key) {
    return String(key)
        .replace(/_/g, ' ')
        .replace(/([a-z])([A-Z])/g, '$1 $2')
        .replace(/\b\w/g, (char) => char.toUpperCase());
}

function shortenClassName(value) {
    if (typeof value !== 'string') {
        return value;
    }

    if (value.includes('\\')) {
        return value.split('\\').pop() || value;
    }

    return value;
}

function parseJsonLike(value) {
    if (typeof value !== 'string') {
        return value;
    }

    const trimmed = value.trim();
    if (!trimmed.startsWith('{') && !trimmed.startsWith('[')) {
        return value;
    }

    try {
        return JSON.parse(trimmed);
    } catch {
        return value;
    }
}

function humanizeMetaValue(value, depth = 0) {
    value = parseJsonLike(value);

    if (value === null || value === undefined || value === '') {
        return null;
    }

    if (typeof value === 'boolean') {
        return value ? 'Yes' : 'No';
    }

    if (typeof value === 'number') {
        return String(value);
    }

    if (typeof value === 'string') {
        return shortenClassName(value);
    }

    if (Array.isArray(value)) {
        if (!value.length) {
            return null;
        }

        const items = value
            .map((item) => humanizeMetaValue(item, depth + 1))
            .filter(Boolean);

        if (!items.length) {
            return null;
        }

        if (items.every((item) => !String(item).includes('\n'))) {
            return items.join(', ');
        }

        return items
            .map((item, index) => `${index + 1}. ${String(item).replace(/\n/g, '; ')}`)
            .join('\n');
    }

    if (typeof value === 'object') {
        const entries = Object.entries(value).filter(([, entryValue]) => entryValue !== null && entryValue !== undefined && entryValue !== '');
        if (!entries.length) {
            return null;
        }

        const indent = depth > 0 ? '  '.repeat(depth) : '';

        return entries
            .map(([key, entryValue]) => {
                const label = humanizeKey(key);
                const formatted = humanizeMetaValue(entryValue, depth + 1);

                if (!formatted) {
                    return null;
                }

                if (typeof entryValue === 'object' && entryValue !== null) {
                    return `${indent}${label}:\n${formatted}`;
                }

                return `${indent}${label}: ${formatted}`;
            })
            .filter(Boolean)
            .join('\n');
    }

    return String(value);
}

function detailRows(item) {
    const meta = item.meta || {};
    const rows = [];

    const push = (label, value) => {
        const formatted = humanizeMetaValue(value);
        if (formatted) {
            rows.push({ label, value: formatted });
        }
    };

    push('Device', meta.device_label);
    push('Browser', meta.browser);
    push('Operating system', meta.os);
    push('Device type', meta.device);
    push('IP address', meta.ip_address);
    push('User agent', meta.user_agent);
    push('Action', meta.action || meta.event_type || meta.event);
    push('Reference', meta.reference_code || meta.quest_reference || meta.contract_reference);
    push('Status', meta.status);
    push('Event', meta.event_key);
    push('Section', meta.section_key ? String(meta.section_key).replace(/[_-]/g, ' ') : null);
    push('Duration', meta.duration_seconds ? `${meta.duration_seconds}s active` : null);
    push('Visits', meta.visits);
    push('Target member', meta.target_name);

    if (meta.actor?.name) {
        push('Actor', `${meta.actor.name}${meta.actor.email ? ` · ${meta.actor.email}` : ''}`);
    }

    if (meta.admin?.name) {
        push('Admin', `${meta.admin.name}${meta.admin.email ? ` · ${meta.admin.email}` : ''}`);
    }

    if (meta.affected_user?.name) {
        push('Affected user', `${meta.affected_user.name}${meta.affected_user.email ? ` · ${meta.affected_user.email}` : ''}`);
    }

    push(
        'Subject',
        meta.subject_type
            ? `${shortenClassName(meta.subject_type)}${meta.subject_id ? ` #${meta.subject_id}` : ''}`
            : null,
    );
    push('Properties', meta.properties);
    push('Before', meta.before || meta.old_value);
    push('After', meta.after || meta.new_value);
    push('Details', meta.details);

    return rows;
}

function onDocumentClick(event) {
    if (!userSearchRoot.value?.contains(event.target)) {
        userDropdownOpen.value = false;
    }
}

onMounted(() => {
    applyPreset(7);
    if (props.selected_user) {
        selectedUser.value = props.selected_user;
        userQuery.value = props.selected_user.name;
    }
    document.addEventListener('click', onDocumentClick);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
});
</script>
