<template>
    <AppShell>
        <Head :title="quest.title">
            <meta v-if="quest.meta_description" head-key="quest-desc" name="description" :content="quest.meta_description" />
            <meta head-key="quest-og-title" property="og:title" :content="quest.title" />
            <meta v-if="quest.meta_description" head-key="quest-og-desc" property="og:description" :content="quest.meta_description" />
            <meta head-key="quest-og-type" property="og:type" content="article" />
            <link v-if="quest.canonical_url" head-key="quest-canonical" rel="canonical" :href="quest.canonical_url" />
        </Head>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <p v-if="is_quest_owner || isStaffRole" class="text-xs font-black uppercase tracking-[0.2em] text-primary-700">
                    Quest · {{ quest.reference_code }}
                </p>
                <p v-else class="text-xs font-black uppercase tracking-[0.2em] text-primary-700">
                    {{ quest.category ? `${quest.category.parent_name ? quest.category.parent_name + ' · ' : ''}${quest.category.name}` : 'Open quest' }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Link
                    :href="allQuestsHref"
                    class="inline-flex items-center rounded-full border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-800 shadow-sm hover:border-primary-200 hover:bg-primary-50"
                >
                    All quests
                </Link>
                <Link
                    v-if="can_edit"
                    :href="route('quests.create')"
                    class="inline-flex items-center rounded-full border border-primary-200 bg-primary-50 px-4 py-2 text-xs font-bold text-primary-900 shadow-sm hover:bg-primary-100"
                >
                    New quest
                </Link>
                <button
                    v-if="isFreelancer && workspace.enabled"
                    type="button"
                    class="inline-flex items-center rounded-full border px-4 py-2 text-xs font-bold shadow-sm transition"
                    :class="localBookmarked ? 'border-emerald-200 bg-emerald-50 text-emerald-900 hover:bg-emerald-100' : 'border-slate-200 bg-white text-slate-800 hover:border-primary-200 hover:bg-primary-50'"
                    :disabled="bookmarkBusy"
                    @click="toggleBookmark"
                >
                    {{ bookmarkBusy ? 'Saving...' : localBookmarked ? 'Saved' : 'Save quest' }}
                </button>
            </div>
        </div>

        <div
            v-if="qualityGateIssues.length"
            class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-950 ring-1 ring-rose-100"
            role="alert"
        >
            <p class="font-bold">Fix these items before publishing</p>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-xs font-semibold leading-relaxed">
                <li v-for="(issue, idx) in qualityGateIssues" :key="idx">{{ issue.message }}</li>
            </ul>
        </div>

        <div
            v-if="is_quest_owner && quest.status === 'open' && quest.is_client_edit_locked"
            class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm font-semibold text-amber-950 ring-1 ring-amber-100"
            role="status"
        >
            <p class="font-bold">
                This quest can no longer be edited
            </p>
            <p class="mt-1 text-xs font-semibold leading-relaxed text-amber-900/95">
                The client editing window has closed. Freelancers who already sent a proposal were notified by email and in-app when you saved changes earlier.
            </p>
        </div>
        <div
            v-else-if="is_quest_owner && quest.status === 'open' && quest.client_edit_until && !quest.is_client_edit_locked"
            class="mt-4 rounded-xl border border-sky-200 bg-sky-50 px-5 py-4 text-sm font-semibold text-sky-950 ring-1 ring-sky-100"
            role="status"
        >
            <p>
                You can edit this quest until
                <span class="font-black">{{ formatWhen(quest.client_edit_until) }}</span>
                (Lagos time). After that, the brief locks so proposals stay comparable.
            </p>
        </div>

        <div
            v-if="is_quest_owner && quest.status === 'closed_unawarded'"
            class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-5 py-4 text-sm font-semibold text-slate-900 ring-1 ring-slate-100"
            role="status"
        >
            <p class="font-bold">Closed — unawarded</p>
            <p class="mt-1 leading-relaxed text-slate-700">
                This quest stopped accepting proposals without an award. Repost in one step to relaunch with the same details, a fresh timeline, and zero proposals.
            </p>
            <button
                v-if="quest.can_repost"
                type="button"
                class="mt-3 inline-flex items-center rounded-full bg-primary-600 px-4 py-2 text-xs font-black text-white shadow-sm hover:bg-primary-700 disabled:opacity-60"
                :disabled="repostBusy"
                @click="submitRepost"
            >
                {{ repostBusy ? 'Reposting…' : 'Repost quest' }}
            </button>
        </div>

        <div v-if="workspace.enabled && workspacePanelItems.length" class="mt-4 rounded-xl border border-secondary-200/80 bg-gradient-to-r from-secondary-50 via-amber-50/90 to-secondary-50 p-5 shadow-sm ring-1 ring-secondary-100 sm:p-6">
            <p class="text-xs font-black uppercase tracking-[0.2em] text-secondary-800">
                Freelancer workspace
            </p>
            <ul class="mt-3 space-y-3">
                <li
                    v-for="(item, i) in workspacePanelItems"
                    :key="i"
                    class="list-none rounded-xl border border-secondary-100/80 bg-white/60 px-3 py-2.5 text-sm font-semibold text-secondary-950 ring-1 ring-white/60"
                >
                    <p class="leading-snug">
                        {{ item.message }}
                    </p>
                    <Link
                        v-if="item.action_url"
                        :href="item.action_url"
                        class="mt-2 inline-flex items-center gap-1 text-xs font-black uppercase tracking-wide text-secondary-900 underline decoration-secondary-400 underline-offset-2 hover:text-secondary-700"
                    >
                        {{ item.action_label || 'Fix this' }}
                        <span aria-hidden="true">→</span>
                    </Link>
                </li>
            </ul>
        </div>

        <div class="mt-6 grid gap-2 lg:grid-cols-12 lg:gap-3">
            <div class="space-y-2 lg:col-span-8">
                <section class="overflow-hidden rounded-xl border border-slate-100 bg-white shadow-md shadow-slate-900/5 ring-1 ring-slate-100">
                    <div class="relative h-44 w-full bg-slate-200 sm:h-52">
                        <img :src="quest.cover_url" alt="" class="absolute inset-0 h-full w-full object-cover" loading="eager" />
                    </div>
                    <div class="border-t border-slate-800 bg-slate-950 px-4 py-4 text-white sm:px-6 sm:py-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <h1 class="font-display min-w-0 flex-1 text-2xl font-black tracking-tight sm:text-3xl md:text-4xl">
                                {{ quest.title }}
                            </h1>
                            <p class="shrink-0 rounded-full bg-white/10 px-3 py-1 text-xs font-black text-white ring-1 ring-white/20">
                                {{ formatBudget(quest.budget_minor) }}
                            </p>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2 text-[11px] font-bold sm:text-xs">
                            <span
                                class="rounded-full px-3 py-1 uppercase tracking-wide ring-1 ring-white/20"
                                :class="statusPillHero(quest.status)"
                            >
                                {{ questStatusLabel(quest.status) }}
                            </span>
                            <span
                                v-if="quest.category"
                                class="rounded-full bg-white/10 px-3 py-1 text-white/95 ring-1 ring-white/15"
                            >
                                {{ quest.category.parent_name ? `${quest.category.parent_name} · ` : '' }}{{ quest.category.name }}
                            </span>
                            <span class="rounded-full bg-white/10 px-3 py-1 text-white/95 ring-1 ring-white/15">
                                {{ [quest.location.city, quest.location.lga, quest.location.state].filter(Boolean).join(' · ') }}
                            </span>
                            <span
                                v-if="quest.featured_boost"
                                class="rounded-full bg-amber-500 px-3 py-1 text-[10px] font-black uppercase tracking-wide text-white shadow-sm ring-1 ring-amber-300/50"
                            >
                                {{ quest.featured_boost.badge_label || 'Boosted' }}
                            </span>
                            <span
                                v-if="quest.visibility"
                                class="rounded-full bg-white/10 px-3 py-1 text-white/95 ring-1 ring-white/15"
                            >
                                {{ visibilityLabel(quest.visibility) }}
                            </span>
                            <span
                                v-if="Number(quest.views_count) > 0"
                                class="rounded-full bg-white/5 px-3 py-1 text-white/90 ring-1 ring-white/10"
                            >
                                {{ quest.views_count }} views
                            </span>
                            <span
                                v-if="Number(quest.saves_count) > 0"
                                class="rounded-full bg-white/5 px-3 py-1 text-white/90 ring-1 ring-white/10"
                            >
                                {{ quest.saves_count }} saves
                            </span>
                            <span
                                v-if="completionSchedule?.hard_deadline_date"
                                class="rounded-full bg-rose-500/20 px-3 py-1 text-white ring-1 ring-rose-200/30"
                            >
                                Deadline {{ completionSchedule.hard_deadline_date }}
                            </span>
                            <span
                                v-else-if="completionSchedule?.planned_finish_date"
                                class="rounded-full bg-white/10 px-3 py-1 text-white/95 ring-1 ring-white/15"
                            >
                                Target finish {{ completionSchedule.planned_finish_date }}
                            </span>
                        </div>
                        <p
                            v-if="questListingMeta.length"
                            class="mt-3 flex flex-wrap gap-x-3 gap-y-1 text-[11px] font-semibold text-white/80 sm:text-xs"
                        >
                            <span v-for="(meta, mi) in questListingMeta" :key="mi">{{ meta }}</span>
                        </p>
                    </div>
                    <div class="space-y-6 border-t border-slate-100 p-5 sm:p-6">
                        <div
                            v-if="viewerInsightLines.length"
                            class="rounded-xl border border-primary-100 bg-gradient-to-r from-primary-50/90 to-teal-50/80 p-4 ring-1 ring-primary-100/80"
                        >
                            <p class="text-[10px] font-black uppercase tracking-wide text-primary-800">
                                For you
                            </p>
                            <ul class="mt-2 space-y-1.5 text-sm font-semibold text-slate-900">
                                <li v-for="(line, vi) in viewerInsightLines" :key="vi" class="flex gap-2">
                                    <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-primary-500" aria-hidden="true" />
                                    <span>{{ line }}</span>
                                </li>
                            </ul>
                        </div>

                        <div v-if="quest.admin_notices?.length" class="space-y-3">
                            <div
                                v-for="notice in quest.admin_notices"
                                :key="notice.id"
                                class="rounded-xl border p-4 text-sm font-semibold"
                                :class="notice.type === 'urgent' ? 'border-rose-200 bg-rose-50 text-rose-950' : notice.type === 'warning' ? 'border-amber-200 bg-amber-50 text-amber-950' : 'border-primary-100 bg-primary-50 text-primary-950'"
                            >
                                <p class="text-[10px] font-black uppercase tracking-wide">{{ notice.type.replace(/_/g, ' ') }} notice from HustleSafe</p>
                                <p class="mt-1 leading-6">{{ notice.body }}</p>
                            </div>
                        </div>

                        <div>
                            <h2 class="font-display text-sm font-black uppercase tracking-wide text-slate-500">
                                About this quest
                            </h2>
                            <div
                                v-if="quest.description"
                                class="quest-description-html prose prose-sm mt-3 max-w-none font-medium leading-relaxed text-slate-700 prose-headings:font-bold prose-a:text-primary-700 prose-a:underline"
                                v-html="quest.description"
                            />
                            <p v-else class="mt-3 rounded-lg border border-dashed border-slate-200 bg-slate-50/80 px-4 py-6 text-sm font-semibold text-slate-500">
                                The client has not added a written description yet — use the gallery and budget as your guide, or ask in chat once you connect.
                            </p>
                            <div v-if="quest.required_skills?.length" class="mt-4 flex flex-wrap gap-2">
                                <span
                                    v-for="skill in quest.required_skills"
                                    :key="skill"
                                    class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-800 ring-1 ring-slate-200"
                                >
                                    {{ skill }}
                                </span>
                            </div>
                        </div>

                        <section
                            v-if="!is_quest_owner"
                            class="rounded-xl border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100"
                        >
                            <h2 class="font-display text-sm font-black uppercase tracking-wide text-slate-500">
                                Client's preferences
                                <span class="font-semibold normal-case text-slate-400">(optional)</span>
                            </h2>
                            <p class="mt-1 text-xs font-semibold text-slate-500">
                                You can accept, propose alternatives, or clarify when you send a proposal.
                            </p>
                            <ul v-if="quest_has_specified_preferences" class="mt-4 space-y-2 text-sm font-semibold text-slate-700">
                                <li v-for="pref in quest_preferences" :key="pref.key" class="flex gap-2">
                                    <span :class="pref.is_specified ? 'text-emerald-600' : 'text-slate-400'">
                                        {{ pref.is_specified ? '✓' : '○' }}
                                    </span>
                                    <span>
                                        <span class="font-black text-slate-900">{{ pref.label }}:</span>
                                        {{ pref.display_value }}
                                    </span>
                                </li>
                            </ul>
                            <p v-else class="mt-3 text-sm font-semibold text-slate-600">
                                Client didn't specify preferences — you can propose your approach in your proposal.
                            </p>
                            <p v-if="quest.preferences_last_updated" class="mt-3 text-xs font-semibold text-slate-500">
                                Preferences last updated {{ formatWhen(quest.preferences_last_updated) }}
                            </p>
                        </section>

                        <section
                            v-if="is_quest_owner && can_edit_preferences"
                            class="rounded-xl border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100"
                        >
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h2 class="font-display text-sm font-black uppercase tracking-wide text-slate-500">
                                        Your preferences
                                    </h2>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">
                                        Optional details freelancers see before they propose.
                                    </p>
                                </div>
                                <button
                                    v-if="!showEditPreferencesForm"
                                    type="button"
                                    class="rounded-full border border-primary-200 bg-primary-50 px-4 py-2 text-xs font-black text-primary-900 hover:bg-primary-100"
                                    @click="openEditPreferences"
                                >
                                    Edit preferences
                                </button>
                                <button
                                    v-else
                                    type="button"
                                    class="rounded-full border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-800 hover:bg-slate-50"
                                    @click="closeEditPreferences"
                                >
                                    Cancel
                                </button>
                            </div>
                            <ul v-if="!showEditPreferencesForm && quest_has_specified_preferences" class="mt-4 space-y-2 text-sm font-semibold text-slate-700">
                                <li v-for="pref in quest_preferences" :key="pref.key" class="flex gap-2">
                                    <span :class="pref.is_specified ? 'text-emerald-600' : 'text-slate-400'">
                                        {{ pref.is_specified ? '✓' : '○' }}
                                    </span>
                                    <span>
                                        <span class="font-black text-slate-900">{{ pref.label }}:</span>
                                        {{ pref.display_value }}
                                    </span>
                                </li>
                            </ul>
                            <p v-else-if="!showEditPreferencesForm" class="mt-3 text-sm font-semibold text-slate-600">
                                No preferences specified yet — add them to guide freelancers.
                            </p>
                            <p v-if="quest.preferences_last_updated && !showEditPreferencesForm" class="mt-3 text-xs font-semibold text-slate-500">
                                Last updated {{ formatWhen(quest.preferences_last_updated) }}
                            </p>
                            <form v-if="showEditPreferencesForm" class="mt-4 space-y-4" @submit.prevent="submitPreferencesEdit">
                                <div
                                    v-if="offersCountDisplay > 0"
                                    class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-950 ring-1 ring-amber-100"
                                    role="alert"
                                >
                                    You have {{ offersCountDisplay }} {{ offersCountDisplay === 1 ? 'proposal' : 'proposals' }}.
                                    Changes may affect how freelancers interpreted your brief — they are not notified automatically.
                                </div>
                                <QuestPreferenceFields
                                    v-model="preferencesEditForm.preferences"
                                    :profile="preference_profile"
                                />
                                <InputError :message="preferencesEditForm.errors.preferences" />
                                <button
                                    type="submit"
                                    class="inline-flex items-center rounded-full bg-primary-600 px-6 py-2.5 text-xs font-black text-white shadow-sm hover:bg-primary-700 disabled:opacity-60"
                                    :disabled="preferencesEditForm.processing"
                                >
                                    <ReLoader4Line v-if="preferencesEditForm.processing" class="mr-2 h-4 w-4 animate-spin" />
                                    Save preferences
                                </button>
                            </form>
                        </section>

                        <div v-if="can_edit && form_options" id="edit-listing-panel" class="rounded-xl border border-slate-200/90 bg-slate-50/60 p-4 ring-1 ring-slate-100">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <h2 class="font-display text-sm font-black uppercase tracking-wide text-slate-600">
                                    Edit listing
                                </h2>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-if="!showEditQuestForm"
                                        type="button"
                                        class="rounded-full border border-primary-200 bg-primary-50 px-4 py-2 text-xs font-black text-primary-900 hover:bg-primary-100"
                                        @click="showEditQuestForm = true"
                                    >
                                        Edit details
                                    </button>
                                    <button
                                        v-else
                                        type="button"
                                        class="rounded-full border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-800 hover:bg-slate-50"
                                        @click="closeEditQuestForm"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </div>
                            <form v-show="showEditQuestForm" class="mt-4 space-y-4" @submit.prevent="submitEdit">
                                <div>
                                    <InputLabel for="e_title" value="Title" />
                                    <TextInput id="e_title" v-model="editForm.title" type="text" class="mt-1 w-full rounded-xl border-slate-200 shadow-sm" />
                                    <InputError class="mt-1" :message="editForm.errors.title" />
                                </div>
                                <div>
                                    <InputLabel for="e_desc" value="Description" />
                                    <QuestRichDescriptionEditor
                                        id="e_desc"
                                        v-model="editForm.description"
                                        class="mt-1"
                                        placeholder="Update your brief…"
                                        :invalid="!!editForm.errors.description"
                                    />
                                    <InputError class="mt-1" :message="editForm.errors.description" />
                                </div>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <InputLabel for="e_parent" value="Domain" />
                                        <UiSelect
                                            id="e_parent"
                                            class="mt-1"
                                            :model-value="editParentId"
                                            :options="editParentCategoryOptions"
                                            placeholder="Domain"
                                            @update:model-value="setEditParent"
                                        />
                                    </div>
                                    <div>
                                        <InputLabel for="e_cat" value="Subcategory" />
                                        <UiSelect
                                            id="e_cat"
                                            v-model="editForm.quest_category_id"
                                            class="mt-1"
                                            :options="editLeafUiOptions"
                                            placeholder="Subcategory"
                                            :invalid="!!editForm.errors.quest_category_id"
                                        />
                                        <InputError class="mt-1" :message="editForm.errors.quest_category_id" />
                                    </div>
                                </div>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <InputLabel for="e_state" value="State" />
                                        <UiSelect
                                            id="e_state"
                                            v-model="editForm.state_id"
                                            class="mt-1"
                                            :options="editStateOptions"
                                            placeholder="State"
                                        />
                                    </div>
                                    <div>
                                        <InputLabel for="e_lga" value="LGA" />
                                        <UiSelect
                                            id="e_lga"
                                            v-model="editForm.local_government_id"
                                            class="mt-1"
                                            :options="editLgaUiOptions"
                                            placeholder="LGA"
                                            :disabled="!editLgas.length"
                                        />
                                    </div>
                                </div>
                                <div>
                                    <InputLabel for="e_city" value="City" />
                                    <TextInput id="e_city" v-model="editForm.city" type="text" class="mt-1 w-full rounded-xl border-slate-200 shadow-sm" />
                                </div>
                                <div>
                                    <InputLabel for="e_budget" value="Budget (₦)" />
                                    <input
                                        id="e_budget"
                                        v-model.number="editBudgetNgn"
                                        type="range"
                                        min="100"
                                        max="500000"
                                        step="500"
                                        class="mt-2 h-2 w-full cursor-pointer accent-primary-600"
                                    />
                                    <p class="mt-1 text-xs font-bold text-primary-800">
                                        {{ formatBudget(editForm.budget_amount_minor) }}
                                    </p>
                                    <InputError class="mt-1" :message="editForm.errors.budget_amount_minor" />
                                </div>
                                <div>
                                    <InputLabel for="e_timing" value="Start timing" />
                                    <UiSelect
                                        id="e_timing"
                                        v-model="editForm.start_timing"
                                        class="mt-1"
                                        :options="start_timing_options"
                                        placeholder="Start timing"
                                    />
                                </div>
                                <div v-if="editForm.start_timing === 'scheduled'">
                                    <InputLabel for="e_sched" value="Scheduled date" />
                                    <PremiumDatePicker id="e_sched" v-model="editForm.scheduled_start_date" placeholder="Pick date" />
                                    <InputError class="mt-1" :message="editForm.errors.scheduled_start_date" />
                                </div>
                                <div>
                                    <InputLabel for="e_days" value="Est. completion (days)" />
                                    <input
                                        id="e_days"
                                        v-model.number="editForm.estimated_completion_days"
                                        type="number"
                                        min="1"
                                        max="365"
                                        class="mt-1 w-full rounded-xl border-slate-200 text-sm font-semibold shadow-sm"
                                    />
                                </div>
                                <button
                                    type="submit"
                                    class="w-full rounded-full bg-slate-900 py-3 text-sm font-black text-white shadow-md hover:bg-slate-800 disabled:opacity-50"
                                    :disabled="editForm.processing"
                                >
                                    Save changes
                                </button>
                            </form>
                        </div>

                        <div>
                            <h2 class="font-display text-sm font-black uppercase tracking-wide text-slate-500">
                                At a glance
                            </h2>
                            <dl class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                <div class="rounded-lg bg-slate-50/90 p-3 ring-1 ring-slate-100">
                                    <dt class="text-[10px] font-black uppercase tracking-wide text-slate-500">
                                        Start timing
                                    </dt>
                                    <dd class="mt-1 text-sm font-bold text-slate-900">
                                        {{ timingLabel(quest.start_timing) }}
                                    </dd>
                                </div>
                                <div class="rounded-lg bg-slate-50/90 p-3 ring-1 ring-slate-100">
                                    <dt class="text-[10px] font-black uppercase tracking-wide text-slate-500">
                                        Est. completion
                                    </dt>
                                    <dd class="mt-1 text-sm font-bold text-slate-900">
                                        {{ quest.estimated_completion_days }} days
                                    </dd>
                                </div>
                                <div class="rounded-lg bg-slate-50/90 p-3 ring-1 ring-slate-100">
                                    <dt class="text-[10px] font-black uppercase tracking-wide text-slate-500">
                                        Due target
                                    </dt>
                                    <dd class="mt-1 text-sm font-bold text-slate-900">
                                        {{ formatWhen(quest.due_at) }}
                                    </dd>
                                    <p v-if="completionSchedule?.summary" class="mt-1 text-[11px] font-semibold text-slate-500">
                                        {{ completionSchedule.summary }}
                                    </p>
                                </div>
                                <div v-if="quest.project_type" class="rounded-lg bg-slate-50/90 p-3 ring-1 ring-slate-100">
                                    <dt class="text-[10px] font-black uppercase tracking-wide text-slate-500">
                                        Project type
                                    </dt>
                                    <dd class="mt-1 text-sm font-bold text-slate-900">
                                        {{ projectTypeLabel(quest.project_type) }}
                                    </dd>
                                </div>
                                <div v-if="quest.team_size" class="rounded-lg bg-slate-50/90 p-3 ring-1 ring-slate-100">
                                    <dt class="text-[10px] font-black uppercase tracking-wide text-slate-500">
                                        People for the job
                                    </dt>
                                    <dd class="mt-1 text-sm font-bold text-slate-900">
                                        {{ teamSizeLabel(quest.team_size) }}
                                    </dd>
                                </div>
                                <div v-if="quest.availability_need" class="rounded-lg bg-slate-50/90 p-3 ring-1 ring-slate-100">
                                    <dt class="text-[10px] font-black uppercase tracking-wide text-slate-500">
                                        Availability
                                    </dt>
                                    <dd class="mt-1 text-sm font-bold text-slate-900">
                                        {{ availabilityLabel(quest.availability_need) }}
                                    </dd>
                                </div>
                                <div v-if="quest.freelancer_location_pref" class="rounded-lg bg-slate-50/90 p-3 ring-1 ring-slate-100">
                                    <dt class="text-[10px] font-black uppercase tracking-wide text-slate-500">
                                        Location preference
                                    </dt>
                                    <dd class="mt-1 text-sm font-bold text-slate-900">
                                        {{ locationPrefLabel(quest.freelancer_location_pref) }}
                                    </dd>
                                </div>
                                <div v-if="quest.featured_boost" class="rounded-lg bg-primary-50/90 p-3 ring-1 ring-primary-100">
                                    <dt class="text-[10px] font-black uppercase tracking-wide text-slate-500">
                                        Featured boost
                                    </dt>
                                    <dd class="mt-1 text-sm font-bold text-primary-900">
                                        {{ quest.featured_boost.label }}
                                    </dd>
                                    <p class="mt-1 text-[11px] font-semibold text-primary-800">
                                        Active until {{ formatWhen(quest.featured_boost.expires_at) }}
                                    </p>
                                </div>
                                <div v-if="quest.max_offers != null" class="rounded-lg bg-slate-50/90 p-3 ring-1 ring-slate-100">
                                    <dt class="text-[10px] font-black uppercase tracking-wide text-slate-500">
                                        Max proposals
                                    </dt>
                                    <dd class="mt-1 text-sm font-bold text-slate-900">
                                        {{ quest.max_offers === 0 ? 'Unlimited' : quest.max_offers }}
                                    </dd>
                                </div>
                                <div v-if="completionSchedule?.planned_finish_date" class="rounded-lg bg-slate-50/90 p-3 ring-1 ring-slate-100">
                                    <dt class="text-[10px] font-black uppercase tracking-wide text-slate-500">
                                        Planned finish
                                    </dt>
                                    <dd class="mt-1 text-sm font-bold text-slate-900">
                                        {{ completionSchedule.planned_finish_date }}
                                    </dd>
                                    <p class="mt-1 text-[11px] font-semibold text-slate-500">Client target — flexible planning date.</p>
                                </div>
                                <div v-if="completionSchedule?.hard_deadline_date" class="rounded-lg bg-rose-50/80 p-3 ring-1 ring-rose-100">
                                    <dt class="text-[10px] font-black uppercase tracking-wide text-rose-700">
                                        Delivery deadline
                                    </dt>
                                    <dd class="mt-1 text-sm font-bold text-rose-950">
                                        {{ completionSchedule.hard_deadline_date }}
                                    </dd>
                                    <p class="mt-1 text-[11px] font-semibold text-rose-800">Hard cutoff for finished work.</p>
                                </div>
                                <div v-if="quest.estimated_hours" class="rounded-lg bg-slate-50/90 p-3 ring-1 ring-slate-100">
                                    <dt class="text-[10px] font-black uppercase tracking-wide text-slate-500">
                                        Est. hours
                                    </dt>
                                    <dd class="mt-1 text-sm font-bold text-slate-900">
                                        {{ quest.estimated_hours }} h
                                    </dd>
                                </div>
                                <div v-if="quest.scheduled_start_date" class="rounded-lg bg-slate-50/90 p-3 ring-1 ring-slate-100">
                                    <dt class="text-[10px] font-black uppercase tracking-wide text-slate-500">
                                        Scheduled start
                                    </dt>
                                    <dd class="mt-1 text-sm font-bold text-slate-900">
                                        {{ quest.scheduled_start_date }}
                                    </dd>
                                </div>
                                <div v-if="is_quest_owner && quest.listing_expires_at && quest.is_listing_clock_active" class="rounded-lg bg-slate-50/90 p-3 ring-1 ring-slate-100">
                                    <dt class="text-[10px] font-black uppercase tracking-wide text-slate-500">
                                        Proposal deadline
                                    </dt>
                                    <dd class="mt-1 text-sm font-bold text-slate-900">
                                        {{ formatWhen(quest.listing_expires_at) }}
                                    </dd>
                                </div>
                                <div v-else-if="is_quest_owner && quest.due_at && !quest.is_listing_clock_active" class="rounded-lg bg-slate-50/90 p-3 ring-1 ring-slate-100">
                                    <dt class="text-[10px] font-black uppercase tracking-wide text-slate-500">
                                        Contract delivery deadline
                                    </dt>
                                    <dd class="mt-1 text-sm font-bold text-slate-900">
                                        {{ formatWhen(quest.due_at) }}
                                    </dd>
                                </div>
                                <div v-else-if="is_quest_owner && quest.listing_expires_at && quest.status === 'closed_unawarded'" class="rounded-lg bg-slate-50/90 p-3 ring-1 ring-slate-100">
                                    <dt class="text-[10px] font-black uppercase tracking-wide text-slate-500">
                                        Listing closed
                                    </dt>
                                    <dd class="mt-1 text-sm font-bold text-slate-900">
                                        {{ formatWhen(quest.listing_expires_at) }}
                                    </dd>
                                </div>
                                <div v-if="is_quest_owner && quest.traffic_source" class="rounded-lg bg-slate-50/90 p-3 ring-1 ring-slate-100">
                                    <dt class="text-[10px] font-black uppercase tracking-wide text-slate-500">
                                        Traffic source
                                    </dt>
                                    <dd class="mt-1 text-sm font-bold text-slate-900">
                                        {{ quest.traffic_source }}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </section>

                <section class="rounded-xl border border-slate-100 bg-white p-5 shadow-md shadow-slate-900/5 ring-1 ring-slate-100 sm:p-6">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="font-display text-lg font-bold text-slate-900">
                            Gallery
                        </h2>
                        <span class="text-xs font-semibold text-slate-500">{{ quest.files.length }} / 10</span>
                    </div>
                    <QuestFileGallery class="mt-4" :files="quest.files" :can-delete="can_edit" @delete="removeFile" />
                    <div v-if="can_edit" class="mt-5">
                        <label class="block text-xs font-black uppercase tracking-wide text-slate-500">Add file</label>
                        <input
                            type="file"
                            accept=".jpg,.jpeg,.png,.webp,.gif,.pdf"
                            class="mt-2 block w-full text-sm font-semibold text-slate-700 file:mr-4 file:rounded-full file:border-0 file:bg-primary-600 file:px-4 file:py-2 file:text-xs file:font-black file:text-white hover:file:bg-primary-700"
                            @change="uploadFile"
                        />
                        <InputError class="mt-2" :message="uploadForm.errors.file" />
                    </div>
                </section>

                <QuestBoostUpsellPanel
                    v-if="is_quest_owner && boost_upsell && (boost_upsell.can_purchase || boost_upsell.has_active_boost || boost_upsell.has_scheduled_follow_on)"
                    :upsell="boost_upsell"
                    :quest="quest"
                    :checkout-url="route('quests.boost.checkout', quest.route_key)"
                    :dismiss-url="route('quests.boost.dismiss-upsell', quest.route_key)"
                />

                <section
                    v-if="quest.commerce"
                    class="rounded-xl border border-emerald-100 bg-gradient-to-br from-emerald-50/90 via-white to-teal-50/70 p-5 shadow-md ring-1 ring-emerald-100 sm:p-6"
                >
                    <h2 class="font-display text-lg font-bold text-slate-900">
                        Escrow & disputes
                    </h2>
                    <p class="mt-1 text-xs font-semibold text-emerald-950/80">
                        Payments and rulings activate once the gateway is connected — timers and evidence still run today.
                    </p>

                    <div v-if="quest.commerce.escrow_timeline" class="mt-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-emerald-900">Escrow timeline</p>
                        <div class="mt-2">
                            <EscrowTransparencyTimeline :timeline="quest.commerce.escrow_timeline" />
                        </div>
                    </div>

                    <DisputePreventionPrompts v-if="quest.commerce.dispute_prevention_prompts?.length" class="mt-4" :prompts="quest.commerce.dispute_prevention_prompts" />
                    <div v-if="quest.commerce.contract_url" class="mt-4 rounded-xl border border-primary-100 bg-primary-50/60 px-4 py-3">
                        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-primary-900">Contract</p>
                        <p class="mt-1 text-xs font-semibold text-primary-950">
                            {{ quest.commerce.contract_reference }} · {{ quest.commerce.contract_status_label }}
                        </p>
                        <Link
                            :href="quest.commerce.contract_url"
                            class="mt-2 inline-flex items-center rounded-full bg-primary-700 px-4 py-2 text-xs font-black uppercase tracking-wide text-white hover:bg-primary-800"
                        >
                            View contract
                        </Link>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <form
                            v-if="quest.commerce.show_fund_button && quest.commerce.funding_post_url"
                            :action="quest.commerce.funding_post_url"
                            method="POST"
                            class="inline-block"
                        >
                            <input type="hidden" name="_token" :value="csrfToken" />
                            <button
                                type="submit"
                                class="inline-flex items-center rounded-full bg-emerald-700 px-4 py-2 text-xs font-black uppercase tracking-wide text-white shadow-sm hover:bg-emerald-800"
                            >
                                Pay with Paystack
                            </button>
                        </form>
                        <Link
                            v-if="quest.commerce.active_dispute"
                            :href="quest.commerce.active_dispute.url"
                            class="inline-flex items-center rounded-full border border-emerald-200 bg-white px-4 py-2 text-xs font-black uppercase tracking-wide text-emerald-900 shadow-sm hover:bg-emerald-50"
                        >
                            Open dispute
                        </Link>
                        <Link
                            v-else-if="quest.commerce.can_open_dispute && quest.commerce.dispute_create_url"
                            :href="quest.commerce.dispute_create_url"
                            class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-4 py-2 text-xs font-black uppercase tracking-wide text-amber-950 shadow-sm hover:bg-amber-100"
                        >
                            Open dispute
                        </Link>
                    </div>
                    <p v-if="quest.commerce.dispute_block_reason && !quest.commerce.can_open_dispute && !quest.commerce.active_dispute" class="mt-3 text-xs font-semibold text-amber-900">
                        {{ quest.commerce.dispute_block_reason }}
                    </p>
                </section>

                <section v-if="similar_quests.length" class="rounded-xl border border-slate-100 bg-white p-5 shadow-md shadow-slate-900/5 ring-1 ring-slate-100 sm:p-6">
                    <h2 class="font-display text-lg font-bold text-slate-900">
                        Similar quests nearby
                    </h2>
                    <ul class="mt-4 space-y-2">
                        <li v-for="s in similar_quests" :key="s.uuid">
                            <Link
                                :href="route('quests.show', s.slug || s.uuid)"
                                class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-100 bg-slate-50/80 px-4 py-3 text-sm font-bold text-slate-900 ring-1 ring-slate-100 transition hover:border-primary-200 hover:bg-white"
                            >
                                <span class="min-w-0 flex-1 truncate">{{ s.title }}</span>
                                <span class="text-xs font-semibold text-primary-800">{{ formatBudget(s.budget_minor) }}</span>
                            </Link>
                        </li>
                    </ul>
                </section>

                <section v-if="from_client_quests.length" class="rounded-xl border border-slate-100 bg-white p-5 shadow-md shadow-slate-900/5 ring-1 ring-slate-100 sm:p-6">
                    <h2 class="font-display text-lg font-bold text-slate-900">
                        More from this client
                    </h2>
                    <ul class="mt-4 space-y-2">
                        <li v-for="s in from_client_quests" :key="s.uuid">
                            <Link
                                :href="route('quests.show', s.slug || s.uuid)"
                                class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-100 bg-slate-50/80 px-4 py-3 text-sm font-bold text-slate-900 ring-1 ring-slate-100 transition hover:border-primary-200 hover:bg-white"
                            >
                                <span class="min-w-0 flex-1 truncate">{{ s.title }}</span>
                                <span class="text-xs font-semibold text-primary-800">{{ formatBudget(s.budget_minor) }}</span>
                            </Link>
                        </li>
                    </ul>
                </section>

                <section v-if="category_quests_other_areas.length" class="rounded-xl border border-slate-100 bg-white p-5 shadow-md shadow-slate-900/5 ring-1 ring-slate-100 sm:p-6">
                    <h2 class="font-display text-lg font-bold text-slate-900">
                        Same category, other states
                    </h2>
                    <ul class="mt-4 space-y-2">
                        <li v-for="s in category_quests_other_areas" :key="s.uuid">
                            <Link
                                :href="route('quests.show', s.slug || s.uuid)"
                                class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-100 bg-slate-50/80 px-4 py-3 text-sm font-bold text-slate-900 ring-1 ring-slate-100 transition hover:border-primary-200 hover:bg-white"
                            >
                                <span class="min-w-0 flex-1 truncate">{{ s.title }}</span>
                                <span class="text-xs font-semibold text-slate-600">{{ s.state }}</span>
                            </Link>
                        </li>
                    </ul>
                </section>
            </div>

            <aside class="space-y-2 lg:col-span-4">
                <PartyInsightCard
                    v-if="showSponsorInsightCard"
                    heading="Client profile"
                    :insight="sponsor_insight"
                />
                <section
                    v-else-if="!is_quest_owner && quest.client?.name"
                    class="rounded-xl border border-slate-100 bg-white p-5 shadow-md shadow-slate-900/5 ring-1 ring-slate-100"
                >
                    <h2 class="font-display text-sm font-bold uppercase tracking-wide text-slate-500">
                        Client
                    </h2>
                    <div class="mt-4 flex items-center gap-3">
                        <UserProfileAvatar
                            :href="clientProfileHref"
                            :src="quest.client.avatar_url"
                            :name="quest.client.name"
                            :alt="quest.client.name"
                            frame-class="h-12 w-12 text-sm shadow-md"
                        />
                        <div class="min-w-0 text-left">
                            <Link
                                v-if="clientProfileHref"
                                :href="clientProfileHref"
                                prefetch="false"
                                preserve-scroll
                                class="block truncate font-bold text-slate-900 underline decoration-primary-300 decoration-2 underline-offset-2 hover:text-primary-800"
                            >
                                {{ quest.client.name }}
                            </Link>
                            <p v-else class="truncate font-bold text-slate-900">
                                {{ quest.client.name }}
                            </p>
                            <p v-if="quest.client.username" class="truncate text-xs font-semibold text-slate-500">
                                @{{ quest.client.username }}
                            </p>
                        </div>
                    </div>
                </section>

                <section
                    v-if="is_quest_owner && boost_upsell?.can_purchase && !boost_upsell?.has_active_boost"
                    class="rounded-xl border border-amber-100 bg-gradient-to-br from-amber-50/90 via-white to-orange-50/70 p-4 shadow-md ring-1 ring-amber-100"
                >
                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-amber-900">
                        Optional
                    </p>
                    <p class="mt-1 text-sm font-bold text-slate-900">
                        Boost this quest
                    </p>
                    <p class="mt-1 text-xs font-semibold text-slate-600">
                        Reach matching pros faster with a paid visibility boost.
                    </p>
                    <a
                        href="#boost-quest"
                        class="mt-3 inline-flex w-full items-center justify-center rounded-full bg-amber-600 px-4 py-2.5 text-xs font-black uppercase tracking-wide text-white shadow-sm hover:bg-amber-700"
                    >
                        View boost options
                    </a>
                </section>

                <section
                    v-if="is_quest_owner && messageThreadCount"
                    class="rounded-xl border border-primary-100 bg-gradient-to-br from-primary-50/90 via-white to-teal-50/80 p-5 shadow-md ring-1 ring-primary-100"
                >
                    <h2 class="font-display text-lg font-bold text-slate-900">
                        Quest messages
                    </h2>
                    <p class="mt-1 text-xs font-semibold text-slate-600">
                        Secure in-app chat — no phone numbers or email.
                    </p>
                    <ul v-if="showMessageThreadList" class="mt-4 space-y-2">
                        <li v-for="t in quest_message_threads" :key="t.slug" class="flex items-center gap-2 rounded-xl border border-white/80 bg-white/90 py-1 pl-1 pr-2 shadow-sm ring-1 ring-slate-100">
                            <UserProfileAvatar
                                :href="route('freelancers.public', t.slug)"
                                :src="t.avatar_url"
                                :name="t.name"
                                :alt="t.name"
                                frame-class="h-10 w-10 text-[10px]"
                            />
                            <Link
                                :href="t.messages_url"
                                prefetch="false"
                                preserve-scroll
                                class="flex min-w-0 flex-1 items-center gap-2 rounded-lg px-2 py-2 text-sm font-bold text-slate-900 transition hover:bg-primary-50/60"
                            >
                                <span class="min-w-0 flex-1 truncate">{{ t.first_name || t.name }}</span>
                                <ChatBubbleLeftRightIcon class="h-5 w-5 shrink-0 text-primary-700" aria-hidden="true" />
                            </Link>
                        </li>
                    </ul>
                    <div v-else-if="showMessageThreadPicker" class="mt-4 space-y-3">
                        <div>
                            <InputLabel value="Choose freelancer" />
                            <UiSelect
                                v-model="selectedMessageThreadSlug"
                                class="mt-1"
                                :options="messageThreadOptions"
                                placeholder="Select a freelancer…"
                            />
                        </div>
                        <Link
                            v-if="selectedMessageThread"
                            :href="selectedMessageThread.messages_url"
                            prefetch="false"
                            preserve-scroll
                            class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-primary-600 px-4 py-2.5 text-xs font-black uppercase tracking-wide text-white shadow-sm hover:bg-primary-700"
                        >
                            <UserProfileAvatar
                                :src="selectedMessageThread.avatar_url"
                                :name="selectedMessageThread.name"
                                :alt="selectedMessageThread.name"
                                frame-class="h-8 w-8 text-[10px]"
                            />
                            Message {{ selectedMessageThread.first_name || selectedMessageThread.name }}
                            <ChatBubbleLeftRightIcon class="h-5 w-5 shrink-0" aria-hidden="true" />
                        </Link>
                        <p class="text-center text-[11px] font-semibold text-slate-500">
                            {{ messageThreadCount }} freelancers available — pick one to open chat.
                        </p>
                    </div>
                </section>

                <ProposalClarificationInboxPanel
                    v-if="clarification_alerts?.length"
                    :items="clarification_alerts"
                    :variant="is_quest_owner ? 'violet' : 'sky'"
                    :title="is_quest_owner ? 'Clarifying questions on this quest' : 'Your clarifying questions'"
                    :show-proposal-link="is_quest_owner"
                />

                <section
                    v-if="is_quest_owner && client_proposals_total"
                    class="rounded-xl border border-violet-100 bg-gradient-to-br from-violet-50/90 via-white to-fuchsia-50/70 p-5 shadow-md ring-1 ring-violet-100"
                >
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <h2 class="font-display text-lg font-bold text-slate-900">
                                Proposals inbox
                            </h2>
                            <p class="mt-1 text-xs font-semibold text-violet-900/90">
                                {{ client_proposals_total }} {{ client_proposals_total === 1 ? 'response' : 'responses' }} on this quest
                            </p>
                        </div>
                        <Link
                            v-if="client_proposals_hub_url"
                            :href="client_proposals_hub_url"
                            class="shrink-0 rounded-full bg-violet-700 px-3 py-1.5 text-[10px] font-black uppercase tracking-wide text-white shadow-sm hover:bg-violet-800"
                        >
                            All proposals
                        </Link>
                    </div>
                    <ul class="mt-4 space-y-2">
                        <li v-for="p in client_proposals" :key="p.id">
                            <article class="flex items-center gap-3 rounded-xl border border-white/80 bg-white/90 px-3 py-2.5 shadow-sm ring-1 ring-violet-100/80">
                                <Link :href="p.show_url" class="flex min-w-0 flex-1 items-center gap-3 transition hover:opacity-90">
                                    <UserProfileAvatar
                                        :href="p.freelancer?.slug ? route('freelancers.public', p.freelancer.slug) : null"
                                        :src="p.freelancer?.avatar_url"
                                        :name="proposalFreelancerName(p)"
                                        :alt="proposalFreelancerName(p)"
                                        frame-class="h-10 w-10 text-[10px]"
                                    />
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-bold text-slate-900">
                                            {{ proposalFreelancerName(p) }}
                                        </p>
                                        <div class="mt-0.5 flex flex-wrap items-center gap-2 text-[10px] font-black uppercase tracking-wide text-violet-900">
                                            <span>{{ p.status.replace(/_/g, ' ') }}</span>
                                            <span class="font-bold text-slate-600">{{ formatBudget(p.escrow_total_minor ?? p.quoted_amount_minor) }}</span>
                                            <span v-if="p.escrow_total_minor && p.escrow_total_minor !== p.quoted_amount_minor" class="block text-[10px] font-semibold text-slate-500">
                                                Quote {{ formatBudget(p.quoted_amount_minor) }}
                                            </span>
                                            <span
                                                v-if="p.clarification?.action_required"
                                                class="rounded-full bg-rose-100 px-2 py-0.5 text-[9px] font-black text-rose-800 ring-1 ring-rose-200"
                                            >
                                                Clarification
                                            </span>
                                            <span
                                                v-else-if="p.clarification?.message_count"
                                                class="rounded-full bg-sky-100 px-2 py-0.5 text-[9px] font-black text-sky-800 ring-1 ring-sky-200"
                                            >
                                                {{ p.clarification.message_count }} clarify msg{{ p.clarification.message_count === 1 ? '' : 's' }}
                                            </span>
                                        </div>
                                    </div>
                                </Link>
                                <div class="flex shrink-0 flex-col gap-1.5">
                                    <Link
                                        v-if="p.clarification?.clarify_url"
                                        :href="p.clarification.clarify_url"
                                        class="rounded-full px-3 py-1.5 text-center text-[10px] font-black uppercase tracking-wide text-white shadow-sm"
                                        :class="p.clarification.action_required ? 'bg-rose-600 hover:bg-rose-700' : 'bg-sky-600 hover:bg-sky-700'"
                                    >
                                        {{ p.clarification.action_required ? 'Open clarify' : 'Clarify' }}
                                    </Link>
                                    <Link
                                        :href="p.show_url"
                                        class="shrink-0 rounded-full bg-violet-700 px-3 py-1.5 text-center text-[10px] font-black uppercase tracking-wide text-white shadow-sm hover:bg-violet-800"
                                    >
                                        View
                                    </Link>
                                </div>
                            </article>
                        </li>
                    </ul>
                    <p v-if="client_proposals_total > 5 && client_proposals_hub_url" class="mt-3 text-center text-[11px] font-bold text-violet-900">
                        <Link :href="client_proposals_hub_url" class="underline decoration-violet-400 underline-offset-2 hover:text-violet-950">
                            View all {{ client_proposals_total }} proposals →
                        </Link>
                    </p>
                </section>

                <section v-if="isFreelancer" class="rounded-xl border border-slate-100 bg-gradient-to-br from-primary-50 via-white to-teal-50 p-5 shadow-md ring-1 ring-primary-100">
                    <h2 class="font-display text-lg font-bold text-slate-900">
                        Proposals
                    </h2>
                    <div v-if="hasSubmittedProposal" class="mt-4 rounded-xl border border-emerald-100 bg-white/90 p-4 text-sm font-semibold text-slate-800 ring-1 ring-emerald-50">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-xs font-black uppercase tracking-wide text-emerald-700">
                                Your proposal · {{ my_offer.status }}
                            </p>
                            <Link
                                v-if="my_offer.show_url"
                                :href="my_offer.show_url"
                                class="text-[11px] font-black uppercase tracking-wide text-primary-800 underline decoration-primary-300 underline-offset-2"
                            >
                                Open
                            </Link>
                        </div>
                        <p class="mt-2 line-clamp-4 text-sm">
                            {{ my_offer.pitch }}
                        </p>
                        <p v-if="my_offer.quoted_amount_minor" class="mt-2 text-xs font-bold text-slate-600">
                            Quoted {{ formatBudget(my_offer.quoted_amount_minor) }}
                        </p>
                    </div>
                    <template v-else>
                        <button
                            v-if="can_offer"
                            type="button"
                            class="mt-4 w-full rounded-full bg-primary-600 px-5 py-3 text-sm font-black text-white shadow-lg shadow-primary-900/20 hover:bg-primary-700"
                            @click="goToProposalComposer"
                        >
                            Build proposal
                        </button>
                        <div
                            v-else-if="proposalBlocker"
                            class="mt-4 rounded-xl border border-amber-200/90 bg-amber-50/90 px-4 py-3 text-xs font-semibold leading-relaxed text-amber-950 ring-1 ring-amber-100"
                        >
                            <p>{{ proposalBlocker.message }}</p>
                            <Link
                                v-if="proposalBlocker.action_url"
                                :href="proposalBlocker.action_url"
                                class="mt-2 inline-flex items-center gap-1 font-black uppercase tracking-wide text-amber-900 underline decoration-amber-400 underline-offset-2 hover:text-amber-950"
                            >
                                {{ proposalBlocker.action_label || 'Fix this' }}
                                <span aria-hidden="true">→</span>
                            </Link>
                        </div>
                        <Link
                            v-if="can_use_quest_messaging && messages_url"
                            :href="messages_url"
                            class="mt-3 flex w-full items-center justify-center gap-2 rounded-full border border-primary-200 bg-white px-5 py-2.5 text-xs font-black uppercase tracking-wide text-primary-900 shadow-sm hover:bg-primary-50"
                        >
                            <ChatBubbleLeftRightIcon class="h-4 w-4 shrink-0 text-primary-700" aria-hidden="true" />
                            Message client (in-app)
                        </Link>
                    </template>
                </section>

                <section
                    v-if="is_quest_owner && quest.is_listing_clock_active && quest.listing_expires_at"
                    class="rounded-xl border border-amber-100 bg-amber-50/70 p-5 shadow-sm ring-1 ring-amber-100"
                >
                    <h2 class="font-display text-lg font-bold text-amber-950">
                        Proposal deadline
                    </h2>
                    <p class="mt-2 text-sm font-semibold text-amber-900/90">
                        Accepting proposals until
                        <span class="font-black">{{ formatWhen(quest.listing_expires_at) }}</span>
                        (Lagos time).
                    </p>
                    <p v-if="Number(quest.listing_extension_count) > 0" class="mt-1 text-xs font-semibold text-amber-800/80">
                        Extended once{{ quest.listing_extended_at ? ` on ${formatWhen(quest.listing_extended_at)}` : '' }}.
                    </p>
                    <p class="mt-2 text-xs font-semibold leading-relaxed text-amber-900/80">
                        After award and escrow funding, the contract delivery deadline takes over — this listing clock no longer applies.
                    </p>
                    <button
                        v-if="quest.can_extend_listing && !showExtendForm"
                        type="button"
                        class="mt-4 w-full rounded-full border border-amber-300 bg-white px-4 py-2.5 text-xs font-black uppercase tracking-wide text-amber-950 hover:bg-amber-100"
                        @click="showExtendForm = true"
                    >
                        Extend once (up to {{ extendMaxDays }} days)
                    </button>
                    <form v-if="quest.can_extend_listing && showExtendForm" class="mt-4 space-y-3" @submit.prevent="submitExtend">
                        <div>
                            <InputLabel for="extend_days" value="Additional days" />
                            <input
                                id="extend_days"
                                v-model.number="extendForm.additional_days"
                                type="number"
                                :min="1"
                                :max="extendMaxDays"
                                required
                                class="mt-1 w-full rounded-xl border-amber-200 text-sm font-semibold shadow-sm"
                            />
                            <InputError class="mt-1" :message="extendForm.errors.additional_days" />
                        </div>
                        <div>
                            <InputLabel for="extend_reason" value="Reason (required, logged)" />
                            <textarea
                                id="extend_reason"
                                v-model="extendForm.reason"
                                rows="3"
                                required
                                minlength="10"
                                maxlength="2000"
                                class="mt-1 w-full rounded-xl border-amber-200 text-sm font-medium shadow-sm"
                                placeholder="Tell freelancers why you need more time…"
                            />
                            <InputError class="mt-1" :message="extendForm.errors.reason" />
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button
                                type="submit"
                                class="inline-flex items-center gap-2 rounded-full bg-amber-600 px-4 py-2 text-xs font-black text-white hover:bg-amber-700 disabled:opacity-60"
                                :disabled="extendForm.processing"
                            >
                                <ReLoader4Line v-if="extendForm.processing" class="h-4 w-4 animate-spin" aria-hidden="true" />
                                Confirm extension
                            </button>
                            <button
                                type="button"
                                class="rounded-full border border-amber-200 bg-white px-4 py-2 text-xs font-bold text-amber-900 hover:bg-amber-50"
                                @click="showExtendForm = false"
                            >
                                Cancel
                            </button>
                        </div>
                    </form>
                </section>

                <section v-if="can_manage_invites" class="rounded-xl border border-slate-100 bg-white p-5 shadow-md shadow-slate-900/5 ring-1 ring-slate-100">
                    <h2 class="font-display text-lg font-bold text-slate-900">
                        Know a pro for this job?
                    </h2>
                    <p class="mt-1 text-sm font-semibold leading-relaxed text-slate-600">
                        Tag a freelancer you’d like on this quest — we’ll email them and send an in-app alert with your brief. If they’re eligible, they can submit a proposal.
                    </p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <span
                            v-for="id in inviteIds"
                            :key="id"
                            class="inline-flex items-center gap-2 rounded-full bg-secondary-50 px-3 py-1 text-xs font-bold text-secondary-950 ring-1 ring-secondary-100"
                        >
                            {{ inviteLabel(id) }}
                            <button type="button" class="font-black text-secondary-700 hover:text-secondary-900" :disabled="inviteBusy" @click="removeInvite(id)">
                                ✕
                            </button>
                        </span>
                        <span v-if="!inviteIds.length" class="text-xs font-semibold text-slate-500">No invites yet — search below or pick from your recommended matches.</span>
                    </div>
                    <TextInput
                        v-model="inviteQuery"
                        type="search"
                        class="mt-4 w-full rounded-xl border-slate-200 text-sm font-semibold shadow-sm"
                        placeholder="Search by name or username…"
                        :disabled="inviteBusy"
                    />
                    <ul v-if="inviteHits.length" class="mt-2 max-h-40 overflow-auto rounded-xl border border-slate-100 bg-white shadow-md">
                        <li v-for="u in inviteHits" :key="u.id">
                            <button type="button" class="flex w-full items-center gap-3 px-3 py-2 text-left text-sm font-semibold hover:bg-primary-50 disabled:opacity-50" :disabled="inviteBusy" @click="addInvite(u)">
                                <UserProfileAvatar
                                    :href="u.slug ? route('freelancers.public', u.slug) : null"
                                    :src="u.avatar_url"
                                    :name="u.name"
                                    :alt="u.name"
                                    frame-class="h-9 w-9 text-[10px]"
                                />
                                <span class="min-w-0 flex-1 truncate">{{ u.name }}</span>
                                <span class="text-[10px] font-black uppercase text-primary-700">Invite</span>
                            </button>
                        </li>
                    </ul>
                    <p v-if="inviteBusy" class="mt-3 text-xs font-semibold text-primary-700">Sending invite…</p>
                    <p v-if="inviteError" class="mt-3 text-xs font-semibold text-rose-700">{{ inviteError }}</p>
                </section>

                <section
                    v-if="is_quest_owner && topFreelancersDisplay.length"
                    class="rounded-xl border border-slate-100 bg-white p-5 shadow-md shadow-slate-900/5 ring-1 ring-slate-100"
                >
                    <h2 class="font-display text-lg font-bold text-slate-900">
                        Recommended for this job
                    </h2>
                    <p class="mt-1 text-xs font-semibold text-slate-500">
                        Top {{ topFreelancersDisplay.length }} match{{ topFreelancersDisplay.length === 1 ? '' : 'es' }} for your brief.
                    </p>
                    <p
                        v-if="freelancer_match_stats?.label"
                        class="mt-2 text-xs font-semibold leading-relaxed text-slate-600"
                    >
                        {{ freelancer_match_stats.total }} freelancers match this category.
                        {{ freelancer_match_stats.label }}
                    </p>
                    <p v-if="can_manage_invites" class="mt-2 text-xs font-semibold text-slate-500">
                        See someone you want? Invite them — they’ll get your quest link straight away.
                    </p>
                    <ul class="mt-3 space-y-3">
                        <li v-for="f in topFreelancersDisplay" :key="f.id">
                            <div class="rounded-lg border border-slate-100 bg-slate-50/80 px-3 py-3 ring-1 ring-slate-100">
                                <div class="flex items-start justify-between gap-2">
                                    <Link
                                        :href="route('freelancers.public', f.slug)"
                                        class="flex min-w-0 flex-1 items-center gap-2 text-sm font-bold text-slate-900 hover:text-primary-800"
                                    >
                                        <UserProfileAvatar
                                            :href="route('freelancers.public', f.slug)"
                                            :src="f.avatar_url"
                                            :name="f.name"
                                            :alt="f.name"
                                            frame-class="h-9 w-9 text-[10px] ring-2 ring-white"
                                        />
                                        <span class="min-w-0">
                                            <span class="block truncate">{{ f.name }}</span>
                                            <span
                                                v-if="f.location"
                                                class="block truncate text-[10px] font-semibold text-slate-500"
                                            >
                                                {{ f.location }}
                                            </span>
                                        </span>
                                    </Link>
                                    <span class="shrink-0 text-right text-[10px] font-black text-primary-700">
                                        {{ f.match_score }}%
                                        <span
                                            v-if="f.match_quality?.label"
                                            class="mt-0.5 block font-semibold text-slate-600"
                                        >
                                            {{ f.match_quality.label }}
                                        </span>
                                    </span>
                                </div>
                                <p
                                    v-if="f.why_recommended"
                                    class="mt-2 text-xs font-semibold text-slate-600"
                                >
                                    {{ f.why_recommended }}
                                </p>
                                <div v-if="can_manage_invites" class="mt-3">
                                    <button
                                        v-if="isRecommendedTagged(f.id)"
                                        type="button"
                                        disabled
                                        class="w-full rounded-full border border-emerald-200 bg-emerald-50 py-2 text-[10px] font-black uppercase tracking-wide text-emerald-800"
                                    >
                                        Tagged
                                    </button>
                                    <button
                                        v-else
                                        type="button"
                                        class="w-full rounded-full bg-primary-600 py-2 text-[10px] font-black uppercase tracking-wide text-white shadow-sm hover:bg-primary-700 disabled:opacity-60"
                                        :disabled="recommendationTagBusy === f.id"
                                        @click="tagRecommendedFreelancer(f)"
                                    >
                                        {{ recommendationTagBusy === f.id ? 'Inviting…' : 'Invite' }}
                                    </button>
                                </div>
                            </div>
                        </li>
                    </ul>
                    <Link
                        v-if="recommendations_more_url && (freelancer_match_stats?.total > topFreelancersDisplay.length || topFreelancersDisplay.length >= 5)"
                        :href="recommendations_more_url"
                        class="mt-4 inline-flex w-full items-center justify-center rounded-full border border-primary-200 bg-primary-50 py-2.5 text-xs font-black uppercase tracking-wide text-primary-900 hover:bg-primary-100"
                    >
                        View more matches
                    </Link>
                </section>

                <ReportConcernSheet
                    v-if="canReportQuest"
                    :action-url="route('quests.reports.store', quest.route_key)"
                    subtitle="Spam, unsafe scope, misleading briefs, or harassment on this listing should be reported. We attach this quest automatically."
                    :context="{
                        type: 'quest',
                        quest_title: quest.title,
                        reference_code: quest.reference_code,
                    }"
                />

                <section v-if="can_edit" class="rounded-xl border border-rose-100 bg-rose-50/60 p-5 shadow-sm ring-1 ring-rose-100">
                    <h2 class="font-display text-lg font-bold text-rose-900">
                        Danger zone
                    </h2>
                    <p class="mt-2 text-xs font-semibold text-rose-800/90">
                        Deletes the quest and all uploaded files. Only available while the quest is unassigned and open or draft.
                    </p>
                    <button
                        type="button"
                        class="mt-4 w-full rounded-full bg-rose-600 py-3 text-sm font-black text-white shadow-md hover:bg-rose-700"
                        @click="openDeleteModal"
                    >
                        Delete quest
                    </button>
                </section>
            </aside>
        </div>

        <AppConfirmModal
            :show="deleteModalOpen"
            :busy="deleteBusy"
            title="Delete this quest?"
            description="This permanently removes the quest and all uploaded files. This cannot be undone."
            confirm-label="Delete quest"
            @cancel="closeDeleteModal"
            @confirm="performDelete"
        />

        <OperationsToastHost />
    </AppShell>
