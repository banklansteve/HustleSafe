<template>
    <AdminShell
        title="Quest Management Engine"
        subtitle="A command centre for quest operations, flags, featured boosts, escrow context, and admin intervention."
    >
        <div class="quest-engine-page">
        <section class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-3xl border border-primary-100 bg-primary-50/90 p-4 text-slate-900 ring-1 ring-primary-100">
            <div>
                <p class="text-xs font-black uppercase tracking-[0.2em] text-primary-700">Documentation</p>
                <p class="mt-1 text-sm font-bold text-slate-800">Need help with quest lifecycle, escrow, admin status, flags, or nested slide-overs?</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Link :href="route('admin.documentation.guide', { topic: 'quest-management' }) + '#statuses-and-signals'" class="learn-more-link">Statuses & signals</Link>
                <Link :href="route('admin.documentation.guide', { topic: 'payments-escrow' })" class="learn-more-link">Escrow</Link>
                <Link :href="route('admin.documentation.guide', { topic: 'flags-notices' })" class="learn-more-link">Flags & notices</Link>
                <Link :href="route('admin.documentation.guide', { topic: 'audit-trails' })" class="learn-more-link">Audit trails</Link>
            </div>
        </section>

        <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
            <button
                v-for="tile in summary"
                :key="tile.key"
                type="button"
                class="group rounded-3xl border border-white/10 bg-slate-900/70 p-4 text-left ring-1 ring-white/5 transition hover:-translate-y-0.5 hover:border-primary-400/50 hover:bg-slate-900"
                :class="form.quick === tile.filter.quick ? 'border-primary-400/60 shadow-lg shadow-primary-950/30' : ''"
                @click="applyShortcut(tile.filter)"
            >
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500 group-hover:text-primary-200">{{ tile.label }}</p>
                <div class="mt-3 flex items-end justify-between gap-3">
                    <p class="font-display text-3xl font-black text-white">{{ tile.value }}</p>
                    <span class="rounded-full bg-white/10 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-slate-300">Filter</span>
                </div>
            </button>
        </section>

        <section class="sticky top-0 z-20 mt-5 rounded-3xl border border-white/10 bg-slate-950/90 p-4 shadow-2xl shadow-slate-950/30 ring-1 ring-white/5 backdrop-blur">
            <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                <div class="flex min-w-0 flex-1 flex-col gap-3 lg:flex-row lg:items-center">
                    <div class="relative min-w-[16rem] flex-1">
                        <input
                            v-model="form.q"
                            type="search"
                            class="h-12 w-full rounded-2xl border border-white/10 bg-slate-900 px-4 pl-11 text-sm font-semibold text-white outline-none ring-2 ring-transparent transition placeholder:text-slate-500 focus:border-primary-400/60 focus:ring-primary-500/30"
                            placeholder="Search title, description, client, email, category, or Quest ID"
                            autocomplete="off"
                        />
                        <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-slate-500">⌕</span>
                    </div>

                    <div class="flex gap-2 overflow-x-auto pb-1">
                        <button
                            v-for="filter in options.quick_filters"
                            :key="filter.value || 'all'"
                            type="button"
                            class="inline-flex h-11 shrink-0 items-center gap-2 rounded-full border px-4 text-xs font-black uppercase tracking-wide transition"
                            :class="form.quick === filter.value ? 'border-primary-400 bg-primary-500 text-slate-950' : 'border-white/10 bg-white/5 text-slate-300 hover:bg-white/10'"
                            @click="setQuick(filter.value)"
                        >
                            {{ filter.label }}
                            <span class="rounded-full bg-black/15 px-2 py-0.5">{{ quickCount(filter.value) }}</span>
                        </button>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" class="control-button" @click="advancedOpen = !advancedOpen">Advanced filters</button>
                    <select v-model="form.per_page" class="control-select" @change="apply">
                        <option :value="25">25 rows</option>
                        <option :value="50">50 rows</option>
                        <option :value="100">100 rows</option>
                    </select>
                    <button type="button" class="control-button" @click="density = density === 'comfortable' ? 'compact' : 'comfortable'">
                        {{ density === 'comfortable' ? 'Compact' : 'Comfortable' }}
                    </button>
                    <div class="inline-flex rounded-2xl border border-white/10 bg-white/5 p-1">
                        <button
                            v-for="mode in viewModes"
                            :key="mode.value"
                            type="button"
                            class="h-9 rounded-xl px-3 text-xs font-black uppercase tracking-wide transition"
                            :class="viewMode === mode.value ? 'bg-primary-500 text-slate-950' : 'text-slate-300 hover:bg-white/10'"
                            @click="viewMode = mode.value"
                        >
                            {{ mode.label }}
                        </button>
                    </div>
                </div>
            </div>

            <Transition enter-active-class="transition duration-150" enter-from-class="-translate-y-2 opacity-0" enter-to-class="translate-y-0 opacity-100">
                <div v-if="advancedOpen" class="mt-4 grid gap-3 rounded-2xl border border-white/10 bg-slate-900/70 p-4 md:grid-cols-2 xl:grid-cols-4">
                    <label class="filter-field">
                        <span>Platform status</span>
                        <select v-model="form.status">
                            <option value="">Any status</option>
                            <option v-for="status in options.status_options" :key="status.value" :value="status.value">{{ status.label }}</option>
                        </select>
                    </label>
                    <label class="filter-field">
                        <span>Admin status</span>
                        <select v-model="form.admin_status">
                            <option value="">Any admin status</option>
                            <option v-for="status in options.admin_status_options" :key="status.value" :value="status.value">{{ status.label }}</option>
                        </select>
                    </label>
                    <label class="filter-field">
                        <span>Budget from</span>
                        <input v-model="form.budget_min" type="number" min="0" placeholder="₦ min" />
                    </label>
                    <label class="filter-field">
                        <span>Budget to</span>
                        <input v-model="form.budget_max" type="number" min="0" placeholder="₦ max" />
                    </label>
                    <label class="filter-field">
                        <span>Date posted from</span>
                        <input v-model="form.posted_from" type="date" />
                    </label>
                    <label class="filter-field">
                        <span>Date posted to</span>
                        <input v-model="form.posted_to" type="date" />
                    </label>
                    <label class="filter-field">
                        <span>Project type</span>
                        <select v-model="form.project_type">
                            <option value="">Any type</option>
                            <option value="fixed_price">Fixed price</option>
                            <option value="hourly">Hourly</option>
                        </select>
                    </label>
                    <label class="filter-field">
                        <span>Proposals min</span>
                        <input v-model="form.proposals_min" type="number" min="0" />
                    </label>
                    <label class="filter-field">
                        <span>Flag type</span>
                        <select v-model="form.flag_type">
                            <option value="">Any flag</option>
                            <option v-for="type in options.flag_types" :key="type" :value="type">{{ labelize(type) }}</option>
                        </select>
                    </label>
                    <label class="inline-flex min-h-11 items-center gap-3 rounded-xl border border-white/10 bg-slate-950 px-3 text-sm font-bold text-slate-200">
                        <input v-model="form.has_media" type="checkbox" class="rounded border-white/20 bg-slate-900 text-primary-500 focus:ring-primary-500" />
                        Has media
                    </label>
                    <label class="filter-field">
                        <span>Escrow</span>
                        <select v-model="form.escrow_funded">
                            <option value="">Any escrow state</option>
                            <option value="1">Escrow funded</option>
                            <option value="0">No escrow</option>
                        </select>
                    </label>
                    <div class="flex items-end gap-2 md:col-span-2">
                        <button type="button" class="primary-button" @click="apply">Apply filters</button>
                        <button type="button" class="secondary-button" @click="clearFilters">Clear all filters</button>
                    </div>
                </div>
            </Transition>

            <div v-if="activeFilterPills.length" class="mt-3 flex flex-wrap items-center gap-2">
                <button
                    v-for="pill in activeFilterPills"
                    :key="pill.key"
                    type="button"
                    class="rounded-full border border-primary-300/30 bg-primary-500/10 px-3 py-1.5 text-xs font-bold text-primary-100"
                    @click="removeFilter(pill.key)"
                >
                    {{ pill.label }} ×
                </button>
                <button type="button" class="text-xs font-black uppercase tracking-wide text-slate-400 underline decoration-white/20 underline-offset-4" @click="clearFilters">
                    Clear all
                </button>
            </div>
        </section>

        <Transition mode="out-in" enter-active-class="transition duration-150" enter-from-class="opacity-0" enter-to-class="opacity-100">
            <section :key="viewMode" class="mt-5">
                <div v-if="viewMode === 'table'" class="overflow-x-auto rounded-3xl border border-white/10 bg-slate-900/60 ring-1 ring-white/5">
                    <table class="min-w-full divide-y divide-white/10 text-left text-sm">
                        <thead class="bg-slate-950/70 text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">
                            <tr>
                                <th class="w-12 px-4 py-3">
                                    <input type="checkbox" class="rounded border-white/20 bg-slate-900 text-primary-500" :checked="allVisibleSelected" @change="toggleVisibleSelection" />
                                </th>
                                <th v-for="column in visibleColumns" :key="column.key" class="px-4 py-3">
                                    <button v-if="column.sort" type="button" class="inline-flex items-center gap-1 hover:text-primary-200" @click="toggleSort(column.sort)">
                                        {{ column.label }}
                                        <span v-if="form.sort === column.sort">↑</span>
                                        <span v-else-if="form.sort === `-${column.sort}`">↓</span>
                                    </button>
                                    <span v-else>{{ column.label }}</span>
                                </th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <tr
                                v-for="quest in quests.data"
                                :key="quest.id"
                                class="cursor-pointer transition hover:bg-white/5"
                                :class="[density === 'compact' ? 'text-xs' : '', quest.featured ? 'bg-amber-500/5' : '', quest.flags.length ? 'border-l-2 border-l-rose-400' : '']"
                                @click="openQuest(quest)"
                            >
                                <td class="px-4" @click.stop>
                                    <input type="checkbox" class="rounded border-white/20 bg-slate-900 text-primary-500" :checked="selectedIds.has(quest.id)" @change="toggleSelection(quest.id)" />
                                </td>
                                <td class="px-4" :class="density === 'compact' ? 'py-2' : 'py-4'">
                                    <p class="text-xs font-black text-slate-500">{{ quest.reference_code }}</p>
                                    <p class="max-w-xs truncate font-display font-bold text-white" :title="quest.title" v-html="highlight(quest.title)"></p>
                                </td>
                                <td class="px-4 font-semibold text-slate-300">
                                    <div class="flex items-center gap-2">
                                        <Avatar :src="quest.client.avatar_url" :name="quest.client.name || quest.client.email" />
                                        <div class="min-w-0">
                                            <p class="truncate text-white">{{ quest.client.name || 'Unknown client' }}</p>
                                            <p class="truncate text-xs text-slate-500">{{ quest.client.email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4">
                                    <p class="text-xs font-black uppercase tracking-wide text-primary-200">{{ quest.category.parent || quest.category.name || 'Uncategorised' }}</p>
                                    <p class="text-xs text-slate-500">{{ quest.category.name }}</p>
                                </td>
                                <td class="px-4 font-bold text-white">{{ quest.budget }}</td>
                                <td class="px-4">
                                    <span class="rounded-full bg-white/10 px-2 py-1 text-xs font-black text-slate-200">{{ quest.proposals_count }}</span>
                                </td>
                                <td class="px-4">
                                    <AdminStatusBadge :status="quest.admin_status" />
                                    <StatusBadge :label="quest.status_label" :tone="quest.status_tone" muted />
                                </td>
                                <td class="px-4">
                                    <div class="flex items-center gap-2">
                                <span v-if="quest.featured" class="text-amber-300" :title="quest.featured.label">★</span>
                                        <span v-if="quest.escrow.funded" class="text-blue-300" title="Escrow funded">🔒</span>
                                        <span v-if="quest.flags.length" :class="flagTone(quest.flags[0].priority)" title="Flagged">⚑</span>
                                <span v-if="quest.has_user_notice" class="text-primary-300" title="User notice active">📣</span>
                                <span v-if="quest.risk_signals?.length" class="text-rose-300" :title="quest.risk_signals.map((signal) => signal.name).join(', ')">!</span>
                                    </div>
                                </td>
                                <td class="px-4 text-xs font-bold text-slate-400" :title="formatDate(quest.created_at)">{{ relativeDate(quest.created_at) }}</td>
                                <td class="px-4 text-right" @click.stop>
                                    <button type="button" class="rounded-xl border border-white/10 px-3 py-2 text-xs font-black uppercase tracking-wide text-primary-200 hover:bg-white/5" @click="openQuest(quest)">
                                        Manage
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else-if="viewMode === 'cards'" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <QuestCard v-for="quest in quests.data" :key="quest.id" :quest="quest" :selected="selectedIds.has(quest.id)" @open="openQuest" @toggle="toggleSelection" />
                </div>

                <div v-else class="grid gap-4 overflow-x-auto xl:grid-cols-7">
                    <section v-for="column in kanbanColumns" :key="column.key" class="min-h-[30rem] min-w-[16rem] rounded-3xl border border-white/10 bg-slate-900/50 p-3" @dragover.prevent @drop="requestStatusChange(draggedQuest, column.status)">
                        <div class="mb-3 flex items-center justify-between">
                            <h3 class="text-xs font-black uppercase tracking-[0.18em] text-slate-300">{{ column.label }}</h3>
                            <span class="rounded-full bg-white/10 px-2 py-0.5 text-xs font-black text-slate-300">{{ column.items.length }} · {{ money(column.budget) }}</span>
                        </div>
                        <div class="space-y-3">
                            <button
                                v-for="quest in column.items"
                                :key="quest.id"
                                type="button"
                                class="w-full rounded-2xl border border-white/10 bg-slate-950/80 p-3 text-left transition hover:border-primary-400/50 hover:bg-slate-950"
                                draggable="true"
                                @dragstart="draggedQuest = quest"
                                @click="openQuest(quest)"
                            >
                                <p class="text-[10px] font-black text-slate-500">{{ quest.reference_code }}</p>
                                <p class="mt-1 line-clamp-2 text-sm font-bold text-white">{{ quest.title }}</p>
                                <div class="mt-3 flex items-center justify-between text-xs text-slate-400">
                                    <span>{{ quest.proposals_count }} proposals</span>
                                    <span v-if="quest.flags.length" :class="flagTone(quest.flags[0].priority)">⚑ {{ labelize(quest.flags[0].priority) }}</span>
                                    <span v-if="quest.has_user_notice" class="text-primary-300">📣</span>
                                </div>
                            </button>
                        </div>
                    </section>
                </div>
            </section>
        </Transition>

        <nav v-if="quests.links?.length > 3" class="mt-6 flex flex-wrap items-center justify-center gap-2" aria-label="Pagination">
            <component
                :is="link.url ? Link : 'span'"
                v-for="link in quests.links"
                :key="String(link.label) + (link.url || 'gap')"
                :href="link.url || undefined"
                preserve-state
                preserve-scroll
                class="min-w-[2.5rem] rounded-lg px-3 py-1.5 text-center text-xs font-bold transition"
                :class="link.active ? 'bg-primary-500 text-slate-950' : 'border border-white/10 text-slate-200 hover:bg-white/5'"
            >
                <span v-html="link.label" />
            </component>
        </nav>

        <div
            v-if="selectedIds.size"
            class="fixed inset-x-4 bottom-4 z-40 rounded-3xl border border-primary-300/30 bg-slate-950/95 p-4 shadow-2xl shadow-slate-950/60 ring-1 ring-white/10 backdrop-blur md:left-1/2 md:max-w-4xl md:-translate-x-1/2"
        >
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <p class="font-bold text-white">{{ selectedIds.size }} quest{{ selectedIds.size === 1 ? '' : 's' }} selected</p>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="secondary-button">Export selected</button>
                    <button type="button" class="secondary-button" @click="bulkAction = 'flag'">Flag all</button>
                    <button type="button" class="danger-button" @click="bulkAction = 'suspend'">Suspend all</button>
                    <button type="button" class="secondary-button" @click="selectedIds.clear()">Clear</button>
                </div>
            </div>
            <p v-if="bulkAction" class="mt-3 text-xs font-semibold text-amber-200">
                Bulk {{ bulkAction }} is staged with impact preview. Connect this to the confirmation drawer before enabling one-click execution.
            </p>
        </div>

        <AdminSlideOver
            :open="Boolean(activeQuest)"
            :title="activeQuest?.title || 'Quest detail'"
            :eyebrow="activeQuest?.reference_code || 'Quest operations'"
            width-class="max-w-full sm:max-w-4xl xl:max-w-6xl"
            panel-class="border-slate-200 bg-slate-50 text-slate-950 shadow-2xl shadow-slate-300/50 ring-1 ring-slate-200"
            @close="closeQuest"
        >
            <div v-if="detailLoading" class="space-y-4">
                <div class="h-24 animate-pulse rounded-3xl bg-white/10"></div>
                <div class="h-80 animate-pulse rounded-3xl bg-white/10"></div>
            </div>

            <div v-else-if="questDetail" class="space-y-5 quest-detail-light-panel">
                <div class="sticky top-0 z-10 -mx-5 -mt-4 border-b border-slate-200 bg-white/95 px-5 py-4 shadow-sm backdrop-blur">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">{{ questDetail.overview.quest.reference_code }}</p>
                            <h2 class="font-display text-2xl font-black text-slate-950">{{ questDetail.overview.quest.title }}</h2>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <span class="text-[10px] font-black uppercase tracking-wide text-slate-500">Admin Status</span>
                                <AdminStatusBadge :status="questDetail.overview.quest.admin_status" />
                                <span class="ml-2 text-[10px] font-black uppercase tracking-wide text-slate-500">Platform Status</span>
                                <StatusBadge :label="questDetail.overview.quest.status_label" :tone="questDetail.overview.quest.status_tone" muted />
                                <span v-if="questDetail.overview.quest.featured" class="rounded-full bg-amber-100 px-3 py-1 text-xs font-black uppercase tracking-wide text-amber-800">
                                    ★ {{ questDetail.overview.quest.featured.label }}
                                </span>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="primary-button" @click="actionPanel = actionPanel === 'status' ? null : 'status'">Change admin status</button>
                            <button type="button" class="secondary-button" @click="actionPanel = actionPanel === 'flag' ? null : 'flag'">Flag quest</button>
                            <button type="button" class="secondary-button" @click="actionPanel = actionPanel === 'notice' ? null : 'notice'">Post notice</button>
                            <button type="button" class="secondary-button text-amber-700" @click="actionPanel = actionPanel === 'boost' ? null : 'boost'">Upgrade to featured</button>
                        </div>
                    </div>

                    <div v-if="actionPanel" class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 shadow-inner shadow-slate-200/70">
                        <StatusPanel v-if="actionPanel === 'status'" :options="options.admin_status_options" :busy="actionBusy" @submit="submitStatus" @cancel="actionPanel = null" />
                        <FlagPanel v-else-if="actionPanel === 'flag'" :options="options" :busy="actionBusy" @submit="submitFlag" @cancel="actionPanel = null" />
                        <ResolveFlagPanel v-else-if="actionPanel === 'resolve'" :flag="resolvingFlag" :busy="actionBusy" @submit="submitResolveFlag" @cancel="actionPanel = null" />
                        <NoticePanel v-else-if="actionPanel === 'notice'" :busy="actionBusy" @submit="submitNotice" @cancel="actionPanel = null" />
                        <BoostPanel v-else :options="options" :busy="actionBusy" @submit="submitBoost" @cancel="actionPanel = null" />
                    </div>
                </div>

                <AdminTabs v-model="activeTab" :tabs="tabs" id-prefix="quest-engine" />

                <AdminTabPanel :current-tab="activeTab" value="overview" id-prefix="quest-engine">
                    <div class="grid gap-5 xl:grid-cols-[1fr_20rem]">
                        <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm ring-1 ring-slate-100">
                            <h3 class="font-display text-xl font-black text-slate-950">{{ questDetail.overview.quest.title }}</h3>
                            <div
                                v-if="questDetail.overview.quest.description"
                                class="quest-description-html mt-3 text-sm leading-7 text-slate-700"
                                v-html="safeQuestDescription(questDetail.overview.quest.description)"
                            ></div>
                            <p v-else class="mt-3 rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-5 text-sm font-semibold text-slate-500">
                                No written description was supplied.
                            </p>
                            <dl class="mt-5 grid gap-3 sm:grid-cols-2">
                                <InfoItem label="Category" :value="[questDetail.overview.quest.category.parent, questDetail.overview.quest.category.name].filter(Boolean).join(' / ') || 'Uncategorised'" />
                                <InfoItem label="Budget" :value="questDetail.overview.quest.budget" />
                                <InfoItem label="Project type" :value="labelize(questDetail.overview.quest.project_type || 'not set')" />
                                <InfoItem label="Location" :value="questDetail.overview.quest.location || 'Remote / not specified'" />
                                <InfoItem label="Due date" :value="formatDate(questDetail.overview.quest.due_at)" />
                                <InfoItem label="Media" :value="`${questDetail.media.items.length} attachment(s)`" />
                            </dl>
                        </article>
                        <aside class="space-y-4">
                            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm ring-1 ring-slate-100">
                                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Client context</p>
                                <div class="mt-4 flex items-center gap-3">
                                    <Avatar :src="questDetail.overview.client_context.avatar_url" :name="questDetail.overview.client_context.name" large />
                                    <div>
                                        <p class="font-bold text-slate-950">{{ questDetail.overview.client_context.name }}</p>
                                        <p class="text-xs text-slate-500">{{ questDetail.overview.client_context.email }}</p>
                                    </div>
                                </div>
                                <dl class="mt-4 space-y-2 text-sm">
                                    <InfoItem label="Verification tier" :value="questDetail.overview.client_context.verification_tier || 'Tier 0'" />
                                    <InfoItem label="Quests posted" :value="questDetail.overview.client_context.quests_posted" />
                                    <InfoItem label="Total spent" :value="questDetail.overview.client_context.amount_spent" />
                                </dl>
                            </div>
                            <FlagStack :flags="questDetail.flags" @resolve="openResolve" />
                        </aside>
                    </div>
                    <Timeline :items="questDetail.overview.timeline" />
                </AdminTabPanel>

                <AdminTabPanel :current-tab="activeTab" value="proposals" id-prefix="quest-engine">
                    <div class="grid gap-3 md:grid-cols-4">
                        <InfoCard label="Total proposals" :value="questDetail.proposals.summary.total" />
                        <InfoCard label="Average quote" :value="questDetail.proposals.summary.average" />
                        <InfoCard label="Lowest quote" :value="questDetail.proposals.summary.lowest" />
                        <InfoCard label="Highest quote" :value="questDetail.proposals.summary.highest" />
                    </div>
                    <div class="mt-4 space-y-3">
                        <article v-for="proposal in questDetail.proposals.items" :key="proposal.id" class="rounded-3xl border border-white/10 bg-slate-900/60 p-4">
                            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                <div class="flex gap-3">
                                    <Avatar :src="proposal.freelancer.avatar_url" :name="proposal.freelancer.name" />
                                    <div>
                                        <p class="font-bold text-white">{{ proposal.freelancer.name }}</p>
                                        <p class="text-xs text-slate-500">{{ proposal.freelancer.email }} · {{ proposal.freelancer.verification_tier || 'Tier 0' }}</p>
                                    </div>
                                </div>
                                <p class="font-display text-lg font-black text-primary-100">{{ proposal.quoted_amount }}</p>
                            </div>
                            <p class="mt-3 line-clamp-3 text-sm leading-6 text-slate-300">{{ proposal.pitch || proposal.scope_detail || 'No cover letter supplied.' }}</p>
                        </article>
                    </div>
                </AdminTabPanel>

                <AdminTabPanel :current-tab="activeTab" value="escrow" id-prefix="quest-engine">
                    <div class="grid gap-4 lg:grid-cols-[20rem_1fr]">
                        <div class="space-y-4">
                            <div class="rounded-3xl border border-white/10 bg-slate-900/70 p-5">
                                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Contract & escrow</p>
                                <dl class="mt-4 space-y-3">
                                    <InfoItem label="Has contract" :value="questDetail.escrow.has_contract ? 'Yes' : 'No'" />
                                    <InfoItem label="Escrow status" :value="questDetail.escrow.contract.escrow_status || 'Not funded'" />
                                    <InfoItem label="Paid out" :value="questDetail.escrow.contract.paid_out" />
                                    <InfoItem label="Refunded" :value="questDetail.escrow.contract.refunded" />
                                    <InfoItem
                                        v-if="questDetail.escrow.contract.receipt_url"
                                        label="VAT receipt"
                                        :value="'Printable summary'"
                                    />
                                </dl>
                                <a
                                    v-if="questDetail.escrow.contract.receipt_url"
                                    :href="questDetail.escrow.contract.receipt_url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="secondary-button mt-4 inline-flex w-full justify-center"
                                >
                                    Open contract receipt
                                </a>
                            </div>
                            <div v-if="questDetail.release_controls" class="rounded-3xl border border-amber-300/30 bg-amber-400/5 p-5">
                                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-amber-200">Release controls</p>
                                <dl class="mt-3 space-y-2 text-xs font-semibold text-slate-300">
                                    <div class="flex justify-between gap-2"><dt>Contract value</dt><dd class="font-black text-white">{{ questDetail.release_controls.amount }}</dd></div>
                                    <div class="flex justify-between gap-2"><dt>High-value threshold</dt><dd>{{ questDetail.release_controls.high_value_threshold }}</dd></div>
                                    <div class="flex justify-between gap-2"><dt>Needs authorisation</dt><dd>{{ questDetail.release_controls.requires_authorization ? 'Yes' : 'No' }}</dd></div>
                                    <div class="flex justify-between gap-2"><dt>Authorised</dt><dd>{{ questDetail.release_controls.has_authorization ? 'Yes' : 'No' }}</dd></div>
                                    <div class="flex justify-between gap-2"><dt>On hold</dt><dd>{{ questDetail.release_controls.release_held ? 'Yes' : 'No' }}</dd></div>
                                </dl>
                                <form class="mt-4 space-y-2" @submit.prevent="submitReleaseAuthorize">
                                    <textarea v-model="releaseControlForm.authorize_reason" rows="2" class="panel-input w-full text-xs" placeholder="Authorise high-value release (min 10 chars)…" />
                                    <button type="submit" class="primary-button w-full text-xs" :disabled="actionBusy || releaseControlForm.authorize_reason.length < 10">
                                        Authorise release
                                    </button>
                                </form>
                                <form class="mt-3 space-y-2" @submit.prevent="submitReleaseHold">
                                    <textarea v-model="releaseControlForm.hold_reason" rows="2" class="panel-input w-full text-xs" placeholder="Hold reason (min 10 chars)…" />
                                    <label class="flex items-center gap-2 text-xs font-bold text-slate-300">
                                        <input v-model="releaseControlForm.indefinite" type="checkbox" /> Indefinite hold
                                    </label>
                                    <input v-if="!releaseControlForm.indefinite" v-model="releaseControlForm.hold_until" type="date" class="panel-input w-full text-xs" />
                                    <button type="submit" class="danger-button w-full text-xs" :disabled="actionBusy || releaseControlForm.hold_reason.length < 10">Place hold</button>
                                </form>
                                <form class="mt-3 space-y-2" @submit.prevent="submitReleaseLiftHold">
                                    <textarea v-model="releaseControlForm.lift_reason" rows="2" class="panel-input w-full text-xs" placeholder="Lift hold reason…" />
                                    <button type="submit" class="secondary-button w-full text-xs" :disabled="actionBusy || releaseControlForm.lift_reason.length < 10">Lift hold</button>
                                </form>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <div class="rounded-3xl border border-white/10 bg-slate-900/60 p-5">
                                <div class="flex items-center justify-between gap-3">
                                    <h3 class="font-display text-lg font-black text-white">Completion timeline</h3>
                                    <Link
                                        :href="route('admin.quest-completion-events.index', { q: activeQuest?.title })"
                                        class="text-[10px] font-black uppercase tracking-wide text-primary-300 hover:text-white"
                                    >
                                        All events →
                                    </Link>
                                </div>
                                <ol class="mt-4 space-y-3">
                                    <li
                                        v-for="(item, idx) in questDetail.completion_timeline || []"
                                        :key="idx"
                                        class="rounded-2xl border border-white/10 bg-slate-950/70 px-3 py-2"
                                    >
                                        <p class="text-sm font-bold text-white">{{ item.label }}</p>
                                        <p class="text-xs text-slate-500">{{ item.actor }} · {{ formatDate(item.at) }}</p>
                                        <p v-if="item.detail" class="mt-1 font-mono text-[10px] text-slate-400">{{ item.detail }}</p>
                                    </li>
                                    <li v-if="!(questDetail.completion_timeline || []).length" class="text-sm text-slate-400">No completion events logged yet.</li>
                                </ol>
                            </div>
                            <div class="rounded-3xl border border-white/10 bg-slate-900/60 p-5">
                                <h3 class="font-display text-lg font-black text-white">Escrow ledger</h3>
                                <div class="mt-4 space-y-3">
                                    <div v-for="entry in questDetail.escrow.ledger.entries || []" :key="entry.reference" class="rounded-2xl border border-white/10 bg-slate-950/70 p-3">
                                        <div class="flex items-center justify-between gap-3">
                                            <p class="font-bold text-white">{{ entry.description }}</p>
                                            <p class="font-black text-primary-100">{{ entry.amount }}</p>
                                        </div>
                                        <p class="mt-1 text-xs text-slate-500">{{ entry.reference }} · {{ formatDate(entry.occurred_at) }}</p>
                                    </div>
                                    <p v-if="!(questDetail.escrow.ledger.entries || []).length" class="text-sm text-slate-400">No escrow ledger entries yet.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </AdminTabPanel>

                <AdminTabPanel :current-tab="activeTab" value="media" id-prefix="quest-engine">
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <article v-for="media in questDetail.media.items" :key="media.id" class="rounded-3xl border border-white/10 bg-slate-900/60 p-4">
                            <img v-if="media.is_image" :src="media.url" :alt="media.name" class="h-40 w-full rounded-2xl object-cover" />
                            <div v-else class="flex h-40 items-center justify-center rounded-2xl bg-slate-950 text-4xl text-slate-500">▣</div>
                            <p class="mt-3 truncate font-bold text-white">{{ media.name }}</p>
                            <p class="text-xs text-slate-500">{{ media.size }} · {{ media.stage }}</p>
                            <div class="mt-3 flex gap-2">
                                <a :href="media.url" target="_blank" class="secondary-button flex-1 text-center">Preview</a>
                                <button type="button" class="danger-button flex-1">Remove</button>
                            </div>
                        </article>
                    </div>
                    <p v-if="!questDetail.media.items.length" class="rounded-3xl border border-dashed border-white/10 p-8 text-center text-sm text-slate-400">No quest media uploaded.</p>
                </AdminTabPanel>

                <AdminTabPanel :current-tab="activeTab" value="flags" id-prefix="quest-engine">
                    <div class="grid gap-4 xl:grid-cols-2">
                        <FlagStack :flags="questDetail.flags" @resolve="openResolve" />
                        <div class="rounded-3xl border border-white/10 bg-slate-900/70 p-5">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">User notices</p>
                                <button type="button" class="secondary-button" @click="actionPanel = 'notice'">Add notice</button>
                            </div>
                            <div class="mt-4 space-y-3">
                                <article v-for="notice in questDetail.notices" :key="notice.id" class="rounded-2xl border border-white/10 bg-slate-950/70 p-3">
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="text-sm font-black text-white">{{ labelize(notice.type) }}</p>
                                        <span class="rounded-full px-2 py-1 text-[10px] font-black" :class="notice.visible_to_users ? 'bg-emerald-400/15 text-emerald-100' : 'bg-slate-500/15 text-slate-300'">
                                            {{ notice.visible_to_users ? 'Visible' : 'Hidden' }}
                                        </span>
                                    </div>
                                    <p class="mt-2 text-sm leading-6 text-slate-300">{{ notice.body }}</p>
                                    <p class="mt-2 text-xs text-slate-500">{{ notice.creator || 'Admin' }} · {{ formatDate(notice.created_at) }}</p>
                                </article>
                                <p v-if="!questDetail.notices.length" class="rounded-2xl border border-dashed border-white/10 p-5 text-center text-sm text-slate-400">No notices posted on this Quest.</p>
                            </div>
                        </div>
                    </div>
                </AdminTabPanel>

                <AdminTabPanel :current-tab="activeTab" value="activity" id-prefix="quest-engine">
                    <Timeline :items="questDetail.activity.items.map((item) => ({ label: labelize(item.action), actor: item.actor || 'System', at: item.created_at, meta: item.properties }))" />
                </AdminTabPanel>

                <AdminTabPanel :current-tab="activeTab" value="notes" id-prefix="quest-engine">
                    <div class="grid gap-4 xl:grid-cols-[1fr_20rem]">
                        <div class="space-y-3">
                            <article v-for="note in questDetail.notes" :key="note.id" class="rounded-3xl border border-white/10 bg-slate-900/60 p-4" :class="note.is_pinned ? 'border-amber-300/40 bg-amber-400/10' : ''">
                                <p class="text-sm font-bold text-white">{{ note.admin.name || 'Admin' }}</p>
                                <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-300">{{ note.body }}</p>
                                <p class="mt-2 text-xs text-slate-500">{{ formatDate(note.created_at) }}</p>
                            </article>
                            <p v-if="!questDetail.notes.length" class="rounded-3xl border border-dashed border-white/10 p-8 text-center text-sm text-slate-400">No internal notes yet.</p>
                        </div>
                        <form class="rounded-3xl border border-white/10 bg-slate-900/70 p-4" @submit.prevent="submitNote(noteForm)">
                            <p class="font-bold text-white">Add internal note</p>
                            <textarea v-model="noteForm.body" class="panel-input mt-3 min-h-32 w-full" placeholder="Write private moderation context..." />
                            <label class="mt-3 flex items-center gap-2 text-sm font-bold text-slate-300">
                                <input v-model="noteForm.is_pinned" type="checkbox" /> Pin note
                            </label>
                            <button type="submit" class="primary-button mt-4 w-full" :disabled="actionBusy || noteForm.body.length < 2">
                                <span v-if="actionBusy" class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"></span>
                                Save note
                            </button>
                        </form>
                    </div>
                </AdminTabPanel>

                <AdminTabPanel :current-tab="activeTab" value="communications" id-prefix="quest-engine">
                    <div class="rounded-3xl border border-white/10 bg-slate-900/60 p-5">
                        <h3 class="font-display text-xl font-black text-white">Quest communications</h3>
                        <p class="mt-2 text-sm text-slate-400">Compose contextual admin notifications to the client or proposing freelancers from this panel. Message delivery can be connected to the existing notification centre without leaving the Quest Engine.</p>
                        <textarea class="mt-4 min-h-32 w-full rounded-2xl border border-white/10 bg-slate-950 p-4 text-sm text-white outline-none focus:border-primary-400/60" placeholder="Write a message with quest context attached..."></textarea>
                        <div class="mt-3 flex justify-end">
                            <button type="button" class="primary-button">Send message</button>
                        </div>
                    </div>
                </AdminTabPanel>
            </div>
        </AdminSlideOver>
        <div class="fixed bottom-5 right-5 z-[100] space-y-2">
            <div v-for="toast in toasts" :key="toast.id" class="rounded-2xl border px-4 py-3 text-sm font-bold shadow-2xl" :class="toast.type === 'error' ? 'border-rose-300 bg-rose-950 text-rose-50' : 'border-emerald-300 bg-emerald-950 text-emerald-50'">
                {{ toast.message }}
            </div>
        </div>
        </div>
    </AdminShell>
</template>

<script setup>
import AdminSlideOver from '@/Components/Admin/AdminSlideOver.vue';
import AdminTabPanel from '@/Components/Admin/AdminTabPanel.vue';
import AdminTabs from '@/Components/Admin/AdminTabs.vue';
import AdminShell from '@/Layouts/AdminShell.vue';
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, defineComponent, h, reactive, ref, watch } from 'vue';

const props = defineProps({
    summary: { type: Array, default: () => [] },
    quests: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    options: { type: Object, required: true },
});

const viewModes = [
    { value: 'table', label: 'Table' },
    { value: 'cards', label: 'Cards' },
    { value: 'kanban', label: 'Kanban' },
];

const tabs = [
    { key: 'overview', label: 'Overview' },
    { key: 'proposals', label: 'Proposals' },
    { key: 'escrow', label: 'Contract & Escrow' },
    { key: 'media', label: 'Media' },
    { key: 'flags', label: 'Flags & Notices' },
    { key: 'communications', label: 'Contact & Comms' },
    { key: 'activity', label: 'Activity & Audit' },
    { key: 'notes', label: 'Admin Notes' },
];

const form = reactive({
    q: props.filters.q ?? '',
    quick: props.filters.quick ?? '',
    status: props.filters.status ?? '',
    admin_status: props.filters.admin_status ?? '',
    budget_min: props.filters.budget_min ?? '',
    budget_max: props.filters.budget_max ?? '',
    posted_from: props.filters.posted_from ?? '',
    posted_to: props.filters.posted_to ?? '',
    project_type: props.filters.project_type ?? '',
    verification_tier: props.filters.verification_tier ?? '',
    proposals_min: props.filters.proposals_min ?? '',
    proposals_max: props.filters.proposals_max ?? '',
    has_media: Boolean(props.filters.has_media),
    escrow_funded: props.filters.escrow_funded ?? '',
    flag_type: props.filters.flag_type ?? '',
    sort: props.filters.sort ?? '-created_at',
    per_page: Number(props.filters.per_page ?? 25),
});

const viewMode = ref(localStorage.getItem('adminQuestViewMode') || 'table');
const density = ref(localStorage.getItem('adminQuestDensity') || 'comfortable');
const advancedOpen = ref(false);
const selectedIds = reactive(new Set());
const activeQuest = ref(null);
const questDetail = ref(null);
const detailLoading = ref(false);
const activeTab = ref('overview');
const actionPanel = ref(null);
const actionBusy = ref(false);
const draggedQuest = ref(null);
const bulkAction = ref(null);
const resolvingFlag = ref(null);
const toasts = ref([]);
const noteForm = reactive({ body: '', is_pinned: false });
const releaseControlForm = reactive({
    authorize_reason: '',
    hold_reason: '',
    hold_until: '',
    indefinite: false,
    lift_reason: '',
});

watch(viewMode, (value) => localStorage.setItem('adminQuestViewMode', value));
watch(density, (value) => localStorage.setItem('adminQuestDensity', value));
watch(() => form.q, debounce(() => apply(), 350));

const columns = [
    { key: 'quest', label: 'Quest', sort: 'created_at' },
    { key: 'client', label: 'Client' },
    { key: 'category', label: 'Category' },
    { key: 'budget', label: 'Budget', sort: 'budget' },
    { key: 'proposals', label: 'Proposals', sort: 'proposals' },
    { key: 'status', label: 'Statuses' },
    { key: 'signals', label: 'Signals' },
    { key: 'posted', label: 'Posted', sort: 'created_at' },
];

const visibleColumns = computed(() => columns);
const allVisibleSelected = computed(() => props.quests.data?.length && props.quests.data.every((quest) => selectedIds.has(quest.id)));
const selectedRows = computed(() => props.quests.data.filter((quest) => selectedIds.has(quest.id)));

const kanbanColumns = computed(() => (props.options.admin_status_options || []).map((status) => {
    const items = props.quests.data.filter((quest) => quest.admin_status?.value === status.value);
    return {
        key: status.value,
        label: status.label,
        status: status.value,
        items,
        budget: items.reduce((total, quest) => total + Number(quest.budget_minor || 0), 0),
    };
}));

const activeFilterPills = computed(() => {
    const labels = [];
    Object.entries(form).forEach(([key, value]) => {
        if (value === '' || value === false || value === null || key === 'per_page' || key === 'sort') {
            return;
        }
        labels.push({ key, label: `${labelize(key)}: ${typeof value === 'boolean' ? 'Yes' : labelize(String(value))}` });
    });
    return labels;
});

function apply(extra = {}) {
    router.get(route('admin.quests.index'), params({ ...form, ...extra }), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['summary', 'quests', 'filters'],
    });
}

