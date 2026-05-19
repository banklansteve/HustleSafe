<template>
    <AdminShell
        title="Platform Insights"
        subtitle="Premium-grade intelligence for GMV, revenue, conversion, trust, geography, escrow, payouts, and retention."
    >
        <div class="space-y-6">
            <section class="grid gap-4 xl:grid-cols-[0.9fr_1.6fr_1fr]">
                <InsightCard title="Platform Health Score" eyebrow="Vital sign" class="xl:row-span-2">
                    <GaugeChart :score="insights.health_score.score" :label="insights.health_score.label" />
                    <div class="mt-5 space-y-2">
                        <div v-for="item in insights.health_score.components" :key="item.metric" class="flex items-center justify-between gap-3 rounded-2xl bg-slate-50 px-3 py-2 text-xs font-bold dark:bg-white/5">
                            <span class="text-slate-600 dark:text-slate-300">{{ item.metric }}</span>
                            <span class="text-slate-900 dark:text-white">{{ item.value_label }} · {{ item.weight }}%</span>
                        </div>
                    </div>
                </InsightCard>

                <InsightCard title="Platform GMV Trend" eyebrow="12 month escrow-funded value" class="min-h-[24rem]">
                    <AreaChart :series="insights.gmv_trend.series" />
                </InsightCard>

                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                    <KpiTile v-for="tile in insights.vitals" :key="tile.label" :tile="tile" />
                </div>
            </section>

            <section class="grid gap-4 xl:grid-cols-3">
                <InsightCard title="Revenue Breakdown" eyebrow="6 month stacked revenue">
                    <StackedBarChart :months="insights.revenue_breakdown.months" :streams="insights.revenue_breakdown.streams" />
                </InsightCard>
                <InsightCard title="Escrow Flow Summary" eyebrow="Current month money movement">
                    <SankeyFlow :nodes="insights.escrow_flow.nodes" :inflow="insights.escrow_flow.inflow_minor" />
                </InsightCard>
                <InsightCard title="Payout Success Rate" eyebrow="Operational payment reliability">
                    <PayoutPanel :payload="insights.payouts" />
                </InsightCard>
            </section>

            <section class="grid gap-4 xl:grid-cols-[1fr_1.1fr_1fr]">
                <InsightCard title="Quest Funnel Conversion" eyebrow="Lifecycle drop-off">
                    <FunnelChart :steps="insights.quest_funnel" />
                </InsightCard>
                <InsightCard title="Proposal Activity Heatmap" eyebrow="Last 30 days by WAT rhythm">
                    <ProposalHeatmap :payload="insights.proposal_heatmap" />
                </InsightCard>
                <InsightCard title="New User Registrations" eyebrow="8 week client vs freelancer mix">
                    <GroupedBarChart :rows="insights.user_registrations" />
                </InsightCard>
            </section>

            <section class="grid gap-4 xl:grid-cols-3">
                <InsightCard title="Top Earning Freelancers" eyebrow="Current month supply leaders">
                    <Leaderboard :rows="insights.leaderboards.freelancers" value-label="earned" type="freelancer" />
                </InsightCard>
                <InsightCard title="Top Spending Clients" eyebrow="Current month demand leaders">
                    <Leaderboard :rows="insights.leaderboards.clients" value-label="funded" type="client" />
                </InsightCard>
                <InsightCard title="Geographic Activity Map" eyebrow="Nigerian state intensity">
                    <GeoMap :states="insights.geo.states" />
                </InsightCard>
            </section>

            <section class="grid gap-4 xl:grid-cols-2">
                <InsightCard title="Dispute Rate & Outcomes" eyebrow="12 month risk signal">
                    <DisputeCombo :months="insights.disputes.months" />
                </InsightCard>
                <InsightCard title="Verification Tier Distribution" eyebrow="Trust depth across users">
                    <DonutLegend :payload="insights.verification_distribution" />
                </InsightCard>
            </section>

            <section class="grid gap-4 xl:grid-cols-[1.1fr_1fr]">
                <InsightCard title="Quest Category Performance Heatmap" eyebrow="Volume, fill, budget, proposal and dispute pressure">
                    <CategoryHeatmap :payload="insights.category_heatmap" />
                </InsightCard>
                <InsightCard title="User Retention Cohort Analysis" eyebrow="Month-over-month stickiness">
                    <CohortTable :payload="insights.retention" />
                </InsightCard>
            </section>
        </div>
    </AdminShell>
