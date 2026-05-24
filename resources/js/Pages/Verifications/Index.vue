<template>
    <AppShell>
        <Head title="Verifications" />

        <div class="mx-auto max-w-2xl pb-14">
            <header class="mb-8">
                <p class="text-xs font-black uppercase tracking-[0.2em] text-primary-700">
                    Account
                </p>
                <h1 class="mt-2 font-display text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">
                    Trust &amp; verification
                </h1>
                <p class="mt-3 max-w-lg text-base font-medium leading-relaxed text-slate-600">
                    Complete each step to raise your verification level and unlock higher quest limits on HustleSafe.
                </p>
            </header>

            <div
                v-if="feedback"
                class="mt-6 rounded-2xl border p-4 shadow-sm"
                :class="feedbackBannerClass"
                role="status"
            >
                <p class="text-xs font-black uppercase tracking-wide">
                    {{ feedback.action_label }} · {{ feedback.category_label }}
                </p>
                <p class="mt-2 text-base font-bold text-slate-900">
                    <template v-if="feedback.action === 'approve'">
                        Your submission was approved.
                    </template>
                    <template v-else-if="feedback.action === 'request_corrections'">
                        Please update your submission and send it again for review.
                    </template>
                    <template v-else>
                        Your submission could not be approved.
                    </template>
                </p>
                <p v-if="feedback.reason_label" class="mt-2 text-sm font-semibold text-slate-800">
                    <span class="font-black">Reason:</span> {{ feedback.reason_label }}
                </p>
                <p v-if="feedback.reason_note" class="mt-1 text-sm font-medium text-slate-700">
                    {{ feedback.reason_note }}
                </p>
                <p v-else-if="feedback.reason_display && !feedback.reason_label" class="mt-2 text-sm font-semibold text-slate-800">
                    {{ feedback.reason_display }}
                </p>
                <p v-if="feedback.reviewed_at_label" class="mt-3 text-xs font-bold text-slate-500">
                    Reviewed {{ feedback.reviewed_at_label }}
                </p>
            </div>

            <div class="mb-8 grid gap-4 sm:grid-cols-2">
                <div class="rounded-3xl border border-slate-200/80 bg-white p-5 shadow-lg shadow-slate-200/30 ring-1 ring-slate-100">
                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">
                        Verification level
                    </p>
                    <p class="mt-2 text-2xl font-black text-slate-900">
                        {{ trust.current_label || ('L' + trust.current_level) }}
                    </p>
                    <p v-if="trust.next_level_label" class="mt-2 text-xs font-bold text-primary-800">
                        Next: {{ trust.next_level_label }}
                    </p>
                </div>
                <div class="rounded-3xl border border-primary-100 bg-gradient-to-br from-primary-50/90 to-white p-5 shadow-lg shadow-primary-100/20 ring-1 ring-primary-100">
                    <p class="text-[10px] font-black uppercase tracking-wider text-primary-800">
                        {{ trust.limit_label || (is_freelancer ? 'Proposal limit' : 'Quest posting limit') }}
                    </p>
                    <p class="mt-2 text-2xl font-black text-slate-900">
                        {{ trust.limit_formatted || formatLimit(trust.limit_minor) }}
                    </p>
                    <p v-if="trust.next_level_limit_formatted" class="mt-2 text-xs font-semibold text-primary-900">
                        {{ trust.next_level_label }} unlocks up to {{ trust.next_level_limit_formatted }}
                    </p>
                </div>
            </div>

            <div v-if="trust.current_level < 5" class="mb-8">
                <div class="mb-2 flex justify-between text-[10px] font-black uppercase tracking-wide text-slate-500">
                    <span>Progress</span>
                    <span>L{{ trust.current_level }} → L{{ trust.next_level ?? 5 }}</span>
                </div>
                <div class="flex gap-1">
                    <div
                        v-for="n in 6"
                        :key="n"
                        class="h-1.5 flex-1 rounded-full transition-colors"
                        :class="n - 1 <= trust.current_level ? 'bg-primary-600' : n - 1 === trust.next_level ? 'bg-primary-200' : 'bg-slate-200'"
                    />
                </div>
            </div>

            <p v-if="trust.cooldown?.active" class="-mt-4 mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-bold text-amber-900">
                New-account cooldown until {{ formatWhen(trust.cooldown.expires_at) }}.
            </p>
            <p
                v-if="trust.enforced_limit_minor != null && trust.enforced_limit_minor < trust.limit_minor"
                class="-mt-4 mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-bold text-amber-900"
            >
                Posting capped at {{ trust.enforced_limit_formatted || formatLimit(trust.enforced_limit_minor) }} until cooldown ends.
            </p>

            <section
                v-if="next_step?.type === 'complete'"
                class="rounded-3xl border border-emerald-200/80 bg-gradient-to-br from-emerald-50 to-white p-6 shadow-lg shadow-emerald-100/30 ring-1 ring-emerald-100"
            >
                <p class="text-xs font-black uppercase tracking-wide text-emerald-800">All steps complete</p>
                <p class="mt-2 font-display text-xl font-bold text-slate-900">{{ next_step.title }}</p>
                <p class="mt-2 text-sm font-medium leading-relaxed text-slate-700">{{ next_step.message }}</p>
            </section>

            <section
                v-else-if="next_step"
                class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-xl shadow-slate-200/40 ring-1 ring-slate-100"
            >
                <div
                    v-if="stepCallout"
                    class="border-b border-primary-100/80 bg-gradient-to-r from-primary-50 via-sky-50/50 to-white px-5 py-4 sm:px-6"
                    role="status"
                >
                    <p v-if="next_step.progress_notice" class="text-[10px] font-black uppercase tracking-[0.15em] text-primary-800">
                        {{ next_step.progress_notice }}
                    </p>
                    <p
                        v-if="next_step.info_bar"
                        class="text-sm font-semibold leading-relaxed text-slate-800"
                        :class="next_step.progress_notice ? 'mt-1' : ''"
                    >
                        {{ next_step.info_bar }}
                    </p>
                </div>

                <div class="p-5 sm:p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">
                                {{ next_step.target_level_label ? `Unlock ${next_step.target_level_label}` : 'Your next step' }}
                            </p>
                            <h2 class="mt-1 font-display text-xl font-bold text-slate-900">
                                {{ next_step.title }}
                            </h2>
                            <p class="mt-2 text-sm font-medium leading-relaxed text-slate-600">
                                {{ next_step.message }}
                            </p>
                        </div>
                        <span
                            v-if="next_step.status"
                            class="shrink-0 rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-wide"
                            :class="statusPillClass(next_step.status)"
                        >
                            {{ next_step.status_label || statusLabel(next_step.status) }}
                        </span>
                    </div>

                    <div v-if="next_step.type === 'email'" class="mt-6 space-y-4">
                    <PrimaryButton
                        class="w-full justify-center rounded-2xl py-3"
                        :class="{ 'opacity-60': resendForm.processing }"
                        :disabled="resendForm.processing"
                        @click="resendVerificationEmail"
                    >
                        Resend verification email
                    </PrimaryButton>
                    <p v-if="resendForm.recentlySuccessful" class="text-center text-sm font-semibold text-emerald-700">
                        A fresh link has been sent to your inbox.
                    </p>
                    </div>

                    <div v-else-if="next_step.type === 'account_age'" class="mt-6 rounded-2xl bg-slate-50 px-4 py-4 text-sm font-semibold text-slate-700 ring-1 ring-slate-100">
                    <span v-if="next_step.days_remaining > 0">No action needed right now — keep using HustleSafe while your account matures.</span>
                    <span v-else>Your L5 upgrade should apply shortly.</span>
                    </div>

                    <div v-else-if="next_step.type === 'nin_bvn'" class="mt-6 space-y-4">
                        <article
                            v-if="next_step.nin_slot"
                            class="rounded-2xl border border-slate-200/80 bg-slate-50/50 p-4 ring-1 ring-slate-100"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">NIN</p>
                                    <h3 class="mt-0.5 font-display text-base font-bold text-slate-900">{{ next_step.nin_slot.title }}</h3>
                                    <p class="mt-1 text-xs font-medium leading-relaxed text-slate-600">{{ next_step.nin_slot.description }}</p>
                                </div>
                                <span
                                    class="shrink-0 rounded-full px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wide"
                                    :class="statusPillClass(next_step.nin_slot.status)"
                                >
                                    {{ next_step.nin_slot.status_label || statusLabel(next_step.nin_slot.status) }}
                                </span>
                            </div>
                            <p
                                v-if="next_step.nin_slot.rejection_reason && next_step.nin_slot.status === 'available'"
                                class="mt-3 rounded-xl border border-rose-100 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-800"
                            >
                                {{ next_step.nin_slot.rejection_reason }}
                            </p>
                            <div
                                v-if="next_step.nin_slot.status === 'pending'"
                                class="mt-4 rounded-xl border border-amber-100 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-950"
                            >
                                Your NIN is with our team for review. You can submit your BVN below while you wait.
                            </div>
                            <form
                                v-else-if="next_step.nin_slot.status === 'available'"
                                class="mt-4 space-y-4"
                                @submit.prevent="submitNumber('nin')"
                            >
                                <div>
                                    <InputLabel for="nin-number" value="NIN number" />
                                    <TextInput
                                        id="nin-number"
                                        v-model="numberForms.nin"
                                        type="text"
                                        inputmode="numeric"
                                        maxlength="11"
                                        class="mt-2"
                                        placeholder="11 digits"
                                        autocomplete="off"
                                    />
                                    <InputError class="mt-2" :message="errors['nin.identifier_number']" />
                                </div>
                                <PrimaryButton class="w-full justify-center rounded-2xl py-3" :disabled="submitting === 'nin'">
                                    Submit NIN for review
                                </PrimaryButton>
                            </form>
                        </article>

                        <article
                            v-if="next_step.bvn_slot"
                            class="rounded-2xl border border-slate-200/80 bg-slate-50/50 p-4 ring-1 ring-slate-100"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">BVN</p>
                                    <h3 class="mt-0.5 font-display text-base font-bold text-slate-900">{{ next_step.bvn_slot.title }}</h3>
                                    <p class="mt-1 text-xs font-medium leading-relaxed text-slate-600">{{ next_step.bvn_slot.description }}</p>
                                </div>
                                <span
                                    class="shrink-0 rounded-full px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wide"
                                    :class="statusPillClass(next_step.bvn_slot.status)"
                                >
                                    {{ next_step.bvn_slot.status_label || statusLabel(next_step.bvn_slot.status) }}
                                </span>
                            </div>
                            <p
                                v-if="next_step.bvn_slot.rejection_reason && next_step.bvn_slot.status === 'available'"
                                class="mt-3 rounded-xl border border-rose-100 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-800"
                            >
                                {{ next_step.bvn_slot.rejection_reason }}
                            </p>
                            <div
                                v-if="next_step.bvn_slot.status === 'pending'"
                                class="mt-4 rounded-xl border border-amber-100 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-950"
                            >
                                Your BVN is with our team for review. You can submit your NIN above while you wait.
                            </div>
                            <form
                                v-else-if="next_step.bvn_slot.status === 'available'"
                                class="mt-4 space-y-4"
                                @submit.prevent="submitNumber('bvn')"
                            >
                                <div>
                                    <InputLabel for="bvn-number" value="BVN number" />
                                    <TextInput
                                        id="bvn-number"
                                        v-model="numberForms.bvn"
                                        type="text"
                                        inputmode="numeric"
                                        maxlength="11"
                                        class="mt-2"
                                        placeholder="11 digits"
                                        autocomplete="off"
                                    />
                                    <InputError class="mt-2" :message="errors['bvn.identifier_number']" />
                                </div>
                                <PrimaryButton class="w-full justify-center rounded-2xl py-3" :disabled="submitting === 'bvn'">
                                    Submit BVN for review
                                </PrimaryButton>
                            </form>
                        </article>
                    </div>

                    <template v-else-if="activeSlot">
                    <p v-if="activeSlot.notice" class="mt-3 rounded-xl bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700 ring-1 ring-slate-100">
                        {{ activeSlot.notice }}
                    </p>

                    <p v-if="activeSlot.rejection_reason && activeSlot.status === 'available'" class="mt-3 rounded-xl border border-rose-100 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-800">
                        {{ activeSlot.rejection_reason }}
                    </p>

                    <div v-if="activeSlot.status === 'pending' || next_step.type === 'pending'" class="mt-6 rounded-xl border border-amber-100 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-950">
                        Your submission is with our team. You will be notified when it is reviewed.
                    </div>

                    <div v-else-if="activeSlot.status === 'locked'" class="mt-6 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700">
                        {{ activeSlot.status_label || activeSlot.description }}
                    </div>

                    <ul v-if="activeSlot.instructions?.length" class="mt-3 list-disc space-y-1 pl-5 text-sm font-semibold text-slate-700">
                        <li v-for="(line, i) in activeSlot.instructions" :key="i">
                            {{ line }}
                        </li>
                    </ul>

                    <p v-if="activeSlot.address_hint" class="mt-3 rounded-xl border border-amber-100 bg-amber-50/80 px-3 py-2 text-xs font-semibold leading-relaxed text-amber-950">
                        {{ activeSlot.address_hint }}
                    </p>

                    <!-- NIN / BVN -->
                    <form
                        v-if="activeSlot.status === 'available' && ['nin', 'bvn'].includes(activeSlot.key)"
                        class="mt-5 space-y-4"
                        @submit.prevent="submitNumber(activeSlot.key)"
                    >
                        <div>
                            <InputLabel :for="`${activeSlot.key}-number`" :value="activeSlot.key === 'nin' ? 'NIN number' : 'BVN number'" />
                            <TextInput
                                :id="`${activeSlot.key}-number`"
                                v-model="numberForms[activeSlot.key]"
                                type="text"
                                inputmode="numeric"
                                maxlength="11"
                                class="mt-2"
                                placeholder="11 digits"
                                autocomplete="off"
                            />
                            <InputError class="mt-2" :message="errors[`${activeSlot.key}.identifier_number`]" />
                        </div>
                        <PrimaryButton class="w-full justify-center rounded-2xl py-3" :disabled="submitting === activeSlot.key">
                            Submit for review
                        </PrimaryButton>
                    </form>

                    <!-- Identity & address -->
                    <form
                        v-if="activeSlot.status === 'available' && activeSlot.key === 'identity_address'"
                        class="mt-5 space-y-4"
                        @submit.prevent="submitIdentityAddress"
                    >
                        <div class="rounded-xl border border-primary-100 bg-primary-50/60 px-4 py-3 text-sm font-semibold leading-relaxed text-primary-950">
                            <p class="font-black">Photo ID (choose one)</p>
                            <p class="mt-1 text-xs text-primary-900">
                                International passport, National ID card, Voter&apos;s registration card, or National driver&apos;s licence — number and upload. Only one ID is required; names must match your account.
                            </p>
                            <ul v-if="activeSlot.identity_requirements?.length" class="mt-2 list-disc space-y-0.5 pl-5 text-xs">
                                <li v-for="(line, i) in activeSlot.identity_requirements" :key="i">{{ line }}</li>
                            </ul>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel for="id_type" value="Photo ID type" />
                                <UiSelect
                                    id="id_type"
                                    v-model="identityForm.id_type"
                                    class="mt-2"
                                    :options="activeSlot.id_type_options"
                                    placeholder="Select ID"
                                />
                            </div>
                            <div>
                                <InputLabel for="identifier_number" :value="idNumberLabel" />
                                <TextInput
                                    id="identifier_number"
                                    v-model="identityForm.identifier_number"
                                    type="text"
                                    class="mt-2"
                                    :placeholder="idNumberLabel"
                                />
                            </div>
                        </div>
                        <div>
                            <InputLabel value="Upload selected ID (required)" />
                            <input
                                type="file"
                                accept="image/jpeg,image/png,image/webp,application/pdf"
                                class="mt-2 block w-full text-sm font-semibold file:mr-3 file:rounded-xl file:border-0 file:bg-primary-600 file:px-4 file:py-2 file:text-sm file:font-bold file:text-white"
                                @change="(e) => (identityForm.id_document = e.target.files?.[0] || null)"
                            />
                        </div>
                        <div>
                            <InputLabel for="confirmed_address" value="Address on your account" />
                            <UiTextarea
                                id="confirmed_address"
                                v-model="identityForm.confirmed_address"
                                class="mt-2"
                                rows="3"
                                placeholder="Street, city, state"
                            />
                            <InputError class="mt-2" :message="errors.identity_address" />
                        </div>
                        <div class="space-y-2">
                            <div class="flex items-center justify-between gap-2">
                                <InputLabel value="Proof of address uploads (required)" />
                                <button
                                    type="button"
                                    class="rounded-full border border-slate-200 px-3 py-1 text-xs font-bold text-slate-800"
                                    @click="addAddressDoc"
                                >
                                    + Add more
                                </button>
                            </div>
                            <div
                                v-for="(row, idx) in identityForm.address_rows"
                                :key="idx"
                                class="flex flex-col gap-2 rounded-xl border border-slate-100 bg-slate-50/80 p-3 sm:flex-row sm:items-center"
                            >
                                <input
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp,application/pdf"
                                    class="block w-full text-xs font-semibold file:rounded-lg file:border-0 file:bg-primary-600 file:px-3 file:py-2 file:text-xs file:font-bold file:text-white"
                                    @change="(e) => (row.file = e.target.files?.[0] || null)"
                                />
                                <button
                                    v-if="identityForm.address_rows.length > 1"
                                    type="button"
                                    class="text-xs font-bold text-rose-600"
                                    @click="removeAddressDoc(idx)"
                                >
                                    Remove
                                </button>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <div class="flex items-center justify-between gap-2">
                                <InputLabel value="Additional ID pages (optional)" />
                                <button type="button" class="rounded-full border border-slate-200 px-3 py-1 text-xs font-bold" @click="addExtraId">
                                    + Add more
                                </button>
                            </div>
                            <div
                                v-for="(row, idx) in identityForm.extra_id_rows"
                                :key="`extra-${idx}`"
                                class="flex flex-col gap-2 rounded-xl border border-slate-100 p-3 sm:flex-row sm:items-end"
                            >
                                <TextInput v-model="row.label" type="text" class="flex-1" placeholder="Label e.g. ID back" />
                                <input
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp,application/pdf"
                                    class="block w-full text-xs font-semibold file:rounded-lg file:border-0 file:bg-primary-600 file:px-3 file:py-2 file:text-xs file:font-bold file:text-white"
                                    @change="(e) => (row.file = e.target.files?.[0] || null)"
                                />
                            </div>
                        </div>
                        <PrimaryButton class="w-full justify-center rounded-2xl py-3" :disabled="submitting === 'identity_address'">
                            Submit for review
                        </PrimaryButton>
                    </form>

                    <!-- CAC / TIN -->
                    <form
                        v-if="activeSlot.status === 'available' && activeSlot.key === 'cac_tin'"
                        class="mt-5 space-y-4"
                        @submit.prevent="submitCacTin"
                    >
                        <div>
                            <InputLabel for="registration_kind" value="Submit as" />
                            <UiSelect
                                id="registration_kind"
                                v-model="cacForm.registration_kind"
                                class="mt-2"
                                :options="[
                                    { value: 'cac', label: 'CAC (RC number)' },
                                    { value: 'tin', label: 'TIN' },
                                ]"
                            />
                        </div>
                        <div>
                            <InputLabel for="cac_identifier" :value="cacForm.registration_kind === 'cac' ? 'RC number' : 'TIN number'" />
                            <TextInput id="cac_identifier" v-model="cacForm.identifier_number" type="text" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel for="registered_business_name" value="Registered business name (optional)" />
                            <TextInput id="registered_business_name" v-model="cacForm.registered_business_name" type="text" class="mt-2" />
                        </div>
                        <div class="space-y-2">
                            <InputLabel value="Certificate upload (optional)" />
                            <input
                                type="file"
                                accept="image/jpeg,image/png,image/webp,application/pdf"
                                class="mt-2 block w-full text-sm font-semibold file:rounded-xl file:border-0 file:bg-primary-600 file:px-4 file:py-2 file:text-sm file:font-bold file:text-white"
                                @change="(e) => (cacForm.file = e.target.files?.[0] || null)"
                            />
                        </div>
                        <PrimaryButton class="w-full justify-center rounded-2xl py-3" :disabled="submitting === 'cac_tin'">
                            Submit for review
                        </PrimaryButton>
                    </form>

                    <!-- Professional -->
                    <form
                        v-if="activeSlot.status === 'available' && activeSlot.key === 'professional_certificate'"
                        class="mt-5 space-y-4"
                        @submit.prevent="submitProfessional"
                    >
                        <div
                            v-for="(entry, idx) in professionalForm.entries"
                            :key="idx"
                            class="space-y-3 rounded-xl border border-slate-100 bg-slate-50/60 p-4"
                        >
                            <p class="text-xs font-black uppercase tracking-wide text-slate-500">
                                Credential {{ idx + 1 }}
                            </p>
                            <div>
                                <InputLabel :value="'What are you submitting?'" />
                                <TextInput v-model="entry.what_submitting" type="text" class="mt-2" />
                            </div>
                            <div>
                                <InputLabel value="Credential identification (optional)" />
                                <TextInput v-model="entry.credential_identification" type="text" class="mt-2" />
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <InputLabel value="Awarding body" />
                                    <TextInput v-model="entry.awarding_body" type="text" class="mt-2" />
                                </div>
                                <div>
                                    <InputLabel value="Year" />
                                    <TextInput v-model="entry.year" type="text" maxlength="4" class="mt-2" inputmode="numeric" />
                                </div>
                            </div>
                            <div>
                                <InputLabel value="File (optional)" />
                                <input
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp,application/pdf"
                                    class="mt-2 block w-full text-xs font-semibold file:rounded-lg file:border-0 file:bg-primary-600 file:px-3 file:py-2 file:text-xs file:font-bold file:text-white"
                                    @change="(e) => (entry.file = e.target.files?.[0] || null)"
                                />
                            </div>
                        </div>
                        <button type="button" class="text-sm font-bold text-primary-700" @click="addProfessionalEntry">
                            + Add more
                        </button>
                        <PrimaryButton class="w-full justify-center rounded-2xl py-3" :disabled="submitting === 'professional_certificate'">
                            Submit for review
                        </PrimaryButton>
                    </form>

                    <!-- Selfie + ID -->
                    <form
                        v-if="activeSlot.status === 'available' && activeSlot.key === 'live_presence'"
                        class="mt-5 space-y-4"
                        @submit.prevent="submitLivePresence"
                    >
                        <input
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="block w-full text-sm font-semibold file:rounded-xl file:border-0 file:bg-primary-600 file:px-4 file:py-2 file:text-sm file:font-bold file:text-white"
                            @change="(e) => (livePhoto = e.target.files?.[0] || null)"
                        />
                        <InputError class="mt-2" :message="errors.live_photo" />
                        <PrimaryButton class="w-full justify-center rounded-2xl py-3" :disabled="submitting === 'live_presence'">
                            Submit for review
                        </PrimaryButton>
                    </form>
                    </template>
                </div>
            </section>

            <div v-if="submissions.length" class="mt-10">
                <h2 class="font-display text-lg font-bold text-slate-900">
                    Submission history
                </h2>
                <ul class="mt-4 space-y-2">
                    <li
                        v-for="item in submissions"
                        :key="item.id"
                        class="rounded-2xl border border-slate-100 bg-white px-4 py-4 shadow-sm ring-1 ring-slate-50"
                    >
                        <p class="text-sm font-bold uppercase tracking-wide text-primary-700">
                            {{ item.category_label }}
                        </p>
                        <p class="mt-1 text-base font-bold text-slate-900">
                            {{ item.status_label || item.status }}
                        </p>
                        <p class="mt-2 text-sm font-medium text-slate-500">
                            {{ formatWhen(item.submitted_at) }}
                        </p>
                        <p v-if="item.rejection_reason" class="mt-2 text-sm font-semibold text-rose-700">
                            {{ item.rejection_reason }}
                        </p>
                    </li>
                </ul>
            </div>
        </div>
    </AppShell>
