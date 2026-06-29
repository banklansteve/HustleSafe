<template>
    <AppShell>
        <Head title="Open dispute · HustleSafe" />

        <div class="mx-auto max-w-3xl space-y-6 pb-10">
            <BackChevronLink :href="route('quests.show', quest.route_key)" aria-label="Back to quest" />

            <header class="space-y-2">
                <p class="text-xs font-black uppercase tracking-[0.2em] text-slate-500">
                    {{ party === 'client' ? 'Client' : 'Freelancer' }} dispute intake
                </p>
                <h1 class="font-display text-2xl font-black tracking-tight text-slate-900">
                    {{ quest.title }}
                </h1>
                <p class="text-sm font-semibold text-slate-600">
                    Minimum value ₦{{ (policy.minimum_disputed_amount_minor / 100).toLocaleString() }} · Self-resolution
                    {{ policy.self_resolution_hours }}h · Formal window {{ policy.formal_ruling_hours }}h.
                    <a :href="workflow_doc_url" class="ml-1 font-black text-primary-800 underline underline-offset-2" target="_blank" rel="noopener noreferrer">Workflow</a>
                </p>
            </header>

            <section class="rounded-2xl border border-primary-100 bg-primary-50/50 p-5 ring-1 ring-primary-100 sm:p-6">
                <h2 class="font-display text-sm font-black uppercase tracking-wide text-primary-900">How dispute resolution works</h2>
                <ol class="mt-3 space-y-2 text-sm font-medium text-slate-700">
                    <li><span class="font-black text-slate-900">1. Self-resolution</span> — both parties share evidence and may propose a settlement ({{ policy.self_resolution_hours }}h per response turn).</li>
                    <li><span class="font-black text-slate-900">2. Staff investigation</span> — if timers expire or the case escalates, a staff investigator reviews everything and may request more evidence.</li>
                    <li><span class="font-black text-slate-900">3. Super Admin decision</span> — a binding ruling is issued and escrow is updated. No resolution fee is charged.</li>
                    <li><span class="font-black text-slate-900">4. Closed</span> — outcome applied; limited appeal window may apply.</li>
                </ol>
                <p class="mt-3 text-xs font-semibold text-amber-900">
                    Accounts involved in more than {{ policy.review_after_dispute_count }} disputes may be flagged for a trust review. Be factual and evidence-based.
                </p>
            </section>

            <form class="space-y-6" @submit.prevent="submit">
                <!-- 1. Reason -->
                <section class="space-y-4 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6">
                    <h2 class="font-display text-sm font-black uppercase tracking-wide text-slate-700">1. Reason</h2>
                    <p class="text-sm font-medium leading-relaxed text-slate-600">
                        Choose the category and specific reason that best matches your situation. This helps staff route your case and apply the right review checklist.
                    </p>
                    <div class="space-y-4">
                        <div>
                            <InputLabel value="Category" />
                            <UiSelect
                                v-model="selectedReasonCategory"
                                class="mt-2"
                                :options="reasonCategoryOptions"
                                placeholder="Select category"
                            />
                        </div>
                        <div>
                            <InputLabel value="Dispute reason" />
                            <UiSelect
                                v-model="form.reason"
                                class="mt-2"
                                :options="reasonOptionsForCategory"
                                placeholder="Select reason"
                                :invalid="!!form.errors.reason"
                            />
                            <p v-if="selectedCategoryLabel" class="mt-1 text-xs font-semibold text-primary-800">
                                Category: {{ selectedCategoryLabel }}
                            </p>
                            <InputError class="mt-1" :message="form.errors.reason" />
                        </div>
                    </div>

                    <div v-if="requiresSilenceDays">
                        <InputLabel value="Days without meaningful reply" />
                        <TextInput
                            v-model.number="form.structured_intake.silence_days_observed"
                            type="number"
                            :min="intake.limits.silence_comms_min_days"
                            class="mt-2 w-full max-w-xs rounded-xl border-slate-200 text-sm font-semibold shadow-sm"
                        />
                        <p class="mt-1 text-xs font-semibold text-slate-500">
                            Minimum {{ intake.limits.silence_comms_min_days }} days without a meaningful reply from the other party on this quest.
                        </p>
                        <InputError class="mt-1" :message="form.errors['structured_intake.silence_days_observed']" />
                    </div>
                </section>

                <!-- 2. Description -->
                <section class="space-y-3 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6">
                    <h2 class="font-display text-sm font-black uppercase tracking-wide text-slate-700">2. Description</h2>
                    <p class="text-sm font-medium leading-relaxed text-slate-600">
                        Tell your side in your own words — what was agreed, what went wrong, and what you need next. Be factual and chronological; staff and the other party will both read this.
                    </p>
                    <UiTextarea
                        v-model="form.opening_summary"
                        label="What happened?"
                        hint="Include key dates, deliverables discussed, messages sent, and the outcome you are seeking."
                        placeholder="Chronological narrative with dates, agreements, and what you need next."
                        :min-rows="3"
                        :max-rows="14"
                        required
                        :error="form.errors.opening_summary"
                    />
                    <p class="text-xs font-semibold" :class="wordCountOk ? 'text-emerald-700' : 'text-amber-800'">
                        {{ descriptionWordCount }} / {{ intake.limits.description_min_words }}–{{ intake.limits.description_max_words }} words
                        <span v-if="!wordCountOk && descriptionWordCount > 0" class="block text-rose-700">
                            {{ wordCountHint }}
                        </span>
                    </p>
                </section>

                <!-- 3. Evidence -->
                <section class="space-y-4 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6">
                    <h2 class="font-display text-sm font-black uppercase tracking-wide text-slate-700">3. Evidence</h2>
                    <p class="text-sm font-medium leading-relaxed text-slate-600">
                        Strong disputes are evidence-first. Upload files, highlight relevant chat dates, or add external links (Loom, Drive, mockups) that support your description. Platform message history is attached automatically.
                    </p>

                    <div>
                        <InputLabel value="Upload files" />
                        <p class="mt-0.5 text-xs font-semibold text-slate-500">
                            Contracts, screenshots, receipts · max {{ intake.limits.evidence_max_files }} files · {{ Math.round(intake.limits.evidence_max_file_kb / 1024) }}MB each
                        </p>
                        <div
                            class="mt-2 flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed px-4 py-7 text-center transition active:scale-[0.99] sm:py-8"
                            :class="dragOver ? 'border-primary-400 bg-primary-50/80' : 'border-slate-200 bg-slate-50/80 hover:border-primary-300'"
                            role="button"
                            tabindex="0"
                            @click="fileInput?.click()"
                            @keydown.enter.prevent="fileInput?.click()"
                            @dragover.prevent="dragOver = true"
                            @dragleave.prevent="dragOver = false"
                            @drop.prevent="onEvidenceDrop"
                        >
                            <p class="text-sm font-bold text-slate-800">Drop files or tap to browse</p>
                            <p class="mt-1 text-xs font-semibold text-slate-500">Images, PDF, or documents</p>
                            <input ref="fileInput" type="file" class="hidden" multiple @change="onEvidencePick" />
                        </div>
                        <ul v-if="evidenceFileRows.length" class="mt-3 space-y-2">
                            <li
                                v-for="(row, idx) in evidenceFileRows"
                                :key="row.key"
                                class="flex items-center justify-between gap-3 rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-2 text-sm font-semibold text-slate-800"
                            >
                                <span class="min-w-0 truncate">{{ row.name }}</span>
                                <span class="shrink-0 text-xs text-slate-500">{{ formatFileSize(row.size) }}</span>
                                <button
                                    type="button"
                                    class="shrink-0 text-[10px] font-black uppercase tracking-wide text-rose-600 hover:text-rose-800"
                                    @click="removeEvidenceFile(idx)"
                                >
                                    Remove
                                </button>
                            </li>
                        </ul>
                        <InputError class="mt-1" :message="form.errors.evidence_files" />
                    </div>

                    <div v-if="conversationDateGroups.length">
                        <InputLabel value="Platform messages" />
                        <p class="mt-0.5 text-xs font-semibold text-slate-500">
                            Select conversation dates to highlight (full history is auto-attached).
                        </p>
                        <div class="mt-2 max-h-64 space-y-3 overflow-y-auto rounded-2xl border border-slate-100 bg-slate-50/80 p-3">
                            <div
                                v-for="group in conversationDateGroups"
                                :key="group.date"
                                class="rounded-xl border border-slate-100 bg-white p-3"
                            >
                                <label class="flex cursor-pointer items-start gap-3">
                                    <input
                                        type="checkbox"
                                        class="mt-1 rounded border-slate-300 text-primary-600 focus:ring-primary-500"
                                        :checked="isDateFullySelected(group)"
                                        @change="toggleDateGroup(group, $event.target.checked)"
                                    />
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-bold text-slate-900">{{ group.date_label }}</span>
                                        <span class="text-xs font-semibold text-slate-500">
                                            {{ group.messages.length }} {{ group.messages.length === 1 ? 'message' : 'messages' }}
                                        </span>
                                    </span>
                                </label>
                                <ul class="mt-2 space-y-1 border-l-2 border-slate-100 pl-4">
                                    <li
                                        v-for="msg in group.messages"
                                        :key="msg.id"
                                        class="text-xs font-medium leading-relaxed text-slate-600"
                                    >
                                        {{ msg.label }}
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <InputLabel value="External links" />
                                <p class="mt-0.5 text-xs font-semibold text-slate-500">Loom videos, Google Drive folders, Figma files, or other proof hosted online.</p>
                            </div>
                            <button
                                type="button"
                                class="text-xs font-black uppercase tracking-wide text-primary-800"
                                @click="addExternalLink"
                            >
                                + Add link
                            </button>
                        </div>
                        <div v-for="(link, idx) in form.structured_intake.external_links" :key="idx" class="mt-2 grid gap-2 sm:grid-cols-2">
                            <TextInput
                                v-model="link.url"
                                type="url"
                                placeholder="https://"
                                class="w-full rounded-xl border-slate-200 text-sm shadow-sm"
                            />
                            <TextInput
                                v-model="link.description"
                                type="text"
                                placeholder="Description"
                                class="w-full rounded-xl border-slate-200 text-sm shadow-sm"
                            />
                        </div>
                    </div>
                </section>

                <!-- 4. Impact -->
                <section class="space-y-4 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6">
                    <h2 class="font-display text-sm font-black uppercase tracking-wide text-slate-700">4. Impact assessment</h2>
                    <p class="text-sm font-medium leading-relaxed text-slate-600">
                        Help us understand how this dispute affects you financially, on the project timeline, and on trust. Estimates are fine — use your best honest figures.
                    </p>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel value="Financial impact (₦)" />
                            <p class="mt-0.5 text-xs font-semibold text-slate-500">Money lost or unusable because of this issue (e.g. wasted spend, rework cost).</p>
                            <TextInput
                                v-model.number="financialImpactNgn"
                                type="number"
                                min="0"
                                class="mt-2 w-full rounded-xl border-slate-200 text-sm font-semibold shadow-sm"
                            />
                        </div>
                        <div>
                            <InputLabel value="Timeline impact (days delayed)" />
                            <p class="mt-0.5 text-xs font-semibold text-slate-500">Days past the agreed delivery date or when work should have been completed.</p>
                            <TextInput
                                v-model.number="form.structured_intake.impact.timeline_delay_days"
                                type="number"
                                min="0"
                                class="mt-2 w-full rounded-xl border-slate-200 text-sm font-semibold shadow-sm"
                            />
                        </div>
                        <div>
                            <InputLabel value="Contract value at risk (₦)" />
                            <p class="mt-0.5 text-xs font-semibold text-slate-500">Total escrow or contract amount you believe is in dispute.</p>
                            <TextInput
                                v-model.number="contractValueNgn"
                                type="number"
                                min="0"
                                class="mt-2 w-full rounded-xl border-slate-200 text-sm font-semibold shadow-sm"
                            />
                        </div>
                        <label class="flex cursor-pointer items-start gap-2 self-end text-sm font-semibold text-slate-800">
                            <input
                                v-model="form.structured_intake.impact.reputation_impact"
                                type="checkbox"
                                class="mt-1 rounded border-slate-300 text-primary-600 focus:ring-primary-500"
                            />
                            <span>
                                Reputation impact
                                <span class="mt-0.5 block text-xs font-semibold text-slate-500">Check if this harmed your professional standing, reviews, or client relationships.</span>
                            </span>
                        </label>
                    </div>
                </section>

                <!-- 5. Resolution requested -->
                <section class="space-y-3 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6">
                    <h2 class="font-display text-sm font-black uppercase tracking-wide text-slate-700">5. Resolution requested</h2>
                    <p class="text-sm font-medium leading-relaxed text-slate-600">
                        What outcome do you want if the platform rules in your favour? This is your opening position — both parties may still negotiate during self-resolution before staff escalates the case.
                    </p>
                    <div class="space-y-2">
                        <label
                            v-for="opt in intake.resolution_options"
                            :key="opt.value"
                            class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-100 px-3 py-2 text-sm font-semibold text-slate-800"
                        >
                            <input
                                v-model="form.structured_intake.resolution_requested"
                                type="radio"
                                :value="opt.value"
                                class="mt-1 border-slate-300 text-primary-600 focus:ring-primary-500"
                            />
                            <span>{{ opt.label }}</span>
                        </label>
                    </div>
                    <div v-if="needsResolutionAmount" class="max-w-xs">
                        <InputLabel value="Amount (₦)" />
                        <p class="mt-0.5 text-xs font-semibold text-amber-800">Required for partial refund or partial payment.</p>
                        <TextInput
                            v-model.number="resolutionAmountNgn"
                            type="number"
                            min="1"
                            required
                            class="mt-2 w-full rounded-xl border-slate-200 text-sm font-semibold shadow-sm"
                        />
                        <InputError :message="form.errors['structured_intake.resolution_amount_minor']" />
                    </div>
                </section>

                <!-- 6. Timeline -->
                <section class="space-y-4 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6">
                    <h2 class="font-display text-sm font-black uppercase tracking-wide text-slate-700">6. Timeline of events</h2>
                    <p class="text-sm font-medium leading-relaxed text-slate-600">
                        Contract milestones are prefilled from your quest record where available. Confirm or adjust them, then add when you first noticed the problem and any informal resolution attempts.
                    </p>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div v-for="field in timelineDateFields" :key="field.key">
                            <InputLabel :value="field.label" />
                            <p v-if="field.hint" class="mt-0.5 text-xs font-semibold text-slate-500">{{ field.hint }}</p>
                            <PremiumDatePicker
                                v-model="form.structured_intake.timeline[field.key]"
                                class="mt-2"
                                placeholder="dd/mm/yyyy"
                                :disabled="field.readonly"
                            />
                        </div>
                        <div>
                            <InputLabel value="Informal resolution attempted" />
                            <p class="mt-0.5 text-xs font-semibold text-slate-500">Did you try to fix this directly with the other party before opening a formal dispute?</p>
                            <label class="mt-2 flex cursor-pointer items-center gap-2 text-sm font-semibold text-slate-800">
                                <input
                                    v-model="form.structured_intake.timeline.informal_resolution_attempted"
                                    type="checkbox"
                                    class="rounded border-slate-300 text-primary-600"
                                />
                                Yes
                            </label>
                        </div>
                    </div>
                </section>

                <!-- 7. Affected areas -->
                <section class="space-y-3 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6">
                    <h2 class="font-display text-sm font-black uppercase tracking-wide text-slate-700">
                        7. Affected area <span class="text-rose-600">*</span>
                    </h2>
                    <p class="text-sm font-medium leading-relaxed text-slate-600">
                        Select every part of the engagement this dispute touches. Choose at least one — this helps staff focus their review.
                    </p>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label
                            v-for="area in intake.affected_areas"
                            :key="area.value"
                            class="flex cursor-pointer items-center gap-2 text-sm font-semibold text-slate-800"
                        >
                            <input
                                v-model="form.structured_intake.affected_areas"
                                type="checkbox"
                                :value="area.value"
                                class="rounded border-slate-300 text-primary-600 focus:ring-primary-500"
                            />
                            {{ area.label }}
                        </label>
                    </div>
                    <InputError :message="form.errors['structured_intake.affected_areas']" />
                </section>

                <!-- 8. Prior attempts -->
                <section class="space-y-3 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6">
                    <h2 class="font-display text-sm font-black uppercase tracking-wide text-slate-700">
                        8. Prior resolution attempts <span class="text-rose-600">*</span>
                    </h2>
                    <p class="text-sm font-medium leading-relaxed text-slate-600">
                        Tell us what you already tried to resolve this. Good-faith effort before escalation strengthens your case and speeds review.
                    </p>
                    <div class="space-y-2">
                        <label
                            v-for="opt in intake.prior_attempt_options"
                            :key="opt.value"
                            class="flex cursor-pointer items-center gap-2 text-sm font-semibold text-slate-800"
                        >
                            <input
                                v-model="form.structured_intake.prior_attempts"
                                type="checkbox"
                                :value="opt.value"
                                class="rounded border-slate-300 text-primary-600 focus:ring-primary-500"
                            />
                            {{ opt.label }}
                        </label>
                    </div>
                    <div v-if="hasPriorMessageAttempt" class="max-w-xs">
                        <InputLabel value="Last message attempt" />
                        <PremiumDatePicker
                            v-model="form.structured_intake.prior_attempt_details.last_message_attempt_date"
                            class="mt-2"
                            placeholder="dd/mm/yyyy"
                        />
                    </div>
                    <div v-if="hasPriorRevisionAttempt" class="max-w-xs">
                        <InputLabel value="Revision requests (count)" />
                        <TextInput
                            v-model.number="form.structured_intake.prior_attempt_details.revision_request_count"
                            type="number"
                            min="0"
                            class="mt-2 w-full rounded-xl border-slate-200 text-sm shadow-sm"
                        />
                    </div>
                    <div v-if="hasPriorOther">
                        <UiTextarea
                            v-model="form.structured_intake.prior_attempt_details.other_description"
                            label="Other — describe"
                            :min-rows="3"
                            :max-rows="8"
                        />
                    </div>
                    <InputError :message="form.errors['structured_intake.prior_attempts']" />
                </section>

                <!-- 9–10. Process & availability -->
                <section class="space-y-4 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6">
                    <h2 class="font-display text-sm font-black uppercase tracking-wide text-slate-700">9. Preferred resolution process</h2>
                    <p class="text-sm font-medium leading-relaxed text-slate-600">
                        How should HustleSafe handle this if the parties cannot agree? Mediation suggests a compromise; arbitration means staff decides; full investigation is for complex or high-value cases.
                    </p>
                    <div class="space-y-2">
                        <label
                            v-for="opt in intake.process_options"
                            :key="opt.value"
                            class="flex cursor-pointer items-center gap-3 text-sm font-semibold text-slate-800"
                        >
                            <input
                                v-model="form.structured_intake.preferred_process"
                                type="radio"
                                :value="opt.value"
                                class="border-slate-300 text-primary-600 focus:ring-primary-500"
                            />
                            {{ opt.label }}
                        </label>
                    </div>

                    <h3 class="pt-2 font-display text-sm font-black uppercase tracking-wide text-slate-700">10. Availability for discussion</h3>
                    <p class="text-sm font-medium leading-relaxed text-slate-600">
                        Optional — let staff and the other party know how flexible you are. This can shorten resolution time.
                    </p>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label
                            v-for="opt in intake.availability_options"
                            :key="opt.value"
                            class="flex cursor-pointer items-center gap-2 text-sm font-semibold text-slate-800"
                        >
                            <input
                                v-model="form.structured_intake.availability"
                                type="checkbox"
                                :value="opt.value"
                                class="rounded border-slate-300 text-primary-600 focus:ring-primary-500"
                            />
                            {{ opt.label }}
                        </label>
                    </div>
                    <div class="max-w-xs">
                        <InputLabel value="Best contact method" />
                        <p class="mt-0.5 text-xs font-semibold text-slate-500">How staff should reach you if they need clarification during review.</p>
                        <UiSelect
                            v-model="form.structured_intake.contact_method"
                            class="mt-2"
                            :options="contactMethodOptions"
                            placeholder="Select method"
                        />
                    </div>
                </section>

                <!-- 11. Acknowledgments -->
                <section class="space-y-3 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6">
                    <h2 class="font-display text-sm font-black uppercase tracking-wide text-slate-700">
                        11. Acknowledgments <span class="text-rose-600">*</span>
                    </h2>
                    <p class="text-sm font-medium leading-relaxed text-slate-600">
                        Please confirm you understand how disputes work on HustleSafe. All items are required before your file can be opened.
                    </p>
                    <p class="text-xs font-semibold" :class="allAcknowledgmentsChecked ? 'text-emerald-700' : 'text-amber-800'">
                        {{ acknowledgmentsCheckedCount }} / {{ intake.acknowledgments.length }} acknowledgments accepted
                    </p>
                    <label
                        v-for="ack in intake.acknowledgments"
                        :key="ack.key"
                        class="flex cursor-pointer items-start gap-3 text-sm font-semibold text-slate-800"
                    >
                        <input
                            :checked="Boolean(form.structured_intake.acknowledgments[ack.key])"
                            type="checkbox"
                            class="mt-1 rounded border-slate-300 text-primary-600 focus:ring-primary-500"
                            @change="setAcknowledgment(ack.key, $event.target.checked)"
                        />
                        <span>{{ ack.label }}</span>
                    </label>
                    <InputError :message="acknowledgmentError" />

                    <label class="flex cursor-pointer items-start gap-3 border-t border-slate-100 pt-3 text-sm font-semibold text-slate-800">
                        <input
                            :checked="form.confirm_philosophy"
                            type="checkbox"
                            class="mt-1 rounded border-slate-300 text-primary-600 focus:ring-primary-500"
                            @change="form.confirm_philosophy = $event.target.checked"
                        />
                        <span>I understand decisions lean on dated evidence, timers can escalate the file, and both sides see the same thread. <span class="text-rose-600">*</span></span>
                    </label>
                    <InputError :message="form.errors.confirm_philosophy" />
                </section>

                <div
                    v-if="submitBlockers.length"
                    class="rounded-2xl border border-amber-200 bg-amber-50/90 p-4 text-sm font-semibold text-amber-950"
                    role="status"
                    aria-live="polite"
                >
                    <p class="text-xs font-black uppercase tracking-wide text-amber-900">Complete these items to open your dispute file:</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        <li v-for="blocker in submitBlockers" :key="blocker.key">{{ blocker.message }}</li>
                    </ul>
                </div>

                <div class="flex flex-wrap justify-end gap-2">
                    <Link
                        :href="route('quests.show', quest.route_key)"
                        class="rounded-full border border-slate-200 px-4 py-2 text-xs font-black uppercase tracking-wide text-slate-700 hover:bg-slate-50"
                    >
                        Cancel
                    </Link>
                    <button
                        type="submit"
                        class="inline-flex items-center rounded-full bg-primary-700 px-5 py-2 text-xs font-black uppercase tracking-wide text-white shadow-sm hover:bg-primary-800 disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        <ReLoader4Line v-if="form.processing" class="mr-2 h-4 w-4 shrink-0 animate-spin" aria-hidden="true" />
                        Open dispute file
                    </button>
                </div>
            </form>
        </div>
    </AppShell>