</template>

<script setup>
import QuestBoostUpsellPanel from '@/Components/Quests/QuestBoostUpsellPanel.vue';
import EscrowTransparencyTimeline from '@/Components/Quests/EscrowTransparencyTimeline.vue';
import DisputePreventionPrompts from '@/Components/Quests/DisputePreventionPrompts.vue';
import ReportConcernSheet from '@/Components/Quests/ReportConcernSheet.vue';
import PremiumDatePicker from '@/Components/Ui/PremiumDatePicker.vue';
import UiSelect from '@/Components/Ui/UiSelect.vue';
import QuestFileGallery from '@/Components/Quests/QuestFileGallery.vue';
import PartyInsightCard from '@/Components/Quests/PartyInsightCard.vue';
import QuestPreferenceFields from '@/Components/Quests/QuestPreferenceFields.vue';
import QuestRichDescriptionEditor from '@/Components/Quests/QuestRichDescriptionEditor.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import UserProfileAvatar from '@/Components/Ui/UserProfileAvatar.vue';
import ProposalClarificationInboxPanel from '@/Components/Quests/ProposalClarificationInboxPanel.vue';
import AppConfirmModal from '@/Components/AppConfirmModal.vue';
import AppShell from '@/Layouts/AppShell.vue';
import OperationsToastHost from '@/Pages/Operations/Components/OperationsToastHost.vue';
import { useOperationsToast } from '@/composables/useOperationsToast';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ReLoader4Line } from '@kalimahapps/vue-icons/re';
import { ChatBubbleLeftRightIcon } from '@heroicons/vue/24/outline';
import axios from 'axios';
import { completionScheduleSummary, questCompletionSchedule } from '@/utils/questCompletionSchedule';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    quest: { type: Object, required: true },
    is_quest_owner: { type: Boolean, default: false },
    can_edit: { type: Boolean, default: false },
    can_offer: { type: Boolean, default: false },
    has_submitted_proposal: { type: Boolean, default: false },
    category_match: { type: Boolean, default: false },
    verification_access: { type: Object, default: null },
    workspace: { type: Object, required: true },
    my_offer: { type: Object, default: null },
    is_bookmarked: { type: Boolean, default: false },
    similar_quests: { type: Array, default: () => [] },
    from_client_quests: { type: Array, default: () => [] },
    category_quests_other_areas: { type: Array, default: () => [] },
    top_freelancers: { type: Array, default: () => [] },
    freelancer_match_stats: { type: Object, default: () => ({}) },
    quest_preferences: { type: Array, default: () => [] },
    quest_has_specified_preferences: { type: Boolean, default: false },
    preference_profile: { type: Object, default: () => ({}) },
    preference_values: { type: Object, default: () => ({}) },
    can_edit_preferences: { type: Boolean, default: false },
    offers_count_display: { type: Number, default: 0 },
    sponsor_insight: { type: Object, default: () => ({}) },
    can_use_quest_messaging: { type: Boolean, default: false },
    messages_url: { type: String, default: null },
    quest_message_threads: { type: Array, default: () => [] },
    start_timing_options: { type: Array, default: () => [] },
    form_options: { type: Object, default: null },
    client_proposals: { type: Array, default: () => [] },
    client_proposals_total: { type: Number, default: 0 },
    client_proposals_hub_url: { type: String, default: null },
    recommendations_more_url: { type: String, default: null },
    can_manage_invites: { type: Boolean, default: false },
    clarification_alerts: { type: Array, default: () => [] },
    boost_upsell: { type: Object, default: null },
    show_boost_upsell_flash: { type: Boolean, default: false },
});

