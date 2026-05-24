<template>
    <AppShell title="Support" subtitle="Chat with our team — we're here to help.">
        <section class="mx-auto max-w-lg rounded-[1.75rem] border border-primary-100 bg-white p-6 shadow-sm">
            <p class="text-xs font-black uppercase tracking-[0.2em] text-primary-700">New conversation</p>
            <p class="mt-2 text-sm font-semibold text-slate-600">Tell us what you need help with. A support agent will join shortly.</p>

            <form class="mt-6 space-y-4" @submit.prevent="submit">
                <div>
                    <label class="text-xs font-black uppercase text-slate-500">Subject</label>
                    <input
                        v-model="form.subject"
                        type="text"
                        required
                        maxlength="200"
                        class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold focus:border-primary-400 focus:ring-2 focus:ring-primary-100"
                        placeholder="Brief summary of your issue"
                    />
                    <p v-if="form.errors.subject" class="mt-1 text-xs font-semibold text-rose-600">{{ form.errors.subject }}</p>
                </div>
                <div>
                    <label class="text-xs font-black uppercase text-slate-500">Category</label>
                    <select
                        v-model="form.category"
                        required
                        class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold focus:border-primary-400 focus:ring-2 focus:ring-primary-100"
                    >
                        <option value="" disabled>Select a category</option>
                        <option v-for="(label, key) in categories" :key="key" :value="key">{{ label }}</option>
                    </select>
                    <p v-if="form.errors.category" class="mt-1 text-xs font-semibold text-rose-600">{{ form.errors.category }}</p>
                </div>
                <div>
                    <label class="text-xs font-black uppercase text-slate-500">Message (optional)</label>
                    <textarea
                        v-model="form.initial_message"
                        rows="4"
                        class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold focus:border-primary-400 focus:ring-2 focus:ring-primary-100"
                        placeholder="Describe your issue…"
                    />
                </div>
                <button
                    type="submit"
                    class="w-full rounded-xl bg-primary-700 py-3 text-sm font-black uppercase text-white hover:bg-primary-800 disabled:opacity-50"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Starting…' : 'Start chat' }}
                </button>
            </form>

            <div v-if="chats.length" class="mt-8 border-t border-slate-100 pt-6">
                <p class="text-xs font-black uppercase text-slate-400">Recent conversations</p>
                <ul class="mt-3 space-y-2">
                    <li v-for="c in chats" :key="c.id">
                        <Link
                            :href="route('support.chat.show', c.id)"
                            class="flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3 transition hover:border-primary-300 hover:bg-primary-50"
                        >
                            <span>
                                <span class="block text-sm font-black text-slate-900">{{ c.subject }}</span>
                                <span class="text-xs font-semibold text-slate-500">{{ c.category_label }} · {{ c.chat_status }}</span>
                            </span>
                            <span v-if="c.unread_count" class="rounded-full bg-rose-600 px-2 py-0.5 text-[10px] font-black text-white">{{ c.unread_count }}</span>
                        </Link>
                    </li>
                </ul>
            </div>

            <div v-if="activeTicket" class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                <p class="text-xs font-black uppercase text-emerald-800">Active chat</p>
                <Link :href="route('support.chat.show', activeTicket.id)" class="mt-1 block text-sm font-bold text-emerald-900 underline">
                    Continue: {{ activeTicket.subject }}
                </Link>
            </div>
        </section>
    </AppShell>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AppShell from '@/Layouts/AppShell.vue';

const props = defineProps({
    categories: { type: Object, default: () => ({}) },
    chats: { type: Array, default: () => [] },
    activeTicket: { type: Object, default: null },
});

const form = useForm({
    subject: '',
    category: '',
    initial_message: '',
});

function route(name, params = {}) {
    return window.route(name, params);
}

function submit() {
    form.post(route('support.chat.start'));
}
</script>
