<template>
    <AuthSplitLayout
        illustration-src="/images/auth/register-illustration.svg"
        illustration-alt="Illustration of onboarding and joining the marketplace"
        aside-eyebrow="Create account"
        aside-title="Join Nigeria's escrow-first marketplace in five guided steps."
        aside-subtitle="Tailored for Project Sponsors and Safe Hustlers — verify once, then browse milestones built for clarity."
    >
        <Head title="Create account" />

        <div
            class="rounded-[1.75rem] bg-white p-7 shadow-xl shadow-slate-300/40 ring-1 ring-slate-200/90 sm:p-12 lg:min-h-[min(36rem,72vh)] lg:p-14 xl:rounded-[2rem] xl:p-16"
        >
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="font-display text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl lg:text-4xl">
                        Create your account
                    </h1>
                    <p class="mt-1 text-sm text-slate-600">
                        Five quick steps — tailored for Nigerian crews and solo hustlers.
                    </p>
                </div>
                <div class="hidden text-right text-xs font-semibold uppercase tracking-wider text-slate-400 sm:block">
                    Step {{ step }} / 5
                </div>
            </div>

            <div class="mt-6 flex gap-1.5 sm:mt-8">
                <div
                    v-for="s in 5"
                    :key="s"
                    class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-100"
                    :aria-current="step === s ? 'step' : undefined"
                >
                    <div
                        class="h-full rounded-full bg-gradient-to-r from-primary-600 to-teal-500 transition-all duration-500"
                        :class="step >= s ? 'w-full opacity-100' : 'w-0 opacity-0'"
                    />
                </div>
            </div>

            <div class="mt-6 space-y-4 sm:mt-8">
                <GoogleSignInButton auth-screen="register" label="Sign up with Google" />
                <div class="relative flex items-center py-1">
                    <div class="grow border-t border-slate-200" />
                    <span class="mx-4 shrink text-xs font-semibold uppercase tracking-wider text-slate-400">
                        Or continue with email
                    </span>
                    <div class="grow border-t border-slate-200" />
                </div>
            </div>

            <form class="mt-6 sm:mt-7" @submit.prevent="submit">
                <Transition name="fade-slide" mode="out-in">
                    <!-- Step 1 -->
                    <div v-if="step === 1" key="s1" class="space-y-5">
                        <p class="text-sm font-semibold text-slate-800">
                            How will you use HustleSafe?
                        </p>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <button
                                type="button"
                                class="group flex flex-col rounded-2xl border p-5 text-left ring-2 transition focus:outline-none focus-visible:ring-4 focus-visible:ring-primary-300"
                                :class="
                                    form.account_type === 'sponsor'
                                        ? 'border-primary-600 bg-primary-50 ring-primary-500'
                                        : 'border-slate-200 hover:border-primary-300 hover:bg-slate-50'
                                "
                                @click="form.account_type = 'sponsor'"
                            >
                                <FaRegBuilding
                                    class="h-9 w-9 text-primary-700 transition group-hover:scale-105"
                                    aria-hidden="true"
                                />
                                <span class="font-display mt-4 text-lg font-bold text-slate-900">
                                    Project Sponsor
                                </span>
                                <span class="mt-2 text-sm leading-snug text-slate-600">
                                    I post briefs, fund escrow, and approve deliveries.
                                </span>
                            </button>
                            <button
                                type="button"
                                class="group flex flex-col rounded-2xl border p-5 text-left ring-2 transition focus:outline-none focus-visible:ring-4 focus-visible:ring-primary-300"
                                :class="
                                    form.account_type === 'hustler'
                                        ? 'border-primary-600 bg-primary-50 ring-primary-500'
                                        : 'border-slate-200 hover:border-primary-300 hover:bg-slate-50'
                                "
                                @click="form.account_type = 'hustler'"
                            >
                                <SparklesIcon
                                    class="h-9 w-9 text-primary-700 transition group-hover:scale-105"
                                    aria-hidden="true"
                                />
                                <span class="font-display mt-4 text-lg font-bold text-slate-900">
                                    Safe Hustler
                                </span>
                                <span class="mt-2 text-sm leading-snug text-slate-600">
                                    I deliver work, hit milestones, and withdraw payouts safely.
                                </span>
                            </button>
                        </div>
                        <InputError :message="fieldError('account_type')" />
                    </div>

                    <!-- Step 2 -->
                    <div v-else-if="step === 2" key="s2" class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel for="first_name" value="First name" />
                                <TextInput
                                    id="first_name"
                                    v-model="form.first_name"
                                    type="text"
                                    class="mt-2 block w-full rounded-xl border-slate-200 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                    autocomplete="given-name"
                                />
                                <InputError class="mt-2" :message="fieldError('first_name')" />
                            </div>
                            <div>
                                <InputLabel for="last_name" value="Last name" />
                                <TextInput
                                    id="last_name"
                                    v-model="form.last_name"
                                    type="text"
                                    class="mt-2 block w-full rounded-xl border-slate-200 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                    autocomplete="family-name"
                                />
                                <InputError class="mt-2" :message="fieldError('last_name')" />
                            </div>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel for="gender" value="Gender (optional)" />
                                <select
                                    id="gender"
                                    v-model="form.gender"
                                    class="mt-2 block w-full rounded-xl border-slate-200 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                >
                                    <option value="">
                                        Optional — choose
                                    </option>
                                    <option value="female">
                                        Female
                                    </option>
                                    <option value="male">
                                        Male
                                    </option>
                                    <option value="non_binary">
                                        Non-binary
                                    </option>
                                    <option value="prefer_not_to_say">
                                        Prefer not to say
                                    </option>
                                </select>
                                <InputError class="mt-2" :message="fieldError('gender')" />
                            </div>
                            <div>
                                <InputLabel for="date_of_birth" value="Date of birth (optional)" />
                                <TextInput
                                    id="date_of_birth"
                                    v-model="form.date_of_birth"
                                    type="date"
                                    class="mt-2 block w-full rounded-xl border-slate-200 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                />
                                <InputError class="mt-2" :message="fieldError('date_of_birth')" />
                            </div>
                        </div>
                        <div v-if="form.account_type === 'sponsor'">
                            <InputLabel for="company_name" value="Company or team name (optional)" />
                            <TextInput
                                id="company_name"
                                v-model="form.company_name"
                                type="text"
                                class="mt-2 block w-full rounded-xl border-slate-200 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                placeholder="e.g. Nova Labs NG"
                            />
                            <InputError class="mt-2" :message="fieldError('company_name')" />
                        </div>
                    </div>

                    <!-- Step 3 -->
                    <div v-else-if="step === 3" key="s3" class="space-y-4">
                        <div>
                            <InputLabel for="email" value="Email address" />
                            <TextInput
                                id="email"
                                v-model="form.email"
                                type="email"
                                class="mt-2 block w-full rounded-xl border-slate-200 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                autocomplete="email"
                            />
                            <InputError class="mt-2" :message="fieldError('email')" />
                        </div>
                        <div>
                            <InputLabel for="phone" value="Phone number" />
                            <TextInput
                                id="phone"
                                v-model="form.phone"
                                type="tel"
                                class="mt-2 block w-full rounded-xl border-slate-200 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                placeholder="+234 …"
                                autocomplete="tel"
                            />
                            <InputError class="mt-2" :message="fieldError('phone')" />
                        </div>
                    </div>

                    <!-- Step 4 -->
                    <div v-else-if="step === 4" key="s4" class="space-y-4">
                        <div>
                            <InputLabel for="address_line" value="Full address" />
                            <textarea
                                id="address_line"
                                v-model="form.address_line"
                                rows="3"
                                class="mt-2 block w-full rounded-xl border-slate-200 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                placeholder="Street, city landmark, apartment details…"
                            />
                            <InputError class="mt-2" :message="fieldError('address_line')" />
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel for="state" value="State" />
                                <select
                                    id="state"
                                    v-model="form.state"
                                    class="mt-2 block w-full rounded-xl border-slate-200 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                >
                                    <option disabled value="">
                                        Select state
                                    </option>
                                    <option v-for="st in states" :key="st" :value="st">
                                        {{ st }}
                                    </option>
                                </select>
                                <InputError class="mt-2" :message="fieldError('state')" />
                            </div>
                            <div>
                                <InputLabel for="local_government" value="Local government (LGA)" />
                                <TextInput
                                    id="local_government"
                                    v-model="form.local_government"
                                    type="text"
                                    class="mt-2 block w-full rounded-xl border-slate-200 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                    placeholder="e.g. Ikeja"
                                />
                                <InputError class="mt-2" :message="fieldError('local_government')" />
                            </div>
                        </div>
                    </div>

                    <!-- Step 5 -->
                    <div v-else key="s5" class="space-y-4">
                        <div>
                            <InputLabel for="password" value="Password" />
                            <TextInput
                                id="password"
                                v-model="form.password"
                                type="password"
                                class="mt-2 block w-full rounded-xl border-slate-200 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                autocomplete="new-password"
                            />
                            <InputError class="mt-2" :message="fieldError('password')" />
                        </div>
                        <div>
                            <InputLabel for="password_confirmation" value="Confirm password" />
                            <TextInput
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                type="password"
                                class="mt-2 block w-full rounded-xl border-slate-200 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                autocomplete="new-password"
                            />
                            <InputError class="mt-2" :message="fieldError('password_confirmation')" />
                        </div>
                    </div>
                </Transition>

                <div class="mt-10 flex flex-col-reverse gap-3 sm:flex-row sm:justify-between">
                    <button
                        v-if="step > 1"
                        type="button"
                        class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-bold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-600"
                        @click="prevStep"
                    >
                        Back
                    </button>
                    <div class="flex flex-1 justify-end gap-3">
                        <button
                            v-if="step < 5"
                            type="button"
                            class="inline-flex w-full items-center justify-center rounded-xl bg-primary-700 px-6 py-3.5 text-sm font-bold text-white shadow-lg shadow-primary-900/25 transition hover:bg-primary-800 focus:outline-none focus-visible:ring-4 focus-visible:ring-primary-300 sm:w-auto"
                            @click="nextStep"
                        >
                            Continue
                        </button>
                        <PrimaryButton
                            v-else
                            class="w-full justify-center rounded-2xl px-6 py-4 text-sm font-bold normal-case tracking-normal sm:w-auto sm:min-w-[200px]"
                            :class="{ 'opacity-60': form.processing }"
                            :disabled="form.processing"
                        >
                            Create account
                        </PrimaryButton>
                    </div>
                </div>

                <p class="mt-8 text-center text-sm text-slate-600">
                    Already registered?
                    <Link
                        :href="route('login')"
                        class="font-bold text-primary-700 underline-offset-4 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-600"
                    >
                        Log in
                    </Link>
                </p>
            </form>
        </div>
    </AuthSplitLayout>
