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

            <AdminTabs v-model="activeTab" :tabs="tabs" id-prefix="verification-engine" aria-label="Verification engine sections" />

            <AdminTabPanel v-model="activeTab" value="settings" id-prefix="verification-engine">
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

                        <div class="grid gap-3 xl:grid-cols-3">
                            <div v-for="level in [0, 1, 2, 3, 4, 5]" :key="level" class="rounded-3xl border p-4" :class="shell.card">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-black">L{{ level }} requirements</p>
                                        <p class="mt-1 text-xs font-bold" :class="shell.cardMuted">{{ levels[level]?.label || `Level ${level}` }}</p>
                                    </div>
                                    <button type="button" class="rounded-xl px-3 py-1.5 text-[11px] font-black uppercase tracking-wide" :class="shell.btnGhost" @click="toggleSettingEditor(`level:${level}`)">
                                        {{ editingSetting === `level:${level}` ? 'Close' : 'Edit' }}
                                    </button>
                                </div>
                                <div v-if="editingSetting === `level:${level}`" class="mt-4 space-y-2">
                                    <label v-for="option in requirementOptions" :key="`${level}-${option.key}`" class="flex items-start gap-2 rounded-2xl border p-3 text-sm font-bold" :class="shell.card">
                                        <input v-model="requirementState[level].checks" :value="option.key" type="checkbox" class="mt-1 rounded border-slate-300 text-primary-600" />
                                        <span>
                                            <span class="block">{{ option.label }}</span>
                                            <span class="block text-[11px] font-semibold" :class="shell.cardMuted">{{ option.hint }}</span>
                                        </span>
                                    </label>
                                    <label class="flex items-start gap-2 rounded-2xl border p-3 text-sm font-bold" :class="shell.card">
                                        <input v-model="requirementState[level].businessEither" type="checkbox" class="mt-1 rounded border-slate-300 text-primary-600" />
                                        <span>
                                            <span class="block">CAC or TIN accepted</span>
                                            <span class="block text-[11px] font-semibold" :class="shell.cardMuted">Either business document can satisfy this level.</span>
                                        </span>
                                    </label>
                                    <label class="block rounded-2xl border p-3 text-sm font-bold" :class="shell.card">
                                        <span>Minimum account age in days</span>
                                        <input v-model.number="requirementState[level].accountAgeDays" type="number" min="0" class="mt-2 w-full rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                                        <span class="mt-1 block text-[11px] font-semibold" :class="shell.cardMuted">Use 0 when account age is not required.</span>
                                    </label>
                                    <button type="submit" class="rounded-xl px-4 py-2 text-xs font-black uppercase" :class="shell.btnPrimary" :disabled="typesForm.processing">
                                        Save Level
                                    </button>
                                </div>
                                <div v-else class="mt-4 flex flex-wrap gap-2">
                                    <span v-for="item in requirementSummary(level)" :key="item" class="rounded-full bg-primary-50 px-3 py-1 text-xs font-black text-primary-800 dark:bg-primary-400/15 dark:text-primary-100">
                                        {{ item }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </form>
                </AdminPanel>
            </AdminTabPanel>

            <AdminTabPanel v-model="activeTab" value="limits" id-prefix="verification-engine">
                <AdminPanel title="Limit Configuration" description="NGN posting and proposal ceilings by effective verification level. Stored in the database and read at runtime.">
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

            <AdminTabPanel v-model="activeTab" value="safeguards" id-prefix="verification-engine">
                <AdminPanel title="Safeguard Configuration" description="Escrow, milestones, cooldowns, reposting, arbitration, and anomaly detection thresholds.">
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

            <AdminTabPanel v-model="activeTab" value="queue" id-prefix="verification-engine">
                <AdminPanel title="Document Review Desk" description="Review BVN, NIN, utility, identity, and credential submissions with reasons, concerns, referrals, and audit trails.">
                    <div class="grid gap-3">
                        <div v-for="item in pending.data" :key="item.id" class="rounded-3xl border p-4" :class="shell.card">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="text-sm font-black">{{ item.user.name }} · L{{ item.user.level }}</p>
                                        <span class="rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-wide" :class="statusPill(item.status)">
                                            {{ labelize(item.status) }}
                                        </span>
                                    </div>
                                    <p class="mt-1 text-xs font-bold text-slate-500">{{ item.user.email }} · {{ labelize(item.type) }} · submitted {{ dateLabel(item.submitted_at) }}</p>
                                    <p v-if="item.concern || item.reason" class="mt-3 rounded-2xl border border-amber-100 bg-amber-50 p-3 text-sm font-bold text-amber-950">
                                        {{ item.concern || item.reason }}
                                    </p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" class="rounded-xl px-4 py-2 text-xs font-black" :class="shell.btnGhost" @click="openVerification(item)">Open details</button>
                                    <button type="button" class="rounded-xl px-4 py-2 text-xs font-black" :class="shell.btnPrimary" @click="openVerification(item, 'verified')">Mark verified</button>
                                </div>
                            </div>
                        </div>
                        <EmptyState v-if="!pending.data?.length" message="0 documents need attention. The review desk is clear." />
                    </div>
                </AdminPanel>
            </AdminTabPanel>

            <AdminTabPanel v-model="activeTab" value="anomalies" id-prefix="verification-engine">
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

            <AdminTabPanel v-model="activeTab" value="audit" id-prefix="verification-engine">
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

            <AdminSlideOver
                :open="!!selectedVerification"
                :title="selectedVerification ? `${selectedVerification.user.name} · ${labelize(selectedVerification.type)}` : 'Verification review'"
                eyebrow="Document Verification"
                width-class="max-w-full sm:max-w-2xl xl:max-w-3xl"
                panel-class="bg-white text-slate-950"
                @close="closeVerification"
            >
                <div v-if="selectedVerification" class="space-y-5">
                    <section class="rounded-3xl border border-primary-100 bg-primary-50/70 p-5">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="text-sm font-black text-slate-950">{{ selectedVerification.user.email }}</p>
                                <p class="mt-1 text-xs font-bold text-slate-600">
                                    Submitted {{ dateLabel(selectedVerification.submitted_at) }} · Account age {{ selectedVerification.user.account_age_days }} days
                                </p>
                            </div>
                            <span class="self-start rounded-full px-3 py-1 text-xs font-black uppercase tracking-wide" :class="statusPill(selectedVerification.status)">
                                {{ labelize(selectedVerification.status) }}
                            </span>
                        </div>
                    </section>

                    <section class="grid gap-3 md:grid-cols-2">
                        <div class="rounded-3xl border border-slate-100 bg-slate-50 p-4">
                            <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Submitted data</p>
                            <dl class="mt-3 space-y-2 text-sm">
                                <div v-for="(value, key) in selectedVerification.metadata" :key="key" class="rounded-2xl bg-white p-3">
                                    <dt class="text-[10px] font-black uppercase tracking-wide text-slate-500">{{ labelize(key) }}</dt>
                                    <dd class="mt-1 break-words font-bold text-slate-900">{{ value || '—' }}</dd>
                                </div>
                            </dl>
                        </div>
                        <div class="rounded-3xl border border-slate-100 bg-slate-50 p-4">
                            <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Documents</p>
                            <div v-if="documentList(selectedVerification).length" class="mt-3 space-y-2">
                                <a
                                    v-for="(doc, index) in documentList(selectedVerification)"
                                    :key="`${doc}-${index}`"
                                    :href="doc"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="block rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-black text-primary-800 hover:bg-primary-50"
                                >
                                    Open document {{ index + 1 }}
                                </a>
                            </div>
                            <p v-else class="mt-3 rounded-2xl border border-dashed border-slate-200 bg-white p-4 text-sm font-bold text-slate-500">No document file was attached.</p>
                        </div>
                    </section>

                    <form class="space-y-4 rounded-3xl border border-slate-100 bg-white p-5 shadow-sm" @submit.prevent="submitVerificationDecision">
                        <div class="grid gap-3 sm:grid-cols-3">
                            <label v-for="option in reviewStatusOptions" :key="option.value" class="cursor-pointer rounded-2xl border p-4" :class="reviewForm.status === option.value ? 'border-primary-300 bg-primary-50 ring-2 ring-primary-100' : 'border-slate-200 bg-white'">
                                <input v-model="reviewForm.status" :value="option.value" type="radio" class="sr-only" />
                                <span class="block text-sm font-black text-slate-950">{{ option.label }}</span>
                                <span class="mt-1 block text-xs font-semibold text-slate-600">{{ option.hint }}</span>
                            </label>
                        </div>
                        <label class="block">
                            <span class="text-xs font-black uppercase tracking-wide text-slate-500">Decision reason</span>
                            <textarea v-model="reviewForm.reason" rows="3" class="mt-1 w-full rounded-2xl border-slate-200 text-sm font-semibold shadow-sm focus:border-primary-500 focus:ring-primary-500" placeholder="Required when marking unverified or flagged." />
                        </label>
                        <label class="block">
                            <span class="text-xs font-black uppercase tracking-wide text-slate-500">Concern to raise</span>
                            <textarea v-model="reviewForm.concern" rows="3" class="mt-1 w-full rounded-2xl border-slate-200 text-sm font-semibold shadow-sm focus:border-primary-500 focus:ring-primary-500" placeholder="Explain what staff should regularise with the user." />
                        </label>
                        <label class="block">
                            <span class="text-xs font-black uppercase tracking-wide text-slate-500">Refer to staff admin</span>
                            <select v-model="reviewForm.referred_to_admin_id" class="mt-1 w-full rounded-2xl border-slate-200 text-sm font-bold shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                <option value="">No referral</option>
                                <option v-for="admin in staffAdmins" :key="admin.id" :value="admin.id">{{ admin.name }} · {{ admin.email }}</option>
                            </select>
                        </label>
                        <div class="flex flex-wrap justify-end gap-2">
                            <button type="button" class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-black text-slate-800 hover:bg-slate-50" @click="closeVerification">Cancel</button>
                            <button type="submit" class="rounded-xl bg-primary-700 px-5 py-2.5 text-sm font-black text-white shadow-md hover:bg-primary-800 disabled:opacity-50" :disabled="reviewBusy">
                                {{ reviewBusy ? 'Saving review...' : 'Save document review' }}
                            </button>
                        </div>
                    </form>
                </div>
            </AdminSlideOver>

            <div class="fixed bottom-5 right-5 z-[100] space-y-2">
                <div v-for="toast in toasts" :key="toast.id" class="rounded-2xl border px-4 py-3 text-sm font-bold shadow-2xl" :class="toast.type === 'error' ? 'border-rose-200 bg-rose-50 text-rose-900' : 'border-emerald-200 bg-emerald-50 text-emerald-900'">
                    {{ toast.message }}
                </div>
            </div>
        </div>
    </AdminShell>
</template>

<script setup>
import AdminPanel from '@/Components/Admin/AdminPanel.vue';
import AdminSlideOver from '@/Components/Admin/AdminSlideOver.vue';
import AdminTabPanel from '@/Components/Admin/AdminTabPanel.vue';
import AdminTabs from '@/Components/Admin/AdminTabs.vue';
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';
import { useTabState } from '@/composables/useTabState';
import AdminShell from '@/Layouts/AdminShell.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { defineComponent, h, reactive, ref } from 'vue';

const props = defineProps({
    section: { type: String, default: 'settings' },
    types: { type: Object, default: () => ({}) },
    levels: { type: Object, default: () => ({}) },
    limits: { type: Object, default: () => ({}) },
    safeguards: { type: Object, default: () => ({}) },
    levelCounts: { type: Object, default: () => ({}) },
    staffAdmins: { type: Array, default: () => [] },
    pending: { type: Object, default: () => ({ data: [] }) },
    anomalies: { type: Object, default: () => ({ data: [] }) },
    audit: { type: Object, default: () => ({ data: [] }) },
});

const { shell } = useInjectedAdminTheme();
const tabs = [
    { key: 'settings', label: 'Verification Settings' },
    { key: 'limits', label: 'Limits' },
    { key: 'safeguards', label: 'Safeguards' },
    { key: 'queue', label: 'Review Queue' },
    { key: 'anomalies', label: 'Anomaly Flags' },
    { key: 'audit', label: 'Audit Log' },
];
const { activeTab } = useTabState(tabs.map((tab) => tab.key), props.section || 'settings');
const editingSetting = ref('');
const editingLimits = ref('');
const editingSafeguard = ref('');
const selectedVerification = ref(null);
const reviewBusy = ref(false);
const toasts = ref([]);
const reviewForm = reactive({
    status: 'verified',
    reason: '',
    concern: '',
    referred_to_admin_id: '',
});
const typesForm = useForm({ types: JSON.parse(JSON.stringify(props.types)), levels: JSON.parse(JSON.stringify(props.levels)) });
const limitsForm = useForm({
    client_posting_minor: normalizeLevelMap(props.limits.client_posting_minor),
    freelancer_proposal_minor: normalizeLevelMap(props.limits.freelancer_proposal_minor),
});
const safeguardForm = useForm({ ...props.safeguards });
const requirementOptions = [
    { key: 'email', label: 'Email verified', hint: 'User clicked the email confirmation link.' },
    { key: 'nin', label: 'NIN approved', hint: 'Admin has approved the submitted NIN.' },
    { key: 'identity_address', label: 'Identity and address approved', hint: 'Government photo ID plus proof of address.' },
    { key: 'bvn', label: 'BVN approved', hint: 'Bank verification number has been reviewed.' },
    { key: 'cac', label: 'CAC approved', hint: 'Registered company documentation.' },
    { key: 'tin', label: 'TIN approved', hint: 'Tax identification number documentation.' },
    { key: 'professional_certificate', label: 'Professional certificate approved', hint: 'Freelancer professional body or membership proof.' },
    { key: 'portfolio_review', label: 'Portfolio reviewed', hint: 'Soft verification for freelancer portfolio authenticity.' },
];
const requirementState = reactive(Object.fromEntries([0, 1, 2, 3, 4, 5].map((level) => [level, requirementsToState(props.levels[level]?.requirements || [])])));
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
    ['new_account_cooldown_days', 'New account cooldown', 'Days to cap earned level by one.'],
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
        typesForm.levels[level] = { ...(typesForm.levels[level] || {}), requirements: stateToRequirements(requirementState[level]) };
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

function requirementSummary(level) {
    const state = requirementState[level] || { checks: [], businessEither: false, accountAgeDays: 0 };
    const labels = (state.checks || [])
        .filter((item) => !(state.businessEither && ['cac', 'tin'].includes(item)))
        .map((item) => requirementOptions.find((option) => option.key === item)?.label || item.replace(/_/g, ' '));
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

function openVerification(item, status = null) {
    selectedVerification.value = item;
    reviewForm.status = status || (['verified', 'unverified', 'flagged'].includes(item.status) ? item.status : 'verified');
    reviewForm.reason = item.reason || '';
    reviewForm.concern = item.concern || '';
    reviewForm.referred_to_admin_id = item.referred_to_admin?.id || '';
}

function closeVerification() {
    selectedVerification.value = null;
    reviewBusy.value = false;
}

async function submitVerificationDecision() {
    if (!selectedVerification.value) return;
    reviewBusy.value = true;
    try {
        const { data } = await window.axios.post(route('admin.verification-engine.verifications.decision', selectedVerification.value.id), {
            status: reviewForm.status,
            reason: reviewForm.reason,
            concern: reviewForm.concern,
            referred_to_admin_id: reviewForm.referred_to_admin_id || null,
        });
        toast(data.message || 'Verification document review saved.');
        selectedVerification.value = null;
        router.reload({ only: ['pending', 'levelCounts', 'audit'], preserveScroll: true });
    } catch (error) {
        toast(error.response?.data?.message || Object.values(error.response?.data?.errors || {})?.flat?.()?.[0] || 'Could not save verification review.', 'error');
    } finally {
        reviewBusy.value = false;
    }
}

async function flagAction(flag, action) {
    const reason = window.prompt(`Reason to ${action} this flag`);
    if (!reason) return;
    await window.axios.post(route('admin.verification-engine.anomalies.action', flag.id), { action, reason });
    router.reload({ only: ['anomalies', 'audit'] });
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

function toast(message, type = 'success') {
    const id = Date.now() + Math.random();
    toasts.value.push({ id, message, type });
    window.setTimeout(() => {
        toasts.value = toasts.value.filter((item) => item.id !== id);
    }, 4200);
}

function dateLabel(value) {
    if (!value) return '—';
    return new Intl.DateTimeFormat('en-NG', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}
</script>
