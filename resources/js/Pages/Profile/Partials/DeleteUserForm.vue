<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);

const form = useForm({
    password: '',
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;

    nextTick(() => passwordInput.value.focus());
};

const deleteUser = () => {
    form.delete(route('account.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;

    form.clearErrors();
    form.reset();
};
</script>

<template>
    <section class="space-y-5">
        <header>
            <h2 class="font-display text-lg font-bold text-rose-950">
                Delete account
            </h2>

            <p class="mt-2 text-sm font-medium leading-relaxed text-rose-900/85">
                This permanently removes your profile, quests, portfolio, and reviews tied to HustleSafe. Download anything you need before continuing.
            </p>
        </header>

        <button
            type="button"
            class="rounded-full bg-rose-600 px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-rose-900/15 transition hover:bg-rose-700"
            @click="confirmUserDeletion"
        >
            Delete my account
        </button>

        <Modal :show="confirmingUserDeletion" @close="closeModal">
            <div class="p-6 sm:p-8">
                <h2 class="font-display text-xl font-bold text-slate-900">
                    Delete your account permanently?
                </h2>

                <p class="mt-3 text-sm font-medium leading-relaxed text-slate-600">
                    This cannot be undone. Enter your current password to confirm.
                </p>

                <div class="mt-6">
                    <InputLabel for="delete-account-password" value="Current password" />

                    <TextInput
                        id="delete-account-password"
                        ref="passwordInput"
                        v-model="form.password"
                        type="password"
                        class="mt-2 block w-full rounded-xl border-slate-200 text-sm font-medium shadow-sm focus:border-rose-500 focus:ring-rose-500"
                        placeholder="Password"
                        autocomplete="current-password"
                        @keyup.enter="deleteUser"
                    />

                    <InputError :message="form.errors.password" class="mt-2" />
                </div>

                <div class="mt-8 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        class="inline-flex justify-center rounded-full border border-slate-200 bg-white px-5 py-2.5 text-sm font-bold text-slate-800 shadow-sm hover:bg-slate-50"
                        @click="closeModal"
                    >
                        Cancel
                    </button>

                    <button
                        type="button"
                        class="inline-flex justify-center rounded-full bg-rose-600 px-5 py-2.5 text-sm font-bold text-white shadow-md hover:bg-rose-700 disabled:opacity-40"
                        :disabled="form.processing"
                        @click="deleteUser"
                    >
                        Yes, delete forever
                    </button>
                </div>
            </div>
        </Modal>
    </section>
</template>
