<template>
    <section class="rounded-xl border border-slate-100 bg-gradient-to-br from-slate-50 via-white to-primary-50/30 p-4 ring-1 ring-slate-100">
        <p v-if="eyebrow" class="text-[10px] font-black uppercase tracking-[0.2em] text-primary-700">{{ eyebrow }}</p>
        <h3 v-if="heading" class="font-display mt-1 text-lg font-black text-slate-950">{{ heading }}</h3>

        <dl class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
            <div v-for="stat in stats" :key="stat.label" class="rounded-xl border border-white/80 bg-white/90 px-3 py-2.5 shadow-sm">
                <dt class="text-[10px] font-black uppercase tracking-wide text-slate-500">{{ stat.label }}</dt>
                <dd class="mt-1 text-sm font-black text-slate-950">{{ stat.value }}</dd>
                <dd v-if="stat.hint" class="mt-0.5 text-[11px] font-semibold text-slate-500">{{ stat.hint }}</dd>
            </div>
        </dl>

        <div v-if="chips?.length" class="mt-4 flex flex-wrap gap-2">
            <span
                v-for="chip in chips"
                :key="chip.label"
                class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wide"
                :class="chip.tone === 'danger' ? 'bg-rose-100 text-rose-800' : chip.tone === 'warn' ? 'bg-amber-100 text-amber-900' : 'bg-slate-100 text-slate-700'"
            >
                {{ chip.label }}
            </span>
        </div>

        <ul v-if="links?.length" class="mt-4 space-y-2 border-t border-slate-100 pt-4">
            <li v-for="link in links" :key="link.label + (link.href || '')">
                <component
                    :is="link.href ? (link.external ? 'a' : Link) : 'div'"
                    :href="link.href || undefined"
                    :target="link.external ? '_blank' : undefined"
                    :rel="link.external ? 'noopener noreferrer' : undefined"
                    class="block rounded-xl border border-slate-100 bg-white px-3 py-2.5 transition hover:border-primary-200 hover:bg-primary-50/50"
                    :class="link.href ? 'cursor-pointer' : ''"
                >
                    <p class="text-[10px] font-black uppercase tracking-wide text-primary-700">{{ link.label }}</p>
                    <p class="mt-0.5 text-sm font-bold text-slate-900">{{ link.title }}</p>
                    <p v-if="link.preview" class="mt-1 line-clamp-2 text-xs font-semibold text-slate-500">{{ link.preview }}</p>
                </component>
            </li>
        </ul>
    </section>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    eyebrow: { type: String, default: 'At a glance' },
    heading: { type: String, default: '' },
    stats: { type: Array, default: () => [] },
    chips: { type: Array, default: () => [] },
    links: { type: Array, default: () => [] },
});
</script>
