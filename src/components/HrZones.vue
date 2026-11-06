<template>
    <section class="hr-zones">
        <h3>{{ t('fit_tracker', 'Heart rate zones') }}</h3>
        <div class="hr-zones__grid">
            <div class="hr-zones__bars">
                <div v-for="z in zones" :key="z.index" class="zone-row">
                    <span class="zone-row__label">
                        <span class="zone-row__name">{{ z.label }}</span>
                        <span class="zone-row__range">{{ z.range }}</span>
                    </span>
                    <span class="zone-row__track">
                        <span class="zone-row__fill" :style="{ width: z.pct + '%', background: z.color }" />
                    </span>
                    <span class="zone-row__time">{{ z.time }}</span>
                    <span class="zone-row__pct">{{ Math.round(z.pct) }} %</span>
                </div>
            </div>
            <div class="hr-zones__summary">
                <div class="stat">
                    <div class="stat__label">{{ t('fit_tracker', 'Training load') }}</div>
                    <div class="stat__value">{{ Math.round(trimp) }}</div>
                    <div class="stat__hint">{{ t('fit_tracker', 'TRIMP') }}</div>
                </div>
            </div>
        </div>
        <p v-if="settings.maxHrIsDefault" class="hr-zones__hint">
            {{ t('fit_tracker', 'Zones are based on your highest recorded heart rate, {bpm} bpm. Set your maximum heart rate in the settings for more accurate zones.', { bpm: settings.maxHr }) }}
        </p>
    </section>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { formatDurationShort } from '../format.js'
import { ZONE_COLORS, zoneLabel } from '../stats.js'

// Time in each heart rate zone for one activity, from the per-bpm histogram
// stored at import (AnalysisService.php), with zone bounds from the server
// (StatsService::heartRateSettings).
export default {
    name: 'HrZones',
    props: {
        histogram: { type: Object, required: true },
        settings: { type: Object, required: true },
    },
    computed: {
        zoneSeconds() {
            const bounds = this.settings.zoneBounds
            const zones = [0, 0, 0, 0, 0]
            for (const [bpm, seconds] of Object.entries(this.histogram)) {
                for (let z = 4; z >= 0; z--) {
                    if (Number(bpm) >= bounds[z]) {
                        zones[z] += seconds
                        break
                    }
                }
            }
            return zones
        },
        zones() {
            const total = this.zoneSeconds.reduce((a, b) => a + b, 0) || 1
            const bounds = this.settings.zoneBounds
            return this.zoneSeconds.map((seconds, i) => ({
                index: i,
                label: zoneLabel(i),
                range: i < 4
                    ? t('fit_tracker', '{from}–{to} bpm', { from: bounds[i], to: bounds[i + 1] - 1 })
                    : t('fit_tracker', '{from}+ bpm', { from: bounds[i] }),
                color: ZONE_COLORS[i],
                pct: seconds / total * 100,
                time: formatDurationShort(seconds, true),
            }))
        },
        // Banister TRIMP, same formula as StatsService::trimp()
        trimp() {
            const { maxHr, restingHr } = this.settings
            const reserve = Math.max(1, maxHr - restingHr)
            let sum = 0
            for (const [bpm, seconds] of Object.entries(this.histogram)) {
                const f = Math.min(1, Math.max(0, (Number(bpm) - restingHr) / reserve))
                sum += seconds / 60 * f * 0.64 * Math.exp(1.92 * f)
            }
            return sum
        },
    },
}
</script>

<style scoped>
.hr-zones {
    margin-bottom: var(--ft-gap-6);
}
.hr-zones__grid {
    display: flex;
    flex-wrap: wrap;
    gap: var(--ft-gap-5);
    align-items: center;
}
.hr-zones__bars {
    flex: 1 1 360px;
    display: flex;
    flex-direction: column;
    gap: var(--ft-gap-2);
}
.zone-row {
    display: grid;
    grid-template-columns: minmax(110px, auto) 1fr 72px 44px;
    align-items: center;
    gap: var(--ft-gap-3);
}
.zone-row__label {
    display: flex;
    flex-direction: column;
}
.zone-row__name {
    font-weight: bold;
}
.zone-row__range,
.zone-row__pct {
    font-size: var(--font-size-small);
    color: var(--color-text-maxcontrast);
}
.zone-row__track {
    height: 12px;
    border-radius: var(--border-radius-pill);
    background: var(--color-background-dark);
    overflow: hidden;
}
.zone-row__fill {
    display: block;
    height: 100%;
    border-radius: var(--border-radius-pill);
}
.zone-row__time {
    text-align: end;
}
.zone-row__pct {
    text-align: end;
}
.hr-zones__summary {
    flex: 0 0 160px;
}
.hr-zones__hint {
    margin-top: var(--ft-gap-3);
    font-size: var(--font-size-small);
    color: var(--color-text-maxcontrast);
}
.stat {
    background: var(--color-background-dark);
    border-radius: var(--border-radius-element);
    padding: var(--ft-gap-3);
    text-align: center;
}
.stat__label,
.stat__hint {
    font-size: var(--font-size-small);
    color: var(--color-text-maxcontrast);
}
.stat__value {
    font-size: 20px;
    font-weight: bold;
}
</style>
