<template>
    <div
        class="flex flex-col bg-white"
        :class="embedded ? 'mx-auto w-full max-w-[30rem]' : 'w-[min(100vw-2rem,22rem)] min-w-[18rem] rounded-2xl border border-slate-200 shadow-xl sm:w-80'"
    >
        <div class="border-b border-slate-100 p-3">
            <div class="flex items-center justify-between gap-2">
                <p class="text-[10px] font-black uppercase tracking-wide text-slate-400">GIFs</p>
                <button type="button" class="rounded-lg px-2 py-1 text-xs font-bold text-slate-500 hover:bg-slate-100" @mousedown.prevent="emit('close')">
                    Close
                </button>
            </div>
            <input
                v-model="query"
                type="search"
                placeholder="Search GIFs…"
                class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary-400 focus:ring-2 focus:ring-primary-100"
                @input="onQueryInput"
                @keydown.stop
            />
        </div>
        <div class="max-h-52 overflow-y-auto p-2">
            <p v-if="loading" class="px-2 py-6 text-center text-xs font-semibold text-slate-500">Loading…</p>
            <p v-else-if="error" class="px-2 py-6 text-center text-xs font-semibold text-rose-600">{{ error }}</p>
            <p v-else-if="!results.length" class="px-2 py-6 text-center text-xs font-semibold text-slate-500">No GIFs found.</p>
            <ul v-else class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                <li v-for="gif in results" :key="gif.id">
                    <button
                        type="button"
                        class="block w-full overflow-hidden rounded-lg ring-1 ring-slate-100 transition hover:ring-primary-400 active:scale-[0.98]"
                        @mousedown.prevent="pick(gif)"
                    >
                        <img :src="gif.preview" :alt="gif.title" class="h-20 w-full object-cover" loading="lazy" draggable="false" />
                    </button>
                </li>
            </ul>
        </div>
    </div>
</template>

<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    searchUrl: { type: String, required: true },
    embedded: { type: Boolean, default: false },
});

const emit = defineEmits(['select', 'close']);

const query = ref('');
const results = ref([]);
const loading = ref(false);
const configured = ref(true);
const error = ref('');

let debounceTimer = null;

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            query.value = '';
            void searchGifs();
        } else {
            results.value = [];
            error.value = '';
        }
    },
);

onBeforeUnmount(() => {
    clearTimeout(debounceTimer);
});

function onQueryInput() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => searchGifs(), 300);
}

async function searchGifs() {
    if (!props.open) return;
    loading.value = true;
    error.value = '';
    try {
        const { data } = await window.axios.get(props.searchUrl, {
            params: { q: query.value.trim() || undefined },
        });
        results.value = data.items ?? [];
        configured.value = data.configured !== false;
        if (!configured.value) {
            error.value = 'GIF search is not configured. Add TENOR_API_KEY or GIPHY_API_KEY to your .env file.';
        }
    } catch {
        results.value = [];
        error.value = 'Could not load GIFs. Try again.';
    } finally {
        loading.value = false;
    }
}

function pick(gif) {
    if (!gif?.url) return;
    emit('select', gif);
}
</script>