const page = usePage();
const { toast } = useOperationsToast();
const qualityGateIssues = computed(() => {
    const flash = page.props.flash?.quality_gate_issues;
    if (Array.isArray(flash) && flash.length) {
        return flash;
    }

    return Array.isArray(props.quest?.quality_gate_feedback) ? props.quest.quality_gate_feedback : [];
});
const isFreelancer = computed(() => page.props.auth?.user?.role?.slug === 'freelancer');
const isStaffRole = computed(() => ['admin', 'super_admin'].includes(page.props.auth?.user?.role?.slug ?? ''));
const canReportQuest = computed(() => Boolean(page.props.auth?.user) && !props.is_quest_owner && !isStaffRole.value);
const localBookmarked = ref(props.is_bookmarked);
const bookmarkBusy = ref(false);
const showExtendForm = ref(false);
const repostBusy = ref(false);
const deleteModalOpen = ref(false);
const deleteBusy = ref(false);

const extendMaxDays = computed(() => props.quest?.proposal_deadline_bounds?.extension_max ?? 14);

const extendForm = useForm({
    additional_days: extendMaxDays.value,
    reason: '',
});

const allQuestsHref = computed(() => {
    if (props.is_quest_owner) {
        return route('quests.index');
    }
    if (isFreelancer.value) {
        return route('quests.browse');
    }

    return route('quests.explore');
});

