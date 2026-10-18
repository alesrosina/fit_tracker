<template>
    <div class="activity-charts">
        <h3>{{ t('fit_tracker', 'Charts') }}</h3>
        <div class="charts-grid">
            <div v-if="hasHR" class="chart-wrap">
                <h4 class="chart-label">{{ t('fit_tracker', 'Heart rate (bpm)') }}</h4>
                <canvas ref="hrCanvas" role="img" :aria-label="t('fit_tracker', 'Heart rate (bpm)')"></canvas>
            </div>
            <div v-if="hasAlt" class="chart-wrap">
                <h4 class="chart-label">{{ t('fit_tracker', 'Elevation (m)') }}</h4>
                <canvas ref="altCanvas" role="img" :aria-label="t('fit_tracker', 'Elevation (m)')"></canvas>
            </div>
            <div v-if="hasSpeed" class="chart-wrap">
                <h4 class="chart-label">{{ speedLabel }}</h4>
                <canvas ref="speedCanvas" role="img" :aria-label="speedLabel"></canvas>
            </div>
            <div v-if="hasPower" class="chart-wrap">
                <h4 class="chart-label">{{ t('fit_tracker', 'Power (W)') }}</h4>
                <canvas ref="powerCanvas" role="img" :aria-label="t('fit_tracker', 'Power (W)')"></canvas>
            </div>
            <div v-if="showSpeedKm" class="chart-wrap">
                <h4 class="chart-label">{{ t('fit_tracker', 'Speed per km (km/h)') }}</h4>
                <canvas ref="cyclingSpeedCanvas" role="img" :aria-label="t('fit_tracker', 'Speed per km (km/h)')"></canvas>
            </div>
        </div>
    </div>
</template>

<script>
import { Chart, LineController, LineElement, PointElement, LinearScale, CategoryScale, Filler, Tooltip } from 'chart.js'
import { t } from '@nextcloud/l10n'
import { SPEED_NOT_PACE_SPORTS } from '../sports.js'
import { baseChartOptions } from '../chartTheme.js'
import { formatDurationClock } from '../format.js'
Chart.register(LineController, LineElement, PointElement, LinearScale, CategoryScale, Filler, Tooltip)

const SAMPLE_MAX = 300

function downsample(arr, max) {
    if (arr.length <= max) return arr
    const step = arr.length / max
    const out = []
    for (let i = 0; i < max; i++) {
        out.push(arr[Math.round(i * step)])
    }
    return out
}

