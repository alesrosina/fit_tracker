<template>
    <div class="week-chart">
        <div class="week-nav">
            <button class="week-btn" @click="prevWeek">‹</button>
            <strong>{{ weekTitle }}</strong>
            <button class="week-btn" @click="nextWeek" :disabled="!canGoNext">›</button>
        </div>
        <canvas ref="canvas"></canvas>
    </div>
</template>

<script>
import { Chart, BarController, BarElement, LinearScale, CategoryScale, Tooltip } from 'chart.js'
import { sportIcon, sportColor } from '../sports.js'
Chart.register(BarController, BarElement, LinearScale, CategoryScale, Tooltip)

function getMonday(d) {
    const date = new Date(d)
    const day = date.getDay()
    date.setDate(date.getDate() + (day === 0 ? -6 : 1 - day))
    date.setHours(0, 0, 0, 0)
    return date
}

export default {
    name: 'ActivityWeekChart',
    props: {
        activities: { type: Array, required: true },
        anchorDate: { default: null },
    },
    data() {
        return {
            chart: null,
            weekStart: getMonday(new Date()),
        }
    },
    computed: {
        canGoNext() {
            return this.weekStart < getMonday(new Date())
        },
        weekTitle() {
            const diff = Math.round((this.weekStart - getMonday(new Date())) / (7 * 86400 * 1000))
            if (diff === 0) return 'This Week'
            if (diff === -1) return 'Last Week'
            const sun = new Date(this.weekStart)
            sun.setDate(sun.getDate() + 6)
            const fmt = d => d.toLocaleDateString(undefined, { day: 'numeric', month: 'short' })
            return `${fmt(this.weekStart)} – ${fmt(sun)}`
        },
    },
    watch: {
        activities() { this.rebuild() },
        weekStart() { this.rebuild() },
        anchorDate(d) {
            if (d) this.weekStart = getMonday(d)
        },
    },
    mounted() {
        this.$nextTick(() => this.rebuild())
    },
    beforeUnmount() {
        this.chart?.destroy()
    },
    methods: {
        prevWeek() {
            const d = new Date(this.weekStart)
            d.setDate(d.getDate() - 7)
            this.weekStart = d
        },
        nextWeek() {
            if (!this.canGoNext) return
            const d = new Date(this.weekStart)
            d.setDate(d.getDate() + 7)
            this.weekStart = d
        },
        rebuild() {
            this.chart?.destroy()
            const { labels, datasets, emojisByDay, totalByDay } = this.buildWeek()

            const emojiPlugin = {
                id: 'emojiAboveBar',
                afterDatasetsDraw(chart) {
                    const { ctx, scales } = chart
                    ctx.save()
                    ctx.font = '13px serif'
                    ctx.textAlign = 'center'
                    ctx.textBaseline = 'bottom'
                    totalByDay.forEach((total, i) => {
                        const emojis = emojisByDay[i]
                        if (!emojis || !total) return
                        const x = scales.x.getPixelForValue(i)
                        const y = scales.y.getPixelForValue(total)
                        ctx.fillText(emojis, x, y - 4)
                    })
                    ctx.restore()
                },
            }

            this.chart = new Chart(this.$refs.canvas, {
                type: 'bar',
                plugins: [emojiPlugin],
                data: { labels, datasets },
                options: {
                    responsive: true,
                    animation: false,
                    layout: { padding: { top: 24 } },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: ctx => {
                                    if (!ctx.parsed.y) return null
                                    const icon = sportIcon(ctx.dataset.label)
                                    return `${icon} ${ctx.dataset.label}: ${ctx.parsed.y} min`
                                },
                            },
                        },
                    },
                    scales: {
                        x: { stacked: true, grid: { display: false }, ticks: { font: { size: 11 } } },
                        y: {
                            stacked: true,
                            beginAtZero: true,
                            ticks: { font: { size: 11 } },
                            grid: { color: 'rgba(0,0,0,0.05)' },
                        },
                    },
                },
            })
        },
        buildWeek() {
            const DEFAULT_COLOR = '#94a3b8'

            const days = Array.from({ length: 7 }, (_, i) => {
                const d = new Date(this.weekStart)
                d.setDate(d.getDate() + i)
                return d
            })
            const labels = days.map(d => {
                const wd = d.toLocaleDateString(undefined, { weekday: 'short' })
                const mm = String(d.getMonth() + 1).padStart(2, '0')
                const dd = String(d.getDate()).padStart(2, '0')
                return `${wd} ${mm}.${dd}.`
            })

            const byDay = days.map(d => this.activities.filter(a => {
                if (!a.startTime) return false
                const ad = new Date(a.startTime)
                return ad.getFullYear() === d.getFullYear()
                    && ad.getMonth() === d.getMonth()
                    && ad.getDate() === d.getDate()
            }))

            const sportsPresent = [...new Set(byDay.flat().map(a => a.sport).filter(Boolean))]

            const datasets = sportsPresent.map(sport => {
                const color = sportColor(sport) ?? DEFAULT_COLOR
                return {
                    label: sport,
                    data: byDay.map(acts =>
                        Math.round(acts.filter(a => a.sport === sport).reduce((s, a) => s + (a.duration ?? 0), 0) / 60)
                    ),
                    backgroundColor: color + 'cc',
                    borderColor: color,
                    borderWidth: 1,
                    borderRadius: 0,
                    stack: 'day',
                }
            })

            const emojisByDay = byDay.map(acts => {
                const seen = new Set()
                const emojis = acts.map(a => sportIcon(a.sport)).filter(e => !seen.has(e) && seen.add(e))
                return emojis.length ? emojis.join('') : null
            })
            const totalByDay = byDay.map(acts =>
                Math.round(acts.reduce((s, a) => s + (a.duration ?? 0), 0) / 60)
            )

            return { labels, datasets, emojisByDay, totalByDay }
        },
    },
}
</script>

<style scoped>
.week-chart {
    flex: 1;
    min-width: 280px;
}
.week-nav {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}
.week-btn {
    background: none;
    border: 1px solid var(--color-border);
    border-radius: 4px;
    cursor: pointer;
    font-size: 18px;
    line-height: 1;
    padding: 2px 10px;
    color: var(--color-main-text);
}
.week-btn:hover:not(:disabled) { background: var(--color-background-dark); }
.week-btn:disabled { opacity: 0.3; cursor: default; }
</style>