const clientProfileHref = computed(() => {
    const c = props.quest?.client;
    if (!c?.slug || c.role_slug !== 'freelancer') {
        return null;
    }

    return route('freelancers.public', c.slug);
});

const questRouteKey = computed(() => props.quest.slug || props.quest.uuid || props.quest.id);

const workspacePanelItems = computed(() => {
    const ws = props.workspace;
    if (!ws?.enabled) {
        return [];
    }
    const items = [];
    for (const b of ws.blockers || []) {
        if (b?.message) {
            items.push({
                message: b.message,
                action_label: b.action_label,
                action_url: b.action_url,
            });
        }
    }
    for (const h of ws.hints || []) {
        if (h?.message) {
            items.push({
                message: h.message,
                action_label: h.action_label,
                action_url: h.action_url,
            });
        }
    }

    return items.slice(0, 5);
});

const categoryMatch = computed(() => props.category_match === true);

const verificationLimitBlocker = computed(() => {
    const access = props.verification_access;
    if (!access || access.can_submit_for_budget !== false) {
        return null;
    }

    const limitLabel = access.proposal_limit_formatted || formatBudget(access.proposal_limit_minor);
    const level = access.current_level ?? access.effective_level;
    const missing = (access.missing_for_next_level || []).filter(Boolean).join(', ');
    let message = `This quest’s budget is above your current verification limit (L${level}, up to ${limitLabel}).`;

    if (access.limit_capped && (access.earned_proposal_limit_formatted || access.earned_proposal_limit_minor)) {
        message += ` Your earned tier allows up to ${access.earned_proposal_limit_formatted || formatBudget(access.earned_proposal_limit_minor)}; an admin custom cap applies.`;
    } else if (missing) {
        message += ` Complete ${missing} to unlock a higher limit.`;
    } else {
        message += ' Complete more verification to unlock this quest.';
    }

    return {
        message,
        action_url: access.verifications_url || route('verifications.index'),
        action_label: 'Open verifications',
    };
});

