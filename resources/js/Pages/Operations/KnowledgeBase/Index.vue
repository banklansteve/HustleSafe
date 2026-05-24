<template>
    <OperationsShell title="Internal knowledge base" subtitle="Search policies, precedents, and step-by-step procedures while handling cases.">
        <div class="mb-4 rounded-2xl border border-primary-100 bg-gradient-to-br from-primary-50/90 to-white p-4 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-primary-800">Quick start</p>
            <p class="mt-1 text-sm font-semibold text-slate-700">
                Filter <strong>Common tasks</strong> for disputes, live support, KYC, fraud flags, and escalations — or search any keyword below.
            </p>
            <div class="mt-3 flex flex-wrap gap-2">
                <button
                    v-for="chip in quickCategories"
                    :key="chip"
                    type="button"
                    class="rounded-full px-3 py-1.5 text-[10px] font-black uppercase tracking-wide transition"
                    :class="category === chip ? 'bg-primary-700 text-white' : 'bg-white text-primary-800 ring-1 ring-primary-200 hover:bg-primary-50'"
                    @click="setCategory(chip)"
                >
                    {{ chip }}
                </button>
            </div>
        </div>

        <div class="mb-4 flex flex-col gap-3 sm:flex-row">
            <input
                v-model="q"
                type="search"
                placeholder="Search articles (e.g. dispute, KYC, fraud, escalate)…"
                class="flex-1 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-900 focus:border-primary-400 focus:ring-2 focus:ring-primary-100"
                @input="debouncedSearch"
            />
            <select
                v-model="category"
                class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-semibold text-slate-900"
                @change="search"
            >
                <option value="">All categories</option>
                <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
            </select>
            <button type="button" class="shrink-0 rounded-xl bg-primary-700 px-4 py-2.5 text-xs font-black uppercase text-white active:scale-[0.98]" @click="search">
                Search
            </button>
        </div>

        <p v-if="articles.length" class="mb-3 text-xs font-semibold text-slate-500">{{ articles.length }} article{{ articles.length === 1 ? '' : 's' }}</p>

        <p v-if="!loading && !articles.length" class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/80 px-6 py-12 text-center text-sm font-semibold text-slate-500">
            No articles match. Try another keyword or category, or submit a suggestion after Super Admin publishes content.
        </p>

        <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="article in articles"
                :key="article.id"
                class="cursor-pointer rounded-2xl border border-slate-100 bg-white p-4 shadow-sm ring-1 ring-slate-100 transition hover:border-primary-200 hover:shadow-md"
                @click="loadArticle(article)"
            >
                <p class="text-[10px] font-black uppercase tracking-wide text-primary-700">{{ article.category }}</p>
                <h3 class="mt-1 font-display text-lg font-black leading-snug text-slate-950">{{ article.title }}</h3>
                <p class="mt-2 line-clamp-3 text-sm font-semibold text-slate-600">{{ article.excerpt }}</p>
                <p class="mt-3 text-[10px] font-semibold text-slate-400">Updated {{ formatDate(article.updated_at) }}</p>
            </article>
        </div>

        <OperationsSlideOver :open="slideOpen" :title="active?.title || 'Article'" subtitle="Procedure reference" eyebrow="Knowledge" @close="slideOpen = false">
            <div v-if="active" class="prose prose-sm max-w-none prose-headings:font-display prose-headings:font-black prose-h2:text-slate-950 prose-p:text-slate-700 prose-li:text-slate-700" v-html="active.body" />
            <div class="mt-8 border-t border-slate-100 pt-5">
                <p class="text-[10px] font-black uppercase tracking-wide text-slate-500">Suggest an improvement</p>
                <textarea v-model="suggestion" rows="3" class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold" placeholder="Missing step, policy change, or new article idea…" />
                <button type="button" class="mt-2 w-full rounded-xl border border-primary-200 bg-primary-50 py-2.5 text-sm font-black text-primary-900 active:scale-[0.98] disabled:opacity-50" :disabled="busy.suggest" @click="submitSuggestion">
                    Submit suggestion
                </button>
                <p class="mt-2 text-center text-[10px] font-semibold text-slate-400">Super Admins review suggestions and update published articles.</p>
            </div>
        </OperationsSlideOver>
    </OperationsShell>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import OperationsSlideOver from '@/Pages/Operations/Components/OperationsSlideOver.vue';
import OperationsShell from '@/Layouts/OperationsShell.vue';
import { useOperationsAction } from '@/composables/useOperationsAction';

const quickCategories = ['Common tasks', 'Getting started', 'Escalations', 'Cases', 'Moderation', 'People', 'Chat'];

const q = ref('');
const category = ref('');
const categories = ref([]);
const articles = ref([]);
const slideOpen = ref(false);
const active = ref(null);
const activeId = ref(null);
const suggestion = ref('');
const loading = ref(false);
const { busy, runAction } = useOperationsAction();

let searchTimer = null;

onMounted(search);

onBeforeUnmount(() => {
    clearTimeout(searchTimer);
});

function formatDate(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
}

function setCategory(c) {
    category.value = category.value === c ? '' : c;
    search();
}

function debouncedSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(search, 300);
}

async function search() {
    loading.value = true;
    try {
        const { data } = await window.axios.get(route('operations.api.knowledge-base.listing'), { params: { q: q.value, category: category.value } });
        categories.value = data.categories ?? [];
        articles.value = data.articles ?? [];
    } finally {
        loading.value = false;
    }
}

async function loadArticle(article) {
    activeId.value = article.id;
    slideOpen.value = true;
    const { data } = await window.axios.get(route('operations.api.knowledge-base.article', article.id));
    active.value = data;
}

async function submitSuggestion() {
    await runAction('suggest', () => window.axios.post(route('operations.api.knowledge-base.suggest'), { article_id: activeId.value, body: suggestion.value }), 'Suggestion sent.', () => { suggestion.value = ''; });
}
</script>
