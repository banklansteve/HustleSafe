<template>
    <component
        :is="shellComponent"
        :title="portfolio.title"
        subtitle="Inspect portfolio copy and every uploaded file. Hide, publish, or request revision when content is unsafe or misleading."
    >
        <div class="space-y-5">
            <div class="flex flex-wrap items-center gap-3">
                <Link :href="pageRoute('portfolio-review.index')" class="text-xs font-black uppercase tracking-wide text-primary-700 underline dark:text-primary-300">
                    ← All portfolios
                </Link>
                <a
                    v-if="portfolio.public_url"
                    :href="portfolio.public_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-xs font-black uppercase tracking-wide text-slate-600 underline dark:text-slate-300"
                >
                    View public page
                </a>
                <a
                    v-if="portfolio.owner?.slug"
                    :href="route('freelancers.public', { slug: portfolio.owner.slug })"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-xs font-black uppercase tracking-wide text-slate-600 underline dark:text-slate-300"
                >
                    Freelancer profile
                </a>
            </div>

            <div class="grid gap-5 xl:grid-cols-[1.4fr_22rem]">
                <div class="space-y-5">
                    <AdminPanel title="Portfolio content">
                        <div v-if="portfolio.cover_url" class="mb-4 overflow-hidden rounded-2xl ring-1 ring-slate-200 dark:ring-white/10">
                            <img :src="portfolio.cover_url" :alt="portfolio.title" class="max-h-80 w-full object-cover" />
                        </div>
                        <p class="text-sm font-semibold leading-relaxed whitespace-pre-wrap" :class="shell.cardMuted">
                            {{ portfolio.description || 'No description provided.' }}
                        </p>
                        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                            <div>
                                <dt class="text-[10px] font-black uppercase tracking-wider text-slate-500">Status</dt>
                                <dd class="mt-1 font-bold capitalize">{{ formatStatus(portfolio.status) }}</dd>
                            </div>
                            <div>
                                <dt class="text-[10px] font-black uppercase tracking-wider text-slate-500">Visibility</dt>
                                <dd class="mt-1 font-bold">{{ portfolio.admin_hidden ? 'Hidden from discovery' : 'Visible' }}</dd>
                            </div>
                            <div v-if="portfolio.category">
                                <dt class="text-[10px] font-black uppercase tracking-wider text-slate-500">Category</dt>
                                <dd class="mt-1 font-bold">{{ portfolio.category }}<span v-if="portfolio.subcategory"> · {{ portfolio.subcategory }}</span></dd>
                            </div>
                            <div v-if="portfolio.quest">
                                <dt class="text-[10px] font-black uppercase tracking-wider text-slate-500">Linked quest</dt>
                                <dd class="mt-1 font-bold">{{ portfolio.quest.title }}</dd>
                            </div>
                        </dl>
                    </AdminPanel>

                    <AdminPanel :title="`Media (${portfolio.files?.length || 0})`" description="Open each file to verify authenticity and appropriateness.">
                        <div v-if="!portfolio.files?.length" class="rounded-2xl border border-dashed p-8 text-center text-sm font-semibold" :class="shell.cardMuted">
                            No media files attached.
                        </div>
                        <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div
                                v-for="file in portfolio.files"
                                :key="file.id"
                                class="overflow-hidden rounded-2xl border ring-1 ring-slate-100 dark:ring-white/10"
                                :class="shell.card"
                            >
                                <a :href="file.url" target="_blank" rel="noopener noreferrer" class="block">
                                    <div class="aspect-video bg-slate-100 dark:bg-slate-900">
                                        <img v-if="file.is_image" :src="file.url" :alt="file.original_name" class="h-full w-full object-cover" />
                                        <video v-else-if="file.is_video" :src="file.url" class="h-full w-full object-cover" controls preload="metadata" />
                                        <div v-else class="flex h-full items-center justify-center p-4 text-center text-xs font-bold text-slate-500">
                                            {{ file.original_name || 'Document' }}
                                        </div>
                                    </div>
                                </a>
                                <p class="truncate px-3 py-2 text-xs font-semibold" :class="shell.cardMuted">{{ file.original_name }}</p>
                            </div>
                        </div>
                    </AdminPanel>
                </div>

                <div class="space-y-5">
                    <AdminPanel title="Owner">
                        <div class="flex items-center gap-3">
                            <img
                                v-if="portfolio.owner?.avatar_url"
                                :src="portfolio.owner.avatar_url"
                                alt=""
                                class="h-12 w-12 rounded-full object-cover ring-2 ring-slate-200 dark:ring-white/10"
                            />
                            <div>
                                <p class="font-black">{{ portfolio.owner?.name || '—' }}</p>
                                <p class="text-xs font-semibold text-slate-500">{{ portfolio.owner?.email }}</p>
                            </div>
                        </div>
                    </AdminPanel>

                    <AdminPanel title="Moderation actions">
                        <form class="space-y-4" @submit.prevent="submit">
                            <div>
                                <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Status</label>
                                <select v-model="form.status" class="mt-1 w-full rounded-2xl border px-3 py-3 text-sm font-bold" :class="shell.input">
                                    <option value="">Keep current</option>
                                    <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                                </select>
                            </div>
                            <label class="flex items-center gap-2 text-sm font-bold">
                                <input v-model="form.admin_hidden" type="checkbox" class="rounded border-slate-300 text-primary-600" />
                                Hide from public discovery
                            </label>
                            <div>
                                <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Internal note</label>
                                <textarea
                                    v-model="form.note"
                                    rows="3"
                                    class="mt-1 w-full rounded-2xl border px-3 py-3 text-sm font-semibold"
                                    :class="shell.input"
                                    placeholder="Optional note for the activity log"
                                />
                            </div>
                            <button type="submit" class="w-full rounded-2xl px-4 py-3 text-sm font-black uppercase" :class="shell.btnPrimary" :disabled="form.processing">
                                Save review
                            </button>
                        </form>
                    </AdminPanel>
                </div>
            </div>
        </div>
    </component>
</template>

<script setup>
import AdminPanel from '@/Components/Admin/AdminPanel.vue';
import AdminShell from '@/Layouts/AdminShell.vue';
import OperationsShell from '@/Layouts/OperationsShell.vue';
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    routeNamespace: { type: String, default: 'admin' },
    portfolio: { type: Object, required: true },
    statusOptions: { type: Array, default: () => [] },
});

const shellComponent = computed(() => (props.routeNamespace === 'operations' ? OperationsShell : AdminShell));
const { shell } = useInjectedAdminTheme();

const form = useForm({
    status: '',
    admin_hidden: props.portfolio.admin_hidden ?? false,
    note: '',
});

function pageRoute(name, params = {}) {
    return route(`${props.routeNamespace}.${name}`, params);
}

function formatStatus(value) {
    return String(value || '').replace(/_/g, ' ');
}

function submit() {
    form.patch(pageRoute('portfolio-review.update', props.portfolio.slug), {
        preserveScroll: true,
    });
}
</script>