const hasSubmittedProposal = computed(
    () => props.has_submitted_proposal === true || Boolean(props.my_offer?.id),
);

const proposalBlocker = computed(() => {
    if (!isFreelancer.value || hasSubmittedProposal.value || props.can_offer) {
        return null;
    }

    if (!props.workspace?.can_submit_proposals) {
        const first = workspacePanelItems.value[0];

        return {
            message: first?.message
                || 'Complete your freelancer workspace checklist to unlock proposals.',
            action_url: first?.action_url || null,
            action_label: first?.action_label || 'Fix this',
        };
    }

    if (!categoryMatch.value) {
        return {
            message: 'Add this quest’s subcategory to your profile so we know you are qualified for this brief.',
            action_url: route('account.show', { tab: 'overview' }) + '#account-work-categories',
            action_label: 'Update work categories',
        };
    }

    if (verificationLimitBlocker.value) {
        return verificationLimitBlocker.value;
    }

    return {
        message: 'You are not eligible to propose on this quest yet.',
        action_url: null,
        action_label: null,
    };
});

const showSponsorInsightCard = computed(
    () => !props.is_quest_owner && Boolean(props.sponsor_insight?.name),
);

const messageThreadCount = computed(() => props.quest_message_threads.length);

const selectedMessageThreadSlug = ref('');