</template>

<script setup>
import GoogleSignInButton from '@/Components/Auth/GoogleSignInButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthSplitLayout from '@/Layouts/Auth/AuthSplitLayout.vue';
import { NIGERIA_STATES } from '@/constants/nigeriaStates.js';
import { FaRegBuilding } from '@kalimahapps/vue-icons/fa';
import { SparklesIcon } from '@heroicons/vue/24/solid';
import { useVuelidate } from '@vuelidate/core';
import { email, helpers, maxLength, minLength, required } from '@vuelidate/validators';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const states = NIGERIA_STATES;

const step = ref(1);

const form = useForm({
    account_type: '',
    first_name: '',
    last_name: '',
    gender: '',
    date_of_birth: '',
    company_name: '',
    email: '',
    phone: '',
    address_line: '',
    local_government: '',
    state: '',
    password: '',
    password_confirmation: '',
});

const phonePattern = helpers.regex(/^[0-9+\-\s()]+$/);

const rules = computed(() => ({
    account_type: { required },
    first_name: { required, maxLength: maxLength(120) },
    last_name: { required, maxLength: maxLength(120) },
    gender: {},
    date_of_birth: {},
    company_name: { maxLength: maxLength(255) },
    email: { required, email },
    phone: { required, phonePattern },
    address_line: { required, maxLength: maxLength(500) },
    local_government: { required, maxLength: maxLength(120) },
    state: { required, maxLength: maxLength(120) },
    password: { required, minLength: minLength(8) },
    password_confirmation: {
        required,
        sameAsPassword: helpers.withMessage(
            'Passwords must match.',
            (value) => value === form.password,
        ),
    },
}));