</template>

<script setup>
import AdminShell from '@/Layouts/AdminShell.vue';
import { computed, defineComponent, h, ref } from 'vue';

const props = defineProps({
    insights: { type: Object, required: true },
});

const money = (minor) => `₦${Number((minor || 0) / 100).toLocaleString('en-NG', { maximumFractionDigits: 0 })}`;
const maxOf = (rows, keys) => Math.max(1, ...rows.flatMap((row) => keys.map((key) => Number(row[key] || 0))));
const pct = (value, total) => (total > 0 ? Math.round((value / total) * 100) : 0);
const sum = (values) => values.reduce((carry, value) => carry + Number(value || 0), 0);
const isZeroValue = (value) => value === 0 || value === '0' || String(value || '').startsWith('₦0');

const InsightCard = defineComponent({
    props: { title: String, eyebrow: String },
    setup(cardProps, { slots }) {
        return () => h('article', { class: 'rounded-3xl border border-slate-200 bg-white p-5 shadow-xl shadow-slate-200/60 ring-1 ring-slate-100 dark:border-white/10 dark:bg-slate-900/70 dark:shadow-black/20 dark:ring-white/5' }, [
            h('div', { class: 'mb-4 flex items-start justify-between gap-3' }, [
                h('div', [
                    h('p', { class: 'text-[10px] font-black uppercase tracking-[0.22em] text-primary-600 dark:text-primary-300' }, cardProps.eyebrow),
                    h('h2', { class: 'mt-1 font-display text-lg font-black text-slate-950 dark:text-white' }, cardProps.title),
                ]),
            ]),
            slots.default?.(),
        ]);
    },
});

const KpiTile = defineComponent({
    props: { tile: Object },
    setup(tileProps) {
        const tone = {
            primary: 'from-primary-500/20 to-primary-50 text-primary-700 dark:to-primary-950/30 dark:text-primary-100',
            blue: 'from-blue-500/20 to-blue-50 text-blue-700 dark:to-blue-950/30 dark:text-blue-100',
            emerald: 'from-emerald-500/20 to-emerald-50 text-emerald-700 dark:to-emerald-950/30 dark:text-emerald-100',
            amber: 'from-amber-500/20 to-amber-50 text-amber-700 dark:to-amber-950/30 dark:text-amber-100',
        }[tileProps.tile.tone] || 'from-slate-100 to-white text-slate-900';
        return () => h('div', { class: `rounded-3xl border border-slate-200 bg-gradient-to-br p-4 shadow-lg shadow-slate-200/60 dark:border-white/10 dark:shadow-black/20 ${tone}` }, [
            h('p', { class: 'text-[10px] font-black uppercase tracking-[0.22em] opacity-70' }, tileProps.tile.label),
            h('p', { class: 'mt-3 font-display text-2xl font-black' }, tileProps.tile.value),
            isZeroValue(tileProps.tile.value) ? h('p', { class: 'mt-2 text-xs font-bold opacity-70' }, `0 / You currently have no ${String(tileProps.tile.label).toLowerCase()} recorded yet.`) : null,
        ]);
    },
});

