<template>
    <component :is="shellComponent" title="Create support ticket" :subtitle="shellSubtitle">
        <div class="mx-auto max-w-6xl space-y-6">
            <section class="rounded-3xl border border-slate-200 bg-gradient-to-br from-white via-slate-50 to-primary-50/30 p-5 shadow-sm">
                <p class="text-xs font-black uppercase tracking-[0.2em] text-primary-700">Ticket creation</p>
                <h2 class="mt-1 text-2xl font-black text-slate-950">Log a customer support request</h2>
                <p class="mt-2 max-w-3xl text-sm font-semibold text-slate-600">Search the customer, capture the issue, and generate a dated reference on submit. Expected resolution defaults to 10 working days.</p>
            </section>

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
                <form class="space-y-5 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm" @submit.prevent="submit">
                    <div>
                        <label class="text-xs font-black uppercase tracking-wide text-slate-500">Customer search</label>
                        <input v-model="customerQuery" type="search" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-semibold" placeholder="Name, email, or username" @input="searchCustomers" />
                        <div v-if="customerResults.length" class="mt-2 overflow-hidden rounded-xl border border-slate-200">
                            <button v-for="customer in customerResults" :key="customer.id" type="button" class="flex w-full items-start justify-between gap-3 border-b border-slate-100 px-3 py-2.5 text-left last:border-b-0 hover:bg-primary-50" @click="selectCustomer(customer)">
                                <span>
                                    <span class="block text-sm font-black text-slate-900">{{ customer.name }}</span>
                                    <span class="block text-xs font-semibold text-slate-500">{{ customer.email }} · @{{ customer.username }}</span>
                                </span>
                                <span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-black uppercase text-slate-600">{{ customer.status }}</span>
                            </button>
                        </div>
                        <p v-if="form.errors.user_id" class="mt-2 text-sm font-semibold text-rose-700">{{ form.errors.user_id }}</p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="text-xs font-black uppercase tracking-wide text-slate-500">Issue group</label>
                            <select v-model="form.issue_group" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-semibold">
                                <option value="">Select category</option>
                                <option v-for="group in issueGroups" :key="group.key" :value="group.key">{{ group.label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-black uppercase tracking-wide text-slate-500">Priority</label>
                            <select v-model="form.priority" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-semibold">
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="text-xs font-black uppercase tracking-wide text-slate-500">Subject</label>
                        <input v-model="form.subject" type="text" maxlength="180" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-semibold" placeholder="Short summary of the issue" />
                    </div>

                    <div>
                        <label class="text-xs font-black uppercase tracking-wide text-slate-500">Issue description</label>
                        <QuestRichDescriptionEditor v-model="form.description" class="mt-2" placeholder="Describe the reported problem in detail." />
                    </div>

                    <div>
                        <label class="text-xs font-black uppercase tracking-wide text-slate-500">Actions to be taken</label>
                        <div class="mt-2 space-y-2">
                            <div v-for="(item, index) in form.action_items" :key="item.id" class="flex gap-2">
                                <input v-model="item.label" type="text" class="flex-1 rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" placeholder="Planned action step" />
                                <button type="button" class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-black uppercase text-slate-600" @click="removeAction(index)">Remove</button>
                            </div>
                            <button type="button" class="rounded-xl border border-dashed border-primary-300 px-3 py-2 text-xs font-black uppercase text-primary-800" @click="addAction">Add action</button>
                        </div>
                    </div>

                    <div>
                        <label class="text-xs font-black uppercase tracking-wide text-slate-500">Internal notes</label>
                        <textarea v-model="form.internal_notes" rows="4" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" placeholder="Operational context — not visible to the customer." />
                    </div>

                    <div>
                        <label class="text-xs font-black uppercase tracking-wide text-slate-500">Attachments (max 5, 5MB each)</label>
                        <input type="file" multiple class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold" @change="onFiles" />
                    </div>

                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl bg-primary-700 px-5 py-3 text-sm font-black text-white disabled:opacity-60" :disabled="form.processing || !selectedCustomer">
                        Create ticket
                    </button>
                </form>

                <aside v-if="selectedCustomer" class="h-fit rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-black uppercase tracking-wide text-slate-500">Customer context</p>
                    <h3 class="mt-2 text-xl font-black text-slate-950">{{ selectedCustomer.name }}</h3>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div><dt class="font-black uppercase tracking-wide text-slate-500">Email</dt><dd class="font-semibold text-slate-800">{{ selectedCustomer.email }}</dd></div>
                        <div><dt class="font-black uppercase tracking-wide text-slate-500">Username</dt><dd class="font-semibold text-slate-800">@{{ selectedCustomer.username }}</dd></div>
                        <div><dt class="font-black uppercase tracking-wide text-slate-500">User type</dt><dd class="font-semibold text-slate-800">{{ selectedCustomer.role }}</dd></div>
                        <div><dt class="font-black uppercase tracking-wide text-slate-500">Account status</dt><dd class="font-semibold text-slate-800">{{ selectedCustomer.status }}</dd></div>
                        <div><dt class="font-black uppercase tracking-wide text-slate-500">Joined</dt><dd class="font-semibold text-slate-800">{{ formatDate(selectedCustomer.joined_at) }}</dd></div>
                    </dl>
                </aside>
            </div>
        </div>
    </component>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AdminShell from '@/Layouts/AdminShell.vue';
import OperationsShell from '@/Layouts/OperationsShell.vue';
import QuestRichDescriptionEditor from '@/Components/Quests/QuestRichDescriptionEditor.vue';
import { formatLeaveDateTime } from '@/utils/formatHumanDateTime';

const props = defineProps({
    issueGroups: { type: Array, default: () => [] },
    routePrefix: { type: String, default: 'admin' },
    prefillCustomerId: { type: Number, default: null },
    prefillCustomer: { type: Object, default: null },
});

const shellComponent = computed(() => (props.routePrefix === 'admin' ? AdminShell : OperationsShell));
const shellSubtitle = computed(() => (props.routePrefix === 'admin' ? 'Super Admin · Support operations' : 'Staff Admin · Support operations'));

const customerQuery = ref('');
const customerResults = ref([]);
const selectedCustomer = ref(null);
let searchTimer = null;

const form = useForm({
    user_id: props.prefillCustomerId || '',
    subject: '',
    issue_group: '',
    priority: 'medium',
    description: '',
    internal_notes: '',
    action_items: [{ id: crypto.randomUUID(), label: '', completed: false }],
    attachments: [],
});

function routeName(name) {
    return `${props.routePrefix}.${name}`;
}

function searchCustomers() {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(async () => {
        if (customerQuery.value.trim().length < 2) {
            customerResults.value = [];
            return;
        }
        const { data } = await window.axios.get(route(routeName('support-tickets.customers.search')), { params: { q: customerQuery.value } });
        customerResults.value = data.items ?? [];
    }, 250);
}

function selectCustomer(customer) {
    selectedCustomer.value = customer;
    form.user_id = customer.id;
    customerResults.value = [];
    customerQuery.value = customer.name;
}

function addAction() {
    form.action_items.push({ id: crypto.randomUUID(), label: '', completed: false });
}

function removeAction(index) {
    form.action_items.splice(index, 1);
}

function onFiles(event) {
    form.attachments = Array.from(event.target.files ?? []).slice(0, 5);
}

function formatDate(value) {
    return value ? formatLeaveDateTime(value) : '—';
}

function submit() {
    form.transform((data) => ({
        ...data,
        action_items: data.action_items.filter((item) => item.label.trim() !== ''),
    })).post(route(routeName('support-tickets.store')), {
        forceFormData: true,
    });
}

onMounted(() => {
    if (props.prefillCustomer) {
        selectCustomer(props.prefillCustomer);
    }
});
</script>
