<template>
    <div class="activity-charts">
        <h3>Charts</h3>
        <div class="charts-grid">
            <div v-if="hasHR" class="chart-wrap">
                <div class="chart-label">Heart Rate (bpm)</div>
                <canvas ref="hrCanvas"></canvas>
            </div>
            <div v-if="hasAlt" class="chart-wrap">
                <div class="chart-label">Elevation (m)</div>
                <canvas ref="altCanvas"></canvas>
            </div>
            <div v-if="hasSpeed" class="chart-wrap">
                <div class="chart-label">{{ speedLabel }}</div>
                <canvas ref="speedCanvas"></canvas>
            </div>
            <div v-if="hasPower" class="chart-wrap">
                <div class="chart-label">Power (W)</div>
                <canvas ref="powerCanvas"></canvas>
            </div>
            <div v-if="showSpeedKm" class="chart-wrap">
                <div class="chart-label">Speed per km (km/h)</div>
                <canvas ref="cyclingSpeedCanvas"></canvas>
            </div>
        </div>
    </div>
</template>

<script>
import { Chart, LineController, LineElement, PointElement, LinearScale, CategoryScale, Filler, Tooltip } from 'chart.js'
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

function buildChart(canvas, label, data, color, labels = null, showXAxis = false) {
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
                fill: true,
                tension: 0.3,
            }],
        },
        options: {
            responsive: true,
            animation: false,
            plugins: { tooltip: { mode: 'index', intersect: false }, legend: { display: false } },
            scales: {
                x: {
                    display: showXAxis,
                    ticks: { maxTicksLimit: 10, font: { size: 10 } },
                },
                y: { grid: { color: 'rgba(0,0,0,0.05)' } },
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
        // Speed in km/h for every trackpoint: use recorded value (already km/h), fallback to distance(km) deltas
        resolvedSpeed() {
            const tps = this.trackpoints
            if (tps.some(tp => tp.speed !== null)) return tps.map(tp => tp.speed)
            const speeds = new Array(tps.length).fill(null)
            for (let i = 1; i < tps.length; i++) {
                const prev = tps[i - 1], curr = tps[i]
                if (curr.distance !== null && prev.distance !== null && curr.timestamp && prev.timestamp) {
                    const dt = (new Date(curr.timestamp.replace(' ', 'T')) - new Date(prev.timestamp.replace(' ', 'T'))) / 1000
                    if (dt > 0 && dt < 60) speeds[i] = (curr.distance - prev.distance) / dt * 3600
                }
            }
            return speeds
        },
        speedData() {
            return downsample(this.resolvedSpeed, SAMPLE_MAX).map(speed => {
                if (speed === null) return null
                // speed is km/h; for running show pace (min/km)
                return ['cycling', 'skiing'].includes(this.sport) ? speed : 60 / (speed || 0.001)
            })
        },
        powerData() { return this.sampled.map(tp => tp.power) },
        sampledLabels() {
            const tps = this.sampled
            if (!tps.length) return []
            const firstTs = tps[0].timestamp ? new Date(tps[0].timestamp.replace(' ', 'T')) : null
            return tps.map(tp => {
                const parts = []
                if (tp.distance !== null) parts.push((tp.distance / 1000).toFixed(2) + ' km')
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
        speedLabel() {
            return ['cycling', 'skiing'].includes(this.sport) ? 'Speed (km/h)' : 'Pace (min/km)'
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
                const maxDist = tps.reduce((m, tp) => Math.max(m, tp.distance ?? 0), 0)
                const scale = maxDist > 500 ? 1 / 1000 : 1  // meters → km
                return tps.map(tp => tp.distance !== null ? tp.distance * scale : null)
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
                labels: keys.map(k => k + ' km'),
                data: keys.map(k => {
                    const vals = buckets[k]
                    return parseFloat((vals.reduce((a, b) => a + b, 0) / vals.length).toFixed(1))
                }),
            }
        },
        buildCharts() {
            const labels = this.sampledLabels
            if (this.hasHR)    buildChart(this.$refs.hrCanvas,    'HR',    this.hrData,    '#ef4444', labels)
            if (this.hasAlt)   buildChart(this.$refs.altCanvas,   'Alt',   this.altData,   '#22c55e', labels)
            if (this.hasSpeed) buildChart(this.$refs.speedCanvas, 'Speed', this.speedData, '#0082c9', labels)
            if (this.hasPower) buildChart(this.$refs.powerCanvas, 'Power', this.powerData, '#f97316', labels)

            if (this.showSpeedKm && this.$refs.cyclingSpeedCanvas) {
                const result = this.buildKmBuckets(this.resolvedSpeed, v => parseFloat(v.toFixed(1)))
                if (result) buildChart(this.$refs.cyclingSpeedCanvas, 'Speed/km', result.data, '#0082c9', result.labels, true)
            }
        },
    },
}
</script>

<style scoped>
.charts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 20px;
}
.chart-wrap {
    border: 1px solid var(--color-border);
    border-radius: 8px;
    padding: 12px;
    background: var(--color-main-background);
}
.chart-label {
    font-size: 12px;
    font-weight: 600;
    color: var(--color-text-maxcontrast);
    margin-bottom: 8px;
    text-transform: uppercase;
}
</style>