const GaugeChart = defineComponent({
    props: { score: Number, label: String },
    setup(gaugeProps) {
        const score = computed(() => Math.max(0, Math.min(100, Number(gaugeProps.score || 0))));
        const radius = 82;
        const circumference = 2 * Math.PI * radius;
        const stroke = computed(() => {
            if (score.value < 40) {
                return '#ef4444';
            }

            if (score.value < 70) {
                return '#f59e0b';
            }

            return '#22c55e';
        });
        const insight = computed(() => {
            if (score.value < 40) {
                return 'Critical attention needed';
            }

            if (score.value < 70) {
                return 'Improving, but still fragile';
            }

            return 'Healthy operating range';
        });

        return () => h('div', { class: 'space-y-4' }, [
            h('div', { class: 'relative mx-auto h-64 w-64' }, [
                h('svg', { viewBox: '0 0 220 220', class: 'h-full w-full -rotate-90' }, [
                    h('circle', {
                        cx: '110',
                        cy: '110',
                        r: radius,
                        fill: 'none',
                        stroke: 'currentColor',
                        'stroke-width': '18',
                        class: 'text-slate-100 dark:text-white/10',
                    }),
                    h('circle', {
                        cx: '110',
                        cy: '110',
                        r: radius,
                        fill: 'none',
                        stroke: stroke.value,
                        'stroke-width': '18',
                        'stroke-linecap': 'round',
                        'stroke-dasharray': circumference,
                        'stroke-dashoffset': circumference - (score.value / 100) * circumference,
                        style: 'transition: stroke-dashoffset .5s ease, stroke .3s ease;',
                    }),
                    h('circle', {
                        cx: '110',
                        cy: '110',
                        r: '3',
                        fill: '#94a3b8',
                        transform: 'rotate(54 110 110) translate(0 -82)',
                    }),
                    h('circle', {
                        cx: '110',
                        cy: '110',
                        r: '3',
                        fill: '#94a3b8',
                        transform: 'rotate(162 110 110) translate(0 -82)',
                    }),
                ]),
                h('div', { class: 'absolute inset-0 flex flex-col items-center justify-center text-center' }, [
                    h('p', { class: 'font-display text-5xl font-black text-slate-950 dark:text-white' }, Math.round(score.value)),
                    h('p', { class: 'mt-1 text-xs font-black uppercase tracking-[0.22em] text-slate-500' }, 'out of 100'),
                    h('p', { class: 'mt-3 rounded-full px-3 py-1 text-xs font-black uppercase tracking-wide', style: `background:${stroke.value}22; color:${stroke.value};` }, gaugeProps.label),
                ]),
            ]),
            h('div', { class: 'rounded-2xl border border-slate-200 bg-slate-50 p-3 text-center dark:border-white/10 dark:bg-white/5' }, [
                h('p', { class: 'text-sm font-black text-slate-900 dark:text-white' }, insight.value),
                h('p', { class: 'mt-1 text-xs font-semibold text-slate-500 dark:text-slate-400' }, 'The ring shows the current composite platform score. Markers indicate the 40 and 70 point thresholds.'),
            ]),
            h('div', { class: 'grid grid-cols-3 gap-2 text-center text-[10px] font-black uppercase tracking-wide' }, [
                h('div', { class: 'rounded-xl bg-rose-50 px-2 py-2 text-rose-700 dark:bg-rose-500/10 dark:text-rose-200' }, '0-39 Poor'),
                h('div', { class: 'rounded-xl bg-amber-50 px-2 py-2 text-amber-700 dark:bg-amber-500/10 dark:text-amber-200' }, '40-69 Fair'),
                h('div', { class: 'rounded-xl bg-emerald-50 px-2 py-2 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-200' }, '70-100 Healthy'),
            ]),
        ]);
    },
});

