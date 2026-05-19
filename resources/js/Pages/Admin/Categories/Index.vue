<template>
    <AdminShell title="Category Management" subtitle="Curate the two-level marketplace taxonomy that powers quests, freelancer skills, search, and analytics.">
        <div class="space-y-5">
            <section class="grid gap-3 md:grid-cols-4">
                <div v-for="tile in summaryTiles" :key="tile.label" class="rounded-3xl border p-4" :class="shell.card">
                    <p class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">{{ tile.label }}</p>
                    <p class="mt-2 text-2xl font-black" :class="shell.title">{{ tile.value }}</p>
                    <p v-if="tile.note" class="mt-1 text-xs font-semibold" :class="shell.cardMuted">{{ tile.note }}</p>
                </div>
            </section>

            <section class="rounded-[2rem] border p-4 shadow-sm" :class="shell.card">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div class="min-w-0 flex-1">
                        <label class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">Search hierarchy</label>
                        <input v-model="search" type="search" placeholder="Search categories and subcategories" class="mt-2 w-full rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="rounded-2xl px-4 py-3 text-xs font-black uppercase" :class="shell.btnGhost" @click="setAllExpanded(false)">Collapse all</button>
                        <button type="button" class="rounded-2xl px-4 py-3 text-xs font-black uppercase" :class="shell.btnGhost" @click="setAllExpanded(true)">Expand all</button>
                        <button type="button" class="rounded-2xl px-4 py-3 text-xs font-black uppercase" :class="bulkMode ? warmBtn : shell.btnGhost" @click="bulkMode = !bulkMode">Bulk edit</button>
                        <button type="button" class="rounded-2xl px-4 py-3 text-xs font-black uppercase" :class="shell.btnGhost" @click="importOpen = true">Import</button>
                        <button type="button" class="rounded-2xl px-4 py-3 text-xs font-black uppercase text-white" :class="warmBtn" @click="openCreateParent">Add category</button>
                        <button type="button" class="rounded-2xl px-4 py-3 text-xs font-black uppercase text-white" :class="warmBtn" @click="openCreateChild(null)">Add subcategory</button>
                    </div>
                </div>

                <div v-if="bulkMode" class="mt-4 rounded-3xl border border-amber-200 bg-amber-50 p-4 text-amber-950">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <p class="font-black">{{ selectedIds.length }} selected</p>
                            <p class="text-xs font-semibold">Bulk operations preview changes before applying and skip unsafe archive rows with open Quests.</p>
                        </div>
                        <div class="grid gap-2 sm:grid-cols-4">
                            <select v-model="bulkForm.action" class="rounded-2xl border-amber-200 px-3 py-2 text-xs font-black">
                                <option value="status">Change status</option>
                                <option value="parent">Change parent</option>
                                <option value="fees">Update service fee</option>
                            </select>
                            <select v-if="bulkForm.action === 'status'" v-model="bulkForm.status" class="rounded-2xl border-amber-200 px-3 py-2 text-xs font-black">
                                <option value="active">Active</option>
                                <option value="hidden">Hidden</option>
                                <option value="archived">Archived</option>
                            </select>
                            <select v-if="bulkForm.action === 'parent'" v-model="bulkForm.parent_id" class="rounded-2xl border-amber-200 px-3 py-2 text-xs font-black">
                                <option value="">New parent</option>
                                <option v-for="parent in parents" :key="parent.id" :value="parent.id">{{ parent.name }}</option>
                            </select>
                            <input v-if="bulkForm.action === 'fees'" v-model="bulkForm.client_fee_percent" type="number" step="0.01" placeholder="Client %" class="rounded-2xl border-amber-200 px-3 py-2 text-xs font-black" />
                            <input v-if="bulkForm.action === 'fees'" v-model="bulkForm.freelancer_fee_percent" type="number" step="0.01" placeholder="Freelancer %" class="rounded-2xl border-amber-200 px-3 py-2 text-xs font-black" />
                            <button type="button" class="rounded-2xl bg-amber-700 px-4 py-2 text-xs font-black uppercase text-white disabled:opacity-50" :disabled="!selectedIds.length" @click="applyBulk">Apply</button>
                        </div>
                    </div>
                </div>

                <div class="mt-5 space-y-3">
                    <article
                        v-for="parent in visibleParents"
                        :key="parent.id"
                        class="overflow-hidden rounded-3xl border transition"
                        :class="[rowDimClass(parent), shell.tableDivide]"
                        :draggable="!bulkMode"
                        @dragstart="onDragStart(parent, null)"
                        @dragover.prevent
                        @drop="onDropParent(parent)"
                    >
                        <div class="flex flex-col gap-3 p-4 lg:flex-row lg:items-center">
                            <div class="flex min-w-0 flex-1 items-center gap-3">
                                <input v-if="bulkMode" v-model="selectedIds" type="checkbox" :value="parent.id" class="rounded border-slate-300 text-amber-600" />
                                <button type="button" class="text-slate-400" title="Drag handle">⋮⋮</button>
                                <button type="button" class="rounded-full p-1 font-black" :class="shell.btnGhost" @click="toggle(parent.id)">{{ expanded[parent.id] ? '⌄' : '›' }}</button>
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl text-lg font-black text-white" :style="{ backgroundColor: parent.icon_color || '#0f766e' }">{{ iconGlyph(parent.icon_name) }}</span>
                                <div class="min-w-0">
                                    <p class="truncate text-base font-black" :class="shell.title" v-html="highlight(parent.name)"></p>
                                    <p class="truncate text-xs font-semibold" :class="shell.cardMuted">{{ parent.description || 'No description yet' }}</p>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-2 text-xs font-black sm:grid-cols-5 lg:w-[38rem]">
                                <Badge>{{ parent.subcategories_count }} subcategories</Badge>
                                <Badge>{{ parent.active_quests_count }} open quests</Badge>
                                <Badge>{{ feeLabel(parent) }}</Badge>
                                <StatusBadge :status="parent.status" />
                                <div class="flex justify-end gap-2">
                                    <button type="button" class="rounded-xl px-3 py-2" :class="shell.btnGhost" @click="openPerformance(parent)">Chart</button>
                                    <button type="button" class="rounded-xl px-3 py-2" :class="shell.btnGhost" @click="openEdit(parent)">Edit</button>
                                    <button type="button" class="rounded-xl px-3 py-2" :class="shell.btnGhost" @click="openCreateChild(parent)">Add</button>
                                </div>
                            </div>
                        </div>

                        <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100 translate-y-0">
                            <div v-if="expanded[parent.id]" class="border-t px-4 pb-4" :class="shell.tableDivide">
                                <div class="ml-0 mt-3 space-y-2 border-l-2 border-dashed border-slate-200 pl-4 dark:border-white/10 md:ml-12">
                                    <div
                                        v-for="child in visibleChildren(parent)"
                                        :key="child.id"
                                        class="rounded-2xl border p-3 transition"
                                        :class="[rowDimClass(child), child.status === 'archived' ? 'opacity-60' : '']"
                                        :draggable="!bulkMode"
                                        @dragstart="onDragStart(child, parent)"
                                        @dragover.prevent
                                        @drop="onDropChild(parent, child)"
                                    >
                                        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                            <div class="flex min-w-0 items-center gap-3">
                                                <input v-if="bulkMode" v-model="selectedIds" type="checkbox" :value="child.id" class="rounded border-slate-300 text-amber-600" />
                                                <button type="button" class="text-slate-400">⋮⋮</button>
                                                <span class="h-2 w-2 rounded-full" :style="{ backgroundColor: child.icon_color || parent.icon_color }"></span>
                                                <div class="min-w-0">
                                                    <p class="truncate text-sm font-black" :class="shell.title" v-html="highlight(child.name)"></p>
                                                    <p class="truncate text-xs font-semibold" :class="shell.cardMuted">{{ child.description || 'No description yet' }}</p>
                                                </div>
                                            </div>
                                            <div class="grid grid-cols-2 gap-2 text-xs font-black sm:grid-cols-6 lg:w-[42rem]">
                                                <Badge>{{ child.active_quests_count }} open</Badge>
                                                <Badge>{{ child.freelancers_count }} freelancers</Badge>
                                                <Badge v-if="child.uses_fee_override">Fee override</Badge>
                                                <Badge v-else>Inherits fee</Badge>
                                                <StatusBadge :status="child.status" />
                                                <button type="button" class="rounded-xl px-3 py-2" :class="shell.btnGhost" @click="openPerformance(child)">Chart</button>
                                                <div class="flex justify-end gap-2">
                                                    <button type="button" class="rounded-xl px-3 py-2" :class="shell.btnGhost" @click="moveMobile(parent, child, -1)">↑</button>
                                                    <button type="button" class="rounded-xl px-3 py-2" :class="shell.btnGhost" @click="moveMobile(parent, child, 1)">↓</button>
                                                    <button type="button" class="rounded-xl px-3 py-2" :class="shell.btnGhost" @click="openEdit(child)">Edit</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button" class="rounded-2xl border border-dashed px-4 py-3 text-sm font-black" :class="shell.btnGhost" @click="openCreateChild(parent)">+ Add subcategory under {{ parent.name }}</button>
                                </div>
                            </div>
                        </Transition>
                    </article>
                </div>
            </section>

            <details v-if="categoryManagement.archived.length" class="rounded-3xl border p-4" :class="shell.card">
                <summary class="cursor-pointer font-black" :class="shell.title">Archived items</summary>
                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <div v-for="item in categoryManagement.archived" :key="item.id" class="rounded-2xl border p-4 opacity-70" :class="shell.card">
                        <p class="font-black">{{ item.name }}</p>
                        <p class="text-xs font-semibold" :class="shell.cardMuted">{{ item.parent_name || 'Parent category' }} · {{ item.active_quests_count }} open quests</p>
                        <button type="button" class="mt-3 rounded-xl bg-emerald-100 px-3 py-2 text-xs font-black text-emerald-800" @click="restore(item)">Restore</button>
                    </div>
                </div>
            </details>
        </div>

        <AdminSlideOver :open="formOpen" :title="editing?.parent_id || form.parent_id ? 'Subcategory details' : 'Category details'" eyebrow="Category library" @close="formOpen = false">
            <form class="space-y-4" @submit.prevent="saveCategory">
                <label v-if="form.parent_id !== null || editing?.parent_id" class="block">
                    <span class="text-xs font-black uppercase tracking-wide" :class="shell.label">Parent category</span>
                    <select v-model="form.parent_id" required class="mt-2 w-full rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input">
                        <option value="">Choose parent</option>
                        <option v-for="parent in parents" :key="parent.id" :value="parent.id">{{ parent.name }}</option>
                    </select>
                </label>
                <label class="block">
                    <span class="flex justify-between text-xs font-black uppercase tracking-wide" :class="shell.label">
                        <span>Name *</span><span>{{ form.name.length }}/{{ form.parent_id ? 60 : 50 }}</span>
                    </span>
                    <input v-model="form.name" required class="mt-2 w-full rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" @input="syncSlug" />
                    <p class="mt-1 text-xs font-bold" :class="nameUnique === false ? 'text-rose-600' : 'text-emerald-600'">{{ uniqueText(nameUnique) }}</p>
                </label>
                <label class="block">
                    <span class="text-xs font-black uppercase tracking-wide" :class="shell.label">Slug *</span>
                    <input v-model="form.slug" required class="mt-2 w-full rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" @input="slugManuallyEdited = true; checkUnique('slug')" />
                    <p class="mt-1 text-xs font-bold" :class="slugUnique === false ? 'text-rose-600' : 'text-emerald-600'">{{ uniqueText(slugUnique) }}</p>
                </label>
                <label class="block">
                    <span class="flex justify-between text-xs font-black uppercase tracking-wide" :class="shell.label">
                        <span>Short description</span><span>{{ form.description.length }}/{{ form.parent_id ? 200 : 150 }}</span>
                    </span>
                    <textarea v-model="form.description" rows="3" class="mt-2 w-full rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input"></textarea>
                </label>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label>
                        <span class="text-xs font-black uppercase tracking-wide" :class="shell.label">Icon</span>
                        <select v-model="form.icon_name" class="mt-2 w-full rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input">
                            <optgroup v-for="group in categoryManagement.icons" :key="group.group" :label="group.group">
                                <option v-for="icon in group.icons" :key="icon" :value="icon">{{ icon }}</option>
                            </optgroup>
                        </select>
                    </label>
                    <label>
                        <span class="text-xs font-black uppercase tracking-wide" :class="shell.label">Accent colour</span>
                        <input v-model="form.icon_color" type="color" class="mt-2 h-12 w-full rounded-2xl border p-1" :class="shell.input" />
                    </label>
                </div>
                <div class="rounded-3xl border p-4" :class="shell.card">
                    <p class="text-xs font-black uppercase tracking-wide" :class="shell.label">Live preview</p>
                    <div class="mt-3 flex items-center gap-3">
                        <span class="flex h-12 w-12 items-center justify-center rounded-2xl text-lg font-black text-white" :style="{ backgroundColor: form.icon_color }">{{ iconGlyph(form.icon_name) }}</span>
                        <div>
                            <p class="font-black" :class="shell.title">{{ form.name || 'Category name' }}</p>
                            <p class="text-xs font-semibold" :class="shell.cardMuted">{{ form.description || 'Short helpful description' }}</p>
                        </div>
                    </div>
                </div>

                <label v-if="form.parent_id" class="flex items-center gap-3 rounded-2xl p-3" :class="shell.card">
                    <input v-model="form.uses_fee_override" type="checkbox" class="rounded border-slate-300 text-amber-600" />
                    <span class="text-sm font-bold">Use different fee from parent category</span>
                </label>
                <div v-if="!form.parent_id || form.uses_fee_override" class="grid gap-3 sm:grid-cols-2">
                    <input v-model="form.client_fee_percent" type="number" step="0.01" placeholder="Client fee %" class="rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                    <input v-model="form.freelancer_fee_percent" type="number" step="0.01" placeholder="Freelancer fee %" class="rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                </div>
                <p class="rounded-2xl bg-emerald-50 p-3 text-xs font-bold text-emerald-900">{{ feePreview }}</p>

                <label class="flex items-center gap-3 rounded-2xl p-3" :class="shell.card">
                    <input v-model="form.budget_guardrails_enabled" type="checkbox" class="rounded border-slate-300 text-amber-600" />
                    <span class="text-sm font-bold">Enable Quest budget guardrails</span>
                </label>
                <div v-if="form.budget_guardrails_enabled" class="grid gap-3 sm:grid-cols-2">
                    <input v-model="form.min_budget_minor" type="number" placeholder="Minimum budget minor" class="rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                    <input v-model="form.max_budget_minor" type="number" placeholder="Maximum budget minor" class="rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                </div>

                <label class="flex items-center gap-3 rounded-2xl p-3" :class="shell.card">
                    <input v-model="form.high_value_approval_enabled" type="checkbox" class="rounded border-slate-300 text-amber-600" />
                    <span class="text-sm font-bold">Require admin approval for high-value Quests</span>
                </label>
                <input v-if="form.high_value_approval_enabled" v-model="form.high_value_threshold_minor" type="number" placeholder="Threshold minor amount" class="w-full rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />

                <div class="grid gap-3 sm:grid-cols-2">
                    <input v-model="form.sort_order" type="number" placeholder="Display order" class="rounded-2xl border px-4 py-3 text-sm font-semibold" :class="shell.input" />
                    <select v-model="form.status" class="rounded-2xl border px-4 py-3 text-sm font-black" :class="shell.input">
                        <option value="active">Active</option>
                        <option value="hidden">Hidden</option>
                        <option value="draft">Draft</option>
                        <option value="archived">Archived</option>
                    </select>
                </div>
                <label v-if="editing && editing.impact?.active_quests + editing.impact?.freelancers > 0" class="flex items-start gap-3 rounded-2xl bg-amber-50 p-3 text-sm font-bold text-amber-950">
                    <input v-model="form.acknowledge_name_impact" type="checkbox" class="mt-1 rounded border-amber-300 text-amber-600" />
                    <span>This will update the displayed category name on {{ editing.impact.active_quests }} active Quests and {{ editing.impact.freelancers }} freelancer profiles.</span>
                </label>
                <label v-if="editing && editing.impact?.active_contracts > 0" class="flex items-start gap-3 rounded-2xl bg-amber-50 p-3 text-sm font-bold text-amber-950">
                    <input v-model="form.acknowledge_fee_impact" type="checkbox" class="mt-1 rounded border-amber-300 text-amber-600" />
                    <span>Fee changes only apply to new contracts. {{ editing.impact.active_contracts }} contracts in progress keep the previous fee structure.</span>
                </label>
                <div class="grid gap-3 sm:grid-cols-2">
                    <button type="submit" class="rounded-2xl px-5 py-3 text-sm font-black text-white disabled:opacity-50" :class="warmBtn" :disabled="form.processing">Save</button>
                    <button v-if="editing" type="button" class="rounded-2xl px-5 py-3 text-sm font-black" :class="shell.btnGhost" @click="hideOrArchive('hide')">Hide</button>
                    <button v-if="editing" type="button" class="rounded-2xl bg-slate-600 px-5 py-3 text-sm font-black text-white" @click="hideOrArchive('archive')">Archive</button>
                </div>
            </form>
        </AdminSlideOver>

        <AdminSlideOver :open="performanceOpen" title="Category performance" eyebrow="Performance" @close="performanceOpen = false">
            <div v-if="performance" class="space-y-4">
                <div class="flex gap-2">
                    <button v-for="days in [30, 90, 365]" :key="days" type="button" class="rounded-full px-3 py-1 text-xs font-black" :class="performance.range_days === days ? warmBtn : shell.btnGhost" @click="loadPerformance(performance.id, days)">Last {{ days === 365 ? '12 months' : `${days} days` }}</button>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div v-for="metric in performanceMetrics" :key="metric.label" class="rounded-3xl border p-4" :class="shell.card">
                        <p class="text-[10px] font-black uppercase tracking-wider" :class="shell.label">{{ metric.label }}</p>
                        <p class="mt-2 text-xl font-black" :class="shell.title">{{ metric.value }}</p>
                    </div>
                </div>
                <div class="rounded-3xl border p-4" :class="shell.card">
                    <p class="font-black" :class="shell.title">Volume trend</p>
                    <div class="mt-3 flex items-end gap-1">
                        <span v-for="(value, month) in performance.trend" :key="month" class="w-8 rounded-t bg-amber-500" :style="{ height: `${Math.max(8, Number(value) * 12)}px` }" :title="`${month}: ${value}`"></span>
                    </div>
                </div>
                <div v-if="performance.top_subcategories?.length" class="rounded-3xl border p-4" :class="shell.card">
                    <p class="font-black" :class="shell.title">Top subcategories</p>
                    <div v-for="item in performance.top_subcategories" :key="item.name" class="mt-3">
                        <div class="flex justify-between text-xs font-bold"><span>{{ item.name }}</span><span>{{ item.value }}</span></div>
                        <div class="mt-1 h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-amber-500" :style="{ width: `${Math.min(100, item.value * 12)}%` }"></div></div>
                    </div>
                </div>
            </div>
        </AdminSlideOver>

        <AdminSlideOver :open="importOpen" title="Import categories" eyebrow="CSV" @close="importOpen = false">
            <form class="space-y-4" @submit.prevent="previewImport(false)">
                <a :href="route('admin.categories.import.template')" class="inline-flex rounded-2xl bg-amber-100 px-4 py-2 text-xs font-black text-amber-800">Download CSV template</a>
                <input type="file" accept=".csv,text/csv" class="w-full rounded-2xl border p-3 text-sm" :class="shell.input" @change="csvFile = $event.target.files?.[0] || null" />
                <button type="submit" class="rounded-2xl px-5 py-3 text-sm font-black text-white" :class="warmBtn">Validate CSV</button>
            </form>
            <div v-if="importPreview" class="mt-5 space-y-3">
                <p class="font-black" :class="shell.title">{{ importPreview.valid.length }} valid rows · {{ importPreview.invalid.length }} rows need fixes</p>
                <div v-for="row in importPreview.invalid" :key="row.row" class="rounded-2xl bg-rose-50 p-3 text-xs font-bold text-rose-800">Row {{ row.row }}: {{ row.errors.join(', ') }}</div>
                <button v-if="importPreview.valid.length" type="button" class="rounded-2xl bg-emerald-600 px-5 py-3 text-sm font-black text-white" @click="previewImport(true)">Import valid rows</button>
            </div>
        </AdminSlideOver>

        <div v-if="undoToken" class="fixed bottom-5 left-5 z-[90] rounded-3xl bg-slate-950 px-5 py-4 text-sm font-bold text-white shadow-2xl">
            Reorder saved.
            <button type="button" class="ml-3 font-black text-amber-300 underline" @click="undoReorder">Undo</button>
        </div>
    </AdminShell>
