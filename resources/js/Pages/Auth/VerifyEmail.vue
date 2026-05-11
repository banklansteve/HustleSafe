<template>
    <AuthGuestShell>
        <Head title="Email Verification" />

        <h1 class="font-display text-2xl font-bold text-slate-900">
            Verify your email
        </h1>
        <p class="mt-2 text-sm leading-relaxed text-slate-600">
            Click the link we sent you to activate your account. Did not receive it?
            Request another below.
        </p>

        <div
            v-if="verificationLinkSent"
            class="mt-6 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 ring-1 ring-emerald-100"
            role="status"
        >
            A new verification link has been sent to your email address.
        </div>

        <form class="mt-8 space-y-6" @submit.prevent="submit">
            <PrimaryButton
                class="flex w-full justify-center rounded-2xl py-3.5 font-bold normal-case"
                :class="{ 'opacity-60': form.processing }"
                :disabled="form.processing"
            >
                Resend verification email
            </PrimaryButton>

            <Link
                :href="route('logout')"
                method="post"
                as="button"
                class="flex w-full justify-center rounded-xl py-3 text-sm font-semibold text-slate-600 underline-offset-4 hover:text-primary-700 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-600"
            >
                Log out
            </Link>
        </form>
    </AuthGuestShell>
</template>

<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AuthGuestShell from '@/Layouts/Auth/AuthGuestShell.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    status: {
        type: String,
        default: null,
    },
});

const form = useForm({});

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);

function submit() {
    form.post(route('verification.send'));
}
</script>
