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
                    Step {{ step }} / {{ maxStep }}
                </div>
            </div>

            <div class="mt-6 flex gap-1.5 sm:mt-8">
                <div
                    v-for="s in maxStep"
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
                                <UiSelect
                                    id="gender"
                                    v-model="form.gender"
                                    class="mt-2"
                                    :options="genderOptions"
                                    placeholder="Optional — choose"
                                    :invalid="!!fieldError('gender')"
                                />
                                <InputError class="mt-2" :message="fieldError('gender')" />
                            </div>
                            <div>
                                <InputLabel for="date_of_birth" value="Date of birth (optional)" />
                                <PremiumDatePicker
                                    id="date_of_birth"
                                    v-model="form.date_of_birth"
                                    class="mt-2"
                                    placeholder="DD/MM/YYYY"
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
                        <div>
                            <InputLabel for="city" value="City / town" />
                            <TextInput
                                id="city"
                                v-model="form.city"
                                type="text"
                                class="mt-2 block w-full rounded-xl border-slate-200 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                placeholder="e.g. Ikeja, Port Harcourt, Wuse"
                                autocomplete="address-level2"
                            />
                            <InputError class="mt-2" :message="fieldError('city')" />
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel for="state_id" value="State" />
                                <UiSelect
                                    id="state_id"
                                    v-model="form.state_id"
                                    class="mt-2"
                                    :options="stateOptions"
                                    placeholder="Select state"
                                    :invalid="!!fieldError('state_id')"
                                />
                                <InputError class="mt-2" :message="fieldError('state_id')" />
                            </div>
                            <div>
                                <InputLabel for="local_government_id" value="Local government (LGA)" />
                                <UiSelect
                                    id="local_government_id"
                                    v-model="form.local_government_id"
                                    class="mt-2"
                                    :options="lgaUiOptions"
                                    :placeholder="form.state_id ? 'Select LGA' : 'Choose a state first'"
                                    :disabled="!form.state_id"
                                    :invalid="!!fieldError('local_government_id')"
                                />
                                <InputError class="mt-2" :message="fieldError('local_government_id')" />
                            </div>
                        </div>
                    </div>

                    <!-- Step 5 -->
                    <div v-else-if="step === 5" key="s5" class="space-y-4">
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

                    <!-- Step 6 — Safe Hustler work categories -->
                    <div v-else key="s6" class="space-y-5">
                        <div>
                            <p class="text-sm font-semibold text-slate-800">
                                What kind of quests should we match you with?
                            </p>
                            <p class="mt-2 text-sm font-medium leading-relaxed text-slate-600">
                                Pick every subcategory you are strong in — you can refine this later in your profile.
                            </p>
                        </div>
                        <InputError :message="fieldError('quest_category_ids')" />
                        <div class="max-h-[min(28rem,55vh)] space-y-5 overflow-y-auto pr-1">
                            <div
                                v-for="parent in questCategories"
                                :key="parent.id"
                                class="rounded-2xl border border-slate-100 bg-slate-50/80 p-4 sm:p-5"
                            >
                                <p class="font-display text-sm font-bold text-slate-900">
                                    {{ parent.name }}
                                </p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <button
                                        v-for="child in parent.children"
                                        :key="child.id"
                                        type="button"
                                        class="rounded-full border px-3 py-2 text-xs font-bold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 sm:text-sm"
                                        :class="
                                            form.quest_category_ids.includes(child.id)
                                                ? 'border-primary-600 bg-primary-600 text-white shadow-md shadow-primary-900/20'
                                                : 'border-slate-200 bg-white text-slate-700 hover:border-primary-300'
                                        "
                                        @click="toggleCategory(child.id)"
                                    >
                                        {{ child.name }}
                                    </button>
                                </div>
                            </div>
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
                            v-if="showContinue"
                            type="button"
                            class="inline-flex w-full items-center justify-center rounded-xl bg-primary-700 px-6 py-3.5 text-sm font-bold text-white shadow-lg shadow-primary-900/25 transition hover:bg-primary-800 focus:outline-none focus-visible:ring-4 focus-visible:ring-primary-300 sm:w-auto"
                            @click="nextStep"
                        >
                            Continue
                        </button>
                        <button
                            v-if="showCreateAccount"
                            type="button"
                            class="inline-flex w-full items-center justify-center rounded-2xl border border-transparent bg-primary-700 px-6 py-4 text-sm font-bold text-white shadow-lg shadow-primary-900/25 transition hover:bg-primary-800 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 sm:w-auto sm:min-w-[200px]"
                            :class="{ 'cursor-not-allowed opacity-60': form.processing }"
                            :disabled="form.processing"
                            @click="submit"
                        >
                            Create account
                        </button>
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
import TextInput from '@/Components/TextInput.vue';
import PremiumDatePicker from '@/Components/Ui/PremiumDatePicker.vue';
import UiSelect from '@/Components/Ui/UiSelect.vue';
import AuthSplitLayout from '@/Layouts/Auth/AuthSplitLayout.vue';
import { FaRegBuilding } from '@kalimahapps/vue-icons/fa';
import { SparklesIcon } from '@heroicons/vue/24/solid';
import { useVuelidate } from '@vuelidate/core';
import { email, helpers, maxLength, minLength, required } from '@vuelidate/validators';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    locations: {
        type: Array,
        default: () => [],
    },
    questCategories: {
        type: Array,
        default: () => [],
    },
});

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
    city: '',
    state_id: 0,
    local_government_id: 0,
    quest_category_ids: [],
    password: '',
    password_confirmation: '',
});

