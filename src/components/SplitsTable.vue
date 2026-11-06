<template>
    <section v-if="splits.length > 1" class="splits">
        <h3>{{ isImperial ? t('fit_tracker', 'Splits per mile') : t('fit_tracker', 'Splits per km') }}</h3>
        <table class="ft-table">
            <thead>
                <tr>
                    <th>{{ distanceUnit }}</th>
                    <th>{{ t('fit_tracker', 'Time') }}</th>
                    <th>{{ usesSpeed ? t('fit_tracker', 'Speed') : t('fit_tracker', 'Pace') }}</th>
                    <th v-if="hasHr">{{ t('fit_tracker', 'Average heart rate') }}</th>
                    <th v-if="hasAlt">{{ t('fit_tracker', 'Elevation') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="s in splits" :key="s.index" :class="{ 'splits__best': s.index === bestIndex }">
                    <td :data-label="distanceUnit">{{ s.label }}</td>
                    <td :data-label="t('fit_tracker', 'Time')">{{ formatDurationClock(s.seconds) }}</td>
                    <td :data-label="usesSpeed ? t('fit_tracker', 'Speed') : t('fit_tracker', 'Pace')" class="splits__pace">
                        <span class="splits__bar" :style="{ width: s.barPct + '%' }" />
                        <span class="splits__pace-text">{{ usesSpeed ? formatSpeed(s.kmh) : formatPace(s.kmh) }}</span>
                    </td>
                    <td v-if="hasHr" :data-label="t('fit_tracker', 'Average heart rate')">{{ s.hr ? t('fit_tracker', '{bpm} bpm', { bpm: s.hr }) : '–' }}</td>
                    <td v-if="hasAlt" :data-label="t('fit_tracker', 'Elevation')">{{ formatElevationChange(s.up, s.down) }}</td>
                </tr>
            </tbody>
        </table>
    </section>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { SPEED_NOT_PACE_SPORTS } from '../sports.js'
import { parseDate, formatDurationClock, formatPace, formatSpeed, formatElevation, splitKm, isImperial, distanceUnit, distanceValue } from '../format.js'

// Time per kilometer (or mile), computed from the trackpoints, for any
// activity with distance data, whether or not the device recorded laps.
export default {
    name: 'SplitsTable',
    props: {
        trackpoints: { type: Array, required: true },
        sport: { type: String, default: '' },
    },
    computed: {
        isImperial,
        distanceUnit,
        usesSpeed() { return SPEED_NOT_PACE_SPORTS.includes(this.sport) },
        hasHr() { return this.splits.some(s => s.hr) },
        hasAlt() { return this.splits.some(s => s.up || s.down) },
        splits() {
            const unit = splitKm()
            const pts = this.trackpoints.filter(tp => tp.distance !== null && tp.timestamp)
            if (pts.length < 2) return []
            const out = []
            let start = 0
            let next = unit
            let hrSum = 0, hrN = 0, up = 0, down = 0
            const finish = (i, partial) => {
                const a = pts[start], b = pts[i]
                const seconds = (parseDate(b.timestamp) - parseDate(a.timestamp)) / 1000
                const km = b.distance - a.distance
                if (seconds > 0 && km > 0) {
                    out.push({
                        index: out.length,
                        label: partial ? distanceValue(b.distance).toFixed(2) : String(out.length + 1),
                        seconds,
                        kmh: km / seconds * 3600,
                        partial,
                        hr: hrN ? Math.round(hrSum / hrN) : null,
                        up,
                        down,
                    })
                }
                start = i
                hrSum = 0; hrN = 0; up = 0; down = 0
            }
            for (let i = 1; i < pts.length; i++) {
                const tp = pts[i]
                if (tp.heartRate) { hrSum += tp.heartRate; hrN++ }
                const prevAlt = pts[i - 1].altitude
                if (tp.altitude !== null && prevAlt !== null) {
                    const d = tp.altitude - prevAlt
                    if (d > 0) up += d
                    else down -= d
                }
                if (tp.distance >= next) {
                    finish(i, false)
                    next += unit
                }
            }
            // The rest after the last full split, if it's more than a few meters
            if (pts[pts.length - 1].distance - pts[start].distance > 0.05 * unit) finish(pts.length - 1, true)

            const speeds = out.filter(s => !s.partial).map(s => s.kmh)
            const max = Math.max(...speeds, ...out.map(s => s.kmh))
            const min = Math.min(...out.map(s => s.kmh))
            for (const s of out) {
                // Bar length relative to the range, so differences between splits stand out
                s.barPct = max > min ? 15 + 85 * (s.kmh - min) / (max - min) : 100
            }
            return out
        },
        bestIndex() {
            const full = this.splits.filter(s => !s.partial)
            if (full.length < 2) return null
            return full.reduce((best, s) => s.kmh > best.kmh ? s : best).index
        },
    },
    methods: {
        formatDurationClock,
        formatPace,
        formatSpeed,
        formatElevationChange(up, down) {
            if (!up && !down) return '–'
            return `${formatElevation(up, true)} / −${formatElevation(down)}`
        },
    },
}
</script>

<style scoped>
.splits {
    margin-bottom: var(--ft-gap-6);
}
.splits__pace {
    position: relative;
    min-width: 140px;
}
.splits__bar {
    position: absolute;
    inset-block: var(--ft-gap-1);
    inset-inline-start: 0;
    background: var(--color-primary-element-light);
    border-radius: var(--border-radius-small);
}
.splits__pace-text {
    position: relative;
    padding-inline-start: var(--ft-gap-2);
}
.splits__best td {
    font-weight: bold;
}
</style>