</template>

<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import BackChevronLink from '@/Components/Ui/BackChevronLink.vue';
import PremiumDatePicker from '@/Components/Ui/PremiumDatePicker.vue';
import UiSelect from '@/Components/Ui/UiSelect.vue';
import UiTextarea from '@/Components/Ui/UiTextarea.vue';
import AppShell from '@/Layouts/AppShell.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ReLoader4Line } from '@kalimahapps/vue-icons/re';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    quest: { type: Object, required: true },
    offer_id: { type: Number, required: true },
    party: { type: String, required: true },
    intake: { type: Object, required: true },
    philosophy: { type: Object, default: () => ({}) },
    policy: { type: Object, required: true },
    store_url: { type: String, required: true },
    workflow_doc_url: { type: String, default: '/docs/dispute-workflow.md' },
});

const dragOver = ref(false);
const fileInput = ref(null);
const evidenceFileRows = ref([]);
const firstGroup = props.intake.reason_groups[0];
const selectedReasonCategory = ref(firstGroup?.category ?? '');

const form = useForm({
    reason: firstGroup?.reasons[0]?.value ?? '',
    opening_summary: '',
    structured_intake: {
        impact: {
            financial_impact_minor: null,
            timeline_delay_days: null,
            reputation_impact: false,
            contract_value_at_risk_minor: props.intake.prefill.contract_value_minor,
        },
        resolution_requested:
            props.intake.resolution_options.find((option) => option.value === 'mediation_investigation')?.value
            ?? props.intake.resolution_options.find((option) => option.value === 'full_refund')?.value
            ?? props.intake.resolution_options.find((option) => option.value === 'dispute_assessment')?.value
            ?? props.intake.resolution_options[0]?.value
            ?? '',
        resolution_amount_minor: null,
        timeline: { ...props.intake.prefill.timeline },
        affected_areas: [],
        prior_attempts: [],
        prior_attempt_details: {
            last_message_attempt_date: null,
            revision_request_count: null,
            other_description: '',
        },
        preferred_process: 'mediation',
        availability: [],
        contact_method: 'platform',
        conversation_links: [],
        external_links: [],
        silence_days_observed: 0,
        acknowledgments: {
            accurate_information: false,
            binding_resolution: false,
            accept_platform_decision: false,
            false_claims_consequence: false,
            evidence_attached: false,
        },
    },
    evidence_files: [],
    confirm_philosophy: false,
});

