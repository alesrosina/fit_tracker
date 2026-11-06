<template>
    <div class="activity-charts" @mouseleave="$emit('hover', null)">
        <h3>{{ t('fit_tracker', 'Charts') }}</h3>
        <div class="charts-grid">
            <div v-if="hasHR" class="chart-wrap">
                <h4 class="chart-label">{{ t('fit_tracker', 'Heart rate (bpm)') }}</h4>
                <canvas ref="hrCanvas" role="img" :aria-label="t('fit_tracker', 'Heart rate (bpm)')"></canvas>
            </div>
            <div v-if="hasAlt" class="chart-wrap">
                <h4 class="chart-label">{{ elevationLabel }}</h4>
                <canvas ref="altCanvas" role="img" :aria-label="elevationLabel"></canvas>
            </div>
            <div v-if="hasSpeed" class="chart-wrap">
                <h4 class="chart-label">{{ speedLabel }}</h4>
                <canvas ref="speedCanvas" role="img" :aria-label="speedLabel"></canvas>
            </div>
            <div v-if="hasPower" class="chart-wrap">
                <h4 class="chart-label">{{ t('fit_tracker', 'Power (W)') }}</h4>
                <canvas ref="powerCanvas" role="img" :aria-label="t('fit_tracker', 'Power (W)')"></canvas>
            </div>
            <div v-if="showSpeedPerUnit" class="chart-wrap">
                <h4 class="chart-label">{{ speedPerUnitLabel }}</h4>
                <canvas ref="cyclingSpeedCanvas" role="img" :aria-label="speedPerUnitLabel"></canvas>
            </div>
        </div>
    </div>
</template>

<script>
import { Chart, LineController, LineElement, PointElement, LinearScale, CategoryScale, Filler, Tooltip } from 'chart.js'
import { t } from '@nextcloud/l10n'
import { SPEED_NOT_PACE_SPORTS } from '../sports.js'
import { baseChartOptions } from '../chartTheme.js'
import {
    parseDate, formatMinutes, formatDurationClock, isImperial, splitKm,
    distanceValue, elevationValue, speedValue, paceValue, speedUnit, paceUnit, elevationUnit, distanceUnit,
} from '../format.js'
Chart.register(LineController, LineElement, PointElement, LinearScale, CategoryScale, Filler, Tooltip)

const SAMPLE_MAX = 300

// Indices of the trackpoints kept when showing at most `max` points
function sampleIndices(length, max) {
    if (length <= max) return Array.from({ length }, (_, i) => i)
    const step = length / max
    return Array.from({ length: max }, (_, i) => Math.round(i * step))
}

// Averages each bucket between sampled indices instead of picking one point,
// so single noisy samples don't survive
function downsampleAvg(arr, indices) {
    if (indices.length === arr.length) return arr
    return indices.map((start, i) => {
        const end = indices[i + 1] ?? arr.length
        let sum = 0, n = 0
        for (let j = start; j < end; j++) {
            if (arr[j] !== null) { sum += arr[j]; n++ }
        }
        return n ? sum / n : null
    })
}

// Centered moving average ignoring nulls; smooths per-second GPS jitter
function smooth(arr, radius) {
    return arr.map((v, i) => {
        if (v === null) return null
        let sum = 0, n = 0
        for (let j = Math.max(0, i - radius); j <= Math.min(arr.length - 1, i + radius); j++) {
            if (arr[j] !== null) { sum += arr[j]; n++ }
        }
        return sum / n
    })
}

const SPEED_SMOOTH_RADIUS = 5
// Pace is clamped to a multiple of the median pace, so stops show as a dip to the axis floor instead of
// a spike towards infinity. Median (not a high quantile) keeps working when an activity has lots of standing.
const PACE_CAP_FACTOR = 3
const PACE_CAP_MAX = 60

