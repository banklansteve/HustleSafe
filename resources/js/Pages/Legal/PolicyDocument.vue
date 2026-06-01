<template>
    <AppShell>
        <Head :title="document.title" />

        <div class="legal-doc mx-auto max-w-6xl px-4 pb-16 pt-6 sm:px-6 lg:px-8 lg:pt-10">
            <!-- Mobile / top bar -->
            <div class="no-print mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <p class="text-[10px] font-black uppercase tracking-[0.22em] text-primary-700">Legal</p>
                    <h1 class="font-display mt-1 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">
                        {{ document.title }}
                    </h1>
                    <p v-if="document.tagline" class="mt-1 text-sm font-semibold text-slate-600">
                        {{ document.tagline }}
                    </p>
                    <p class="mt-2 text-xs font-bold text-slate-500">
                        Last updated: {{ document.last_updated }}
                    </p>
                </div>
                <div class="flex shrink-0 flex-wrap gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-full border-2 border-slate-200 bg-white px-4 py-2 text-xs font-black uppercase tracking-wide text-slate-800 shadow-sm transition hover:border-primary-200 hover:bg-primary-50"
                        @click="printPage"
                    >
                        <PrinterIcon class="h-4 w-4" aria-hidden="true" />
                        Print
                    </button>
                    <a
                        :href="document.pdf_url"
                        class="inline-flex items-center gap-2 rounded-full bg-primary-700 px-4 py-2 text-xs font-black uppercase tracking-wide text-white shadow-sm transition hover:bg-primary-800"
                    >
                        <ArrowDownTrayIcon class="h-4 w-4" aria-hidden="true" />
                        Download PDF
                    </a>
                </div>
            </div>

            <!-- Plain-English summary -->
            <section class="mb-8 rounded-2xl border border-primary-200/80 bg-gradient-to-br from-primary-50/90 to-teal-50/50 p-5 ring-1 ring-primary-100 sm:p-6">
                <h2 class="text-xs font-black uppercase tracking-[0.18em] text-primary-800">Plain-English summary</h2>
                <p class="mt-2 text-xs font-semibold text-primary-900/80">
                    This is a short overview. The full document below is what applies legally.
                </p>
                <ul class="mt-4 space-y-3 text-sm font-semibold leading-relaxed text-slate-800">
                    <li v-for="(line, i) in document.summary" :key="i" class="flex gap-3">
                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-primary-600" aria-hidden="true" />
                        <span>{{ line }}</span>
                    </li>
                </ul>
            </section>

            <!-- Mobile section jump -->
            <div class="no-print mb-6 lg:hidden">
                <label for="section-jump" class="text-[10px] font-black uppercase tracking-wide text-slate-500">Jump to section</label>
                <select
                    id="section-jump"
                    v-model="activeSection"
                    class="mt-2 w-full rounded-xl border-slate-200 bg-white py-3 text-sm font-semibold text-slate-800 shadow-sm ring-1 ring-slate-100"
                    @change="scrollToSection(activeSection)"
                >
                    <option v-for="section in document.sections" :key="section.id" :value="section.id">
                        {{ section.title }}
                    </option>
                </select>
            </div>

            <div class="lg:grid lg:grid-cols-[minmax(0,15rem)_minmax(0,1fr)] lg:gap-10 xl:grid-cols-[minmax(0,17rem)_minmax(0,1fr)] xl:gap-12">
                <!-- Desktop sidebar -->
                <aside class="no-print hidden lg:block">
                    <nav
                        class="sticky top-24 max-h-[calc(100vh-7rem)] overflow-y-auto rounded-2xl border border-slate-200 bg-white p-4 shadow-sm ring-1 ring-slate-100"
                        aria-label="Document sections"
                    >
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">On this page</p>
                        <ul class="mt-3 space-y-1">
                            <li v-for="section in document.sections" :key="section.id">
                                <a
                                    :href="`#${section.id}`"
                                    class="block rounded-lg px-3 py-2 text-xs font-bold leading-snug transition"
                                    :class="activeSection === section.id ? 'bg-primary-50 text-primary-900 ring-1 ring-primary-100' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'"
                                    @click.prevent="scrollToSection(section.id)"
                                >
                                    {{ section.title }}
                                </a>
                            </li>
                        </ul>
                    </nav>
                </aside>

                <!-- Main content -->
                <article class="min-w-0 space-y-8">
                    <section
                        v-for="section in document.sections"
                        :id="section.id"
                        :key="section.id"
                        class="scroll-mt-24 rounded-2xl border border-slate-100 bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6"
                    >
                        <h2 class="font-display text-lg font-black text-slate-900 sm:text-xl">
                            {{ section.title }}
                        </h2>
                        <div v-if="section.paragraphs?.length" class="mt-4 space-y-4">
                            <p
                                v-for="(para, pi) in section.paragraphs"
                                :key="pi"
                                class="text-sm font-medium leading-relaxed text-slate-700"
                                v-html="linkify(para)"
                            />
                        </div>
                        <ul v-if="section.bullets?.length" class="mt-4 list-none space-y-3 pl-0">
                            <li
                                v-for="(bullet, bi) in section.bullets"
                                :key="bi"
                                class="flex gap-3 text-sm font-medium leading-relaxed text-slate-700"
                            >
                                <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-teal-500" aria-hidden="true" />
                                <span v-html="linkify(bullet)" />
                            </li>
                        </ul>
                    </section>

                    <!-- Related policies -->
                    <section class="no-print rounded-2xl border border-slate-200 bg-slate-50/80 p-5 sm:p-6">
                        <h2 class="font-display text-sm font-black uppercase tracking-wide text-slate-700">Related policies</h2>
                        <ul class="mt-4 space-y-3">
                            <li v-for="policy in document.related_policies" :key="policy.href">
                                <Link
                                    :href="policy.href"
                                    class="group flex flex-col rounded-xl border border-slate-200 bg-white px-4 py-3 transition hover:border-primary-200 hover:shadow-sm"
                                >
                                    <span class="text-sm font-black text-primary-800 group-hover:underline">{{ policy.label }}</span>
                                    <span class="mt-0.5 text-xs font-semibold text-slate-600">{{ policy.description }}</span>
                                </Link>
                            </li>
                        </ul>
                    </section>

                    <p class="text-xs font-semibold leading-relaxed text-slate-500">
                        This document is provided for transparency. Your in-product contracts and checkout screens may show additional terms for specific transactions.
                    </p>
                </article>
            </div>
        </div>
    </AppShell>