const timelineDateFields = [
    { key: 'contract_awarded', label: 'Contract awarded', readonly: true, hint: 'Prefilled from your contract record.' },
    { key: 'work_started', label: 'Work started', readonly: true, hint: 'When delivery work began.' },
    { key: 'expected_delivery', label: 'Expected delivery', readonly: true, hint: 'Agreed or estimated completion date.' },
    { key: 'deliverable_submitted', label: 'Deliverable submitted', readonly: true, hint: 'When work was marked as delivered.' },
    { key: 'issue_first_noticed', label: 'Issue first noticed', readonly: false, hint: 'When you first saw a problem with the work, payment, or communication.' },
    { key: 'informal_resolution_date', label: 'Informal resolution date', readonly: false, hint: 'When you last tried to resolve this directly with the other party.' },
    { key: 'escalation_date', label: 'Escalation date', readonly: true, hint: 'Today — when you are opening this dispute file.' },
];

const reasonCategoryOptions = computed(() =>
    props.intake.reason_groups.map((group) => ({
        value: group.category,
        label: group.category_label,
    })),
);

const reasonOptionsForCategory = computed(() => {
    const group = props.intake.reason_groups.find((g) => g.category === selectedReasonCategory.value);
    return (group?.reasons ?? []).map((reason) => ({
        value: reason.value,
        label: reason.label,
    }));
});