const AreaChart = defineComponent({
    props: { series: Array },
    setup(chartProps) {
        const hovered = ref(null);
        const chart = computed(() => {
            const rows = chartProps.series || [];
            const max = Math.max(1, ...rows.map((row) => Math.max(row.value_minor, row.previous_year_minor)));
            const points = rows.map((row, index) => {
                const x = 28 + index * (344 / Math.max(1, rows.length - 1));
                const y = 190 - (row.value_minor / max) * 150;
                const py = 190 - (row.previous_year_minor / max) * 150;
                return { ...row, x, y, py };
            });
            return {
                points,
                line: points.map((p) => `${p.x},${p.y}`).join(' '),
                previous: points.map((p) => `${p.x},${p.py}`).join(' '),
                area: `28,190 ${points.map((p) => `${p.x},${p.y}`).join(' ')} 372,190`,
                latest: points[points.length - 1],
            };
        });
        return () => h('div', { class: 'h-72' }, [
            chart.value.points.every((point) => Number(point.value_minor || 0) === 0)
                ? h('div', { class: 'mb-3 rounded-2xl bg-slate-50 p-3 text-sm font-bold text-slate-500 dark:bg-white/5' }, '0 / No escrow-funded GMV has been recorded for this period yet.')
                : null,
            h('svg', { viewBox: '0 0 400 240', class: 'h-full w-full overflow-visible' }, [
                h('defs', [h('linearGradient', { id: 'gmvGradient', x1: '0', x2: '0', y1: '0', y2: '1' }, [
                    h('stop', { offset: '0%', 'stop-color': 'rgb(14 165 233)', 'stop-opacity': '.35' }),
                    h('stop', { offset: '100%', 'stop-color': 'rgb(14 165 233)', 'stop-opacity': '.02' }),
                ])]),
                h('polygon', { points: chart.value.area, fill: 'url(#gmvGradient)' }),
                h('polyline', { points: chart.value.previous, fill: 'none', stroke: '#94a3b8', 'stroke-dasharray': '5 5', 'stroke-width': '2' }),
                h('polyline', { points: chart.value.line, fill: 'none', stroke: 'rgb(14 165 233)', 'stroke-width': '4', 'stroke-linecap': 'round', 'stroke-linejoin': 'round' }),
                ...chart.value.points.map((point) => h('circle', { cx: point.x, cy: point.y, r: point === chart.value.latest ? 6 : 4, fill: point === chart.value.latest ? '#0f172a' : 'rgb(14 165 233)', stroke: 'white', 'stroke-width': '2', onMouseenter: () => { hovered.value = point; }, onMouseleave: () => { hovered.value = null; } })),
                chart.value.latest && h('text', { x: chart.value.latest.x - 58, y: chart.value.latest.y - 14, class: 'fill-slate-900 text-[11px] font-black dark:fill-white' }, money(chart.value.latest.value_minor)),
            ]),
            h('div', { class: 'mt-1 flex justify-between text-[10px] font-black uppercase tracking-wide text-slate-500' }, (chartProps.series || []).map((row) => h('span', row.label))),
            hovered.value && h('p', { class: 'mt-2 rounded-xl bg-slate-100 px-3 py-2 text-xs font-bold text-slate-700 dark:bg-white/10 dark:text-slate-200' }, `${hovered.value.label}: ${money(hovered.value.value_minor)} GMV · previous year ${money(hovered.value.previous_year_minor)}`),
        ]);
    },
});

const StackedBarChart = defineComponent({
    props: { months: Array, streams: Object },
    setup(barProps) {
        const keys = ['service_fees', 'featured_listings', 'dispute_fees', 'other'];
        const colors = ['bg-primary-500', 'bg-secondary-500', 'bg-rose-500', 'bg-slate-400'];
        const max = computed(() => Math.max(1, ...barProps.months.map((row) => sum(keys.map((key) => row[key])))));
        return () => h('div', [
            barProps.months.every((row) => sum(keys.map((key) => row[key])) === 0)
                ? h('div', { class: 'mb-3 rounded-2xl bg-slate-50 p-3 text-sm font-bold text-slate-500 dark:bg-white/5' }, '0 / You currently have no revenue recorded for this period.')
                : null,
            h('div', { class: 'flex h-64 items-end gap-3' }, barProps.months.map((row) => h('div', { class: 'flex flex-1 flex-col items-center gap-2' }, [
                h('div', { class: 'flex h-52 w-full max-w-12 flex-col justify-end overflow-hidden rounded-t-2xl bg-slate-100 dark:bg-white/10' }, keys.map((key, index) => h('div', { class: colors[index], title: `${barProps.streams[key]} ${money(row[key])}`, style: `height:${Math.max(2, (row[key] / max.value) * 100)}%` }))),
                h('span', { class: 'text-xs font-black text-slate-500' }, row.label),
            ]))),
            h('div', { class: 'mt-4 flex flex-wrap gap-2' }, keys.map((key, index) => h('span', { class: 'inline-flex items-center gap-2 text-xs font-bold text-slate-600 dark:text-slate-300' }, [
                h('i', { class: `h-2.5 w-2.5 rounded-full ${colors[index]}` }),
                barProps.streams[key],
            ]))),
        ]);
    },
});