</template>

<script setup>
import AdminPanel from '@/Components/Admin/AdminPanel.vue';
import AdminSlideOver from '@/Components/Admin/AdminSlideOver.vue';
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';
import AdminShell from '@/Layouts/AdminShell.vue';
import { router, useForm } from '@inertiajs/vue3';
import { computed, defineComponent, h, reactive, ref, watch } from 'vue';

const props = defineProps({
    categoryManagement: { type: Object, required: true },
});

const { shell } = useInjectedAdminTheme();
const warmBtn = 'bg-gradient-to-r from-primary-600 to-primary-500 text-white shadow-sm shadow-primary-500/20 hover:from-primary-500 hover:to-primary-400';
const search = ref('');
const expanded = reactive({});
const bulkMode = ref(false);
const selectedIds = ref([]);
const formOpen = ref(false);
const importOpen = ref(false);
const performanceOpen = ref(false);
const performance = ref(null);
const editing = ref(null);
const dragged = ref(null);
const undoToken = ref('');
const csvFile = ref(null);
const importPreview = ref(null);
const nameUnique = ref(null);
const slugUnique = ref(null);
const slugManuallyEdited = ref(false);

const form = useForm(defaultForm());
const bulkForm = reactive({ action: 'status', status: 'hidden', parent_id: '', client_fee_percent: '', freelancer_fee_percent: '', uses_fee_override: true });

