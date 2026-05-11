<template>
    <AuthGuestShell>
        <Head title="Forgot Password" />

        <h1 class="font-display text-2xl font-bold text-slate-900">
            Reset password
        </h1>
        <p class="mt-2 text-sm leading-relaxed text-slate-600">
            Enter your email — we will send a secure link to choose a new password.
        </p>

        <div
            v-if="status"
            class="mt-6 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 ring-1 ring-emerald-100"
            role="status"
        >
            {{ status }}
        </div>

        <form class="mt-8 space-y-6" @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="Email address" />
                <TextInput
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="mt-2 block w-full rounded-xl border-slate-200 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                    required
                    autofocus
                    autocomplete="username"
                />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <PrimaryButton
                class="flex w-full justify-center rounded-2xl py-3.5 text-sm font-bold normal-case tracking-normal"
                :class="{ 'opacity-60': form.processing }"
                :disabled="form.processing"
            >
                Email reset link
            </PrimaryButton>
        </form>
    </AuthGuestShell>
</template>

<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthGuestShell from '@/Layouts/Auth/AuthGuestShell.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
        default: null,
    },
});

const form = useForm({
    email: '',
});

function submit() {
    form.post(route('password.email'));
}
</script>
