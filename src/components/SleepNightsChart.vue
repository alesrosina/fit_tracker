<template>
    <section class="ft-panel sleep-chart">
        <h3>{{ title }}</h3>
        <div v-if="bucketChoices.length > 1" class="sleep-chart__toolbar">
            <NcRadioGroup
                :model-value="kind"
                :label="t('fit_tracker', 'Bars')"
                hide-label
                @update:model-value="chosenBucket = $event"
            >
                <NcRadioGroupButton v-for="b in bucketChoices" :key="b" :value="b" :label="bucketLabel(b)" />
            </NcRadioGroup>
        </div>
        <div class="sleep-chart__canvas">
            <canvas ref="canvas" role="img" :aria-label="title"></canvas>
        </div>
    </section>
</template>

<script>
import { Chart, BarController, BarElement, LinearScale, CategoryScale, Tooltip, Legend } from 'chart.js'
import { t } from '@nextcloud/l10n'
import { NcRadioGroup, NcRadioGroupButton } from '@nextcloud/vue'
import { STAGE_COLORS, stageLabel } from '../sleepStages.js'
import { baseChartOptions } from '../chartTheme.js'
import { formatDurationShort } from '../format.js'
import { avg } from '../sleepStats.js'
import { rangeLabel, formatWindow, bucketOptions, defaultBucket, bucketLabel, bucketStart, bucketsBetween, formatBucket } from '../timeframe.js'
Chart.register(BarController, BarElement, LinearScale, CategoryScale, Tooltip, Legend)

const STAGES = [['deep', 'timeDeep'], ['rem', 'timeRem'], ['light', 'timeLight'], ['awake', 'timeAwake']]
const hasStages = s => (s.timeDeep || 0) + (s.timeLight || 0) + (s.timeRem || 0) + (s.timeAwake || 0) > 0

// Sleep over the selected timeframe: one stacked bar of sleep stages per
// night, or the average night per week, month or year.
export default {
    name: 'SleepNightsChart',
    components: { NcRadioGroup, NcRadioGroupButton },
    props: {
        // sleepStats.js toNights(), already limited to the timeframe
        nights: { type: Array, required: true },
        timeframe: { type: Object, required: true },
    },
    data() {
        return { chosenBucket: null }
    },
    computed: {
        bucketChoices() {
            return bucketOptions(this.timeframe.days)
        },
        kind() {
            return this.bucketChoices.includes(this.chosenBucket) ? this.chosenBucket : defaultBucket(this.timeframe.days)
        },
        title() {
            const tf = this.timeframe
            const when = tf.range === 'custom' ? formatWindow(tf) : rangeLabel(tf.range)
            const what = this.kind === 'day' ? t('fit_tracker', 'Sleep per night') : t('fit_tracker', 'Average night')
            return t('fit_tracker', '{measure} · {timeframe}', { measure: what, timeframe: when })
        },
        redrawKey() {
            return [this.kind, this.timeframe.from.getTime(), this.timeframe.to.getTime(), this.nights.length].join('|')
        },
    },
    watch: {
        redrawKey() { this.draw() },
        'timeframe.range'() { this.chosenBucket = null },
    },
    created() {
        this.chart = null
    },
    mounted() {
        this.$nextTick(() => this.draw())
    },
    beforeUnmount() {
        this.chart?.destroy()
    },
    methods: {
        bucketLabel,
        draw() {
            this.chart?.destroy()
            this.chart = null
            if (!this.$refs.canvas) return
            const starts = bucketsBetween(this.timeframe.from, this.timeframe.to, this.kind)
            const index = new Map(starts.map((s, i) => [s.getTime(), i]))
            const groups = starts.map(() => [])
            for (const night of this.nights) {
                const i = index.get(bucketStart(night.evening, this.kind).getTime())
                if (i !== undefined) groups[i].push(night.session)
            }
            const hours = (list, fn) => list.length ? avg(list.map(fn)) / 3600 : 0
            // Nights without stage data count in a grey "total" part
            const datasets = [
                ...STAGES.map(([stage, field]) => ({
                    label: stageLabel(stage),
                    data: groups.map(g => hours(g, s => hasStages(s) ? (s[field] || 0) : 0)),
                    backgroundColor: STAGE_COLORS[stage],
                    stack: 'sleep',
                })),
                {
                    label: t('fit_tracker', 'Total'),
                    data: groups.map(g => hours(g, s => hasStages(s) ? 0 : s.duration)),
                    backgroundColor: '#94a3b8',
                    stack: 'sleep',
                },
            ].filter(ds => ds.data.some(v => v > 0))
            const scores = groups.map(g => avg(g.map(s => s.score || null)))
            const counts = groups.map(g => g.length)
            const base = baseChartOptions()
            this.chart = new Chart(this.$refs.canvas, {
                type: 'bar',
                data: { labels: starts.map(d => formatBucket(d, this.kind)), datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { color: base.theme.mainText, font: base.ticks.font, boxWidth: 12, padding: 12 } },
                        tooltip: {
                            ...base.tooltip,
                            callbacks: {
                                label: ctx => ctx.parsed.y
                                    // TRANSLATORS: Chart tooltip, e.g. "Deep: 1h 20m"
                                    ? t('fit_tracker', '{stage}: {duration}', { stage: ctx.dataset.label, duration: formatDurationShort(ctx.parsed.y * 3600) })
                                    : null,
                                afterBody: items => {
                                    const i = items[0]?.dataIndex
                                    const lines = []
                                    if (this.kind !== 'day' && counts[i]) lines.push(t('fit_tracker', 'Nights: {count}', { count: counts[i] }))
                                    if (scores[i]) lines.push(t('fit_tracker', 'Sleep score: {score}/100', { score: Math.round(scores[i]) }))
                                    return lines
                                },
                            },
                        },
                    },
                    scales: {
                        x: { stacked: true, grid: { display: false }, ticks: { ...base.ticks, autoSkip: true, maxRotation: 0 } },
                        y: {
                            stacked: true,
                            beginAtZero: true,
                            ticks: { ...base.ticks, callback: v => formatDurationShort(v * 3600) },
                            grid: base.grid,
                            border: { color: base.grid.color },
                        },
                    },
                },
            })
        },
    },
}
</script>

<style scoped>
.sleep-chart h3 {
    margin: 0 0 var(--ft-gap-3);
}
.sleep-chart__toolbar {
    /* Flex, so the button group keeps its natural width */
    display: flex;
    margin-bottom: var(--ft-gap-3);
}
.sleep-chart__canvas {
    position: relative;
    height: 300px;
}
@media (max-width: 600px) {
    .sleep-chart__canvas {
        height: 240px;
    }
}
</style>