</template>

<script setup>
import UiSelect from '@/Components/Ui/UiSelect.vue';
import UiTextarea from '@/Components/Ui/UiTextarea.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AppShell from '@/Layouts/AppShell.vue';
import { formatFormalDateTime } from '@/utils/formatFormalDateTime';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    is_freelancer: { type: Boolean, default: false },
    role_notice: { type: String, default: '' },
    trust: { type: Object, default: () => ({}) },
    next_step: { type: Object, default: null },
    prefilled_address: { type: Object, default: () => ({ formatted: '' }) },
    slots: { type: Array, default: () => [] },
    submissions: { type: Array, default: () => [] },
    feedback: { type: Object, default: null },
});

const resendForm = useForm({});

const activeSlot = computed(() => props.next_step?.slot ?? null);

const stepCallout = computed(() => {
    const step = props.next_step;
    if (!step || step.type === 'complete') {
        return '';
    }

    return Boolean(step.info_bar || step.progress_notice);
});

const feedbackBannerClass = computed(() => {
    if (!props.feedback) {
        return '';
    }
    if (props.feedback.action === 'approve') {
        return 'border-emerald-200 bg-emerald-50 ring-1 ring-emerald-100';
    }
    if (props.feedback.action === 'request_corrections') {
        return 'border-amber-200 bg-amber-50 ring-1 ring-amber-100';
    }

    return 'border-rose-200 bg-rose-50 ring-1 ring-rose-100';
});

