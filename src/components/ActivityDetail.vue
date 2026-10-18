<template>
    <div class="activity-detail">
        <DetailToolbar
            :back-to="'/'"
            :back-label="t('fit_tracker', 'All activities')"
            :show-actions="!!activity"
            :delete-label="t('fit_tracker', 'Delete activity')"
            :delete-title="t('fit_tracker', 'Delete activity and move its file to the trash?')"
            :delete-message="deleteMessage"
            :busy-label="busyLabel"
            @delete="deleteActivity"
            @reimport="reimport"
        >
            <NcButton variant="primary" @click="showShare = true">
                <template #icon>
                    <NcIconSvgWrapper :svg="icons.share" :size="20" />
                </template>
                {{ t('fit_tracker', 'Share') }}
            </NcButton>
        </DetailToolbar>

        <LoadingSkeleton v-if="loading" variant="detail" />
        <NcEmptyContent
            v-else-if="notFound"
            :name="t('fit_tracker', 'Activity not found')"
            :description="t('fit_tracker', 'It may have been deleted.')"
        >
            <template #icon>
                <NcIconSvgWrapper :svg="icons.exercise" />
            </template>
        </NcEmptyContent>
        <LoadError
            v-else-if="error"
            :heading="t('fit_tracker', 'Could not load activity')"
            :message="t('fit_tracker', 'Check your connection and try again.')"
            @retry="load"
        />

        <template v-else-if="activity">
            <div class="activity-detail__header">
                <SportBadge :icon="sportIcon" :color="sportColor" :size="32" />
                <div>
                    <h2>{{ activity.name }}</h2>
                    <div class="subtitle">{{ formatDateTime(activity.startTime) }}</div>
                </div>
            </div>

            <!-- Key stats -->
            <div class="stats-grid">
                <div v-for="stat in stats" :key="stat.label" class="stat">
                    <div class="stat__label">{{ stat.label }}</div>
                    <div class="stat__value">{{ stat.value }}</div>
                </div>
            </div>

            <!-- Map -->
            <ActivityMap
                v-if="hasGps"
                :trackpoints="trackpoints"
                :photos="sortedPhotos"
            />
            <!-- Photos (GPS-tagged images matched to the route, any sport) -->
            <ActivityPhotos
                v-if="sortedPhotos.length > 0"
                :photos="sortedPhotos"
                @open-photo="openLightbox"
            />

            <!-- HR + elevation charts -->
            <ActivityCharts
                v-if="trackpoints.length > 0"
                :trackpoints="trackpoints"
                :sport="activity.sport"
            />

            <!-- Laps table -->
            <section v-if="laps.length > 0">
                <h3>{{ t('fit_tracker', 'Laps') }}</h3>
                <table class="laps-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th v-for="col in lapColumns" :key="col">{{ col }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="lap in laps" :key="lap.id">
                            <td data-label="#">{{ lap.lapNumber }}</td>
                            <td :data-label="lapColumns[0]">{{ lap.distance ? formatDistance(lap.distance) : '–' }}</td>
                            <td :data-label="lapColumns[1]">{{ lap.duration ? formatDurationClock(lap.duration) : '–' }}</td>
                            <td :data-label="lapColumns[2]">{{ lapPaceOrSpeed(lap) }}</td>
                            <td :data-label="lapColumns[3]">{{ lap.avgHr ? t('fit_tracker', '{bpm} bpm', { bpm: lap.avgHr }) : '–' }}</td>
                            <td :data-label="lapColumns[4]">{{ lap.elevationGain ? t('fit_tracker', '+{meters} m', { meters: Math.round(lap.elevationGain) }) : '–' }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </template>

        <!-- Fullscreen photo gallery -->
        <PhotoLightbox
            v-if="lightboxIndex !== null"
            :photos="sortedPhotos"
            :start-index="lightboxIndex"
            @close="lightboxIndex = null"
        />

        <ShareCard
            v-if="showShare"
            :activity="activity"
            :trackpoints="trackpoints"
            @close="showShare = false"
        />
    </div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { defineAsyncComponent } from 'vue'
import { t } from '@nextcloud/l10n'
import { NcButton, NcEmptyContent, NcIconSvgWrapper } from '@nextcloud/vue'
import DetailToolbar from './DetailToolbar.vue'
import LoadingSkeleton from './LoadingSkeleton.vue'
import LoadError from './LoadError.vue'
import SportBadge from './SportBadge.vue'
import { sportIcon as getSportIcon, sportColor as getSportColor, SPEED_NOT_PACE_SPORTS } from '../sports.js'
import { icons } from '../icons.js'
import { formatDistance, formatDurationClock, formatDateTime, formatPace, formatSpeed } from '../format.js'
import { deleteItem, reimportItem, fileName } from '../fileActions.js'

export default {
    name: 'ActivityDetail',
    components: {
        DetailToolbar, LoadingSkeleton, LoadError, SportBadge,
        NcButton, NcEmptyContent, NcIconSvgWrapper,
        ActivityMap:    defineAsyncComponent(() => import('./ActivityMap.vue')),
        ActivityCharts: defineAsyncComponent(() => import('./ActivityCharts.vue')),
        ActivityPhotos: defineAsyncComponent(() => import('./ActivityPhotos.vue')),
        PhotoLightbox:  defineAsyncComponent(() => import('./PhotoLightbox.vue')),
        ShareCard:      defineAsyncComponent(() => import('./ShareCard.vue')),
    },
    props: { id: { type: String, required: true } },
    data() {
        return {
            icons,
            activity: null,
            laps: [],
            trackpoints: [],
            photos: [],
            loading: true,
            error: false,
            notFound: false,
            busy: null, // 'reimport' | 'delete' while running
            lightboxIndex: null,
            showShare: false,
        }
    },
    computed: {
        busyLabel() {
            if (this.busy === 'reimport') return t('fit_tracker', 'Re-importing…')
            if (this.busy === 'delete') return t('fit_tracker', 'Deleting…')
            return ''
        },
        sortedPhotos() {
            return [...this.photos].sort((a, b) => {
                if (!a.takenAt) return 1
                if (!b.takenAt) return -1
                return new Date(a.takenAt.replace(' ', 'T')) - new Date(b.takenAt.replace(' ', 'T'))
            })
        },
        sportIcon() {
            return getSportIcon(this.activity?.sport)
        },
        sportColor() {
            return getSportColor(this.activity?.sport)
        },
        deleteMessage() {
            // TRANSLATORS: {file} is the name of the .fit file, e.g. "2026-03-01-08-15-22.fit"
            return t('fit_tracker', 'The activity will be removed from FIT Tracker and its file {file} will be moved to the Nextcloud trash. If you restore the file from the trash, the activity is imported again on the next sync.', {
                file: fileName(this.activity?.fitFilePath),
            })
        },
        stats() {
            const a = this.activity
            if (!a) return []
            const list = []
            // value() runs only when the stat is shown: t() throws on a null
            // placeholder, and most activities lack some fields (e.g. power)
            const add = (cond, label, value) => { if (cond) list.push({ label, value: value() }) }
            add(this.effectiveAvgSpeed && this.showsSpeedStat, t('fit_tracker', 'Average speed'), () => formatSpeed(this.effectiveAvgSpeed))
            add(this.effectiveMaxSpeed && this.showsSpeedStat, t('fit_tracker', 'Maximum speed'), () => formatSpeed(this.effectiveMaxSpeed))
            add(a.distance, t('fit_tracker', 'Distance'), () => formatDistance(a.distance))
            add(a.duration, t('fit_tracker', 'Duration'), () => formatDurationClock(a.duration))
            add(this.pace, t('fit_tracker', 'Average pace'), () => this.pace)
            add(a.avgHr, t('fit_tracker', 'Average heart rate'), () => t('fit_tracker', '{bpm} bpm', { bpm: a.avgHr }))
            add(a.maxHr, t('fit_tracker', 'Maximum heart rate'), () => t('fit_tracker', '{bpm} bpm', { bpm: a.maxHr }))
            add(a.calories, t('fit_tracker', 'Calories'), () => t('fit_tracker', '{kcal} kcal', { kcal: a.calories }))
            add(a.elevationGain, t('fit_tracker', 'Elevation gain'), () => t('fit_tracker', '+{meters} m', { meters: Math.round(a.elevationGain) }))
            add(a.avgCadence, t('fit_tracker', 'Average cadence'), () => t('fit_tracker', '{rpm} rpm', { rpm: a.avgCadence }))
            add(a.avgPower, t('fit_tracker', 'Average power'), () => t('fit_tracker', '{watts} W', { watts: a.avgPower }))
            return list
        },
        lapColumns() {
            return [
                t('fit_tracker', 'Distance'),
                t('fit_tracker', 'Duration'),
                SPEED_NOT_PACE_SPORTS.includes(this.activity?.sport) ? t('fit_tracker', 'Speed') : t('fit_tracker', 'Pace'),
                t('fit_tracker', 'Average heart rate'),
                t('fit_tracker', 'Elevation gain'),
            ]
        },
        hasGps() {
            return this.trackpoints.some(tp => tp.lat !== null && tp.lon !== null)
        },
        // Effective avg speed in km/h — falls back to distance(km) / duration(s) * 3600
        effectiveAvgSpeed() {
            const a = this.activity
            if (!a) return null
            if (a.avgSpeed) return a.avgSpeed
            if (a.distance && a.duration > 0) return a.distance / a.duration * 3600
            return null
        },
        effectiveMaxSpeed() {
            const a = this.activity
            if (!a) return null
            return a.maxSpeed || null
        },
        // Sports where km/h is the natural unit (see SPEED_NOT_PACE_SPORTS), plus
        // running — which shows both a raw speed stat and pace(min/km) below.
        showsSpeedStat() {
            const sport = this.activity?.sport
            return sport === 'running' || SPEED_NOT_PACE_SPORTS.includes(sport)
        },
        pace() {
            const a = this.activity
            if (!a || !this.effectiveAvgSpeed) return null
            if (SPEED_NOT_PACE_SPORTS.includes(a.sport) || ['gym', 'swimming', 'breathwork', 'meditation'].includes(a.sport)) return null
            return formatPace(this.effectiveAvgSpeed)
        },
    },
    watch: {
        // Re-import can land on a new id for the same page component
        id() {
            this.photos = []
            this.load()
        },
    },
    async mounted() {
        await this.load()
        this._photoOpenHandler = (e) => this.openLightboxByFileId(e.detail.fileId)
        document.addEventListener('fit-photo-open', this._photoOpenHandler)
    },
    unmounted() {
        document.removeEventListener('fit-photo-open', this._photoOpenHandler)
    },
    methods: {
        async load() {
            this.loading = true
            this.error = false
            this.notFound = false
            try {
                const [actRes, lapRes, tpRes] = await Promise.all([
                    axios.get(generateUrl(`/apps/fit_tracker/api/activities/${this.id}`)),
                    axios.get(generateUrl(`/apps/fit_tracker/api/activities/${this.id}/laps`)),
                    axios.get(generateUrl(`/apps/fit_tracker/api/activities/${this.id}/trackpoints`)),
                ])
                this.activity    = actRes.data
                this.laps        = lapRes.data
                this.trackpoints = tpRes.data

                if (this.hasGps) {
                    this.loadPhotos()
                }
            } catch (e) {
                if (e.response?.status === 404) this.notFound = true
                else this.error = true
            } finally {
                this.loading = false
            }
        },
        openLightbox(index) {
            this.lightboxIndex = index
        },
        openLightboxByFileId(fileId) {
            const index = this.sortedPhotos.findIndex(p => p.fileId === fileId)
            if (index !== -1) this.lightboxIndex = index
        },
        async loadPhotos() {
            try {
                const res = await axios.get(generateUrl(`/apps/fit_tracker/api/activities/${this.id}/photos`))
                this.photos = res.data
            } catch {
                // Photos are optional — silently ignore errors
            }
        },
        async deleteActivity() {
            this.busy = 'delete'
            const deleted = await deleteItem('activities', this.id, this.activity?.fitFilePath)
            this.busy = null
            if (deleted) this.$router.push('/')
        },
        async reimport() {
            // The content is about to be replaced: show the skeleton meanwhile
            this.busy = 'reimport'
            this.loading = true
            const ok = await reimportItem('activities', this.id, this.$router)
            this.busy = null
            if (!ok) this.loading = false
        },
        formatDateTime,
        formatDistance,
        formatDurationClock,
        lapPaceOrSpeed(lap) {
            let speed = lap.avgSpeed || null  // km/h
            // Fallback: derive from distance(km) / duration(s) * 3600 = km/h
            if (!speed && lap.distance && lap.duration > 0) {
                speed = lap.distance / lap.duration * 3600
            }
            if (!speed) return '–'
            if (SPEED_NOT_PACE_SPORTS.includes(this.activity?.sport)) return formatSpeed(speed)
            return formatPace(speed)
        },
    },
}
</script>

<style scoped>
.activity-detail {
    padding: 0 var(--ft-gap-5) var(--ft-gap-6);
    max-width: 960px;
}
.activity-detail__header {
    display: flex;
    align-items: center;
    gap: var(--ft-gap-4);
    margin-bottom: var(--ft-gap-6);
}
.activity-detail__header h2 {
    margin: 0 0 var(--ft-gap-1);
    font-size: 24px;
    font-weight: bold;
}
.subtitle { color: var(--color-text-maxcontrast); }
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: var(--ft-gap-3);
    margin: 0 0 var(--ft-gap-6);
}
.stat {
    background: var(--color-background-dark);
    border-radius: var(--border-radius-element);
    padding: var(--ft-gap-3);
    text-align: center;
}
.stat__label {
    font-size: var(--font-size-small);
    color: var(--color-text-maxcontrast);
    margin-bottom: var(--ft-gap-1);
}
.stat__value {
    margin: 0;
    font-size: 20px;
    font-weight: bold;
}
.laps-table {
    width: 100%;
    border-collapse: collapse;
}
.laps-table th, .laps-table td {
    padding: var(--ft-gap-2) var(--ft-gap-3);
    text-align: start;
    border-bottom: 1px solid var(--color-border);
}
.laps-table th {
    font-size: var(--font-size-small);
    font-weight: bold;
    color: var(--color-text-maxcontrast);
}
@media (max-width: 600px) {
    .laps-table thead { display: none; }
    .laps-table, .laps-table tbody, .laps-table tr, .laps-table td {
        display: block;
        width: 100%;
    }
    .laps-table tr {
        border: 1px solid var(--color-border);
        border-radius: var(--border-radius-element);
        margin-bottom: var(--ft-gap-2);
        padding: var(--ft-gap-1) var(--ft-gap-3);
    }
    .laps-table td {
        display: flex;
        justify-content: space-between;
        padding: var(--ft-gap-1) 0;
        border-bottom: none;
    }
    .laps-table td::before {
        content: attr(data-label);
        font-weight: bold;
        color: var(--color-text-maxcontrast);
        margin-inline-end: var(--ft-gap-3);
    }
}
</style>
