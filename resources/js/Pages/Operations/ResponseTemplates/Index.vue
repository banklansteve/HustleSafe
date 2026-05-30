<template>
    <component :is="shellComponent" title="Response template library" subtitle="Approved outreach templates — select and personalise when working the proactive queue.">
        <div class="mb-4 flex flex-wrap gap-2">
            <button type="button" class="rounded-lg bg-primary-700 px-4 py-2 text-xs font-black uppercase text-white shadow-md active:scale-[0.98]" @click="openCreate">New template</button>
            <Link :href="outreachHref" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-xs font-black uppercase text-slate-700 active:scale-[0.98]">Back to outreach queue</Link>
        </div>

        <div v-if="loading" class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">Loading templates…</div>

        <div v-else class="space-y-3">
            <article v-for="item in items" :key="item.id" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-black uppercase text-primary-700">{{ item.situation_label || item.situation_key }}</p>
                        <h3 class="mt-1 text-lg font-black text-slate-950">{{ item.title }}</h3>
                        <p class="mt-1 text-sm font-semibold text-slate-600">Subject: {{ item.subject }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <span class="rounded-full px-2 py-0.5 text-[10px] font-black uppercase" :class="item.is_active ? 'bg-emerald-100 text-emerald-900' : 'bg-slate-100 text-slate-600'">{{ item.is_active ? 'Active' : 'Inactive' }}</span>
                        <button type="button" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-black uppercase text-slate-700 active:scale-[0.98]" @click="openEdit(item)">Edit</button>
                    </div>
                </div>
                <pre class="mt-3 whitespace-pre-wrap rounded-xl bg-slate-50 p-3 text-sm leading-6 text-slate-700">{{ item.body }}</pre>
                <div v-if="item.policy_tags?.length" class="mt-2 flex flex-wrap gap-1">
                    <span v-for="tag in item.policy_tags" :key="tag" class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase text-slate-600">{{ tag }}</span>
                </div>
            </article>
        </div>

        <OperationsSlideOver :open="editorOpen" :title="editor.id ? 'Edit template' : 'New template'" subtitle="Use placeholders like :name, :quest_title, :freelancer_name" eyebrow="Template library" @close="editorOpen = false">
            <form class="space-y-3" @submit.prevent="saveTemplate">
                <OperationsFormField v-model="editor.title" label="Admin title" required />
                <label class="block text-xs font-bold text-slate-600">
                    Situation
                    <select v-model="editor.situation_key" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-semibold text-slate-900">
                        <option v-for="s in situations" :key="s.key" :value="s.key">{{ s.label }}</option>
                    </select>
                </label>
                <OperationsFormField v-model="editor.category" label="Category" required />
                <OperationsFormField v-model="editor.subject" label="Email subject" required />
                <OperationsFormField v-model="editor.body" label="Body" multiline :rows="10" required />
                <OperationsFormField v-model="editor.policy_tags_text" label="Policy tags (comma-separated)" />
                <label class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                    <input v-model="editor.is_active" type="checkbox" class="rounded border-slate-300 text-primary-700" />
                    Active
                </label>
                <button type="submit" class="w-full rounded-xl bg-primary-700 py-2.5 text-sm font-black text-white active:scale-[0.98]" :disabled="busy.save">{{ editor.id ? 'Save changes' : 'Create template' }}</button>
            </form>
        </OperationsSlideOver>
    </component>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AdminShell from '@/Layouts/AdminShell.vue';
import OperationsFormField from '@/Pages/Operations/Components/OperationsFormField.vue';
import OperationsSlideOver from '@/Pages/Operations/Components/OperationsSlideOver.vue';
import OperationsShell from '@/Layouts/OperationsShell.vue';
import { useOperationsAction } from '@/composables/useOperationsAction';

const props = defineProps({
    situations: { type: Array, default: () => [] },
    route_prefix: { type: String, default: 'operations' },
    use_admin_shell: { type: Boolean, default: false },
});

const shellComponent = computed(() => (props.use_admin_shell ? AdminShell : OperationsShell));
const api = (name, params) => route(`${props.route_prefix}.${name}`, params);
const outreachHref = computed(() => route(`${props.route_prefix}.outreach.index`));

const items = ref([]);
const loading = ref(false);
const editorOpen = ref(false);
const editor = reactive({
    id: null,
    title: '',
    situation_key: props.situations[0]?.key ?? '',
    category: 'retention',
    subject: '',
    body: '',
    policy_tags_text: '',
    is_active: true,
});
const { busy, runAction } = useOperationsAction();

onMounted(reload);

async function reload() {
    loading.value = true;
    try {
        const { data } = await window.axios.get(api('api.response-templates.listing'));
        items.value = data.items ?? [];
    } finally {
        loading.value = false;
    }
}

function resetEditor() {
    editor.id = null;
    editor.title = '';
    editor.situation_key = props.situations[0]?.key ?? '';
    editor.category = 'retention';
    editor.subject = '';
    editor.body = '';
    editor.policy_tags_text = '';
    editor.is_active = true;
}

function openCreate() {
    resetEditor();
    editorOpen.value = true;
}

function openEdit(item) {
    editor.id = item.id;
    editor.title = item.title;
    editor.situation_key = item.situation_key;
    editor.category = item.category;
    editor.subject = item.subject;
    editor.body = item.body;
    editor.policy_tags_text = (item.policy_tags ?? []).join(', ');
    editor.is_active = item.is_active;
    editorOpen.value = true;
}

async function saveTemplate() {
    const payload = {
        title: editor.title,
        situation_key: editor.situation_key,
        category: editor.category,
        subject: editor.subject,
        body: editor.body,
        is_active: editor.is_active,
        policy_tags: editor.policy_tags_text.split(',').map((t) => t.trim()).filter(Boolean),
    };

    const action = editor.id
        ? () => window.axios.patch(api('api.response-templates.update', editor.id), payload)
        : () => window.axios.post(api('api.response-templates.store'), payload);

    await runAction('save', action, editor.id ? 'Template updated.' : 'Template created.', () => {
        editorOpen.value = false;
        reload();
    });
}
</script>
