<template>
    <AdminShell
        title="Proposal Management Engine"
        subtitle="Retrospective moderation, risk intelligence, referrals, notices, and audit trails for freelancer proposals."
    >
        <div class="proposal-engine-page">
            <section class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-3xl border border-primary-100 bg-primary-50/90 p-4 text-slate-900 ring-1 ring-primary-100">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.2em] text-primary-700">Documentation</p>
                    <p class="mt-1 text-sm font-bold text-slate-800">Need help with proposal statuses, flags, notices, risk signals, or bulk actions?</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a :href="route('admin.documentation.guide', { topic: 'proposal-management' }) + '#admin-status-lifecycle'" class="learn-more-link">Admin status lifecycle</a>
                    <a :href="route('admin.documentation.guide', { topic: 'proposal-management' }) + '#risk-signals-explained'" class="learn-more-link">Risk signals</a>
                    <a :href="route('admin.documentation.guide', { topic: 'flags-notices' })" class="learn-more-link">Flags & notices</a>
                    <a :href="route('admin.documentation.guide', { topic: 'bulk-operations' })" class="learn-more-link">Bulk actions</a>
                </div>
            </section>

            <section class="flex gap-3 overflow-x-auto pb-1">
                <button
                    v-for="tile in summary"
                    :key="tile.key"
                    type="button"
                    class="group min-w-[15rem] rounded-3xl border border-white/10 bg-slate-900/70 p-4 text-left ring-1 ring-white/5 transition hover:-translate-y-0.5 hover:border-primary-400/50 hover:bg-slate-900"
                    :class="form.quick === tile.filter?.quick || form.admin_status === tile.filter?.admin_status ? 'border-primary-400/60 shadow-lg shadow-primary-950/30' : ''"
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
                                placeholder="Search proposal, freelancer, Quest title, email, or proposal ID"
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
                            <span>Admin status</span>
                            <select v-model="form.admin_status">
                                <option value="">Any admin status</option>
                                <option v-for="status in options.admin_status_options" :key="status.value" :value="status.value">{{ status.label }}</option>
                            </select>
                        </label>
                        <label class="filter-field">
                            <span>Operational status</span>
                            <select v-model="form.status">
                                <option value="">Any operational status</option>
                                <option v-for="status in options.status_options" :key="status.value" :value="status.value">{{ status.label }}</option>
                            </select>
                        </label>
                        <label class="filter-field">
                            <span>Category</span>
                            <select v-model="form.category_id">
                                <option value="">Any category</option>
                                <option v-for="category in options.categories" :key="category.value" :value="category.value">{{ category.label }}</option>
                            </select>
                        </label>
                        <label class="filter-field">
                            <span>Verification tier</span>
                            <input v-model="form.verification_tier" placeholder="basic, tier_2..." />
                        </label>
                        <label class="filter-field">
                            <span>Quest budget from</span>
                            <input v-model="form.budget_min" type="number" min="0" placeholder="₦ min" />
                        </label>
                        <label class="filter-field">
                            <span>Quest budget to</span>
                            <input v-model="form.budget_max" type="number" min="0" placeholder="₦ max" />
                        </label>
                        <label class="filter-field">
                            <span>Proposed amount from</span>
                            <input v-model="form.amount_min" type="number" min="0" placeholder="₦ min" />
                        </label>
                        <label class="filter-field">
                            <span>Trust score min</span>
                            <input v-model="form.trust_min" type="number" min="0" max="100" />
                        </label>
                        <label class="filter-field">
                            <span>Submitted from</span>
                            <AdminDateInput v-model="form.submitted_from" />
                        </label>
                        <label class="filter-field">
                            <span>Submitted to</span>
                            <AdminDateInput v-model="form.submitted_to" />
                        </label>
                        <label class="filter-field">
                            <span>Flag type</span>
                            <select v-model="form.flag_type">
                                <option value="">Any flag</option>
                                <option v-for="type in options.flag_types" :key="type" :value="type">{{ labelize(type) }}</option>
                            </select>
                        </label>
                        <label class="inline-flex min-h-11 items-center gap-3 rounded-xl border border-white/10 bg-slate-950 px-3 text-sm font-bold text-slate-200">
                            <input v-model="form.has_notice" type="checkbox" class="rounded border-white/20 bg-slate-900 text-primary-500 focus:ring-primary-500" />
                            Has notice
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

            <section v-if="selectedIds.size" class="rounded-3xl border border-primary-300/20 bg-primary-500/10 p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm font-bold text-primary-100">{{ selectedIds.size }} proposal(s) selected. Accepted contracts are excluded from destructive bulk actions.</p>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="secondary-button" @click="openBulk('change_status')">Change status</button>
                        <button type="button" class="secondary-button" @click="openBulk('flag')">Flag all</button>
                        <button type="button" class="secondary-button" @click="openBulk('refer')">Refer all</button>
                        <button type="button" class="danger-button" @click="openBulk('suspend')">Suspend all</button>
                        <a :href="route('admin.proposals.export', form)" class="secondary-button">Export CSV</a>
                    </div>
                </div>
            </section>

            <Transition mode="out-in" enter-active-class="transition duration-150" enter-from-class="opacity-0" enter-to-class="opacity-100">
                <section :key="viewMode">
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
                                    v-for="proposal in proposals.data"
                                    :key="proposal.id"
                                    class="cursor-pointer transition hover:bg-white/5"
                                    :class="[density === 'compact' ? 'text-xs' : '', proposal.flag_count ? 'border-l-2 border-l-rose-400' : '', proposal.risk_score >= 60 ? 'bg-rose-500/5' : '']"
                                    @click="openProposal(proposal)"
                                >
                                    <td class="px-4" @click.stop>
                                        <input type="checkbox" class="rounded border-white/20 bg-slate-900 text-primary-500" :checked="selectedIds.has(proposal.id)" @change="toggleSelection(proposal.id)" />
                                    </td>
                                    <td class="px-4" :class="density === 'compact' ? 'py-2' : 'py-4'">
                                        <div class="flex flex-col gap-1">
                                            <AdminStatusBadge :status="proposal.admin_status" />
                                            <StatusBadge :label="proposal.status_label" :tone="proposal.status_tone" muted />
                                        </div>
                                    </td>
                                    <td class="px-4">
                                        <p class="text-xs font-black text-slate-500">{{ proposal.reference_code }}</p>
                                        <p class="max-w-sm truncate font-semibold text-white" :title="proposal.cover_letter_excerpt" v-html="highlight(proposal.cover_letter_excerpt)"></p>
                                    </td>
                                    <td class="px-4">
                                        <div class="flex items-center gap-2">
                                            <Avatar :src="proposal.freelancer.avatar_url" :name="proposal.freelancer.name || proposal.freelancer.email" />
                                            <div class="min-w-0">
                                                <p class="truncate text-white">{{ proposal.freelancer.name || 'Unknown freelancer' }}</p>
                                                <p class="truncate text-xs text-slate-500">{{ proposal.freelancer.email }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4">
                                        <p class="text-xs font-black uppercase tracking-wide text-primary-200">{{ proposal.freelancer.verification_tier }}</p>
                                        <p class="text-xs text-slate-500">Trust {{ proposal.freelancer.trust_score }}/100</p>
                                    </td>
                                    <td class="px-4">
                                        <p class="font-bold text-white">{{ proposal.proposed_amount }}</p>
                                        <span class="rounded-full px-2 py-1 text-[10px] font-black" :class="varianceTone(proposal.bid_variance.tone)">{{ proposal.bid_variance.label }}</span>
                                    </td>
                                    <td class="px-4">
                                        <button type="button" class="max-w-xs truncate text-left font-bold text-primary-100 hover:underline" @click.stop="openNestedQuest(proposal)">
                                            {{ proposal.quest.title }}
                                        </button>
                                        <p class="text-xs text-slate-500">{{ proposal.quest.reference_code }} · {{ proposal.quest.budget }}</p>
                                    </td>
                                    <td class="px-4">
                                        <div class="flex items-center gap-2">
                                            <span v-if="proposal.flag_count" :class="flagTone(proposal.flags[0]?.priority)" title="Flagged">⚑ {{ proposal.flag_count }}</span>
                                            <span v-if="proposal.has_user_notice" class="text-primary-300" title="User notice active">Notice</span>
                                            <span v-if="proposal.risk_score" class="text-rose-300" :title="proposal.risk_signals.map((signal) => signal.name).join(', ')">Risk {{ proposal.risk_score }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 text-xs font-bold text-slate-400" :title="formatDate(proposal.created_at)">{{ relativeDate(proposal.created_at) }}</td>
                                    <td class="px-4 text-right" @click.stop>
                                        <button type="button" class="rounded-xl border border-white/10 px-3 py-2 text-xs font-black uppercase tracking-wide text-primary-200 hover:bg-white/5" @click="openProposal(proposal)">
                                            Manage
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-else-if="viewMode === 'cards'" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <ProposalCard v-for="proposal in proposals.data" :key="proposal.id" :proposal="proposal" :selected="selectedIds.has(proposal.id)" @open="openProposal" @toggle="toggleSelection" />
                    </div>

                    <div v-else class="grid gap-4 overflow-x-auto xl:grid-cols-8">
                        <section v-for="column in kanbanColumns" :key="column.key" class="min-h-[30rem] min-w-[16rem] rounded-3xl border border-white/10 bg-slate-900/50 p-3" @dragover.prevent @drop="requestStatusChange(draggedProposal, column.status)">
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <h3 class="text-xs font-black uppercase tracking-[0.18em] text-slate-300">{{ column.label }}</h3>
                                <span class="rounded-full bg-white/10 px-2 py-0.5 text-xs font-black text-slate-300">{{ column.items.length }}</span>
                            </div>
                            <div class="space-y-3">
                                <button
                                    v-for="proposal in column.items"
                                    :key="proposal.id"
                                    type="button"
                                    class="w-full rounded-2xl border border-white/10 bg-slate-950/80 p-3 text-left transition hover:border-primary-400/50 hover:bg-slate-950"
                                    draggable="true"
                                    @dragstart="draggedProposal = proposal"
                                    @click="openProposal(proposal)"
                                >
                                    <p class="text-[10px] font-black text-slate-500">{{ proposal.reference_code }}</p>
                                    <h3 class="mt-1 line-clamp-2 font-display text-sm font-black text-white">{{ proposal.freelancer.name || proposal.freelancer.email }}</h3>
                                    <p class="mt-2 text-xs text-slate-400">{{ proposal.quest.title }}</p>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <span class="rounded-full bg-primary-500/15 px-2 py-1 text-[10px] font-black text-primary-100">{{ proposal.proposed_amount }}</span>
                                        <span class="rounded-full bg-white/10 px-2 py-1 text-[10px] font-black text-slate-300">{{ proposal.bid_variance.label }}</span>
                                        <span v-if="proposal.flag_count" class="rounded-full bg-rose-500/15 px-2 py-1 text-[10px] font-black text-rose-100">{{ proposal.flag_count }} flags</span>
                                    </div>
                                </button>
                            </div>
                        </section>
                    </div>
                </section>
            </Transition>

            <div class="flex flex-wrap items-center justify-between gap-3 text-sm text-slate-400">
                <p>Showing {{ proposals.data.length }} of {{ proposals.total }} proposals.</p>
                <div class="flex gap-2">
                    <button type="button" class="secondary-button" :disabled="!proposals.prev_page_url" @click="visitPage(proposals.prev_page_url)">Previous</button>
                    <button type="button" class="secondary-button" :disabled="!proposals.next_page_url" @click="visitPage(proposals.next_page_url)">Next</button>
                </div>
            </div>

            <AdminSlideOver
                :open="Boolean(activeProposal)"
                :title="activeProposal ? `${activeProposal.reference_code} · ${activeProposal.freelancer.name || activeProposal.freelancer.email}` : 'Proposal details'"
                eyebrow="Proposal Management"
                width-class="max-w-full lg:max-w-[55vw]"
                panel-class="quest-detail-light-panel bg-white text-slate-950"
                @close="closeProposal"
            >
                <div v-if="detailLoading" class="space-y-4">
                    <div class="h-24 animate-pulse rounded-3xl bg-slate-100"></div>
                    <div class="h-64 animate-pulse rounded-3xl bg-slate-100"></div>
                </div>

                <div v-else-if="proposalDetail" class="space-y-5">
                    <header class="rounded-3xl border border-white/10 bg-slate-900/70 p-4">
                        <div class="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Proposal #{{ proposalDetail.overview.proposal.id }}</p>
                                <h2 class="mt-1 font-display text-2xl font-black text-white">{{ proposalDetail.overview.proposal.quest.title }}</h2>
                                <div class="mt-3 flex flex-wrap items-center gap-2">
                                    <AdminStatusBadge :status="proposalDetail.overview.proposal.admin_status" />
                                    <StatusBadge :label="proposalDetail.overview.proposal.status_label" :tone="proposalDetail.overview.proposal.status_tone" muted />
                                    <span class="rounded-full bg-emerald-500/15 px-3 py-1 text-[10px] font-black uppercase tracking-wide text-emerald-100">Live viewer: you</span>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="secondary-button" @click="actionPanel = 'status'">Change status</button>
                                <button type="button" class="secondary-button" @click="actionPanel = 'flag'">Flag</button>
                                <button type="button" class="secondary-button" @click="actionPanel = 'notice'">Post notice</button>
                                <button type="button" class="secondary-button" @click="actionPanel = 'contact'">Contact</button>
                                <button type="button" class="danger-button" @click="actionPanel = 'delete'">More</button>
                            </div>
                        </div>
                    </header>

                    <section v-if="actionPanel" class="rounded-3xl border border-primary-300/20 bg-primary-500/10 p-4">
                        <StatusPanel v-if="actionPanel === 'status'" :options="options.admin_status_options" :busy="actionBusy" @cancel="actionPanel = null" @submit="submitStatus" />
                        <FlagPanel v-else-if="actionPanel === 'flag'" :options="options" :busy="actionBusy" @cancel="actionPanel = null" @submit="submitFlag" />
                        <NoticePanel v-else-if="actionPanel === 'notice'" :busy="actionBusy" @cancel="actionPanel = null" @submit="submitNotice" />
                        <ContactPanel v-else-if="actionPanel === 'contact'" :busy="actionBusy" @cancel="actionPanel = null" @submit="submitContact" />
                        <ContentPanel v-else-if="actionPanel === 'edit'" :content="proposalDetail.content" :busy="actionBusy" @cancel="actionPanel = null" @submit="submitContent" />
                        <ResolveFlagPanel v-else-if="actionPanel === 'resolve'" :flag="resolvingFlag" :busy="actionBusy" @cancel="actionPanel = null" @submit="submitResolveFlag" />
                        <DeletePanel v-else-if="actionPanel === 'delete'" :busy="actionBusy" @cancel="actionPanel = null" @submit="submitDelete" />
                    </section>

                    <AdminTabs v-model="activeTab" :tabs="tabs" id-prefix="proposal-engine" aria-label="Proposal management tabs" />

                    <AdminTabPanel :current-tab="activeTab" value="content" id-prefix="proposal-engine">
                        <div class="grid gap-4 xl:grid-cols-[1fr_20rem]">
                            <article class="rounded-3xl border border-white/10 bg-slate-900/60 p-5">
                                <div class="flex items-start justify-between gap-3">
                                    <h3 class="font-display text-xl font-black text-white">Proposal content</h3>
                                    <button v-if="proposalDetail.edit_options.can_edit_content" type="button" class="secondary-button" @click="actionPanel = 'edit'">Edit with diff</button>
                                </div>
                                <div class="mt-4 space-y-4 text-sm leading-7 text-slate-300">
                                    <p class="whitespace-pre-line" v-html="highlightRisk(proposalDetail.content.pitch)"></p>
                                    <div v-if="proposalDetail.content.scope_detail" class="rounded-2xl border border-white/10 bg-slate-950/70 p-4">
                                        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Scope detail</p>
                                        <p class="mt-2 whitespace-pre-line">{{ proposalDetail.content.scope_detail }}</p>
                                    </div>
                                    <div v-if="proposalDetail.content.warranty_terms" class="rounded-2xl border border-white/10 bg-slate-950/70 p-4">
                                        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Warranty terms</p>
                                        <p class="mt-2 whitespace-pre-line">{{ proposalDetail.content.warranty_terms }}</p>
                                    </div>
                                </div>
                            </article>
                            <aside class="space-y-3">
                                <InfoItem label="Proposed amount" :value="proposalDetail.content.proposed_amount" />
                                <InfoItem label="Timeline" :value="proposalDetail.content.timeline || 'Not provided'" />
                                <InfoItem label="Submitted" :value="formatDate(proposalDetail.content.submitted_at)" />
                                <InfoItem label="Attachments" :value="`${proposalDetail.content.attachments.length} files`" />
                            </aside>
                        </div>
                    </AdminTabPanel>

                    <AdminTabPanel :current-tab="activeTab" value="risk" id-prefix="proposal-engine">
                        <div class="grid gap-4 xl:grid-cols-[20rem_1fr]">
                            <div class="rounded-3xl border border-white/10 bg-slate-900/70 p-5">
                                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Automated risk score</p>
                                <p class="mt-3 font-display text-5xl font-black text-white">{{ proposalDetail.risk.score }}</p>
                                <div class="mt-4 h-3 overflow-hidden rounded-full bg-slate-950">
                                    <div class="h-full rounded-full bg-rose-400" :style="{ width: `${proposalDetail.risk.score}%` }"></div>
                                </div>
                                <div class="mt-4 space-y-2">
                                    <p class="text-sm font-bold text-slate-300">Similarity: {{ proposalDetail.risk.similarity_score }}%</p>
                                    <p class="text-sm font-bold text-slate-300">Velocity: {{ proposalDetail.risk.submission_velocity.label }} ({{ proposalDetail.risk.submission_velocity.last_24h }}/24h)</p>
                                    <p class="text-sm font-bold text-slate-300">Outlier: {{ proposalDetail.risk.outlier_detection.label }}</p>
                                </div>
                            </div>
                            <div class="space-y-3">
                                <article v-for="signal in proposalDetail.risk.signals" :key="signal.key" class="rounded-3xl border border-white/10 bg-slate-900/60 p-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="font-bold text-white">{{ signal.name }}</p>
                                            <p class="mt-1 text-sm leading-6 text-slate-400">{{ signal.explanation }}</p>
                                            <p class="mt-2 text-xs font-bold text-slate-500">{{ signal.evidence }}</p>
                                        </div>
                                        <button type="button" class="secondary-button" @click="flagFromSignal(signal)">Flag</button>
                                    </div>
                                </article>
                                <p v-if="!proposalDetail.risk.signals.length" class="rounded-3xl border border-dashed border-white/10 p-8 text-center text-sm text-slate-400">No automated risk signals detected.</p>
                            </div>
                        </div>
                    </AdminTabPanel>

                    <AdminTabPanel :current-tab="activeTab" value="freelancer" id-prefix="proposal-engine">
                        <div class="grid gap-4 xl:grid-cols-[20rem_1fr]">
                            <div class="rounded-3xl border border-white/10 bg-slate-900/70 p-5">
                                <div class="flex items-center gap-3">
                                    <Avatar :src="proposalDetail.freelancer.profile.avatar_url" :name="proposalDetail.freelancer.profile.name" large />
                                    <div>
                                        <p class="font-display text-lg font-black text-white">{{ proposalDetail.freelancer.profile.name }}</p>
                                        <p class="text-sm text-slate-500">{{ proposalDetail.freelancer.profile.email }}</p>
                                    </div>
                                </div>
                                <dl class="mt-5 space-y-3">
                                    <InfoItem label="Verification tier" :value="proposalDetail.freelancer.profile.verification_tier" />
                                    <InfoItem label="Trust score" :value="`${proposalDetail.freelancer.profile.trust_score}/100`" />
                                    <InfoItem label="Profile completion" :value="`${proposalDetail.freelancer.profile.profile_completion}%`" />
                                    <InfoItem label="Account age" :value="proposalDetail.freelancer.profile.account_age" />
                                    <InfoItem label="Acceptance rate" :value="proposalDetail.freelancer.stats.acceptance_rate" />
                                    <InfoItem label="Rating" :value="proposalDetail.freelancer.stats.rating" />
                                </dl>
                                <div class="mt-4 grid gap-2">
                                    <button type="button" class="secondary-button" @click="filterByFreelancer">Filter by freelancer</button>
                                    <button type="button" class="secondary-button" @click="actionPanel = 'flag'">Flag freelancer context</button>
                                </div>
                            </div>
                            <div class="rounded-3xl border border-white/10 bg-slate-900/60 p-5">
                                <h3 class="font-display text-lg font-black text-white">Last 15 proposals</h3>
                                <div class="mt-4 space-y-3">
                                    <article v-for="item in proposalDetail.freelancer.recent_proposals" :key="item.id" class="rounded-2xl border border-white/10 bg-slate-950/70 p-3">
                                        <div class="flex items-center justify-between gap-3">
                                            <p class="font-bold text-white">{{ item.quest }}</p>
                                            <span class="rounded-full bg-rose-500/15 px-2 py-1 text-[10px] font-black text-rose-100">Risk {{ item.risk_score }}</span>
                                        </div>
                                        <p class="mt-1 text-xs text-slate-500">{{ item.amount }} · {{ item.status }} · {{ formatDate(item.created_at) }}</p>
                                    </article>
                                </div>
                            </div>
                        </div>
                    </AdminTabPanel>

                    <AdminTabPanel :current-tab="activeTab" value="quest" id-prefix="proposal-engine">
                        <div class="rounded-3xl border border-white/10 bg-slate-900/60 p-5">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">{{ proposalDetail.quest.summary.reference_code }}</p>
                                    <h3 class="mt-1 font-display text-xl font-black text-white">{{ proposalDetail.quest.summary.title }}</h3>
                                    <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-400">{{ proposalDetail.quest.summary.description }}</p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" class="secondary-button" @click="openNestedQuest(proposalDetail.overview.proposal)">Open Quest</button>
                                    <button type="button" class="secondary-button" @click="filterByQuest">Filter by Quest</button>
                                    <button type="button" class="secondary-button" @click="actionPanel = 'contact'">Contact client</button>
                                </div>
                            </div>
                            <dl class="mt-5 grid gap-3 md:grid-cols-4">
                                <InfoItem label="Budget" :value="proposalDetail.quest.summary.budget" />
                                <InfoItem label="Category" :value="proposalDetail.quest.summary.category" />
                                <InfoItem label="Proposal ranking" :value="`${proposalDetail.quest.ranking.by_amount} of ${proposalDetail.quest.ranking.total}`" />
                                <InfoItem label="Client" :value="proposalDetail.quest.summary.client?.name || proposalDetail.quest.summary.client?.email" />
                            </dl>
                        </div>
                    </AdminTabPanel>

                    <AdminTabPanel :current-tab="activeTab" value="flags" id-prefix="proposal-engine">
                        <div class="grid gap-4 xl:grid-cols-2">
                            <FlagStack :flags="proposalDetail.flags" @resolve="openResolve" />
                            <div class="rounded-3xl border border-white/10 bg-slate-900/70 p-5">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">User notices</p>
                                    <button type="button" class="secondary-button" @click="actionPanel = 'notice'">Add notice</button>
                                </div>
                                <div class="mt-4 space-y-3">
                                    <article v-for="notice in proposalDetail.notices" :key="notice.id" class="rounded-2xl border border-white/10 bg-slate-950/70 p-3">
                                        <p class="text-sm font-black text-white">{{ labelize(notice.type) }}</p>
                                        <p class="mt-2 text-sm leading-6 text-slate-300">{{ notice.body }}</p>
                                        <p class="mt-2 text-xs text-slate-500">{{ notice.creator || 'Admin' }} · Freelancer {{ notice.visible_to_freelancer ? 'visible' : 'hidden' }} · Client {{ notice.visible_to_client ? 'visible' : 'hidden' }}</p>
                                    </article>
                                    <p v-if="!proposalDetail.notices.length" class="rounded-2xl border border-dashed border-white/10 p-5 text-center text-sm text-slate-400">No notices posted on this proposal.</p>
                                </div>
                            </div>
                        </div>
                    </AdminTabPanel>

                    <AdminTabPanel :current-tab="activeTab" value="communications" id-prefix="proposal-engine">
                        <div class="grid gap-4 xl:grid-cols-2">
                            <ContactPanel :busy="actionBusy" inline @submit="submitContact" />
                            <div class="rounded-3xl border border-white/10 bg-slate-900/60 p-5">
                                <h3 class="font-display text-xl font-black text-white">CS chat handoff</h3>
                                <p class="mt-2 text-sm leading-6 text-slate-400">Use this panel to document email, in-app, or CS chat outreach. Every contact action is written to the immutable proposal audit trail.</p>
                                <div class="mt-4 rounded-2xl border border-dashed border-white/10 p-5 text-sm text-slate-400">Embedded chat transcript will appear here when the CS bridge is connected.</div>
                            </div>
                        </div>
                    </AdminTabPanel>

                    <AdminTabPanel :current-tab="activeTab" value="activity" id-prefix="proposal-engine">
                        <Timeline :items="proposalDetail.activity.items.map((item) => ({ label: labelize(item.action), actor: item.actor || 'System', at: item.created_at, meta: item.properties }))" />
                    </AdminTabPanel>

                    <AdminTabPanel :current-tab="activeTab" value="notes" id-prefix="proposal-engine">
                        <div class="grid gap-4 xl:grid-cols-[1fr_20rem]">
                            <div class="space-y-3">
                                <article v-for="note in proposalDetail.notes" :key="note.id" class="rounded-3xl border border-white/10 bg-slate-900/60 p-4" :class="note.is_pinned ? 'border-amber-300/40 bg-amber-400/10' : ''">
                                    <p class="text-sm font-bold text-white">{{ note.admin.name || 'Admin' }}</p>
                                    <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-300">{{ note.body }}</p>
                                    <p class="mt-2 text-xs text-slate-500">{{ formatDate(note.created_at) }}</p>
                                </article>
                                <p v-if="!proposalDetail.notes.length" class="rounded-3xl border border-dashed border-white/10 p-8 text-center text-sm text-slate-400">No internal notes yet.</p>
                            </div>
                            <form class="rounded-3xl border border-white/10 bg-slate-900/70 p-4" @submit.prevent="submitNote(noteForm)">
                                <p class="font-bold text-white">Add internal note</p>
                                <textarea v-model="noteForm.body" class="panel-input mt-3 min-h-32 w-full" placeholder="Write private moderation context. Use @mentions for handoffs." />
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
                </div>
            </AdminSlideOver>

            <AdminSlideOver
                :open="Boolean(nestedQuest)"
                :title="nestedQuest?.quest?.title || proposalDetail?.quest?.summary?.title || 'Quest context'"
                eyebrow="Nested Quest Layer"
                width-class="max-w-full lg:max-w-[45vw]"
                panel-class="quest-detail-light-panel bg-white text-slate-950"
                @close="nestedQuest = null"
            >
                <div class="rounded-3xl border border-white/10 bg-slate-900/60 p-5">
                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">{{ nestedQuest?.quest?.reference_code || proposalDetail?.quest?.summary?.reference_code }}</p>
                    <h3 class="mt-1 font-display text-xl font-black text-white">{{ nestedQuest?.quest?.title || proposalDetail?.quest?.summary?.title }}</h3>
                    <p class="mt-3 text-sm leading-6 text-slate-400">Quest detail opens in a nested moderation layer so admins keep proposal context while reviewing the parent Quest.</p>
                    <a v-if="nestedQuest?.quest?.route_key" :href="route('admin.quests.index', { q: nestedQuest.quest.reference_code })" class="primary-button mt-5">Open in Quest Engine</a>
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
import AdminDateInput from '@/Components/Admin/AdminDateInput.vue';
import AdminSlideOver from '@/Components/Admin/AdminSlideOver.vue';
import AdminTabPanel from '@/Components/Admin/AdminTabPanel.vue';
import AdminTabs from '@/Components/Admin/AdminTabs.vue';
import AdminShell from '@/Layouts/AdminShell.vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, defineComponent, h, onMounted, reactive, ref, watch } from 'vue';

const props = defineProps({
    summary: { type: Array, default: () => [] },
    proposals: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    options: { type: Object, required: true },
});

const viewModes = [
    { value: 'table', label: 'Table' },
    { value: 'cards', label: 'Cards' },
    { value: 'kanban', label: 'Kanban' },
];

const tabs = [
    { key: 'content', label: 'Proposal Content' },
    { key: 'risk', label: 'Risk Intelligence' },
    { key: 'freelancer', label: 'Freelancer Profile' },
    { key: 'quest', label: 'Quest Context' },
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
    category_id: props.filters.category_id ?? '',
    quest_id: props.filters.quest_id ?? '',
    freelancer_id: props.filters.freelancer_id ?? '',
    budget_min: props.filters.budget_min ?? '',
    budget_max: props.filters.budget_max ?? '',
    amount_min: props.filters.amount_min ?? '',
    amount_max: props.filters.amount_max ?? '',
    verification_tier: props.filters.verification_tier ?? '',
    trust_min: props.filters.trust_min ?? '',
    submitted_from: props.filters.submitted_from ?? '',
    submitted_to: props.filters.submitted_to ?? '',
    flag_type: props.filters.flag_type ?? '',
    acting_admin_id: props.filters.acting_admin_id ?? '',
    has_notice: Boolean(props.filters.has_notice),
    sort: props.filters.sort ?? '-created_at',
    per_page: Number(props.filters.per_page ?? 25),
});

const viewMode = ref(localStorage.getItem('adminProposalViewMode') || 'table');
const density = ref(localStorage.getItem('adminProposalDensity') || 'comfortable');
const advancedOpen = ref(false);
const selectedIds = reactive(new Set());
const activeProposal = ref(null);
const proposalDetail = ref(null);
const detailLoading = ref(false);
const activeTab = ref('content');
const actionPanel = ref(null);
const actionBusy = ref(false);
const draggedProposal = ref(null);
const resolvingFlag = ref(null);
const nestedQuest = ref(null);
const toasts = ref([]);
const noteForm = reactive({ body: '', is_pinned: false });
const bulkAction = ref(null);

const columns = [
    { key: 'status', label: 'Statuses' },
    { key: 'proposal', label: 'Proposal', sort: 'created_at' },
    { key: 'freelancer', label: 'Freelancer' },
    { key: 'trust', label: 'Verification' },
    { key: 'amount', label: 'Amount', sort: 'amount' },
    { key: 'quest', label: 'Quest' },
    { key: 'risk', label: 'Risk' },
    { key: 'created', label: 'Submitted', sort: 'created_at' },
];

const visibleColumns = computed(() => columns);
const allVisibleSelected = computed(() => props.proposals.data.length > 0 && props.proposals.data.every((proposal) => selectedIds.has(proposal.id)));
const kanbanColumns = computed(() => props.options.admin_status_options.map((status) => ({
    key: status.value,
    label: status.label,
    status: status.value,
    items: props.proposals.data.filter((proposal) => proposal.admin_status?.value === status.value),
})));
const activeFilterPills = computed(() => Object.entries(form)
    .filter(([key, value]) => !['sort', 'per_page'].includes(key) && value !== '' && value !== false && value !== null)
    .map(([key, value]) => ({ key, label: `${labelize(key)}: ${value}` })));

watch(() => form.q, debounce(() => apply(), 450));
watch(viewMode, (value) => localStorage.setItem('adminProposalViewMode', value));
watch(density, (value) => localStorage.setItem('adminProposalDensity', value));

function apply() {
    router.get(route('admin.proposals.index'), cleanPayload(form), { preserveState: true, preserveScroll: true, replace: true });
}

function visitPage(url) {
    if (url) {
        router.visit(url, { preserveState: true, preserveScroll: true });
    }
}

function applyShortcut(filter = {}) {
    Object.assign(form, { quick: '', admin_status: '' }, filter);
    apply();
}

function setQuick(value) {
    form.quick = value;
    if (String(value).startsWith('admin:')) {
        form.admin_status = '';
    }
    apply();
}

function clearFilters() {
    Object.assign(form, {
        q: '',
        quick: '',
        status: '',
        admin_status: '',
        category_id: '',
        quest_id: '',
        freelancer_id: '',
        budget_min: '',
        budget_max: '',
        amount_min: '',
        amount_max: '',
        verification_tier: '',
        trust_min: '',
        submitted_from: '',
        submitted_to: '',
        flag_type: '',
        acting_admin_id: '',
        has_notice: false,
        sort: '-created_at',
    });
    apply();
}

function removeFilter(key) {
    form[key] = typeof form[key] === 'boolean' ? false : '';
    apply();
}

function toggleSort(sort) {
    form.sort = form.sort === sort ? `-${sort}` : sort;
    apply();
}

function toggleSelection(id) {
    selectedIds.has(id) ? selectedIds.delete(id) : selectedIds.add(id);
}

function toggleVisibleSelection() {
    if (allVisibleSelected.value) {
        props.proposals.data.forEach((proposal) => selectedIds.delete(proposal.id));
    } else {
        props.proposals.data.forEach((proposal) => selectedIds.add(proposal.id));
    }
}

async function openProposalFromDeepLink(openKey) {
    if (!openKey) {
        return;
    }

    const match = props.proposals.data?.find(
        (proposal) => String(proposal.id) === String(openKey)
            || proposal.reference_code === openKey,
    );

    if (match) {
        await openProposal(match);

        return;
    }

    detailLoading.value = true;
    try {
        const { data } = await axios.get(route('admin.proposals.detail', openKey));
        const overview = data?.overview?.proposal;
        if (overview) {
            activeProposal.value = overview;
            proposalDetail.value = data;
        }
    } catch (error) {
        toast(error.response?.data?.message || 'Could not load proposal details.', 'error');
    } finally {
        detailLoading.value = false;
    }
}

onMounted(() => {
    const openKey = new URLSearchParams(window.location.search).get('open');
    if (openKey) {
        openProposalFromDeepLink(openKey);
    }
});

async function openProposal(proposal) {
    activeProposal.value = proposal;
    proposalDetail.value = null;
    actionPanel.value = null;
    activeTab.value = 'content';
    detailLoading.value = true;
    try {
        const { data } = await axios.get(route('admin.proposals.detail', proposal.id));
        proposalDetail.value = data;
    } catch (error) {
        toast(error.response?.data?.message || 'Could not load proposal details.', 'error');
    } finally {
        detailLoading.value = false;
    }
}

function closeProposal() {
    activeProposal.value = null;
    proposalDetail.value = null;
    actionPanel.value = null;
}

async function refreshDetail() {
    if (!activeProposal.value) {
        return;
    }
    const { data } = await axios.get(route('admin.proposals.detail', activeProposal.value.id));
    proposalDetail.value = data;
}

async function postAction(callback) {
    actionBusy.value = true;
    try {
        const response = await callback();
        await refreshDetail();
        actionPanel.value = null;
        router.reload({ only: ['summary', 'proposals'], preserveScroll: true });
        toast(response?.data?.message || 'Proposal action completed.');
    } catch (error) {
        toast(error.response?.data?.message || Object.values(error.response?.data?.errors || {})?.flat?.()?.[0] || 'Proposal action failed. Please review the form and try again.', 'error');
        throw error;
    } finally {
        actionBusy.value = false;
    }
}

function submitStatus(payload) {
    return postAction(() => axios.patch(route('admin.proposals.admin-status', activeProposal.value.id), payload));
}

function submitFlag(payload) {
    return postAction(() => axios.post(route('admin.proposals.flags.store', activeProposal.value.id), payload));
}

function submitNotice(payload) {
    return postAction(() => axios.post(route('admin.proposals.notices.store', activeProposal.value.id), payload));
}

function submitNote(payload) {
    return postAction(() => axios.post(route('admin.proposals.notes.store', activeProposal.value.id), payload)).then(() => {
        noteForm.body = '';
        noteForm.is_pinned = false;
    });
}

function submitContent(payload) {
    return postAction(() => axios.patch(route('admin.proposals.update', activeProposal.value.id), payload));
}

function submitResolveFlag(payload) {
    if (!resolvingFlag.value) {
        return null;
    }
    return postAction(() => axios.post(route('admin.proposals.flags.resolve', [activeProposal.value.id, resolvingFlag.value.id]), payload));
}

function submitDelete(payload) {
    return postAction(() => axios.delete(route('admin.proposals.destroy', activeProposal.value.id), { data: payload })).then(() => closeProposal());
}

function submitContact(payload) {
    const message = `Contact action recorded for ${payload.recipient}: ${payload.subject || 'Proposal moderation contact'}`;
    return submitNote({ body: `${message}\n\n${payload.body}`, is_pinned: false });
}

function requestStatusChange(proposal, status) {
    if (!proposal || proposal.admin_status?.value === status) {
        return;
    }
    activeProposal.value = proposal;
    openProposal(proposal).then(() => {
        actionPanel.value = 'status';
        toast('Choose the dragged status and enter a mandatory reason before saving.');
    });
}

function openResolve(flag) {
    resolvingFlag.value = flag;
    actionPanel.value = 'resolve';
}

function flagFromSignal(signal) {
    actionPanel.value = 'flag';
    toast(`Flag form opened for ${signal.name}.`);
}

function openNestedQuest(proposal) {
    nestedQuest.value = proposal;
}

function filterByFreelancer() {
    form.freelancer_id = proposalDetail.value.freelancer.profile.id;
    closeProposal();
    apply();
}

function filterByQuest() {
    form.quest_id = proposalDetail.value.quest.summary.id;
    closeProposal();
    apply();
}

function openBulk(action) {
    bulkAction.value = action;
    actionPanel.value = null;
    const reason = window.prompt(`Reason for bulk ${labelize(action)} (${selectedIds.size} proposals)`);
    if (!reason) {
        return;
    }
    actionBusy.value = true;
    axios.post(route('admin.proposals.bulk'), {
        ids: [...selectedIds],
        action,
        reason,
        admin_status: action === 'change_status' ? 'under_review' : null,
    }).then((response) => {
        selectedIds.clear();
        router.reload({ only: ['summary', 'proposals'], preserveScroll: true });
        toast(response.data.message || 'Bulk action completed.');
    }).catch((error) => {
        toast(error.response?.data?.message || 'Bulk action failed.', 'error');
    }).finally(() => {
        actionBusy.value = false;
    });
}

function quickCount(value) {
    const tile = props.summary.find((item) => item.filter?.quick === value);
    if (tile) {
        return tile.value;
    }
    if (value === '') {
        return props.proposals.total ?? props.proposals.data.length;
    }
    if (String(value).startsWith('admin:')) {
        return props.proposals.data.filter((proposal) => proposal.admin_status?.value === String(value).replace('admin:', '')).length;
    }
    return props.proposals.data.filter((proposal) => proposal.status === value).length;
}

function toast(message, type = 'success') {
    const id = Date.now() + Math.random();
    toasts.value.push({ id, message, type });
    window.setTimeout(() => {
        toasts.value = toasts.value.filter((item) => item.id !== id);
    }, 4200);
}

function highlightRisk(text) {
    let value = escapeHtml(String(text || ''));
    (proposalDetail.value?.risk.highlighted_phrases || []).forEach((phrase) => {
        value = value.replace(new RegExp(`(${escapeRegExp(escapeHtml(phrase))})`, 'ig'), '<mark class="risk-mark">$1</mark>');
    });
    return value;
}

function highlight(text) {
    const value = String(text || '');
    const needle = String(form.q || '').trim();
    if (!needle) {
        return escapeHtml(value);
    }
    return escapeHtml(value).replace(new RegExp(`(${escapeRegExp(needle)})`, 'ig'), '<mark class="rounded bg-primary-300/25 px-0.5 text-primary-100">$1</mark>');
}

function cleanPayload(source) {
    return Object.fromEntries(Object.entries(source).filter(([, value]) => value !== '' && value !== false && value !== null));
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

function varianceTone(tone) {
    return {
        red: 'bg-rose-500/15 text-rose-100',
        amber: 'bg-amber-500/15 text-amber-100',
        blue: 'bg-blue-500/15 text-blue-100',
        green: 'bg-emerald-500/15 text-emerald-100',
        gray: 'bg-slate-500/15 text-slate-200',
    }[tone] || 'bg-slate-500/15 text-slate-200';
}

function escapeRegExp(value) {
    return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
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
            ? h('img', { src: props.src, alt: props.name, class: `${props.large ? 'h-14 w-14' : 'h-10 w-10'} proposal-avatar rounded-full object-cover ring-2 ring-white/10` })
            : h('span', { class: `${props.large ? 'h-14 w-14 text-lg' : 'h-10 w-10 text-sm'} proposal-avatar-fallback inline-flex shrink-0 items-center justify-center rounded-full bg-primary-500/15 font-black text-primary-100 ring-2 ring-white/10` }, String(props.name || '?').charAt(0).toUpperCase());
    },
});