const parents = computed(() => props.categoryManagement.tree || []);
const summaryTiles = computed(() => [
    { label: 'Total active categories', value: props.categoryManagement.summary.active_categories },
    { label: 'Total active subcategories', value: props.categoryManagement.summary.active_subcategories },
    { label: 'Open quests', value: props.categoryManagement.summary.open_quests },
    { label: 'Most active category', value: props.categoryManagement.summary.most_active_category?.name || 'None', note: `${props.categoryManagement.summary.most_active_category?.open_quests || 0} open quests` },
]);
const visibleParents = computed(() => parents.value.filter((parent) => parent.status !== 'archived'));
const feePreview = computed(() => {
    const clientFee = Number(form.client_fee_percent || props.categoryManagement.defaults.client_fee_percent || 0);
    const freelancerFee = Number(form.freelancer_fee_percent || props.categoryManagement.defaults.freelancer_fee_percent || 0);
    const clientTotal = 100000 + (100000 * clientFee / 100);
    const freelancerReceives = 100000 - (100000 * freelancerFee / 100);
    return `On a ₦100,000 Quest, the client pays ₦${clientTotal.toLocaleString()} total and the freelancer receives ₦${freelancerReceives.toLocaleString()}.`;
});
const performanceMetrics = computed(() => performance.value ? [
    { label: 'Quests posted', value: performance.value.total_quests },
    { label: 'Fill rate', value: `${performance.value.fill_rate}%` },
    { label: 'Average budget', value: performance.value.average_budget },
    { label: 'Avg proposals', value: performance.value.average_proposals },
    { label: 'Time to hire', value: `${performance.value.average_time_to_hire_hours}h` },
    { label: 'Revenue', value: performance.value.platform_revenue },
    { label: 'Dispute rate', value: `${performance.value.dispute_rate}%` },
    { label: 'Supply/demand', value: performance.value.supply_demand_label },
] : []);