const v$ = useVuelidate(rules, form);

function fieldError(key) {
    if (form.errors[key]) {
        return form.errors[key];
    }
    const err = v$.value[key]?.$errors?.[0]?.$message;

    return err ?? '';
}

async function validateStep(current) {
    const fieldsByStep = {
        1: ['account_type'],
        2: ['first_name', 'last_name'],
        3: ['email', 'phone'],
        4: ['address_line', 'local_government', 'state'],
        5: ['password', 'password_confirmation'],
    };
    const keys = fieldsByStep[current] ?? [];
    let ok = true;
    for (const key of keys) {
        await v$.value[key].$touch();
        if (v$.value[key].$invalid) {
            ok = false;
        }
    }

    return ok;
}

async function nextStep() {
    const ok = await validateStep(step.value);
    if (!ok) {
        return;
    }
    if (step.value < 5) {
        step.value += 1;
    }
}

function prevStep() {
    if (step.value > 1) {
        step.value -= 1;
    }
}

async function submit() {
    const ok = await v$.value.$validate();
    if (!ok) {
        return;
    }
    form
        .transform((data) => ({
            ...data,
            gender: data.gender === '' ? null : data.gender,
            date_of_birth: data.date_of_birth === '' ? null : data.date_of_birth,
            company_name: data.company_name === '' ? null : data.company_name,
        }))
        .post(route('register'), {
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
}
</script>

<style scoped>
.fade-slide-enter-active,
.fade-slide-leave-active {
    transition:
        opacity 0.35s ease,
        transform 0.35s ease;
}
.fade-slide-enter-from,
.fade-slide-leave-to {
    opacity: 0;
    transform: translateY(12px);
}
</style>