function applyShortcut(filter) {
    form.quick = filter.quick || '';
    form.admin_status = filter.admin_status || '';
    form.status = '';
    apply();
}

function setQuick(value) {
    form.quick = value;
    apply();
}

function clearFilters() {
    Object.assign(form, {
        q: '',
        quick: '',
        status: '',
        admin_status: '',
        budget_min: '',
        budget_max: '',
        posted_from: '',
        posted_to: '',
        project_type: '',
        verification_tier: '',
        proposals_min: '',
        proposals_max: '',
        has_media: false,
        escrow_funded: '',
        flag_type: '',
        sort: '-created_at',
        per_page: form.per_page,
    });
    apply();
}

function removeFilter(key) {
    form[key] = typeof form[key] === 'boolean' ? false : '';
    apply();
}

function toggleSort(column) {
    form.sort = form.sort === column ? `-${column}` : column;
    apply();
}

function params(source) {
    return Object.fromEntries(Object.entries(source).filter(([, value]) => value !== '' && value !== false && value !== null && value !== undefined));
}

function toggleSelection(id) {
    selectedIds.has(id) ? selectedIds.delete(id) : selectedIds.add(id);
}

function toggleVisibleSelection() {
    if (allVisibleSelected.value) {
        props.quests.data.forEach((quest) => selectedIds.delete(quest.id));
    } else {
        props.quests.data.forEach((quest) => selectedIds.add(quest.id));
    }
}

