<template>
    <div class="space-y-3">
        <div v-if="showControls" class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex gap-2 overflow-x-auto pb-1">
                <button
                    v-for="item in categories"
                    :key="item.key"
                    type="button"
                    class="rounded-full px-3 py-1.5 text-xs font-black transition"
                    :class="activeCategory === item.key ? shell.navActive : shell.btnGhost"
                    @click="$emit('update:category', item.key)"
                >
                    {{ item.label }}
                </button>
            </div>
            <div v-if="activeCategory !== 'support_tickets'" class="flex flex-1 flex-wrap justify-end gap-2">
                <input
                    :value="search"
                    type="search"
                    placeholder="Search users, quests, events..."
                    class="min-w-0 rounded-xl border px-3 py-2 text-sm font-semibold sm:w-72"
                    :class="shell.input"
                    @input="$emit('update:search', $event.target.value)"
                />
                <button type="button" class="rounded-xl px-3 py-2 text-xs font-black uppercase" :class="paused ? shell.navActive : shell.btnGhost" @click="$emit('toggle-pause')">
                    {{ paused ? 'Resume live' : 'Pause live updates' }}
                </button>
            </div>
            <div v-else class="flex flex-1 flex-wrap justify-end gap-2">
                <input
                    :value="ticketSearch"
                    type="search"
                    placeholder="Search tickets, customers, references..."
                    class="min-w-0 rounded-xl border px-3 py-2 text-sm font-semibold sm:w-72"
                    :class="shell.input"
                    @input="$emit('update:ticket-search', $event.target.value)"
                />
            </div>
        </div>

        <slot v-if="activeCategory === 'support_tickets'" name="support-tickets" />

        <template v-else>
            <button
                v-if="newCount > 0"
                type="button"
                class="sticky top-2 z-10 w-full rounded-2xl px-4 py-2 text-sm font-black shadow-lg"
                :class="shell.btnPrimary"
                @click="$emit('reveal-new')"
            >
                {{ newCount }} new {{ newCount === 1 ? 'event' : 'events' }}
            </button>

            <div v-if="events.length === 0" class="rounded-2xl border px-4 py-10 text-center" :class="shell.card">
                <p class="text-sm font-black" :class="shell.cardTitle">No live activities to show.</p>
                <p class="mt-1 text-xs font-semibold" :class="shell.cardMuted">Try another category or search term.</p>
            </div>

            <TransitionGroup v-else name="feed" tag="div" class="space-y-2">
                <article
                    v-for="event in events"
                    :key="event.uuid || event.id"
                    class="group rounded-2xl border p-4 transition"
                    :class="[shell.card, severityClass(event)]"
                >
                    <div class="flex gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl text-sm font-black text-white" :class="iconClass(event.category)">
                            {{ iconLabel(event.category) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="font-bold leading-6" :class="shell.cardTitle">
                                        {{ event.title }}
                                    </p>
                                    <p class="text-sm font-semibold leading-6" :class="shell.cardMuted">
                                        <template v-for="(part, index) in sentenceParts(event)" :key="index">
                                            <a
                                                v-if="part.href"
                                                :href="part.href"
                                                class="font-black"
                                                :class="shell.link"
                                                @click.prevent="$emit('inspect', part)"
                                            >
                                                {{ part.text }}
                                            </a>
                                            <span v-else>{{ part.text }}</span>
                                        </template>
                                    </p>
                                </div>
                                <div class="flex items-start gap-2">
                                    <time class="whitespace-nowrap text-xs font-bold" :class="shell.cardMuted" :title="absoluteTime(event.occurred_at)">
                                        {{ event.occurred_at_label }}
                                    </time>
                                    <div v-if="event.actions?.length" class="relative">
                                        <button type="button" class="rounded-lg px-2 py-1 opacity-100 transition lg:opacity-0 lg:group-hover:opacity-100" :class="shell.btnGhost" @click="openMenu = openMenu === event.id ? null : event.id">
                                            ...
                                        </button>
                                        <div v-if="openMenu === event.id" class="absolute right-0 z-20 mt-2 w-48 rounded-2xl border p-2 shadow-xl" :class="shell.card">
                                            <button
                                                v-for="action in event.actions"
                                                :key="action.key"
                                                type="button"
                                                class="block w-full rounded-xl px-3 py-2 text-left text-xs font-bold disabled:cursor-not-allowed disabled:opacity-50"
                                                :class="shell.btnGhost"
                                                :disabled="action.enabled === false"
                                                @click="openMenu = null; $emit('action', { event, action })"
                                            >
                                                {{ action.label }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <span v-for="meta in metadataBadges(event)" :key="meta" class="rounded-full border px-2.5 py-1 text-[11px] font-bold" :class="shell.btnGhost">
                                    {{ meta }}
                                </span>
                            </div>
                        </div>
                    </div>
                </article>
            </TransitionGroup>

            <button v-if="hasMore" type="button" class="w-full rounded-2xl px-4 py-3 text-sm font-black" :class="shell.btnGhost" @click="$emit('load-more')">
                Load older events
            </button>
        </template>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    events: { type: Array, default: () => [] },
    activeCategory: { type: String, default: 'all' },
    search: { type: String, default: '' },
    ticketSearch: { type: String, default: '' },
    paused: { type: Boolean, default: false },
    newCount: { type: Number, default: 0 },
    hasMore: { type: Boolean, default: false },
    showControls: { type: Boolean, default: true },
    enableSupportTicketsTab: { type: Boolean, default: false },
    shell: { type: Object, required: true },
});

defineEmits(['update:category', 'update:search', 'update:ticket-search', 'toggle-pause', 'reveal-new', 'load-more', 'inspect', 'action']);

const openMenu = ref(null);
const categories = computed(() => {
    const items = [
        { key: 'all', label: 'All' },
        { key: 'financial', label: 'Financial' },
        { key: 'disputes', label: 'Disputes' },
        { key: 'users', label: 'Users' },
        { key: 'jobs', label: 'Quests' },
        { key: 'security', label: 'Security' },
        { key: 'reviews', label: 'Reviews' },
    ];

    if (props.enableSupportTicketsTab) {
        items.push({ key: 'support_tickets', label: 'Support tickets' });
    }

    return items;
});

function iconClass(category) {
    return {
        financial: 'bg-emerald-500',
        disputes: 'bg-red-500',
        users: 'bg-blue-500',
        jobs: 'bg-sky-500',
        security: 'bg-red-600',
        reviews: 'bg-purple-500',
        support_tickets: 'bg-indigo-500',
    }[category] || 'bg-slate-500';
}

function iconLabel(category) {
    return {
        financial: '₦',
        disputes: '!',
        users: 'U',
        jobs: 'Q',
        security: 'S',
        reviews: 'R',
        support_tickets: 'T',
    }[category] || '•';
}

function severityClass(event) {
    return ['critical', 'warning'].includes(event.severity) ? 'border-l-4 border-l-red-500' : '';
}

function sentenceParts(event) {
    const entities = event.entities || [];
    if (!entities.length) {
        return [{ text: event.summary }];
    }

    let sentence = event.summary || '';
    const parts = [];
    entities.forEach((entity) => {
        const index = sentence.indexOf(entity.label);
        if (index >= 0) {
            if (index > 0) parts.push({ text: sentence.slice(0, index) });
            parts.push({ text: entity.label, href: entity.href, entity });
            sentence = sentence.slice(index + entity.label.length);
        }
    });
    if (sentence) parts.push({ text: sentence });

    return parts.length ? parts : [{ text: event.summary }];
}

function metadataBadges(event) {
    const meta = event.metadata || {};

    return [meta.amount, meta.category, meta.state, meta.local_government, meta.rating ? `${meta.rating} stars` : null, meta.status ? `Status: ${meta.status}` : null, meta.assignee ? `Assignee: ${meta.assignee}` : null]
        .filter(Boolean);
}

function absoluteTime(value) {
    return value ? new Date(value).toLocaleString('en-NG', { dateStyle: 'medium', timeStyle: 'short' }) : '';
}
</script>

<style scoped>
.feed-enter-active {
    transition: all 220ms ease-out;
}

.feed-enter-from {
    opacity: 0;
    transform: translateY(-10px);
}
</style>
