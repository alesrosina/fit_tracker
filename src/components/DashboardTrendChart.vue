<template>
    <section class="ft-panel trend">
        <h3>{{ title }}</h3>
        <div class="trend__toolbar">
            <NcRadioGroup v-model="metric" :label="t('fit_tracker', 'Measure')" hide-label>
                <NcRadioGroupButton v-for="m in metricOptions" :key="m" :value="m" :label="shortLabel(m)" />
            </NcRadioGroup>
            <NcRadioGroup
                v-if="bucketChoices.length > 1"
                :model-value="kind"
                :label="t('fit_tracker', 'Bars')"
                hide-label
                @update:model-value="chosenBucket = $event"
            >
                <NcRadioGroupButton v-for="b in bucketChoices" :key="b" :value="b" :label="bucketLabel(b)" />
            </NcRadioGroup>
        </div>
        <div class="trend__canvas">
            <canvas ref="canvas" role="img" :aria-label="title"></canvas>
        </div>
    </section>
</template>

<script>
import { Chart, BarController, BarElement, LineController, LineElement, PointElement, LinearScale, CategoryScale, Tooltip, Legend } from 'chart.js'
import { t } from '@nextcloud/l10n'
import { NcRadioGroup, NcRadioGroupButton } from '@nextcloud/vue'
import { baseChartOptions } from '../chartTheme.js'
import { sportColor, sportLabel } from '../sports.js'
import { settings } from '../store/settings.js'
import { parseDate, formatDurationShort } from '../format.js'
import { metricLabel, metricValue, displayValue, displayUnit } from '../stats.js'
import { rangeLabel, formatWindow, bucketOptions, defaultBucket, bucketLabel, bucketStart, bucketsBetween, formatBucket } from '../timeframe.js'
Chart.register(BarController, BarElement, LineController, LineElement, PointElement, LinearScale, CategoryScale, Tooltip, Legend)

