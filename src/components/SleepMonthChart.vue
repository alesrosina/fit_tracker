<template>
    <div class="sleep-month-chart">
        <PageNav
            class="sleep-month-chart__header"
            :previous-label="t('fit_tracker', 'Previous month')"
            :next-label="t('fit_tracker', 'Next month')"
            :has-next="canGoForward"
            @previous="monthOffset--"
            @next="monthOffset++"
        >
            {{ monthTitle }}
        </PageNav>
        <canvas ref="canvas" role="img" :aria-label="t('fit_tracker', 'Sleep duration per night, by stage')"></canvas>
    </div>
</template>

<script>
import { Chart, BarController, BarElement, LinearScale, CategoryScale, Tooltip, Legend } from 'chart.js'
import { t } from '@nextcloud/l10n'
import PageNav from './PageNav.vue'
import { STAGE_COLORS, stageLabel } from '../sleepStages.js'
import { baseChartOptions } from '../chartTheme.js'
import { formatDurationShort } from '../format.js'
Chart.register(BarController, BarElement, LinearScale, CategoryScale, Tooltip, Legend)

export default {
    name: 'SleepMonthChart',
    components: { PageNav },
    props: {
        sessions: { type: Array, required: true },
    },
    data() {
        return { chart: null, monthOffset: 0 }
    },
    computed: {
        viewedDate() {
            const d = new Date()
            d.setDate(1)
            d.setMonth(d.getMonth() + this.monthOffset)
            return d
        },
        canGoForward() {
            return this.monthOffset < 0
        },
        monthTitle() {
            return this.viewedDate.toLocaleDateString(undefined, { month: 'long', year: 'numeric' })
        },
    },
    watch: {
        sessions() { this.rebuild() },
        monthOffset() { this.rebuild() },
    },
    mounted() {
        this.$nextTick(() => this.rebuild())
    },
    beforeUnmount() {
        this.chart?.destroy()
    },
    methods: {
        rebuild() {
            this.chart?.destroy()
            const { labels, datasets, scoreData, hrvData } = this.buildMonthData()
            const base = baseChartOptions()
            this.chart = new Chart(this.$refs.canvas, {
                type: 'bar',
                data: { labels, datasets },
                options: {
                    responsive: true,
                    animation: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom',
                            labels: { color: base.theme.mainText, font: base.ticks.font, boxWidth: 12, padding: 12 },
                        },
                        tooltip: {
                            ...base.tooltip,
                            callbacks: {
                                label: ctx => {
                                    const v = ctx.parsed.y
                                    if (!v) return null
                                    const dur = formatDurationShort(v * 60)
                                    // TRANSLATORS: Chart tooltip, e.g. "Deep: 1h 20m"
                                    return t('fit_tracker', '{stage}: {duration}', { stage: ctx.dataset.label, duration: dur })
                                },
                                afterBody: (items) => {
                                    const idx = items[0]?.dataIndex
                                    const score = scoreData[idx]
                                    const hrv = hrvData[idx]
                                    const lines = []
                                    if (score) lines.push(t('fit_tracker', 'Sleep score: {score}/100', { score }))
                                    if (hrv)   lines.push(t('fit_tracker', 'HRV: {score}', { score: hrv }))
                                    return lines
                                },
                            },
                        },
                    },
                    scales: {
                        x: {
                            stacked: true,
                            grid: { display: false },
                            ticks: { ...base.ticks, autoSkip: true, maxRotation: 0 },
                        },
                        y: {
                            stacked: true,
                            beginAtZero: true,
                            ticks: {
                                ...base.ticks,
                                callback: v => formatDurationShort(v * 60),
                            },
                            grid: base.grid,
                            border: { color: base.grid.color },
                        },
                    },
                },
            })
        },
        buildMonthData() {
            const year  = this.viewedDate.getFullYear()
            const month = this.viewedDate.getMonth()
            const dayCount = new Date(year, month + 1, 0).getDate()
            const days = Array.from({ length: dayCount }, (_, i) => new Date(year, month, i + 1))

            const labels = days.map(d => String(d.getDate()))

            const scoreData = []
            const hrvData   = []
            const deep  = [], rem = [], light = [], awake = [], total = []

            days.forEach(d => {
                // Match a session whose start time falls on this calendar day
                const sess = this.sessions.find(s => {
                    if (!s.startTime) return false
                    const sd = new Date(s.startTime)
                    return sd.getFullYear() === d.getFullYear()
                        && sd.getMonth()    === d.getMonth()
                        && sd.getDate()     === d.getDate()
                })
                scoreData.push(sess?.score   ?? null)
                hrvData.push(sess?.hrvScore  ?? null)

                const hasStages = sess && ((sess.timeDeep || 0) + (sess.timeLight || 0) + (sess.timeRem || 0) + (sess.timeAwake || 0)) > 0
                if (hasStages) {
                    deep.push(Math.round((sess.timeDeep  || 0) / 60))
                    rem.push(Math.round((sess.timeRem    || 0) / 60))
                    light.push(Math.round((sess.timeLight || 0) / 60))
                    awake.push(Math.round((sess.timeAwake || 0) / 60))
                    total.push(null)
                } else if (sess) {
                    deep.push(0); rem.push(0); light.push(0); awake.push(0)
                    total.push(Math.round((sess.duration || 0) / 60))
                } else {
                    deep.push(0); rem.push(0); light.push(0); awake.push(0); total.push(0)
                }
            })

            const datasets = [
                { label: stageLabel('deep'),  data: deep,  backgroundColor: STAGE_COLORS.deep,  stack: 'sleep', borderRadius: 0 },
                { label: stageLabel('rem'),   data: rem,   backgroundColor: STAGE_COLORS.rem,   stack: 'sleep', borderRadius: 0 },
                { label: stageLabel('light'), data: light, backgroundColor: STAGE_COLORS.light, stack: 'sleep', borderRadius: 0 },
                { label: stageLabel('awake'), data: awake, backgroundColor: STAGE_COLORS.awake, stack: 'sleep', borderRadius: 0 },
                // Nights without stage data: total duration only
                { label: t('fit_tracker', 'Total'), data: total, backgroundColor: '#94a3b8', stack: 'sleep', borderRadius: 4 },
            ]

            return { labels, datasets, scoreData, hrvData }
        },
    },
}
</script>

<style scoped>
.sleep-month-chart {
    width: 100%;
}
.sleep-month-chart__header {
    max-width: 320px;
    margin: 0 auto var(--ft-gap-3);
}
</style>