const submitting = ref('');
const errors = ref({});
const livePhoto = ref(null);

const numberForms = reactive({
    nin: '',
    bvn: '',
});

const identityForm = reactive({
    id_type: 'passport',
    identifier_number: '',
    confirmed_address: props.prefilled_address?.formatted || '',
    id_document: null,
    address_rows: [{ file: null }],
    extra_id_rows: [],
});

const cacForm = reactive({
    registration_kind: 'cac',
    identifier_number: '',
    registered_business_name: '',
    file: null,
});

const professionalForm = reactive({
    entries: [
        {
            what_submitting: '',
            credential_identification: '',
            awarding_body: '',
            year: '',
            file: null,
        },
    ],
});

const idNumberLabel = computed(() => {
    if (identityForm.id_type === 'drivers_licence') {
        return "Driver's licence number";
    }
    if (identityForm.id_type === 'voters_card') {
        return "Voter's registration number";
    }
    if (identityForm.id_type === 'national_id') {
        return 'National ID card number';
    }

    return 'Passport number';
});

function statusLabel(status) {
    const map = {
        available: 'Ready to submit',
        pending: 'Under review',
        locked: 'Locked',
        approved: 'Approved',
    };

    return map[status] || status;
}

function statusPillClass(status) {
    if (status === 'pending') {
        return 'bg-amber-100 text-amber-900';
    }
    if (status === 'locked') {
        return 'bg-slate-100 text-slate-600';
    }
    if (status === 'available') {
        return 'bg-primary-100 text-primary-900';
    }

    return 'bg-emerald-100 text-emerald-800';
}

