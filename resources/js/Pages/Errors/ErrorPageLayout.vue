<template>
    <div class="flex min-h-screen flex-col items-center justify-center bg-gradient-to-b from-slate-50 via-white to-primary-50/40 px-4 py-12">
        <div class="w-full max-w-lg text-center">
            <p class="text-[10px] font-black uppercase tracking-[0.35em] text-primary-700">{{ code }}</p>
            <ErrorScene class="mx-auto mt-6" />
            <h1 class="font-display mt-8 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">{{ title }}</h1>
            <p class="mt-3 text-sm font-semibold leading-relaxed text-slate-600">{{ subtitle }}</p>
            <p v-if="detail" class="mt-2 text-xs font-medium text-slate-500">{{ detail }}</p>
            <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                <button
                    type="button"
                    class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-black text-slate-800 shadow-sm transition hover:bg-slate-50"
                    @click="goBack"
                >
                    ← Go back
                </button>
                <Link
                    :href="resolvedHomeHref"
                    class="rounded-2xl bg-primary-700 px-5 py-3 text-sm font-black text-white shadow-lg shadow-primary-900/20 transition hover:bg-primary-800"
                >
                    {{ homeLabel }}
                </Link>
            </div>
        </div>
    </div>
</template>

<script setup>
import ErrorScene from '@/Components/Errors/ErrorScene.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    code: { type: String, required: true },
    title: { type: String, required: true },
    subtitle: { type: String, required: true },
    detail: { type: String, default: '' },
    homeHref: { type: String, default: '' },
    homeLabel: { type: String, default: 'Home' },
});

const page = usePage();

const resolvedHomeHref = computed(() => {
    if (props.homeHref) {
        return props.homeHref;
    }
    const role = page.props.auth?.user?.role?.slug;
    if (role === 'super_admin') {
        return route('admin.dashboard');
    }
    if (role === 'admin') {
        return route('operations.dashboard');
    }

    return route('dashboard');
});

function goBack() {
    if (window.history.length > 1) {
        window.history.back();
    } else {
        window.location.href = resolvedHomeHref.value;
    }
}
</script>