async function openQuest(quest) {
    activeQuest.value = quest;
    activeTab.value = 'overview';
    actionPanel.value = null;
    detailLoading.value = true;
    try {
        const { data } = await axios.get(route('admin.quests.detail', quest.route_key));
        questDetail.value = data;
    } finally {
        detailLoading.value = false;
    }
}

function closeQuest() {
    activeQuest.value = null;
    questDetail.value = null;
}

async function submitStatus(payload) {
    await postAction(() => axios.patch(route('admin.quests.admin-status', activeQuest.value.route_key), payload));
}

async function submitFlag(payload) {
    await postAction(() => axios.post(route('admin.quests.flags.store', activeQuest.value.route_key), payload));
}

async function submitBoost(payload) {
    await postAction(() => axios.post(route('admin.quests.boost', activeQuest.value.route_key), payload));
}

async function submitNotice(payload) {
    await postAction(() => axios.post(route('admin.quests.notices.store', activeQuest.value.route_key), payload));
}

async function submitNote(payload) {
    await postAction(() => axios.post(route('admin.quests.notes.store', activeQuest.value.route_key), payload));
    noteForm.body = '';
    noteForm.is_pinned = false;
}

async function submitResolveFlag(payload) {
    if (!resolvingFlag.value) {
        return;
    }
    await postAction(() => axios.post(route('admin.quests.flags.resolve', [activeQuest.value.route_key, resolvingFlag.value.id]), payload));
}