function addAddressDoc() {
    identityForm.address_rows.push({ file: null });
}

function removeAddressDoc(idx) {
    identityForm.address_rows.splice(idx, 1);
}

function addExtraId() {
    identityForm.extra_id_rows.push({ label: '', file: null });
}

function addProfessionalEntry() {
    professionalForm.entries.push({
        what_submitting: '',
        credential_identification: '',
        awarding_body: '',
        year: '',
        file: null,
    });
}

const visitOpts = { forceFormData: true, preserveScroll: true, timeout: 180000 };

function postForm(data, key) {
    submitting.value = key;
    errors.value = {};
    const fd = new FormData();
    Object.entries(data).forEach(([k, v]) => {
        if (v === null || v === undefined) {
            return;
        }
        if (v instanceof File) {
            fd.append(k, v);
            return;
        }
        if (Array.isArray(v)) {
            v.forEach((item, i) => {
                if (item instanceof File) {
                    fd.append(`${k}[${i}]`, item);
                } else if (typeof item === 'object' && item !== null) {
                    Object.entries(item).forEach(([ik, iv]) => {
                        if (iv instanceof File) {
                            fd.append(`${k}[${i}][${ik}]`, iv);
                        } else if (iv !== null && iv !== undefined && iv !== '') {
                            fd.append(`${k}[${i}][${ik}]`, iv);
                        }
                    });
                } else if (item !== null && item !== '') {
                    fd.append(`${k}[${i}]`, item);
                }
            });
            return;
        }
        fd.append(k, v);
    });

    router.post(route('verifications.store'), fd, {
        ...visitOpts,
        onError: (e) => {
            errors.value = e;
        },
        onFinish: () => {
            submitting.value = '';
        },
    });
}