const showMessageThreadList = computed(() => messageThreadCount.value > 0 && messageThreadCount.value <= 3);

const showMessageThreadPicker = computed(() => messageThreadCount.value > 3);

const topFreelancersDisplay = computed(() => props.top_freelancers.slice(0, 5));

const messageThreadOptions = computed(() =>
    props.quest_message_threads.map((t) => ({
        value: t.slug,
        label: `${t.first_name || t.name}${t.slug ? '' : ''}`,
    })),
);

const selectedMessageThread = computed(
    () => props.quest_message_threads.find((t) => t.slug === selectedMessageThreadSlug.value) ?? null,
);

watch(
    () => props.quest_message_threads,
    (threads) => {
        if (!threads?.length) {
            selectedMessageThreadSlug.value = '';

            return;
        }
        if (!threads.some((t) => t.slug === selectedMessageThreadSlug.value)) {
            selectedMessageThreadSlug.value = threads[0].slug;
        }
    },
    { immediate: true },
);

const completionSchedule = computed(() => questCompletionSchedule(props.quest));

const questListingMeta = computed(() => {
    const lines = [];
    if (props.quest.created_at) {
        lines.push(`Posted ${formatWhen(props.quest.created_at)}`);
    }
    if (props.quest.updated_at && props.quest.updated_at !== props.quest.created_at) {
        lines.push(`Updated ${formatWhen(props.quest.updated_at)}`);
    }
    const offers = props.offers_count_display ?? props.quest.offers_count ?? 0;
    lines.push(`${offers} ${offers === 1 ? 'proposal' : 'proposals'} received`);
    const scheduleSummary = completionScheduleSummary(completionSchedule.value);
    if (scheduleSummary) {
        lines.push(scheduleSummary);
    } else {
        lines.push('No client finish date specified');
    }

    return lines;
});