const contactMethodOptions = computed(() =>
    props.intake.contact_methods.map((method) => ({
        value: method.value,
        label: method.label,
    })),
);

const conversationDateGroups = computed(
    () => props.intake.prefill.conversation_messages_by_date ?? [],
);

const selectedCategoryLabel = computed(() => {
    const group = props.intake.reason_groups.find((g) => g.category === selectedReasonCategory.value);
    return group?.category_label ?? null;
});

const requiresSilenceDays = computed(() =>
    ['freelancer_unresponsive', 'client_not_responding', 'silence_comms'].includes(form.reason),
);

const needsResolutionAmount = computed(() =>
    ['partial_refund', 'partial_payment'].includes(form.structured_intake.resolution_requested),
);

const hasPriorMessageAttempt = computed(() => form.structured_intake.prior_attempts.includes('discussed_in_messages'));
const hasPriorRevisionAttempt = computed(() => form.structured_intake.prior_attempts.includes('requested_revisions'));
const hasPriorOther = computed(() => form.structured_intake.prior_attempts.includes('other'));

function wordCount(text) {
    const normalized = (text ?? '').trim().replace(/\s+/g, ' ');
    if (!normalized) return 0;
    return normalized.split(/\s+/).filter(Boolean).length;
}

const descriptionWordCount = computed(() => wordCount(form.opening_summary));
const wordCountOk = computed(
    () =>
        descriptionWordCount.value >= props.intake.limits.description_min_words
        && descriptionWordCount.value <= props.intake.limits.description_max_words,
);