async function submitReleaseAuthorize() {
    if (!activeQuest.value?.id) {
        return;
    }
    await postAction(() =>
        axios.post(route('admin.quests.release.authorize', activeQuest.value.id), {
            reason: releaseControlForm.authorize_reason,
        }),
    );
    releaseControlForm.authorize_reason = '';
}

async function submitReleaseHold() {
    if (!activeQuest.value?.id) {
        return;
    }
    await postAction(() =>
        axios.post(route('admin.quests.release.hold', activeQuest.value.id), {
            reason: releaseControlForm.hold_reason,
            hold_until: releaseControlForm.indefinite ? null : releaseControlForm.hold_until,
            indefinite: releaseControlForm.indefinite,
        }),
    );
    releaseControlForm.hold_reason = '';
}

async function submitReleaseLiftHold() {
    if (!activeQuest.value?.id) {
        return;
    }
    await postAction(() =>
        axios.post(route('admin.quests.release.lift-hold', activeQuest.value.id), {
            reason: releaseControlForm.lift_reason,
        }),
    );
    releaseControlForm.lift_reason = '';
}

async function postAction(callback) {
    actionBusy.value = true;
    try {
        const response = await callback();
        const { data } = await axios.get(route('admin.quests.detail', activeQuest.value.route_key));
        questDetail.value = data;
        actionPanel.value = null;
        router.reload({ only: ['summary', 'quests'], preserveScroll: true });
        toast(response?.data?.message || 'Quest action completed.');
    } catch (error) {
        toast(error.response?.data?.message || Object.values(error.response?.data?.errors || {})?.flat?.()?.[0] || 'Quest action failed. Please review the form and try again.', 'error');
        throw error;
    } finally {
        actionBusy.value = false;
    }
}

