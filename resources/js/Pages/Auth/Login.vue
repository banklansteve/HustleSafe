<template>
    <AuthSplitLayout
        illustration-src="/images/auth/login-illustration.svg"
        illustration-alt="Illustration of a freelancer working at a laptop"
        aside-eyebrow="Welcome back"
        aside-title="Continue building trust — one milestone at a time."
        aside-subtitle="Project sponsors and Safe Hustlers use the same calm, escrow-first workspace tailored for Nigeria."
    >
        <Head title="Log in" />

        <div
            class="rounded-[1.75rem] bg-white p-9 shadow-xl shadow-slate-300/40 ring-1 ring-slate-200/90 sm:p-12 lg:min-h-[min(32rem,70vh)] lg:p-14 xl:rounded-[2rem] xl:p-16"
        >
            <h1 class="font-display text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">
                Welcome back
            </h1>
            <p class="mt-3 text-base leading-relaxed text-slate-600 sm:text-lg">
                Sign in with your email and password to reach your dashboard.
            </p>

            <div
                v-if="status"
                class="mt-6 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 ring-1 ring-emerald-100"
                role="status"
            >
                {{ status }}
            </div>

            <div
                v-if="loginFeedback"
                class="mt-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold leading-relaxed text-rose-900 ring-1 ring-rose-100"
                role="alert"
                aria-live="assertive"
            >
                {{ loginFeedback }}
            </div>

            <div class="mt-8 space-y-4 sm:mt-10">
                <GoogleSignInButton auth-screen="login" label="Log in with Google" />
                <div class="relative flex items-center py-1">
                    <div class="grow border-t border-slate-200" />
                    <span class="mx-4 shrink text-xs font-semibold uppercase tracking-wider text-slate-400">
                        Or with email
                    </span>
                    <div class="grow border-t border-slate-200" />
                </div>
            </div>

            <form class="mt-8 space-y-7 sm:mt-10" @submit.prevent="submit">
                <div>
                    <InputLabel for="email" value="Email address" />
                    <TextInput
                        id="email"
                        v-model="form.email"
                        type="email"
                        class="mt-2 block w-full rounded-xl border-slate-200 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                        autofocus
                        autocomplete="username"
                    />
                    <InputError class="mt-2" :message="form.errors.email" />
                </div>

                <div>
                    <InputLabel for="password" value="Password" />
                    <TextInput
                        id="password"
                        v-model="form.password"
                        type="password"
                        class="mt-2 block w-full rounded-xl border-slate-200 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                        autocomplete="current-password"
                    />
                    <InputError class="mt-2" :message="form.errors.password" />
                </div>

                <div class="flex flex-wrap items-center justify-between gap-4">
                    <label class="flex items-center gap-2">
                        <Checkbox v-model:checked="form.remember" name="remember" />
                        <span class="text-sm font-medium text-slate-600">Remember me</span>
                    </label>
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="text-sm font-semibold text-primary-700 underline-offset-4 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2"
                    >
                        Forgot password?
                    </Link>
                </div>

                <PrimaryButton
                    class="flex w-full justify-center rounded-2xl px-4 py-4 text-base font-bold normal-case text-white shadow-lg shadow-primary-900/15 ring-1 ring-primary-500/25 hover:bg-primary-800"
                    :class="{ 'opacity-60': form.processing }"
                    :disabled="form.processing"
                >
                    Log in
                </PrimaryButton>

                <p class="text-center text-sm text-slate-600">
                    New here?
                    <Link
                        :href="route('register')"
                        class="font-bold text-primary-700 underline-offset-4 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2"
                    >
                        Create an account
                    </Link>
                </p>
            </form>

            <details class="mt-10 rounded-2xl border border-slate-200 bg-slate-50/90 px-5 py-4 ring-1 ring-slate-100">
                <summary class="cursor-pointer list-none text-sm font-bold text-slate-800 [&::-webkit-details-marker]:hidden">
                    Reactivate a deactivated account
                </summary>
                <p class="mt-3 text-sm font-medium leading-relaxed text-slate-600">
                    If you previously deactivated your account, sign in here with your email and password to restore access immediately.
                </p>
                <div
                    v-if="reactivateFeedback"
                    class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-900"
                    role="alert"
                    aria-live="assertive"
                >
                    {{ reactivateFeedback }}
                </div>
                <form class="mt-5 space-y-4" @submit.prevent="submitReactivate">
                    <div>
                        <InputLabel for="reactivate-email" value="Email" />
                        <TextInput
                            id="reactivate-email"
                            v-model="reactivateForm.email"
                            type="email"
                            class="mt-2 block w-full rounded-xl border-slate-200 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                            autocomplete="username"
                        />
                        <InputError class="mt-2" :message="reactivateForm.errors.email" />
                    </div>
                    <div>
                        <InputLabel for="reactivate-password" value="Password" />
                        <TextInput
                            id="reactivate-password"
                            v-model="reactivateForm.password"
                            type="password"
                            class="mt-2 block w-full rounded-xl border-slate-200 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                            autocomplete="current-password"
                        />
                        <InputError class="mt-2" :message="reactivateForm.errors.password" />
                    </div>
                    <PrimaryButton
                        type="submit"
                        class="w-full justify-center rounded-2xl px-4 py-3.5 text-sm font-bold normal-case"
                        :class="{ 'opacity-60': reactivateForm.processing }"
                        :disabled="reactivateForm.processing"
                    >
                        Reactivate and sign in
                    </PrimaryButton>
                </form>
            </details>
        </div>
    </AuthSplitLayout>
</template>

<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import GoogleSignInButton from '@/Components/Auth/GoogleSignInButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthSplitLayout from '@/Layouts/Auth/AuthSplitLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineProps({
    canResetPassword: {
        type: Boolean,
        default: false,
    },
    status: {
        type: String,
        default: null,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});
const fallbackLoginError = ref('');

const reactivateForm = useForm({
    email: '',
    password: '',
});
const fallbackReactivateError = ref('');

const loginFeedback = computed(() => fallbackLoginError.value || firstError(form.errors));
const reactivateFeedback = computed(() => fallbackReactivateError.value || firstError(reactivateForm.errors));

function submit() {
    fallbackLoginError.value = '';
    form.clearErrors();
    form.post(route('login'), {
        preserveScroll: true,
        onSuccess: () => {
            fallbackLoginError.value = '';
        },
        onError: (errors) => {
            fallbackLoginError.value = firstError(errors) || 'We could not sign you in. Please check your email and password, then try again.';
        },
        onFinish: () => {
            form.reset('password');
        },
    });
}

function submitReactivate() {
    fallbackReactivateError.value = '';
    reactivateForm.clearErrors();
    reactivateForm.post(route('account.reactivate'), {
        preserveScroll: true,
        onError: (errors) => {
            fallbackReactivateError.value = firstError(errors) || 'We could not reactivate this account. Please check the email and password, then try again.';
        },
        onFinish: () => reactivateForm.reset('password'),
    });
}

function firstError(errors) {
    const first = Object.values(errors || {})[0];

    return Array.isArray(first) ? first[0] : (first || '');
}
</script>