const financialImpactNgn = computed({
    get: () => (form.structured_intake.impact.financial_impact_minor ?? 0) / 100,
    set: (v) => {
        form.structured_intake.impact.financial_impact_minor = Math.round((Number(v) || 0) * 100);
    },
});

const contractValueNgn = computed({
    get: () => (form.structured_intake.impact.contract_value_at_risk_minor ?? 0) / 100,
    set: (v) => {
        form.structured_intake.impact.contract_value_at_risk_minor = Math.round((Number(v) || 0) * 100);
    },
});

const resolutionAmountNgn = computed({
    get: () => (form.structured_intake.resolution_amount_minor ?? 0) / 100,
    set: (v) => {
        form.structured_intake.resolution_amount_minor = Math.round((Number(v) || 0) * 100);
    },
});

const allAcknowledgmentsChecked = computed(() =>
    props.intake.acknowledgments.every((ack) => Boolean(form.structured_intake.acknowledgments[ack.key])),
);

const acknowledgmentsCheckedCount = computed(() =>
    props.intake.acknowledgments.filter((ack) => Boolean(form.structured_intake.acknowledgments[ack.key])).length,
);

const wordCountHint = computed(() => {
    const min = props.intake.limits.description_min_words;
    const max = props.intake.limits.description_max_words;
    const count = descriptionWordCount.value;

    if (count < min) {
        return `Add ${min - count} more word${min - count === 1 ? '' : 's'} (minimum ${min}).`;
    }
    if (count > max) {
        return `Remove ${count - max} word${count - max === 1 ? '' : 's'} (maximum ${max}).`;
    }
    return '';
});