const FunnelChart = defineComponent({
    props: { steps: Array },
    setup(funnelProps) {
        const max = computed(() => Math.max(1, ...funnelProps.steps.map((step) => step.count)));
        return () => h('div', { class: 'space-y-2' }, funnelProps.steps.map((step, index) => h('div', [
            index === 0 && funnelProps.steps.every((item) => Number(item.count || 0) === 0)
                ? h('div', { class: 'mb-3 rounded-2xl bg-slate-50 p-3 text-sm font-bold text-slate-500 dark:bg-white/5' }, '0 / No Quest lifecycle activity has been recorded yet.')
                : null,
            h('div', { class: 'flex items-center gap-3' }, [
                h('div', { class: 'rounded-2xl bg-gradient-to-r from-primary-500 to-primary-700 px-4 py-3 text-white shadow-lg shadow-primary-900/10', style: `width:${Math.max(34, (step.count / max.value) * 100)}%` }, [
                    h('div', { class: 'flex justify-between gap-3 text-sm font-black' }, [h('span', step.label), h('span', Number(step.count).toLocaleString())]),
                ]),
                h('span', { class: 'w-20 text-right text-xs font-black text-slate-500' }, `${step.conversion_rate}%`),
            ]),
            index > 0 && h('p', { class: 'ml-4 py-1 text-[10px] font-black uppercase tracking-wide text-rose-500' }, `${step.dropoff_rate}% lost from previous stage`),
        ])));
    },
});

const GroupedBarChart = defineComponent({
    props: { rows: Array },
    setup(groupProps) {
        const max = computed(() => maxOf(groupProps.rows, ['clients', 'freelancers']));
        return () => h('div', [
            groupProps.rows.every((row) => Number(row.clients || 0) + Number(row.freelancers || 0) === 0)
                ? h('div', { class: 'mb-3 rounded-2xl bg-slate-50 p-3 text-sm font-bold text-slate-500 dark:bg-white/5' }, '0 / No new client or freelancer registrations in this window.')
                : null,
            h('div', { class: 'flex h-64 items-end gap-3' }, groupProps.rows.map((row) => h('div', { class: 'flex flex-1 flex-col items-center gap-2' }, [
                h('div', { class: 'flex h-52 items-end gap-1.5' }, [
                    h('div', { class: 'w-4 rounded-t-lg bg-primary-500', style: `height:${Math.max(3, (row.clients / max.value) * 100)}%`, title: `${row.clients} clients` }),
                    h('div', { class: 'w-4 rounded-t-lg bg-secondary-500', style: `height:${Math.max(3, (row.freelancers / max.value) * 100)}%`, title: `${row.freelancers} freelancers` }),
                ]),
                h('span', { class: 'text-[10px] font-black text-slate-500' }, row.label),
            ]))),
            h('div', { class: 'mt-3 flex gap-3 text-xs font-bold text-slate-600 dark:text-slate-300' }, [
                h('span', '● Clients'),
                h('span', { class: 'text-secondary-500' }, '● Freelancers'),
            ]),
        ]);
    },
});

const DonutLegend = defineComponent({
    props: { payload: Object },
    setup(donutProps) {
        const colors = ['#e0f2fe', '#7dd3fc', '#38bdf8', '#0284c7', '#075985'];
        const segments = computed(() => {
            let start = 0;
            return donutProps.payload.tiers.map((tier, index) => {
                const end = start + Number(tier.percent || 0);
                const segment = `${colors[index]} ${start}% ${end}%`;
                start = end;
                return segment;
            });
        });
        return () => h('div', { class: 'grid gap-5 md:grid-cols-[14rem_1fr]' }, [
            h('div', { class: 'relative mx-auto h-52 w-52 rounded-full', style: `background: conic-gradient(${segments.value.join(', ')});` }, [
                h('div', { class: 'absolute inset-8 flex flex-col items-center justify-center rounded-full bg-white text-center shadow-inner dark:bg-slate-900' }, [
                    h('p', { class: 'font-display text-3xl font-black text-slate-950 dark:text-white' }, Number(donutProps.payload.total).toLocaleString()),
                    h('p', { class: 'text-xs font-black uppercase tracking-wide text-slate-500' }, 'Active users'),
                ]),
            ]),
            h('div', { class: 'space-y-2' }, donutProps.payload.tiers.map((tier, index) => h('div', { class: 'rounded-2xl border border-slate-200 p-3 dark:border-white/10' }, [
                h('div', { class: 'flex items-center justify-between gap-3' }, [
                    h('span', { class: 'inline-flex items-center gap-2 text-sm font-black text-slate-900 dark:text-white' }, [h('i', { class: 'h-3 w-3 rounded-full', style: `background:${colors[index]}` }), tier.label]),
                    h('span', { class: 'text-xs font-black text-slate-500' }, `${tier.count} · ${tier.percent}%`),
                ]),
                h('p', { class: 'mt-1 text-xs text-slate-500' }, tier.capability),
            ]))),
        ]);
    },
});

