<template>
    <div class="ft-page stats-page">
        <h2 class="ft-page-title">{{ t('fit_tracker', 'Trends') }}</h2>

        <LoadingSkeleton v-if="loading" />
        <LoadError
            v-else-if="error"
            :heading="t('fit_tracker', 'Could not load trends')"
            :message="t('fit_tracker', 'Check your connection and try again.')"
            @retry="load"
        />
        <NcEmptyContent
            v-else-if="allActivities.length === 0"
            :name="t('fit_tracker', 'No activities yet')"
            :description="t('fit_tracker', 'Trends appear once activities are imported.')"
        >
            <template #icon>
                <NcIconSvgWrapper :svg="icons.trending_up" />
            </template>
        </NcEmptyContent>

        <template v-else>
            <div class="ft-toolbar">
                <NcSelect
                    v-model="metricOption"
                    class="stats-page__select"
                    :options="metricOptions"
                    :input-label="t('fit_tracker', 'Measure')"
                    :clearable="false"
                    label="label"
                />
                <NcSelect
                    v-model="sportOption"
                    class="stats-page__select"
                    :options="sportOptions"
                    :input-label="t('fit_tracker', 'Sport')"
                    :clearable="false"
                    label="label"
                />
            </div>

            <p class="ft-panel__hint stats-page__intro">
                {{ t('fit_tracker', 'Long-term views. Totals for any timeframe are on the Overview.') }}
                <router-link to="/">{{ t('fit_tracker', 'Open Overview') }}</router-link>
            </p>

            <section class="ft-panel">
                <h3>{{ t('fit_tracker', 'Year over year') }}</h3>
                <p class="ft-panel__hint">{{ t('fit_tracker', '{measure} added up since 1 January, this year compared to the years before.', { measure: metricLabel(metric) }) }}</p>
                <canvas ref="yoyCanvas" role="img" :aria-label="t('fit_tracker', 'Cumulative total this year and last year')"></canvas>
            </section>


            <NcNoteCard v-if="analyzing" type="info">
                {{ n('fit_tracker', 'Analyzing heart rate data of %n older activity…', 'Analyzing heart rate data of %n older activities…', analyzing) }}
            </NcNoteCard>

            <template v-if="training && training.activities.length">
                <section class="ft-panel">
                    <h3>{{ t('fit_tracker', 'Fitness and fatigue') }}</h3>
                    <p class="ft-panel__hint">
                        {{ t('fit_tracker', 'Training load (TRIMP) from heart rate. Fitness is the average load over about six weeks, fatigue over about one week; form is the difference. A ratio of fatigue to fitness between 0.8 and 1.3 is usually a safe rate of building up.') }}
                    </p>
                    <div class="ft-stats">
                        <div class="ft-stat">
                            <div class="ft-stat__label">{{ t('fit_tracker', 'Fitness') }}</div>
                            <div class="ft-stat__value">{{ Math.round(today.ctl) }}</div>
                        </div>
                        <div class="ft-stat">
                            <div class="ft-stat__label">{{ t('fit_tracker', 'Fatigue') }}</div>
                            <div class="ft-stat__value">{{ Math.round(today.atl) }}</div>
                        </div>
                        <div class="ft-stat">
                            <div class="ft-stat__label">{{ t('fit_tracker', 'Form') }}</div>
                            <div class="ft-stat__value">{{ today.tsb > 0 ? '+' : '' }}{{ Math.round(today.tsb) }}</div>
                        </div>
                        <div class="ft-stat">
                            <div class="ft-stat__label">{{ t('fit_tracker', 'Fatigue ÷ fitness') }}</div>
                            <div class="ft-stat__value">{{ ratio === null ? '–' : ratio.toFixed(2) }}</div>
                            <div class="ft-stat__sub">{{ ratioLabel }}</div>
                        </div>
                    </div>
                    <canvas ref="loadCanvas" role="img" :aria-label="t('fit_tracker', 'Daily training load, fitness and fatigue over the last 90 days')"></canvas>
                </section>

                <section class="ft-panel">
                    <h3>{{ t('fit_tracker', 'Time in heart rate zones per week') }}</h3>
                    <p class="ft-panel__hint">
                        {{ t('fit_tracker', 'Zones based on a maximum heart rate of {bpm} bpm.', { bpm: training.settings.maxHr }) }}
                        <template v-if="training.settings.maxHrIsDefault">
                            {{ t('fit_tracker', 'This is your highest recorded heart rate; set your own in the settings.') }}
                        </template>
                    </p>
                    <canvas ref="zonesCanvas" role="img" :aria-label="t('fit_tracker', 'Minutes per heart rate zone per week')"></canvas>
                </section>
            </template>
        </template>
    </div>
