<template>
    <div class="public-activity">
        <div class="public-activity__header">
            <SportBadge :icon="sportIcon(activity.sport)" :color="sportColor(activity.sport)" :size="32" />
            <div>
                <h2>{{ activity.name }}</h2>
                <div class="subtitle">
                    {{ formatDateTime(activity.startTime) }}
                    <template v-if="share.owner"> · {{ t('fit_tracker', 'Shared by {name}', { name: share.owner }) }}</template>
                </div>
            </div>
        </div>

        <div class="ft-stats">
            <div v-for="stat in stats" :key="stat.label" class="ft-stat">
                <div class="ft-stat__label">{{ stat.label }}</div>
                <div class="ft-stat__value">{{ stat.value }}</div>
            </div>
        </div>

        <ActivityMap
            v-if="hasGps"
            :trackpoints="trackpoints"
            :highlight-index="hoverIndex"
            @hover="hoverIndex = $event"
        />
        <p v-if="hasGps && share.hideEnds" class="ft-panel__hint public-activity__privacy">
            {{ t('fit_tracker', 'The start and end of the route are hidden.') }}
        </p>

        <ActivityCharts
            v-if="trackpoints.length > 0"
            :trackpoints="trackpoints"
            :sport="activity.sport"
            :highlight-index="hoverIndex"
            @hover="hoverIndex = $event"
        />

        <SplitsTable
            v-if="activity.distance && trackpoints.length > 0"
            :trackpoints="trackpoints"
            :sport="activity.sport"
        />

        <section v-if="laps.length > 1">
            <h3>{{ t('fit_tracker', 'Laps') }}</h3>
            <table class="ft-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ t('fit_tracker', 'Distance') }}</th>
                        <th>{{ t('fit_tracker', 'Duration') }}</th>
                        <th>{{ usesSpeed ? t('fit_tracker', 'Speed') : t('fit_tracker', 'Pace') }}</th>
                        <th>{{ t('fit_tracker', 'Average heart rate') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="lap in laps" :key="lap.id">
                        <td data-label="#">{{ lap.lapNumber }}</td>
                        <td :data-label="t('fit_tracker', 'Distance')">{{ lap.distance ? formatDistance(lap.distance) : '–' }}</td>
                        <td :data-label="t('fit_tracker', 'Duration')">{{ lap.duration ? formatDurationClock(lap.duration) : '–' }}</td>
                        <td :data-label="usesSpeed ? t('fit_tracker', 'Speed') : t('fit_tracker', 'Pace')">{{ lapSpeed(lap) }}</td>
                        <td :data-label="t('fit_tracker', 'Average heart rate')">{{ lap.avgHr ? t('fit_tracker', '{bpm} bpm', { bpm: lap.avgHr }) : '–' }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <p class="public-activity__footer ft-panel__hint">
            {{ t('fit_tracker', 'Recorded with a Garmin device and shared from FIT Tracker for Nextcloud.') }}
        </p>
    </div>
</template>

<script>
import { defineAsyncComponent } from 'vue'
import { t } from '@nextcloud/l10n'
import SportBadge from './SportBadge.vue'
import { sportIcon, sportColor, SPEED_NOT_PACE_SPORTS } from '../sports.js'
import { formatDateTime, formatDistance, formatDurationClock, formatElevation, formatPace, formatSpeed } from '../format.js'

// The public page of a shared activity: stats, route, charts and laps,
// from the data PublicController put into the page. No API calls.
export default {
    name: 'PublicActivity',
    components: {
        SportBadge,
        ActivityMap:    defineAsyncComponent(() => import('./ActivityMap.vue')),
        ActivityCharts: defineAsyncComponent(() => import('./ActivityCharts.vue')),
        SplitsTable:    defineAsyncComponent(() => import('./SplitsTable.vue')),
    },
    props: {
        share: { type: Object, required: true },
    },
    data() {
        return { hoverIndex: null }
    },
    computed: {
        activity() { return this.share.activity },
        laps() { return this.share.laps ?? [] },
        trackpoints() { return this.share.trackpoints ?? [] },
        hasGps() { return this.trackpoints.some(tp => tp.lat !== null && tp.lon !== null) },
        usesSpeed() { return SPEED_NOT_PACE_SPORTS.includes(this.activity.sport) },
        speed() {
            const a = this.activity
            if (a.avgSpeed) return a.avgSpeed
            return a.distance && a.duration ? a.distance / a.duration * 3600 : null
        },
        stats() {
            const a = this.activity
            const list = []
            const add = (cond, label, value) => { if (cond) list.push({ label, value: value() }) }
            add(a.distance, t('fit_tracker', 'Distance'), () => formatDistance(a.distance))
            add(a.duration, t('fit_tracker', 'Duration'), () => formatDurationClock(a.duration))
            add(this.speed && a.distance, this.usesSpeed ? t('fit_tracker', 'Average speed') : t('fit_tracker', 'Average pace'),
                () => this.usesSpeed ? formatSpeed(this.speed) : formatPace(this.speed))
            add(a.elevationGain, t('fit_tracker', 'Elevation gain'), () => formatElevation(a.elevationGain, true))
            add(a.avgHr, t('fit_tracker', 'Average heart rate'), () => t('fit_tracker', '{bpm} bpm', { bpm: a.avgHr }))
            add(a.calories, t('fit_tracker', 'Calories'), () => t('fit_tracker', '{kcal} kcal', { kcal: a.calories }))
            add(a.avgPower, t('fit_tracker', 'Average power'), () => t('fit_tracker', '{watts} W', { watts: a.avgPower }))
            return list
        },
    },
    methods: {
        sportIcon,
        sportColor,
        formatDateTime,
        formatDistance,
        formatDurationClock,
        lapSpeed(lap) {
            const speed = lap.avgSpeed || (lap.distance && lap.duration ? lap.distance / lap.duration * 3600 : null)
            if (!speed) return '–'
            return this.usesSpeed ? formatSpeed(speed) : formatPace(speed)
        },
    },
}
</script>

<style scoped>
.public-activity {
    box-sizing: border-box;
    width: min(960px, 100%);
    margin: 0 auto;
    padding: var(--ft-gap-6) var(--ft-gap-5);
    background: var(--color-main-background);
    border-radius: var(--border-radius-container-large, var(--border-radius-container));
}
.public-activity__header {
    display: flex;
    align-items: center;
    gap: var(--ft-gap-4);
    margin-bottom: var(--ft-gap-6);
}
.public-activity__header h2 {
    margin: 0 0 var(--ft-gap-1);
    font-size: 24px;
    font-weight: bold;
}
.subtitle {
    color: var(--color-text-maxcontrast);
}
.public-activity__privacy {
    margin: calc(-1 * var(--ft-gap-4)) 0 var(--ft-gap-6);
}
.public-activity__footer {
    margin-top: var(--ft-gap-6);
    text-align: center;
}
</style>