const CategoryHeatmap = defineComponent({
    props: { payload: Object },
    setup(heatProps) {
        const tone = { good: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-100', warn: 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-100', bad: 'bg-rose-100 text-rose-800 dark:bg-rose-500/20 dark:text-rose-100' };
        return () => h('div', { class: 'overflow-x-auto' }, [
            h('table', { class: 'min-w-full border-separate border-spacing-2 text-sm' }, [
                h('thead', [h('tr', [
                    h('th', { class: 'text-left text-xs font-black uppercase tracking-wide text-slate-500' }, 'Category'),
                    ...heatProps.payload.metrics.map((metric) => h('th', { class: 'text-left text-xs font-black uppercase tracking-wide text-slate-500' }, metric.label)),
                ])]),
                h('tbody', heatProps.payload.rows.map((row) => h('tr', [
                    h('td', { class: 'whitespace-nowrap font-black text-slate-900 dark:text-white' }, row.category),
                    ...heatProps.payload.metrics.map((metric) => h('td', { class: `rounded-xl px-3 py-2 text-xs font-black ${tone[row.values[metric.key]?.tone]}` }, row.values[metric.key]?.label || '—')),
                ]))),
            ]),
        ]);
    },
});

const DisputeCombo = defineComponent({
    props: { months: Array },
    setup(disputeProps) {
        const maxRate = computed(() => Math.max(1, ...disputeProps.months.map((row) => row.rate)));
        const outcomeKeys = ['client_refund', 'freelancer_release', 'split', 'dismissed', 'unresolved'];
        const colors = ['bg-amber-500', 'bg-emerald-500', 'bg-primary-500', 'bg-slate-400', 'bg-rose-500'];
        return () => h('div', [
            h('div', { class: 'flex h-32 items-end gap-2 border-b border-slate-200 dark:border-white/10' }, disputeProps.months.map((row) => h('div', { class: 'flex flex-1 flex-col items-center gap-1' }, [
                h('div', { class: 'w-1.5 rounded-full bg-rose-500', style: `height:${Math.max(3, (row.rate / maxRate.value) * 100)}%`, title: `${row.rate}% dispute rate` }),
                h('span', { class: 'text-[10px] font-bold text-slate-500' }, row.label),
            ]))),
            h('div', { class: 'mt-5 flex h-36 items-end gap-2' }, disputeProps.months.map((row) => {
                const total = Math.max(1, sum(outcomeKeys.map((key) => row.outcomes[key])));
                return h('div', { class: 'flex flex-1 flex-col justify-end overflow-hidden rounded-t-xl bg-slate-100 dark:bg-white/10' }, outcomeKeys.map((key, index) => h('div', { class: colors[index], style: `height:${Math.max(2, (row.outcomes[key] / total) * 100)}%` })));
            })),
        ]);
    },
});

