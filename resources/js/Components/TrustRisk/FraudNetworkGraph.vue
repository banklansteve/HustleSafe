<template>
    <div class="relative">
        <div class="mb-3 flex flex-wrap gap-2">
            <button type="button" class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-[10px] font-black uppercase text-slate-700" @click="focusHighestRisk">Focus highest risk</button>
            <button type="button" class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-[10px] font-black uppercase text-slate-700" @click="fit">Fit view</button>
        </div>
        <div ref="container" class="h-[min(420px,55vh)] w-full rounded-2xl border border-slate-200 bg-slate-50" />
        <p v-if="loading" class="absolute inset-0 flex items-center justify-center rounded-2xl bg-white/70 text-sm font-semibold text-slate-600">Loading network…</p>
        <p v-if="!loading && !graph?.nodes?.length" class="mt-2 text-sm font-semibold text-slate-500">No linked accounts detected for this user.</p>
        <ul v-if="graph?.clusters?.length" class="mt-3 flex flex-wrap gap-2">
            <li v-for="c in graph.clusters" :key="c.id" class="rounded-full bg-rose-50 px-2 py-1 text-[10px] font-black uppercase text-rose-800">{{ c.label }} · {{ c.size }}</li>
        </ul>
    </div>
</template>

<script setup>
import cytoscape from 'cytoscape';
import { onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    graph: { type: Object, default: null },
    loading: { type: Boolean, default: false },
});

const emit = defineEmits(['node-select']);

const container = ref(null);
let cy = null;

const tierColor = {
    low: '#22c55e',
    medium: '#f59e0b',
    high: '#f97316',
    critical: '#ef4444',
};

function destroyCy() {
    if (cy) {
        cy.destroy();
        cy = null;
    }
}

function renderGraph() {
    destroyCy();
    if (!container.value || !props.graph?.nodes?.length) {
        return;
    }

    const elements = [];
    for (const n of props.graph.nodes) {
        elements.push({
            data: {
                id: n.id,
                label: n.label,
                score: n.score,
                tier: n.tier,
                userId: n.user_id,
            },
        });
    }
    for (const e of props.graph.edges || []) {
        elements.push({
            data: {
                id: `${e.source}-${e.target}-${e.type}`,
                source: String(e.source),
                target: String(e.target),
                label: `${e.label}${e.first_seen ? ` · ${formatDate(e.first_seen)}` : ''}`,
                type: e.type,
            },
        });
    }

    cy = cytoscape({
        container: container.value,
        elements,
        style: [
            {
                selector: 'node',
                style: {
                    label: 'data(label)',
                    'font-size': 9,
                    'text-valign': 'bottom',
                    'text-margin-y': 6,
                    width: 'data(score)',
                    height: 'data(score)',
                    'background-color': (ele) => tierColor[ele.data('tier')] || '#94a3b8',
                    'border-width': (ele) => (ele.data('userId') === props.graph.center_user_id ? 3 : 1),
                    'border-color': '#0f172a',
                },
            },
            {
                selector: 'edge',
                style: {
                    width: 2,
                    'line-color': '#cbd5e1',
                    'target-arrow-color': '#cbd5e1',
                    'target-arrow-shape': 'triangle',
                    'curve-style': 'bezier',
                    label: 'data(label)',
                    'font-size': 8,
                    color: '#64748b',
                },
            },
        ],
        layout: { name: 'cose', animate: false, padding: 24 },
        wheelSensitivity: 0.2,
    });

    cy.nodes().forEach((node) => {
        const score = Number(node.data('score')) || 0;
        node.style('width', Math.max(24, Math.min(64, 20 + score * 0.4)));
        node.style('height', Math.max(24, Math.min(64, 20 + score * 0.4)));
    });

    cy.on('tap', 'node', (evt) => {
        const userId = Number(evt.target.data('userId'));
        if (userId) {
            emit('node-select', userId);
        }
    });
}

function focusHighestRisk() {
    if (!cy) return;
    const best = cy.nodes().sort((a, b) => (Number(b.data('score')) || 0) - (Number(a.data('score')) || 0))[0];
    if (best) {
        cy.animate({ center: { eles: best }, zoom: 1.4 }, { duration: 300 });
        emit('node-select', Number(best.data('userId')));
    }
}

function fit() {
    cy?.fit(undefined, 40);
}

function formatDate(iso) {
    try {
        return new Date(iso).toLocaleDateString();
    } catch {
        return '';
    }
}

watch(() => props.graph, () => renderGraph(), { deep: true });
onBeforeUnmount(destroyCy);

defineExpose({ fit, focusHighestRisk });
</script>
