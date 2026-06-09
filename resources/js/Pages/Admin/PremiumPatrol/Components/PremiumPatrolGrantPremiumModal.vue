<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-sm" @click.self="close">
            <form class="w-full max-w-lg rounded-2xl border p-6 shadow-2xl" :class="shell.card" @submit.prevent="submit">
                <h3 class="text-lg font-black" :class="shell.cardTitle">Manual premium upgrade</h3>
                <p class="mt-1 text-xs font-semibold" :class="shell.cardMuted">Search by name, email, or username. Selection is required before upgrade.</p>

                <div class="relative mt-4">
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Search user…"
                        class="w-full rounded-xl border px-3 py-2.5 text-sm font-semibold"
                        :class="shell.input"
                        autocomplete="off"
                        @input="onSearch"
                    />
                    <div v-if="searching" class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2">
                        <span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-primary-600 border-t-transparent" />
                    </div>
                </div>

                <ul
                    v-if="results.length && !selectedUser"
                    class="mt-2 max-h-48 overflow-auto rounded-xl border"
                    :class="shell.card"
                >
                    <li v-for="u in results" :key="u.id">
                        <button
                            type="button"
                            class="flex w-full items-center gap-3 px-3 py-2.5 text-left text-sm transition hover:bg-primary-50 dark:hover:bg-primary-950/30"
                            @mousedown.prevent
                            @click="selectUser(u)"
                        >
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-100 text-xs font-black text-primary-800">{{ initials(u.name) }}</span>
                            <span class="min-w-0">
                                <span class="block font-bold" :class="shell.cardTitle">{{ u.name }}</span>
                                <span class="block truncate text-xs" :class="shell.cardMuted">{{ u.email }} · L{{ u.verification_tier ?? 0 }}</span>
                            </span>
                        </button>
                    </li>
                </ul>

                <div v-if="selectedUser" class="mt-3 flex items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50/80 p-3 dark:border-emerald-800 dark:bg-emerald-950/30">
                    <div class="min-w-0">
                        <p class="text-xs font-black uppercase text-emerald-700 dark:text-emerald-300">Selected user</p>
                        <p class="truncate text-sm font-bold text-slate-900 dark:text-slate-100">{{ selectedUser.name }}</p>
                        <p class="truncate text-xs text-slate-600">{{ selectedUser.email }}</p>
                    </div>
                    <button type="button" class="shrink-0 text-xs font-black text-slate-500 hover:text-slate-800" @click="clearSelection">Change</button>
                </div>

                <select v-model="form.billing_cycle" class="mt-4 w-full rounded-xl border px-3 py-2.5 text-sm font-semibold" :class="shell.input">
                    <option value="month">Monthly</option>
                    <option value="year">Annual</option>
                </select>

                <textarea
                    v-model="form.reason_notes"
                    required
                    rows="3"
                    maxlength="1000"
                    class="mt-4 w-full rounded-xl border px-3 py-2.5 text-sm font-semibold"
                    :class="shell.input"
                    placeholder="Reason for manual upgrade (audit trail)…"
                />

                <div class="mt-5 flex flex-wrap gap-2">
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 rounded-xl bg-emerald-700 px-4 py-2.5 text-xs font-black uppercase text-white disabled:opacity-50"
                        :disabled="!selectedUser || submitting"
                    >
                        <span v-if="submitting" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                        {{ submitting ? 'Upgrading…' : 'Upgrade to premium' }}
                    </button>
                    <button type="button" class="rounded-xl border px-4 py-2.5 text-xs font-black uppercase" :class="shell.btnGhost" :disabled="submitting" @click="close">Cancel</button>
                </div>
            </form>
        </div>
    </Teleport>
</template>

<script setup>
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { reactive, ref, watch } from 'vue';

const props = defineProps({
    open: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'upgraded']);

const { shell } = useInjectedAdminTheme();

const search = ref('');
const results = ref([]);
const selectedUser = ref(null);
const searching = ref(false);
const submitting = ref(false);
let searchTimer = null;

const form = reactive({
    billing_cycle: 'month',
    reason_code: 'manual_grant',
    reason_notes: '',
});

watch(() => props.open, (isOpen) => {
    if (!isOpen) {
        reset();
    }
});

function initials(name) {
    return String(name || '?').split(' ').map((p) => p[0]).join('').slice(0, 2).toUpperCase();
}

function reset() {
    search.value = '';
    results.value = [];
    selectedUser.value = null;
    form.billing_cycle = 'month';
    form.reason_notes = '';
    submitting.value = false;
}

function close() {
    emit('close');
}

function selectUser(user) {
    selectedUser.value = user;
    results.value = [];
    search.value = user.name;
}

function clearSelection() {
    selectedUser.value = null;
    search.value = '';
    results.value = [];
}

function onSearch() {
    selectedUser.value = null;
    window.clearTimeout(searchTimer);
    if (search.value.trim().length < 2) {
        results.value = [];
        return;
    }
    searchTimer = window.setTimeout(fetchUsers, 280);
}

async function fetchUsers() {
    searching.value = true;
    try {
        const { data } = await axios.get(route('admin.api.premium-patrol.users.search'), { params: { q: search.value.trim() } });
        results.value = data.data || [];
    } finally {
        searching.value = false;
    }
}

function submit() {
    if (!selectedUser.value || submitting.value) {
        return;
    }

    submitting.value = true;
    router.post(route('admin.premium-patrol.premium-users.grant', selectedUser.value.id), { ...form }, {
        preserveScroll: true,
        onSuccess: () => {
            emit('upgraded', selectedUser.value.name);
            close();
        },
        onFinish: () => {
            submitting.value = false;
        },
    });
}
</script>