function requestStatusChange(quest, status) {
    if (!quest || quest.admin_status?.value === status) {
        return;
    }
    activeQuest.value = quest;
    openQuest(quest).then(() => {
        actionPanel.value = 'status';
    });
}

function openResolve(flag) {
    resolvingFlag.value = flag;
    actionPanel.value = 'resolve';
}

function quickCount(value) {
    const tile = props.summary.find((item) => item.filter?.quick === value);
    if (tile) {
        return tile.value;
    }
    if (value === '') {
        return props.quests.total ?? props.quests.data.length;
    }
    if (String(value).startsWith('admin:')) {
        return props.quests.data.filter((quest) => quest.admin_status?.value === String(value).replace('admin:', '')).length;
    }
    return props.quests.data.filter((quest) => quest.status === value || (value === 'flagged' && quest.flags.length) || (value === 'featured' && quest.featured)).length;
}

function toast(message, type = 'success') {
    const id = Date.now() + Math.random();
    toasts.value.push({ id, message, type });
    window.setTimeout(() => {
        toasts.value = toasts.value.filter((item) => item.id !== id);
    }, 4200);
}

function money(minor) {
    return new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', maximumFractionDigits: 0 }).format(Number(minor || 0) / 100);
}

function highlight(text) {
    const value = String(text || '');
    const needle = String(form.q || '').trim();
    if (!needle) {
        return escapeHtml(value);
    }
    return escapeHtml(value).replace(new RegExp(`(${escapeRegExp(needle)})`, 'ig'), '<mark class="rounded bg-primary-300/25 px-0.5 text-primary-100">$1</mark>');
}

function labelize(value) {
    return String(value || '').replace(/[_-]/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());
}

