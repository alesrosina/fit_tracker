<template>
    <div class="activity-detail">
        <div class="activity-detail__back">
            <button class="back-btn" @click="$router.push('/')">← Back</button>
            <button v-if="activity" class="delete-btn" @click="deleteActivity">Delete</button>
        </div>

        <div v-if="loading" class="loading"></div>
        <div v-else-if="error" class="error">{{ error }}</div>

        <template v-else-if="activity">
            <div class="activity-detail__header">
                <span class="sport-icon">{{ sportIcon }}</span>
                <div>
                    <h2>{{ activity.name }}</h2>
                    <div class="subtitle">{{ formatDate(activity.startTime) }}</div>
                </div>
            </div>

            <!-- Key stats -->
            <div class="stats-grid">
                <div v-if="effectiveAvgSpeed && ['cycling', 'running', 'skiing'].includes(activity.sport)" class="stat">
                    <div class="stat__label">Avg Speed</div>
                    <div class="stat__value">{{ effectiveAvgSpeed.toFixed(1) }} km/h</div>
                </div>
                <div v-if="effectiveMaxSpeed && ['cycling', 'running', 'skiing'].includes(activity.sport)" class="stat">
                    <div class="stat__label">Max Speed</div>
                    <div class="stat__value">{{ effectiveMaxSpeed.toFixed(1) }} km/h</div>
                </div>
                <div v-if="activity.distance" class="stat">
                    <div class="stat__label">Distance</div>
                    <div class="stat__value">{{ formatDistance(activity.distance) }}</div>
                </div>
                <div v-if="activity.duration" class="stat">
                    <div class="stat__label">Duration</div>
                    <div class="stat__value">{{ formatDuration(activity.duration) }}</div>
                </div>
                <div v-if="pace" class="stat">
                    <div class="stat__label">Avg Pace</div>
                    <div class="stat__value">{{ pace }}</div>
                </div>
                <div v-if="activity.avgHr" class="stat">
                    <div class="stat__label">Avg HR</div>
                    <div class="stat__value">{{ activity.avgHr }} bpm</div>
                </div>
                <div v-if="activity.maxHr" class="stat">
                    <div class="stat__label">Max HR</div>
                    <div class="stat__value">{{ activity.maxHr }} bpm</div>
                </div>
                <div v-if="activity.calories" class="stat">
                    <div class="stat__label">Calories</div>
                    <div class="stat__value">{{ activity.calories }} kcal</div>
                </div>
                <div v-if="activity.elevationGain" class="stat">
                    <div class="stat__label">Elevation</div>
                    <div class="stat__value">+{{ Math.round(activity.elevationGain) }} m</div>
                </div>
                <div v-if="activity.avgCadence" class="stat">
                    <div class="stat__label">Avg Cadence</div>
                    <div class="stat__value">{{ activity.avgCadence }} rpm</div>
                </div>
                <div v-if="activity.avgPower" class="stat">
                    <div class="stat__label">Avg Power</div>
                    <div class="stat__value">{{ activity.avgPower }} W</div>
                </div>
            </div>

            <!-- Map -->
            <ActivityMap
                v-if="hasGps"
                :trackpoints="trackpoints"
                :photos="photos"
                class="section"
            />
            <!-- Photos (cycling + hiking only, GPS-tagged images on the route) -->
            <ActivityPhotos
                v-if="photos.length > 0"
                :photos="photos"
                class="section"
                @open-photo="openLightbox"
            />

            <!-- HR + elevation charts -->
            <ActivityCharts
                v-if="trackpoints.length > 0"
                :trackpoints="trackpoints"
                :sport="activity.sport"
                class="section"
            />

            <!-- Laps table -->
            <div v-if="laps.length > 0" class="section">
                <h3>Laps</h3>
                <table class="laps-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Distance</th>
                            <th>Duration</th>
                            <th>Pace / Speed</th>
                            <th>Avg HR</th>
                            <th>Elevation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="lap in laps" :key="lap.id">
                            <td>{{ lap.lapNumber }}</td>
                            <td>{{ lap.distance ? formatDistance(lap.distance) : '–' }}</td>
                            <td>{{ lap.duration ? formatDuration(lap.duration) : '–' }}</td>
                            <td>{{ lapPaceOrSpeed(lap) }}</td>
                            <td>{{ lap.avgHr ? lap.avgHr + ' bpm' : '–' }}</td>
                            <td>{{ lap.elevationGain ? '+' + Math.round(lap.elevationGain) + ' m' : '–' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <!-- Photo lightbox (NcModal fallback when OCA.Viewer is unavailable) -->
        <NcModal
            v-if="lightboxPhoto"
            size="large"
            :name="lightboxPhoto.name"
            @close="lightboxPhoto = null"
        >
            <template #default>
                <div class="lightbox-body">
                    <img
                        :src="lightboxPreviewUrl"
                        :alt="lightboxPhoto.name"
                        class="lightbox-img"
                    />
                </div>
            </template>
        </NcModal>
    </div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { NcModal } from '@nextcloud/vue'
import ActivityMap from './ActivityMap.vue'
import ActivityCharts from './ActivityCharts.vue'
import ActivityPhotos from './ActivityPhotos.vue'

export default {
    name: 'ActivityDetail',
    components: { ActivityMap, ActivityCharts, ActivityPhotos, NcModal },
    props: { id: { type: String, required: true } },
    data() {
        return {
            activity: null,
            laps: [],
            trackpoints: [],
            photos: [],
            loading: true,
            error: null,
            lightboxPhoto: null,
        }
    },
    computed: {
        sportIcon() {
            return { running: '🏃', cycling: '🚴', hiking: '🥾', swimming: '🏊', gym: '🏋', breathwork: '🧘', meditation: '🕉️', skiing: '⛷️' }[this.activity?.sport] ?? '🏅'
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
        pace() {
            const a = this.activity
            if (!a || ['gym', 'cycling', 'swimming', 'breathwork', 'meditation', 'skiing'].includes(a.sport) || !this.effectiveAvgSpeed) return null
            // speed is km/h → pace = 60 / speed (min/km)
            const minPerKm = 60 / this.effectiveAvgSpeed
            const m = Math.floor(minPerKm)
            const s = Math.round((minPerKm - m) * 60)
            return `${m}:${String(s).padStart(2, '0')} /km`
        },
        lightboxPreviewUrl() {
            if (!this.lightboxPhoto) return ''
            return generateUrl(`/core/preview?fileId=${this.lightboxPhoto.fileId}&x=2000&y=2000&a=1`)
        },
    },
    async mounted() {
        await this.load()
        this._photoOpenHandler = (e) => this.openLightbox(e.detail)
        document.addEventListener('fit-photo-open', this._photoOpenHandler)
    },
    unmounted() {
        document.removeEventListener('fit-photo-open', this._photoOpenHandler)
    },
    methods: {
        async load() {
            this.loading = true
            this.error = null
            try {
                const [actRes, lapRes, tpRes] = await Promise.all([
                    axios.get(generateUrl(`/apps/fit_tracker/api/activities/${this.id}`)),
                    axios.get(generateUrl(`/apps/fit_tracker/api/activities/${this.id}/laps`)),
                    axios.get(generateUrl(`/apps/fit_tracker/api/activities/${this.id}/trackpoints`)),
                ])
                this.activity    = actRes.data
                this.laps        = lapRes.data
                this.trackpoints = tpRes.data

                if (['cycling', 'hiking'].includes(this.activity.sport)) {
                    this.loadPhotos()
                }
            } catch (e) {
                this.error = e.response?.status === 404 ? 'Activity not found' : 'Failed to load activity'
            } finally {
                this.loading = false
            }
        },
        openLightbox(photo) {
            this.lightboxPhoto = photo
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
            if (!confirm('Delete this activity? This cannot be undone.')) return
            await axios.delete(generateUrl(`/apps/fit_tracker/api/activities/${this.id}`))
            this.$router.push('/')
            window.dispatchEvent(new CustomEvent('fit-tracker:refresh'))
        },
        formatDate(raw) {
            if (!raw) return ''
            return new Date(raw).toLocaleString(undefined, { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' })
        },
        formatDistance(km) {
            if (km < 1) return Math.round(km * 1000) + ' m'
            return km.toFixed(2) + ' km'
        },
        formatDuration(s) {
            const h = Math.floor(s / 3600)
            const m = Math.floor((s % 3600) / 60)
            const sec = s % 60
            if (h > 0) return `${h}:${String(m).padStart(2,'0')}:${String(sec).padStart(2,'0')}`
            return `${m}:${String(sec).padStart(2,'0')}`
        },
        lapPaceOrSpeed(lap) {
            let speed = lap.avgSpeed || null  // km/h
            // Fallback: derive from distance(km) / duration(s) * 3600 = km/h
            if (!speed && lap.distance && lap.duration > 0) {
                speed = lap.distance / lap.duration * 3600
            }
            if (!speed) return '–'
            if (['cycling', 'skiing'].includes(this.activity?.sport)) {
                return speed.toFixed(1) + ' km/h'
            }
            // pace = 60 / speed (min/km)
            const minPerKm = 60 / speed
            const m = Math.floor(minPerKm)
            const s = Math.round((minPerKm - m) * 60)
            return `${m}:${String(s).padStart(2, '0')} /km`
        },
    },
}
</script>

<style scoped>
.activity-detail {
    padding: 20px;
    padding-top: 52px;
    max-width: 960px;
}
.activity-detail__back {
    display: flex;
    justify-content: space-between;
    margin-bottom: 16px;
}
.back-btn {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 14px;
    color: var(--color-primary-element);
}
.delete-btn {
    background: var(--color-element-error);
    border: none;
    border-radius: var(--border-radius);
    cursor: pointer;
    font-size: 14px;
    color: #fff;
    padding: 6px 14px;
}
.delete-btn:hover {
    background: var(--color-error-hover);
}
.activity-detail__header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
}
.sport-icon { font-size: 40px; }
.activity-detail__header h2 { margin: 0 0 4px; }
.subtitle { color: var(--color-text-maxcontrast); font-size: 14px; }
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
    gap: 12px;
    margin-bottom: 24px;
}
.stat {
    background: var(--color-background-dark);
    border-radius: 8px;
    padding: 12px;
    text-align: center;
}
.stat__label {
    font-size: 11px;
    text-transform: uppercase;
    color: var(--color-text-maxcontrast);
    margin-bottom: 4px;
}
.stat__value {
    font-size: 20px;
    font-weight: 600;
}
.section { margin-bottom: 32px; }
.section h3 { margin-bottom: 12px; }
.laps-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}
.laps-table th, .laps-table td {
    padding: 8px 12px;
    text-align: left;
    border-bottom: 1px solid var(--color-border);
}
.laps-table th { font-weight: 600; color: var(--color-text-maxcontrast); }
.loading, .error { padding: 40px; text-align: center; }
.error { color: var(--color-error); }
.lightbox-body {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    min-height: 300px;
}
.lightbox-img {
    max-width: 100%;
    max-height: 80vh;
    object-fit: contain;
    border-radius: 4px;
}
</style>