const submitBlockers = computed(() => {
    const blockers = [];

    if (!form.reason) {
        blockers.push({ key: 'reason', message: 'Select a dispute reason.' });
    }

    if (descriptionWordCount.value < props.intake.limits.description_min_words) {
        blockers.push({
            key: 'description_min',
            message: `Description must be at least ${props.intake.limits.description_min_words} words (currently ${descriptionWordCount.value}).`,
        });
    } else if (descriptionWordCount.value > props.intake.limits.description_max_words) {
        blockers.push({
            key: 'description_max',
            message: `Description must be at most ${props.intake.limits.description_max_words} words.`,
        });
    }

    if (!form.structured_intake.affected_areas.length) {
        blockers.push({ key: 'affected_areas', message: 'Select at least one affected area (section 7).' });
    }

    if (!form.structured_intake.prior_attempts.length) {
        blockers.push({ key: 'prior_attempts', message: 'Select at least one prior resolution attempt (section 8).' });
    }

    if (needsResolutionAmount.value) {
        const amount = Number(form.structured_intake.resolution_amount_minor || 0);
        if (amount <= 0) {
            blockers.push({ key: 'resolution_amount', message: 'Enter the requested partial amount (section 5).' });
        }
    }

    if (requiresSilenceDays.value) {
        const days = Number(form.structured_intake.silence_days_observed || 0);
        const minDays = props.intake.limits.silence_comms_min_days;
        if (days < minDays) {
            blockers.push({
                key: 'silence_days',
                message: `Document at least ${minDays} days without meaningful replies.`,
            });
        }
    }

    if (!allAcknowledgmentsChecked.value) {
        blockers.push({
            key: 'acknowledgments',
            message: `Accept all ${props.intake.acknowledgments.length} acknowledgments in section 11.`,
        });
    }

    if (!form.confirm_philosophy) {
        blockers.push({ key: 'philosophy', message: 'Confirm you understand the dispute process (section 11).' });
    }

    return blockers;
});

