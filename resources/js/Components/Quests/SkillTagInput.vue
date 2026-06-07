<template>
    <div class="relative" data-skill-tag-input-root>
        <div v-if="modelValue.length" class="flex flex-wrap gap-2">
            <span
                v-for="(skill, idx) in modelValue"
                :key="`${skill}-${idx}`"
                class="inline-flex items-center gap-1.5 rounded-full bg-primary-100 px-3 py-1.5 text-xs font-bold text-primary-900 ring-1 ring-primary-200/80"
            >
                {{ skill }}
                <button
                    type="button"
                    class="rounded-full p-0.5 text-primary-700 transition hover:bg-primary-200/60 hover:text-primary-950"
                    :aria-label="`Remove ${skill}`"
                    @click="removeSkill(idx)"
                >
                    ×
                </button>
            </span>
        </div>

        <div class="relative mt-2">
            <TextInput
                :id="inputId"
                v-model="draft"
                type="text"
                maxlength="80"
                class="w-full rounded-xl border-slate-200 font-semibold shadow-sm"
                :class="invalid ? 'border-rose-300 ring-rose-200' : ''"
                :placeholder="placeholder"
                :disabled="atMax"
                autocomplete="off"
                @focus="openSuggestions = true"
                @keydown.enter.prevent="commitDraft"
                @keydown.escape="closeSuggestions"
                @keydown.down.prevent="moveHighlight(1)"
                @keydown.up.prevent="moveHighlight(-1)"
            />

            <ul
                v-if="openSuggestions && suggestions.length"
                class="absolute z-30 mt-1 max-h-48 w-full overflow-auto rounded-xl border border-slate-200 bg-white py-1 shadow-lg ring-1 ring-slate-100"
            >
                <li v-for="(skill, idx) in suggestions" :key="skill">
                    <button
                        type="button"
                        class="flex w-full px-3 py-2.5 text-left text-sm font-semibold transition"
                        :class="idx === highlightedIndex ? 'bg-primary-50 text-primary-900' : 'text-slate-800 hover:bg-slate-50'"
                        @mousedown.prevent="selectSkill(skill)"
                    >
                        {{ skill }}
                    </button>
                </li>
            </ul>
        </div>

        <p class="mt-1.5 text-[11px] font-semibold text-slate-500">
            {{ modelValue.length }} / {{ max }} selected
            <span v-if="atMax"> — remove one to add another.</span>
        </p>
    </div>
</template>

<script setup>
import TextInput from '@/Components/TextInput.vue';
import axios from 'axios';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    max: { type: Number, default: 10 },
    categoryId: { type: [Number, String, null], default: null },
    suggestUrl: { type: String, required: true },
    placeholder: { type: String, default: 'Start typing a skill…' },
    inputId: { type: String, default: 'required-skills-input' },
    invalid: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const draft = ref('');
const suggestions = ref([]);
const openSuggestions = ref(false);
const highlightedIndex = ref(0);
let suggestTimer = null;

const atMax = computed(() => props.modelValue.length >= props.max);

function closeSuggestions() {
    openSuggestions.value = false;
    highlightedIndex.value = 0;
}

function moveHighlight(delta) {
    if (!suggestions.value.length) {
        return;
    }
    const len = suggestions.value.length;
    highlightedIndex.value = (highlightedIndex.value + delta + len) % len;
}

function selectSkill(skill) {
    addSkill(skill);
    draft.value = '';
    closeSuggestions();
}

function addSkill(raw) {
    const label = String(raw || '').trim();
    if (!label || atMax.value) {
        return;
    }
    const exists = props.modelValue.some((s) => String(s).toLowerCase() === label.toLowerCase());
    if (exists) {
        return;
    }
    emit('update:modelValue', [...props.modelValue, label]);
}

function removeSkill(idx) {
    const next = [...props.modelValue];
    next.splice(idx, 1);
    emit('update:modelValue', next);
}

function commitDraft() {
    if (suggestions.value.length && highlightedIndex.value >= 0) {
        selectSkill(suggestions.value[highlightedIndex.value]);

        return;
    }
    if (draft.value.trim()) {
        addSkill(draft.value);
        draft.value = '';
        closeSuggestions();
    }
}

async function fetchSuggestions() {
    const q = draft.value.trim();
    if (!props.categoryId || q.length < 1) {
        suggestions.value = [];
        highlightedIndex.value = 0;

        return;
    }

    try {
        const { data } = await axios.get(props.suggestUrl, {
            params: {
                q,
                quest_category_id: props.categoryId,
                exclude: props.modelValue,
            },
        });
        suggestions.value = data.skills || [];
        highlightedIndex.value = 0;
        openSuggestions.value = suggestions.value.length > 0;
    } catch {
        suggestions.value = [];
    }
}

watch(draft, () => {
    window.clearTimeout(suggestTimer);
    suggestTimer = window.setTimeout(fetchSuggestions, 220);
});

watch(
    () => props.categoryId,
    () => {
        draft.value = '';
        suggestions.value = [];
        closeSuggestions();
    },
);

watch(
    () => [...props.modelValue],
    () => {
        if (draft.value.trim()) {
            fetchSuggestions();
        }
    },
);

function onDocumentClick(event) {
    if (!(event.target instanceof Element)) {
        return;
    }
    if (!event.target.closest('[data-skill-tag-input-root]')) {
        closeSuggestions();
    }
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
});

onUnmounted(() => {
    document.removeEventListener('click', onDocumentClick);
    window.clearTimeout(suggestTimer);
});
</script>
