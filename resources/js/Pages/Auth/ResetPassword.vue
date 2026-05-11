<template>
    <AuthGuestShell>
        <Head title="Reset Password" />

        <h1 class="font-display text-2xl font-bold text-slate-900">
            Choose a new password
        </h1>
        <p class="mt-2 text-sm text-slate-600">
            Pick a strong password you have not used elsewhere.
        </p>

        <form class="mt-8 space-y-5" @submit.prevent="submit">
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

            <div>
                <InputLabel for="password" value="New password" />
                <TextInput
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="mt-2 block w-full rounded-xl border-slate-200 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                    required
                    autocomplete="new-password"
                />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div>
                <InputLabel for="password_confirmation" value="Confirm password" />
                <TextInput
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    class="mt-2 block w-full rounded-xl border-slate-200 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                    required
                    autocomplete="new-password"
                />
                <InputError class="mt-2" :message="form.errors.password_confirmation" />
            </div>

            <PrimaryButton
                class="flex w-full justify-center rounded-2xl py-3.5 font-bold normal-case"
                :class="{ 'opacity-60': form.processing }"
                :disabled="form.processing"
            >
                Reset password
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

const props = defineProps({
    email: {
        type: String,
        required: true,
    },
    token: {
        type: String,
        required: true,
    },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>