const canSubmit = computed(() => submitBlockers.value.length === 0);

const acknowledgmentError = computed(() => {
    const keys = [
        'structured_intake.acknowledgments.accurate_information',
        'structured_intake.acknowledgments.binding_resolution',
        'structured_intake.acknowledgments.accept_platform_decision',
        'structured_intake.acknowledgments.false_claims_consequence',
        'structured_intake.acknowledgments.evidence_attached',
    ];
    return keys.map((k) => form.errors[k]).find(Boolean) ?? null;
});

watch(
    () => form.reason,
    (value) => {
        for (const group of props.intake.reason_groups) {
            if (group.reasons.some((reason) => reason.value === value)) {
                selectedReasonCategory.value = group.category;
                break;
            }
        }
    },
    { immediate: true },
);

watch(selectedReasonCategory, (category) => {
    const group = props.intake.reason_groups.find((g) => g.category === category);
    if (!group) return;
    if (!group.reasons.some((reason) => reason.value === form.reason)) {
        form.reason = group.reasons[0]?.value ?? '';
    }
});

watch(
    () => props.intake.reason_groups,
    (groups) => {
        if (!groups.length) return;
        if (!groups.some((g) => g.category === selectedReasonCategory.value)) {
            selectedReasonCategory.value = groups[0].category;
        }
        const values = groups.flatMap((g) => g.reasons.map((r) => r.value));
        if (values.length && !values.includes(form.reason)) {
            form.reason = values[0];
        }
    },
    { immediate: true },
);