const Badge = defineComponent({
    setup(_, { slots }) {
        return () => h('span', { class: 'inline-flex items-center justify-center rounded-full bg-slate-100 px-3 py-1 text-[11px] font-black text-slate-700 dark:bg-white/10 dark:text-slate-200' }, slots.default?.());
    },
});
const StatusBadge = defineComponent({
    props: { status: String },
    setup(p) {
        const klass = computed(() => ({
            active: 'bg-emerald-100 text-emerald-800',
            hidden: 'bg-amber-100 text-amber-800',
            draft: 'bg-sky-100 text-sky-800',
            archived: 'bg-slate-200 text-slate-700',
        }[p.status] || 'bg-slate-100 text-slate-700'));
        return () => h('span', { class: ['inline-flex items-center justify-center rounded-full px-3 py-1 text-[11px] font-black capitalize', klass.value] }, p.status || 'unknown');
    },
});

watch(parents, () => setAllExpanded(true), { immediate: true });
watch(() => form.name, () => checkUnique('name'));

function defaultForm() {
    return {
        parent_id: null,
        name: '',
        slug: '',
        description: '',
        icon_name: 'briefcase',
        icon_color: '#0f766e',
        status: 'active',
        sort_order: '',
        uses_fee_override: false,
        client_fee_percent: '',
        freelancer_fee_percent: '',
        budget_guardrails_enabled: false,
        min_budget_minor: '',
        max_budget_minor: '',
        high_value_approval_enabled: false,
        high_value_threshold_minor: '',
        acknowledge_name_impact: false,
        acknowledge_fee_impact: false,
    };
}