const viewerInsightLines = computed(() => {
    const lines = [];
    if (props.is_quest_owner) {
        lines.push('You are viewing this listing as the client — a clear brief attracts stronger proposals.');
        if (props.quest.status === 'open' && !props.quest.is_client_edit_locked) {
            lines.push('You can still adjust title, description, and budget until the editing window closes.');
        }
    } else if (isFreelancer.value) {
        if (props.my_offer) {
            lines.push('You already submitted a proposal — follow up in thread if the client has questions.');
        } else if (props.can_offer) {
            lines.push('You can send a proposal on this quest — highlight outcomes, timeline, and how you de-risk delivery.');
        } else if (proposalBlocker.value) {
            lines.push(proposalBlocker.value.message);
        }
        if (localBookmarked.value) {
            lines.push('You saved this quest for later — it stays in your saved list.');
        }
    }

    return lines.slice(0, 4);
});

const uploadForm = useForm({ file: null });
const csrfToken = computed(() => {
    const fromPage = page.props?.csrf_token;
    if (fromPage) {
        return fromPage;
    }
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
});
const showEditQuestForm = ref(false);
const showEditPreferencesForm = ref(false);
const offersCountDisplay = computed(() => props.offers_count_display ?? props.quest?.offers_count ?? 0);

const preferencesEditForm = useForm({
    preferences: { ...props.preference_values },
});

function openEditPreferences() {
    preferencesEditForm.preferences = { ...props.preference_values };
    preferencesEditForm.clearErrors();
    showEditPreferencesForm.value = true;
}

function closeEditPreferences() {
    showEditPreferencesForm.value = false;
    preferencesEditForm.clearErrors();
}

function submitPreferencesEdit() {
    preferencesEditForm.patch(route('quests.preferences.update', questRouteKey.value), {
        preserveScroll: true,
        onSuccess: () => {
            showEditPreferencesForm.value = false;
            router.reload({ only: ['quest_preferences', 'quest_has_specified_preferences', 'preference_values', 'quest'], preserveScroll: true });
        },
    });
}

watch(
    () => props.preference_values,
    (v) => {
        if (!showEditPreferencesForm.value) {
            preferencesEditForm.preferences = { ...(v || {}) };
        }
    },
    { deep: true },
);

const inviteQuery = ref('');
const inviteHits = ref([]);
const inviteIds = ref([]);
const inviteLabels = ref({});
const recommendationTagBusy = ref(null);
const inviteBusy = ref(false);
const inviteError = ref('');
let inviteTimer = null;
let proposalPollTimer = null;
let proposalEchoChannel = null;

const editForm = useForm({
    title: props.quest.title,
    description: props.quest.description,
    quest_category_id: props.quest.quest_category_id,
    state_id: props.quest.state_id,
    local_government_id: props.quest.local_government_id,
    city: props.quest.city,
    budget_amount_minor: props.quest.budget_minor,
    start_timing: props.quest.start_timing,
    scheduled_start_date: props.quest.scheduled_start_date || '',
    estimated_completion_days: props.quest.estimated_completion_days,
    site_visits_allowed: !!props.quest.site_visits_allowed,
});

const editParentId = ref(findParentForLeaf(props.quest.quest_category_id));
const editBudgetNgn = ref(Math.round(props.quest.budget_minor / 100));

watch(
    () => props.quest,
    (q) => {
        inviteIds.value = (q.invited || []).map((u) => Number(u.id));
        inviteLabels.value = Object.fromEntries((q.invited || []).map((u) => [u.id, u.name]));
        applyQuestToEditForm(q);
    },
    { deep: true, immediate: true },
);

watch(
    () => editForm.state_id,
    (newId, oldId) => {
        if (oldId === undefined) {
            return;
        }
        if (Number(newId) !== Number(oldId)) {
            editForm.local_government_id = 0;
        }
    },
);

watch(
    () => editForm.errors,
    (errs) => {
        if (errs && Object.keys(errs).length > 0) {
            showEditQuestForm.value = true;
        }
    },
    { deep: true },
);

watch(editBudgetNgn, (n) => {
    editForm.budget_amount_minor = Math.max(10000, Math.round(Number(n) || 0) * 100);
});

