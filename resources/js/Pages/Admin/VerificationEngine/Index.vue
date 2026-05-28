<template>
    <AdminShell
        title="Verification Engine"
        subtitle="Tiered trust, dynamic limits, safeguards, review queues, anomaly flags, and full auditability."
    >
        <div class="space-y-5">
            <section class="flex flex-wrap items-center justify-between gap-3 rounded-3xl border border-primary-100 bg-primary-50/90 p-4 text-slate-900 ring-1 ring-primary-100">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.2em] text-primary-700">Documentation</p>
                    <p class="mt-1 text-sm font-bold text-slate-800">Need help with document review, trust levels, restrictions, or anomaly signals?</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link :href="route('admin.documentation.guide', { topic: 'verification-trust' }) + '#document-review-workflow'" class="rounded-xl border border-primary-200 bg-white px-3 py-2 text-xs font-black uppercase tracking-wide text-primary-800 shadow-sm hover:bg-primary-100">Document review</Link>
                    <Link :href="route('admin.documentation.guide', { topic: 'verification-trust' }) + '#trust-levels-and-limits'" class="rounded-xl border border-primary-200 bg-white px-3 py-2 text-xs font-black uppercase tracking-wide text-primary-800 shadow-sm hover:bg-primary-100">Trust levels</Link>
                    <Link :href="route('admin.documentation.guide', { topic: 'risk-engine' })" class="rounded-xl border border-primary-200 bg-white px-3 py-2 text-xs font-black uppercase tracking-wide text-primary-800 shadow-sm hover:bg-primary-100">Risk signals</Link>
                </div>
            </section>

            <div class="grid gap-3 md:grid-cols-6">
                <div v-for="level in [0, 1, 2, 3, 4, 5]" :key="level" class="rounded-3xl border p-4 shadow-sm" :class="shell.card">
                    <p class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">Level L{{ level }}</p>
                    <p class="mt-2 text-3xl font-black" :class="shell.title">{{ levelCounts[level] || 0 }}</p>
                    <p class="mt-1 text-xs font-bold" :class="shell.cardMuted">{{ levels[level]?.label || `L${level}` }}</p>
                </div>
            </div>

            <AdminTabbedPage v-model="activeTab" :tabs="tabs" id-prefix="verification-engine" aria-label="Verification engine sections">
            <AdminTabPanel :current-tab="activeTab" value="settings" id-prefix="verification-engine">
                <AdminPanel title="Verification Settings" description="Control verification types and the requirements that unlock L0-L5. Changes take effect immediately after save.">
                    <form class="space-y-6" @submit.prevent="saveTypes">
                        <div class="grid gap-3 lg:grid-cols-2">
                            <div v-for="(type, key) in typesForm.types" :key="key" class="rounded-3xl border p-4" :class="shell.card">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-black">{{ type.label }}</p>
                                        <p class="mt-1 text-xs font-bold" :class="shell.cardMuted">{{ key.replace(/_/g, ' ') }}</p>
                                    </div>
                                    <button type="button" class="rounded-xl px-3 py-1.5 text-[11px] font-black uppercase tracking-wide" :class="shell.btnGhost" @click="toggleSettingEditor(`type:${key}`)">
                                        {{ editingSetting === `type:${key}` ? 'Close' : 'Edit' }}
                                    </button>
                                </div>
                                <div v-if="editingSetting === `type:${key}`" class="mt-4 space-y-3">
                                    <input v-model="type.label" class="w-full rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                                    <label class="flex items-center gap-2 text-xs font-black">
                                        <input v-model="type.enabled" type="checkbox" />
                                        Enabled platform-wide
                                    </label>
                                    <button type="submit" class="rounded-xl px-4 py-2 text-xs font-black uppercase" :class="shell.btnPrimary" :disabled="typesForm.processing">
                                        Save
                                    </button>
                                </div>
                                <div v-else class="mt-4">
                                    <span class="rounded-full px-3 py-1 text-xs font-black" :class="type.enabled ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/15 dark:text-emerald-200' : 'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-300'">
                                        {{ type.enabled ? 'Enabled' : 'Disabled' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-8">
                            <div>
                                <h3 class="text-sm font-black uppercase tracking-wide text-primary-800">Client levels (L0–L5)</h3>
                                <p class="mt-1 text-xs font-bold" :class="shell.cardMuted">Email → identity &amp; address → NIN → BVN → 180-day account age.</p>
                                <div class="mt-4 grid gap-3 xl:grid-cols-3">
                                    <div v-for="level in [0, 1, 2, 3, 4, 5]" :key="`client-${level}`" class="rounded-3xl border p-4" :class="shell.card">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <p class="font-black">L{{ level }} requirements</p>
                                                <p class="mt-1 text-xs font-bold" :class="shell.cardMuted">{{ client_levels[level]?.label || `Level ${level}` }}</p>
                                            </div>
                                            <button type="button" class="rounded-xl px-3 py-1.5 text-[11px] font-black uppercase tracking-wide" :class="shell.btnGhost" @click="toggleSettingEditor(`client:level:${level}`)">
                                                {{ editingSetting === `client:level:${level}` ? 'Close' : 'Edit' }}
                                            </button>
                                        </div>
                                        <div v-if="editingSetting === `client:level:${level}`" class="mt-4 space-y-2">
                                            <label v-for="option in requirementOptions" :key="`client-${level}-${option.key}`" class="flex items-start gap-2 rounded-2xl border p-3 text-sm font-bold" :class="shell.card">
                                                <input v-model="clientRequirementState[level].checks" :value="option.key" type="checkbox" class="mt-1 rounded border-slate-300 text-primary-600" />
                                                <span>
                                                    <span class="block">{{ option.label }}</span>
                                                    <span class="block text-[11px] font-semibold" :class="shell.cardMuted">{{ option.hint }}</span>
                                                </span>
                                            </label>
                                            <label class="block rounded-2xl border p-3 text-sm font-bold" :class="shell.card">
                                                <span>Minimum account age in days</span>
                                                <input v-model.number="clientRequirementState[level].accountAgeDays" type="number" min="0" class="mt-2 w-full rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                                            </label>
                                        </div>
                                        <div v-else class="mt-4 flex flex-wrap gap-2">
                                            <span v-for="item in requirementSummary(clientRequirementState[level], requirementOptions)" :key="item" class="rounded-full bg-primary-50 px-3 py-1 text-xs font-black text-primary-800 dark:bg-primary-400/15 dark:text-primary-100">
                                                {{ item }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <h3 class="text-sm font-black uppercase tracking-wide text-primary-800">Freelancer levels (L0–L5)</h3>
                                <p class="mt-1 text-xs font-bold" :class="shell.cardMuted">Email → identity &amp; address → NIN &amp; BVN → CAC/TIN → 90 days + selfie + ID.</p>
                                <div class="mt-4 grid gap-3 xl:grid-cols-3">
                                    <div v-for="level in [0, 1, 2, 3, 4, 5]" :key="`freelancer-${level}`" class="rounded-3xl border p-4" :class="shell.card">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <p class="font-black">L{{ level }} requirements</p>
                                                <p class="mt-1 text-xs font-bold" :class="shell.cardMuted">{{ freelancer_levels[level]?.label || `Level ${level}` }}</p>
                                            </div>
                                            <button type="button" class="rounded-xl px-3 py-1.5 text-[11px] font-black uppercase tracking-wide" :class="shell.btnGhost" @click="toggleSettingEditor(`freelancer:level:${level}`)">
                                                {{ editingSetting === `freelancer:level:${level}` ? 'Close' : 'Edit' }}
                                            </button>
                                        </div>
                                        <div v-if="editingSetting === `freelancer:level:${level}`" class="mt-4 space-y-2">
                                            <label v-for="option in freelancerRequirementOptions" :key="`freelancer-${level}-${option.key}`" class="flex items-start gap-2 rounded-2xl border p-3 text-sm font-bold" :class="shell.card">
                                                <input v-model="freelancerRequirementState[level].checks" :value="option.key" type="checkbox" class="mt-1 rounded border-slate-300 text-primary-600" />
                                                <span>
                                                    <span class="block">{{ option.label }}</span>
                                                    <span class="block text-[11px] font-semibold" :class="shell.cardMuted">{{ option.hint }}</span>
                                                </span>
                                            </label>
                                            <label class="flex items-start gap-2 rounded-2xl border p-3 text-sm font-bold" :class="shell.card">
                                                <input v-model="freelancerRequirementState[level].businessEither" type="checkbox" class="mt-1 rounded border-slate-300 text-primary-600" />
                                                <span>
                                                    <span class="block">CAC or TIN accepted</span>
                                                    <span class="block text-[11px] font-semibold" :class="shell.cardMuted">Either business document can satisfy this level.</span>
                                                </span>
                                            </label>
                                            <label class="block rounded-2xl border p-3 text-sm font-bold" :class="shell.card">
                                                <span>Minimum account age in days</span>
                                                <input v-model.number="freelancerRequirementState[level].accountAgeDays" type="number" min="0" class="mt-2 w-full rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                                            </label>
                                        </div>
                                        <div v-else class="mt-4 flex flex-wrap gap-2">
                                            <span v-for="item in requirementSummary(freelancerRequirementState[level], freelancerRequirementOptions)" :key="item" class="rounded-full bg-primary-50 px-3 py-1 text-xs font-black text-primary-800 dark:bg-primary-400/15 dark:text-primary-100">
                                                {{ item }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <h3 class="text-sm font-black uppercase tracking-wide text-primary-800">Stage copy (user-facing)</h3>
                                <p class="mt-1 text-xs font-bold" :class="shell.cardMuted">Editable titles, messages, and info bars shown on the verification page.</p>
                                <div class="mt-4 grid gap-3 lg:grid-cols-2">
                                    <div v-for="role in ['client', 'freelancer']" :key="role" class="rounded-3xl border p-4" :class="shell.card">
                                        <p class="font-black capitalize">{{ role }} stages</p>
                                        <div v-for="level in [1, 2, 3, 4, 5]" :key="`${role}-${level}`" class="mt-4 rounded-2xl border p-3" :class="shell.card">
                                            <button type="button" class="text-xs font-black uppercase tracking-wide text-primary-700" @click="toggleSettingEditor(`stage:${role}:${level}`)">
                                                L{{ level }} · {{ editingSetting === `stage:${role}:${level}` ? 'Close' : 'Edit copy' }}
                                            </button>
                                            <div v-if="editingSetting === `stage:${role}:${level}`" class="mt-3 space-y-2">
                                                <input v-model="typesForm.stage_content[role][level].title" placeholder="Title" class="w-full rounded-2xl border px-3 py-2 text-sm font-semibold" :class="shell.input" />
                                                <textarea v-model="typesForm.stage_content[role][level].message" rows="2" placeholder="Message" class="w-full rounded-2xl border px-3 py-2 text-sm font-semibold" :class="shell.input" />
                                                <textarea v-model="typesForm.stage_content[role][level].info_bar" rows="2" placeholder="Info bar" class="w-full rounded-2xl border px-3 py-2 text-sm font-semibold" :class="shell.input" />
                                            </div>
                                            <p v-else class="mt-2 text-xs font-semibold" :class="shell.cardMuted">{{ typesForm.stage_content[role]?.[level]?.title || '—' }}</p>
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="mt-4 rounded-xl px-4 py-2 text-xs font-black uppercase" :class="shell.btnPrimary" :disabled="typesForm.processing">
                                    Save all settings
                                </button>
                            </div>
                        </div>
                    </form>
                </AdminPanel>
            </AdminTabPanel>

            <AdminTabPanel :current-tab="activeTab" value="limits" id-prefix="verification-engine">
                <AdminPanel title="Limit Configuration" description="Client limits cap maximum quest budget per post. Freelancer limits cap the quest value they can propose on. Stored in the database and enforced at runtime.">
                    <form class="grid gap-4 xl:grid-cols-2" @submit.prevent="saveLimits">
                        <div class="rounded-3xl border p-4" :class="shell.card">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="font-black">Client Quest Posting Limits</h3>
                                <button type="button" class="rounded-xl px-3 py-1.5 text-[11px] font-black uppercase tracking-wide" :class="shell.btnGhost" @click="editingLimits = editingLimits === 'client' ? '' : 'client'">
                                    {{ editingLimits === 'client' ? 'Close' : 'Edit' }}
                                </button>
                            </div>
                            <LimitRow v-for="level in [0, 1, 2, 3, 4, 5]" :key="`client-${level}`" v-model="limitsForm.client_posting_minor[level]" :level="level" :editing="editingLimits === 'client'" />
                            <button v-if="editingLimits === 'client'" type="submit" class="mt-4 rounded-xl px-4 py-2 text-xs font-black uppercase" :class="shell.btnPrimary" :disabled="limitsForm.processing">Save client limits</button>
                        </div>
                        <div class="rounded-3xl border p-4" :class="shell.card">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="font-black">Freelancer Proposal Limits</h3>
                                <button type="button" class="rounded-xl px-3 py-1.5 text-[11px] font-black uppercase tracking-wide" :class="shell.btnGhost" @click="editingLimits = editingLimits === 'freelancer' ? '' : 'freelancer'">
                                    {{ editingLimits === 'freelancer' ? 'Close' : 'Edit' }}
                                </button>
                            </div>
                            <LimitRow v-for="level in [0, 1, 2, 3, 4, 5]" :key="`freelancer-${level}`" v-model="limitsForm.freelancer_proposal_minor[level]" :level="level" :editing="editingLimits === 'freelancer'" />
                            <button v-if="editingLimits === 'freelancer'" type="submit" class="mt-4 rounded-xl px-4 py-2 text-xs font-black uppercase" :class="shell.btnPrimary" :disabled="limitsForm.processing">
                                Save freelancer limits
                            </button>
                        </div>
                    </form>
                </AdminPanel>
            </AdminTabPanel>

            <AdminTabPanel :current-tab="activeTab" value="safeguards" id-prefix="verification-engine">
                <AdminPanel title="Safeguard Configuration" description="Escrow, milestones, reposting, arbitration, and anomaly detection thresholds.">
                    <form class="grid gap-3 md:grid-cols-2 xl:grid-cols-3" @submit.prevent="saveSafeguards">
                        <div v-for="field in safeguardFields" :key="field.key" class="rounded-3xl border p-4" :class="shell.card">
                            <div class="flex items-start justify-between gap-3">
                                <span class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">{{ field.label }}</span>
                                <button type="button" class="rounded-xl px-3 py-1.5 text-[11px] font-black uppercase tracking-wide" :class="shell.btnGhost" @click="editingSafeguard = editingSafeguard === field.key ? '' : field.key">
                                    {{ editingSafeguard === field.key ? 'Close' : 'Edit' }}
                                </button>
                            </div>
                            <div v-if="editingSafeguard === field.key && field.money" class="mt-2">
                                <div class="flex rounded-2xl border" :class="shell.input">
                                    <span class="flex items-center border-r px-4 text-sm font-black">₦</span>
                                    <input
                                        :value="moneyInputValue(field.key)"
                                        type="text"
                                        inputmode="decimal"
                                        class="min-w-0 flex-1 rounded-r-2xl border-0 bg-transparent px-4 py-3 text-sm font-semibold focus:outline-none"
                                        placeholder="0.00"
                                        @input="updateMoneySafeguard(field.key, $event.target.value)"
                                    />
                                </div>
                                <span class="mt-1 block text-[11px] font-black text-primary-700 dark:text-primary-200">Saved as {{ formatMoney(safeguardForm[field.key]) }}</span>
                            </div>
                            <input v-else-if="editingSafeguard === field.key" v-model.number="safeguardForm[field.key]" type="number" min="0" class="mt-2 w-full rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                            <p v-else class="mt-2 text-2xl font-black" :class="shell.title">
                                {{ field.money ? formatMoney(safeguardForm[field.key]) : safeguardForm[field.key] }}
                            </p>
                            <span class="mt-1 block text-xs font-bold" :class="shell.cardMuted">{{ field.hint }}</span>
                            <button v-if="editingSafeguard === field.key" type="submit" class="mt-4 rounded-xl px-4 py-2 text-xs font-black uppercase" :class="shell.btnPrimary" :disabled="safeguardForm.processing">
                                Save value
                            </button>
                        </div>
                    </form>
                </AdminPanel>
            </AdminTabPanel>

            <AdminTabPanel :current-tab="activeTab" value="queue" id-prefix="verification-engine">
                <AdminPanel title="Document Review Desk" description="One row per user and verification type — the latest outcome only. Open a row to review every attempt for that type (approve, reject, request corrections, or re-review an approval).">
                    <div class="mb-4 grid gap-3 sm:grid-cols-[1fr_12rem_auto]">
                        <input
                            v-model="queueSearch"
                            type="search"
                            placeholder="Search name, email, or verification type…"
                            class="rounded-2xl border px-4 py-3 text-sm font-semibold"
                            :class="shell.input"
                            @input="debouncedQueueReload"
                        />
                        <button type="button" class="rounded-2xl px-4 py-3 text-sm font-black uppercase" :class="shell.btnGhost" @click="reloadQueue">
                            Refresh
                        </button>
                    </div>
                    <div class="grid gap-3">
                        <div
                            v-for="item in pending.data"
                            :key="`${item.user_id}-${item.type_key || item.type}`"
                            class="cursor-pointer rounded-3xl border p-4 transition hover:border-primary-300 hover:shadow-md"
                            :class="shell.card"
                            role="button"
                            tabindex="0"
                            @click="openAccountTimeline(item)"
                            @keydown.enter.prevent="openAccountTimeline(item)"
                        >
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="text-sm font-black">{{ item.user.name }} · L{{ item.user.level }}</p>
                                        <span class="rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-wide" :class="statusPill(item.status)">
                                            {{ labelize(item.status) }}
                                        </span>
                                    </div>
                                    <p class="mt-1 text-xs font-bold text-slate-500">
                                        {{ item.user.email }} · {{ item.type_label || labelize(item.type) }} · submitted {{ item.submitted_at_label || dateLabel(item.submitted_at) }}
                                    </p>
                                    <div v-if="item.document_previews?.length" class="mt-3 flex flex-wrap gap-2">
                                        <button
                                            v-for="doc in item.document_previews"
                                            :key="doc.path"
                                            type="button"
                                            class="h-16 w-16 overflow-hidden rounded-xl border border-slate-200 bg-slate-100 shadow-sm ring-1 ring-slate-100"
                                            @click.stop="openAccountTimeline(item)"
                                        >
                                            <img v-if="doc.is_image" :src="doc.url" :alt="doc.label" class="h-full w-full object-cover" />
                                            <span v-else class="flex h-full items-center justify-center text-lg">{{ doc.is_pdf ? '📄' : '📎' }}</span>
                                        </button>
                                    </div>
                                    <p v-if="item.concern || item.reason" class="mt-3 rounded-2xl border border-amber-100 bg-amber-50 p-3 text-sm font-bold text-amber-950">
                                        {{ item.concern || item.reason }}
                                    </p>
                                </div>
                                <div class="flex flex-wrap gap-2" @click.stop>
                                    <button type="button" class="rounded-xl px-4 py-2 text-xs font-black" :class="shell.btnGhost" @click="openAccountTimeline(item)">Account timeline</button>
                                    <button
                                        v-if="item.needs_review"
                                        type="button"
                                        class="rounded-xl px-4 py-2 text-xs font-black"
                                        :class="shell.btnPrimary"
                                        @click="openAccountTimeline(item)"
                                    >
                                        Review now
                                    </button>
                                </div>
                            </div>
                        </div>
                        <EmptyState v-if="!pending.data?.length" message="0 documents need attention. The review desk is clear." />
                    </div>
                </AdminPanel>
            </AdminTabPanel>

            <AdminTabPanel :current-tab="activeTab" value="anomalies" id-prefix="verification-engine">
                <AdminPanel title="Anomaly Flags Queue" description="Risk signals for super-admin review. Flags do not restrict users until an admin acts.">
                    <div class="grid gap-3">
                        <div v-for="flag in anomalies.data" :key="flag.id" class="rounded-3xl border p-4" :class="shell.card">
                            <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                                <div>
                                    <p class="font-black">{{ flag.user.name }} · L{{ flag.user.level }} · {{ flag.user.account_age_days }} days old</p>
                                    <p class="mt-1 text-xs font-bold uppercase tracking-wide text-rose-600">{{ flag.type.replace(/_/g, ' ') }} · {{ flag.status }}</p>
                                    <pre class="mt-3 max-h-36 overflow-auto rounded-2xl border border-slate-200 bg-slate-50 p-3 text-xs font-semibold text-slate-800">{{ flag.context }}</pre>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" class="rounded-xl px-4 py-2 text-xs font-black" :class="shell.btnGhost" @click="flagAction(flag, 'clear')">Clear</button>
                                    <button type="button" class="rounded-xl px-4 py-2 text-xs font-black" :class="shell.btnGhost" @click="flagAction(flag, 'restrict')">Restrict</button>
                                    <button type="button" class="rounded-xl px-4 py-2 text-xs font-black" :class="shell.btnPrimary" @click="flagAction(flag, 'escalate')">Escalate</button>
                                </div>
                            </div>
                        </div>
                        <EmptyState v-if="!anomalies.data?.length" message="0 anomaly flags. No risky verification patterns are waiting for review." />
                    </div>
                </AdminPanel>
            </AdminTabPanel>

            <AdminTabPanel :current-tab="activeTab" value="overrides" id-prefix="verification-engine">
                <AdminPanel title="Per-user level override" description="Search by full name or email, review their current level, set a target level (upgrade or downgrade), and document why. Every change is written to the audit log.">
                    <form class="max-w-2xl space-y-6" @submit.prevent="submitLevelOverride">
                        <div class="relative">
                            <label class="block text-sm font-bold">
                                User
                                <input
                                    v-model="overrideForm.query"
                                    type="search"
                                    autocomplete="off"
                                    class="mt-2 w-full rounded-2xl border px-4 py-3 text-sm font-semibold"
                                    :class="shell.input"
                                    placeholder="Search by full name or email"
                                    @input="onOverrideSearchInput"
                                />
                            </label>
                            <p v-if="overrideForm.searchLoading" class="mt-2 text-xs font-semibold text-slate-500">Searching…</p>
                            <ul
                                v-else-if="overrideForm.searchResults.length"
                                class="absolute z-20 mt-1 max-h-64 w-full overflow-auto rounded-2xl border border-slate-200 bg-white py-1 shadow-xl"
                            >
                                <li v-for="user in overrideForm.searchResults" :key="user.id">
                                    <button
                                        type="button"
                                        class="flex w-full flex-col items-start px-4 py-3 text-left hover:bg-primary-50"
                                        @click="selectOverrideUser(user)"
                                    >
                                        <span class="text-sm font-black text-slate-900">{{ user.name }}</span>
                                        <span class="text-xs font-semibold text-slate-500">{{ user.email }} · {{ user.current_label }}</span>
                                    </button>
                                </li>
                            </ul>
                        </div>

                        <div v-if="overrideForm.selectedUser" class="rounded-3xl border p-5" :class="shell.card">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="text-xs font-black uppercase tracking-wide text-slate-500">Selected user</p>
                                    <p class="mt-1 text-lg font-black text-slate-900">{{ overrideForm.selectedUser.name }}</p>
                                    <p class="text-sm font-semibold text-slate-600">{{ overrideForm.selectedUser.email }}</p>
                                </div>
                                <button type="button" class="text-xs font-black uppercase text-slate-500 hover:text-rose-600" @click="clearOverrideUser">
                                    Change user
                                </button>
                            </div>

                            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                                <div class="rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3">
                                    <p class="text-[10px] font-black uppercase tracking-wide text-slate-500">Current level</p>
                                    <p class="mt-1 text-xl font-black text-slate-900">{{ overrideForm.selectedUser.current_label }}</p>
                                </div>
                                <div class="rounded-2xl border border-primary-200 bg-primary-50/80 px-4 py-3">
                                    <p class="text-[10px] font-black uppercase tracking-wide text-primary-800">Target level</p>
                                    <select
                                        v-model.number="overrideForm.level"
                                        class="mt-2 w-full rounded-xl border border-primary-200 bg-white px-3 py-2.5 text-sm font-bold text-slate-900"
                                    >
                                        <option v-for="opt in overrideLevelOptions" :key="opt.value" :value="opt.value">
                                            {{ opt.label }}
                                        </option>
                                    </select>
                                    <p
                                        v-if="overrideDirection"
                                        class="mt-2 text-xs font-black uppercase tracking-wide"
                                        :class="overrideDirection === 'upgrade' ? 'text-emerald-700' : overrideDirection === 'downgrade' ? 'text-amber-800' : 'text-slate-600'"
                                    >
                                        {{ overrideDirectionLabel }}
                                    </p>
                                </div>
                            </div>

                            <label class="mt-5 block text-sm font-bold">
                                Reason (required for audit trail)
                                <textarea
                                    v-model="overrideForm.reason"
                                    rows="4"
                                    required
                                    minlength="8"
                                    class="mt-2 w-full rounded-2xl border px-4 py-3 text-sm font-semibold"
                                    :class="shell.input"
                                    placeholder="Explain why this user is being moved to the target level (compliance note, support resolution, etc.)."
                                />
                            </label>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <button
                                type="submit"
                                class="rounded-xl px-5 py-2.5 text-xs font-black uppercase"
                                :class="shell.btnPrimary"
                                :disabled="overrideForm.busy || !canSubmitOverride"
                            >
                                Apply override
                            </button>
                            <p v-if="overrideForm.message" class="text-sm font-bold" :class="overrideForm.messageType === 'error' ? 'text-rose-700' : 'text-emerald-700'">
                                {{ overrideForm.message }}
                            </p>
                        </div>
                    </form>
                </AdminPanel>
            </AdminTabPanel>

            <AdminTabPanel :current-tab="activeTab" value="audit" id-prefix="verification-engine">
                <AdminPanel title="Verification Engine Audit Log" description="Every limit change, threshold change, verification decision, override, and anomaly action.">
                    <div class="space-y-3">
                        <div v-for="log in audit.data" :key="log.id" class="rounded-3xl border p-4" :class="shell.card">
                            <div class="flex flex-col gap-2 lg:flex-row lg:items-start lg:justify-between">
                                <div>
                                    <p class="font-black">{{ log.action.replace(/\./g, ' ') }}</p>
                                    <p class="text-xs font-bold text-slate-500">{{ log.actor || 'System' }} · {{ log.affected_user || 'Platform setting' }} · {{ dateLabel(log.created_at) }}</p>
                                    <p v-if="log.reason" class="mt-2 text-sm font-semibold">{{ log.reason }}</p>
                                </div>
                                <details class="text-xs">
                                    <summary class="cursor-pointer font-black text-primary-700">Values</summary>
                                    <pre class="mt-2 max-w-xl overflow-auto rounded-2xl border border-slate-200 bg-slate-50 p-3 font-semibold text-slate-800">{{ { old: log.old_value, new: log.new_value } }}</pre>
                                </details>
                            </div>
                        </div>
                        <EmptyState v-if="!audit.data?.length" message="0 audit entries yet. Verification engine activity will appear here." />
                    </div>
                </AdminPanel>
            </AdminTabPanel>
            </AdminTabbedPage>

            <VerificationAccountTimelineSlideOver
                :open="timelineOpen"
                :user-id="timelineUserId"
                :type-key="timelineTypeKey"
                :type-label="timelineTypeLabel"
                :initial-verification-id="timelineVerificationId"
                :decision-reasons="decision_reasons"
                @close="closeAccountTimeline"
                @decided="onVerificationDecided"
            />

            <div class="fixed bottom-5 right-5 z-[100] space-y-2">
                <div v-for="toast in toasts" :key="toast.id" class="rounded-2xl border px-4 py-3 text-sm font-bold shadow-2xl" :class="toast.type === 'error' ? 'border-rose-200 bg-rose-50 text-rose-900' : 'border-emerald-200 bg-emerald-50 text-emerald-900'">
                    {{ toast.message }}
                </div>
            </div>
        </div>
    </AdminShell>
</template>

<script setup>
import VerificationAccountTimelineSlideOver from '@/Components/Admin/VerificationAccountTimelineSlideOver.vue';
import { formatFormalDateTime } from '@/utils/formatFormalDateTime';
import AdminPanel from '@/Components/Admin/AdminPanel.vue';
import AdminSlideOver from '@/Components/Admin/AdminSlideOver.vue';
import AdminTabPanel from '@/Components/Admin/AdminTabPanel.vue';
import AdminTabbedPage from '@/Components/Admin/AdminTabbedPage.vue';
import { useAdminPageTabs } from '@/composables/useAdminPageTabs';
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';
import AdminShell from '@/Layouts/AdminShell.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, defineComponent, h, reactive, ref } from 'vue';

const props = defineProps({
    section: { type: String, default: 'settings' },
    types: { type: Object, default: () => ({}) },
    levels: { type: Object, default: () => ({}) },
    client_levels: { type: Object, default: () => ({}) },
    freelancer_levels: { type: Object, default: () => ({}) },
    stage_content: { type: Object, default: () => ({}) },
    limits: { type: Object, default: () => ({}) },
    safeguards: { type: Object, default: () => ({}) },
    levelCounts: { type: Object, default: () => ({}) },
    staffAdmins: { type: Array, default: () => [] },
    pending: { type: Object, default: () => ({ data: [] }) },
    anomalies: { type: Object, default: () => ({ data: [] }) },
    audit: { type: Object, default: () => ({ data: [] }) },
    decision_reasons: { type: Array, default: () => [] },
});

const { shell } = useInjectedAdminTheme();
const tabs = [
    { key: 'settings', label: 'Verification Settings' },
    { key: 'limits', label: 'Limits' },
    { key: 'safeguards', label: 'Safeguards' },
    { key: 'queue', label: 'Review Queue' },
    { key: 'anomalies', label: 'Anomaly Flags' },
    { key: 'overrides', label: 'User overrides' },
    { key: 'audit', label: 'Audit Log' },
];
const { activeTab } = useAdminPageTabs(props.section || 'settings', {
    validTabs: tabs.map((tab) => tab.key),
    aliases: { pending: 'queue' },
    syncProp: () => (props.section === 'pending' ? 'queue' : props.section),
});
const editingSetting = ref('');
const editingLimits = ref('');
const editingSafeguard = ref('');
const timelineOpen = ref(false);
const timelineUserId = ref(null);
const timelineTypeKey = ref('');
const timelineTypeLabel = ref('');
const timelineVerificationId = ref(null);
const queueSearch = ref('');
let queueSearchTimer = null;
const reviewBusy = ref(false);
const toasts = ref([]);
const reviewForm = reactive({
    status: 'verified',
    reason: '',
    concern: '',
    referred_to_admin_id: '',
});
function normalizeStageContent(source = {}) {
    const roles = ['client', 'freelancer'];
    const normalized = {};
    for (const role of roles) {
        normalized[role] = {};
        for (const level of [1, 2, 3, 4, 5]) {
            normalized[role][level] = {
                title: source[role]?.[level]?.title || '',
                message: source[role]?.[level]?.message || '',
                info_bar: source[role]?.[level]?.info_bar || '',
            };
        }
    }

    return normalized;
}

const typesForm = useForm({
    types: JSON.parse(JSON.stringify(props.types)),
    client_levels: JSON.parse(JSON.stringify(Object.keys(props.client_levels || {}).length ? props.client_levels : props.levels)),
    freelancer_levels: JSON.parse(JSON.stringify(props.freelancer_levels)),
    stage_content: normalizeStageContent(props.stage_content),
});
const limitsForm = useForm({
    client_posting_minor: normalizeLevelMap(props.limits.client_posting_minor),
    freelancer_proposal_minor: normalizeLevelMap(props.limits.freelancer_proposal_minor),
});
const safeguardForm = useForm({ ...props.safeguards });
const requirementOptions = [
    { key: 'email', label: 'Email verified (L1)', hint: 'User confirmed their email address.' },
    { key: 'identity_address', label: 'Identity & address (L2)', hint: 'Photo ID and proof of address approved.' },
    { key: 'nin', label: 'NIN approved (L3)', hint: 'National Identification Number verified.' },
    { key: 'bvn', label: 'BVN approved (L4)', hint: 'Bank Verification Number verified.' },
];
const clientRequirementState = reactive(Object.fromEntries([0, 1, 2, 3, 4, 5].map((level) => [level, requirementsToState((props.client_levels[level] || props.levels[level])?.requirements || [])])));
const freelancerRequirementState = reactive(Object.fromEntries([0, 1, 2, 3, 4, 5].map((level) => [level, requirementsToState(props.freelancer_levels[level]?.requirements || [])])));
const freelancerRequirementOptions = [
    ...requirementOptions,
    { key: 'live_presence', label: 'Selfie + ID approved', hint: 'Live presence check for L5 high-value unlock.' },
];
const overrideForm = reactive({
    query: '',
    selectedUser: null,
    level: 0,
    reason: '',
    busy: false,
    message: '',
    messageType: 'success',
    searchResults: [],
    searchLoading: false,
});
let overrideSearchTimer = null;

const overrideLevelOptions = computed(() => {
    const user = overrideForm.selectedUser;
    if (!user) {
        return [0, 1, 2, 3, 4, 5].map((level) => ({ value: level, label: `L${level}` }));
    }
    const isFreelancer = ['freelancer', 'seller', 'provider'].includes(user.role);
    const levels = isFreelancer ? props.freelancer_levels : props.client_levels;

    return [0, 1, 2, 3, 4, 5].map((level) => ({
        value: level,
        label: levels[level]?.label || `L${level}`,
    }));
});

const overrideDirection = computed(() => {
    if (!overrideForm.selectedUser || overrideForm.level === null) {
        return null;
    }
    const current = overrideForm.selectedUser.current_level;
    if (overrideForm.level > current) {
        return 'upgrade';
    }
    if (overrideForm.level < current) {
        return 'downgrade';
    }

    return 'same';
});

const overrideDirectionLabel = computed(() => {
    if (overrideDirection.value === 'upgrade') {
        return 'Upgrade';
    }
    if (overrideDirection.value === 'downgrade') {
        return 'Downgrade';
    }
    if (overrideDirection.value === 'same') {
        return 'No level change';
    }

    return '';
});

const canSubmitOverride = computed(() => Boolean(
    overrideForm.selectedUser
    && overrideForm.reason.trim().length >= 8
    && overrideForm.level !== null
    && overrideDirection.value !== 'same',
));

function onOverrideSearchInput() {
    overrideForm.selectedUser = null;
    window.clearTimeout(overrideSearchTimer);
    const q = overrideForm.query.trim();
    if (q.length < 2) {
        overrideForm.searchResults = [];
        return;
    }
    overrideSearchTimer = window.setTimeout(async () => {
        overrideForm.searchLoading = true;
        try {
            const { data } = await window.axios.get(route('admin.verification-engine.users.search'), { params: { q } });
            overrideForm.searchResults = data.users || [];
        } catch {
            overrideForm.searchResults = [];
        } finally {
            overrideForm.searchLoading = false;
        }
    }, 280);
}

function selectOverrideUser(user) {
    overrideForm.selectedUser = user;
    overrideForm.level = user.current_level;
    overrideForm.query = user.name;
    overrideForm.searchResults = [];
}

function clearOverrideUser() {
    overrideForm.selectedUser = null;
    overrideForm.query = '';
    overrideForm.level = 0;
    overrideForm.reason = '';
    overrideForm.searchResults = [];
}

const moneySafeguardKeys = new Set([
    'escrow_enforcement_threshold_minor',
    'milestone_enforcement_threshold_minor',
    'high_value_arbitration_threshold_minor',
    'anomaly_high_value_minor',
    'rapid_completion_high_value_minor',
]);
const safeguardFields = [
    ['escrow_enforcement_threshold_minor', 'Escrow threshold', 'Quest value above this amount requires escrow funding before work starts.'],
    ['milestone_enforcement_threshold_minor', 'Milestone threshold', 'Quest value above this amount requires milestones.'],
    ['minimum_milestone_count', 'Minimum milestones', 'Required count when milestone rule applies.'],
    ['quest_repost_limit', 'Quest repost limit', 'Duplicate quest count before flagging.'],
    ['high_value_arbitration_threshold_minor', 'Arbitration threshold', 'Quest value requiring both-party arbitration consent.'],
    ['anomaly_new_account_days', 'New account anomaly days', 'Age window for new-account risk rules.'],
    ['anomaly_near_ceiling_percent', 'Near ceiling percent', 'Percent of level limit considered near ceiling.'],
    ['anomaly_verification_window_hours', 'Verification burst window', 'Hours for rapid verification completion.'],
    ['anomaly_high_value_minor', 'Anomaly high value', 'Quest/proposal value treated as high value.'],
    ['anomaly_proposal_burst_count', 'Proposal burst count', 'Number of high-value proposals.'],
    ['anomaly_proposal_burst_minutes', 'Proposal burst window', 'Minutes for proposal burst detection.'],
    ['rapid_completion_high_value_minor', 'Rapid completion value', 'Quest value used for rapid completion payout risk.'],
].map(([key, label, hint]) => ({ key, label, hint, money: moneySafeguardKeys.has(key) }));
const reviewStatusOptions = [
    { value: 'verified', label: 'Verified', hint: 'Document is valid and should count toward the user level.' },
    { value: 'unverified', label: 'Unverified', hint: 'Document is not acceptable yet but can be regularised.' },
    { value: 'flagged', label: 'Flagged', hint: 'Document needs concern handling or staff follow-up.' },
];

const LimitRow = defineComponent({
    props: { modelValue: [Number, String], level: Number, editing: Boolean },
    emits: ['update:modelValue'],
    setup(rowProps, { emit }) {
        return () => h('div', { class: 'mt-3 grid grid-cols-[5rem_1fr] items-center gap-3 text-sm font-bold' }, [
            h('span', `L${rowProps.level}`),
            rowProps.editing
                ? h('input', {
                    value: (Number(rowProps.modelValue || 0) / 100).toFixed(2),
                    type: 'number',
                    min: 0,
                    step: '0.01',
                    class: 'rounded-2xl border px-4 py-3 text-sm font-semibold',
                    onInput: (event) => emit('update:modelValue', Math.round(Number(event.target.value || 0) * 100)),
                })
                : h('span', { class: 'rounded-2xl bg-slate-50 px-4 py-3 text-sm font-black text-slate-900 dark:bg-white/10 dark:text-white' }, formatMoney(rowProps.modelValue)),
        ]);
    },
});

const EmptyState = defineComponent({
    props: { message: String },
    setup(emptyProps) {
        return () => h('div', { class: 'rounded-3xl border border-dashed p-6 text-sm font-black text-slate-500' }, emptyProps.message);
    },
});

function normalizeLevelMap(value = {}) {
    return Object.fromEntries([0, 1, 2, 3, 4, 5].map((level) => [level, Number(value[level] || 0)]));
}

function saveTypes() {
    for (const level of [0, 1, 2, 3, 4, 5]) {
        typesForm.client_levels[level] = { ...(typesForm.client_levels[level] || {}), requirements: stateToRequirements(clientRequirementState[level]) };
        typesForm.freelancer_levels[level] = { ...(typesForm.freelancer_levels[level] || {}), requirements: stateToRequirements(freelancerRequirementState[level]) };
    }
    typesForm.patch(route('admin.verification-engine.types.update'), {
        preserveScroll: true,
        onSuccess: () => {
            editingSetting.value = '';
        },
    });
}

function saveLimits() {
    limitsForm.patch(route('admin.verification-engine.limits.update'), {
        preserveScroll: true,
        onSuccess: () => {
            editingLimits.value = '';
        },
    });
}

function saveSafeguards() {
    safeguardForm.patch(route('admin.verification-engine.safeguards.update'), {
        preserveScroll: true,
        onSuccess: () => {
            editingSafeguard.value = '';
        },
    });
}

function toggleSettingEditor(key) {
    editingSetting.value = editingSetting.value === key ? '' : key;
}

function requirementsToState(requirements = []) {
    const state = { checks: [], businessEither: false, accountAgeDays: 0 };
    for (const requirement of requirements) {
        if (typeof requirement === 'string') {
            state.checks.push(requirement);
        } else if (requirement?.any_of) {
            state.businessEither = requirement.any_of.includes('cac') && requirement.any_of.includes('tin');
        } else if (requirement?.account_age_days) {
            state.accountAgeDays = Number(requirement.account_age_days || 0);
        }
    }

    return state;
}

function stateToRequirements(state) {
    const requirements = [...new Set(state.checks || [])];
    const filtered = state.businessEither ? requirements.filter((item) => !['cac', 'tin'].includes(item)) : requirements;
    if (state.businessEither) {
        filtered.push({ any_of: ['cac', 'tin'] });
    }
    if (Number(state.accountAgeDays || 0) > 0) {
        filtered.push({ account_age_days: Number(state.accountAgeDays) });
    }

    return filtered;
}

function requirementSummary(state = { checks: [], businessEither: false, accountAgeDays: 0 }, options = requirementOptions) {
    const labels = (state.checks || [])
        .filter((item) => !(state.businessEither && ['cac', 'tin'].includes(item)))
        .map((item) => options.find((option) => option.key === item)?.label || item.replace(/_/g, ' '));
    if (state.businessEither) {
        labels.push('CAC or TIN accepted');
    }
    if (Number(state.accountAgeDays || 0) > 0) {
        labels.push(`${state.accountAgeDays} days account age`);
    }

    return labels.length ? labels : ['No checks required'];
}

function moneyInputValue(key) {
    return (Number(safeguardForm[key] || 0) / 100).toFixed(2);
}

function updateMoneySafeguard(key, value) {
    const normalized = String(value || '').replace(/,/g, '').trim();
    const naira = Number(normalized);
    safeguardForm[key] = Number.isFinite(naira) ? Math.max(0, Math.round(naira * 100)) : 0;
}

function formatMoney(minor) {
    return new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(minor || 0) / 100);
}

function openAccountTimeline(item) {
    timelineUserId.value = item.user?.id ?? item.user_id ?? null;
    timelineTypeKey.value = item.type_key ?? item.type ?? '';
    timelineTypeLabel.value = item.type_label ?? '';
    timelineVerificationId.value = item.id ?? null;
    timelineOpen.value = true;
}

function closeAccountTimeline() {
    timelineOpen.value = false;
    timelineUserId.value = null;
    timelineTypeKey.value = '';
    timelineTypeLabel.value = '';
    timelineVerificationId.value = null;
}

function debouncedQueueReload() {
    clearTimeout(queueSearchTimer);
    queueSearchTimer = setTimeout(reloadQueue, 350);
}

function reloadQueue() {
    router.get(
        route('admin.verification-engine.index'),
        {
            tab: 'queue',
            q: queueSearch.value || undefined,
        },
        { only: ['pending'], preserveScroll: true, replace: true },
    );
}

function onVerificationDecided(data) {
    toast(data?.message || 'Verification decision saved.');
    closeAccountTimeline();
    router.reload({ only: ['pending', 'levelCounts', 'audit'], preserveScroll: true });
}

async function flagAction(flag, action) {
    const reason = window.prompt(`Reason to ${action} this flag`);
    if (!reason) return;
    await window.axios.post(route('admin.verification-engine.anomalies.action', flag.id), { action, reason });
    router.reload({ only: ['anomalies', 'audit'] });
}

async function submitLevelOverride() {
    if (!canSubmitOverride.value || !overrideForm.selectedUser) {
        return;
    }
    overrideForm.busy = true;
    overrideForm.message = '';
    try {
        const { data } = await window.axios.post(
            route('admin.verification-engine.users.level-override', overrideForm.selectedUser.id),
            {
                level: overrideForm.level,
                reason: overrideForm.reason,
            },
        );
        overrideForm.message = data.message || 'Verification level override applied and logged.';
        overrideForm.messageType = 'success';
        if (data.user) {
            overrideForm.selectedUser.current_level = data.user.current_level;
            overrideForm.selectedUser.current_label = data.user.current_label;
            overrideForm.level = data.user.current_level;
        }
        overrideForm.reason = '';
        router.reload({ only: ['audit', 'levelCounts'], preserveScroll: true });
    } catch (error) {
        overrideForm.message = error?.response?.data?.message || 'Could not apply override. Please try again.';
        overrideForm.messageType = 'error';
    } finally {
        overrideForm.busy = false;
    }
}

function documentList(item) {
    if (Array.isArray(item.documents)) return item.documents;
    if (item.documents && typeof item.documents === 'object') return Object.values(item.documents).flat();

    return [];
}

function labelize(value) {
    return String(value || '—').replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());
}

function statusPill(status) {
    return {
        verified: 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-200',
        approved: 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-200',
        pending: 'bg-amber-100 text-amber-900 ring-1 ring-amber-200',
        in_review: 'bg-sky-100 text-sky-800 ring-1 ring-sky-200',
        unverified: 'bg-slate-100 text-slate-800 ring-1 ring-slate-200',
        rejected: 'bg-rose-100 text-rose-800 ring-1 ring-rose-200',
        flagged: 'bg-orange-100 text-orange-900 ring-1 ring-orange-200',
    }[status] || 'bg-slate-100 text-slate-800 ring-1 ring-slate-200';
}

let pageToastTimer = null;

function toast(message, type = 'success') {
    if (!message) {
        return;
    }

    const id = Date.now();
    toasts.value = [{ id, message, type }];
    window.clearTimeout(pageToastTimer);
    pageToastTimer = window.setTimeout(() => {
        toasts.value = [];
    }, 8000);
}

function dateLabel(value) {
    return formatFormalDateTime(value);
}
</script>