// Distance, time, elevation or count over the selected timeframe, as bars
// per day, week, month or year (by sport when several are shown). The previous timeframe of
// the same length is drawn as a line for comparison.
export default {
    name: 'DashboardTrendChart',
    components: { NcRadioGroup, NcRadioGroupButton },
    props: {
        activities: { type: Array, required: true },
        timeframe: { type: Object, required: true },
        bySport: { type: Boolean, default: true },
        // Leave out distance and elevation for sports without them
        hasDistance: { type: Boolean, default: true },
    },
    data() {
        // Bar size picked by the user; null = the default for the timeframe
        return { metric: this.hasDistance ? 'distance' : 'duration', chosenBucket: null }
    },
    computed: {
        metricOptions() {
            return this.hasDistance ? ['distance', 'duration', 'elevation', 'count'] : ['duration', 'count']
        },
        bucketChoices() {
            return bucketOptions(this.timeframe.days)
        },
        kind() {
            return this.bucketChoices.includes(this.chosenBucket) ? this.chosenBucket : defaultBucket(this.timeframe.days)
        },
        // "Distance · Last 30 days" or "Distance · Mar 30 – Apr 10, 2026"
        title() {
            const tf = this.timeframe
            const when = tf.range === 'custom' ? formatWindow(tf) : rangeLabel(tf.range)
            return t('fit_tracker', '{measure} · {timeframe}', { measure: metricLabel(this.metric), timeframe: when })
        },
        redrawKey() {
            return [this.metric, this.kind, this.timeframe.from.getTime(), this.timeframe.to.getTime(), this.activities.length, this.bySport, settings.units].join('|')
        },
    },
    watch: {
        redrawKey() { this.draw() },
        // A new timeframe starts with its default bar size
        'timeframe.range'() { this.chosenBucket = null },
        hasDistance(has) {
            if (!has && ['distance', 'elevation'].includes(this.metric)) this.metric = 'duration'
        },
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
        metricLabel,
        shortLabel(m) {
            return m === 'elevation' ? t('fit_tracker', 'Elevation') : metricLabel(m)
        },
        bucketLabel,
        buckets(from, to) {
            return bucketsBetween(from, to, this.kind)
        },
        // Totals per bucket for the given activities, keyed like buckets()
        totals(list, starts) {
            const index = new Map(starts.map((s, i) => [s.getTime(), i]))
            const totals = new Array(starts.length).fill(0)
            for (const a of list) {
                const i = index.get(bucketStart(parseDate(a.startTime), this.kind).getTime())
                if (i !== undefined) totals[i] += metricValue(a, this.metric)
            }
            return totals.map(v => displayValue(v, this.metric))
        },
        label(d) {
            return formatBucket(d, this.kind)
        },
        format(v) {
            if (this.metric === 'duration') return formatDurationShort(v * 3600)
            if (this.metric === 'count') return String(Math.round(v))
            return `${v.toFixed(1)} ${displayUnit(this.metric)}`
        },
        draw() {
            this.chart?.destroy()
            this.chart = null
            if (!this.$refs.canvas) return
            const tf = this.timeframe
            const starts = this.buckets(tf.from, tf.to)
            const inWindow = this.activities.filter(a => {
                const d = parseDate(a.startTime)
                return d >= tf.from && d < tf.to
            })
            const sports = this.bySport ? [...new Set(inWindow.map(a => a.sport))] : ['all']
            const datasets = sports.map(sport => ({
                type: 'bar',
                label: sport === 'all' ? metricLabel(this.metric) : sportLabel(sport),
                data: this.totals(sport === 'all' ? inWindow : inWindow.filter(a => a.sport === sport), starts),
                backgroundColor: sport === 'all' ? '#0082c9' : sportColor(sport),
                borderRadius: 2,
                stack: 'now',
                order: 2,
            })).filter(ds => ds.data.some(v => v > 0))
            const base = baseChartOptions()
            if (tf.previous) {
                // Same number of buckets, counted from the start of the previous timeframe
                const prevStarts = this.buckets(tf.previous.from, tf.previous.to).slice(0, starts.length)
                const prevList = this.activities.filter(a => {
                    const d = parseDate(a.startTime)
                    return d >= tf.previous.from && d < tf.previous.to
                })
                datasets.push({
                    type: 'line',
                    label: t('fit_tracker', 'Previous {days} days', { days: tf.days }),
                    data: this.totals(prevList, prevStarts),
                    borderColor: base.theme.text,
                    backgroundColor: base.theme.text,
                    borderDash: [4, 4],
                    borderWidth: 1.5,
                    pointRadius: 0,
                    cubicInterpolationMode: 'monotone',
                    // Its own stack, so it isn't added on top of the bars
                    stack: 'previous',
                    order: 1,
                })
            }
            this.chart = new Chart(this.$refs.canvas, {
                data: { labels: starts.map(d => this.label(d)), datasets },
                options: {
                    responsive: true,
                    // Height comes from .trend__canvas, so phones get a usable chart too
                    maintainAspectRatio: false,
                    animation: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: {
                            display: datasets.length > 1,
                            position: 'bottom',
                            labels: { color: base.theme.mainText, font: base.ticks.font, boxWidth: 12, padding: 12 },
                        },
                        tooltip: {
                            ...base.tooltip,
                            callbacks: { label: ctx => ctx.parsed.y ? `${ctx.dataset.label}: ${this.format(ctx.parsed.y)}` : null },
                        },
                    },
                    scales: {
                        x: { stacked: true, ticks: { ...base.ticks, maxRotation: 0, autoSkip: true }, grid: { display: false } },
                        y: {
                            stacked: true,
                            beginAtZero: true,
                            ticks: { ...base.ticks, callback: v => this.metric === 'duration' ? formatDurationShort(v * 3600) : v },
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
.trend h3 {
    margin: 0 0 var(--ft-gap-3);
}
.trend__toolbar {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: var(--ft-gap-2) var(--ft-gap-4);
    margin-bottom: var(--ft-gap-3);
}
.trend__canvas {
    position: relative;
    height: 300px;
}
@media (max-width: 600px) {
    .trend__canvas {
        height: 240px;
    }
}
</style>
