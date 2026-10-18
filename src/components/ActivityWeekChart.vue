<template>
    <div class="week-chart">
        <PageNav
            class="week-nav"
            :previous-label="t('fit_tracker', 'Previous week')"
            :next-label="t('fit_tracker', 'Next week')"
            :has-next="canGoNext"
            @previous="prevWeek"
            @next="nextWeek"
        >
            {{ weekTitle }}
        </PageNav>
        <canvas ref="canvas" role="img" :aria-label="t('fit_tracker', 'Minutes of activity per day, by sport')"></canvas>
    </div>
</template>

<script>
import { Chart, BarController, BarElement, LinearScale, CategoryScale, Tooltip } from 'chart.js'
import { t } from '@nextcloud/l10n'
import PageNav from './PageNav.vue'
import { sportIcon, sportColor, sportLabel } from '../sports.js'
import { iconImage } from '../icons.js'
import { baseChartOptions } from '../chartTheme.js'
Chart.register(BarController, BarElement, LinearScale, CategoryScale, Tooltip)

const ICON_SIZE = 16

function getMonday(d) {
    const date = new Date(d)
    const day = date.getDay()
    date.setDate(date.getDate() + (day === 0 ? -6 : 1 - day))
    date.setHours(0, 0, 0, 0)
    return date
}

export default {
    name: 'ActivityWeekChart',
    components: { PageNav },
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
            if (diff === 0) return t('fit_tracker', 'This week')
            if (diff === -1) return t('fit_tracker', 'Last week')
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
        this._destroyed = true
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
        async rebuild() {
            const build = this.buildWeek()
            const base = baseChartOptions()

            // Sport icons are drawn above each bar; rasterize them first.
            const iconNames = [...new Set(build.iconsByDay.flat())]
            const images = Object.fromEntries(await Promise.all(
                iconNames.map(async name => [name, await iconImage(name, base.theme.text, ICON_SIZE * 2)]),
            ))
            // A newer rebuild may have started while icons were loading
            if (this._destroyed || build !== this._latestBuild) return

            const { labels, datasets, iconsByDay, totalByDay } = build
            const iconPlugin = {
                id: 'sportIconsAboveBar',
                afterDatasetsDraw(chart) {
                    const { ctx, scales } = chart
                    totalByDay.forEach((total, i) => {
                        const names = iconsByDay[i]
                        if (!names.length || !total) return
                        const x = scales.x.getPixelForValue(i)
                        const y = scales.y.getPixelForValue(total) - ICON_SIZE - 4
                        const startX = x - (names.length * ICON_SIZE) / 2
                        names.forEach((name, j) => {
                            const img = images[name]
                            if (img) ctx.drawImage(img, startX + j * ICON_SIZE, y, ICON_SIZE, ICON_SIZE)
                        })
                    })
                },
            }

            this.chart?.destroy()
            this.chart = new Chart(this.$refs.canvas, {
                type: 'bar',
                plugins: [iconPlugin],
                data: { labels, datasets },
                options: {
                    responsive: true,
                    animation: false,
                    layout: { padding: { top: ICON_SIZE + 8 } },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            ...base.tooltip,
                            callbacks: {
                                label: ctx => {
                                    if (!ctx.parsed.y) return null
                                    // TRANSLATORS: Chart tooltip, e.g. "Running: 45 min"
                                    return t('fit_tracker', '{sport}: {minutes} min', {
                                        sport: sportLabel(ctx.dataset.label),
                                        minutes: ctx.parsed.y,
                                    })
                                },
                            },
                        },
                    },
                    scales: {
                        x: { stacked: true, grid: { display: false }, ticks: base.ticks },
                        y: { stacked: true, beginAtZero: true, ticks: base.ticks, grid: base.grid, border: { color: base.grid.color } },
                    },
                },
            })
        },
        buildWeek() {
            const days = Array.from({ length: 7 }, (_, i) => {
                const d = new Date(this.weekStart)
                d.setDate(d.getDate() + i)
                return d
            })
            const labels = days.map(d => d.toLocaleDateString(undefined, { weekday: 'short', day: 'numeric' }))

            const byDay = days.map(d => this.activities.filter(a => {
                if (!a.startTime) return false
                const ad = new Date(a.startTime)
                return ad.getFullYear() === d.getFullYear()
                    && ad.getMonth() === d.getMonth()
                    && ad.getDate() === d.getDate()
            }))

            const sportsPresent = [...new Set(byDay.flat().map(a => a.sport).filter(Boolean))]

            const datasets = sportsPresent.map(sport => {
                const color = sportColor(sport)
                return {
                    label: sport,
                    data: byDay.map(acts =>
                        Math.round(acts.filter(a => a.sport === sport).reduce((s, a) => s + (a.duration ?? 0), 0) / 60),
                    ),
                    backgroundColor: color + 'cc',
                    borderColor: color,
                    borderWidth: 1,
                    borderRadius: 0,
                    stack: 'day',
                }
            })

            const iconsByDay = byDay.map(acts => [...new Set(acts.map(a => sportIcon(a.sport)))])
            const totalByDay = byDay.map(acts =>
                Math.round(acts.reduce((s, a) => s + (a.duration ?? 0), 0) / 60),
            )

            const build = { labels, datasets, iconsByDay, totalByDay }
            this._latestBuild = build
            return build
        },
    },
}
</script>

<style scoped>
.week-chart {
    flex: 1;
    min-width: 260px;
}
.week-nav {
    margin-bottom: var(--ft-gap-2);
}
</style>