function submitNumber(key) {
    const num = String(numberForms[key] || '').replace(/\D/g, '');
    if (num.length !== 11) {
        errors.value = { [`${key}.identifier_number`]: 'Enter exactly 11 digits.' };
        return;
    }
    postForm({ category: key, identifier_number: num }, key);
}

function submitIdentityAddress() {
    const addressFiles = identityForm.address_rows.map((r) => r.file).filter(Boolean);
    if (!identityForm.id_document) {
        errors.value = { identity_address: 'Upload your government ID.' };
        return;
    }
    if (!addressFiles.length) {
        errors.value = { identity_address: 'Add at least one proof-of-address document.' };
        return;
    }
    if (!identityForm.confirmed_address.trim()) {
        errors.value = { identity_address: 'Confirm your address.' };
        return;
    }

    const fd = new FormData();
    fd.append('category', 'identity_address');
    fd.append('id_type', identityForm.id_type);
    fd.append('identifier_number', identityForm.identifier_number);
    fd.append('confirmed_address', identityForm.confirmed_address);
    fd.append('id_document', identityForm.id_document);
    addressFiles.forEach((f, i) => fd.append(`address_documents[${i}]`, f));
    identityForm.extra_id_rows.forEach((row, i) => {
        if (row.file) {
            fd.append(`additional_id_documents[${i}]`, row.file);
            fd.append(`additional_id_labels[${i}]`, row.label || `Additional ID ${i + 1}`);
        }
    });

    submitting.value = 'identity_address';
    errors.value = {};
    router.post(route('verifications.store'), fd, {
        ...visitOpts,
        onError: (e) => {
            errors.value = e;
        },
        onFinish: () => {
            submitting.value = '';
        },
    });
}