const Leaderboard = defineComponent({
    props: { rows: Array, valueLabel: String, type: String },
    setup(boardProps) {
        const max = computed(() => Math.max(1, ...boardProps.rows.map((row) => row.value_minor)));
        return () => h('div', { class: 'space-y-3' }, (boardProps.rows.length ? boardProps.rows : []).map((row, index) => h('div', { class: 'space-y-1' }, [
            h('div', { class: 'flex items-center justify-between gap-3' }, [
                h('div', { class: 'flex min-w-0 items-center gap-2' }, [
                    row.avatar_url ? h('img', { src: row.avatar_url, alt: '', class: 'h-9 w-9 rounded-full object-cover' }) : h('span', { class: 'flex h-9 w-9 items-center justify-center rounded-full bg-primary-100 text-xs font-black text-primary-700' }, row.name?.charAt(0) || '?'),
                    h('div', { class: 'min-w-0' }, [h('p', { class: 'truncate text-sm font-black text-slate-900 dark:text-white' }, row.name), h('p', { class: 'truncate text-xs text-slate-500' }, row.email)]),
                ]),
                h('p', { class: 'text-sm font-black text-slate-900 dark:text-white' }, row.value),
            ]),
            h('div', { class: 'h-3 overflow-hidden rounded-full bg-slate-100 dark:bg-white/10' }, [h('div', { class: index === 0 ? 'h-full rounded-full bg-primary-600' : 'h-full rounded-full bg-primary-400', style: `width:${Math.max(4, (row.value_minor / max.value) * 100)}%` })]),
            h('div', { class: 'flex justify-between text-[10px] font-black uppercase tracking-wide text-slate-500' }, [
                h('span', boardProps.valueLabel),
                boardProps.type === 'freelancer' ? h('span', `Trust ${row.trust_score} · ${row.disputes_count} disputes · ${row.completion_rate}% complete`) : h('span', `${row.active_contracts} active · ${row.disputes_count ? 'At risk' : 'No open risk'}`),
            ]),
        ]), h('p', { class: boardProps.rows.length ? 'hidden' : 'rounded-2xl bg-slate-50 p-4 text-sm font-bold text-slate-500 dark:bg-white/5' }, 'No leaderboard data for the current month yet.')));
    },
});

const GeoMap = defineComponent({
    props: { states: Array },
    setup(mapProps) {
        const metric = ref('users');
        const metrics = { users: 'Users', quests: 'Quests Posted', contract_value_minor: 'Contract Value' };
        const max = computed(() => Math.max(1, ...mapProps.states.map((state) => Number(state[metric.value] || 0))));
        const states = computed(() => [...mapProps.states].sort((a, b) => Number(b[metric.value] || 0) - Number(a[metric.value] || 0)));
        return () => h('div', [
            h('div', { class: 'mb-4 inline-flex rounded-2xl bg-slate-100 p-1 dark:bg-white/10' }, Object.entries(metrics).map(([key, label]) => h('button', { type: 'button', class: `rounded-xl px-3 py-2 text-xs font-black ${metric.value === key ? 'bg-primary-600 text-white' : 'text-slate-600 dark:text-slate-300'}`, onClick: () => { metric.value = key; } }, label))),
            h('div', { class: 'grid max-h-[28rem] grid-cols-3 gap-2 overflow-y-auto pr-1 sm:grid-cols-4 lg:grid-cols-5' }, states.value.map((state) => {
                const intensity = pct(Number(state[metric.value] || 0), max.value);
                return h('div', { class: 'rounded-2xl border border-slate-200 p-3 dark:border-white/10', style: `background: rgba(14, 165, 233, ${0.08 + intensity / 130})`, title: `${state.name}: ${state.users} users, ${state.quests} quests, ${state.contract_value}` }, [
                    h('p', { class: 'text-xs font-black text-slate-900 dark:text-white' }, state.name),
                    h('p', { class: 'mt-1 text-[10px] font-bold text-slate-600 dark:text-slate-300' }, metric.value === 'contract_value_minor' ? state.contract_value : Number(state[metric.value] || 0).toLocaleString()),
                ]);
            })),
            h('p', { class: 'mt-3 text-xs font-semibold text-slate-500' }, 'Interactive choropleth-style state grid; hover a state for full users, quest and contract value details.'),
        ]);
    },
});

const SankeyFlow = defineComponent({
    props: { nodes: Array, inflow: Number },
    setup(flowProps) {
        const inflow = computed(() => Math.max(1, flowProps.inflow || 1));
        return () => h('div', { class: 'space-y-4' }, [
            h('div', { class: 'rounded-3xl bg-primary-50 p-4 dark:bg-primary-500/10' }, [h('p', { class: 'text-xs font-black uppercase tracking-wide text-primary-700 dark:text-primary-200' }, flowProps.nodes[0]?.label), h('p', { class: 'font-display text-2xl font-black text-slate-950 dark:text-white' }, flowProps.nodes[0]?.value)]),
            h('div', { class: 'space-y-3' }, flowProps.nodes.slice(1).map((node, index) => h('div', { class: 'grid grid-cols-[1fr_auto] items-center gap-3' }, [
                h('div', { class: 'h-8 overflow-hidden rounded-full bg-slate-100 dark:bg-white/10' }, [h('div', { class: ['h-full rounded-full', index === 0 ? 'bg-emerald-500' : index === 1 ? 'bg-amber-500' : 'bg-rose-500'], style: `width:${Math.max(5, (node.minor / inflow.value) * 100)}%` })]),
                h('div', { class: 'w-32 text-right' }, [h('p', { class: 'text-xs font-black text-slate-900 dark:text-white' }, node.value), h('p', { class: 'text-[10px] text-slate-500' }, node.label)]),
            ]))),
        ]);
    },
});

