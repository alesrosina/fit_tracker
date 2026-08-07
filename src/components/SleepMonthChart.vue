<template>
    <div class="sleep-month-chart">
        <div class="sleep-month-chart__header">
            <button class="pagination__btn" @click="monthOffset--">&#8592;</button>
            <span class="sleep-month-chart__title">{{ monthTitle }}</span>
            <button class="pagination__btn" :disabled="!canGoForward" @click="monthOffset++">&#8594;</button>
        </div>
        <canvas ref="canvas"></canvas>
    </div>
</template>

<script>
import { Chart, BarController, BarElement, LinearScale, CategoryScale, Tooltip, Legend } from 'chart.js'
Chart.register(BarController, BarElement, LinearScale, CategoryScale, Tooltip, Legend)

export default {
    name: 'SleepMonthChart',
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
                            labels: { font: { size: 11 }, boxWidth: 12, padding: 10 },
                        },
                        tooltip: {
                            callbacks: {
                                label: ctx => {
                                    const v = ctx.parsed.y
                                    if (!v) return null
                                    const h = Math.floor(v / 60)
                                    const m = v % 60
                                    const dur = h > 0 ? `${h}h ${m}m` : `${m}m`
                                    // append score on total bar
                                    const score = scoreData[ctx.dataIndex]
                                    if (ctx.dataset.label === 'Total' && score) {
                                        return `Total: ${dur}  ·  Score ${score}/100`
                                    }
                                    return `${ctx.dataset.label}: ${dur}`
                                },
                                afterBody: (items) => {
                                    const idx = items[0]?.dataIndex
                                    const score = scoreData[idx]
                                    const hrv = hrvData[idx]
                                    const lines = []
                                    if (score) lines.push(`Sleep score: ${score}/100`)
                                    if (hrv)   lines.push(`HRV: ${hrv}`)
                                    return lines
                                },
                            },
                        },
                    },
                    scales: {
                        x: {
                            stacked: true,
                            grid: { display: false },
                            ticks: { font: { size: 11 }, autoSkip: true, maxRotation: 0 },
                        },
                        y: {
                            stacked: true,
                            beginAtZero: true,
                            ticks: {
                                font: { size: 11 },
                                callback: v => {
                                    const h = Math.floor(v / 60)
                                    const m = v % 60
                                    return h > 0 ? (m ? `${h}h${m}m` : `${h}h`) : `${m}m`
                                },
                            },
                            grid: { color: 'rgba(0,0,0,0.05)' },
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
                { label: 'Deep',  data: deep,  backgroundColor: '#1e40af', stack: 'sleep', borderRadius: 0 },
                { label: 'REM',   data: rem,   backgroundColor: '#7c3aed', stack: 'sleep', borderRadius: 0 },
                { label: 'Light', data: light, backgroundColor: '#60a5fa', stack: 'sleep', borderRadius: 0 },
                { label: 'Awake', data: awake, backgroundColor: '#fbbf24', stack: 'sleep', borderRadius: 0 },
                { label: 'Total', data: total, backgroundColor: '#94a3b8', stack: 'sleep', borderRadius: 4 },
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
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 16px;
    margin-bottom: 12px;
}
.sleep-month-chart__title {
    font-size: 14px;
    font-weight: 600;
    color: var(--color-text-maxcontrast);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    min-width: 160px;
    text-align: center;
}
.pagination__btn {
    background: var(--color-main-background);
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius);
    padding: 6px 14px;
    cursor: pointer;
    font-size: 16px;
    color: var(--color-main-text);
    transition: background 0.15s;
}
.pagination__btn:hover:not(:disabled) { background: var(--color-background-dark); }
.pagination__btn:disabled { opacity: 0.4; cursor: default; }
</style>