const maxStep = computed(() => (form.account_type === 'hustler' ? 6 : 5));

const showContinue = computed(() => {
    if (step.value < 5) {
        return true;
    }
    if (step.value === 5 && form.account_type === 'hustler') {
        return true;
    }

    return false;
});

const showCreateAccount = computed(() => {
    if (step.value === 5 && form.account_type === 'sponsor') {
        return true;
    }
    if (step.value === 6) {
        return true;
    }

    return false;
});

const lgaOptions = computed(() => {
    const st = props.locations.find((s) => s.id === form.state_id);

    return st?.local_governments ?? [];
});

const genderOptions = [
    { value: 'female', label: 'Female' },
    { value: 'male', label: 'Male' },
    { value: 'non_binary', label: 'Non-binary' },
    { value: 'prefer_not_to_say', label: 'Prefer not to say' },
];

const stateOptions = computed(() =>
    props.locations.map((st) => ({
        value: st.id,
        label: st.name,
    })),
);

const lgaUiOptions = computed(() =>
    lgaOptions.value.map((lg) => ({
        value: lg.id,
        label: lg.name,
    })),
);

watch(
    () => form.state_id,
    () => {
        form.local_government_id = 0;
    },
);

const phonePattern = helpers.regex(/^[0-9+\-\s()]+$/);

const requiredField = (fieldName) =>
    helpers.withMessage(`The ${fieldName} field is required.`, required);

const rules = computed(() => ({
    account_type: { required: requiredField('account type') },
    first_name: {
        required: requiredField('first name'),
        maxLength: helpers.withMessage(
            'The first name may not be greater than 120 characters.',
            maxLength(120),
        ),
    },
    last_name: {
        required: requiredField('last name'),
        maxLength: helpers.withMessage(
            'The last name may not be greater than 120 characters.',
            maxLength(120),
        ),
    },
    gender: {},
    date_of_birth: {},
    company_name: {
        maxLength: helpers.withMessage(
            'The company name may not be greater than 255 characters.',
            maxLength(255),
        ),
    },
    email: {
        required: requiredField('email address'),
        email: helpers.withMessage('The email must be a valid email address.', email),
    },
    phone: {
        required: requiredField('phone number'),
        phonePattern: helpers.withMessage('Use a valid phone number.', phonePattern),
    },
    address_line: {
        required: requiredField('address'),
        maxLength: helpers.withMessage(
            'The address may not be greater than 500 characters.',
            maxLength(500),
        ),
    },
    city: {
        required: requiredField('city / town'),
        maxLength: helpers.withMessage(
            'The city may not be greater than 160 characters.',
            maxLength(160),
        ),
    },
    state_id: {
        validState: helpers.withMessage(
            'Please select a state.',
            (value) => value !== '' && value !== null && Number(value) >= 1,
        ),
    },
    local_government_id: {
        validLga: helpers.withMessage(
            'Please select a local government area.',
            (value) => value !== '' && value !== null && Number(value) >= 1,
        ),
    },
    password: {
        required: requiredField('password'),
        minLength: helpers.withMessage(
            'The password must be at least 8 characters.',
            minLength(8),
        ),
    },
    password_confirmation: {
        required: requiredField('password confirmation'),
        sameAsPassword: helpers.withMessage(
            'The password confirmation does not match.',
            (value) => value === form.password,
        ),
    },
    quest_category_ids: {
        minPicked: helpers.withMessage(
            'Pick at least one work category.',
            () => form.account_type !== 'hustler'
                || (Array.isArray(form.quest_category_ids) && form.quest_category_ids.length >= 1),
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
        4: ['address_line', 'city', 'state_id', 'local_government_id'],
        5: ['password', 'password_confirmation'],
        6: ['quest_category_ids'],
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

        return;
    }
    if (step.value === 5 && form.account_type === 'hustler') {
        step.value = 6;
    }
}

function prevStep() {
    if (step.value > 1) {
        step.value -= 1;
    }
}

function toggleCategory(id) {
    const list = form.quest_category_ids;
    const i = list.indexOf(id);
    if (i === -1) {
        list.push(id);
    } else {
        list.splice(i, 1);
    }
}

async function submit() {
    if (form.account_type === 'hustler' && step.value < 6) {
        const ok5 = await validateStep(5);
        if (!ok5) {
            return;
        }
        step.value = 6;

        return;
    }

    const ok = await v$.value.$validate();
    if (!ok) {
        return;
    }
    form
        .transform((data) => {
            const payload = {
                ...data,
                gender: data.gender === '' ? null : data.gender,
                date_of_birth: data.date_of_birth === '' ? null : data.date_of_birth,
                company_name: data.company_name === '' ? null : data.company_name,
                state_id: Number(data.state_id) || null,
                local_government_id: Number(data.local_government_id) || null,
            };
            if (payload.account_type === 'sponsor') {
                delete payload.quest_category_ids;
            }

            return payload;
        })
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
