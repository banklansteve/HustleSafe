<template>
    <AdminShell title="Conversation thread" subtitle="Threaded view of the full client-freelancer conversation and admin access controls.">
        <AdminPanel eyebrow="Thread" :title="`#${thread.id}`">
            <template #actions>
                <div class="flex flex-wrap gap-2">
                    <button v-if="thread.is_blocked" type="button" class="rounded-xl px-4 py-2 text-xs font-black uppercase tracking-wide" :class="shell.btnPrimary" @click="updateVisibility('restore')">Restore access</button>
                    <button v-else type="button" class="rounded-xl border px-4 py-2 text-xs font-black uppercase tracking-wide" :class="shell.btnGhost" @click="updateVisibility('hide')">Hide from users</button>
                    <button type="button" class="rounded-xl bg-rose-600 px-4 py-2 text-xs font-black uppercase tracking-wide text-white" @click="updateVisibility('delete')">Delete access</button>
                    <Link :href="route('admin.management.index', { resource: 'conversation_threads' })" class="rounded-xl border px-4 py-2 text-xs font-black uppercase tracking-wide" :class="shell.btnGhost">Back to threads</Link>
                </div>
            </template>

            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-[10px] font-black uppercase tracking-wider" :class="shell.cardMuted">Quest</dt>
                    <dd class="mt-1 font-semibold">
                        {{ thread.quest?.reference_code }} · {{ thread.quest?.title }}
                    </dd>
                </div>
                <div>
                    <dt class="text-[10px] font-black uppercase tracking-wider" :class="shell.cardMuted">Client</dt>
                    <dd class="mt-1 font-semibold">{{ thread.client?.name }} · {{ thread.client?.email }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] font-black uppercase tracking-wider" :class="shell.cardMuted">Freelancer</dt>
                    <dd class="mt-1 font-semibold">{{ thread.freelancer?.name }} · {{ thread.freelancer?.email }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] font-black uppercase tracking-wider" :class="shell.cardMuted">Last message</dt>
                    <dd class="mt-1 font-semibold">{{ thread.last_message_at || '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] font-black uppercase tracking-wider" :class="shell.cardMuted">Access state</dt>
                    <dd class="mt-1 font-semibold" :class="thread.is_blocked ? 'text-rose-600' : 'text-emerald-600'">
                        {{ thread.is_blocked ? 'Hidden from users' : 'Visible to users' }}
                    </dd>
                </div>
            </dl>
            <div v-if="thread.is_blocked" class="mt-4 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-semibold text-rose-800 dark:border-rose-400/30 dark:bg-rose-400/10 dark:text-rose-100">
                <p class="font-black">Users no longer have access to this thread.</p>
                <p class="mt-1">{{ thread.admin_visibility_reason || 'No reason recorded.' }}</p>
                <p v-if="thread.admin_visibility_changed_by" class="mt-1 text-xs">Changed by {{ thread.admin_visibility_changed_by.name }}.</p>
            </div>
        </AdminPanel>

        <AdminPanel eyebrow="Thread transcript" :title="`${messages.length} messages`">
            <div class="rounded-3xl border p-4" :class="shell.card">
                <div v-for="group in groupedMessages" :key="group.date" class="space-y-4">
                    <div class="sticky top-2 z-10 mx-auto my-4 w-max rounded-full border px-3 py-1 text-[10px] font-black uppercase tracking-wider" :class="[shell.card, shell.cardMuted]">
                        {{ group.date }}
                    </div>
                    <div v-for="message in group.items" :key="message.id" class="flex" :class="message.user_id === thread.client?.id ? 'justify-start' : 'justify-end'">
                        <article class="max-w-3xl rounded-3xl border px-4 py-3 shadow-sm" :class="message.user_id === thread.client?.id ? 'bg-slate-50 dark:bg-white/5' : 'bg-primary-50 dark:bg-primary-400/10'">
                            <div class="flex flex-wrap items-baseline justify-between gap-3">
                                <p class="text-sm font-black" :class="shell.cardTitle">{{ message.author }}</p>
                                <p class="text-[10px] font-bold uppercase tracking-wider" :class="shell.cardMuted">{{ message.created_at }}</p>
                            </div>
                            <p class="mt-1 text-xs" :class="shell.cardMuted">{{ message.email }}</p>
                            <p class="mt-3 whitespace-pre-wrap text-sm font-semibold leading-6" :class="shell.cardMuted">{{ message.body }}</p>
                        </article>
                    </div>
                </div>
                <p v-if="!messages.length" class="text-sm font-semibold" :class="shell.cardMuted">No messages in this thread yet.</p>
            </div>
        </AdminPanel>
    </AdminShell>
</template>

<script setup>
import AdminPanel from '@/Components/Admin/AdminPanel.vue';
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';
import AdminShell from '@/Layouts/AdminShell.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    thread: { type: Object, required: true },
    messages: { type: Array, default: () => [] },
});

const { shell } = useInjectedAdminTheme();

const groupedMessages = computed(() => {
    const groups = {};
    props.messages.forEach((message) => {
        const date = String(message.created_at || '').split(',')[0] || 'Thread';
        groups[date] ||= [];
        groups[date].push(message);
    });

    return Object.entries(groups).map(([date, items]) => ({ date, items }));
});

function updateVisibility(action) {
    const reason = action === 'restore'
        ? null
        : window.prompt(action === 'delete' ? 'Reason for deleting user access to this thread?' : 'Reason for hiding this thread from users?');

    if (action !== 'restore' && (!reason || reason.trim().length < 10)) {
        window.alert('Please provide a reason of at least 10 characters.');
        return;
    }

    router.post(route('admin.management.conversation_threads.visibility', props.thread.id), { action, reason }, { preserveScroll: true });
}
</script>