</template>

<script>
import { Chart, BarController, BarElement, LineController, LineElement, PointElement, LinearScale, CategoryScale, Tooltip, Legend, Filler } from 'chart.js'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { t } from '@nextcloud/l10n'
import { NcEmptyContent, NcIconSvgWrapper, NcNoteCard, NcSelect } from '@nextcloud/vue'
import LoadingSkeleton from './LoadingSkeleton.vue'
import LoadError from './LoadError.vue'
import { icons } from '../icons.js'
import { sportLabel } from '../sports.js'
import { baseChartOptions } from '../chartTheme.js'
import { navData, fetchActivities } from '../store/navData.js'
import { settings } from '../store/settings.js'
import { ensureAnalyzed } from '../store/analysis.js'
import { parseDate, localDayKey, formatDurationShort } from '../format.js'
import {
    METRICS, metricLabel, metricValue, displayValue, displayUnit,
    periodStart, addPeriods, formatPeriod, fitnessFatigue, dayKey, ZONE_COLORS, zoneLabel,
} from '../stats.js'
Chart.register(BarController, BarElement, LineController, LineElement, PointElement, LinearScale, CategoryScale, Tooltip, Legend, Filler)

const LOAD_DAYS = 90
const ZONE_WEEKS = 12

export default {
    name: 'StatsPage',
    components: { LoadingSkeleton, LoadError, NcEmptyContent, NcIconSvgWrapper, NcNoteCard, NcSelect },
    data() {
        return {
            icons,
            loading: true,
            error: false,
            metricOption: null,
            sportOption: null,
            training: null,
            analyzing: 0,
        }
    },
    computed: {
        allActivities() { return navData.activities },
        metric() { return this.metricOption?.value ?? 'distance' },
        sport() { return this.sportOption?.value ?? '' },
        activities() {
            return this.sport ? this.allActivities.filter(a => a.sport === this.sport) : this.allActivities
        },
        metricOptions() {
            return METRICS.map(m => ({ value: m, label: metricLabel(m) }))
        },
        sportOptions() {
            const sports = [...new Set(this.allActivities.map(a => a.sport))].sort()
            return [{ value: '', label: t('fit_tracker', 'All sports') }, ...sports.map(s => ({ value: s, label: sportLabel(s) }))]
        },
        loadDays() {
            if (!this.training) return []
            const byDay = new Map()
            for (const a of this.training.activities) {
                if (this.sport && a.sport !== this.sport) continue
                const key = localDayKey(a.startTime)
                byDay.set(key, (byDay.get(key) ?? 0) + a.trimp)
            }
            const to = new Date()
            to.setHours(0, 0, 0, 0)
            const from = new Date(to)
            from.setDate(from.getDate() - LOAD_DAYS + 1)
            return fitnessFatigue(byDay, from, to)
        },
        today() {
            return this.loadDays[this.loadDays.length - 1] ?? { ctl: 0, atl: 0, tsb: 0 }
        },
        ratio() {
            return this.today.ctl > 1 ? this.today.atl / this.today.ctl : null
        },
        ratioLabel() {
            if (this.ratio === null) return ''
            if (this.ratio < 0.8) return t('fit_tracker', 'Training less than usual')
            if (this.ratio <= 1.3) return t('fit_tracker', 'Steady build-up')
            if (this.ratio <= 1.5) return t('fit_tracker', 'Ramping up fast')
            return t('fit_tracker', 'High risk of overdoing it')
        },
        chartKey() {
            // Everything the charts depend on; changing any of it redraws them
            return [this.metric, this.sport, settings.units, this.allActivities.length, !!this.training].join('|')
        },
    },
    watch: {
        chartKey() {
            this.$nextTick(() => this.buildCharts())
        },
    },
    created() {
        this.charts = {}
    },
    async mounted() {
        this.metricOption = this.metricOptions[0]
        this.sportOption = this.sportOptions[0]
        await this.load()
    },
    beforeUnmount() {
        Object.values(this.charts).forEach(c => c.destroy())
    },
    methods: {
        metricLabel,
        formatDurationShort,
        sportLabel,
        async load() {
            this.loading = navData.activities.length === 0
            this.error = false
            try {
                await fetchActivities()
                this.sportOption = this.sportOptions.find(o => o.value === this.sport) ?? this.sportOptions[0]
            } catch (e) {
                this.error = true
                return
            } finally {
                this.loading = false
            }
            this.$nextTick(() => this.buildCharts())
            try {
                await ensureAnalyzed(remaining => { this.analyzing = remaining })
            } catch (e) {
                // Load still works for what's already analyzed
            }
            this.analyzing = 0
            try {
                const { data } = await axios.get(generateUrl('/apps/fit_tracker/api/stats/load'))
                this.training = data
            } catch (e) {
                this.training = null
            }
        },
        replaceChart(name, canvas, config) {
            this.charts[name]?.destroy()
            delete this.charts[name]
            if (canvas && config) this.charts[name] = new Chart(canvas, config)
        },
        buildCharts() {
            if (this.loading || this.error) return
            this.buildYoyChart()
            this.buildLoadChart()
            this.buildZonesChart()
        },
        axisOptions(base, stacked = false, yFormat = null) {
            return {
                x: { stacked, ticks: { ...base.ticks, maxRotation: 0, autoSkip: true }, grid: { display: false } },
                y: {
                    stacked,
                    beginAtZero: true,
                    ticks: { ...base.ticks, ...(yFormat ? { callback: yFormat } : {}) },
                    grid: base.grid,
                    border: { color: base.grid.color },
                },
            }
        },
        legendOptions(base) {
            return { position: 'bottom', labels: { color: base.theme.mainText, font: base.ticks.font, boxWidth: 12, padding: 12 } }
        },
        // A value already in display units, e.g. 12.3 km or 4.5 h
        formatDisplay(v) {
            if (this.metric === 'duration') return formatDurationShort(v * 3600)
            if (this.metric === 'count') return String(Math.round(v))
            return `${v.toFixed(1)} ${displayUnit(this.metric)}`
        },
        buildYoyChart() {
            const base = baseChartOptions()
            const year = new Date().getFullYear()
            const years = [year, year - 1, year - 2].filter(y => this.activities.some(a => parseDate(a.startTime).getFullYear() === y))
            const colors = ['#0082c9', '#94a3b8', '#cbd5e1']
            // Day-of-year axis, labelled with the current year's dates
            const days = Array.from({ length: 366 }, (_, i) => new Date(year, 0, 1 + i))
            const datasets = years.map((y, k) => {
                const perDay = new Array(366).fill(0)
                for (const a of this.activities) {
                    const d = parseDate(a.startTime)
                    if (d.getFullYear() !== y) continue
                    const doy = Math.floor((new Date(y, d.getMonth(), d.getDate()) - new Date(y, 0, 1)) / 86400000)
                    perDay[doy] += metricValue(a, this.metric)
                }
                let sum = 0
                const today = Math.floor((new Date() - new Date(year, 0, 1)) / 86400000)
                const data = perDay.map((v, i) => {
                    sum += v
                    return y === year && i > today ? null : displayValue(sum, this.metric)
                })
                return {
                    label: String(y),
                    data,
                    borderColor: colors[k],
                    backgroundColor: colors[k],
                    borderWidth: k === 0 ? 2.5 : 1.5,
                    pointRadius: 0,
                    tension: 0.2,
                }
            })
            this.replaceChart('yoy', this.$refs.yoyCanvas, {
                type: 'line',
                data: { labels: days.map(d => d.toLocaleDateString(undefined, { day: 'numeric', month: 'short' })), datasets },
                options: {
                    responsive: true,
                    // Full-width panels: wider than Chart.js' default 2:1
                    aspectRatio: 3,
                    animation: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: this.legendOptions(base),
                        tooltip: { ...base.tooltip, callbacks: { label: ctx => `${ctx.dataset.label}: ${this.formatDisplay(ctx.parsed.y)}` } },
                    },
                    scales: {
                        x: { ticks: { ...base.ticks, maxTicksLimit: 12, maxRotation: 0 }, grid: { display: false } },
                        y: { beginAtZero: true, ticks: base.ticks, grid: base.grid, border: { color: base.grid.color } },
                    },
                },
            })
        },
        buildLoadChart() {
            const base = baseChartOptions()
            const days = this.loadDays
            this.replaceChart('load', this.$refs.loadCanvas, days.length && {
                data: {
                    labels: days.map(d => d.date.toLocaleDateString(undefined, { day: 'numeric', month: 'short' })),
                    datasets: [
                        { type: 'line', label: t('fit_tracker', 'Fitness'), data: days.map(d => d.ctl), borderColor: '#0082c9', backgroundColor: '#0082c9', pointRadius: 0, borderWidth: 2, tension: 0.3, yAxisID: 'y' },
                        { type: 'line', label: t('fit_tracker', 'Fatigue'), data: days.map(d => d.atl), borderColor: '#ef4444', backgroundColor: '#ef4444', pointRadius: 0, borderWidth: 1.5, tension: 0.3, yAxisID: 'y' },
                        { type: 'bar', label: t('fit_tracker', 'Daily load'), data: days.map(d => d.load || null), backgroundColor: '#94a3b880', yAxisID: 'y' },
                    ],
                },
                options: {
                    responsive: true,
                    // Full-width panels: wider than Chart.js' default 2:1
                    aspectRatio: 3,
                    animation: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: this.legendOptions(base),
                        tooltip: { ...base.tooltip, callbacks: { label: ctx => ctx.parsed.y === null ? null : `${ctx.dataset.label}: ${Math.round(ctx.parsed.y)}` } },
                    },
                    scales: {
                        x: { ticks: { ...base.ticks, maxTicksLimit: 9, maxRotation: 0 }, grid: { display: false } },
                        y: { beginAtZero: true, ticks: base.ticks, grid: base.grid, border: { color: base.grid.color } },
                    },
                },
            })
        },
        buildZonesChart() {
            if (!this.training) return
            const base = baseChartOptions()
            const last = periodStart(new Date(), 'week')
            const starts = Array.from({ length: ZONE_WEEKS }, (_, i) => addPeriods(last, 'week', i - ZONE_WEEKS + 1))
            const index = new Map(starts.map((s, i) => [dayKey(s), i]))
            const minutes = Array.from({ length: 5 }, () => new Array(ZONE_WEEKS).fill(0))
            for (const a of this.training.activities) {
                if (this.sport && a.sport !== this.sport) continue
                const i = index.get(dayKey(periodStart(parseDate(a.startTime), 'week')))
                if (i === undefined) continue
                a.zones.forEach((s, z) => { minutes[z][i] += s / 60 })
            }
            this.replaceChart('zones', this.$refs.zonesCanvas, {
                type: 'bar',
                data: {
                    labels: starts.map(s => formatPeriod(s, 'week')),
                    datasets: minutes.map((data, z) => ({ label: zoneLabel(z), data, backgroundColor: ZONE_COLORS[z] })),
                },
                options: {
                    responsive: true,
                    // Full-width panels: wider than Chart.js' default 2:1
                    aspectRatio: 3,
                    animation: false,
                    plugins: {
                        legend: this.legendOptions(base),
                        tooltip: { ...base.tooltip, callbacks: { label: ctx => `${ctx.dataset.label}: ${formatDurationShort(ctx.parsed.y * 60)}` } },
                    },
                    scales: this.axisOptions(base, true, v => formatDurationShort(v * 60)),
                },
            })
        },
    },
}
</script>

<style scoped>
.stats-page__select {
    min-width: 180px;
}
.up {
    color: var(--color-success-text);
}
.down {
    color: var(--color-error-text);
}
</style>