function submitCacTin() {
    const data = {
        category: cacForm.registration_kind,
        identifier_number: cacForm.identifier_number,
        registered_business_name: cacForm.registered_business_name || '',
    };
    if (cacForm.file) {
        data.document_files = [cacForm.file];
        data.document_labels = [cacForm.registration_kind === 'cac' ? 'CAC certificate' : 'TIN document'];
    }
    postForm(data, 'cac_tin');
}

function submitProfessional() {
    const entries = professionalForm.entries.filter((e) => e.what_submitting.trim() && e.awarding_body.trim() && e.year);
    if (!entries.length) {
        errors.value = { professional: 'Complete at least one credential.' };
        return;
    }

    const fd = new FormData();
    fd.append('category', 'professional_certificate');
    entries.forEach((entry, i) => {
        fd.append(`professional_entries[${i}][what_submitting]`, entry.what_submitting);
        if (entry.credential_identification) {
            fd.append(`professional_entries[${i}][credential_identification]`, entry.credential_identification);
        }
        fd.append(`professional_entries[${i}][awarding_body]`, entry.awarding_body);
        fd.append(`professional_entries[${i}][year]`, entry.year);
        if (entry.file) {
            fd.append(`professional_entries[${i}][file]`, entry.file);
        }
    });

    submitting.value = 'professional_certificate';
    router.post(route('verifications.store'), fd, {
        ...visitOpts,
        onError: (e) => {
            errors.value = e;
        },
        onFinish: () => {
            submitting.value = '';
        },
    });
}

function submitLivePresence() {
    if (!livePhoto.value) {
        errors.value = { live_photo: 'Choose a photo before submitting.' };
        return;
    }
    postForm({ category: 'live_presence', live_photo: livePhoto.value }, 'live_presence');
}

function formatWhen(iso) {
    return formatFormalDateTime(iso);
}

function formatLimit(minor) {
    if (minor == null || minor <= 0) {
        return '—';
    }

    return new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
        maximumFractionDigits: 0,
    }).format(Number(minor) / 100);
}

function resendVerificationEmail() {
    resendForm.post(route('verification.send'), { preserveScroll: true });
}
</script>
