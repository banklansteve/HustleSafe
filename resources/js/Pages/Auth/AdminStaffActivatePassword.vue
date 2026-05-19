<template>
    <AuthGuestShell>
        <Head title="Set admin password" />

        <p class="text-xs font-black uppercase tracking-[0.24em] text-primary-600">Admin access</p>
        <h1 class="mt-2 font-display text-2xl font-bold text-slate-900">
            Set your admin password
        </h1>
        <p class="mt-2 text-sm leading-6 text-slate-600">
            Your admin account for
            <span class="font-semibold text-slate-900">{{ email }}</span>
            is ready. Choose a strong password to continue to the admin dashboard.
        </p>

        <form class="mt-8 space-y-5" @submit.prevent="submit">
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
                Save password & open dashboard
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
    email: { type: String, required: true },
    name: { type: String, default: '' },
    post_url: { type: String, required: true },
});

const form = useForm({
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post(props.post_url, {
        preserveScroll: true,
    });
}
</script>