function formatDate(value) {
    return value ? new Intl.DateTimeFormat('en-NG', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '—';
}

function relativeDate(value) {
    if (!value) {
        return '—';
    }
    const diff = Date.now() - new Date(value).getTime();
    const days = Math.floor(diff / 86400000);
    if (days > 0) {
        return `${days} day${days === 1 ? '' : 's'} ago`;
    }
    const hours = Math.max(1, Math.floor(diff / 3600000));
    return `${hours} hour${hours === 1 ? '' : 's'} ago`;
}

function flagTone(priority) {
    return {
        low: 'text-slate-300',
        medium: 'text-blue-300',
        high: 'text-amber-300',
        critical: 'text-rose-300',
    }[priority] || 'text-slate-300';
}

function escapeRegExp(value) {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function escapeHtml(value) {
    return value.replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
}

function safeQuestDescription(value) {
    const raw = String(value || '');
    if (!raw.trim()) {
        return '';
    }

    const withoutDangerousBlocks = raw
        .replace(/<script[\s\S]*?>[\s\S]*?<\/script>/gi, '')
        .replace(/<style[\s\S]*?>[\s\S]*?<\/style>/gi, '');

    const template = document.createElement('template');
    template.innerHTML = withoutDangerousBlocks;
    const allowedTags = new Set(['P', 'BR', 'STRONG', 'B', 'EM', 'I', 'U', 'UL', 'OL', 'LI', 'A', 'H2', 'H3', 'BLOCKQUOTE']);

    template.content.querySelectorAll('*').forEach((node) => {
        if (!allowedTags.has(node.tagName)) {
            node.replaceWith(document.createTextNode(node.textContent || ''));
            return;
        }

        [...node.attributes].forEach((attribute) => {
            const name = attribute.name.toLowerCase();
            if (node.tagName === 'A' && ['href', 'target', 'rel'].includes(name)) {
                return;
            }
            node.removeAttribute(attribute.name);
        });

        if (node.tagName === 'A') {
            node.setAttribute('target', '_blank');
            node.setAttribute('rel', 'noopener noreferrer');
        }
    });

    return template.innerHTML || escapeHtml(raw);
}

function debounce(fn, wait) {
    let timeout;
    return (...args) => {
        clearTimeout(timeout);
        timeout = setTimeout(() => fn(...args), wait);
    };
}

const Avatar = defineComponent({
    props: { src: String, name: String, large: Boolean },
    setup(props) {
        return () => props.src
            ? h('img', { src: props.src, alt: props.name, class: `${props.large ? 'h-14 w-14' : 'h-10 w-10'} rounded-full object-cover ring-2 ring-white/10` })
            : h('span', { class: `${props.large ? 'h-14 w-14 text-lg' : 'h-10 w-10 text-sm'} inline-flex shrink-0 items-center justify-center rounded-full bg-primary-500/15 font-black text-primary-100 ring-2 ring-white/10` }, String(props.name || '?').charAt(0).toUpperCase());
    },
});

const StatusBadge = defineComponent({
    props: { label: String, tone: String, muted: Boolean },
    setup(props) {
        const classes = {
            primary: 'bg-primary-500/15 text-primary-100 border-primary-300/20',
            blue: 'bg-blue-500/15 text-blue-100 border-blue-300/20',
            green: 'bg-emerald-500/15 text-emerald-100 border-emerald-300/20',
            red: 'bg-rose-500/15 text-rose-100 border-rose-300/20',
            gray: 'bg-slate-500/15 text-slate-200 border-slate-300/20',
            dark_red: 'bg-red-950/70 text-red-100 border-red-500/30',
            amber: 'bg-amber-500/15 text-amber-100 border-amber-300/20',
        };
        return () => h('span', { class: `inline-flex rounded-full border px-2.5 py-1 text-[10px] font-black uppercase tracking-wide ${props.muted ? 'opacity-70' : ''} ${classes[props.tone] || classes.gray}` }, props.label);
    },
});

const AdminStatusBadge = defineComponent({
    props: { status: Object },
    setup(props) {
        const classes = {
            gray: 'bg-slate-500/15 text-slate-100 border-slate-300/20',
            orange: 'bg-orange-500/15 text-orange-100 border-orange-300/30',
            indigo: 'bg-indigo-500/15 text-indigo-100 border-indigo-300/30',
            purple: 'bg-purple-500/15 text-purple-100 border-purple-300/30',
            amber: 'bg-amber-500/15 text-amber-100 border-amber-300/30',
            yellow: 'bg-yellow-500/15 text-yellow-100 border-yellow-300/30',
            dark_red: 'bg-red-950/80 text-red-100 border-red-500/40',
            green: 'bg-emerald-500/15 text-emerald-100 border-emerald-300/30',
        };
        return () => h('span', { class: `inline-flex rounded-full border px-3 py-1 text-[10px] font-black uppercase tracking-wide ${classes[props.status?.tone] || classes.gray}` }, props.status?.label || 'Clear');
    },
});

const InfoItem = defineComponent({
    props: { label: String, value: [String, Number] },
    setup(props) {
        return () => h('div', { class: 'rounded-2xl border border-white/10 bg-slate-950/60 p-3' }, [
            h('dt', { class: 'text-[10px] font-black uppercase tracking-[0.18em] text-slate-500' }, props.label),
            h('dd', { class: 'mt-1 text-sm font-bold text-white' }, props.value || '—'),
        ]);
    },
});

const InfoCard = defineComponent({
    props: { label: String, value: [String, Number] },
    setup(props) {
        return () => h('div', { class: 'rounded-3xl border border-white/10 bg-slate-900/70 p-4' }, [
            h('p', { class: 'text-[10px] font-black uppercase tracking-[0.18em] text-slate-500' }, props.label),
            h('p', { class: 'mt-2 font-display text-2xl font-black text-white' }, props.value),
        ]);
    },
});

const QuestCard = defineComponent({
    props: { quest: Object, selected: Boolean },
    emits: ['open', 'toggle'],
    setup(props, { emit }) {
        return () => h('article', { class: `rounded-3xl border bg-slate-900/60 p-4 ring-1 ring-white/5 transition hover:-translate-y-0.5 ${props.quest.flags.length ? 'border-rose-400/50' : props.quest.featured ? 'border-amber-300/40' : 'border-white/10'}` }, [
            h('div', { class: 'flex items-start justify-between gap-3' }, [
                h('button', { type: 'button', class: 'text-left', onClick: () => emit('open', props.quest) }, [
                    h('p', { class: 'text-[10px] font-black uppercase tracking-wide text-slate-500' }, props.quest.reference_code),
                    h('h3', { class: 'mt-1 line-clamp-2 font-display text-lg font-black text-white' }, props.quest.title),
                ]),
                h('input', { type: 'checkbox', checked: props.selected, class: 'mt-1 rounded border-white/20 bg-slate-900 text-primary-500', onChange: () => emit('toggle', props.quest.id) }),
            ]),
            h('p', { class: 'mt-3 line-clamp-2 text-sm leading-6 text-slate-400' }, props.quest.description_excerpt),
            h('div', { class: 'mt-4 flex items-center justify-between gap-3 text-sm' }, [
                h('span', { class: 'font-black text-primary-100' }, props.quest.budget),
                h('span', { class: 'rounded-full bg-white/10 px-2 py-1 text-xs font-black text-slate-300' }, `${props.quest.proposals_count} proposals`),
            ]),
            h('div', { class: 'mt-4 h-2 overflow-hidden rounded-full bg-slate-950' }, [
                h('div', { class: 'h-full rounded-full bg-primary-400', style: `width:${Math.min(100, (props.quest.proposals_count / Math.max(1, props.quest.proposal_capacity || 10)) * 100)}%` }),
            ]),
            h('div', { class: 'mt-4 flex items-center justify-between' }, [
                h('div', { class: 'flex flex-wrap gap-2' }, [
                    h(AdminStatusBadge, { status: props.quest.admin_status }),
                    h(StatusBadge, { label: props.quest.status_label, tone: props.quest.status_tone, muted: true }),
                ]),
                h('button', { type: 'button', class: 'rounded-xl border border-white/10 px-3 py-2 text-xs font-black uppercase tracking-wide text-white hover:bg-white/5', onClick: () => emit('open', props.quest) }, 'Manage'),
            ]),
        ]);
    },
});

const Timeline = defineComponent({
    props: { items: Array },
    setup(props) {
        return () => h('div', { class: 'mt-5 rounded-3xl border border-white/10 bg-slate-900/60 p-5' }, [
            h('h3', { class: 'font-display text-lg font-black text-white' }, 'Timeline'),
            h('div', { class: 'mt-4 space-y-3' }, (props.items || []).map((item) => h('div', { class: 'rounded-2xl border border-white/10 bg-slate-950/70 p-3' }, [
                h('p', { class: 'font-bold text-white' }, item.label),
                h('p', { class: 'mt-1 text-xs text-slate-500' }, `${item.actor || 'System'} · ${formatDate(item.at)}`),
            ]))),
        ]);
    },
});

const FlagStack = defineComponent({
    props: { flags: Array },
    emits: ['resolve'],
    setup(props, { emit }) {
        return () => h('div', { class: 'rounded-3xl border border-white/10 bg-slate-900/70 p-5' }, [
            h('p', { class: 'text-[10px] font-black uppercase tracking-[0.2em] text-slate-500' }, 'Active flags'),
            ...(props.flags?.length ? props.flags.map((flag) => h('div', { class: 'mt-3 rounded-2xl border border-white/10 bg-slate-950/70 p-3' }, [
                h('p', { class: `text-sm font-black ${flagTone(flag.priority)}` }, `${labelize(flag.priority)} · ${labelize(flag.type)}`),
                h('p', { class: 'mt-1 text-xs leading-5 text-slate-400' }, flag.description),
                h('button', { type: 'button', class: 'mt-3 text-xs font-black uppercase tracking-wide text-primary-200', onClick: () => emit('resolve', flag) }, 'Resolve flag'),
            ])) : [h('p', { class: 'mt-3 text-sm text-slate-400' }, 'No active flags.')]),
        ]);
    },
});

const StatusPanel = defineComponent({
    props: { options: Array, busy: Boolean },
    emits: ['submit', 'cancel'],
    setup(props, { emit }) {
        const state = reactive({ admin_status: '', reason: '', notify_client: true, notification_preview: 'HustleSafe moderation has updated the review state for your Quest. Please check your dashboard for details.' });
        return () => h('form', { class: 'space-y-3', onSubmit: (event) => { event.preventDefault(); emit('submit', { ...state }); } }, [
            h('p', { class: 'font-bold text-white' }, 'Change admin moderation status'),
            h('select', { class: 'panel-input', value: state.admin_status, onChange: (event) => { state.admin_status = event.target.value; } }, [h('option', { value: '' }, 'Choose admin status'), ...props.options.map((option) => h('option', { value: option.value }, option.label))]),
            h('textarea', { class: 'panel-input min-h-24', value: state.reason, placeholder: 'Mandatory reason (minimum 20 characters, 50 for suspension)', onInput: (event) => { state.reason = event.target.value; } }),
            h('label', { class: 'flex items-center gap-2 text-sm font-bold text-slate-300' }, [h('input', { type: 'checkbox', checked: state.notify_client, onChange: (event) => { state.notify_client = event.target.checked; } }), 'Notify client']),
            state.notify_client ? h('textarea', { class: 'panel-input min-h-20', value: state.notification_preview, placeholder: 'Editable notification preview', onInput: (event) => { state.notification_preview = event.target.value; } }) : null,
            h('div', { class: 'flex gap-2' }, [
                h('button', { type: 'submit', disabled: props.busy || !state.admin_status || state.reason.length < 20, class: 'primary-button' }, props.busy ? 'Saving...' : 'Confirm admin status'),
                h('button', { type: 'button', class: 'secondary-button', onClick: () => emit('cancel') }, 'Cancel'),
            ]),
        ]);
    },
});

const FlagPanel = defineComponent({
    props: { options: Object, busy: Boolean },
    emits: ['submit', 'cancel'],
    setup(props, { emit }) {
        const state = reactive({ type: 'suspicious_content', priority: 'medium', assigned_group: 'all_moderation_admins', description: '', due_at: '', visibility_impact: 'none', notify_client: false });
        return () => h('form', { class: 'space-y-3', onSubmit: (event) => { event.preventDefault(); emit('submit', { ...state }); } }, [
            h('p', { class: 'font-bold text-white' }, 'Create operational flag'),
            h('div', { class: 'grid gap-3 md:grid-cols-3' }, [
                h('select', { class: 'panel-input', value: state.type, onChange: (event) => { state.type = event.target.value; } }, props.options.flag_types.map((type) => h('option', { value: type }, labelize(type)))),
                h('select', { class: 'panel-input', value: state.priority, onChange: (event) => { state.priority = event.target.value; } }, props.options.flag_priorities.map((priority) => h('option', { value: priority }, labelize(priority)))),
                h('select', { class: 'panel-input', value: state.assigned_group, onChange: (event) => { state.assigned_group = event.target.value; } }, props.options.flag_groups.map((group) => h('option', { value: group }, labelize(group)))),
            ]),
            h('select', { class: 'panel-input', value: state.visibility_impact, onChange: (event) => { state.visibility_impact = event.target.value; } }, [
                h('option', { value: 'none' }, 'No visibility change'),
                h('option', { value: 'restrict_new_proposals' }, 'Restrict new proposals while flag is open'),
                h('option', { value: 'hide_pending_resolution' }, 'Suspend and hide pending resolution'),
            ]),
            h('textarea', { class: 'panel-input min-h-24', value: state.description, placeholder: 'Describe what needs to be done', onInput: (event) => { state.description = event.target.value; } }),
            h('input', { type: 'date', class: 'panel-input', value: state.due_at, onInput: (event) => { state.due_at = event.target.value; } }),
            h('label', { class: 'flex items-center gap-2 text-sm font-bold text-slate-300' }, [h('input', { type: 'checkbox', checked: state.notify_client, onChange: (event) => { state.notify_client = event.target.checked; } }), 'Notify client']),
            h('div', { class: 'flex gap-2' }, [h('button', { type: 'submit', disabled: props.busy || state.description.length < 30, class: state.visibility_impact === 'hide_pending_resolution' ? 'danger-button' : 'primary-button' }, props.busy ? 'Saving...' : 'Create flag'), h('button', { type: 'button', class: 'secondary-button', onClick: () => emit('cancel') }, 'Cancel')]),
        ]);
    },
});

const NoticePanel = defineComponent({
    props: { busy: Boolean },
    emits: ['submit', 'cancel'],
    setup(props, { emit }) {
        const state = reactive({ type: 'warning', body: '', visible_to_users: true, notify_stakeholders: false });
        return () => h('form', { class: 'space-y-3', onSubmit: (event) => { event.preventDefault(); emit('submit', { ...state }); } }, [
            h('p', { class: 'font-bold text-white' }, 'Post user-facing Quest notice'),
            h('select', { class: 'panel-input', value: state.type, onChange: (event) => { state.type = event.target.value; } }, [
                h('option', { value: 'warning' }, 'Warning'),
                h('option', { value: 'informational' }, 'Informational'),
                h('option', { value: 'urgent' }, 'Urgent'),
                h('option', { value: 'resolved' }, 'Resolved'),
            ]),
            h('textarea', { class: 'panel-input min-h-24', value: state.body, placeholder: 'Notice text visible on the public Quest page', onInput: (event) => { state.body = event.target.value; } }),
            h('label', { class: 'flex items-center gap-2 text-sm font-bold text-slate-300' }, [h('input', { type: 'checkbox', checked: state.visible_to_users, onChange: (event) => { state.visible_to_users = event.target.checked; } }), 'Visible to users']),
            h('label', { class: 'flex items-center gap-2 text-sm font-bold text-slate-300' }, [h('input', { type: 'checkbox', checked: state.notify_stakeholders, onChange: (event) => { state.notify_stakeholders = event.target.checked; } }), 'Notify Quest stakeholders']),
            h('div', { class: 'flex gap-2' }, [
                h('button', { type: 'submit', disabled: props.busy || state.body.length < 10, class: 'primary-button' }, props.busy ? 'Posting...' : 'Post notice'),
                h('button', { type: 'button', class: 'secondary-button', onClick: () => emit('cancel') }, 'Cancel'),
            ]),
        ]);
    },
});

const BoostPanel = defineComponent({
    props: { options: Object, busy: Boolean },
    emits: ['submit', 'cancel'],
    setup(props, { emit }) {
        const state = reactive({ tier: 'premium', duration_days: 7, paid_upgrade: false, payment_method: 'wallet', amount_paid_minor: 0, grant_reason: 'platform_promotion', internal_note: '' });
        return () => h('form', { class: 'space-y-3', onSubmit: (event) => { event.preventDefault(); emit('submit', { ...state }); } }, [
            h('p', { class: 'font-bold text-white' }, 'Upgrade quest to featured'),
            h('div', { class: 'grid gap-3 md:grid-cols-3' }, [
                h('select', { class: 'panel-input', value: state.tier, onChange: (event) => { state.tier = event.target.value; } }, Object.entries(props.options.boost_tiers).map(([value, config]) => h('option', { value }, config.label))),
                h('select', { class: 'panel-input', value: state.duration_days, onChange: (event) => { state.duration_days = Number(event.target.value); } }, [3, 7, 14, 30].map((days) => h('option', { value: days }, `${days} days`))),
                h('select', { class: 'panel-input', value: state.grant_reason, onChange: (event) => { state.grant_reason = event.target.value; } }, props.options.grant_reasons.map((reason) => h('option', { value: reason }, labelize(reason)))),
            ]),
            h('textarea', { class: 'panel-input min-h-24', value: state.internal_note, placeholder: 'Internal note or complimentary grant reason', onInput: (event) => { state.internal_note = event.target.value; } }),
            h('div', { class: 'flex gap-2' }, [h('button', { type: 'submit', disabled: props.busy, class: 'primary-button' }, 'Grant boost'), h('button', { type: 'button', class: 'secondary-button', onClick: () => emit('cancel') }, 'Cancel')]),
        ]);
    },
});

const ResolveFlagPanel = defineComponent({
    props: { flag: Object, busy: Boolean },
    emits: ['submit', 'cancel'],
    setup(props, { emit }) {
        const state = reactive({ resolution_outcome: 'actioned_resolved', resolution_note: '' });
        return () => h('form', { class: 'space-y-3', onSubmit: (event) => { event.preventDefault(); emit('submit', { ...state }); } }, [
            h('div', { class: 'rounded-2xl border border-white/10 bg-slate-950/60 p-3' }, [
                h('p', { class: 'text-xs font-black uppercase tracking-wide text-slate-500' }, 'Resolving flag'),
                h('p', { class: 'mt-1 font-bold text-white' }, props.flag ? `${labelize(props.flag.priority)} · ${labelize(props.flag.type)}` : 'Selected flag'),
                h('p', { class: 'mt-1 text-xs leading-5 text-slate-400' }, props.flag?.description || ''),
            ]),
            h('select', { class: 'panel-input', value: state.resolution_outcome, onChange: (event) => { state.resolution_outcome = event.target.value; } }, [
                h('option', { value: 'actioned_resolved' }, 'Actioned and resolved'),
                h('option', { value: 'escalated_to_super_admin' }, 'Escalated to Super Admin'),
                h('option', { value: 'no_action_required' }, 'No action required'),
                h('option', { value: 'referred_to_another_team' }, 'Referred to another team'),
            ]),
            h('textarea', { class: 'panel-input min-h-24', value: state.resolution_note, placeholder: 'Resolution note', onInput: (event) => { state.resolution_note = event.target.value; } }),
            h('div', { class: 'flex gap-2' }, [h('button', { type: 'submit', disabled: props.busy, class: 'primary-button' }, 'Resolve flag'), h('button', { type: 'button', class: 'secondary-button', onClick: () => emit('cancel') }, 'Cancel')]),
        ]);
    },
});
</script>

<style scoped>
.quest-engine-page {
    @apply space-y-5;
}

:global(.admin-theme-light .quest-engine-page) {
    background-color: #f8fafc;
    color: #0f172a;
}

:global(.admin-theme-light .quest-engine-page [class*="bg-slate-950"]),
:global(.admin-theme-light .quest-engine-page [class*="bg-slate-900"]),
:global(.admin-theme-light .quest-engine-page [class*="bg-slate-800"]) {
    background-color: #fff !important;
}

:global(.admin-theme-light .quest-engine-page [class*="bg-white/5"]),
:global(.admin-theme-light .quest-engine-page [class*="bg-white/10"]) {
    background-color: #f8fafc !important;
}

:global(.admin-theme-light .quest-engine-page [class*="border-white/"]),
:global(.admin-theme-light .quest-engine-page [class*="border-slate-700"]),
:global(.admin-theme-light .quest-engine-page [class*="border-slate-800"]) {
    border-color: #e2e8f0 !important;
}

:global(.admin-theme-light .quest-engine-page [class*="divide-white/"]) > :not([hidden]) ~ :not([hidden]),
:global(.admin-theme-light .quest-engine-page [class*="divide-slate-700"]) > :not([hidden]) ~ :not([hidden]) {
    border-color: #e2e8f0 !important;
}

:global(.admin-theme-light .quest-engine-page [class*="ring-white/"]) {
    --tw-ring-color: #f1f5f9 !important;
}

:global(.admin-theme-light .quest-engine-page .text-white),
:global(.admin-theme-light .quest-engine-page [class*="text-white"]) {
    color: #0f172a !important;
}

:global(.admin-theme-light .quest-engine-page [class*="text-slate-100"]),
:global(.admin-theme-light .quest-engine-page [class*="text-slate-200"]),
:global(.admin-theme-light .quest-engine-page [class*="text-slate-300"]),
:global(.admin-theme-light .quest-engine-page [class*="text-slate-400"]) {
    color: #475569 !important;
}

:global(.admin-theme-light .quest-engine-page [class*="text-slate-500"]) {
    color: #64748b !important;
}

:global(.admin-theme-light .quest-engine-page .control-button),
:global(.admin-theme-light .quest-engine-page .secondary-button) {
    background-color: #fff !important;
    border-color: #cbd5e1 !important;
    color: #334155 !important;
}

:global(.admin-theme-light .quest-engine-page .control-select),
:global(.admin-theme-light .quest-engine-page .panel-input),
:global(.admin-theme-light .quest-engine-page .filter-field select),
:global(.admin-theme-light .quest-engine-page .filter-field input),
:global(.admin-theme-light .quest-engine-page input),
:global(.admin-theme-light .quest-engine-page select),
:global(.admin-theme-light .quest-engine-page textarea) {
    background-color: #fff !important;
    border-color: #cbd5e1 !important;
    color: #0f172a !important;
}

:global(.admin-theme-light .quest-engine-page .primary-button) {
    color: #fff !important;
    box-shadow: 0 12px 24px rgb(14 165 233 / 0.2) !important;
}

:global(.admin-theme-light) .quest-engine-page :where([class*="bg-slate-950"], [class*="bg-slate-900"], [class*="bg-slate-800"]) {
    background-color: #fff !important;
}

:global(.admin-theme-light) .quest-engine-page :where([class*="bg-white/5"], [class*="bg-white/10"], [class*="bg-slate-100"]) {
    background-color: #f8fafc !important;
}

:global(.admin-theme-light) .quest-engine-page :where([class*="border-white/10"], [class*="border-white/15"], [class*="border-slate-700"], [class*="border-slate-800"]) {
    border-color: #e2e8f0 !important;
}

:global(.admin-theme-light) .quest-engine-page :where([class*="divide-white/"], [class*="divide-slate-700"]) > :not([hidden]) ~ :not([hidden]) {
    border-color: #e2e8f0 !important;
}

:global(.admin-theme-light) .quest-engine-page :where([class*="ring-white/"], [class*="ring-slate-700"]) {
    --tw-ring-color: #f1f5f9 !important;
}

:global(.admin-theme-light) .quest-engine-page :where([class*="shadow-slate-950"], [class*="shadow-black"]) {
    --tw-shadow-color: rgb(203 213 225 / 0.35) !important;
    --tw-shadow: var(--tw-shadow-colored) !important;
}

:global(.admin-theme-light) .quest-engine-page :where(.text-white, [class*="text-slate-950"]) {
    color: #0f172a !important;
}

:global(.admin-theme-light) .quest-engine-page :where([class*="text-slate-200"], [class*="text-slate-300"], [class*="text-slate-400"]) {
    color: #475569 !important;
}

:global(.admin-theme-light) .quest-engine-page :where([class*="text-slate-500"]) {
    color: #64748b !important;
}

:global(.admin-theme-light) .quest-engine-page :where([class*="text-primary-100"], [class*="text-primary-200"], [class*="text-primary-300"]) {
    color: #0369a1 !important;
}

:global(.admin-theme-light) .quest-engine-page :where(input, select, textarea) {
    background-color: #fff !important;
    border-color: #cbd5e1 !important;
    color: #0f172a !important;
}

:global(.admin-theme-light) .quest-engine-page :where(input::placeholder, textarea::placeholder) {
    color: #94a3b8 !important;
}

:global(.admin-theme-light) .quest-engine-page mark {
    background-color: rgb(14 165 233 / 0.16) !important;
    color: #075985 !important;
}

.control-button,
.secondary-button {
    @apply inline-flex min-h-11 items-center justify-center rounded-xl border border-white/10 bg-white/5 px-3 text-xs font-black uppercase tracking-wide text-slate-200 transition hover:bg-white/10;
}

.primary-button {
    @apply inline-flex min-h-11 items-center justify-center rounded-xl bg-primary-500 px-4 text-xs font-black uppercase tracking-wide text-slate-950 shadow-lg shadow-slate-950/30 transition hover:bg-primary-400 disabled:opacity-50;
}

.learn-more-link {
    @apply inline-flex items-center justify-center rounded-xl border border-primary-200 bg-white px-3 py-2 text-xs font-black uppercase tracking-wide text-primary-800 shadow-sm transition hover:bg-primary-100;
}

.danger-button {
    @apply inline-flex min-h-11 items-center justify-center rounded-xl border border-rose-400/30 bg-rose-500/10 px-3 text-xs font-black uppercase tracking-wide text-rose-100 transition hover:bg-rose-500/20;
}

.control-select,
.panel-input,
.filter-field select,
.filter-field input {
    @apply min-h-11 rounded-xl border border-white/10 bg-slate-950 px-3 text-sm font-semibold text-white outline-none ring-2 ring-transparent transition focus:border-primary-400/60 focus:ring-primary-500/30;
}

.filter-field {
    @apply flex flex-col gap-1 text-[10px] font-black uppercase tracking-[0.18em] text-slate-500;
}

.quest-detail-light-panel :deep([class*="bg-slate-950"]),
.quest-detail-light-panel :deep([class*="bg-slate-900"]),
.quest-detail-light-panel :deep([class*="bg-slate-800"]) {
    @apply !bg-white;
}

.quest-detail-light-panel :deep([class*="bg-white/5"]),
.quest-detail-light-panel :deep([class*="bg-white/10"]) {
    @apply !bg-slate-50;
}

.quest-detail-light-panel :deep([class*="border-white/"]),
.quest-detail-light-panel :deep([class*="border-slate-700"]),
.quest-detail-light-panel :deep([class*="border-slate-800"]) {
    @apply !border-slate-200;
}

.quest-detail-light-panel :deep(.text-white),
.quest-detail-light-panel :deep([class*="text-white"]) {
    @apply !text-slate-950;
}

.quest-detail-light-panel :deep([class*="text-slate-100"]),
.quest-detail-light-panel :deep([class*="text-slate-200"]),
.quest-detail-light-panel :deep([class*="text-slate-300"]),
.quest-detail-light-panel :deep([class*="text-slate-400"]) {
    @apply !text-slate-600;
}

.quest-detail-light-panel :deep([class*="text-primary-100"]),
.quest-detail-light-panel :deep([class*="text-primary-200"]),
.quest-detail-light-panel :deep([class*="text-primary-300"]) {
    @apply !text-primary-700;
}

.quest-detail-light-panel :deep(input),
.quest-detail-light-panel :deep(select),
.quest-detail-light-panel :deep(textarea) {
    @apply !border-slate-200 !bg-white !text-slate-950;
}

.quest-description-html {
    overflow-wrap: anywhere;
}

.quest-description-html :deep(*) {
    color: inherit !important;
    background: transparent !important;
    font-family: inherit !important;
}

.quest-description-html :deep(p) {
    @apply my-3;
}

.quest-description-html :deep(ul) {
    @apply my-3 list-disc pl-5;
}

.quest-description-html :deep(ol) {
    @apply my-3 list-decimal pl-5;
}

.quest-description-html :deep(a) {
    @apply font-bold text-primary-700 underline underline-offset-4;
}
</style>