</template>

<script setup>
import AppShell from '@/Layouts/AppShell.vue';
import { ArrowDownTrayIcon, PrinterIcon } from '@heroicons/vue/24/outline';
import { Head, Link } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    document: { type: Object, required: true },
});

const activeSection = ref(props.document.sections?.[0]?.id ?? '');
let observer;

function scrollToSection(id) {
    if (!id) {
        return;
    }
    activeSection.value = id;
    const el = globalThis.document.getElementById(id);
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function printPage() {
    window.print();
}

function linkify(text) {
    if (!text || typeof text !== 'string') {
        return '';
    }
    const escaped = text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

    return escaped.replace(/(https?:\/\/[^\s<]+)/g, (url) => {
        return `<a href="${url}" class="font-bold text-primary-800 underline underline-offset-2" target="_blank" rel="noopener noreferrer">${url}</a>`;
    });
}

onMounted(() => {
    const sections = props.document.sections ?? [];
    if (!sections.length || typeof IntersectionObserver === 'undefined') {
        return;
    }

    observer = new IntersectionObserver(
        (entries) => {
            const visible = entries
                .filter((e) => e.isIntersecting)
                .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];
            if (visible?.target?.id) {
                activeSection.value = visible.target.id;
            }
        },
        { rootMargin: '-20% 0px -55% 0px', threshold: [0, 0.25, 0.5] },
    );

    sections.forEach((section) => {
        const el = globalThis.document.getElementById(section.id);
        if (el) {
            observer.observe(el);
        }
    });
});

onBeforeUnmount(() => {
    observer?.disconnect();
});
</script>

<style scoped>
@media print {
    .no-print {
        display: none !important;
    }

    .legal-doc {
        max-width: 100%;
        padding: 0;
    }

    .legal-doc section {
        break-inside: avoid;
        box-shadow: none;
        border: none;
        ring: none;
        padding-left: 0;
        padding-right: 0;
    }
}
</style>
