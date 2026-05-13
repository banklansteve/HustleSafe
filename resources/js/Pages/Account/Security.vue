<template>
    <AppShell>
        <Head title="Security · HustleSafe" />

        <div class="mx-auto w-full max-w-lg space-y-8">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-primary-700">
                    Account
                </p>
                <h1 class="font-display mt-2 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">
                    Security &amp; sign-in
                </h1>
                <p class="mt-2 text-sm font-medium text-slate-600">
                    Password, profile photo, and account deletion. Your sign-in email is fixed for safety.
                </p>
            </div>

            <div
                v-if="mustVerifyEmail"
                class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm font-semibold text-amber-950 ring-1 ring-amber-100"
            >
                Verify your email to use the full marketplace. Check your inbox for the link we sent.
            </div>

            <!-- Photo -->
            <section class="rounded-[1.75rem] border border-slate-100 bg-white p-6 shadow-sm ring-1 ring-slate-100 sm:p-8">
                <h2 class="font-display text-lg font-bold text-slate-900">
                    Profile photo
                </h2>
                <p class="mt-2 text-sm font-medium text-slate-600">
                    Shown on your account and public freelancer profile. Images are stored securely on Cloudinary.
                </p>

                <div
                    v-if="!avatarConfigured"
                    class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-950"
                >
                    Photo upload is disabled until you add Cloudinary credentials to your <code class="rounded bg-white px-1 py-0.5 text-xs">.env</code> file
                    (<span class="font-mono text-xs">CLOUDINARY_URL</span> or cloud name / key / secret).
                </div>

                <div class="mt-6 flex flex-col items-center gap-6 sm:flex-row sm:items-start">
                    <div
                        class="relative flex h-28 w-28 shrink-0 overflow-hidden rounded-full border-2 border-slate-100 bg-gradient-to-br from-primary-600 to-primary-800 shadow-lg ring-2 ring-white"
                    >
                        <img
                            v-if="user.avatar_url"
                            :src="user.avatar_url"
                            alt=""
                            class="h-full w-full object-cover"
                        />
                        <span
                            v-else
                            class="flex h-full w-full items-center justify-center text-3xl font-black tracking-tight text-white"
                        >
                            {{ initials }}
                        </span>
                    </div>
                    <div class="min-w-0 flex-1 space-y-3 text-center sm:text-left">
                        <input
                            ref="fileInput"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="sr-only"
                            :disabled="!avatarConfigured || avatarForm.processing"
                            @change="onAvatarSelected"
                        />
                        <button
                            type="button"
                            class="inline-flex w-full items-center justify-center rounded-full bg-primary-600 px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-primary-900/15 hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto"
                            :disabled="!avatarConfigured || avatarForm.processing"
                            @click="fileInput?.click()"
                        >
                            {{ avatarForm.processing ? 'Uploading…' : 'Upload new photo' }}
                        </button>
                        <InputError :message="avatarForm.errors.avatar" />
                        <p class="text-xs font-medium text-slate-500">
                            JPEG, PNG or Webp · max 4&nbsp;MB
                        </p>
                    </div>
                </div>
            </section>

            <!-- Email (read-only) -->
            <section class="rounded-[1.75rem] border border-slate-100 bg-white p-6 shadow-sm ring-1 ring-slate-100 sm:p-8">
                <h2 class="font-display text-lg font-bold text-slate-900">
                    Sign-in email
                </h2>
                <p class="mt-2 text-sm font-medium text-slate-600">
                    For security, your login email cannot be changed in the app. Contact support if you no longer have access.
                </p>
                <p class="mt-5 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold text-slate-800">
                    {{ user.email }}
                </p>
            </section>

            <!-- Password -->
            <section class="rounded-[1.75rem] border border-slate-100 bg-white p-6 shadow-sm ring-1 ring-slate-100 sm:p-8">
                <h2 class="font-display text-lg font-bold text-slate-900">
                    Password
                </h2>
                <p class="mt-2 text-sm font-medium text-slate-600">
                    Use a long, unique password. You will stay signed in on this device until you log out.
                </p>
                <form class="mt-6 space-y-5" @submit.prevent="submitPassword">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Current password</label>
                        <input
                            ref="currentPasswordInput"
                            v-model="passwordForm.current_password"
                            type="password"
                            class="mt-1 w-full rounded-xl border-slate-200 text-sm font-medium shadow-sm focus:border-primary-500 focus:ring-primary-500"
                            autocomplete="current-password"
                        />
                        <InputError class="mt-1" :message="passwordForm.errors.current_password" />
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">New password</label>
                        <input
                            ref="newPasswordInput"
                            v-model="passwordForm.password"
                            type="password"
                            class="mt-1 w-full rounded-xl border-slate-200 text-sm font-medium shadow-sm focus:border-primary-500 focus:ring-primary-500"
                            autocomplete="new-password"
                        />
                        <InputError class="mt-1" :message="passwordForm.errors.password" />
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Confirm new password</label>
                        <input
                            v-model="passwordForm.password_confirmation"
                            type="password"
                            class="mt-1 w-full rounded-xl border-slate-200 text-sm font-medium shadow-sm focus:border-primary-500 focus:ring-primary-500"
                            autocomplete="new-password"
                        />
                        <InputError class="mt-1" :message="passwordForm.errors.password_confirmation" />
                    </div>
                    <div class="flex flex-wrap items-center gap-4">
                        <button
                            type="submit"
                            class="rounded-full bg-primary-600 px-6 py-2.5 text-sm font-bold text-white shadow-md shadow-primary-900/15 hover:bg-primary-700 disabled:opacity-50"
                            :disabled="passwordForm.processing"
                        >
                            Update password
                        </button>
                        <p v-if="passwordForm.recentlySuccessful" class="text-sm font-semibold text-emerald-700">
                            Saved.
                        </p>
                    </div>
                </form>
            </section>

            <!-- Delete -->
            <section class="rounded-[1.75rem] border border-rose-100 bg-rose-50/30 p-6 ring-1 ring-rose-100 sm:p-8">
                <DeleteUserForm />
            </section>

            <div class="text-center">
                <Link :href="route('account.show')" class="text-sm font-bold text-primary-700 hover:underline">
                    ← Back to account
                </Link>
            </div>
        </div>
    </AppShell>