const StatusBadge = defineComponent({
    props: { label: String, tone: String, muted: Boolean },
    setup(props) {
        const classes = {
            blue: 'bg-blue-500/15 text-blue-100 border-blue-300/20',
            green: 'bg-emerald-500/15 text-emerald-100 border-emerald-300/20',
            red: 'bg-rose-500/15 text-rose-100 border-rose-300/20',
            gray: 'bg-slate-500/15 text-slate-200 border-slate-300/20',
        };
        return () => h('span', { class: `proposal-status-badge proposal-status-${props.tone || 'gray'} inline-flex rounded-full border px-2.5 py-1 text-[10px] font-black uppercase tracking-wide ${props.muted ? 'opacity-70' : ''} ${classes[props.tone] || classes.gray}` }, props.label);
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
        return () => h('span', { class: `proposal-admin-status-badge proposal-admin-${props.status?.tone || 'gray'} inline-flex rounded-full border px-3 py-1 text-[10px] font-black uppercase tracking-wide ${classes[props.status?.tone] || classes.gray}` }, props.status?.label || 'Clear');
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

const ProposalCard = defineComponent({
    props: { proposal: Object, selected: Boolean },
    emits: ['open', 'toggle'],
    setup(props, { emit }) {
        return () => h('article', { class: `rounded-3xl border bg-slate-900/60 p-4 ring-1 ring-white/5 transition hover:-translate-y-0.5 ${props.proposal.flag_count ? 'border-rose-400/50' : 'border-white/10'}` }, [
            h('div', { class: 'flex items-start justify-between gap-3' }, [
                h('button', { type: 'button', class: 'text-left', onClick: () => emit('open', props.proposal) }, [
                    h('p', { class: 'text-[10px] font-black uppercase tracking-wide text-slate-500' }, props.proposal.reference_code),
                    h('h3', { class: 'mt-1 line-clamp-2 font-display text-lg font-black text-white' }, props.proposal.freelancer.name || props.proposal.freelancer.email),
                ]),
                h('input', { type: 'checkbox', checked: props.selected, class: 'mt-1 rounded border-white/20 bg-slate-900 text-primary-500', onChange: () => emit('toggle', props.proposal.id) }),
            ]),
            h('p', { class: 'mt-3 line-clamp-3 text-sm leading-6 text-slate-400' }, props.proposal.cover_letter_excerpt),
            h('div', { class: 'mt-4 flex items-center justify-between gap-3 text-sm' }, [
                h('span', { class: 'font-black text-primary-100' }, props.proposal.proposed_amount),
                h('span', { class: 'rounded-full bg-white/10 px-2 py-1 text-xs font-black text-slate-300' }, `Risk ${props.proposal.risk_score}`),
            ]),
            h('p', { class: 'mt-3 truncate text-xs font-bold text-slate-500' }, props.proposal.quest.title),
            h('div', { class: 'mt-4 flex items-center justify-between' }, [
                h('div', { class: 'flex flex-wrap gap-2' }, [
                    h(AdminStatusBadge, { status: props.proposal.admin_status }),
                    h(StatusBadge, { label: props.proposal.status_label, tone: props.proposal.status_tone, muted: true }),
                ]),
                h('button', { type: 'button', class: 'rounded-xl border border-white/10 px-3 py-2 text-xs font-black uppercase tracking-wide text-white hover:bg-white/5', onClick: () => emit('open', props.proposal) }, 'Manage'),
            ]),
        ]);
    },
});

const Timeline = defineComponent({
    props: { items: Array },
    setup(props) {
        return () => h('div', { class: 'rounded-3xl border border-white/10 bg-slate-900/60 p-5' }, [
            h('h3', { class: 'font-display text-lg font-black text-white' }, 'Immutable audit trail'),
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
            h('p', { class: 'text-[10px] font-black uppercase tracking-[0.2em] text-slate-500' }, 'Flag history'),
            ...(props.flags?.length ? props.flags.map((flag) => h('div', { class: 'mt-3 rounded-2xl border border-white/10 bg-slate-950/70 p-3' }, [
                h('p', { class: `text-sm font-black ${flagTone(flag.priority)}` }, `${labelize(flag.priority)} · ${labelize(flag.type)}`),
                h('p', { class: 'mt-1 text-xs leading-5 text-slate-400' }, flag.description),
                h('p', { class: 'mt-1 text-[10px] font-bold uppercase tracking-wide text-slate-500' }, `Impact: ${labelize(flag.visibility_impact || 'none')}`),
                h('button', { type: 'button', class: 'mt-3 text-xs font-black uppercase tracking-wide text-primary-200', onClick: () => emit('resolve', flag) }, 'Resolve flag'),
            ])) : [h('p', { class: 'mt-3 text-sm text-slate-400' }, 'No active flags.')]),
        ]);
    },
});

const StatusPanel = defineComponent({
    props: { options: Array, busy: Boolean },
    emits: ['submit', 'cancel'],
    setup(props, { emit }) {
        const state = reactive({ admin_status: '', reason: '', notify_freelancer: true, notify_client: false, notification_preview: 'HustleSafe moderation has updated the review state for this proposal.' });
        return () => h('form', { class: 'space-y-3', onSubmit: (event) => { event.preventDefault(); emit('submit', { ...state }); } }, [
            h('p', { class: 'font-bold text-white' }, 'Change admin moderation status'),
            h('select', { class: 'panel-input', value: state.admin_status, onChange: (event) => { state.admin_status = event.target.value; } }, [h('option', { value: '' }, 'Choose admin status'), ...props.options.map((option) => h('option', { value: option.value }, option.label))]),
            h('textarea', { class: 'panel-input min-h-24', value: state.reason, placeholder: 'Mandatory reason (minimum 20 characters, 50 for suspension)', onInput: (event) => { state.reason = event.target.value; } }),
            h('div', { class: 'flex flex-wrap gap-4 text-sm font-bold text-slate-300' }, [
                h('label', { class: 'flex items-center gap-2' }, [h('input', { type: 'checkbox', checked: state.notify_freelancer, onChange: (event) => { state.notify_freelancer = event.target.checked; } }), 'Notify freelancer']),
                h('label', { class: 'flex items-center gap-2' }, [h('input', { type: 'checkbox', checked: state.notify_client, onChange: (event) => { state.notify_client = event.target.checked; } }), 'Notify client']),
            ]),
            h('textarea', { class: 'panel-input min-h-20', value: state.notification_preview, placeholder: 'Editable notification preview', onInput: (event) => { state.notification_preview = event.target.value; } }),
            h('div', { class: 'flex gap-2' }, [
                h('button', { type: 'submit', disabled: props.busy || !state.admin_status || state.reason.length < 20, class: 'primary-button' }, props.busy ? 'Saving...' : 'Confirm status'),
                h('button', { type: 'button', class: 'secondary-button', onClick: () => emit('cancel') }, 'Cancel'),
            ]),
        ]);
    },
});

const FlagPanel = defineComponent({
    props: { options: Object, busy: Boolean },
    emits: ['submit', 'cancel'],
    setup(props, { emit }) {
        const state = reactive({ type: 'off_platform_contact', priority: 'medium', assigned_group: 'all_moderation_admins', description: '', due_at: '', visibility_impact: 'none', notify_freelancer: false, notify_client: false });
        return () => h('form', { class: 'space-y-3', onSubmit: (event) => { event.preventDefault(); emit('submit', { ...state }); } }, [
            h('p', { class: 'font-bold text-white' }, 'Create proposal flag'),
            h('div', { class: 'grid gap-3 md:grid-cols-3' }, [
                h('select', { class: 'panel-input', value: state.type, onChange: (event) => { state.type = event.target.value; } }, props.options.flag_types.map((type) => h('option', { value: type }, labelize(type)))),
                h('select', { class: 'panel-input', value: state.priority, onChange: (event) => { state.priority = event.target.value; } }, props.options.flag_priorities.map((priority) => h('option', { value: priority }, labelize(priority)))),
                h('select', { class: 'panel-input', value: state.assigned_group, onChange: (event) => { state.assigned_group = event.target.value; } }, props.options.flag_groups.map((group) => h('option', { value: group }, labelize(group)))),
            ]),
            h('select', { class: 'panel-input', value: state.visibility_impact, onChange: (event) => { state.visibility_impact = event.target.value; } }, [
                h('option', { value: 'none' }, 'No visibility change'),
                h('option', { value: 'restrict_acceptance' }, 'Restrict acceptance while flag is open'),
                h('option', { value: 'hide_pending_resolution' }, 'Suspend and hide pending resolution'),
            ]),
            h('textarea', { class: 'panel-input min-h-24', value: state.description, placeholder: 'Describe the issue, evidence, and next action', onInput: (event) => { state.description = event.target.value; } }),
            h('input', { type: 'date', class: 'panel-input', value: state.due_at, onInput: (event) => { state.due_at = event.target.value; } }),
            h('div', { class: 'flex gap-2' }, [h('button', { type: 'submit', disabled: props.busy || state.description.length < (state.visibility_impact === 'hide_pending_resolution' ? 50 : 30), class: state.visibility_impact === 'hide_pending_resolution' ? 'danger-button' : 'primary-button' }, props.busy ? 'Saving...' : 'Create flag'), h('button', { type: 'button', class: 'secondary-button', onClick: () => emit('cancel') }, 'Cancel')]),
        ]);
    },
});

const NoticePanel = defineComponent({
    props: { busy: Boolean },
    emits: ['submit', 'cancel'],
    setup(props, { emit }) {
        const state = reactive({ type: 'warning', body: '', visible_to_freelancer: true, visible_to_client: true, notify_stakeholders: false });
        return () => h('form', { class: 'space-y-3', onSubmit: (event) => { event.preventDefault(); emit('submit', { ...state }); } }, [
            h('p', { class: 'font-bold text-white' }, 'Post user-facing proposal notice'),
            h('select', { class: 'panel-input', value: state.type, onChange: (event) => { state.type = event.target.value; } }, ['warning', 'informational', 'urgent', 'resolved'].map((type) => h('option', { value: type }, labelize(type)))),
            h('textarea', { class: 'panel-input min-h-24', value: state.body, placeholder: 'Notice text visible to selected parties', onInput: (event) => { state.body = event.target.value; } }),
            h('div', { class: 'flex flex-wrap gap-4 text-sm font-bold text-slate-300' }, [
                h('label', { class: 'flex items-center gap-2' }, [h('input', { type: 'checkbox', checked: state.visible_to_freelancer, onChange: (event) => { state.visible_to_freelancer = event.target.checked; } }), 'Visible to freelancer']),
                h('label', { class: 'flex items-center gap-2' }, [h('input', { type: 'checkbox', checked: state.visible_to_client, onChange: (event) => { state.visible_to_client = event.target.checked; } }), 'Visible to client']),
                h('label', { class: 'flex items-center gap-2' }, [h('input', { type: 'checkbox', checked: state.notify_stakeholders, onChange: (event) => { state.notify_stakeholders = event.target.checked; } }), 'Notify stakeholders']),
            ]),
            h('div', { class: 'flex gap-2' }, [
                h('button', { type: 'submit', disabled: props.busy || state.body.length < 10, class: 'primary-button' }, props.busy ? 'Posting...' : 'Post notice'),
                h('button', { type: 'button', class: 'secondary-button', onClick: () => emit('cancel') }, 'Cancel'),
            ]),
        ]);
    },
});

const ContactPanel = defineComponent({
    props: { busy: Boolean, inline: Boolean },
    emits: ['submit', 'cancel'],
    setup(props, { emit }) {
        const state = reactive({ recipient: 'freelancer', channel: 'email', subject: '', body: '', schedule_at: '', cc_client: false });
        return () => h('form', { class: props.inline ? 'rounded-3xl border border-white/10 bg-slate-900/60 p-5 space-y-3' : 'space-y-3', onSubmit: (event) => { event.preventDefault(); emit('submit', { ...state }); } }, [
            h('p', { class: 'font-bold text-white' }, 'Contact & comms composer'),
            h('div', { class: 'grid gap-3 md:grid-cols-3' }, [
                h('select', { class: 'panel-input', value: state.recipient, onChange: (event) => { state.recipient = event.target.value; } }, ['freelancer', 'client', 'both'].map((type) => h('option', { value: type }, labelize(type)))),
                h('select', { class: 'panel-input', value: state.channel, onChange: (event) => { state.channel = event.target.value; } }, ['email', 'in_app', 'cs_chat'].map((type) => h('option', { value: type }, labelize(type)))),
                h('input', { class: 'panel-input', type: 'datetime-local', value: state.schedule_at, onInput: (event) => { state.schedule_at = event.target.value; } }),
            ]),
            h('input', { class: 'panel-input w-full', value: state.subject, placeholder: 'Subject', onInput: (event) => { state.subject = event.target.value; } }),
            h('textarea', { class: 'panel-input min-h-32 w-full', value: state.body, placeholder: 'Rich text content / CS chat summary', onInput: (event) => { state.body = event.target.value; } }),
            h('label', { class: 'flex items-center gap-2 text-sm font-bold text-slate-300' }, [h('input', { type: 'checkbox', checked: state.cc_client, onChange: (event) => { state.cc_client = event.target.checked; } }), 'CC client']),
            h('div', { class: 'flex gap-2' }, [
                h('button', { type: 'submit', disabled: props.busy || state.body.length < 10, class: 'primary-button' }, props.busy ? 'Recording...' : 'Record contact'),
                props.inline ? null : h('button', { type: 'button', class: 'secondary-button', onClick: () => emit('cancel') }, 'Cancel'),
            ]),
        ]);
    },
});

const ContentPanel = defineComponent({
    props: { content: Object, busy: Boolean },
    emits: ['submit', 'cancel'],
    setup(props, { emit }) {
        const state = reactive({
            pitch: props.content?.pitch || '',
            scope_detail: props.content?.scope_detail || '',
            warranty_terms: props.content?.warranty_terms || '',
            quoted_amount_minor: props.content?.quoted_amount_minor || 0,
            estimated_duration_days: '',
            reason: '',
            notify_freelancer: true,
            notify_client: true,
        });
        return () => h('form', { class: 'space-y-3', onSubmit: (event) => { event.preventDefault(); emit('submit', { ...state }); } }, [
            h('p', { class: 'font-bold text-white' }, 'Super Admin content edit'),
            h('textarea', { class: 'panel-input min-h-32 w-full', value: state.pitch, onInput: (event) => { state.pitch = event.target.value; } }),
            h('textarea', { class: 'panel-input min-h-24 w-full', value: state.scope_detail, placeholder: 'Scope detail', onInput: (event) => { state.scope_detail = event.target.value; } }),
            h('div', { class: 'grid gap-3 md:grid-cols-2' }, [
                h('input', { class: 'panel-input', type: 'number', value: state.quoted_amount_minor, placeholder: 'Amount minor', onInput: (event) => { state.quoted_amount_minor = Number(event.target.value); } }),
                h('input', { class: 'panel-input', type: 'number', value: state.estimated_duration_days, placeholder: 'Estimated days', onInput: (event) => { state.estimated_duration_days = Number(event.target.value); } }),
            ]),
            h('textarea', { class: 'panel-input min-h-24 w-full', value: state.reason, placeholder: 'Mandatory reason for diff audit', onInput: (event) => { state.reason = event.target.value; } }),
            h('div', { class: 'flex gap-2' }, [
                h('button', { type: 'submit', disabled: props.busy || state.reason.length < 30, class: 'primary-button' }, props.busy ? 'Saving...' : 'Save audited edit'),
                h('button', { type: 'button', class: 'secondary-button', onClick: () => emit('cancel') }, 'Cancel'),
            ]),
        ]);
    },
});

const ResolveFlagPanel = defineComponent({
    props: { flag: Object, busy: Boolean },
    emits: ['submit', 'cancel'],
    setup(props, { emit }) {
        const state = reactive({ resolution_outcome: 'actioned_resolved', resolution_note: '' });
        return () => h('form', { class: 'space-y-3', onSubmit: (event) => { event.preventDefault(); emit('submit', { ...state }); } }, [
            h('p', { class: 'font-bold text-white' }, 'Resolve proposal flag'),
            h('select', { class: 'panel-input', value: state.resolution_outcome, onChange: (event) => { state.resolution_outcome = event.target.value; } }, ['actioned_resolved', 'escalated_to_super_admin', 'no_action_required', 'referred_to_another_team'].map((type) => h('option', { value: type }, labelize(type)))),
            h('textarea', { class: 'panel-input min-h-24', value: state.resolution_note, placeholder: 'Resolution note', onInput: (event) => { state.resolution_note = event.target.value; } }),
            h('div', { class: 'flex gap-2' }, [h('button', { type: 'submit', disabled: props.busy || state.resolution_note.length < 10, class: 'primary-button' }, 'Resolve flag'), h('button', { type: 'button', class: 'secondary-button', onClick: () => emit('cancel') }, 'Cancel')]),
        ]);
    },
});

const DeletePanel = defineComponent({
    props: { busy: Boolean },
    emits: ['submit', 'cancel'],
    setup(props, { emit }) {
        const state = reactive({ confirmation: '', reason: '' });
        return () => h('form', { class: 'space-y-3', onSubmit: (event) => { event.preventDefault(); emit('submit', { ...state }); } }, [
            h('p', { class: 'font-bold text-rose-100' }, 'Permanent proposal removal'),
            h('input', { class: 'panel-input w-full', value: state.confirmation, placeholder: 'Type removal confirmation', onInput: (event) => { state.confirmation = event.target.value; } }),
            h('textarea', { class: 'panel-input min-h-24 w-full', value: state.reason, placeholder: 'Mandatory destructive action reason', onInput: (event) => { state.reason = event.target.value; } }),
            h('div', { class: 'flex gap-2' }, [h('button', { type: 'submit', disabled: props.busy || state.reason.length < 30, class: 'danger-button' }, props.busy ? 'Removing...' : 'Remove proposal'), h('button', { type: 'button', class: 'secondary-button', onClick: () => emit('cancel') }, 'Cancel')]),
        ]);
    },
});
</script>

<style scoped>
.proposal-engine-page {
    @apply space-y-5;
}

:global(.admin-theme-light .proposal-engine-page) {
    background-color: #f8fafc;
    color: #0f172a;
}

:global(.admin-theme-light .proposal-engine-page [class*="bg-slate-950"]),
:global(.admin-theme-light .proposal-engine-page [class*="bg-slate-900"]),
:global(.admin-theme-light .proposal-engine-page [class*="bg-slate-800"]) {
    background-color: #fff !important;
}

:global(.admin-theme-light .proposal-engine-page [class*="bg-white/5"]),
:global(.admin-theme-light .proposal-engine-page [class*="bg-white/10"]) {
    background-color: #f8fafc !important;
}

:global(.admin-theme-light .proposal-engine-page [class*="border-white/"]),
:global(.admin-theme-light .proposal-engine-page [class*="border-slate-700"]),
:global(.admin-theme-light .proposal-engine-page [class*="border-slate-800"]) {
    border-color: #e2e8f0 !important;
}

:global(.admin-theme-light .proposal-engine-page [class*="divide-white/"]) > :not([hidden]) ~ :not([hidden]) {
    border-color: #e2e8f0 !important;
}

:global(.admin-theme-light .proposal-engine-page [class*="ring-white/"]) {
    --tw-ring-color: #f1f5f9 !important;
}

:global(.admin-theme-light .proposal-engine-page .text-white),
:global(.admin-theme-light .proposal-engine-page [class*="text-white"]) {
    color: #0f172a !important;
}

:global(.admin-theme-light .proposal-engine-page [class*="text-slate-100"]),
:global(.admin-theme-light .proposal-engine-page [class*="text-slate-200"]),
:global(.admin-theme-light .proposal-engine-page [class*="text-slate-300"]),
:global(.admin-theme-light .proposal-engine-page [class*="text-slate-400"]) {
    color: #475569 !important;
}

:global(.admin-theme-light .proposal-engine-page [class*="text-slate-500"]) {
    color: #64748b !important;
}

:global(.admin-theme-light .proposal-engine-page :where(input, select, textarea)) {
    background-color: #fff !important;
    border-color: #cbd5e1 !important;
    color: #0f172a !important;
}

:global(.admin-theme-light .proposal-engine-page mark) {
    background-color: rgb(14 165 233 / 0.16) !important;
    color: #075985 !important;
}

:global(.admin-theme-light .proposal-engine-page .proposal-avatar) {
    border: 2px solid #2563eb !important;
    box-shadow: 0 0 0 4px rgb(37 99 235 / 0.14) !important;
}

:global(.admin-theme-light .proposal-engine-page .proposal-avatar-fallback) {
    background: linear-gradient(135deg, #1d4ed8, #7c3aed) !important;
    color: #fff !important;
    box-shadow: 0 0 0 4px rgb(37 99 235 / 0.14) !important;
}

:global(.admin-theme-light .proposal-engine-page .proposal-admin-status-badge),
:global(.admin-theme-light .proposal-engine-page .proposal-status-badge) {
    opacity: 1 !important;
    box-shadow: 0 8px 18px rgb(15 23 42 / 0.08) !important;
}

:global(.admin-theme-light .proposal-engine-page .proposal-admin-gray),
:global(.admin-theme-light .proposal-engine-page .proposal-status-gray) {
    background-color: #f1f5f9 !important;
    border-color: #64748b !important;
    color: #334155 !important;
}

:global(.admin-theme-light .proposal-engine-page .proposal-admin-orange) {
    background-color: #ffedd5 !important;
    border-color: #f97316 !important;
    color: #9a3412 !important;
}

:global(.admin-theme-light .proposal-engine-page .proposal-admin-indigo),
:global(.admin-theme-light .proposal-engine-page .proposal-status-blue) {
    background-color: #dbeafe !important;
    border-color: #2563eb !important;
    color: #1e3a8a !important;
}

:global(.admin-theme-light .proposal-engine-page .proposal-admin-purple) {
    background-color: #ede9fe !important;
    border-color: #7c3aed !important;
    color: #4c1d95 !important;
}

:global(.admin-theme-light .proposal-engine-page .proposal-admin-amber),
:global(.admin-theme-light .proposal-engine-page .proposal-admin-yellow) {
    background-color: #fef3c7 !important;
    border-color: #d97706 !important;
    color: #78350f !important;
}

:global(.admin-theme-light .proposal-engine-page .proposal-admin-dark_red),
:global(.admin-theme-light .proposal-engine-page .proposal-status-red) {
    background-color: #fee2e2 !important;
    border-color: #dc2626 !important;
    color: #7f1d1d !important;
}

:global(.admin-theme-light .proposal-engine-page .proposal-admin-green),
:global(.admin-theme-light .proposal-engine-page .proposal-status-green) {
    background-color: #dcfce7 !important;
    border-color: #16a34a !important;
    color: #14532d !important;
}

:global(.admin-theme-light .proposal-engine-page .rounded-full.bg-primary-500\/15),
:global(.admin-theme-light .proposal-engine-page .rounded-full.bg-rose-500\/15),
:global(.admin-theme-light .proposal-engine-page .rounded-full.bg-amber-500\/15),
:global(.admin-theme-light .proposal-engine-page .rounded-full.bg-blue-500\/15),
:global(.admin-theme-light .proposal-engine-page .rounded-full.bg-emerald-500\/15) {
    border: 1px solid currentColor !important;
}

:global(.proposal-engine-page .risk-mark) {
    border-bottom: 3px solid rgb(245 158 11 / 0.85);
    background-color: rgb(245 158 11 / 0.16);
    color: inherit;
}

.control-button,
.secondary-button {
    @apply inline-flex min-h-11 items-center justify-center rounded-xl border border-white/10 bg-white/5 px-3 text-xs font-black uppercase tracking-wide text-slate-200 transition hover:bg-white/10 disabled:cursor-not-allowed disabled:opacity-50;
}

.primary-button {
    @apply inline-flex min-h-11 items-center justify-center rounded-xl bg-primary-500 px-4 text-xs font-black uppercase tracking-wide text-slate-950 shadow-lg shadow-slate-950/30 transition hover:bg-primary-400 disabled:cursor-not-allowed disabled:opacity-50;
}

.learn-more-link {
    @apply inline-flex items-center justify-center rounded-xl border border-primary-200 bg-white px-3 py-2 text-xs font-black uppercase tracking-wide text-primary-800 shadow-sm transition hover:bg-primary-100;
}

.danger-button {
    @apply inline-flex min-h-11 items-center justify-center rounded-xl border border-rose-400/30 bg-rose-500/10 px-3 text-xs font-black uppercase tracking-wide text-rose-100 transition hover:bg-rose-500/20 disabled:cursor-not-allowed disabled:opacity-50;
}

.control-select,
.panel-input,
.filter-field select,
.filter-field input,
.filter-field button {
    @apply min-h-11 rounded-xl border border-white/10 bg-slate-950 px-3 text-sm font-semibold text-white outline-none ring-2 ring-transparent transition focus:border-primary-400/60 focus:ring-primary-500/30;
}

.filter-field {
    @apply flex flex-col gap-1 text-[10px] font-black uppercase tracking-[0.18em] text-slate-500;
}
</style>