const ProposalHeatmap = defineComponent({
    props: { payload: Object },
    setup(heatProps) {
        return () => h('div', { class: 'overflow-x-auto' }, [
            h('div', { class: 'grid min-w-[42rem] gap-1', style: 'grid-template-columns: 3rem repeat(24, minmax(0, 1fr));' }, [
                h('span'),
                ...heatProps.payload.hours.map((hour) => h('span', { class: 'text-center text-[9px] font-black text-slate-400' }, hour)),
                ...heatProps.payload.days.flatMap((day, dayIndex) => [
                    h('span', { class: 'py-1 text-xs font-black text-slate-500' }, day),
                    ...heatProps.payload.hours.map((hour) => {
                        const cell = heatProps.payload.cells.find((item) => item.day === dayIndex && item.hour === hour);
                        const intensity = cell ? cell.count / heatProps.payload.max : 0;
                        return h('div', { class: 'h-5 rounded-md border border-white/40 dark:border-slate-800', style: `background: rgba(14, 165, 233, ${0.08 + intensity * 0.82})`, title: `${day} ${hour}:00 · ${cell?.count || 0} proposals` });
                    }),
                ]),
            ]),
        ]);
    },
});

const PayoutPanel = defineComponent({
    props: { payload: Object },
    setup(payoutProps) {
        return () => h('div', { class: 'space-y-5' }, [
            h('div', { class: 'grid gap-3 sm:grid-cols-3' }, payoutProps.payload.split.map((item) => h('div', { class: 'rounded-2xl bg-slate-50 p-3 text-center dark:bg-white/5' }, [
                h('p', { class: 'text-xs font-black uppercase tracking-wide text-slate-500' }, item.label),
                h('p', { class: 'mt-1 font-display text-2xl font-black text-slate-950 dark:text-white' }, `${item.percent}%`),
                h('p', { class: 'text-xs text-slate-500' }, `${item.count} payouts`),
            ]))),
            h('div', { class: 'space-y-2' }, payoutProps.payload.failure_reasons.map((reason) => h('a', { href: route('admin.financial.index', { tab: 'payouts', q: reason.reason }), class: 'block rounded-2xl border border-slate-200 p-3 transition hover:border-primary-300 dark:border-white/10' }, [
                h('div', { class: 'flex justify-between text-sm font-black text-slate-900 dark:text-white' }, [h('span', reason.reason), h('span', reason.count)]),
            ]))),
        ]);
    },
});

const CohortTable = defineComponent({
    props: { payload: Object },
    setup(cohortProps) {
        const tone = (percent) => percent >= 60 ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-100' : percent >= 30 ? 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-100' : 'bg-rose-100 text-rose-800 dark:bg-rose-500/20 dark:text-rose-100';
        return () => h('div', { class: 'overflow-x-auto' }, [
            h('table', { class: 'min-w-full border-separate border-spacing-2 text-sm' }, [
                h('thead', [h('tr', [h('th', { class: 'text-left text-xs font-black text-slate-500' }, 'Cohort'), ...cohortProps.payload.columns.map((column) => h('th', { class: 'text-left text-xs font-black text-slate-500' }, column))])]),
                h('tbody', cohortProps.payload.rows.map((row) => h('tr', [
                    h('td', { class: 'whitespace-nowrap font-black text-slate-900 dark:text-white' }, `${row.label} (${row.size})`),
                    ...row.cells.map((cell) => h('td', { class: `rounded-xl px-3 py-2 text-xs font-black ${cell ? tone(cell.percent) : 'bg-slate-100 text-slate-400 dark:bg-white/5'}` }, cell ? `${cell.percent}%` : '—')),
                ]))),
            ]),
        ]);
    },
});
</script>
