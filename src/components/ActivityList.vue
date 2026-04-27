<template>
    <div class="activity-list">
        <h2 class="page-title">{{ pageTitle }}</h2>
        <div v-if="loading" class="loading"></div>
        <div v-else-if="error" class="error">{{ error }}</div>
        <template v-else>
            <div v-if="syncErrors.length > 0" class="sync-errors">
                <strong>Import errors:</strong>
                <ul>
                    <li v-for="e in syncErrors" :key="e">{{ e }}</li>
                </ul>
            </div>

            <div v-if="activities.length > 0" class="dashboard">
                <div class="dashboard-row">
                    <div class="dashboard-panel">
                        <h3 class="panel-title">{{ monthTitle }}</h3>
                        <ActivityCalendar :activities="filtered" @select-date="calendarSelectedDate = $event" />
                    </div>
                    <div class="dashboard-panel">
                        <ActivityWeekChart :activities="filtered" :anchorDate="calendarSelectedDate" />
                    </div>
                </div>
                <div v-if="lastSleep" class="dashboard-row dashboard-row--sleep">
                    <div class="dashboard-panel sleep-panel" @click="$router.push('/sleep/' + lastSleep.id)">
                        <h3 class="panel-title">Last Sleep</h3>
                        <div class="sleep-summary">
                            <span class="sleep-summary__icon">🛌</span>
                            <div class="sleep-summary__body">
                                <div class="sleep-summary__top">
                                    <span class="sleep-summary__dur">{{ formatDuration(lastSleep.duration) }}</span>
                                    <span v-if="lastSleep.hrvScore" class="sleep-summary__hrv">💚 HRV {{ lastSleep.hrvScore }}</span>
                                </div>
                                <div class="sleep-summary__meta">
                                    <span>{{ formatDate(lastSleep.startTime) }}</span>
                                    <span v-if="lastSleep.score"> · Score {{ lastSleep.score }}/100</span>
                                </div>
                                <SleepStageBar
                                    v-if="(lastSleep.timeDeep || 0) + (lastSleep.timeLight || 0) + (lastSleep.timeRem || 0) + (lastSleep.timeAwake || 0) > 0"
                                    :timeDeep="lastSleep.timeDeep"
                                    :timeLight="lastSleep.timeLight"
                                    :timeRem="lastSleep.timeRem"
                                    :timeAwake="lastSleep.timeAwake"
                                    style="margin-top: 6px;"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="filtered.length === 0" class="empty">
                No activities found. Configure a folder in Settings.
            </div>
            <template v-else>
                <h3 class="list-title">Activities</h3>
                <div class="activity-grid">
                    <div
                        v-for="activity in paginated"
                        :key="activity.id"
                        class="activity-card"
                        @click="$router.push('/activity/' + activity.id)"
                    >
                        <div class="activity-card__icon">{{ sportIcon(activity.sport) }}</div>
                        <div class="activity-card__body">
                            <div class="activity-card__name">{{ activity.name }}</div>
                            <div class="activity-card__date">{{ formatTimeRange(activity.startTime, activity.duration) }}</div>
                            <div class="activity-card__stats">
                                <span v-if="activity.distance">{{ formatDistance(activity.distance) }}</span>
                                <span v-if="activity.duration">{{ formatDuration(activity.duration) }}</span>
                                <span v-if="activity.avgHr">❤ {{ activity.avgHr }} bpm</span>
                                <span v-if="activity.calories">🔥 {{ activity.calories }} kcal</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-if="totalPages > 1" class="pagination">
                    <button class="pagination__btn" :disabled="currentPage === 1" @click="currentPage--">&#8592;</button>
                    <span class="pagination__info">{{ currentPage }} / {{ totalPages }}</span>
                    <button class="pagination__btn" :disabled="currentPage === totalPages" @click="currentPage++">&#8594;</button>
                </div>
            </template>
        </template>
    </div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import ActivityCalendar from './ActivityCalendar.vue'
import ActivityWeekChart from './ActivityWeekChart.vue'
import SleepStageBar from './SleepStageBar.vue'
import { sportIcon as getSportIcon, sportLabel } from '../sports.js'

