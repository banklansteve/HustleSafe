<template>
    <AdminShell
        :title="user.name"
        subtitle="Activity stream, sign-ins, and admin audit touches for this account."
    >
        <AdminPanel eyebrow="Account" title="Profile snapshot">
            <template #actions>
                <Link
                    :href="route('admin.management.index', { resource: 'users' })"
                    class="rounded-xl border px-4 py-2 text-xs font-black uppercase tracking-wide"
                    :class="shell.btnGhost"
                >
                    Back to users
                </Link>
            </template>

            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-[10px] font-black uppercase tracking-wider" :class="shell.cardMuted">Email</dt>
                    <dd class="mt-1 font-semibold">{{ user.email }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] font-black uppercase tracking-wider" :class="shell.cardMuted">Role</dt>
                    <dd class="mt-1 font-semibold capitalize">{{ (user.role_slug || '—').replace(/_/g, ' ') }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] font-black uppercase tracking-wider" :class="shell.cardMuted">Status</dt>
                    <dd class="mt-1 font-semibold" :class="user.suspended_at ? 'text-amber-600' : 'text-emerald-600'">
                        {{ user.suspended_at ? 'Suspended' : 'Active' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-[10px] font-black uppercase tracking-wider" :class="shell.cardMuted">Last active</dt>
                    <dd class="mt-1 font-semibold">{{ formatWhen(user.last_active_at) }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] font-black uppercase tracking-wider" :class="shell.cardMuted">Joined</dt>
                    <dd class="mt-1 font-semibold">{{ formatWhen(user.created_at) }}</dd>
                </div>
            </dl>
        </AdminPanel>

        <div class="grid gap-2 xl:grid-cols-3">
            <AdminPanel eyebrow="Timeline" :title="`Activity (${activity.length})`" class="xl:col-span-2">
                <ul v-if="activity.length" class="space-y-3">
                    <li
                        v-for="item in activity"
                        :key="item.id"
                        class="rounded-xl border px-4 py-3"
                        :class="shell.card"
                    >
                        <p class="text-xs font-black uppercase tracking-wide text-teal-600 dark:text-teal-300">
                            {{ item.type }}
                        </p>
                        <p class="mt-1 font-bold">{{ item.title }}</p>
                        <p v-if="item.body" class="mt-1 text-sm font-semibold" :class="shell.cardMuted">{{ item.body }}</p>
                        <p class="mt-2 text-[10px] font-bold uppercase tracking-wider" :class="shell.cardMuted">
                            {{ formatWhen(item.created_at) }}
                        </p>
                    </li>
                </ul>
                <p v-else class="text-sm font-semibold" :class="shell.cardMuted">No activity logged yet.</p>
            </AdminPanel>

            <div class="space-y-2">
                <AdminPanel eyebrow="Security" :title="`Sign-ins (${logins.length})`">
                    <ul v-if="logins.length" class="max-h-80 space-y-2 overflow-y-auto">
                        <li
                            v-for="login in logins"
                            :key="login.id"
                            class="rounded-xl border px-3 py-2 text-xs"
                            :class="shell.card"
                        >
                            <p class="font-bold">{{ formatWhen(login.logged_in_at) }}</p>
                            <p class="mt-1 font-mono" :class="shell.cardMuted">{{ login.ip_address || '—' }}</p>
                        </li>
                    </ul>
                    <p v-else class="text-sm font-semibold" :class="shell.cardMuted">No login events.</p>
                </AdminPanel>

                <AdminPanel eyebrow="Admin audit" :title="`Console actions (${admin_audit.length})`">
                    <ul v-if="admin_audit.length" class="max-h-80 space-y-2 overflow-y-auto">
                        <li
                            v-for="entry in admin_audit"
                            :key="entry.id"
                            class="rounded-xl border px-3 py-2 text-xs"
                            :class="shell.card"
                        >
                            <p class="font-bold">{{ entry.action }}</p>
                            <p class="mt-1" :class="shell.cardMuted">
                                {{ entry.actor?.name || 'System' }}
                                <span v-if="entry.actor?.email"> · {{ entry.actor.email }}</span>
                            </p>
                            <p class="mt-1 font-mono text-[10px]" :class="shell.cardMuted">{{ formatWhen(entry.created_at) }}</p>
                        </li>
                    </ul>
                    <p v-else class="text-sm font-semibold" :class="shell.cardMuted">No admin audit entries for this user.</p>
                </AdminPanel>
            </div>
        </div>
    </AdminShell>
</template>

<script setup>
import AdminPanel from '@/Components/Admin/AdminPanel.vue';
import { useInjectedAdminTheme } from '@/composables/useAdminTheme';
import AdminShell from '@/Layouts/AdminShell.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    user: { type: Object, required: true },
    activity: { type: Array, default: () => [] },
    logins: { type: Array, default: () => [] },
    admin_audit: { type: Array, default: () => [] },
});

const { shell } = useInjectedAdminTheme();

function formatWhen(value) {
    if (!value) {
        return '—';
    }

    try {
        return new Date(value).toLocaleString('en-NG', { dateStyle: 'medium', timeStyle: 'short' });
    } catch {
        return String(value);
    }
}
</script>
