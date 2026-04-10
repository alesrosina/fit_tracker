<template>
    <div class="week-chart">
        <canvas ref="canvas"></canvas>
    </div>
</template>

<script>
import { Chart, BarController, BarElement, LinearScale, CategoryScale, Tooltip } from 'chart.js'
Chart.register(BarController, BarElement, LinearScale, CategoryScale, Tooltip)

export default {
    name: 'ActivityWeekChart',
    props: {
        activities: { type: Array, required: true },
    },
    data() {
        return { chart: null }
    },
    watch: {
        activities() { this.rebuild() },
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
            const { labels, datasets, emojisByDay, totalByDay } = this.last7Days()

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
                                    const ICONS = { running: '🏃', cycling: '🚴', hiking: '🥾', swimming: '🏊', gym: '🏋', breathwork: '🧘', meditation: '🕉️', skiing: '⛷️' }
                                    const icon = ICONS[ctx.dataset.label] ?? '🏅'
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
        last7Days() {
            const ICONS  = { running: '🏃', cycling: '🚴', hiking: '🥾', swimming: '🏊', gym: '🏋', breathwork: '🧘', meditation: '🕉️', skiing: '⛷️' }
            const COLORS = { running: '#f97316', cycling: '#0082c9', hiking: '#16a34a', swimming: '#06b6d4', gym: '#7c3aed', breathwork: '#0d9488', meditation: '#6366f1', skiing: '#38bdf8' }
            const DEFAULT_COLOR = '#94a3b8'

            const today = new Date()
            const days = Array.from({ length: 7 }, (_, i) => {
                const d = new Date(today)
                d.setDate(today.getDate() - (6 - i))
                return d
            })
            const labels = days.map(d => {
                const wd = d.toLocaleDateString(undefined, { weekday: 'short' })
                const mm = String(d.getMonth() + 1).padStart(2, '0')
                const dd = String(d.getDate()).padStart(2, '0')
                return `${wd} ${mm}.${dd}.`
            })

            // activities per day
            const byDay = days.map(d => this.activities.filter(a => {
                if (!a.startTime) return false
                const ad = new Date(a.startTime)
                return ad.getFullYear() === d.getFullYear()
                    && ad.getMonth() === d.getMonth()
                    && ad.getDate() === d.getDate()
            }))

            // sports present across these 7 days (in a stable order)
            const sportsPresent = [...new Set(byDay.flat().map(a => a.sport).filter(Boolean))]

            // one dataset per sport
            const datasets = sportsPresent.map(sport => {
                const color = COLORS[sport] ?? DEFAULT_COLOR
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

            // emojis & totals per day for the plugin
            const emojisByDay = byDay.map(acts => {
                const seen = new Set()
                const emojis = acts.map(a => ICONS[a.sport] ?? '🏅').filter(e => !seen.has(e) && seen.add(e))
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
</style>