</template>

<script setup>
import InputError from '@/Components/InputError.vue';
import AppShell from '@/Layouts/AppShell.vue';
import DeleteUserForm from '@/Pages/Profile/Partials/DeleteUserForm.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    mustVerifyEmail: { type: Boolean, default: false },
    user: { type: Object, required: true },
    avatarConfigured: { type: Boolean, default: false },
});

const fileInput = ref(null);
const currentPasswordInput = ref(null);
const newPasswordInput = ref(null);

const initials = computed(() => {
    const n = props.user.name || '';
    const parts = n.trim().split(/\s+/);

    return ((parts[0]?.[0] || 'H') + (parts[1]?.[0] || '')).toUpperCase();
});

const avatarForm = useForm({
    avatar: null,
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

function onAvatarSelected(e) {
    const file = e.target.files?.[0];
    if (!file) {
        return;
    }
    avatarForm.avatar = file;
    avatarForm.post(route('account.security.avatar'), {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            avatarForm.reset('avatar');
            e.target.value = '';
        },
    });
}

function submitPassword() {
    passwordForm.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => {
            passwordForm.reset();
        },
        onError: () => {
            if (passwordForm.errors.password) {
                passwordForm.reset('password', 'password_confirmation');
                newPasswordInput.value?.focus();
            }
            if (passwordForm.errors.current_password) {
                passwordForm.reset('current_password');
                currentPasswordInput.value?.focus();
            }
        },
    });
}
</script>