function setAllExpanded(value) {
    parents.value.forEach((parent) => { expanded[parent.id] = value; });
}
function toggle(id) {
    expanded[id] = !expanded[id];
}
function visibleChildren(parent) {
    return (parent.children || []).filter((child) => child.status !== 'archived');
}
function rowDimClass(item) {
    return search.value && !matches(item) ? 'opacity-35' : item.status === 'archived' ? 'opacity-50' : '';
}
function matches(item) {
    return !search.value || item.name.toLowerCase().includes(search.value.toLowerCase());
}
function highlight(text) {
    if (!search.value) {
        return escapeHtml(text);
    }
    const escaped = escapeHtml(text);
    const needle = search.value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    return escaped.replace(new RegExp(`(${needle})`, 'ig'), '<mark class="rounded bg-amber-200 px-1">$1</mark>');
}
function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
}
function iconGlyph(icon) {
    return { code: '</>', 'device-mobile': '📱', 'shield-lock': '🛡', database: 'DB', headset: '☎', palette: '🎨', photo: '🖼', video: '▶', brush: '🖌', writing: '✍', hammer: '🔨', tools: '🛠', building: '🏗', paint: '▣', plug: '⚡', briefcase: '💼', scale: '⚖', 'chart-bar': '▥', cash: '₦', megaphone: '📣' }[icon] || '•';
}
function feeLabel(item) {
    return `${item.client_fee_percent ?? 'Default'}% / ${item.freelancer_fee_percent ?? 'Default'}%`;
}
function uniqueText(value) {
    return value === null ? 'Checking uniqueness as you type' : value ? 'Unique' : 'Already used in this level';
}
function syncSlug() {
    if (!slugManuallyEdited.value) {
        form.slug = form.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
        checkUnique('slug');
    }
}
async function checkUnique(field) {
    const value = field === 'name' ? form.name : form.slug;
    if (!value) {
        return;
    }
    const { data } = await window.axios.get(route('admin.categories.unique'), { params: { parent_id: form.parent_id || null, field, value, ignore_id: editing.value?.id || null } });
    if (field === 'name') nameUnique.value = data.unique;
    if (field === 'slug') slugUnique.value = data.unique;
}
function openCreateParent() {
    editing.value = null;
    slugManuallyEdited.value = false;
    form.defaults(defaultForm());
    form.reset();
    form.parent_id = null;
    formOpen.value = true;
}
function openCreateChild(parent) {
    editing.value = null;
    slugManuallyEdited.value = false;
    form.defaults(defaultForm());
    form.reset();
    form.parent_id = parent?.id || '';
    formOpen.value = true;
}
function openEdit(item) {
    editing.value = item;
    slugManuallyEdited.value = true;
    Object.assign(form, { ...defaultForm(), ...item });
    formOpen.value = true;
}
function saveCategory() {
    const options = { preserveScroll: true, onSuccess: () => { formOpen.value = false; router.reload({ only: ['categoryManagement'] }); } };
    if (editing.value) {
        form.patch(route('admin.categories.update', editing.value.id), options);
    } else {
        form.post(route('admin.categories.store'), options);
    }
}
function hideOrArchive(action) {
    if (!editing.value) return;
    router.post(route(`admin.categories.${action}`, editing.value.id), {}, { preserveScroll: true, onSuccess: () => { formOpen.value = false; router.reload({ only: ['categoryManagement'] }); } });
}
function restore(item) {
    router.post(route('admin.categories.restore', item.id), {}, { preserveScroll: true, onSuccess: () => router.reload({ only: ['categoryManagement'] }) });
}
function onDragStart(item, parent) {
    dragged.value = { item, parent };
}
function onDropParent(targetParent) {
    if (!dragged.value) return;
    const item = dragged.value.item;
    if (item.parent_id) {
        if (!window.confirm(`Move ${item.name} to ${targetParent.name}? This affects ${item.impact.active_quests} open Quests and ${item.impact.freelancers} freelancer profiles.`)) return;
        postReorder([{ id: item.id, parent_id: targetParent.id, sort_order: targetParent.children.length + 10 }], true);
    } else {
        const ordered = [...parents.value];
        reorderArray(ordered, item.id, targetParent.id);
        postReorder(ordered.map((parent, index) => ({ id: parent.id, parent_id: null, sort_order: (index + 1) * 10 })));
    }
}
function onDropChild(parent, targetChild) {
    if (!dragged.value || !dragged.value.item.parent_id) return;
    const ordered = [...parent.children];
    reorderArray(ordered, dragged.value.item.id, targetChild.id);
    postReorder(ordered.map((child, index) => ({ id: child.id, parent_id: parent.id, sort_order: (index + 1) * 10 })), dragged.value.item.parent_id !== parent.id);
}
function moveMobile(parent, child, direction) {
    const ordered = [...parent.children];
    const index = ordered.findIndex((item) => item.id === child.id);
    const next = index + direction;
    if (next < 0 || next >= ordered.length) return;
    [ordered[index], ordered[next]] = [ordered[next], ordered[index]];
    postReorder(ordered.map((item, i) => ({ id: item.id, parent_id: parent.id, sort_order: (i + 1) * 10 })));
}
function reorderArray(items, movingId, targetId) {
    const from = items.findIndex((item) => item.id === movingId);
    const to = items.findIndex((item) => item.id === targetId);
    if (from < 0 || to < 0) return;
    const [item] = items.splice(from, 1);
    items.splice(to, 0, item);
}
async function postReorder(items, confirmMove = false) {
    const { data } = await window.axios.post(route('admin.categories.reorder'), { items, confirm_move: confirmMove });
    undoToken.value = data.undo_token;
    window.setTimeout(() => { undoToken.value = ''; }, 10000);
    router.reload({ only: ['categoryManagement'], preserveScroll: true });
}
async function undoReorder() {
    await window.axios.post(route('admin.categories.reorder.undo'), { token: undoToken.value });
    undoToken.value = '';
    router.reload({ only: ['categoryManagement'], preserveScroll: true });
}
async function openPerformance(item) {
    performanceOpen.value = true;
    await loadPerformance(item.id, 30);
}
async function loadPerformance(id, days) {
    const { data } = await window.axios.get(route('admin.categories.performance', id), { params: { days } });
    performance.value = data;
}
async function applyBulk() {
    if (!window.confirm(`Apply bulk ${bulkForm.action} to ${selectedIds.value.length} items?`)) return;
    await window.axios.post(route('admin.categories.bulk'), { ids: selectedIds.value, ...bulkForm, confirm: true });
    selectedIds.value = [];
    router.reload({ only: ['categoryManagement'], preserveScroll: true });
}
async function previewImport(commit) {
    if (!csvFile.value) return;
    const data = new FormData();
    data.append('csv', csvFile.value);
    data.append('commit', commit ? '1' : '0');
    const response = await window.axios.post(route('admin.categories.import'), data);
    importPreview.value = response.data;
    if (commit) router.reload({ only: ['categoryManagement'], preserveScroll: true });
}
</script>
