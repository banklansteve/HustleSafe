<template>
    <AppShell>
        <Head title="Create quest" />

        <div class="mx-auto w-full max-w-3xl">
            <div class="rounded-[2rem] bg-gradient-to-br from-primary-800 via-slate-900 to-slate-950 px-6 py-10 text-white shadow-xl ring-1 ring-white/10 sm:px-10">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.25em] text-teal-200/90">
                            Guided quest studio
                        </p>
                        <h1 class="font-display mt-3 text-3xl font-black tracking-tight sm:text-4xl">
                            Create a quest
                        </h1>
                        <p class="mt-3 max-w-xl text-sm font-semibold leading-relaxed text-teal-50/95">
                            Seven quick steps plus a review screen — nothing is saved until you submit. Your progress is kept in this browser until then.
                        </p>
                    </div>
                    <Link
                        :href="route('quests.index')"
                        class="inline-flex items-center rounded-full border border-white/25 bg-white/10 px-5 py-2.5 text-sm font-bold text-white backdrop-blur-sm transition hover:bg-white/20"
                    >
                        My quests
                    </Link>
                </div>
            </div>

            <div
                class="mt-6 rounded-2xl border border-slate-200/90 bg-white px-4 py-4 text-sm font-semibold text-slate-800 shadow-sm ring-1 ring-slate-100 sm:px-5"
                role="region"
                aria-label="Disputes and escrow"
            >
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Fair resolution</p>
                <p class="mt-2 leading-relaxed text-slate-700">
                    Every funded quest runs on escrow and a documented dispute path if expectations diverge. Skim how disputes work before you publish —
                    <Link :href="route('disputes.index')" class="font-black text-primary-800 underline decoration-primary-300 underline-offset-2">Disputes centre</Link>
                    ·
                    <a href="/docs/dispute-workflow.md" target="_blank" rel="noopener noreferrer" class="font-black text-primary-800 underline decoration-primary-300 underline-offset-2">Workflow (Markdown)</a>
                    ·
                    <Link :href="route('legal.terms')" class="font-black text-primary-800 underline decoration-primary-300 underline-offset-2">Terms</Link>.
                </p>
            </div>

            <div class="mt-8 flex flex-wrap gap-2">
                <button
                    v-for="(s, i) in stepTitles"
                    :key="s"
                    type="button"
                    class="rounded-full px-3 py-1.5 text-xs font-black uppercase tracking-wide transition"
                    :class="stepPillClass(i + 1)"
                    :disabled="i + 1 === 7 && maxReachedStep < 6"
                    @click="goToStep(i + 1)"
                >
                    {{ i + 1 }}. {{ s }}
                </button>
            </div>

            <form class="mt-8" @submit.prevent="submit">
                <Transition name="fade-slide" mode="out-in">
                    <div :key="step" class="rounded-2xl border border-slate-100 bg-white p-6 shadow-lg shadow-slate-900/5 ring-1 ring-slate-100 sm:p-8">
                        <div
                            v-if="clientStepBanner"
                            id="quest-create-alert"
                            class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold leading-snug text-rose-900 ring-1 ring-rose-100"
                            role="alert"
                        >
                            {{ clientStepBanner }}
                        </div>
                        <!-- Step 1 -->
                        <section v-if="step === 1">
                            <h2 class="font-display text-xl font-bold text-slate-900">
                                Category & story
                            </h2>
                            <p class="mt-1 text-sm text-slate-500">
                                Pick the closest subcategory — we tune follow-up questions from here.
                            </p>
                            <div class="mt-6 grid gap-5 sm:grid-cols-2">
                                <div>
                                    <div class="flex items-center gap-1">
                                        <InputLabel for="parent_cat" value="Domain" />
                                        <FieldHint :text="hintDomain" />
                                    </div>
                                    <UiSelect
                                        id="parent_cat"
                                        :model-value="parentCategoryId"
                                        class="mt-2"
                                        :options="parentSelectOptions"
                                        placeholder="Select domain"
                                        @update:model-value="onParentCategoryChange"
                                    />
                                </div>
                                <div>
                                    <div class="flex items-center gap-1">
                                        <InputLabel for="quest_category_id" value="Subcategory" />
                                        <FieldHint text="Leaf categories power matching and which optional fields appear." />
                                    </div>
                                    <UiSelect
                                        id="quest_category_id"
                                        v-model="form.quest_category_id"
                                        class="mt-2"
                                        :options="leafSelectOptions"
                                        :placeholder="leafOptions.length ? 'Choose subcategory' : 'Pick domain first'"
                                        :disabled="!leafOptions.length"
                                        :invalid="!!form.errors.quest_category_id"
                                    />
                                    <InputError class="mt-2" :message="form.errors.quest_category_id" />
                                </div>
                            </div>
                            <div class="mt-6 space-y-4">
                                <div>
                                    <InputLabel for="title" value="Title" />
                                    <TextInput id="title" v-model="form.title" type="text" class="mt-2 w-full rounded-xl border-slate-200 font-semibold shadow-sm" required />
                                    <InputError class="mt-2" :message="form.errors.title" />
                                </div>
                                <div>
                                    <div class="flex items-center gap-1">
                                        <InputLabel for="description" value="Description" />
                                        <FieldHint
                                            text="The more context you share, the better proposals you will get. Aim for enough detail that a freelancer could start without guessing."
                                        />
                                    </div>
                                    <div
                                        class="mt-3 rounded-2xl border border-primary-100/90 bg-gradient-to-br from-primary-50/95 via-white to-teal-50/50 p-4 shadow-sm ring-1 ring-primary-100/60"
                                        role="note"
                                    >
                                        <div class="flex gap-3">
                                            <InformationCircleIcon class="mt-0.5 h-5 w-5 shrink-0 text-primary-600" aria-hidden="true" />
                                            <div class="min-w-0">
                                                <p class="text-sm font-bold text-slate-900">Give freelancers enough to go on</p>
                                                <ul class="mt-2 list-inside list-disc space-y-1.5 text-xs font-semibold leading-relaxed text-slate-600">
                                                    <li>What outcome you need and how you will measure success</li>
                                                    <li>Deliverables, formats, brand or technical constraints</li>
                                                    <li>Timeline expectations, meetings, and collaboration style</li>
                                                    <li>Links, references, or files you will attach on the last step</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    <QuestRichDescriptionEditor
                                        id="description"
                                        v-model="form.description"
                                        class="mt-3"
                                        placeholder="Describe the quest in detail…"
                                        :invalid="!!form.errors.description"
                                    />
                                    <InputError class="mt-2" :message="form.errors.description" />
                                </div>
                            </div>
                        </section>

                        <!-- Step 2 -->
                        <section v-else-if="step === 2">
                            <h2 class="font-display text-xl font-bold text-slate-900">
                                Visibility & targeting
                            </h2>
                            <p class="mt-1 text-sm text-slate-500">
                                Control who discovers this quest and how freelancers should think about location.
                            </p>
                            <div class="mt-6 space-y-5">
                                <div>
                                    <div class="flex items-center gap-1">
                                        <InputLabel value="Job visibility" />
                                        <FieldHint :text="hintVisibility" learn-more-url="/dashboard/guides/trust" />
                                    </div>
                                    <UiSelect v-model="form.visibility" class="mt-2" :options="visibilityOptions" />
                                    <InputError class="mt-2" :message="form.errors.visibility" />
                                </div>
                                <div v-if="fieldProfile.show_location_pref !== false">
                                    <div class="flex items-center gap-1">
                                        <InputLabel value="Preferred freelancer location" />
                                        <FieldHint text="Remote-friendly welcomes nationwide talent; local-only signals on-site expectations." />
                                    </div>
                                    <UiSelect v-model="form.freelancer_location_pref" class="mt-2" :options="locationPrefOptions" />
                                    <InputError class="mt-2" :message="form.errors.freelancer_location_pref" />
                                </div>
                                <p
                                    v-else
                                    class="rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-2.5 text-xs font-semibold leading-relaxed text-slate-700 ring-1 ring-slate-100"
                                >
                                    This category is normally completed remotely — location preference is set to remote-friendly automatically.
                                </p>
                                <div v-if="fieldProfile.show_availability">
                                    <div class="flex items-center gap-1">
                                        <InputLabel value="Availability expectation" />
                                        <FieldHint text="Helps freelancers gauge capacity for your cadence." />
                                    </div>
                                    <UiSelect v-model="form.availability_need" class="mt-2" :options="availabilityOptions" placeholder="Select availability" />
                                    <InputError class="mt-2" :message="form.errors.availability_need" />
                                </div>
                                <div>
                                    <InputLabel for="traffic_source" value="Traffic source (optional)" />
                                    <TextInput id="traffic_source" v-model="form.traffic_source" type="text" class="mt-2 w-full rounded-xl border-slate-200 shadow-sm" placeholder="e.g. instagram, newsletter" />
                                </div>
                                <p class="text-xs font-semibold leading-relaxed text-slate-600">
                                    If you share special links to this quest, the three fields below help you see which posts or partners send the best visitors. They are optional — you can leave them blank.
                                </p>
                                <div class="grid gap-3 sm:grid-cols-3">
                                    <div>
                                        <div class="flex items-center gap-1">
                                            <InputLabel for="utm_s" value="Referrer / source" />
                                            <FieldHint text="Where the visitor came from — e.g. Instagram story, WhatsApp group, or a partner site." />
                                        </div>
                                        <TextInput id="utm_s" v-model="utm.utm_source" type="text" class="mt-2 w-full rounded-xl border-slate-200 text-sm shadow-sm" placeholder="e.g. instagram" />
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-1">
                                            <InputLabel for="utm_m" value="Channel type" />
                                            <FieldHint text="What kind of link it was — e.g. social, email, paid ad, or referral." />
                                        </div>
                                        <TextInput id="utm_m" v-model="utm.utm_medium" type="text" class="mt-2 w-full rounded-xl border-slate-200 text-sm shadow-sm" placeholder="e.g. social" />
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-1">
                                            <InputLabel for="utm_c" value="Campaign name" />
                                            <FieldHint text="A short label for this push — e.g. spring-launch — so you can compare results later." />
                                        </div>
                                        <TextInput id="utm_c" v-model="utm.utm_campaign" type="text" class="mt-2 w-full rounded-xl border-slate-200 text-sm shadow-sm" placeholder="e.g. spring-2026" />
                                    </div>
                                </div>
                                <p v-if="form.visibility === 'invite_only'" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-semibold text-amber-950">
                                    Invite-only quests need at least one tagged freelancer on the final step before publishing.
                                </p>
                            </div>
                        </section>

                        <!-- Step 3 -->
                        <section v-else-if="step === 3">
                            <h2 class="font-display text-xl font-bold text-slate-900">
                                Location
                            </h2>
                            <div class="mt-6 grid gap-5 sm:grid-cols-2">
                                <div>
                                    <InputLabel for="state_id" value="State" />
                                    <UiSelect
                                        id="state_id"
                                        v-model="form.state_id"
                                        class="mt-2"
                                        :options="stateSelectOptions"
                                        placeholder="Select state"
                                        :invalid="!!form.errors.state_id"
                                    />
                                    <InputError class="mt-2" :message="form.errors.state_id" />
                                </div>
                                <div>
                                    <InputLabel for="local_government_id" value="LGA" />
                                    <UiSelect
                                        id="local_government_id"
                                        v-model="form.local_government_id"
                                        class="mt-2"
                                        :options="lgaSelectOptions"
                                        :placeholder="form.state_id ? 'Select LGA' : 'State first'"
                                        :disabled="!form.state_id"
                                        :invalid="!!form.errors.local_government_id"
                                    />
                                    <InputError class="mt-2" :message="form.errors.local_government_id" />
                                </div>
                                <div class="sm:col-span-2">
                                    <InputLabel for="city" value="City / area" />
                                    <TextInput id="city" v-model="form.city" type="text" class="mt-2 w-full rounded-xl border-slate-200 shadow-sm" required />
                                    <InputError class="mt-2" :message="form.errors.city" />
                                </div>
                            </div>
                        </section>

                        <!-- Step 4 -->
                        <section v-else-if="step === 4">
                            <h2 class="font-display text-xl font-bold text-slate-900">
                                Schedule & budget
                            </h2>
                            <div class="mt-6 space-y-5">
                                <div>
                                    <div class="flex items-center gap-1">
                                        <InputLabel value="When work should start" />
                                        <FieldHint text="Window shopping is for early planning without a firm kickoff date." />
                                    </div>
                                    <UiSelect v-model="form.start_timing" class="mt-2" :options="startTimingOptionsUi" />
                                    <InputError class="mt-2" :message="form.errors.start_timing" />
                                </div>
                                <div v-if="form.start_timing === 'scheduled'">
                                    <InputLabel for="sched" value="Planned start date" />
                                    <PremiumDatePicker id="sched" v-model="form.scheduled_start_date" class="mt-2" placeholder="Pick start date" />
                                    <InputError class="mt-2" :message="form.errors.scheduled_start_date" />
                                </div>
                                <div>
                                    <InputLabel for="ecd" value="Estimated duration (days)" />
                                    <UiSelect
                                        id="ecd"
                                        v-model="form.estimated_completion_days"
                                        class="mt-2"
                                        :options="completionDayOptions"
                                        placeholder="Select duration"
                                    />
                                    <p
                                        v-if="completionGuidanceCopy"
                                        class="mt-2 rounded-xl border border-teal-100 bg-teal-50/60 px-3 py-2.5 text-xs font-semibold leading-relaxed text-teal-950 ring-1 ring-teal-100/80"
                                    >
                                        {{ completionGuidanceCopy }}
                                    </p>
                                </div>
                                <div>
                                    <div class="flex items-center gap-1">
                                        <InputLabel for="edd" value="Planned finish date (optional)" />
                                        <FieldHint text="Target date for the full deliverable — complements the duration above." />
                                    </div>
                                    <PremiumDatePicker
                                        id="edd"
                                        v-model="form.estimated_delivery_date"
                                        class="mt-2"
                                        placeholder="Optional finish date"
                                        :min="finishDateMin"
                                    />
                                    <InputError class="mt-2" :message="form.errors.estimated_delivery_date" />
                                </div>
                                <div class="rounded-2xl border border-slate-100 bg-gradient-to-br from-slate-50/90 to-white p-4 ring-1 ring-slate-100 sm:p-5">
                                    <div class="flex flex-wrap items-end justify-between gap-2">
                                        <InputLabel for="budget" value="Quest budget" />
                                        <p class="text-lg font-black tabular-nums text-primary-800">{{ formatNgn(clampedBudgetMinor) }}</p>
                                    </div>
                                    <div class="mt-2 flex justify-between gap-2 text-[10px] font-black uppercase tracking-wider text-slate-500">
                                        <span>Min {{ formatNgn(minBudgetMinor) }}</span>
                                        <span>Max {{ formatNgn(effectiveMaxBudgetMinor) }}</span>
                                    </div>
                                    <p
                                        v-if="verificationLimit && verificationLimit < maxBudgetMinor"
                                        class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs font-black leading-relaxed text-amber-800"
                                    >
                                        Your current verification level caps Quest budgets at {{ formatNgn(verificationLimit) }}.
                                    </p>
                                    <p
                                        v-if="budgetGuidanceCopy"
                                        class="mt-3 rounded-xl border border-primary-100 bg-primary-50/50 px-3 py-2.5 text-xs font-semibold leading-relaxed text-primary-950 ring-1 ring-primary-100/80"
                                    >
                                        {{ budgetGuidanceCopy }}
                                    </p>
                                    <input
                                        id="budget"
                                        v-model.number="budgetSliderModel"
                                        type="range"
                                        :min="minBudgetMinor"
                                        :max="effectiveMaxBudgetMinor"
                                        :step="budgetSliderStep"
                                        class="budget-range mt-4 w-full"
                                    />
                                    <div class="mt-4">
                                        <div class="flex items-center gap-1">
                                            <InputLabel for="budget_ngn_direct" value="Or type budget (₦)" />
                                            <FieldHint text="Whole naira. We keep it within the min and max shown above." />
                                        </div>
                                        <input
                                            id="budget_ngn_direct"
                                            v-model="budgetNairaText"
                                            type="text"
                                            inputmode="numeric"
                                            class="mt-2 w-full max-w-xs rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30"
                                            placeholder="e.g. 50000"
                                            @blur="commitBudgetNairaFromInput"
                                            @keyup.enter="commitBudgetNairaFromInput"
                                        />
                                    </div>
                                    <InputError class="mt-2" :message="form.errors.budget_amount_minor" />
                                </div>
                            </div>
                        </section>

                        <!-- Step 5 -->
                        <section v-else-if="step === 5">
                            <h2 class="font-display text-xl font-bold text-slate-900">
                                Scope & requirements
                            </h2>
                            <div class="mt-6 space-y-5">
                                <div v-if="fieldProfile.show_site_visit">
                                    <div class="flex items-center gap-1">
                                        <InputLabel value="Site visits before proposals?" />
                                        <FieldHint text="Great for trades, property, or on-site assessments. Digital-only work usually skips this." />
                                    </div>
                                    <UiSelect v-model="siteVisitChoice" class="mt-2" :options="yesNoOptions" />
                                </div>
                                <div>
                                    <div class="flex items-center gap-1">
                                        <InputLabel value="Project type" />
                                        <FieldHint text="Hourly unlocks estimated hours below." />
                                    </div>
                                    <UiSelect v-model="form.project_type" class="mt-2" :options="projectTypeOptions" placeholder="Optional" />
                                </div>
                                <div v-if="fieldProfile.show_hourly_fields && form.project_type === 'hourly'">
                                    <InputLabel for="eh" value="Estimated hours" />
                                    <input id="eh" v-model.number="form.estimated_hours" type="number" min="1" max="2000" class="mt-2 w-full rounded-xl border-slate-200 text-sm font-semibold shadow-sm" />
                                    <InputError class="mt-2" :message="form.errors.estimated_hours" />
                                </div>
                                <div v-if="fieldProfile.show_team_size">
                                    <InputLabel value="Freelancers needed" />
                                    <UiSelect v-model="form.team_size" class="mt-2" :options="teamSizeOptions" />
                                    <InputError class="mt-2" :message="form.errors.team_size" />
                                </div>
                                <div v-if="fieldProfile.show_site_access" class="space-y-4 rounded-xl border border-slate-100 bg-slate-50/60 p-4 ring-1 ring-slate-100">
                                    <p class="text-xs font-black uppercase tracking-wide text-slate-600">On-site brief</p>
                                    <div>
                                        <InputLabel value="Location accessibility" />
                                        <UiSelect v-model="form.site_access_level" class="mt-2" :options="siteAccessOptions" placeholder="Select access type" />
                                        <InputError class="mt-2" :message="form.errors.site_access_level" />
                                    </div>
                                    <div>
                                        <InputLabel value="Are pets usually on-site?" />
                                        <div class="mt-2 flex flex-wrap gap-4 text-sm font-semibold text-slate-800">
                                            <label class="inline-flex cursor-pointer items-center gap-2">
                                                <input v-model="form.pets_on_site" type="radio" class="text-primary-600 focus:ring-primary-500" :value="true" />
                                                Yes
                                            </label>
                                            <label class="inline-flex cursor-pointer items-center gap-2">
                                                <input v-model="form.pets_on_site" type="radio" class="text-primary-600 focus:ring-primary-500" :value="false" />
                                                No
                                            </label>
                                        </div>
                                        <InputError class="mt-2" :message="form.errors.pets_on_site" />
                                    </div>
                                    <div v-if="form.pets_on_site === true">
                                        <InputLabel value="Pet details (optional)" />
                                        <textarea
                                            v-model="form.pets_detail"
                                            rows="2"
                                            maxlength="255"
                                            class="mt-2 w-full rounded-xl border-slate-200 text-sm font-medium shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                            placeholder="e.g. Friendly dog, stays in garden during visits."
                                        />
                                        <InputError class="mt-2" :message="form.errors.pets_detail" />
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- Step 6 -->
                        <section v-else-if="step === 6">
                            <h2 class="font-display text-xl font-bold text-slate-900">
                                Trust, discovery & launch
                            </h2>
                            <p class="mt-1 text-sm text-slate-500">
                                Tags, files, and launch options — admins manage featured boosts after a quest is live.
                            </p>
                            <div class="mt-6 space-y-5">
                                <div>
                                    <div class="flex items-center gap-1">
                                        <InputLabel for="exp" value="Auto-close if unfilled (days, optional)" />
                                        <FieldHint text="We will mark the listing closed after this many days from publish." />
                                    </div>
                                    <input id="exp" v-model.number="form.auto_listing_expiry_days" type="number" min="1" max="90" class="mt-2 w-full rounded-xl border-slate-200 text-sm font-semibold shadow-sm" placeholder="Leave blank to keep open" />
                                </div>
                                <div>
                                    <InputLabel for="maxo" value="Max proposals (optional)" />
                                    <input id="maxo" v-model.number="form.max_offers" type="number" min="1" max="200" class="mt-2 w-full rounded-xl border-slate-200 text-sm font-semibold shadow-sm" />
                                </div>
                                <div v-if="freelancerNetworkGroups.length">
                                    <div class="flex items-center gap-1">
                                        <p class="text-xs font-black uppercase tracking-wide text-slate-500">
                                            Tag from your network
                                        </p>
                                        <FieldHint text="Multi-select freelancers you follow or who follow you. You can still search below to add anyone else." />
                                    </div>
                                    <UiMultiSelect
                                        v-model="form.tagged_freelancer_ids"
                                        class="mt-2"
                                        :groups="freelancerNetworkGroups"
                                        placeholder="Tap to choose followers / following"
                                        :max="20"
                                    />
                                </div>
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                        Or search freelancers
                                    </p>
                                    <TextInput v-model="tagQuery" type="search" class="mt-2 w-full rounded-xl border-slate-200 text-sm shadow-sm" placeholder="Search by name…" />
                                    <ul v-if="tagResults.length" class="mt-2 max-h-40 overflow-auto rounded-xl border border-slate-100 bg-white shadow-md">
                                        <li v-for="u in tagResults" :key="u.id">
                                            <button type="button" class="flex w-full px-3 py-2 text-left text-sm font-semibold hover:bg-primary-50" @click="addTag(u)">{{ u.name }}</button>
                                        </li>
                                    </ul>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <span v-for="t in taggedDisplay" :key="t.id" class="inline-flex items-center gap-2 rounded-full bg-primary-50 px-3 py-1 text-xs font-bold text-primary-950 ring-1 ring-primary-100">
                                            {{ t.label }}
                                            <button type="button" class="text-primary-700" @click="removeTag(t.id)">✕</button>
                                        </span>
                                    </div>
                                </div>
                                <div>
                                    <InputLabel value="Reference files" />
                                    <div
                                        class="mt-2 flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed px-4 py-7 text-center transition active:scale-[0.99] sm:py-8"
                                        :class="dragOver ? 'border-primary-400 bg-primary-50/80' : 'border-slate-200 bg-slate-50/80 hover:border-primary-300'"
                                        role="button"
                                        tabindex="0"
                                        @click="fileInput?.click()"
                                        @keydown.enter.prevent="fileInput?.click()"
                                        @dragover.prevent="dragOver = true"
                                        @dragleave.prevent="dragOver = false"
                                        @drop.prevent="onDrop"
                                    >
                                        <p class="text-sm font-bold text-slate-800">Drop files or tap to browse</p>
                                        <p class="mt-1 text-xs font-semibold text-slate-500">Images or PDF · up to 10 files</p>
                                        <input ref="fileInput" type="file" class="hidden" multiple accept=".jpg,.jpeg,.png,.webp,.gif,.pdf" @change="onFileInput" />
                                    </div>
                                    <ul v-if="fileRows.length" class="mt-4 grid grid-cols-3 gap-2 sm:gap-3">
                                        <li
                                            v-for="(row, idx) in fileRows"
                                            :key="row.key"
                                            class="flex min-w-0 flex-col overflow-hidden rounded-2xl border border-slate-100 bg-white ring-1 ring-slate-100"
                                        >
                                            <div class="relative aspect-square w-full overflow-hidden bg-slate-100">
                                                <img v-if="row.url" :src="row.url" alt="" class="h-full w-full object-cover" />
                                                <div v-else class="flex h-full w-full flex-col items-center justify-center px-1 text-center">
                                                    <span class="text-[10px] font-black uppercase text-slate-500">{{ row.isPdf ? 'PDF' : 'File' }}</span>
                                                </div>
                                            </div>
                                            <div class="min-w-0 p-2">
                                                <p class="line-clamp-2 text-[11px] font-bold leading-tight text-slate-900">{{ row.name }}</p>
                                                <p class="text-[10px] font-semibold text-slate-500">{{ formatFileSize(row.size) }}</p>
                                                <button
                                                    type="button"
                                                    class="mt-1.5 text-[10px] font-black uppercase tracking-wide text-rose-600 hover:text-rose-800"
                                                    @click="removeFile(idx)"
                                                >
                                                    Remove
                                                </button>
                                            </div>
                                        </li>
                                    </ul>
                                    <InputError class="mt-2" :message="form.errors.files" />
                                </div>
                            </div>
                        </section>

                        <!-- Step 7: Review -->
                        <section v-else class="space-y-6">
                            <div>
                                <h2 class="font-display text-xl font-bold text-slate-900">
                                    Review & submit
                                </h2>
                                <p class="mt-1 text-sm text-slate-500">
                                    Nothing is saved to the server until you submit. Edit any section below, or use Back to adjust step-by-step.
                                </p>
                                <p class="mt-3 rounded-xl border border-amber-100 bg-amber-50/80 px-4 py-2 text-xs font-semibold text-amber-950 ring-1 ring-amber-100/80">
                                    Drafts in this browser do not include attached files. If you refreshed the page, re-add references before publishing.
                                </p>
                            </div>

                            <div class="space-y-3">
                                <article
                                    v-for="block in previewBlocks"
                                    :key="block.id"
                                    class="rounded-2xl border border-slate-100 bg-slate-50/40 p-4 ring-1 ring-slate-100/80"
                                >
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <h3 class="text-sm font-black uppercase tracking-wide text-slate-500">
                                            {{ block.title }}
                                        </h3>
                                        <button
                                            type="button"
                                            class="rounded-full border border-primary-200 bg-white px-3 py-1 text-xs font-bold text-primary-800 shadow-sm transition hover:bg-primary-50"
                                            @click="goToStep(block.id)"
                                        >
                                            Edit section
                                        </button>
                                    </div>
                                    <dl class="mt-3 space-y-2 text-sm">
                                        <div v-for="row in block.rows" :key="row.label" class="flex flex-wrap gap-x-2 gap-y-1">
                                            <dt class="min-w-[7rem] font-bold text-slate-500">{{ row.label }}</dt>
                                            <dd class="min-w-0 flex-1 font-semibold text-slate-900">{{ row.value }}</dd>
                                        </div>
                                    </dl>
                                </article>
                            </div>

                            <details class="group rounded-2xl border border-slate-200 bg-white shadow-sm ring-1 ring-slate-100">
                                <summary
                                    class="cursor-pointer list-none px-4 py-3 text-sm font-bold text-slate-900 marker:hidden [&::-webkit-details-marker]:hidden"
                                >
                                    <span class="flex items-center justify-between gap-2">
                                        <span>Preview terms in-page</span>
                                        <span class="text-xs font-black uppercase tracking-wide text-primary-700 group-open:hidden">Open</span>
                                        <span class="hidden text-xs font-black uppercase tracking-wide text-primary-700 group-open:inline">Close</span>
                                    </span>
                                </summary>
                                <div class="border-t border-slate-100 p-3">
                                    <iframe
                                        title="Terms of Service"
                                        class="h-64 w-full rounded-xl border border-slate-200 bg-white sm:h-80"
                                        :src="termsFrameSrc"
                                    />
                                </div>
                            </details>

                            <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-emerald-200/90 bg-emerald-50/60 px-4 py-3 text-sm font-semibold text-emerald-950 ring-1 ring-emerald-100">
                                <input v-model="form.accepted_terms" type="checkbox" class="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                                <span>
                                    I agree to the
                                    <a :href="route('legal.terms')" target="_blank" rel="noopener noreferrer" class="font-black text-emerald-800 underline decoration-emerald-400 underline-offset-2">
                                        Terms of Service
                                    </a>
                                    and
                                    <a :href="route('legal.privacy')" target="_blank" rel="noopener noreferrer" class="font-black text-emerald-800 underline decoration-emerald-400 underline-offset-2">
                                        Privacy Policy
                                    </a>
                                    , and to post accurate quest details on HustleSafe.
                                </span>
                            </label>
                            <InputError class="mt-1" :message="form.errors.accepted_terms" />

                            <label class="flex items-start gap-3 rounded-2xl border border-emerald-100 bg-emerald-50/70 p-4 ring-1 ring-emerald-100/80">
                                <input v-model="form.publish_now" type="checkbox" class="mt-1 rounded border-slate-300 text-emerald-600" />
                                <span class="text-sm font-bold text-emerald-950">Publish immediately (otherwise save as draft)</span>
                            </label>
                        </section>
                    </div>
                </Transition>

                <div class="mt-8 flex flex-wrap items-center justify-between gap-4">
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-if="fromPreview"
                            type="button"
                            class="rounded-full border border-primary-200 bg-primary-50 px-6 py-2.5 text-sm font-bold text-primary-900 shadow-sm hover:bg-primary-100"
                            @click="exitEditToPreview"
                        >
                            Back to review
                        </button>
                        <button
                            v-else-if="step > 1"
                            type="button"
                            class="rounded-full border border-slate-200 bg-white px-6 py-2.5 text-sm font-bold text-slate-800 shadow-sm hover:bg-slate-50"
                            @click="prev"
                        >
                            Back
                        </button>
                    </div>
                    <div class="flex flex-wrap items-center justify-end gap-3">
                        <button
                            v-if="step < 7"
                            type="button"
                            class="rounded-full bg-gradient-to-r from-primary-600 to-teal-600 px-8 py-3 text-sm font-black text-white shadow-lg"
                            @click="next"
                        >
                            {{ continueLabel }}
                        </button>
                        <button
                            v-if="step === 7"
                            type="submit"
                            class="rounded-full bg-slate-900 px-8 py-3 text-sm font-black text-white shadow-lg disabled:opacity-50"
                            :disabled="form.processing || !form.accepted_terms"
                        >
                            {{ form.processing ? 'Saving…' : 'Submit quest' }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </AppShell>
</template>

<script setup>
import PremiumDatePicker from '@/Components/Ui/PremiumDatePicker.vue';
import FieldHint from '@/Components/Ui/FieldHint.vue';
import UiMultiSelect from '@/Components/Ui/UiMultiSelect.vue';
import UiSelect from '@/Components/Ui/UiSelect.vue';
import QuestRichDescriptionEditor from '@/Components/Quests/QuestRichDescriptionEditor.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import AppShell from '@/Layouts/AppShell.vue';
import { InformationCircleIcon } from '@heroicons/vue/24/outline';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, nextTick, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { useQuestCreateDraft } from '@/composables/useQuestCreateDraft';
import { validateQuestCreateStep } from '@/utils/questCreateClientValidation';
import { htmlToPlainText } from '@/utils/htmlPlainText';

const props = defineProps({
    locations: { type: Array, required: true },
    categoryTree: { type: Array, required: true },
    startTimingOptions: { type: Array, required: true },
    maxBudgetMinor: { type: Number, default: 100_000_000 },
    verificationLimit: { type: Number, default: null },
    minBudgetMinor: { type: Number, default: 10_000 },
    fieldProfileUrl: { type: String, required: true },
    freelancersYouFollow: { type: Array, default: () => [] },
    freelancersFollowingYou: { type: Array, default: () => [] },
    quest_stats_hints: {
        type: Object,
        default: () => ({
            by_category: {},
            global_budget: null,
            global_completion: null,
        }),
    },
});

const page = usePage();

const termsFrameSrc = computed(() => {
    const z = page.props.ziggy;
    if (z?.location) {
        return `${String(z.location).replace(/\/$/, '')}/terms-of-service`;
    }

    return '/terms-of-service';
});

const draftStorageKey = computed(() => `hustlesafe:quest-create:v1:${page.props.auth?.user?.id ?? 'guest'}`);

const stepTitles = ['Story', 'Audience', 'Location', 'Schedule', 'Scope', 'Launch', 'Review'];
const step = ref(1);
const maxReachedStep = ref(1);
const fromPreview = ref(false);
const clientStepBanner = ref('');
const dragOver = ref(false);
const fileInput = ref(null);
/** @type {import('vue').Ref<{ key: number, name: string, size: number, url: string | null, isPdf: boolean }[]>} */
const fileRows = ref([]);
let fileRowKey = 0;
const parentCategoryId = ref(0);
const fieldProfile = reactive({
    show_site_visit: false,
    show_site_access: false,
    show_pets_question: false,
    show_availability: true,
    show_hourly_fields: true,
    show_team_size: true,
    show_location_pref: true,
    remote_first: false,
    default_site_visits: false,
});
const tagQuery = ref('');
const tagResults = ref([]);
const tagLabelById = ref({});
let tagTimer = null;
const utm = reactive({ utm_source: '', utm_medium: '', utm_campaign: '' });

const completionPresets = [1, 2, 3, 5, 7, 10, 14, 21, 30, 45, 60, 90];

const visibilityOptions = [
    { value: 'public', label: 'Public — discoverable in Explore' },
    { value: 'invite_only', label: 'Invite-only — tagged + followers only' },
    { value: 'private', label: 'Private — only you (no marketplace proposals)' },
];
const locationPrefOptions = [
    { value: 'remote_friendly', label: 'Remote-friendly' },
    { value: 'local_only', label: 'Local only' },
];
const availabilityOptions = [
    { value: 'full_time', label: 'Full-time cadence' },
    { value: 'part_time', label: 'Part-time' },
    { value: 'as_needed', label: 'As-needed / flexible' },
];
const projectTypeOptions = [
    { value: 'fixed_price', label: 'Fixed price' },
    { value: 'hourly', label: 'Hourly rate' },
];
const teamSizeOptions = [
    { value: 'solo', label: 'Solo freelancer' },
    { value: 'small_team', label: 'Small team (2–5)' },
];
const yesNoOptions = [
    { value: 'yes', label: 'Yes, visits may be needed' },
    { value: 'no', label: 'No site visits' },
];

const siteAccessOptions = [
    { value: 'ground_level_easy', label: 'Ground level — easy access' },
    { value: 'stairs_no_lift', label: 'Stairs only (no lift)' },
    { value: 'stairs_with_lift', label: 'Stairs + lift available' },
    { value: 'ladder_or_height_work', label: 'Ladder / height work' },
    { value: 'narrow_or_difficult_access', label: 'Narrow or difficult access' },
    { value: 'other', label: 'Other (explain in description)' },
];

const siteVisitChoice = ref('no');

const form = useForm({
    quest_category_id: 0,
    title: '',
    description: '',
    visibility: 'public',
    freelancer_location_pref: 'remote_friendly',
    availability_need: 'as_needed',
    state_id: 0,
    local_government_id: 0,
    city: '',
    start_timing: 'flexible',
    scheduled_start_date: '',
    estimated_completion_days: 14,
    estimated_delivery_date: '',
    budget_amount_minor: 10_000,
    project_type: 'fixed_price',
    estimated_hours: null,
    team_size: 'solo',
    site_visits_allowed: false,
    site_access_level: '',
    pets_on_site: null,
    pets_detail: '',
    auto_listing_expiry_days: null,
    max_offers: null,
    traffic_source: '',
    publish_now: true,
    accepted_terms: false,
    tagged_freelancer_ids: [],
    files: [],
});

const startTimingOptionsUi = computed(() => props.startTimingOptions.map((o) => ({ value: o.value, label: o.label })));

const leafOptions = computed(() => {
    const p = props.categoryTree.find((c) => c.id === parentCategoryId.value);

    return p?.children ?? [];
});

const lgaOptions = computed(() => {
    const s = props.locations.find((x) => x.id === form.state_id);

    return s?.local_governments ?? [];
});

const parentSelectOptions = computed(() => props.categoryTree.map((p) => ({ value: p.id, label: p.name })));

const leafSelectOptions = computed(() => leafOptions.value.map((c) => ({ value: c.id, label: c.name })));

const stateSelectOptions = computed(() => props.locations.map((s) => ({ value: s.id, label: s.name })));

const lgaSelectOptions = computed(() => lgaOptions.value.map((lg) => ({ value: lg.id, label: lg.name })));

const completionDayOptions = computed(() => completionPresets.map((d) => ({ value: d, label: `${d} days` })));

const finishDateMin = computed(() => {
    if (form.start_timing === 'scheduled' && form.scheduled_start_date) {
        return form.scheduled_start_date;
    }

    return '';
});

watch(
    () => [form.start_timing, form.scheduled_start_date, form.estimated_delivery_date],
    () => {
        if (
            form.start_timing === 'scheduled'
            && form.scheduled_start_date
            && form.estimated_delivery_date
            && form.estimated_delivery_date < form.scheduled_start_date
        ) {
            form.estimated_delivery_date = form.scheduled_start_date;
        }
    },
);

const statsHintsByCategory = computed(() => props.quest_stats_hints?.by_category ?? {});

const budgetStatsForSelection = computed(() => {
    const id = form.quest_category_id;
    const key = id ? String(id) : '';
    const row = key ? statsHintsByCategory.value[key] : null;
    const b = row?.budget;

    return b?.sample_size ? b : props.quest_stats_hints?.global_budget ?? null;
});

const completionStatsForSelection = computed(() => {
    const id = form.quest_category_id;
    const key = id ? String(id) : '';
    const row = key ? statsHintsByCategory.value[key] : null;
    const c = row?.completion;

    return c?.sample_size ? c : props.quest_stats_hints?.global_completion ?? null;
});

const budgetGuidanceCopy = computed(() => {
    const b = budgetStatsForSelection.value;
    if (!b?.sample_size) {
        return '';
    }

    return `Open quests with a similar subcategory often budget between ${formatNgn(b.min_minor)} and ${formatNgn(b.max_minor)} (average ${formatNgn(b.avg_minor)}, from ${b.sample_size} live listings). This is guidance only — set what fits your scope.`;
});

const completionGuidanceCopy = computed(() => {
    const c = completionStatsForSelection.value;
    if (!c?.sample_size) {
        return '';
    }

    return `Typical timelines cluster around ${Math.round(c.avg_days)} days for comparable work (${c.min_days}–${c.max_days} day range across ${c.sample_size} quests). Pick what matches your delivery reality.`;
});

const taggedDisplay = computed(() => form.tagged_freelancer_ids.map((id) => ({ id, label: tagLabelById.value[id] || `#${id}` })));

const freelancerNetworkGroups = computed(() => {
    const groups = [];
    const mapUser = (u) => ({
        value: u.id,
        label: u.first_name ? `${String(u.first_name).trim()} · ${u.name}` : u.name,
        hint: u.slug ? `@${u.slug}` : undefined,
    });
    if (props.freelancersYouFollow?.length) {
        groups.push({
            label: 'Freelancers you follow',
            options: props.freelancersYouFollow.map(mapUser),
        });
    }
    if (props.freelancersFollowingYou?.length) {
        groups.push({
            label: 'Freelancers following you',
            options: props.freelancersFollowingYou.map(mapUser),
        });
    }

    return groups;
});

function clampBudgetMinor(n) {
    const minB = props.minBudgetMinor;
    const maxB = effectiveMaxBudgetMinor.value;
    const x = Number(n);
    if (!Number.isFinite(x)) {
        return minB;
    }

    return Math.min(maxB, Math.max(minB, x));
}

const budgetSliderStep = computed(() => {
    const span = effectiveMaxBudgetMinor.value - props.minBudgetMinor;
    if (span <= 500_000) {
        return 10_000;
    }
    if (span <= 5_000_000) {
        return 50_000;
    }

    return 100_000;
});

const budgetSliderModel = computed({
    get() {
        return clampBudgetMinor(form.budget_amount_minor);
    },
    set(v) {
        form.budget_amount_minor = clampBudgetMinor(v);
    },
});

const clampedBudgetMinor = computed(() => clampBudgetMinor(form.budget_amount_minor));
const effectiveMaxBudgetMinor = computed(() => Math.max(props.minBudgetMinor, Math.min(props.maxBudgetMinor, props.verificationLimit || props.maxBudgetMinor)));

const budgetNairaText = ref('');

watch(
    clampedBudgetMinor,
    (m) => {
        budgetNairaText.value = String(Math.round(m / 100));
    },
    { immediate: true },
);

function commitBudgetNairaFromInput() {
    const raw = String(budgetNairaText.value || '')
        .replace(/,/g, '')
        .trim();
    const n = Math.round(Number(raw));
    if (!Number.isFinite(n) || n < 0) {
        budgetNairaText.value = String(Math.round(clampedBudgetMinor.value / 100));

        return;
    }
    form.budget_amount_minor = clampBudgetMinor(n * 100);
    budgetNairaText.value = String(Math.round(form.budget_amount_minor / 100));
}

watch(step, (s) => {
    maxReachedStep.value = Math.max(maxReachedStep.value, s);
    clientStepBanner.value = '';
});

const continueLabel = computed(() => {
    if (fromPreview.value) {
        return 'Save & return to review';
    }
    if (step.value === 6) {
        return 'Continue to review';
    }

    return 'Continue';
});

function stepPillClass(i) {
    const active = i === step.value;
    const done = i < step.value;
    const locked = i === 7 && maxReachedStep.value < 6;

    if (active) {
        return 'bg-primary-600 text-white shadow-md';
    }
    if (done) {
        return 'bg-emerald-100 text-emerald-900 ring-1 ring-emerald-200';
    }
    if (locked) {
        return 'cursor-not-allowed bg-slate-100 text-slate-400 ring-1 ring-slate-200 opacity-60';
    }

    return 'bg-slate-100 text-slate-500 ring-1 ring-slate-200 hover:bg-slate-200';
}

function formatNgn(minor) {
    return `₦${(Number(minor) / 100).toLocaleString('en-NG', { maximumFractionDigits: 0 })}`;
}

function formatDate(value) {
    if (!value) return '';
    return new Intl.DateTimeFormat('en-NG', { dateStyle: 'medium' }).format(new Date(value));
}

function optionLabel(options, value) {
    const m = options.find((o) => String(o.value) === String(value));

    return m?.label ?? String(value ?? '—');
}

function leafCategoryName() {
    for (const p of props.categoryTree) {
        const c = (p.children || []).find((x) => Number(x.id) === Number(form.quest_category_id));
        if (c) {
            return c.name;
        }
    }

    return '—';
}

function parentDomainName() {
    const p = props.categoryTree.find((x) => x.id === parentCategoryId.value);

    return p?.name ?? '—';
}

function stateName() {
    return props.locations.find((s) => Number(s.id) === Number(form.state_id))?.name ?? '—';
}

function lgaName() {
    return lgaOptions.value.find((lg) => Number(lg.id) === Number(form.local_government_id))?.name ?? '—';
}

const previewBlocks = computed(() => {
    const timing = optionLabel(startTimingOptionsUi.value, form.start_timing);

    return [
        {
            id: 1,
            title: 'Story',
            rows: [
                { label: 'Domain', value: parentDomainName() },
                { label: 'Subcategory', value: leafCategoryName() },
                { label: 'Title', value: String(form.title || '').trim() || '—' },
                {
                    label: 'Description',
                    value: (() => {
                        const plain = htmlToPlainText(form.description);
                        return plain ? `${plain.slice(0, 220)}${plain.length > 220 ? '…' : ''}` : '—';
                    })(),
                },
            ],
        },
        {
            id: 2,
            title: 'Audience',
            rows: [
                { label: 'Visibility', value: optionLabel(visibilityOptions, form.visibility) },
                { label: 'Location pref', value: optionLabel(locationPrefOptions, form.freelancer_location_pref) },
                ...(fieldProfile.show_availability
                    ? [{ label: 'Availability', value: optionLabel(availabilityOptions, form.availability_need) }]
                    : []),
                { label: 'Traffic source', value: String(form.traffic_source || '').trim() || '—' },
                { label: 'Campaign tracking', value: [utm.utm_source, utm.utm_medium, utm.utm_campaign].filter(Boolean).join(' · ') || '—' },
            ],
        },
        {
            id: 3,
            title: 'Location',
            rows: [
                { label: 'State', value: stateName() },
                { label: 'LGA', value: lgaName() },
                { label: 'City', value: String(form.city || '').trim() || '—' },
            ],
        },
        {
            id: 4,
            title: 'Schedule & budget',
            rows: [
                { label: 'Start', value: timing },
                ...(form.start_timing === 'scheduled'
                    ? [{ label: 'Start date', value: String(form.scheduled_start_date || '').trim() || '—' }]
                    : []),
                { label: 'Estimated duration', value: `${form.estimated_completion_days} days` },
                { label: 'Planned finish', value: String(form.estimated_delivery_date || '').trim() || '—' },
                { label: 'Budget cap', value: formatNgn(form.budget_amount_minor) },
            ],
        },
        {
            id: 5,
            title: 'Scope',
            rows: [
                ...(fieldProfile.show_site_visit
                    ? [{ label: 'Site visits', value: form.site_visits_allowed ? 'Allowed / expected' : 'Not expected' }]
                    : []),
                ...(fieldProfile.show_site_access
                    ? [
                          { label: 'Location access', value: optionLabel(siteAccessOptions, form.site_access_level) },
                          {
                              label: 'Pets on site',
                              value: form.pets_on_site === true ? 'Yes' : form.pets_on_site === false ? 'No' : '—',
                          },
                          ...(String(form.pets_detail || '').trim()
                              ? [{ label: 'Pet notes', value: String(form.pets_detail).trim() }]
                              : []),
                      ]
                    : []),
                { label: 'Project type', value: optionLabel(projectTypeOptions, form.project_type) },
                ...(fieldProfile.show_hourly_fields && form.project_type === 'hourly'
                    ? [{ label: 'Est. hours', value: String(form.estimated_hours ?? '—') }]
                    : []),
                ...(fieldProfile.show_team_size ? [{ label: 'Team', value: optionLabel(teamSizeOptions, form.team_size) }] : []),
            ],
        },
        {
            id: 6,
            title: 'Launch',
            rows: [
                { label: 'Auto-expiry (days)', value: form.auto_listing_expiry_days != null && form.auto_listing_expiry_days !== '' ? String(form.auto_listing_expiry_days) : '—' },
                { label: 'Max proposals', value: form.max_offers != null && form.max_offers !== '' ? String(form.max_offers) : '—' },
                { label: 'Tagged freelancers', value: taggedDisplay.value.map((t) => t.label).join(', ') || '—' },
                { label: 'Files', value: form.files?.length ? `${form.files.length} attached` : 'None' },
            ],
        },
    ];
});

const hintDomain = 'Choose the broad industry so we can unlock the right optional questions.';
const hintVisibility = 'Public quests appear in Explore. Invite-only limits discovery to people you tag plus followers. Private hides the listing from others entirely.';

function validationDeps() {
    return {
        form,
        fieldProfile,
        categoryTree: props.categoryTree,
        locations: props.locations,
        maxBudgetMinor: props.maxBudgetMinor,
        minBudgetMinor: props.minBudgetMinor,
    };
}

function applyClientErrors(errors) {
    form.clearErrors();
    for (const [k, v] of Object.entries(errors)) {
        form.setError(k, v);
    }
}

const { clearDraft } = useQuestCreateDraft(
    draftStorageKey,
    () => ({
        step: step.value,
        parentCategoryId: parentCategoryId.value,
        fieldProfile: { ...fieldProfile },
        siteVisitChoice: siteVisitChoice.value,
        utm: { ...utm },
        tagLabelById: { ...tagLabelById.value },
        form,
    }),
    (data) => {
        if (typeof data.step === 'number') {
            step.value = Math.min(7, Math.max(1, data.step));
        }
        if (data.parentCategoryId != null) {
            parentCategoryId.value = data.parentCategoryId;
        }
        if (data.fieldProfile && typeof data.fieldProfile === 'object') {
            Object.assign(fieldProfile, data.fieldProfile);
        }
        if (data.utm && typeof data.utm === 'object') {
            Object.assign(utm, data.utm);
        }
        if (data.siteVisitChoice === 'yes' || data.siteVisitChoice === 'no') {
            siteVisitChoice.value = data.siteVisitChoice;
        }
        if (data.tagLabelById && typeof data.tagLabelById === 'object') {
            tagLabelById.value = { ...data.tagLabelById };
        }
        if (data.form && typeof data.form === 'object') {
            for (const [key, val] of Object.entries(data.form)) {
                if (key === 'files') {
                    continue;
                }
                if (key in form) {
                    form[key] = val;
                }
            }
        }
        maxReachedStep.value = Math.max(maxReachedStep.value, step.value, 1);
    },
);

watch(siteVisitChoice, (v) => {
    form.site_visits_allowed = v === 'yes';
});

function onParentCategoryChange(value) {
    const next = Number(value);
    if (next === Number(parentCategoryId.value)) {
        return;
    }
    parentCategoryId.value = next;
    form.quest_category_id = 0;
}

function syncParentCategoryFromLeaf() {
    const leafId = Number(form.quest_category_id);
    if (!leafId) {
        return;
    }
    for (const p of props.categoryTree) {
        const children = p.children || [];
        if (children.some((c) => Number(c.id) === leafId)) {
            const pid = Number(p.id);
            if (Number(parentCategoryId.value) !== pid) {
                parentCategoryId.value = pid;
            }

            return;
        }
    }
}

watch(
    () => [form.quest_category_id, props.categoryTree],
    () => {
        syncParentCategoryFromLeaf();
    },
    { deep: true },
);

onMounted(() => {
    syncParentCategoryFromLeaf();
});

watch(
    () => form.state_id,
    (newId, oldId) => {
        if (oldId === undefined) {
            return;
        }
        if (Number(newId) !== Number(oldId)) {
            form.local_government_id = 0;
        }
    },
);

watch(
    () => form.quest_category_id,
    async (id) => {
        if (!id) {
            return;
        }
        try {
            const { data } = await axios.get(props.fieldProfileUrl, { params: { quest_category_id: id } });
            Object.assign(fieldProfile, data);
            if (!fieldProfile.show_site_access) {
                form.site_access_level = '';
                form.pets_on_site = null;
                form.pets_detail = '';
            }
            if (fieldProfile.remote_first || fieldProfile.show_location_pref === false) {
                form.freelancer_location_pref = 'remote_friendly';
            }
            if (!fieldProfile.show_site_visit) {
                siteVisitChoice.value = 'no';
                form.site_visits_allowed = false;
            } else if (fieldProfile.default_site_visits) {
                siteVisitChoice.value = 'yes';
                form.site_visits_allowed = true;
            }
            if (!fieldProfile.show_availability) {
                form.availability_need = null;
            } else if (!form.availability_need) {
                form.availability_need = 'as_needed';
            }
        } catch {
            /* ignore */
        }
    },
);

watch(tagQuery, (q) => {
    window.clearTimeout(tagTimer);
    if (!q || q.trim().length < 2) {
        tagResults.value = [];

        return;
    }
    tagTimer = window.setTimeout(async () => {
        try {
            const { data } = await axios.get(route('users.freelancers.search'), { params: { q: q.trim() } });
            tagResults.value = (data.users || []).filter((u) => !form.tagged_freelancer_ids.some((x) => Number(x) === Number(u.id)));
        } catch {
            tagResults.value = [];
        }
    }, 280);
});

watch(
    () => [...form.tagged_freelancer_ids],
    () => {
        const next = { ...tagLabelById.value };
        const pool = [...(props.freelancersYouFollow || []), ...(props.freelancersFollowingYou || [])];
        for (const rawId of form.tagged_freelancer_ids) {
            const id = Number(rawId);
            if (!Number.isFinite(id) || next[id]) {
                continue;
            }
            const u = pool.find((x) => Number(x.id) === id);
            if (u) {
                next[id] = u.first_name ? `${String(u.first_name).trim()} · ${u.name}` : u.name;
            }
        }
        tagLabelById.value = next;
    },
    { deep: true },
);

function buildPayload() {
    const raw = { ...form.data() };
    delete raw.files;

    const traffic_utm = {};
    if (utm.utm_source) {
        traffic_utm.utm_source = utm.utm_source;
    }
    if (utm.utm_medium) {
        traffic_utm.utm_medium = utm.utm_medium;
    }
    if (utm.utm_campaign) {
        traffic_utm.utm_campaign = utm.utm_campaign;
    }

    return {
        ...raw,
        traffic_utm: Object.keys(traffic_utm).length ? traffic_utm : null,
        tagged_freelancer_ids: form.tagged_freelancer_ids,
    };
}

function scrollToStepAlert() {
    nextTick(() => {
        document.getElementById('quest-create-alert')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
}

function next() {
    const deps = validationDeps();
    if (fromPreview.value) {
        for (let s = 1; s <= 6; s += 1) {
            const { ok, errors } = validateQuestCreateStep(s, deps);
            if (!ok) {
                const msg = Object.values(errors)[0];
                clientStepBanner.value = typeof msg === 'string' ? msg : 'Please fix the highlighted fields.';
                applyClientErrors(errors);
                step.value = s;
                scrollToStepAlert();

                return;
            }
        }
        form.clearErrors();
        clientStepBanner.value = '';
        fromPreview.value = false;
        step.value = 7;

        return;
    }

    const { ok, errors } = validateQuestCreateStep(step.value, deps);
    if (!ok) {
        const msg = Object.values(errors)[0];
        clientStepBanner.value = typeof msg === 'string' ? msg : 'Please fix the highlighted fields.';
        applyClientErrors(errors);
        scrollToStepAlert();

        return;
    }

    form.clearErrors();
    clientStepBanner.value = '';
    if (step.value === 6) {
        step.value = 7;

        return;
    }
    if (step.value < 6) {
        step.value += 1;
    }
}

function prev() {
    if (step.value === 7) {
        step.value = 6;
        fromPreview.value = false;
        return;
    }
    if (step.value > 1) {
        step.value -= 1;
    }
}

function goToStep(n) {
    if (n < 1 || n > 7) {
        return;
    }
    if (n === 7 && maxReachedStep.value < 6) {
        return;
    }
    const wasPreview = step.value === 7;
    step.value = n;
    fromPreview.value = wasPreview && n >= 1 && n < 7;
    form.clearErrors();
}

function exitEditToPreview() {
    fromPreview.value = false;
    step.value = 7;
    form.clearErrors();
}

function addTag(u) {
    if (form.tagged_freelancer_ids.length >= 20) {
        return;
    }
    const id = Number(u.id);
    if (form.tagged_freelancer_ids.some((x) => Number(x) === id)) {
        return;
    }
    form.tagged_freelancer_ids.push(id);
    tagLabelById.value = { ...tagLabelById.value, [id]: u.label || u.name };
    tagResults.value = [];
    tagQuery.value = '';
}

function removeTag(id) {
    form.tagged_freelancer_ids = form.tagged_freelancer_ids.filter((x) => Number(x) !== Number(id));
}

function formatFileSize(bytes) {
    const n = Number(bytes) || 0;
    if (n < 1024) {
        return `${n} B`;
    }
    if (n < 1024 * 1024) {
        return `${(n / 1024).toFixed(1)} KB`;
    }

    return `${(n / (1024 * 1024)).toFixed(1)} MB`;
}

function pushFiles(list) {
    for (const file of Array.from(list || [])) {
        if (form.files.length >= 10) {
            break;
        }
        form.files.push(file);
        const isPdf = file.type === 'application/pdf' || /\.pdf$/i.test(file.name);
        const url = file.type.startsWith('image/') ? URL.createObjectURL(file) : null;
        fileRowKey += 1;
        fileRows.value.push({
            key: fileRowKey,
            name: file.name,
            size: file.size,
            url,
            isPdf,
        });
    }
}

function removeFile(index) {
    const row = fileRows.value[index];
    if (row?.url) {
        URL.revokeObjectURL(row.url);
    }
    fileRows.value.splice(index, 1);
    form.files.splice(index, 1);
}

function onFileInput(e) {
    pushFiles(e.target.files);
    e.target.value = '';
}

function onDrop(e) {
    dragOver.value = false;
    pushFiles(e.dataTransfer?.files);
}

onUnmounted(() => {
    for (const row of fileRows.value) {
        if (row.url) {
            URL.revokeObjectURL(row.url);
        }
    }
});

function submit() {
    if (step.value !== 7) {
        return;
    }
    const deps = validationDeps();
    for (let s = 1; s <= 6; s += 1) {
        const { ok, errors } = validateQuestCreateStep(s, deps);
        if (!ok) {
            const msg = Object.values(errors)[0];
            clientStepBanner.value = typeof msg === 'string' ? msg : 'Please fix the highlighted fields.';
            applyClientErrors(errors);
            step.value = s;
            scrollToStepAlert();

            return;
        }
    }

    if (form.visibility === 'invite_only' && form.tagged_freelancer_ids.length < 1) {
        form.clearErrors();
        form.setError('tagged_freelancer_ids', 'Add at least one freelancer for invite-only quests.');
        clientStepBanner.value = 'Invite-only quests need at least one tagged freelancer.';
        step.value = 6;
        scrollToStepAlert();

        return;
    }
    if (!form.accepted_terms) {
        form.clearErrors();
        form.setError('accepted_terms', 'Please confirm you agree to the Terms of Service and Privacy Policy.');
        clientStepBanner.value = 'Confirm the legal checkboxes before submitting.';
        scrollToStepAlert();

        return;
    }
    clientStepBanner.value = '';
    form
        .transform((data) => ({
            ...data,
            site_visits_allowed: !!data.site_visits_allowed,
            publish_now: !!data.publish_now,
            accepted_terms: !!data.accepted_terms,
            traffic_utm: buildPayload().traffic_utm,
            tagged_freelancer_ids: data.tagged_freelancer_ids.length ? data.tagged_freelancer_ids.map((x) => Number(x)) : [],
            availability_need: data.availability_need || null,
            project_type: data.project_type || null,
            estimated_hours: data.estimated_hours || null,
            team_size: data.team_size || null,
            auto_listing_expiry_days: data.auto_listing_expiry_days || null,
            max_offers: data.max_offers || null,
            traffic_source: data.traffic_source?.trim() || null,
            estimated_delivery_date: data.estimated_delivery_date || null,
            scheduled_start_date: data.scheduled_start_date || null,
        }))
        .post(route('quests.store'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => clearDraft(),
        });
}
</script>

<style scoped>
.fade-slide-enter-active,
.fade-slide-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}
.fade-slide-enter-from {
    opacity: 0;
    transform: translateX(12px);
}
.fade-slide-leave-to {
    opacity: 0;
    transform: translateX(-12px);
}

.budget-range {
    -webkit-appearance: none;
    appearance: none;
    height: 2.75rem;
    background: transparent;
    touch-action: manipulation;
}

.budget-range:focus {
    outline: none;
}

.budget-range:focus-visible::-webkit-slider-thumb {
    box-shadow: 0 0 0 4px rgb(20 184 166 / 0.35);
}

.budget-range::-webkit-slider-runnable-track {
    height: 0.5rem;
    border-radius: 9999px;
    background: linear-gradient(to right, rgb(226 232 240), rgb(203 213 225));
}

.budget-range::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 1.375rem;
    height: 1.375rem;
    margin-top: -0.4375rem;
    border-radius: 9999px;
    border: 3px solid #fff;
    background: linear-gradient(145deg, rgb(13 148 136), rgb(59 130 246));
    box-shadow: 0 2px 10px rgb(15 23 42 / 0.22);
}

.budget-range::-moz-range-track {
    height: 0.5rem;
    border-radius: 9999px;
    background: linear-gradient(to right, rgb(226 232 240), rgb(203 213 225));
}

.budget-range::-moz-range-thumb {
    width: 1.375rem;
    height: 1.375rem;
    border-radius: 9999px;
    border: 3px solid #fff;
    background: linear-gradient(145deg, rgb(13 148 136), rgb(59 130 246));
    box-shadow: 0 2px 10px rgb(15 23 42 / 0.22);
}
</style>
