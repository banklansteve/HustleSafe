<template>
    <AppShell>
        <Head title="Account · HustleSafe" />

        <div class="mx-auto w-full max-w-4xl space-y-8">
            <div
                v-if="mustVerifyEmail"
                class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm font-semibold text-amber-950 ring-1 ring-amber-100"
            >
                Please verify your email to unlock the full marketplace. Check your inbox for the verification link.
            </div>

            <!-- Hero identity -->
            <section
                class="relative overflow-hidden rounded-[1.75rem] border border-slate-200/90 bg-gradient-to-br from-white via-slate-50 to-primary-50/40 p-6 shadow-sm ring-1 ring-slate-100 sm:p-8"
            >
                <div
                    class="pointer-events-none absolute -right-20 top-0 h-48 w-48 rounded-full bg-primary-200/30 blur-3xl"
                    aria-hidden="true"
                />
                <div class="relative flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                    <div class="flex flex-col gap-5 sm:flex-row sm:items-start">
                        <div class="relative shrink-0">
                            <UserProfileAvatar
                                :href="user.role_slug === 'freelancer' && user.slug ? route('freelancers.public', user.slug) : null"
                                :src="user.avatar_url"
                                :name="user.name"
                                :alt="user.name"
                                frame-class="h-24 w-24 border-2 border-white text-2xl shadow-lg ring-2 ring-slate-100 sm:h-28 sm:w-28 sm:text-3xl"
                            />
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-bold uppercase tracking-[0.2em] text-primary-700">
                                Your account
                            </p>
                            <h1 class="font-display mt-2 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">
                                {{ user.first_name || user.name }}
                            </h1>
                            <p
                                class="mt-4 w-full max-w-xl rounded-2xl border border-primary-200/80 bg-gradient-to-r from-primary-700 to-teal-700 px-4 py-3 text-center text-sm font-black leading-snug tracking-tight text-white shadow-md shadow-primary-900/20 ring-1 ring-white/20 sm:px-5 sm:text-base"
                            >
                                {{ roleBannerLabel }}
                            </p>
                            <div class="mt-3 flex flex-wrap gap-2 text-sm font-semibold text-slate-600">
                                <span v-if="user.username" class="rounded-full bg-white px-3 py-1 ring-1 ring-slate-200/80">
                                    @{{ user.username }}
                                </span>
                                <span v-if="user.slug" class="rounded-full bg-white px-3 py-1 ring-1 ring-slate-200/80">
                                    /{{ user.slug }}
                                </span>
                                <span v-if="joinedLabel" class="rounded-full bg-white px-3 py-1 ring-1 ring-slate-200/80">
                                    Joined {{ joinedLabel }}
                                </span>
                            </div>
                            <p
                                v-if="user.role_slug === 'freelancer' && (publicReviewsUrl || publicPortfoliosUrl)"
                                class="mt-4 flex flex-wrap gap-x-4 gap-y-2 text-sm font-semibold"
                            >
                                <Link
                                    v-if="publicReviewsUrl"
                                    :href="publicReviewsUrl"
                                    class="text-primary-700 underline-offset-4 hover:underline"
                                >
                                    Open public reviews list
                                </Link>
                                <Link
                                    v-if="publicPortfoliosUrl"
                                    :href="publicPortfoliosUrl"
                                    class="text-primary-700 underline-offset-4 hover:underline"
                                >
                                    Open public portfolio list
                                </Link>
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Tabs -->
            <nav
                class="flex gap-2 overflow-x-auto pb-1 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                aria-label="Account sections"
            >
                <button
                    v-for="t in tabs"
                    :key="t.id"
                    type="button"
                    class="shrink-0 rounded-2xl px-4 py-2.5 text-sm font-bold transition"
                    :class="
                        localTab === t.id
                            ? 'bg-primary-700 text-white shadow-md'
                            : 'border border-slate-200 bg-white text-slate-700 hover:border-primary-200 hover:bg-primary-50'
                    "
                    role="tab"
                    :aria-selected="localTab === t.id"
                    @click="setTab(t.id)"
                >
                    <span class="inline-flex items-center gap-2">
                        <component :is="t.icon" class="h-4 w-4" aria-hidden="true" />
                        {{ t.label }}
                    </span>
                </button>
            </nav>

            <!-- Overview -->
            <div v-show="localTab === 'overview'" class="space-y-8">
                <section
                    class="rounded-[1.75rem] border border-slate-100 bg-white p-6 shadow-sm ring-1 ring-slate-100 sm:p-8"
                >
                    <div class="flex flex-col items-center gap-6">
                        <div class="shrink-0">
                            <TrustHalfDonut
                                :score="primaryTrustScore"
                                :label="primaryTrustLabel"
                                :variant="user.role_slug === 'freelancer' ? 'freelancer' : 'client'"
                                :animate-on-mount="true"
                                compact
                            />
                        </div>
                        <div class="max-w-lg text-center">
                            <h2 class="font-display text-lg font-bold text-slate-900">
                                Trust snapshot
                            </h2>
                            <p class="mt-2 text-sm font-medium leading-relaxed text-slate-600">
                                Your score updates as you complete quests, collect reviews, and fill out your profile. Strong trust helps you win work across Nigeria and beyond.
                            </p>
                        </div>
                        <div
                            class="grid w-full grid-cols-2 gap-3 sm:grid-cols-3 lg:max-w-2xl lg:grid-cols-3"
                        >
                            <div
                                v-for="tile in statTiles"
                                :key="tile.label"
                                class="rounded-2xl border border-slate-100 bg-slate-50/80 p-4 shadow-sm ring-1 ring-slate-100/80"
                            >
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                                    {{ tile.label }}
                                </p>
                                <p class="mt-1 font-display text-lg font-black tabular-nums text-slate-900 sm:text-xl">
                                    {{ tile.value }}
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <div class="space-y-6">
                    <div
                        id="account-profile-story"
                        class="rounded-[1.75rem] border border-slate-100 bg-white p-6 shadow-sm ring-1 ring-slate-100 sm:p-8"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h2 class="font-display text-lg font-bold text-slate-900">
                                    Profile story
                                </h2>
                                <p class="mt-1 text-sm font-medium text-slate-600">
                                    {{ storySectionBlurb }}
                                </p>
                            </div>
                            <button
                                type="button"
                                class="rounded-full p-2 text-slate-400 ring-1 ring-slate-200/80 transition hover:bg-primary-50 hover:text-primary-800"
                                :aria-pressed="editSection === 'story'"
                                :aria-label="editSection === 'story' ? 'Close editor' : 'Edit profile story'"
                                @click="toggleSection('story')"
                            >
                                <PencilSquareIcon v-if="editSection !== 'story'" class="h-6 w-6" aria-hidden="true" />
                                <XMarkIcon v-else class="h-6 w-6" aria-hidden="true" />
                            </button>
                        </div>
                        <div class="mt-6 min-h-[12rem]">
                            <div v-if="editSection !== 'story'" class="space-y-4 text-sm font-semibold text-slate-800">
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-wide text-slate-500">Headline</p>
                                    <p class="mt-1 font-bold text-slate-900">
                                        {{ user.headline || '—' }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-wide text-slate-500">Bio</p>
                                    <p class="mt-1 whitespace-pre-wrap leading-relaxed text-slate-700">
                                        {{ user.bio || '—' }}
                                    </p>
                                </div>
                            </div>
                            <form v-else class="space-y-5" @submit.prevent="submitDetails">
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Headline</label>
                                    <input
                                        v-model="detailsForm.headline"
                                        type="text"
                                        class="mt-1 w-full rounded-xl border-slate-200 text-sm font-medium shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                    />
                                    <InputError class="mt-1" :message="detailsForm.errors.headline" />
                                </div>
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Bio</label>
                                    <textarea
                                        v-model="detailsForm.bio"
                                        rows="5"
                                        class="mt-1 w-full rounded-xl border-slate-200 text-sm font-medium shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                    />
                                    <InputError class="mt-1" :message="detailsForm.errors.bio" />
                                </div>
                                <div class="flex justify-end">
                                    <button
                                        type="submit"
                                        class="rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-bold text-white shadow-md hover:bg-primary-700 disabled:opacity-50"
                                        :disabled="detailsForm.processing"
                                    >
                                        Save story
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div v-if="user.role_slug === 'freelancer'" class="space-y-6">
                        <div
                            id="account-professional-details"
                            class="rounded-[1.75rem] border border-slate-100 bg-white p-6 shadow-sm ring-1 ring-slate-100 sm:p-8"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h2 class="font-display text-lg font-bold text-slate-900">
                                        Professional details
                                    </h2>
                                    <p class="mt-1 text-sm font-medium text-slate-600">
                                        Rates and experience inform matching and your public profile.
                                    </p>
                                </div>
                                <div class="flex shrink-0 items-center gap-2">
                                    <BriefcaseIcon class="hidden h-6 w-6 text-slate-300 sm:block" aria-hidden="true" />
                                    <button
                                        type="button"
                                        class="rounded-full p-2 text-slate-400 ring-1 ring-slate-200/80 transition hover:bg-primary-50 hover:text-primary-800"
                                        :aria-pressed="editSection === 'professional'"
                                        :aria-label="editSection === 'professional' ? 'Close editor' : 'Edit professional details'"
                                        @click="toggleSection('professional')"
                                    >
                                        <PencilSquareIcon v-if="editSection !== 'professional'" class="h-6 w-6" aria-hidden="true" />
                                        <XMarkIcon v-else class="h-6 w-6" aria-hidden="true" />
                                    </button>
                                </div>
                            </div>
                            <div class="mt-6 min-h-[22rem]">
                                <dl
                                    v-if="editSection !== 'professional'"
                                    class="grid gap-4 text-sm font-semibold text-slate-800 sm:grid-cols-2"
                                >
                                    <div class="sm:col-span-2">
                                        <dt class="text-[10px] font-black uppercase tracking-wide text-slate-500">Display name</dt>
                                        <dd class="mt-1 font-bold text-slate-900">{{ user.name || '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-[10px] font-black uppercase tracking-wide text-slate-500">First name</dt>
                                        <dd class="mt-1">{{ user.first_name || '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-[10px] font-black uppercase tracking-wide text-slate-500">Last name</dt>
                                        <dd class="mt-1">{{ user.last_name || '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-[10px] font-black uppercase tracking-wide text-slate-500">Profession</dt>
                                        <dd class="mt-1">{{ user.profession || '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-[10px] font-black uppercase tracking-wide text-slate-500">Job title</dt>
                                        <dd class="mt-1">{{ user.job_title || '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-[10px] font-black uppercase tracking-wide text-slate-500">Years experience</dt>
                                        <dd class="mt-1">{{ user.years_experience ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-[10px] font-black uppercase tracking-wide text-slate-500">Hourly min (₦)</dt>
                                        <dd class="mt-1">{{ user.hourly_rate_min ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-[10px] font-black uppercase tracking-wide text-slate-500">Hourly max (₦)</dt>
                                        <dd class="mt-1">{{ user.hourly_rate_max ?? '—' }}</dd>
                                    </div>
                                </dl>
                                <form v-else class="grid gap-5 sm:grid-cols-2" @submit.prevent="submitDetails">
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Display name</label>
                                    <input
                                        v-model="detailsForm.name"
                                        type="text"
                                        class="mt-1 w-full rounded-xl border-slate-200 text-sm font-medium shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                    />
                                    <InputError class="mt-1" :message="detailsForm.errors.name" />
                                </div>
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">First name</label>
                                    <input
                                        v-model="detailsForm.first_name"
                                        type="text"
                                        class="mt-1 w-full rounded-xl border-slate-200 text-sm font-medium shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                    />
                                    <InputError class="mt-1" :message="detailsForm.errors.first_name" />
                                </div>
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Last name</label>
                                    <input
                                        v-model="detailsForm.last_name"
                                        type="text"
                                        class="mt-1 w-full rounded-xl border-slate-200 text-sm font-medium shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                    />
                                    <InputError class="mt-1" :message="detailsForm.errors.last_name" />
                                </div>
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Profession</label>
                                    <input
                                        v-model="detailsForm.profession"
                                        type="text"
                                        class="mt-1 w-full rounded-xl border-slate-200 text-sm font-medium shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                    />
                                    <InputError class="mt-1" :message="detailsForm.errors.profession" />
                                </div>
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Job title</label>
                                    <input
                                        v-model="detailsForm.job_title"
                                        type="text"
                                        class="mt-1 w-full rounded-xl border-slate-200 text-sm font-medium shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                    />
                                    <InputError class="mt-1" :message="detailsForm.errors.job_title" />
                                </div>
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Years experience</label>
                                    <input
                                        v-model="detailsForm.years_experience"
                                        type="number"
                                        min="0"
                                        class="mt-1 w-full rounded-xl border-slate-200 text-sm font-medium shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                    />
                                    <InputError class="mt-1" :message="detailsForm.errors.years_experience" />
                                </div>
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Hourly min (₦)</label>
                                    <input
                                        v-model="detailsForm.hourly_rate_min"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="mt-1 w-full rounded-xl border-slate-200 text-sm font-medium shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                    />
                                    <InputError class="mt-1" :message="detailsForm.errors.hourly_rate_min" />
                                </div>
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Hourly max (₦)</label>
                                    <input
                                        v-model="detailsForm.hourly_rate_max"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="mt-1 w-full rounded-xl border-slate-200 text-sm font-medium shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                    />
                                    <InputError class="mt-1" :message="detailsForm.errors.hourly_rate_max" />
                                </div>
                                <div class="flex items-end sm:col-span-2">
                                    <button
                                        type="submit"
                                        class="w-full rounded-full bg-primary-600 px-6 py-2.5 text-sm font-bold text-white shadow-md shadow-primary-900/15 hover:bg-primary-700 disabled:opacity-50 sm:w-auto"
                                        :disabled="detailsForm.processing"
                                    >
                                        Save professional details
                                    </button>
                                </div>
                        </form>
                            </div>
                        </div>
                        <div
                            v-if="questCategoryTree.length"
                            id="account-work-categories"
                            class="rounded-[1.75rem] border border-slate-100 bg-white p-6 shadow-sm ring-1 ring-slate-100 sm:p-8"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h3 class="font-display text-lg font-bold text-slate-900">
                                        Work subcategories
                                    </h3>
                                    <p class="mt-1 text-sm font-medium text-slate-600">
                                        Domain → subcategory selections used to match you on open quests. Pick every leaf category you want offers for.
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    class="rounded-full p-2 text-slate-400 ring-1 ring-slate-200/80 transition hover:bg-primary-50 hover:text-primary-800"
                                    :aria-pressed="editSection === 'categories'"
                                    :aria-label="editSection === 'categories' ? 'Close editor' : 'Edit work subcategories'"
                                    @click="toggleSection('categories')"
                                >
                                    <PencilSquareIcon v-if="editSection !== 'categories'" class="h-6 w-6" aria-hidden="true" />
                                    <XMarkIcon v-else class="h-6 w-6" aria-hidden="true" />
                                </button>
                            </div>
                            <div class="mt-6 min-h-[10rem]">
                                <div v-if="editSection !== 'categories'" class="flex flex-wrap gap-2">
                                    <span
                                        v-for="c in categories"
                                        :key="c.id"
                                        class="rounded-full border border-primary-100 bg-primary-50 px-3 py-1.5 text-xs font-bold text-primary-900 ring-1 ring-primary-100/80"
                                    >
                                        {{ c.name }}
                                    </span>
                                    <span v-if="!categories.length" class="text-sm font-semibold text-slate-500">No subcategories selected yet.</span>
                                </div>
                                <template v-else>
                                    <div class="max-h-[24rem] space-y-5 overflow-y-auto pr-1">
                                        <div v-for="parent in questCategoryTree" :key="parent.id" class="rounded-2xl border border-slate-100 bg-slate-50/80 p-4">
                                            <p class="text-xs font-black uppercase tracking-wide text-slate-500">{{ parent.name }}</p>
                                            <div class="mt-3 flex flex-wrap gap-2">
                                                <label
                                                    v-for="child in parent.children || []"
                                                    :key="child.id"
                                                    class="inline-flex cursor-pointer items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-800 shadow-sm transition hover:border-primary-200 hover:bg-primary-50/60"
                                                >
                                                    <input
                                                        v-model="categoryForm.quest_category_ids"
                                                        type="checkbox"
                                                        class="rounded border-slate-300 text-primary-600 focus:ring-primary-500"
                                                        :value="child.id"
                                                    />
                                                    <span>{{ child.name }}</span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-5 flex flex-wrap items-center gap-3">
                                        <button
                                            type="button"
                                            class="rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-bold text-white shadow-md hover:bg-primary-700 disabled:opacity-50"
                                            :disabled="categoryForm.processing || categoryForm.quest_category_ids.length < 1"
                                            @click="submitCategories"
                                        >
                                            Save categories
                                        </button>
                                        <InputError class="w-full sm:w-auto" :message="categoryForm.errors.quest_category_ids" />
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div
                        v-else
                        id="account-client-profile"
                        class="rounded-[1.75rem] border border-slate-100 bg-white p-6 shadow-sm ring-1 ring-slate-100 sm:p-8"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h2 class="font-display text-lg font-bold text-slate-900">
                                    Profile details
                                </h2>
                                <p class="mt-1 text-sm font-medium text-slate-600">
                                    How you appear in the app; visibility is controlled in the Visibility tab.
                                </p>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <BriefcaseIcon class="hidden h-6 w-6 text-slate-300 sm:block" aria-hidden="true" />
                                <button
                                    type="button"
                                    class="rounded-full p-2 text-slate-400 ring-1 ring-slate-200/80 transition hover:bg-primary-50 hover:text-primary-800"
                                    :aria-pressed="editSection === 'client'"
                                    :aria-label="editSection === 'client' ? 'Close editor' : 'Edit profile details'"
                                    @click="toggleSection('client')"
                                >
                                    <PencilSquareIcon v-if="editSection !== 'client'" class="h-6 w-6" aria-hidden="true" />
                                    <XMarkIcon v-else class="h-6 w-6" aria-hidden="true" />
                                </button>
                            </div>
                        </div>
                        <div class="mt-6 min-h-[16rem]">
                            <dl
                                v-if="editSection !== 'client'"
                                class="grid gap-4 text-sm font-semibold text-slate-800 sm:grid-cols-2"
                            >
                                <div class="sm:col-span-2">
                                    <dt class="text-[10px] font-black uppercase tracking-wide text-slate-500">Display name</dt>
                                    <dd class="mt-1 font-bold text-slate-900">{{ user.name || '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] font-black uppercase tracking-wide text-slate-500">First name</dt>
                                    <dd class="mt-1">{{ user.first_name || '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] font-black uppercase tracking-wide text-slate-500">Last name</dt>
                                    <dd class="mt-1">{{ user.last_name || '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] font-black uppercase tracking-wide text-slate-500">Job title</dt>
                                    <dd class="mt-1">{{ user.job_title || '—' }}</dd>
                                </div>
                                <div class="sm:col-span-2">
                                    <dt class="text-[10px] font-black uppercase tracking-wide text-slate-500">Company / organisation</dt>
                                    <dd class="mt-1">{{ user.company_name || '—' }}</dd>
                                </div>
                            </dl>
                            <form v-else class="grid gap-5 sm:grid-cols-2" @submit.prevent="submitDetails">
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Display name</label>
                                <input
                                    v-model="detailsForm.name"
                                    type="text"
                                    class="mt-1 w-full rounded-xl border-slate-200 text-sm font-medium shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                />
                                <InputError class="mt-1" :message="detailsForm.errors.name" />
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">First name</label>
                                <input
                                    v-model="detailsForm.first_name"
                                    type="text"
                                    class="mt-1 w-full rounded-xl border-slate-200 text-sm font-medium shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                />
                                <InputError class="mt-1" :message="detailsForm.errors.first_name" />
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Last name</label>
                                <input
                                    v-model="detailsForm.last_name"
                                    type="text"
                                    class="mt-1 w-full rounded-xl border-slate-200 text-sm font-medium shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                />
                                <InputError class="mt-1" :message="detailsForm.errors.last_name" />
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Job title</label>
                                <input
                                    v-model="detailsForm.job_title"
                                    type="text"
                                    class="mt-1 w-full rounded-xl border-slate-200 text-sm font-medium shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                />
                                <InputError class="mt-1" :message="detailsForm.errors.job_title" />
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Company / organisation</label>
                                <input
                                    v-model="detailsForm.company_name"
                                    type="text"
                                    class="mt-1 w-full rounded-xl border-slate-200 text-sm font-medium shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                />
                                <InputError class="mt-1" :message="detailsForm.errors.company_name" />
                            </div>
                            <div class="flex items-end sm:col-span-2">
                                <button
                                    type="submit"
                                    class="rounded-full bg-primary-600 px-6 py-2.5 text-sm font-bold text-white shadow-md shadow-primary-900/15 hover:bg-primary-700 disabled:opacity-50"
                                    :disabled="detailsForm.processing"
                                >
                                    Save profile details
                                </button>
                            </div>
                        </form>
                            </div>
                    </div>

                    <div
                        id="account-structured-address"
                        class="rounded-[1.75rem] border border-slate-100 bg-white p-6 shadow-sm ring-1 ring-slate-100 sm:p-8"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h2 class="font-display text-lg font-bold text-slate-900">
                                    Phone &amp; Nigeria location
                                </h2>
                                <p class="mt-1 text-sm font-medium text-slate-600">
                                    {{ contactSectionBlurb }}
                                </p>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <PhoneIcon class="hidden h-6 w-6 text-slate-300 sm:block" aria-hidden="true" />
                                <button
                                    type="button"
                                    class="rounded-full p-2 text-slate-400 ring-1 ring-slate-200/80 transition hover:bg-primary-50 hover:text-primary-800"
                                    :aria-pressed="editSection === 'address'"
                                    :aria-label="editSection === 'address' ? 'Close editor' : 'Edit phone and address'"
                                    @click="toggleSection('address')"
                                >
                                    <PencilSquareIcon v-if="editSection !== 'address'" class="h-6 w-6" aria-hidden="true" />
                                    <XMarkIcon v-else class="h-6 w-6" aria-hidden="true" />
                                </button>
                            </div>
                        </div>
                        <div class="mt-6 min-h-[14rem]">
                            <dl
                                v-if="editSection !== 'address'"
                                class="grid gap-4 text-sm font-semibold text-slate-800 sm:grid-cols-2"
                            >
                                <div>
                                    <dt class="text-[10px] font-black uppercase tracking-wide text-slate-500">Phone</dt>
                                    <dd class="mt-1">{{ user.phone || '—' }}</dd>
                                </div>
                                <div class="sm:col-span-2">
                                    <dt class="text-[10px] font-black uppercase tracking-wide text-slate-500">Street address</dt>
                                    <dd class="mt-1 whitespace-pre-wrap leading-relaxed">{{ user.address_line || '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] font-black uppercase tracking-wide text-slate-500">State</dt>
                                    <dd class="mt-1">{{ user.state || '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] font-black uppercase tracking-wide text-slate-500">LGA</dt>
                                    <dd class="mt-1">{{ user.local_government || '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] font-black uppercase tracking-wide text-slate-500">City</dt>
                                    <dd class="mt-1">{{ user.city || '—' }}</dd>
                                </div>
                            </dl>
                            <form v-else class="grid gap-5 sm:grid-cols-2" @submit.prevent="submitDetails">
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Phone</label>
                                    <input
                                        v-model="detailsForm.phone"
                                        type="tel"
                                        class="mt-1 w-full rounded-xl border-slate-200 text-sm font-medium shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                    />
                                    <InputError class="mt-1" :message="detailsForm.errors.phone" />
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Street address</label>
                                    <textarea
                                        v-model="detailsForm.address_line"
                                        rows="3"
                                        class="mt-1 w-full rounded-xl border-slate-200 text-sm font-medium shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                        placeholder="House number, street, area…"
                                    />
                                    <InputError class="mt-1" :message="detailsForm.errors.address_line" />
                                </div>
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">State</label>
                                    <UiSelect
                                        v-model="detailsForm.state_id"
                                        class="mt-1"
                                        :options="stateSelectOptions"
                                        placeholder="Select state"
                                        :invalid="!!detailsForm.errors.state_id"
                                    />
                                    <InputError class="mt-1" :message="detailsForm.errors.state_id" />
                                </div>
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Local government (LGA)</label>
                                    <UiSelect
                                        v-model="detailsForm.local_government_id"
                                        class="mt-1"
                                        :options="lgaSelectOptions"
                                        :placeholder="detailsForm.state_id ? 'Select LGA' : 'Choose state first'"
                                        :disabled="!detailsForm.state_id"
                                        :invalid="!!detailsForm.errors.local_government_id"
                                    />
                                    <InputError class="mt-1" :message="detailsForm.errors.local_government_id" />
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">City</label>
                                    <input
                                        v-model="detailsForm.city"
                                        type="text"
                                        class="mt-1 w-full rounded-xl border-slate-200 text-sm font-medium shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                    />
                                    <InputError class="mt-1" :message="detailsForm.errors.city" />
                                </div>
                                <div class="sm:col-span-2 flex justify-end">
                                    <button
                                        type="submit"
                                        class="rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-bold text-white shadow-md hover:bg-primary-700 disabled:opacity-50"
                                        :disabled="detailsForm.processing"
                                    >
                                        Save phone &amp; location
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reviews -->
            <div v-show="localTab === 'reviews'" class="space-y-8">
                <section class="rounded-[1.75rem] border border-slate-100 bg-white p-6 shadow-sm ring-1 ring-slate-100 sm:p-8">
                    <div class="flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <h2 class="font-display text-xl font-bold text-slate-900">
                                Review snapshot
                            </h2>
                            <p class="mt-1 text-sm font-medium text-slate-600">
                                {{ reviewStats.total }} total · {{ reviewStats.with_stars }} with star ratings
                            </p>
                        </div>
                        <Link
                            v-if="publicReviewsUrl"
                            :href="publicReviewsUrl"
                            class="inline-flex items-center gap-2 rounded-full bg-primary-600 px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-primary-900/15 hover:bg-primary-700"
                        >
                            Open public list
                            <ArrowTopRightOnSquareIcon class="h-4 w-4" aria-hidden="true" />
                        </Link>
                    </div>
                    <div class="mt-8 max-w-lg space-y-2">
                        <div v-for="lvl in 5" :key="lvl" class="flex items-center gap-3">
                            <span class="w-8 text-xs font-bold text-slate-500">{{ lvl }}★</span>
                            <div class="h-2.5 min-w-0 flex-1 overflow-hidden rounded-full bg-slate-100">
                                <div
                                    class="h-full rounded-full bg-gradient-to-r from-secondary-400 to-secondary-600"
                                    :style="{ width: distBarWidth(lvl) }"
                                />
                            </div>
                            <span class="w-8 text-right text-xs font-semibold tabular-nums text-slate-600">
                                {{ reviewStats.distribution[String(lvl)] ?? 0 }}
                            </span>
                        </div>
                    </div>
                </section>

                <section class="space-y-4">
                    <h3 class="font-display text-lg font-bold text-slate-900">
                        Recent reviews
                    </h3>
                    <ul class="space-y-4">
                        <li
                            v-for="r in recentReviews"
                            :key="r.id"
                            class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm ring-1 ring-slate-50"
                        >
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="rounded-full bg-secondary-50 px-2.5 py-0.5 text-xs font-black text-secondary-800 ring-1 ring-secondary-100">
                                    {{ r.rating ?? '—' }}/5
                                </span>
                                <span class="text-sm font-bold text-slate-900">{{ r.reviewer_label }}</span>
                                <span class="text-xs font-semibold text-slate-500">{{ formatWhen(r.created_at) }}</span>
                            </div>
                            <p v-if="r.title" class="mt-2 font-display text-base font-bold text-slate-900">
                                {{ r.title }}
                            </p>
                            <p v-if="r.quest_title" class="mt-1 text-xs font-bold uppercase tracking-wide text-primary-700">
                                {{ r.quest_title }}
                            </p>
                            <p v-if="r.comment" class="mt-3 text-sm font-medium leading-relaxed text-slate-700">
                                {{ r.comment }}
                            </p>
                        </li>
                    </ul>
                </section>
            </div>

            <!-- Portfolio (freelancer) -->
            <div v-show="localTab === 'portfolio' && user.role_slug === 'freelancer'" class="space-y-6">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h2 class="font-display text-xl font-bold text-slate-900">
                            Portfolio
                        </h2>
                        <p class="mt-1 text-sm font-medium text-slate-600">
                            {{ portfolio.counts.published }} published · {{ portfolio.counts.draft }} drafts
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Link
                            :href="route('portfolio.manage')"
                            class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-800 shadow-sm hover:border-primary-200 hover:bg-primary-50"
                        >
                            Manage
                        </Link>
                        <Link
                            v-if="publicPortfoliosUrl"
                            :href="publicPortfoliosUrl"
                            class="rounded-full bg-primary-600 px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-primary-900/15 hover:bg-primary-700"
                        >
                            Public gallery
                        </Link>
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Link
                        v-for="p in portfolio.preview"
                        :key="p.slug"
                        :href="route('portfolio.show', p.slug)"
                        class="group overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm ring-1 ring-slate-100 transition hover:-translate-y-0.5 hover:shadow-lg"
                    >
                        <div class="aspect-[16/10] overflow-hidden bg-slate-100">
                            <img
                                v-if="p.cover_url"
                                :src="p.cover_url"
                                :alt="p.title"
                                class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]"
                            />
                        </div>
                        <div class="p-4">
                            <p class="font-display text-sm font-bold text-slate-900 line-clamp-2">
                                {{ p.title }}
                            </p>
                            <p class="mt-2 text-xs font-semibold text-slate-500">
                                {{ p.favorites_count }} likes
                            </p>
                        </div>
                    </Link>
                </div>
            </div>

            <!-- Credentials -->
            <div v-show="localTab === 'credentials' && user.role_slug === 'freelancer'" class="space-y-8">
                <div
                    class="flex flex-nowrap items-center justify-between gap-4 overflow-x-auto rounded-2xl border border-primary-100 bg-primary-50/50 p-5 ring-1 ring-primary-100 sm:p-6"
                >
                    <div class="min-w-0 flex-1 pr-2">
                        <h2 class="font-display text-lg font-bold text-slate-900">
                            Credentials hub
                        </h2>
                        <p class="mt-1 text-sm font-medium text-slate-700">
                            Add NAICOM-backed insurance, council licences, qualifications, and certifications — each as its own entry. HustleSafe can verify them for your public profile.
                        </p>
                    </div>
                    <Link
                        :href="route('account.credentials.index')"
                        class="inline-flex shrink-0 items-center justify-center whitespace-nowrap rounded-xl bg-primary-700 px-5 py-2.5 text-sm font-bold text-white shadow-md hover:bg-primary-800"
                    >
                        Full manager
                    </Link>
                </div>

                <div
                    v-for="section in credentialSections"
                    :key="section.type"
                    class="rounded-[1.75rem] border border-slate-100 bg-white p-6 shadow-sm ring-1 ring-slate-100 sm:p-8"
                >
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h3 class="font-display text-base font-bold text-slate-900">
                                {{ section.title }}
                            </h3>
                            <p class="mt-1 text-xs font-medium text-slate-600">
                                {{ section.hint }}
                            </p>
                        </div>
                        <Link
                            :href="route('account.credentials.create', { type: section.type })"
                            class="shrink-0 rounded-xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-bold text-slate-800 hover:border-primary-200 hover:bg-primary-50"
                        >
                            Add {{ section.short }}
                        </Link>
                    </div>
                    <ul v-if="section.items.length" class="mt-6 space-y-4">
                        <li
                            v-for="c in section.items"
                            :key="c.id"
                            class="flex flex-col gap-4 rounded-2xl border border-slate-100 bg-slate-50/50 p-4 ring-1 ring-slate-100 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-display text-base font-bold text-slate-900">
                                        {{ c.title }}
                                    </p>
                                    <span
                                        v-if="c.is_verified"
                                        class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-black uppercase tracking-wide text-emerald-900 ring-1 ring-emerald-200"
                                    >
                                        Verified
                                    </span>
                                    <span
                                        v-else
                                        class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-black uppercase tracking-wide text-amber-950 ring-1 ring-amber-200"
                                    >
                                        Not verified
                                    </span>
                                </div>
                                <p v-if="c.issuing_authority" class="mt-1 text-sm font-semibold text-slate-600">
                                    {{ c.issuing_authority }}
                                </p>
                            </div>
                            <div class="flex flex-col gap-3 sm:items-end">
                                <div class="flex flex-wrap gap-2">
                                    <Link
                                        :href="route('account.credentials.edit', { freelancerCredential: c.id })"
                                        class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-800 hover:border-primary-200"
                                    >
                                        Edit
                                    </Link>
                                </div>
                                <label class="flex cursor-pointer items-center gap-2 text-xs font-bold text-slate-700">
                                    <input
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500"
                                        :checked="c.is_public"
                                        @change="patchCredentialPublic(c, $event.target.checked)"
                                    />
                                    Public profile
                                </label>
                            </div>
                        </li>
                    </ul>
                    <p
                        v-else
                        class="mt-4 rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-center text-sm font-semibold text-slate-600"
                    >
                        No {{ section.short.toLowerCase() }} yet — use “Add {{ section.short }}”.
                    </p>
                </div>
                <div v-if="cac" class="rounded-2xl border border-slate-200 bg-slate-50 p-6">
                    <h3 class="font-display text-lg font-bold text-slate-900">
                        CAC registration
                    </h3>
                    <p class="mt-2 text-sm font-semibold text-slate-700">
                        RC: {{ cac.registration_number }}
                    </p>
                    <p class="mt-1 text-xs font-bold uppercase tracking-wide text-slate-500">
                        {{ formatCac(cac.status) }}
                    </p>
                    <p class="mt-3 text-xs font-medium text-slate-600">
                        Visibility of CAC on your public profile is controlled in the Visibility tab.
                    </p>
                </div>
            </div>

            <!-- Visibility -->
            <div v-show="localTab === 'visibility'" id="account-visibility-settings" class="space-y-6">
                <div class="rounded-[1.75rem] border border-amber-200 bg-amber-50/80 p-6 ring-1 ring-amber-100">
                    <p class="text-sm font-bold text-amber-950">
                        {{ visibilityRiskWarning }}
                    </p>
                </div>
                <div class="rounded-[1.75rem] border border-primary-100 bg-primary-50/60 p-6 ring-1 ring-primary-100/80">
                    <p class="text-sm font-semibold leading-relaxed text-primary-950">
                        {{ visibilityFieldHelp }}
                    </p>
                </div>
                <div class="rounded-[1.75rem] border border-slate-100 bg-white p-6 shadow-sm ring-1 ring-slate-100 sm:p-8">
                    <div>
                        <h2 class="font-display text-lg font-bold text-slate-900">
                            Public visibility
                        </h2>
                        <p class="mt-1 text-sm font-medium text-slate-600">
                            Choose what appears on your public profile. Changes save when you click the button below.
                        </p>
                    </div>
                    <form class="mt-6 space-y-4" @submit.prevent="submitVisibility">
                        <div
                            v-for="key in visibilityKeys"
                            :key="key"
                            class="flex items-start gap-4 rounded-2xl border border-slate-100 bg-slate-50/50 p-4"
                        >
                            <input
                                :id="'vis-'+key"
                                v-model="visibilityForm.settings[key]"
                                type="checkbox"
                                class="mt-1 h-5 w-5 rounded border-slate-300 text-primary-600 focus:ring-primary-500"
                            />
                            <div>
                                <label :for="'vis-'+key" class="text-sm font-bold text-slate-900">{{ visibilityLabels[key] }}</label>
                                <p class="mt-1 text-xs font-medium text-slate-600">
                                    {{ visibilityHints[key] }}
                                </p>
                            </div>
                        </div>
                        <div class="flex justify-end pt-2">
                            <button
                                type="submit"
                                class="rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-bold text-white shadow-md hover:bg-primary-700 disabled:opacity-50"
                                :disabled="visibilityForm.processing"
                            >
                                Save visibility
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Settings -->
            <div v-show="localTab === 'settings'" class="space-y-10">
                <section id="account-online-presence" class="rounded-[1.75rem] border border-slate-100 bg-white p-6 shadow-sm ring-1 ring-slate-100 sm:p-8">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="font-display text-lg font-bold text-slate-900">
                                Online status
                            </h2>
                            <p class="mt-2 text-sm font-medium text-slate-600">
                                When you allow it, a green indicator can show on your public profile while you are active. If you hide your status, you will not see whether other people are online either — this keeps the feature fair for everyone.
                            </p>
                        </div>
                        <button
                            type="button"
                            class="rounded-full p-2 text-slate-400 ring-1 ring-slate-200/80 transition hover:bg-primary-50 hover:text-primary-800"
                            :aria-pressed="editSection === 'presence'"
                            :aria-label="editSection === 'presence' ? 'Close editor' : 'Edit online status'"
                            @click="toggleSection('presence')"
                        >
                            <PencilSquareIcon v-if="editSection !== 'presence'" class="h-6 w-6" aria-hidden="true" />
                            <XMarkIcon v-else class="h-6 w-6" aria-hidden="true" />
                        </button>
                    </div>
                    <div class="mt-5 min-h-[8rem]">
                        <p v-if="editSection !== 'presence'" class="text-sm font-bold text-slate-900">
                            {{ presenceForm.hide_online_presence ? 'Hidden — others will not see when you are online.' : 'Visible — you and others can see online indicators when enabled.' }}
                        </p>
                        <form v-else class="space-y-4" @submit.prevent="submitPresence">
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-slate-50/80 p-4">
                                <input
                                    v-model="presenceForm.hide_online_presence"
                                    type="checkbox"
                                    class="mt-1 h-5 w-5 rounded border-slate-300 text-primary-600 focus:ring-primary-500"
                                />
                                <span>
                                    <span class="block text-sm font-bold text-slate-900">Hide my online status</span>
                                    <span class="mt-1 block text-xs font-medium text-slate-600">
                                        You stop sharing when you were last active, and you will not see others’ online indicators while this is on.
                                    </span>
                                </span>
                            </label>
                            <div class="flex justify-end">
                                <button
                                    type="submit"
                                    class="rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-bold text-white shadow-md hover:bg-primary-700 disabled:opacity-50"
                                    :disabled="presenceForm.processing"
                                >
                                    Save preference
                                </button>
                            </div>
                        </form>
                    </div>
                </section>

                <section class="rounded-[1.75rem] border border-slate-100 bg-white p-6 shadow-sm ring-1 ring-slate-100 sm:p-8">
                    <h2 class="font-display text-lg font-bold text-slate-900">
                        Security &amp; sign-in
                    </h2>
                    <p class="mt-2 text-sm font-medium text-slate-600">
                        Update your password, profile photo, or permanently delete your account. Your email cannot be changed here.
                    </p>
                    <Link
                        :href="route('account.security.edit')"
                        class="mt-5 inline-flex items-center gap-2 rounded-full bg-primary-600 px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-primary-900/15 hover:bg-primary-700"
                    >
                        Open security settings
                        <ArrowTopRightOnSquareIcon class="h-4 w-4" aria-hidden="true" />
                    </Link>
                </section>

                <section class="rounded-[1.75rem] border border-rose-100 bg-rose-50/40 p-6 ring-1 ring-rose-100 sm:p-8">
                    <h2 class="font-display text-lg font-bold text-rose-950">
                        Deactivate account
                    </h2>
                    <p class="mt-2 text-sm font-semibold leading-relaxed text-rose-900/90">
                        Deactivation hides you from the marketplace and blocks sign-in until you reactivate with your email and password from the login page.
                    </p>
                    <form class="mt-6 space-y-4" @submit.prevent="submitDeactivate">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wide text-rose-800">Current password</label>
                            <input
                                v-model="deactivateForm.password"
                                type="password"
                                class="mt-1 w-full max-w-md rounded-xl border-rose-200 text-sm font-medium shadow-sm focus:border-rose-500 focus:ring-rose-500"
                                autocomplete="current-password"
                            />
                            <InputError class="mt-1" :message="deactivateForm.errors.password" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wide text-rose-800">Type DEACTIVATE to confirm</label>
                            <input
                                v-model="deactivateForm.confirm"
                                type="text"
                                class="mt-1 w-full max-w-md rounded-xl border-rose-200 text-sm font-medium shadow-sm focus:border-rose-500 focus:ring-rose-500"
                                autocomplete="off"
                            />
                            <InputError class="mt-1" :message="deactivateForm.errors.confirm" />
                        </div>
                        <button
                            type="submit"
                            class="rounded-xl bg-rose-600 px-5 py-2.5 text-sm font-bold text-white shadow-md hover:bg-rose-700 disabled:opacity-50"
                            :disabled="deactivateForm.processing"
                        >
                            Deactivate my account
                        </button>
                    </form>
                </section>
            </div>
        </div>
    </AppShell>
</template>

<script setup>
import InputError from '@/Components/InputError.vue';
import UserProfileAvatar from '@/Components/Ui/UserProfileAvatar.vue';
import UiSelect from '@/Components/Ui/UiSelect.vue';
import TrustHalfDonut from '@/Components/Home/TrustHalfDonut.vue';
import AppShell from '@/Layouts/AppShell.vue';
import {
    ArrowTopRightOnSquareIcon,
    BriefcaseIcon,
    Cog6ToothIcon,
    EyeIcon,
    PencilSquareIcon,
    PhoneIcon,
    RectangleStackIcon,
    ShieldCheckIcon,
    Squares2X2Icon,
    StarIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { usePathname } from '@/composables/usePathname';
import { formatCompactCountWithFull } from '@/utils/formatCompactCount';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    activeTab: { type: String, default: 'overview' },
    mustVerifyEmail: { type: Boolean, default: false },
    user: { type: Object, required: true },
    trust: { type: Object, required: true },
    reviewStats: { type: Object, required: true },
    recentReviews: { type: Array, default: () => [] },
    portfolio: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
    questCategoryTree: { type: Array, default: () => [] },
    credentials: { type: Array, default: () => [] },
    cac: { type: Object, default: null },
    visibility: { type: Object, required: true },
    visibilityKeys: { type: Array, required: true },
    publicReviewsUrl: { type: String, default: null },
    publicPortfoliosUrl: { type: String, default: null },
    follower_count: { type: Number, default: 0 },
    following_count: { type: Number, default: 0 },
    visibilityFieldHelp: { type: String, default: '' },
    locations: { type: Array, default: () => [] },
});

const tabs = computed(() => {
    const base = [
        { id: 'overview', label: 'Overview', icon: Squares2X2Icon },
        { id: 'reviews', label: 'Reviews', icon: StarIcon },
    ];
    if (props.user.role_slug === 'freelancer') {
        base.push({ id: 'portfolio', label: 'Portfolio', icon: RectangleStackIcon });
        base.push({ id: 'credentials', label: 'Credentials', icon: ShieldCheckIcon });
    }
    base.push({ id: 'visibility', label: 'Visibility', icon: EyeIcon });
    base.push({ id: 'settings', label: 'Settings', icon: Cog6ToothIcon });

    return base;
});

const page = usePage();
const pathname = usePathname(page);

const localTab = ref(props.activeTab || 'overview');

function tabIds() {
    return tabs.value.map((t) => t.id);
}

watch(
    () => props.activeTab,
    (v) => {
        if (v && tabIds().includes(v)) {
            localTab.value = v;
        }
    },
);

watch(
    () => page.url,
    () => {
        try {
            const q = new URL(page.url, window.location.origin).searchParams.get('tab');
            if (q && tabIds().includes(q)) {
                localTab.value = q;
            }
        } catch {
            //
        }
    },
);

function setTab(id) {
    if (!tabIds().includes(id)) {
        return;
    }
    editSection.value = null;
    localTab.value = id;
    const u = new URL(window.location.href);
    u.searchParams.set('tab', id);
    const qs = u.searchParams.toString();
    window.history.replaceState({}, '', qs ? `${u.pathname}?${qs}` : u.pathname);
}

function syncTabFromUrl() {
    const q = new URLSearchParams(window.location.search).get('tab');
    if (q && tabIds().includes(q)) {
        localTab.value = q;
    }
}

const presenceForm = useForm({
    hide_online_presence: !!props.user.hide_online_presence,
});

watch(
    () => props.user.hide_online_presence,
    (v) => {
        presenceForm.hide_online_presence = !!v;
    },
);

const detailsForm = useForm({
    first_name: props.user.first_name,
    last_name: props.user.last_name,
    name: props.user.name,
    headline: props.user.headline,
    bio: props.user.bio,
    phone: props.user.phone,
    profession: props.user.profession,
    job_title: props.user.job_title,
    years_experience: props.user.years_experience,
    hourly_rate_min: props.user.hourly_rate_min,
    hourly_rate_max: props.user.hourly_rate_max,
    city: props.user.city,
    company_name: props.user.company_name,
    address_line: props.user.address_line ?? '',
    state_id: props.user.state_id ?? null,
    local_government_id: props.user.local_government_id ?? null,
});

const visibilityForm = useForm({
    settings: Object.fromEntries(props.visibilityKeys.map((k) => [k, !!props.visibility[k]])),
});

const deactivateForm = useForm({
    password: '',
    confirm: '',
});

const visibilityLabels = {
    show_bio: 'Bio & story',
    show_headline: 'Headline',
    show_location: 'Location',
    show_rates: 'Rate guide',
    show_phone: 'Phone number',
    show_email: 'Email address',
    show_credentials: 'Certifications & insurance',
    show_cac: 'CAC registration',
    show_portfolio: 'Portfolio previews',
    show_experience: 'Profession & experience',
    show_company: 'Company or organisation',
};

const visibilityHints = {
    show_bio: 'Helps others understand who you are and how you work.',
    show_headline: 'A sharp one-liner under your name.',
    show_location: 'State, LGA, and city when you enable location.',
    show_rates: 'Typical hourly range for budgeting.',
    show_phone: 'Only enable if you want direct calls.',
    show_email: 'Rarely needed — messaging inside HustleSafe is safer.',
    show_credentials: 'Licences and insurance build instant trust.',
    show_cac: 'Registered business signals seriousness.',
    show_portfolio: 'Link visitors to your best work.',
    show_experience: 'Years and profession badges on your hero.',
    show_company: 'Displays your organisation when you sponsor or collaborate on quests.',
};

const visibilityRiskWarning = computed(() =>
    props.user.role_slug === 'freelancer'
        ? 'The more you hide, the less proof clients see — strong public profiles win more invitations and proposals. Only turn off items you are sure you want to keep private.'
        : 'Freelancers often choose who to work with based on what they can verify. Keep useful details visible when you can.',
);

const categoryForm = useForm({
    quest_category_ids: props.categories.map((c) => c.id),
});

/** One inline editor at a time — view mode avoids accidental edits. */
const editSection = ref(null);

const stateSelectOptions = computed(() =>
    (props.locations || []).map((s) => ({
        value: s.id,
        label: s.name,
    })),
);

const lgaSelectOptions = computed(() => {
    const sid = Number(detailsForm.state_id);
    const st = (props.locations || []).find((x) => Number(x.id) === sid);

    return (st?.local_governments || []).map((lg) => ({
        value: lg.id,
        label: lg.name,
    }));
});

function scrollToAccountHash() {
    const id = window.location.hash?.replace('#', '') || '';
    if (!id.startsWith('account-')) {
        return;
    }
    requestAnimationFrame(() => {
        document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
}

function toggleSection(section) {
    if (editSection.value === section) {
        editSection.value = null;
        hydrateDetailsForm();
        detailsForm.clearErrors();
        visibilityForm.clearErrors();
        presenceForm.clearErrors();

        return;
    }
    editSection.value = section;
    hydrateDetailsForm();
}

function hydrateDetailsForm() {
    const u = props.user;
    detailsForm.first_name = u.first_name ?? '';
    detailsForm.last_name = u.last_name ?? '';
    detailsForm.name = u.name ?? '';
    detailsForm.headline = u.headline ?? '';
    detailsForm.bio = u.bio ?? '';
    detailsForm.phone = u.phone ?? '';
    detailsForm.profession = u.profession ?? '';
    detailsForm.job_title = u.job_title ?? '';
    detailsForm.years_experience = u.years_experience ?? '';
    detailsForm.hourly_rate_min = u.hourly_rate_min ?? '';
    detailsForm.hourly_rate_max = u.hourly_rate_max ?? '';
    detailsForm.city = u.city ?? '';
    detailsForm.company_name = u.company_name ?? '';
    detailsForm.address_line = u.address_line ?? '';
    detailsForm.state_id = u.state_id ?? null;
    detailsForm.local_government_id = u.local_government_id ?? null;
}

watch(
    () => detailsForm.state_id,
    (n, o) => {
        if (editSection.value !== 'address' || o === undefined) {
            return;
        }
        if (Number(n) !== Number(o)) {
            detailsForm.local_government_id = null;
        }
    },
);

watch(
    () => props.visibility,
    (v) => {
        if (!v) {
            return;
        }
        visibilityForm.settings = Object.fromEntries(props.visibilityKeys.map((k) => [k, !!v[k]]));
    },
    { deep: true },
);

watch(
    () => props.user,
    () => {
        hydrateDetailsForm();
    },
    { deep: true },
);

watch(
    () => page.url,
    () => {
        if (pathname.value.startsWith('/account')) {
            nextTick(scrollToAccountHash);
        }
    },
);

watch(
    () => props.categories,
    (cats) => {
        categoryForm.quest_category_ids = (cats || []).map((c) => c.id);
    },
    { deep: true },
);

function submitCategories() {
    categoryForm.patch(route('account.quest-categories.update'), {
        preserveScroll: true,
        replace: true,
        onSuccess: () => {
            editSection.value = null;
        },
    });
}

const roleBannerLabel = computed(() => {
    if (props.user.role_slug === 'freelancer') {
        return 'Signed in as Freelancer';
    }
    if (props.user.role_slug === 'client') {
        return 'Signed in as Client';
    }

    return `Signed in as ${roleLabel.value}`;
});

const credentialSectionDefs = [
    {
        type: 'insurance',
        title: 'Insurance & indemnity',
        short: 'Policy',
        hint: 'NAICOM-regulated policies, professional indemnity, or asset cover relevant to your field.',
    },
    {
        type: 'professional_licence',
        title: 'Professional licences',
        short: 'Licence',
        hint: 'Council registrations such as COREN, ARCON, NIM, MDCN, or other Nigerian regulators.',
    },
    {
        type: 'qualification',
        title: 'Qualifications',
        short: 'Qualification',
        hint: 'Degrees, diplomas, HSE programmes, and formal training.',
    },
    {
        type: 'certification',
        title: 'Certifications',
        short: 'Certification',
        hint: 'Vendor badges, cloud certs, safety cards, and short professional courses.',
    },
];

const credentialSections = computed(() => {
    const list = props.credentials || [];

    return credentialSectionDefs.map((def) => ({
        ...def,
        items: list.filter((c) => c.credential_type === def.type),
    }));
});

const storySectionBlurb = computed(() =>
    props.user.role_slug === 'freelancer'
        ? 'Headline and bio appear on your public freelancer profile when visibility allows.'
        : 'Headline and bio can be shared with freelancers when your visibility settings allow.',
);

const contactSectionBlurb = computed(() =>
    props.user.role_slug === 'freelancer'
        ? 'Phone, street address, state, LGA, and city stay private unless you enable location on your public profile.'
        : 'Phone, street address, state, LGA, and city stay private unless you enable them for people you work with.',
);

const initials = computed(() => {
    const n = props.user.name || '';
    const parts = n.trim().split(/\s+/);

    return ((parts[0]?.[0] || 'H') + (parts[1]?.[0] || '')).toUpperCase();
});

const joinedLabel = computed(() => {
    if (!props.user.created_at) {
        return '';
    }
    try {
        return new Date(props.user.created_at).toLocaleDateString('en-NG', { month: 'short', year: 'numeric' });
    } catch {
        return '';
    }
});

const roleLabel = computed(() => {
    const slug = props.user.role_slug;

    if (slug === 'freelancer') {
        return 'Safe Hustler';
    }
    if (slug === 'client') {
        return 'Client';
    }

    return slug ? String(slug).replaceAll('_', ' ') : 'Member';
});

const primaryTrustScore = computed(() =>
    props.user.role_slug === 'freelancer' ? props.trust.freelancer : props.trust.client,
);

const primaryTrustLabel = computed(() =>
    props.user.role_slug === 'freelancer' ? 'Freelancer trust' : 'Client trust',
);

function formatCountTile(n) {
    return formatCompactCountWithFull(Number(n) || 0);
}

const statTiles = computed(() => {
    const tiles = [];
    if (props.user.role_slug === 'freelancer') {
        tiles.push({ label: 'Trust', value: `${props.trust.freelancer}%` });
        tiles.push({
            label: 'Rating',
            value: props.trust.avg_rating_freelancer != null ? Number(props.trust.avg_rating_freelancer).toFixed(1) : '—',
        });
        tiles.push({ label: 'Reviews', value: props.trust.rating_count_freelancer });
        tiles.push({ label: 'Profile', value: `${props.trust.profile_percent ?? 0}%` });
        tiles.push({ label: 'Followers', value: formatCountTile(props.follower_count) });
        tiles.push({ label: 'Following', value: formatCountTile(props.following_count) });
        tiles.push({ label: 'Portfolios', value: props.portfolio.counts.published });
    } else {
        tiles.push({ label: 'Trust', value: `${props.trust.client}%` });
        tiles.push({
            label: 'Rating',
            value: props.trust.avg_rating_client != null ? Number(props.trust.avg_rating_client).toFixed(1) : '—',
        });
        tiles.push({ label: 'Reviews', value: props.trust.rating_count_client });
        tiles.push({ label: 'Profile', value: `${props.trust.profile_percent ?? 0}%` });
        tiles.push({ label: 'Followers', value: formatCountTile(props.follower_count) });
        tiles.push({ label: 'Following', value: formatCountTile(props.following_count) });
    }

    return tiles;
});

const distMax = computed(() => {
    const d = props.reviewStats.distribution || {};

    return Math.max(1, ...Object.values(d).map((n) => Number(n) || 0));
});

function distBarWidth(lvl) {
    const n = Number(props.reviewStats.distribution?.[String(lvl)] ?? 0);

    return `${Math.round((n / distMax.value) * 100)}%`;
}

function submitDetails() {
    detailsForm.patch(route('account.details'), {
        preserveScroll: true,
        replace: true,
        onSuccess: () => {
            editSection.value = null;
        },
    });
}

function submitVisibility() {
    visibilityForm.patch(route('account.visibility'), {
        preserveScroll: true,
        replace: true,
        onSuccess: () => {
            editSection.value = null;
        },
    });
}

function submitPresence() {
    presenceForm.patch(route('account.presence'), {
        preserveScroll: true,
        replace: true,
        onSuccess: () => {
            editSection.value = null;
        },
    });
}

function submitDeactivate() {
    deactivateForm.post(route('account.deactivate'), { preserveScroll: true });
}

function patchCredentialPublic(c, isPublic) {
    router.patch(
        route('account.credentials.visibility', { freelancerCredential: c.id }),
        { is_public: isPublic },
        { preserveScroll: true },
    );
}

function formatWhen(iso) {
    if (!iso) {
        return '';
    }
    try {
        return new Date(iso).toLocaleDateString('en-NG', { day: 'numeric', month: 'short', year: 'numeric' });
    } catch {
        return '';
    }
}

function formatCac(s) {
    return String(s || '').replaceAll('_', ' ');
}

onMounted(() => {
    window.addEventListener('popstate', syncTabFromUrl);
    syncTabFromUrl();
    hydrateDetailsForm();
    scrollToAccountHash();
});

onUnmounted(() => {
    window.removeEventListener('popstate', syncTabFromUrl);
});
</script>