function median(values) {
    const sorted = values.filter(v => v !== null).sort((a, b) => a - b)
    return sorted.length ? sorted[Math.floor(sorted.length / 2)] : null
}

// Draws a vertical line at chart.$highlight (a data index), the same moment
// highlighted in every chart and on the map
const crosshairPlugin = {
    id: 'ftCrosshair',
    afterDatasetsDraw(chart) {
        const index = chart.$highlight
        if (index === null || index === undefined) return
        const x = chart.scales.x.getPixelForValue(index)
        if (!Number.isFinite(x)) return
        const { top, bottom } = chart.chartArea
        const ctx = chart.ctx
        ctx.save()
        ctx.strokeStyle = chart.$crosshairColor ?? '#888'
        ctx.lineWidth = 1
        ctx.beginPath()
        ctx.moveTo(x, top)
        ctx.lineTo(x, bottom)
        ctx.stroke()
        ctx.restore()
    },
}

export default {
    name: 'ActivityCharts',
    props: {
        trackpoints: { type: Array, required: true },
        sport: { type: String, default: 'running' },
        // Trackpoint index to mark with a vertical line (hovered here or on the map)
        highlightIndex: { type: Number, default: null },
    },
    emits: ['hover'],
    created() {
        // Not in data(): Chart.js instances must not become reactive proxies
        this.charts = []
    },
    computed: {
        sampleIdx() { return sampleIndices(this.trackpoints.length, SAMPLE_MAX) },
        sampled() { return this.sampleIdx.map(i => this.trackpoints[i]) },
        hrData()    { return this.sampled.map(tp => tp.heartRate) },
        altData()   { return this.sampled.map(tp => tp.altitude === null ? null : elevationValue(tp.altitude)) },
        // Factor converting tp.distance to km: FIT parser (metric) stores km, but tolerate meters too
        distScale() {
            const maxDist = this.trackpoints.reduce((m, tp) => Math.max(m, tp.distance ?? 0), 0)
            return maxDist > 500 ? 1 / 1000 : 1
        },
        // Speed in km/h for every trackpoint: use recorded value (already km/h), fallback to distance deltas
        resolvedSpeed() {
            const tps = this.trackpoints
            if (tps.some(tp => tp.speed !== null)) return tps.map(tp => tp.speed)
            const speeds = new Array(tps.length).fill(null)
            for (let i = 1; i < tps.length; i++) {
                const prev = tps[i - 1], curr = tps[i]
                if (curr.distance !== null && prev.distance !== null && curr.timestamp && prev.timestamp) {
                    const dt = (parseDate(curr.timestamp) - parseDate(prev.timestamp)) / 1000
                    if (dt > 0 && dt < 60) speeds[i] = (curr.distance - prev.distance) * this.distScale / dt * 3600
                }
            }
            return speeds
        },
        speedData() {
            // Smooth and average speed first, then invert: averaging pace directly would be dominated by slow samples
            const speeds = downsampleAvg(smooth(this.resolvedSpeed, SPEED_SMOOTH_RADIUS), this.sampleIdx)
            if (!this.isPace) return speeds.map(s => s === null ? null : speedValue(s))
            const paces = speeds.map(speed => speed === null ? null : paceValue(Math.max(speed, 0.1)))
            const cap = Math.min(PACE_CAP_MAX, Math.ceil(median(paces) * PACE_CAP_FACTOR / 5) * 5)
            return paces.map(p => p === null ? null : Math.min(p, cap))
        },
        powerData() { return this.sampled.map(tp => tp.power) },
        sampledLabels() {
            const tps = this.sampled
            if (!tps.length) return []
            const firstTs = tps[0].timestamp ? parseDate(tps[0].timestamp) : null
            return tps.map(tp => {
                const parts = []
                if (tp.distance !== null) {
                    parts.push(`${distanceValue(tp.distance * this.distScale).toFixed(2)} ${distanceUnit()}`)
                }
                if (firstTs && tp.timestamp) {
                    const elapsed = (parseDate(tp.timestamp) - firstTs) / 1000
                    if (elapsed >= 0) parts.push(formatDurationClock(Math.floor(elapsed)))
                }
                return parts.join(' | ') || null
            })
        },
        hasHR()    { return this.hrData.some(v => v !== null) },
        hasAlt()   { return this.altData.some(v => v !== null) },
        hasSpeed() { return !['gym', 'breathwork', 'meditation'].includes(this.sport) && this.resolvedSpeed.some(v => v !== null) },
        hasPower() { return this.powerData.some(v => v !== null) },
        isPace() { return !SPEED_NOT_PACE_SPORTS.includes(this.sport) },
        speedLabel() {
            return !this.isPace
                ? t('fit_tracker', 'Speed ({unit})', { unit: speedUnit() })
                : t('fit_tracker', 'Pace ({unit})', { unit: paceUnit() })
        },
        elevationLabel() {
            return t('fit_tracker', 'Elevation ({unit})', { unit: elevationUnit() })
        },
        speedPerUnitLabel() {
            return isImperial()
                ? t('fit_tracker', 'Speed per mile ({unit})', { unit: speedUnit() })
                : t('fit_tracker', 'Speed per km ({unit})', { unit: speedUnit() })
        },
        isRunOrCycle() {
            return this.sport === 'cycling' || this.sport === 'running'
        },
        showSpeedPerUnit() { return this.isRunOrCycle && this.hasSpeed },
    },
    watch: {
        highlightIndex(index) {
            const sample = index === null ? null : this.nearestSample(index)
            for (const chart of this.charts) {
                if (chart.$timeline && chart.$highlight !== sample) {
                    chart.$highlight = sample
                    chart.draw()
                }
            }
        },
    },
    mounted() {
        this.$nextTick(() => this.buildCharts())
    },
    beforeUnmount() {
        this.charts.forEach(c => c.destroy())
    },
    methods: {
        nearestSample(tpIndex) {
            const idx = this.sampleIdx
            let lo = 0, hi = idx.length - 1
            while (lo < hi) {
                const mid = (lo + hi) >> 1
                if (idx[mid] < tpIndex) lo = mid + 1
                else hi = mid
            }
            return lo
        },
        // timeline: x axis is the sampled trackpoints, so hovering maps to a position on the route
        buildChart(canvas, label, data, color, labels, { showXAxis = false, y = {}, formatValue = null, fill = true, timeline = true } = {}) {
            const base = baseChartOptions()
            const chart = new Chart(canvas, {
                type: 'line',
                data: {
                    labels: labels ?? data.map((_, i) => i),
                    datasets: [{
                        label,
                        data,
                        borderColor: color,
                        backgroundColor: color + '33',
                        borderWidth: 1.5,
                        pointRadius: 0,
                        fill,
                        tension: 0.3,
                        spanGaps: true,
                    }],
                },
                plugins: [crosshairPlugin],
                options: {
                    responsive: true,
                    animation: false,
                    interaction: { mode: 'index', intersect: false },
                    onHover: timeline
                        ? (_, elements) => {
                            if (elements.length) this.$emit('hover', this.sampleIdx[elements[0].index])
                        }
                        : undefined,
                    plugins: {
                        tooltip: {
                            ...base.tooltip,
                            mode: 'index',
                            intersect: false,
                            callbacks: formatValue ? { label: ctx => `${label}: ${formatValue(ctx.parsed.y)}` } : {},
                        },
                        legend: { display: false },
                    },
                    scales: {
                        x: {
                            display: showXAxis,
                            ticks: { ...base.ticks, maxTicksLimit: 10 },
                            grid: base.grid,
                            border: { color: base.grid.color },
                        },
                        y: {
                            ...y,
                            ticks: { ...base.ticks, ...(formatValue ? { callback: formatValue } : {}) },
                            grid: base.grid,
                            border: { color: base.grid.color },
                        },
                    },
                },
            })
            chart.$timeline = timeline
            chart.$crosshairColor = base.theme.text
            this.charts.push(chart)
            return chart
        },
        // Returns cumulative distance in km for each trackpoint index.
        cumulativeKm() {
            const tps = this.trackpoints
            // Use tp.distance if any trackpoints have it
            const hasDistField = tps.some(tp => tp.distance !== null)
            if (hasDistField) {
                return tps.map(tp => tp.distance !== null ? tp.distance * this.distScale : null)
            }
            // Fallback: integrate speed (km/h) × Δt (s) → km
            const result = [0]
            let cum = 0
            for (let i = 1; i < tps.length; i++) {
                const prev = tps[i - 1]
                const curr = tps[i]
                if (curr.speed !== null && prev.timestamp && curr.timestamp) {
                    const dt = (parseDate(curr.timestamp) - parseDate(prev.timestamp)) / 1000
                    if (dt > 0 && dt < 60) cum += curr.speed * dt / 3600
                }
                result.push(cum)
            }
            return result
        },
        // Average of values per full km (or mile)
        buildUnitBuckets(values) {
            const distances = this.cumulativeKm()
            const unit = splitKm()
            const buckets = {}
            for (let i = 0; i < this.trackpoints.length; i++) {
                const km = distances[i]
                const v = values[i]
                if (km === null || v === null) continue
                const bucket = Math.floor(km / unit)
                if (!buckets[bucket]) buckets[bucket] = []
                buckets[bucket].push(v)
            }
            const keys = Object.keys(buckets).map(Number).sort((a, b) => a - b)
            if (keys.length < 2) return null  // not enough distance data
            return {
                labels: keys.map(k => `${k} ${distanceUnit()}`),
                data: keys.map(k => {
                    const vals = buckets[k]
                    return parseFloat(speedValue(vals.reduce((a, b) => a + b, 0) / vals.length).toFixed(1))
                }),
            }
        },
        buildCharts() {
            const labels = this.sampledLabels
            if (this.hasHR)    this.buildChart(this.$refs.hrCanvas,    t('fit_tracker', 'Heart rate'), this.hrData,    '#ef4444', labels)
            if (this.hasAlt)   this.buildChart(this.$refs.altCanvas,   t('fit_tracker', 'Elevation'), this.altData,   '#22c55e', labels)
            if (this.hasSpeed) {
                // Pace: faster is better, so reverse the axis (like Garmin/Strava) and show m:ss
                const paceOpts = this.isPace ? { y: { reverse: true, min: 0 }, formatValue: formatMinutes, fill: 'end' } : {}
                this.buildChart(this.$refs.speedCanvas, this.speedLabel, this.speedData, '#0082c9', labels, paceOpts)
            }
            if (this.hasPower) this.buildChart(this.$refs.powerCanvas, t('fit_tracker', 'Power'), this.powerData, '#f97316', labels)

            if (this.showSpeedPerUnit && this.$refs.cyclingSpeedCanvas) {
                const result = this.buildUnitBuckets(this.resolvedSpeed)
                if (result) {
                    this.buildChart(this.$refs.cyclingSpeedCanvas, t('fit_tracker', 'Speed'), result.data, '#0082c9', result.labels, { showXAxis: true, timeline: false })
                }
            }
        },
    },
}
</script>

<style scoped>
.activity-charts {
    margin-bottom: var(--ft-gap-6);
}
.charts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(380px, 100%), 1fr));
    gap: var(--ft-gap-5);
}
.chart-wrap {
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-container);
    padding: var(--ft-gap-3);
    background: var(--color-main-background);
}
.chart-label {
    margin: 0 0 var(--ft-gap-2);
    font-size: var(--font-size-small);
    font-weight: bold;
    color: var(--color-text-maxcontrast);
}
</style>