function setAcknowledgment(key, value) {
    form.structured_intake = {
        ...form.structured_intake,
        acknowledgments: {
            ...form.structured_intake.acknowledgments,
            [key]: value,
        },
    };
}

function addExternalLink() {
    if (form.structured_intake.external_links.length >= props.intake.limits.external_links_max) return;
    form.structured_intake.external_links.push({ url: '', description: '' });
}

function syncEvidenceFilesToForm() {
    form.evidence_files = evidenceFileRows.value.map((row) => row.file);
}

function addEvidenceFiles(files) {
    const max = props.intake.limits.evidence_max_files;
    const remaining = Math.max(0, max - evidenceFileRows.value.length);
    const picked = files.slice(0, remaining);

    for (const file of picked) {
        evidenceFileRows.value.push({
            key: `${file.name}-${file.size}-${Date.now()}-${Math.random()}`,
            file,
            name: file.name,
            size: file.size,
        });
    }

    syncEvidenceFilesToForm();
}

function onEvidencePick(event) {
    addEvidenceFiles(Array.from(event.target.files ?? []));
    if (fileInput.value) {
        fileInput.value.value = '';
    }
}

function onEvidenceDrop(event) {
    dragOver.value = false;
    addEvidenceFiles(Array.from(event.dataTransfer?.files ?? []));
}

function removeEvidenceFile(index) {
    evidenceFileRows.value.splice(index, 1);
    syncEvidenceFilesToForm();
}

function formatFileSize(bytes) {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function isDateFullySelected(group) {
    return group.messages.every((message) => form.structured_intake.conversation_links.includes(message.id));
}

function toggleDateGroup(group, checked) {
    const ids = group.messages.map((message) => message.id);
    if (checked) {
        form.structured_intake.conversation_links = [...new Set([...form.structured_intake.conversation_links, ...ids])];
        return;
    }
    form.structured_intake.conversation_links = form.structured_intake.conversation_links.filter((id) => !ids.includes(id));
}

function submit() {
    if (!canSubmit.value) {
        const first = submitBlockers.value[0];
        if (first) {
            window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
        }
        return;
    }

    form
        .transform((data) => ({
            ...data,
            structured_intake: {
                ...data.structured_intake,
                external_links: (data.structured_intake.external_links ?? []).filter((link) => (link.url ?? '').trim() !== ''),
            },
        }))
        .post(props.store_url, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                // Success toast is shown on the dispute page via session flash + AppToastHost.
            },
        });
}
</script>
