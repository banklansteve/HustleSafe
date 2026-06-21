<template>
    <p class="whitespace-pre-wrap leading-relaxed">
        <template v-if="isRedacted && !isRevealed">
            <span
                class="font-black uppercase tracking-wide"
                :class="inverted ? 'text-rose-300' : 'text-rose-600'"
            >{{ displayLabel }}</span>
        </template>
        <template v-else>
            <template v-for="(part, index) in parts" :key="index">
                <mark
                    v-if="part.highlight"
                    class="rounded bg-rose-100 px-0.5 font-black text-rose-700 ring-1 ring-rose-200"
                >{{ part.text }}</mark>
                <span v-else>{{ part.text }}</span>
            </template>
        </template>
    </p>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    body: { type: String, default: '' },
    isRedacted: { type: Boolean, default: false },
    isRevealed: { type: Boolean, default: false },
    redactionLabel: { type: String, default: null },
    triggerHighlights: {
        type: Array,
        default: () => [],
    },
    inverted: { type: Boolean, default: false },
});

const displayLabel = computed(() => {
    if (props.redactionLabel) {
        return props.redactionLabel;
    }

    return props.body || 'REDACTED — POLICY VIOLATION';
});

const parts = computed(() => {
    const text = props.body || '';
    const spans = [...(props.triggerHighlights || [])]
        .filter((span) => Number.isFinite(span?.start) && Number.isFinite(span?.end) && span.end > span.start)
        .sort((left, right) => left.start - right.start);

    if (!text || spans.length === 0) {
        return [{ text, highlight: false }];
    }

    const segments = [];
    let cursor = 0;

    for (const span of spans) {
        const start = Math.max(0, Math.min(text.length, Number(span.start)));
        const end = Math.max(start, Math.min(text.length, Number(span.end)));

        if (start > cursor) {
            segments.push({ text: text.slice(cursor, start), highlight: false });
        }

        if (end > start) {
            segments.push({ text: text.slice(start, end), highlight: true });
        }

        cursor = Math.max(cursor, end);
    }

    if (cursor < text.length) {
        segments.push({ text: text.slice(cursor), highlight: false });
    }

    return segments.length ? segments : [{ text, highlight: false }];
});
</script>