onMounted(() => {
    const path = page.url || '';
    if (/\bedit=1\b/.test(path) && props.can_edit) {
        showEditQuestForm.value = true;
        nextTick(() => {
            document.getElementById('edit-listing-panel')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }

    if (props.is_quest_owner && props.quest.status === 'open') {
        const qid = Number(props.quest.id);
        if (qid > 0) {
            const reloadProposals = () => {
                router.reload({
                    only: ['client_proposals', 'client_proposals_total', 'quest', 'clarification_alerts', 'my_offer'],
                    preserveScroll: true,
                });
            };
            if (window.Echo) {
                proposalEchoChannel = window.Echo.private(`quests.${qid}.client`).listen('.proposals.updated', reloadProposals);
            } else {
                proposalPollTimer = window.setInterval(reloadProposals, 15000);
            }
        }
    }
});

onBeforeUnmount(() => {
    if (proposalPollTimer) {
        window.clearInterval(proposalPollTimer);
    }
    if (proposalEchoChannel && window.Echo) {
        const qid = Number(props.quest.id);
        if (qid > 0) {
            window.Echo.leave(`quests.${qid}.client`);
        }
    }
});

const editLeafOptions = computed(() => {
    const p = props.form_options?.category_tree?.find((c) => c.id === editParentId.value);

    return p?.children ?? [];
});

const editLgas = computed(() => {
    const s = props.form_options?.locations?.find((x) => x.id === editForm.state_id);

    return s?.local_governments ?? [];
});

const editParentCategoryOptions = computed(() =>
    (props.form_options?.category_tree ?? []).map((p) => ({
        value: p.id,
        label: p.name,
    })),
);

const editLeafUiOptions = computed(() =>
    editLeafOptions.value.map((c) => ({
        value: c.id,
        label: c.name,
    })),
);

const editStateOptions = computed(() =>
    (props.form_options?.locations ?? []).map((s) => ({
        value: s.id,
        label: s.name,
    })),
);

const editLgaUiOptions = computed(() =>
    editLgas.value.map((lg) => ({
        value: lg.id,
        label: lg.name,
    })),
);

watch(inviteQuery, (q) => {
    window.clearTimeout(inviteTimer);
    if (!props.can_manage_invites || !q || q.trim().length < 2) {
        inviteHits.value = [];

        return;
    }
    inviteTimer = window.setTimeout(async () => {
        try {
            const { data } = await axios.get(route('quests.freelancers.search', questRouteKey.value), { params: { q: q.trim() } });
            inviteHits.value = (data.users || []).filter((u) => !inviteIds.value.includes(Number(u.id)));
        } catch {
            inviteHits.value = [];
        }
    }, 280);
});

function applyQuestToEditForm(q) {
    editForm.title = q.title;
    editForm.description = q.description;
    editForm.quest_category_id = q.quest_category_id;
    editForm.state_id = q.state_id;
    editForm.local_government_id = q.local_government_id;
    editForm.city = q.city;
    editForm.budget_amount_minor = q.budget_minor;
    editForm.start_timing = q.start_timing;
    editForm.scheduled_start_date = q.scheduled_start_date || '';
    editForm.estimated_completion_days = q.estimated_completion_days;
    editForm.site_visits_allowed = !!q.site_visits_allowed;
    editParentId.value = findParentForLeaf(q.quest_category_id);
    editBudgetNgn.value = Math.round(q.budget_minor / 100);
}

function closeEditQuestForm() {
    showEditQuestForm.value = false;
    editForm.clearErrors();
    applyQuestToEditForm(props.quest);
}

function findParentForLeaf(leafId) {
    for (const p of props.form_options?.category_tree || []) {
        if ((p.children || []).some((c) => c.id === leafId)) {
            return p.id;
        }
    }

    const first = props.form_options?.category_tree?.[0];

    return first?.id ?? 0;
}

function setEditParent(v) {
    editParentId.value = v;
    onEditParent();
}

function onEditParent() {
    const first = editLeafOptions.value[0];
    editForm.quest_category_id = first?.id ?? editForm.quest_category_id;
}

function initials(name) {
    const p = (name || 'H').trim().split(/\s+/);

    return ((p[0]?.[0] || 'H') + (p[1]?.[0] || '')).toUpperCase();
}

function statusPillHero(s) {
    if (s === 'open') {
        return 'bg-emerald-500/35 text-white ring-emerald-100/40';
    }
    if (s === 'draft') {
        return 'bg-amber-400/40 text-white ring-amber-100/35';
    }
    if (s === 'closed_unawarded') {
        return 'bg-slate-400/50 text-white ring-slate-100/35';
    }

    return 'bg-white/15 text-white ring-white/25';
}

function questStatusLabel(s) {
    const map = {
        open: 'Open',
        draft: 'Draft',
        closed_unawarded: 'Closed — unawarded',
        assigned: 'Assigned',
        in_progress: 'In progress',
        pending_review: 'Pending review',
        completed: 'Completed',
    };

    return map[s] || String(s || '').replace(/_/g, ' ');
}

function submitExtend() {
    extendForm.post(route('quests.extend-listing', questRouteKey.value), {
        preserveScroll: true,
        onSuccess: () => {
            showExtendForm.value = false;
            extendForm.reset('reason');
        },
    });
}

function submitRepost() {
    if (repostBusy.value) {
        return;
    }
    repostBusy.value = true;
    router.post(route('quests.repost', questRouteKey.value), {}, {
        onFinish: () => {
            repostBusy.value = false;
        },
    });
}

function timingLabel(v) {
    const o = props.start_timing_options.find((x) => x.value === v);

    return o?.label || v;
}

function visibilityLabel(v) {
    const map = {
        public: 'Public listing',
        invite_only: 'Invite-only',
        private: 'Private',
    };

    return map[v] || v;
}

function projectTypeLabel(v) {
    const map = { fixed_price: 'Fixed price', hourly: 'Hourly' };

    return map[v] || v;
}

function teamSizeLabel(v) {
    const map = {
        solo: 'Solo freelancer',
        small_team: 'Small team (2–5)',
        flexible_crew: 'Pro decides crew size',
        client_assists: 'Client or family will assist',
        freelancer_arranges: 'Freelancer arranges extra help',
    };

    return map[v] || v;
}

function availabilityLabel(v) {
    const map = {
        full_time: 'Full-time cadence',
        part_time: 'Part-time',
        as_needed: 'As-needed / flexible',
    };

    return map[v] || v;
}

function locationPrefLabel(v) {
    const map = { remote_friendly: 'Remote-friendly', local_only: 'Local only' };

    return map[v] || v;
}

async function toggleBookmark() {
    if (bookmarkBusy.value) {
        return;
    }

    bookmarkBusy.value = true;
    const previous = localBookmarked.value;
    localBookmarked.value = !previous;

    try {
        const method = previous ? 'delete' : 'post';
        const url = previous
            ? route('quests.bookmark.destroy', questRouteKey.value)
            : route('quests.bookmark.store', questRouteKey.value);
        const { data } = await axios.request({
            method,
            url,
            headers: { Accept: 'application/json' },
        });

        localBookmarked.value = Boolean(data.bookmarked);
    } catch (error) {
        localBookmarked.value = previous;
        window.alert(error.response?.data?.message || 'We could not update this saved quest right now. Please try again.');
    } finally {
        bookmarkBusy.value = false;
    }
}

function proposalFreelancerName(proposal) {
    const f = proposal?.freelancer;
    if (!f) {
        return 'Freelancer';
    }
    const full = String(f.name || '').trim();
    if (full) {
        return full;
    }
    const parts = [f.first_name, f.last_name].map((x) => String(x || '').trim()).filter(Boolean);

    return parts.length ? parts.join(' ') : 'Freelancer';
}

function formatBudget(minor) {
    const n = Number(minor) / 100;

    return `₦${n.toLocaleString('en-NG', { maximumFractionDigits: 0 })}`;
}

function formatWhen(iso) {
    if (!iso) {
        return '';
    }
    try {
        return new Date(iso).toLocaleString('en-NG', {
            dateStyle: 'medium',
            timeStyle: 'short',
            timeZone: 'Africa/Lagos',
        });
    } catch {
        return iso;
    }
}

function removeFile(id) {
    if (!window.confirm('Remove this file?')) {
        return;
    }
    router.delete(route('quests.files.destroy', [questRouteKey.value, id]), { preserveScroll: true });
}

function uploadFile(e) {
    const f = e.target.files?.[0];
    e.target.value = '';
    if (!f) {
        return;
    }
    uploadForm.file = f;
    uploadForm.post(route('quests.files.store', questRouteKey.value), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => uploadForm.reset('file'),
    });
}

function submitEdit() {
    editForm.transform((data) => ({
        ...data,
        site_visits_allowed: !!data.site_visits_allowed,
    })).patch(route('quests.update', questRouteKey.value), {
        preserveScroll: true,
        onSuccess: () => {
            showEditQuestForm.value = false;
        },
    });
}

function inviteLabel(id) {
    return inviteLabels.value[id] || `#${id}`;
}

function addInvite(u) {
    const id = Number(u.id);
    if (!id || inviteIds.value.includes(id) || inviteBusy.value) {
        return;
    }

    persistInvites([...inviteIds.value, id], {
        onSuccess: () => {
            inviteLabels.value = { ...inviteLabels.value, [id]: u.name };
            inviteHits.value = inviteHits.value.filter((x) => Number(x.id) !== id);
            inviteQuery.value = '';
        },
    });
}

function removeInvite(id) {
    if (inviteBusy.value) {
        return;
    }

    persistInvites(inviteIds.value.filter((x) => Number(x) !== Number(id)));
}

function persistInvites(nextIds, { onSuccess } = {}) {
    if (!props.can_manage_invites) {
        return;
    }

    inviteError.value = '';
    inviteBusy.value = true;
    router.post(
        route('quests.invites.store', questRouteKey.value),
        { freelancer_ids: nextIds.map((id) => Number(id)) },
        {
            preserveScroll: true,
            onSuccess: () => {
                inviteIds.value = nextIds.map((id) => Number(id));
                onSuccess?.();
            },
            onError: (errors) => {
                const message = errors.freelancer_ids ?? errors.message;
                inviteError.value = Array.isArray(message) ? message[0] : (message || 'Could not send invite. Please try again.');
            },
            onFinish: () => {
                inviteBusy.value = false;
                recommendationTagBusy.value = null;
            },
        },
    );
}

function isRecommendedTagged(id) {
    return inviteIds.value.includes(Number(id));
}

function tagRecommendedFreelancer(f) {
    if (!props.can_manage_invites || isRecommendedTagged(f.id) || inviteBusy.value) {
        return;
    }

    const id = Number(f.id);
    recommendationTagBusy.value = id;
    persistInvites([...inviteIds.value, id], {
        onSuccess: () => {
            inviteLabels.value = { ...inviteLabels.value, [id]: f.name };
        },
    });
}

function syncInvites() {
    persistInvites(inviteIds.value);
}

function goToProposalComposer() {
    if (props.my_offer?.show_url) {
        router.visit(props.my_offer.show_url);

        return;
    }

    router.visit(route('quests.proposals.create', questRouteKey.value));
}

function openDeleteModal() {
    deleteModalOpen.value = true;
}

function closeDeleteModal() {
    if (deleteBusy.value) {
        return;
    }

    deleteModalOpen.value = false;
}

function performDelete() {
    deleteBusy.value = true;
    router.delete(route('quests.destroy', questRouteKey.value), {
        onSuccess: () => {
            toast('Quest removed.');
        },
        onError: () => {
            toast('Could not delete this quest. Try again or contact support.', 'error');
        },
        onFinish: () => {
            deleteBusy.value = false;
            deleteModalOpen.value = false;
        },
    });
}
</script>
