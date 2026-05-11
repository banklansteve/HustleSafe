<template>
    <AuthGuestShell>
        <Head title="Confirm Password" />

        <h1 class="font-display text-2xl font-bold text-slate-900">
            Confirm it is you
        </h1>
        <p class="mt-2 text-sm text-slate-600">
            Enter your password to continue to this protected area.
        </p>

        <form class="mt-8 space-y-6" @submit.prevent="submit">
            <div>
                <InputLabel for="password" value="Password" />
                <TextInput
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="mt-2 block w-full rounded-xl border-slate-200 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                    required
                    autocomplete="current-password"
                    autofocus
                />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <PrimaryButton
                class="flex w-full justify-center rounded-2xl py-3.5 font-bold normal-case"
                :class="{ 'opacity-60': form.processing }"
                :disabled="form.processing"
            >
                Confirm
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

const form = useForm({
    password: '',
});

function submit() {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    });
}
</script>