export default {
    name: 'ActivityList',
    components: { ActivityCalendar, ActivityWeekChart, SleepStageBar },
    data() {
        return {
            activities: [],
            syncErrors: [],
            lastSleep: null,
            loading: true,
            error: null,
            currentPage: 1,
            calendarSelectedDate: null,
        }
    },
    computed: {
        pageTitle() {
            const sport = this.$route.query.sport
            return sport ? sportLabel(sport) : 'All Activities'
        },
        monthTitle() {
            return new Date().toLocaleDateString(undefined, { month: 'long', year: 'numeric' })
        },
        filtered() {
            const sport = this.$route.query.sport
            if (!sport) return this.activities
            return this.activities.filter(a => a.sport === sport)
        },
        totalPages() {
            return Math.ceil(this.filtered.length / 9) || 1
        },
        paginated() {
            const start = (this.currentPage - 1) * 9
            return this.filtered.slice(start, start + 9)
        },
    },
    watch: {
        filtered() {
            this.currentPage = 1
        },
    },
    mounted() {
        this.load()
        window.addEventListener('fit-tracker:refresh', this.load)
    },
    beforeUnmount() {
        window.removeEventListener('fit-tracker:refresh', this.load)
    },
    methods: {
        async load() {
            this.loading = true
            this.error = null
            this.syncErrors = []
            try {
                const [actRes, sleepRes] = await Promise.all([
                    axios.get(generateUrl('/apps/fit_tracker/api/activities')),
                    axios.get(generateUrl('/apps/fit_tracker/api/sleep')).catch(() => null),
                ])
                this.activities  = actRes.data.activities  ?? actRes.data
                this.syncErrors  = actRes.data.syncErrors  ?? []
                const sessions   = sleepRes?.data?.sessions ?? []
                this.lastSleep   = sessions.length > 0 ? sessions[0] : null
            } catch (e) {
                this.error = 'Failed to load activities'
            } finally {
                this.loading = false
            }
        },
        sportIcon: getSportIcon,
        formatDate(raw) {
            if (!raw) return ''
            return new Date(raw).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
        },
        formatTimeRange(startRaw, durationSeconds) {
            if (!startRaw) return ''
            const start = new Date(startRaw)
            const opts = { hour: '2-digit', minute: '2-digit' }
            const from = start.toLocaleTimeString(undefined, opts)
            if (!durationSeconds) return from
            const end = new Date(start.getTime() + durationSeconds * 1000)
            return `${from} – ${end.toLocaleTimeString(undefined, opts)}`
        },
        formatDistance(km) {
            if (km < 1) return Math.round(km * 1000) + ' m'
            return km.toFixed(2) + ' km'
        },
        formatDuration(seconds) {
            const h = Math.floor(seconds / 3600)
            const m = Math.floor((seconds % 3600) / 60)
            const s = seconds % 60
            if (h > 0) return `${h}h ${m}m`
            return `${m}m ${s}s`
        },
    },
}
</script>

<style scoped>
.activity-list {
    padding: 0 20px;
    max-width: 1000px;
}
.dashboard {
    margin-bottom: 28px;
}
.dashboard-row {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}
.dashboard-panel {
    flex: 1;
    min-width: 280px;
    background: var(--color-main-background);
    border: 1px solid var(--color-border);
    border-radius: 8px;
    padding: 16px;
}
.panel-title {
    margin: 0 0 12px;
    font-size: 14px;
    font-weight: 600;
    color: var(--color-text-maxcontrast);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.list-title {
    margin: 0 0 12px;
    font-size: 16px;
    font-weight: 600;
}
.page-title {
    margin: 0 0 20px 30px;
    font-size: 20px;
    font-weight: 600;
    padding-top: 8px;
}
.activity-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
}
.activity-card {
    display: flex;
    gap: 12px;
    padding: 16px;
    border-radius: 8px;
    border: 1px solid var(--color-border);
    cursor: pointer;
    transition: box-shadow 0.15s;
    background: var(--color-main-background);
}
.activity-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.activity-card__icon {
    font-size: 28px;
    line-height: 1;
}
.activity-card__name {
    font-weight: 600;
    margin-bottom: 2px;
}
.activity-card__date {
    font-size: 12px;
    color: var(--color-text-maxcontrast);
    margin-bottom: 6px;
}
.activity-card__stats {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    font-size: 13px;
}
.sync-errors {
    margin-bottom: 16px;
    padding: 12px 16px;
    background: var(--color-background-dark);
    border-left: 3px solid var(--color-text-error);
    border-radius: 4px;
    font-size: 13px;
    color: var(--color-text-error);
}
.sync-errors ul { margin: 4px 0 0 16px; padding: 0; }
.dashboard-row--sleep { margin-top: 16px; }
.sleep-panel {
    cursor: pointer;
    transition: box-shadow 0.15s;
    flex: none;
    width: 100%;
    padding: 10px 16px;
}
.sleep-panel:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.sleep-panel .panel-title { margin-bottom: 8px; }
.sleep-summary {
    display: flex;
    align-items: center;
    gap: 12px;
}
.sleep-summary__icon { font-size: 24px; line-height: 1; }
.sleep-summary__body { flex: 1; }
.sleep-summary__top {
    display: flex;
    align-items: baseline;
    gap: 12px;
    margin-bottom: 2px;
}
.sleep-summary__dur { font-size: 20px; font-weight: 600; }
.sleep-summary__hrv { font-size: 15px; font-weight: 600; }
.sleep-summary__meta { font-size: 13px; color: var(--color-text-maxcontrast); }
.loading, .error, .empty {
    padding: 40px;
    text-align: center;
    color: var(--color-text-maxcontrast);
}
.error { color: var(--color-text-error); }
.pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 16px;
    margin-top: 24px;
}
.pagination__btn {
    background: var(--color-main-background);
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius);
    padding: 6px 14px;
    cursor: pointer;
    font-size: 16px;
    color: var(--color-main-text);
    transition: background 0.15s;
}
.pagination__btn:hover:not(:disabled) { background: var(--color-background-dark); }
.pagination__btn:disabled { opacity: 0.4; cursor: default; }
.pagination__info { font-size: 14px; color: var(--color-text-maxcontrast); min-width: 60px; text-align: center; }
</style>