// Like downsample(), but averages each bucket instead of picking one point, so single noisy samples don't survive
function downsampleAvg(arr, max) {
    if (arr.length <= max) return arr
    const step = arr.length / max
    const out = []
    for (let i = 0; i < max; i++) {
        let sum = 0, n = 0
        for (let j = Math.floor(i * step); j < Math.floor((i + 1) * step); j++) {
            if (arr[j] !== null) { sum += arr[j]; n++ }
        }
        out.push(n ? sum / n : null)
    }
    return out
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

// Minutes per km → "5:30"
const formatPaceAxis = min => formatDurationClock(min * 60)

function buildChart(canvas, label, data, color, labels = null, { showXAxis = false, y = {}, formatValue = null, fill = true } = {}) {
    const base = baseChartOptions()
    return new Chart(canvas, {
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
        options: {
            responsive: true,
            animation: false,
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
}

export default {
    name: 'ActivityCharts',
    props: {
        trackpoints: { type: Array, required: true },
        sport: { type: String, default: 'running' },
    },
    computed: {
        sampled() { return downsample(this.trackpoints, SAMPLE_MAX) },
        hrData()    { return this.sampled.map(tp => tp.heartRate) },
        altData()   { return this.sampled.map(tp => tp.altitude) },
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
                    const dt = (new Date(curr.timestamp.replace(' ', 'T')) - new Date(prev.timestamp.replace(' ', 'T'))) / 1000
                    if (dt > 0 && dt < 60) speeds[i] = (curr.distance - prev.distance) * this.distScale / dt * 3600
                }
            }
            return speeds
        },
        speedData() {
            // Smooth and average speed first, then invert: averaging pace directly would be dominated by slow samples
            const speeds = downsampleAvg(smooth(this.resolvedSpeed, SPEED_SMOOTH_RADIUS), SAMPLE_MAX)
            if (!this.isPace) return speeds
            // speed is km/h; for running show pace (min/km)
            const paces = speeds.map(speed => speed === null ? null : 60 / Math.max(speed, 0.1))
            const cap = Math.min(PACE_CAP_MAX, Math.ceil(median(paces) * PACE_CAP_FACTOR / 5) * 5)
            return paces.map(p => p === null ? null : Math.min(p, cap))
        },
        powerData() { return this.sampled.map(tp => tp.power) },
        sampledLabels() {
            const tps = this.sampled
            if (!tps.length) return []
            const firstTs = tps[0].timestamp ? new Date(tps[0].timestamp.replace(' ', 'T')) : null
            return tps.map(tp => {
                const parts = []
                if (tp.distance !== null) parts.push(t('fit_tracker', '{km} km', { km: (tp.distance * this.distScale).toFixed(2) }))
                if (firstTs && tp.timestamp) {
                    const elapsed = (new Date(tp.timestamp.replace(' ', 'T')) - firstTs) / 1000
                    if (elapsed >= 0) {
                        const h = Math.floor(elapsed / 3600)
                        const m = Math.floor((elapsed % 3600) / 60)
                        const s = Math.floor(elapsed % 60)
                        parts.push(h > 0
                            ? `${h}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`
                            : `${m}:${String(s).padStart(2,'0')}`)
                    }
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
                ? t('fit_tracker', 'Speed (km/h)')
                : t('fit_tracker', 'Pace (min/km)')
        },
        isRunOrCycle() {
            return this.sport === 'cycling' || this.sport === 'running'
        },
        showSpeedKm() { return this.isRunOrCycle && this.hasSpeed },
    },
    mounted() {
        this.$nextTick(() => this.buildCharts())
    },
    methods: {
        // Returns cumulative distance in km for each trackpoint index.
        cumulativeKm() {
            const tps = this.trackpoints
            // Use tp.distance if any trackpoints have it
            const hasDistField = tps.some(tp => tp.distance !== null)
            if (hasDistField) {
                return tps.map(tp => tp.distance !== null ? tp.distance * this.distScale : null)
            }
            // Fallback: integrate speed (m/s) × Δt (s) → km
            const result = [0]
            let cum = 0
            for (let i = 1; i < tps.length; i++) {
                const prev = tps[i - 1]
                const curr = tps[i]
                if (curr.speed !== null && prev.timestamp && curr.timestamp) {
                    const dt = (new Date(curr.timestamp.replace(' ', 'T')) - new Date(prev.timestamp.replace(' ', 'T'))) / 1000
                    if (dt > 0 && dt < 60) cum += curr.speed * dt / 1000
                }
                result.push(cum)
            }
            return result
        },
        buildKmBuckets(fieldOrValues, transform) {
            const distances = this.cumulativeKm()
            const values = Array.isArray(fieldOrValues) ? fieldOrValues : this.trackpoints.map(tp => tp[fieldOrValues])
            const buckets = {}
            for (let i = 0; i < this.trackpoints.length; i++) {
                const km = distances[i]
                const v = values[i]
                if (km === null || v === null) continue
                const bucket = Math.floor(km)
                if (!buckets[bucket]) buckets[bucket] = []
                buckets[bucket].push(transform(v))
            }
            const keys = Object.keys(buckets).map(Number).sort((a, b) => a - b)
            if (keys.length < 2) return null  // not enough distance data
            return {
                labels: keys.map(k => t('fit_tracker', '{km} km', { km: k })),
                data: keys.map(k => {
                    const vals = buckets[k]
                    return parseFloat((vals.reduce((a, b) => a + b, 0) / vals.length).toFixed(1))
                }),
            }
        },
        buildCharts() {
            const labels = this.sampledLabels
            if (this.hasHR)    buildChart(this.$refs.hrCanvas,    t('fit_tracker', 'Heart rate'), this.hrData,    '#ef4444', labels)
            if (this.hasAlt)   buildChart(this.$refs.altCanvas,   t('fit_tracker', 'Elevation'), this.altData,   '#22c55e', labels)
            if (this.hasSpeed) {
                // Pace: faster is better, so reverse the axis (like Garmin/Strava) and show m:ss
                const paceOpts = this.isPace ? { y: { reverse: true, min: 0 }, formatValue: formatPaceAxis, fill: 'end' } : {}
                buildChart(this.$refs.speedCanvas, this.speedLabel, this.speedData, '#0082c9', labels, paceOpts)
            }
            if (this.hasPower) buildChart(this.$refs.powerCanvas, t('fit_tracker', 'Power'), this.powerData, '#f97316', labels)

            if (this.showSpeedKm && this.$refs.cyclingSpeedCanvas) {
                const result = this.buildKmBuckets(this.resolvedSpeed, v => parseFloat(v.toFixed(1)))
                if (result) buildChart(this.$refs.cyclingSpeedCanvas, t('fit_tracker', 'Speed'), result.data, '#0082c9', result.labels, { showXAxis: true })
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
