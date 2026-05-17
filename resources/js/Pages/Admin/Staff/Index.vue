<template>
    <AdminShell
        title="Staff & roles"
        subtitle="Grant operational admin access. CSV import expects a header row with an email column. Exports include every admin and super admin account."
    >
        <div class="mb-6 flex flex-col gap-3 rounded-2xl border border-white/10 bg-slate-900/40 p-4 ring-1 ring-white/5 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">CSV</p>
                <p class="mt-1 text-sm font-semibold text-slate-400">Bulk promote existing users to admin (max 200 rows per upload).</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a
                    :href="route('admin.staff.export')"
                    class="inline-flex rounded-xl bg-teal-500 px-4 py-2 text-sm font-black uppercase tracking-wide text-slate-950 shadow-lg shadow-teal-900/30 transition hover:bg-teal-400"
                >
                    Export CSV
                </a>
                <form class="flex flex-wrap items-end gap-2" @submit.prevent="submitImport">
                    <input
                        ref="staffFileRef"
                        type="file"
                        accept=".csv,.txt"
                        class="block w-full max-w-xs text-xs font-semibold text-slate-300 file:mr-2 file:rounded-lg file:border-0 file:bg-white/10 file:px-3 file:py-2 file:text-xs file:font-bold file:text-white"
                        @change="onStaffFile"
                    />
                    <button
                        type="submit"
                        class="rounded-xl border border-violet-400/50 bg-violet-500/20 px-4 py-2 text-sm font-black uppercase tracking-wide text-violet-100 transition hover:bg-violet-500/30 disabled:opacity-40"
                        :disabled="importForm.processing || !importForm.file"
                    >
                        Import
                    </button>
                </form>
            </div>
            <p v-if="importForm.errors.file" class="w-full text-xs font-bold text-rose-300">
                {{ importForm.errors.file }}
            </p>
        </div>

        <section class="rounded-2xl border border-white/10 bg-slate-900/50 p-5 ring-1 ring-white/5">
            <h2 class="font-display text-lg font-bold text-white">Create operations staff (email invite)</h2>
            <p class="mt-2 text-sm font-semibold text-slate-400">
                Creates a new platform account with the <span class="text-teal-200">admin</span> role. They receive an email to set their password (or can use Forgot password). They cannot self-register.
            </p>
            <form class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:items-end" @submit.prevent="submitInvite">
                <div>
                    <label class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500" for="inv-fn">First name</label>
                    <input
                        id="inv-fn"
                        v-model="inviteForm.first_name"
                        type="text"
                        required
                        autocomplete="given-name"
                        class="mt-1 w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2 text-sm font-semibold text-white outline-none ring-2 ring-transparent focus:border-teal-400/60 focus:ring-teal-500/40"
                    />
                    <p v-if="inviteForm.errors.first_name" class="mt-1 text-xs font-bold text-rose-300">{{ inviteForm.errors.first_name }}</p>
                </div>
                <div>
                    <label class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500" for="inv-ln">Last name</label>
                    <input
                        id="inv-ln"
                        v-model="inviteForm.last_name"
                        type="text"
                        required
                        autocomplete="family-name"
                        class="mt-1 w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2 text-sm font-semibold text-white outline-none ring-2 ring-transparent focus:border-teal-400/60 focus:ring-teal-500/40"
                    />
                    <p v-if="inviteForm.errors.last_name" class="mt-1 text-xs font-bold text-rose-300">{{ inviteForm.errors.last_name }}</p>
                </div>
                <div class="sm:col-span-2 lg:col-span-1">
                    <label class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500" for="inv-em">Work email</label>
                    <input
                        id="inv-em"
                        v-model="inviteForm.email"
                        type="email"
                        required
                        autocomplete="off"
                        class="mt-1 w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2 text-sm font-semibold text-white outline-none ring-2 ring-transparent focus:border-teal-400/60 focus:ring-teal-500/40"
                        placeholder="name@company.com"
                    />
                    <p v-if="inviteForm.errors.email" class="mt-1 text-xs font-bold text-rose-300">{{ inviteForm.errors.email }}</p>
                </div>
                <div class="sm:col-span-2 lg:col-span-1">
                    <button
                        type="submit"
                        class="inline-flex w-full items-center justify-center rounded-xl bg-violet-500 px-5 py-2.5 text-sm font-black uppercase tracking-wide text-white shadow-lg shadow-violet-900/30 transition hover:bg-violet-400 disabled:opacity-50"
                        :disabled="inviteForm.processing"
                    >
                        Send invite
                    </button>
                </div>
            </form>
        </section>

        <section class="mt-8 rounded-2xl border border-white/10 bg-slate-900/50 p-5 ring-1 ring-white/5">
            <h2 class="font-display text-lg font-bold text-white">Promote existing member</h2>
            <p class="mt-2 text-sm font-semibold text-slate-400">
                If they already have an account, grant <span class="text-teal-200">admin</span> without a new login. Super admin accounts cannot be changed here.
            </p>
            <form class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end" @submit.prevent="submit">
                <div class="flex-1">
                    <label class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500" for="staff-email">Work email</label>
                    <input
                        id="staff-email"
                        v-model="form.email"
                        type="email"
                        required
                        autocomplete="off"
                        class="mt-1 w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2 text-sm font-semibold text-white outline-none ring-2 ring-transparent focus:border-teal-400/60 focus:ring-teal-500/40"
                        placeholder="name@company.com"
                    />
                    <p v-if="form.errors.email" class="mt-2 text-xs font-bold text-rose-300">
                        {{ form.errors.email }}
                    </p>
                </div>
                <button
                    type="submit"
                    class="inline-flex items-center justify-center rounded-xl bg-teal-500 px-5 py-2.5 text-sm font-black uppercase tracking-wide text-slate-950 shadow-lg shadow-teal-900/30 transition hover:bg-teal-400 disabled:opacity-50"
                    :disabled="form.processing"
                >
                    Grant admin
                </button>
            </form>
        </section>

        <section class="mt-8">
            <h2 class="font-display text-lg font-bold text-white">Current staff</h2>
            <div class="mt-4 space-y-3">
                <article
                    v-for="user in staff.data"
                    :key="user.id"
                    class="flex flex-col gap-2 rounded-2xl border border-white/10 bg-slate-900/60 p-4 ring-1 ring-white/5 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <p class="font-display text-base font-bold text-white">{{ user.name }}</p>
                        <p class="text-xs font-semibold text-slate-400">{{ user.email }}</p>
                    </div>
                    <span class="inline-flex w-fit rounded-full border border-white/15 bg-white/5 px-3 py-1 text-[10px] font-black uppercase tracking-wide text-teal-100">
                        {{ (user.role?.slug ?? '—').replace(/_/g, ' ') }}
                    </span>
                </article>
            </div>
            <nav v-if="staff.links?.length > 3" class="mt-6 flex flex-wrap justify-center gap-2" aria-label="Pagination">
                <component
                    :is="link.url ? Link : 'span'"
                    v-for="link in staff.links"
                    :key="String(link.label) + (link.url || 'gap')"
                    :href="link.url || undefined"
                    prefetch="false"
                    class="min-w-[2.5rem] rounded-lg px-3 py-1.5 text-center text-xs font-bold transition"
                    :class="[
                        link.active ? 'bg-teal-500 text-slate-950' : 'border border-white/10 text-slate-200 hover:bg-white/5',
                        !link.url ? 'pointer-events-none opacity-40' : '',
                    ]"
                    preserve-state
                >
                    <span v-html="link.label" />
                </component>
            </nav>
        </section>
    </AdminShell>
</template>

<script setup>
import AdminShell from '@/Layouts/AdminShell.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    staff: { type: Object, required: true },
});

const staffFileRef = ref(null);

const form = useForm({
    email: '',
});

const importForm = useForm({
    file: null,
});

const inviteForm = useForm({
    first_name: '',
    last_name: '',
    email: '',
});

function submitInvite() {
    inviteForm.post(route('admin.staff.invite'), {
        preserveScroll: true,
        onSuccess: () => {
            inviteForm.reset();
        },
    });
}

function submit() {
    form.post(route('admin.staff.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
        },
    });
}

function onStaffFile(e) {
    importForm.file = e.target.files?.[0] || null;
}

function submitImport() {
    if (!importForm.file) {
        return;
    }
    importForm.post(route('admin.staff.import'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            importForm.reset();
            if (staffFileRef.value) {
                staffFileRef.value.value = '';
            }
        },
    });
}
</script>
