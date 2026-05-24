<template>
    <section class="mb-6 overflow-hidden rounded-[2rem] border border-amber-200 bg-gradient-to-br from-amber-50 via-white to-primary-50/60 p-6 shadow-sm ring-1 ring-amber-100">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
            <div class="max-w-xl">
                <p class="text-[10px] font-black uppercase tracking-[0.28em] text-amber-800">Workshop mode</p>
                <h2 class="font-display mt-2 text-2xl font-black text-slate-950">Site maintenance switch</h2>
                <p class="mt-2 text-sm font-semibold leading-relaxed text-slate-600">
                    Shows the custom workshop page to the public. Does <strong>not</strong> run <code class="rounded bg-slate-100 px-1">php artisan down</code> — you can still log in and use <code class="rounded bg-slate-100 px-1">/admin</code> while maintenance is on.
                </p>
                <p v-if="status.enabled" class="mt-3 rounded-xl bg-amber-100 px-3 py-2 text-xs font-bold text-amber-950">Maintenance is <strong>ON</strong> — public users see the workshop page.</p>
                <p v-if="status.legacy_artisan_down" class="mt-3 rounded-xl bg-rose-100 px-3 py-2 text-xs font-bold text-rose-950">
                    Legacy <code>artisan down</code> file detected — saving here will remove it so admin and custom pages work again.
                </p>
                <details class="mt-3 rounded-xl border border-slate-200 bg-white/80 px-3 py-2 text-xs font-semibold text-slate-600">
                    <summary class="cursor-pointer font-black text-slate-800">Locked out of admin?</summary>
                    <p class="mt-2">In your project folder run:</p>
                    <pre class="mt-1 overflow-x-auto rounded-lg bg-slate-900 p-2 text-[11px] text-emerald-200">php artisan up
php artisan platform:maintenance off</pre>
                </details>
            </div>
            <ErrorScene class="mx-auto shrink-0 lg:mx-0" />
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-2">
            <label class="block text-sm font-bold text-slate-800">
                Message
                <textarea v-model="form.message" rows="3" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-semibold" placeholder="We are tuning the workshop…" />
            </label>
            <label class="block text-sm font-bold text-slate-800">
                Estimated return
                <input v-model="form.return_time" type="datetime-local" class="mt-2 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-semibold" />
            </label>
        </div>

        <div class="mt-6 flex flex-wrap items-center gap-3">
            <button
                type="button"
                class="rounded-2xl px-6 py-3 text-sm font-black uppercase tracking-wide text-white shadow-lg transition disabled:opacity-60"
                :class="status.enabled ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-amber-600 hover:bg-amber-700'"
                :disabled="saving || !ready"
                @click="toggle"
            >
                {{ !ready ? 'Loading…' : saving ? 'Saving…' : status.enabled ? 'Turn maintenance OFF' : 'Turn maintenance ON' }}
            </button>
            <a :href="previewUrl" target="_blank" rel="noopener" class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-black text-slate-700 hover:bg-slate-50">Preview public page</a>
            <p v-if="status.enabled" class="w-full text-xs font-semibold text-slate-500">Logged-in super admins still use <code class="rounded bg-slate-100 px-1">/admin</code>. Preview in a private/incognito window to see what the public sees.</p>
            <a
                v-if="status.enabled"
                :href="route('admin.api.maintenance.off')"
                class="rounded-2xl border border-emerald-300 bg-emerald-50 px-5 py-3 text-sm font-black text-emerald-900 hover:bg-emerald-100"
            >
                Force off (direct link)
            </a>
        </div>
        <p v-if="toast" class="mt-3 text-sm font-bold" :class="toastError ? 'text-rose-700' : 'text-primary-800'">{{ toast }}</p>
    </section>
</template>

<script setup>
import ErrorScene from '@/Components/Errors/ErrorScene.vue';
import { onMounted, reactive, ref } from 'vue';

const status = reactive({ enabled: false, message: '', return_time: null, legacy_artisan_down: false });
const form = reactive({ message: '', return_time: '' });
const saving = ref(false);
const ready = ref(false);
const toast = ref('');
const toastError = ref(false);
const previewUrl = `${window.location.origin}/`;

function isEnabledValue(value) {
    if (value === true || value === 1) {
        return true;
    }
    if (value === false || value === 0 || value === null || value === undefined) {
        return false;
    }
    if (typeof value === 'string') {
        return ['1', 'true', 'yes', 'on'].includes(value.toLowerCase().trim());
    }

    return false;
}

function applyStatus(payload) {
    status.enabled = isEnabledValue(payload?.enabled);
    status.message = payload?.message ?? '';
    status.return_time = payload?.return_time ?? null;
    status.legacy_artisan_down = payload?.legacy_artisan_down === true;
    form.message = status.message;
    form.return_time = status.return_time || '';

    window.dispatchEvent(new CustomEvent('admin:maintenance-changed', {
        detail: { enabled: status.enabled },
    }));
}

onMounted(load);

async function load() {
    ready.value = false;
    try {
        const { data } = await window.axios.get(route('admin.api.maintenance.status'));
        applyStatus(data);
    } catch (err) {
        toastError.value = true;
        toast.value = err.response?.data?.message
            || 'Could not load maintenance status. Refresh the page or run: php artisan platform:maintenance off';
    } finally {
        ready.value = true;
    }
}

async function toggle() {
    if (!ready.value || saving.value) {
        return;
    }

    const turningOff = status.enabled === true;
    saving.value = true;
    toast.value = '';
    toastError.value = false;
    try {
        const url = turningOff
            ? route('admin.api.maintenance.off')
            : route('admin.api.maintenance.on');
        const payload = turningOff
            ? {}
            : { message: form.message || null, return_time: form.return_time || null };
        const { data } = await window.axios.post(
            url,
            payload,
            { headers: { 'Content-Type': 'application/json', Accept: 'application/json' } },
        );
        if (!data?.status) {
            throw new Error('Unexpected response from server.');
        }
        applyStatus(data.status);
        toast.value = data.message;
        if (data.status.legacy_artisan_down) {
            toastError.value = true;
            toast.value = 'Legacy artisan down file is still active. Click Turn maintenance OFF again or run php artisan up.';
            return;
        }
        if (turningOff && !status.enabled) {
            window.setTimeout(() => window.location.reload(), 600);
        }
    } catch (err) {
        toastError.value = true;
        toast.value = err.response?.data?.message
            || Object.values(err.response?.data?.errors || {})?.flat()?.[0]
            || err.message
            || 'Could not update maintenance mode. Try again.';
        await load();
    } finally {
        saving.value = false;
    }
}
</script>
