<template>
    <div class="activity-list">
        <h2 class="page-title">{{ pageTitle }}</h2>
        <LoadingSkeleton v-if="loading" />
        <LoadError
            v-else-if="error"
            :heading="t('fit_tracker', 'Could not load activities')"
            :message="t('fit_tracker', 'Check your connection and try again.')"
            @retry="load"
        />
        <template v-else>
            <NcNoteCard
                v-if="syncErrors.length > 0"
                type="warning"
                :heading="n('fit_tracker', '%n file could not be imported', '%n files could not be imported', syncErrors.length)"
                class="sync-errors"
            >
                <ul>
                    <li v-for="e in syncErrors" :key="e">{{ e }}</li>
                </ul>
            </NcNoteCard>

            <div v-if="activities.length > 0" class="dashboard">
                <div class="dashboard-row">
                    <section class="dashboard-panel">
                        <ActivityCalendar :activities="filtered" @select-date="calendarSelectedDate = $event" />
                    </section>
                    <section class="dashboard-panel">
                        <ActivityWeekChart :activities="filtered" :anchorDate="calendarSelectedDate" />
                    </section>
                </div>
                <router-link
                    v-if="lastSleep"
                    :to="'/sleep/' + lastSleep.id"
                    class="dashboard-panel sleep-panel"
                >
                    <h3 class="panel-title">{{ t('fit_tracker', 'Last sleep') }}</h3>
                    <div class="sleep-summary">
                        <SportBadge icon="bedtime" :color="sleepColor" :size="24" />
                        <div class="sleep-summary__body">
                            <div class="sleep-summary__top">
                                <span class="sleep-summary__dur">{{ formatDurationShort(lastSleep.duration) }}</span>
                                <span v-if="lastSleep.hrvScore" class="sleep-summary__hrv">
                                    {{ t('fit_tracker', 'HRV {score}', { score: lastSleep.hrvScore }) }}
                                </span>
                            </div>
                            <div class="sleep-summary__meta">
                                <span>{{ formatDay(lastSleep.startTime) }}</span>
                                <span v-if="lastSleep.score"> · {{ t('fit_tracker', 'Score {score}/100', { score: lastSleep.score }) }}</span>
                            </div>
                            <SleepStageBar
                                v-if="hasStageDurations(lastSleep)"
                                :timeDeep="lastSleep.timeDeep"
                                :timeLight="lastSleep.timeLight"
                                :timeRem="lastSleep.timeRem"
                                :timeAwake="lastSleep.timeAwake"
                                class="sleep-summary__stagebar"
                            />
                        </div>
                    </div>
                </router-link>
            </div>

            <NcEmptyContent
                v-if="filtered.length === 0"
                :name="emptyTitle"
                :description="emptyDescription"
            >
                <template #icon>
                    <NcIconSvgWrapper :svg="icons.exercise" />
                </template>
                <template #action>
                    <NcButton v-if="navData.folderPath === ''" variant="primary" @click="openSettings">
                        {{ t('fit_tracker', 'Choose folder') }}
                    </NcButton>
                    <NcButton v-else-if="!$route.query.sport" @click="load">
                        {{ t('fit_tracker', 'Sync now') }}
                    </NcButton>
                </template>
            </NcEmptyContent>
            <template v-else>
                <h3 class="list-title">{{ t('fit_tracker', 'Activities') }}</h3>
                <div class="activity-grid">
                    <router-link
                        v-for="activity in paginated"
                        :key="activity.id"
                        :to="'/activity/' + activity.id"
                        class="activity-card"
                    >
                        <SportBadge :icon="sportIcon(activity.sport)" :color="sportColor(activity.sport)" :size="24" />
                        <div class="activity-card__body">
                            <div class="activity-card__name">{{ activity.name }}</div>
                            <div class="activity-card__date">{{ formatTimeRange(activity.startTime, activity.duration) }}</div>
                            <div class="activity-card__stats">
                                <span v-if="activity.distance">{{ formatDistance(activity.distance) }}</span>
                                <span v-if="activity.duration">{{ formatDurationShort(activity.duration, true) }}</span>
                                <span v-if="activity.avgHr">{{ t('fit_tracker', '{bpm} bpm', { bpm: activity.avgHr }) }}</span>
                                <span v-if="activity.calories">{{ t('fit_tracker', '{kcal} kcal', { kcal: activity.calories }) }}</span>
                            </div>
                        </div>
                    </router-link>
                </div>
                <PageNav
                    v-if="totalPages > 1"
                    class="pagination"
                    :previous-label="t('fit_tracker', 'Previous page')"
                    :next-label="t('fit_tracker', 'Next page')"
                    :has-previous="currentPage > 1"
                    :has-next="currentPage < totalPages"
                    @previous="currentPage--"
                    @next="currentPage++"
                >
                    {{ t('fit_tracker', 'Page {page} of {total}', { page: currentPage, total: totalPages }) }}
                </PageNav>
            </template>
        </template>
    </div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { t } from '@nextcloud/l10n'
import { NcButton, NcEmptyContent, NcIconSvgWrapper, NcNoteCard } from '@nextcloud/vue'
import ActivityCalendar from './ActivityCalendar.vue'
import ActivityWeekChart from './ActivityWeekChart.vue'
import SleepStageBar from './SleepStageBar.vue'
import SportBadge from './SportBadge.vue'
import LoadingSkeleton from './LoadingSkeleton.vue'
import LoadError from './LoadError.vue'
import PageNav from './PageNav.vue'
import { sportIcon, sportColor, sportLabel } from '../sports.js'
import { STAGE_COLORS } from '../sleepStages.js'
import { navData, setActivities, setSleep, openSettings } from '../store/navData.js'
import { icons } from '../icons.js'
import { formatDistance, formatDurationShort, formatDay, formatTime } from '../format.js'

const PAGE_SIZE = 9

export default {
    name: 'ActivityList',
    components: {
        ActivityCalendar, ActivityWeekChart, SleepStageBar, SportBadge, LoadingSkeleton, LoadError, PageNav,
        NcButton, NcEmptyContent, NcIconSvgWrapper, NcNoteCard,
    },
    data() {
        return {
            navData,
            icons,
            sleepColor: STAGE_COLORS.rem,
            activities: [],
            syncErrors: [],
            lastSleep: null,
            loading: true,
            error: false,
            currentPage: 1,
            calendarSelectedDate: null,
        }
    },
    computed: {
        pageTitle() {
            const sport = this.$route.query.sport
            return sport ? sportLabel(sport) : t('fit_tracker', 'All activities')
        },
        filtered() {
            const sport = this.$route.query.sport
            if (!sport) return this.activities
            return this.activities.filter(a => a.sport === sport)
        },
        totalPages() {
            return Math.ceil(this.filtered.length / PAGE_SIZE) || 1
        },
        paginated() {
            const start = (this.currentPage - 1) * PAGE_SIZE
            return this.filtered.slice(start, start + PAGE_SIZE)
        },
        emptyTitle() {
            if (navData.folderPath === '') return t('fit_tracker', 'No folder selected yet')
            const sport = this.$route.query.sport
            if (sport) return t('fit_tracker', 'No activities of this type')
            return t('fit_tracker', 'No activities yet')
        },
        emptyDescription() {
            if (navData.folderPath === '') {
                return t('fit_tracker', 'Choose the folder where your watch exports its .fit files.')
            }
            if (this.$route.query.sport) return ''
            return t('fit_tracker', 'No activity files were found in {folder}. New files are imported on every sync.', { folder: navData.folderPath ?? '' })
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
            this.error = false
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
                setActivities(this.activities)
                setSleep(sessions)
            } catch (e) {
                this.error = true
            } finally {
                this.loading = false
            }
        },
        openSettings,
        sportIcon,
        sportColor,
        formatDistance,
        formatDurationShort,
        formatDay,
        formatTimeRange(startRaw, durationSeconds) {
            if (!startRaw) return ''
            const from = formatTime(startRaw)
            if (!durationSeconds) return from
            const end = new Date(new Date(startRaw).getTime() + durationSeconds * 1000)
            return `${from} – ${formatTime(end)}`
        },
        hasStageDurations(s) {
            return (s.timeDeep || 0) + (s.timeLight || 0) + (s.timeRem || 0) + (s.timeAwake || 0) > 0
        },
    },
}
</script>

<style scoped>
.activity-list {
    padding: 0 var(--ft-gap-5) var(--ft-gap-6);
    max-width: 1000px;
}
.page-title {
    margin: 0 0 var(--ft-gap-5) var(--ft-gap-8);
    padding-top: var(--ft-gap-2);
    font-size: 20px;
    font-weight: bold;
}
.sync-errors ul {
    margin: var(--ft-gap-1) 0 0 var(--ft-gap-4);
    padding: 0;
    list-style: disc;
}
.dashboard {
    display: flex;
    flex-direction: column;
    gap: var(--ft-gap-4);
    margin-bottom: var(--ft-gap-6);
}
.dashboard-row {
    display: flex;
    gap: var(--ft-gap-5);
    flex-wrap: wrap;
}
.dashboard-panel {
    flex: 1;
    min-width: 280px;
    background: var(--color-main-background);
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-container);
    padding: var(--ft-gap-4);
}
.panel-title {
    margin: 0 0 var(--ft-gap-2);
    font-size: var(--default-font-size);
    font-weight: bold;
    color: var(--color-text-maxcontrast);
}
.list-title {
    margin: 0 0 var(--ft-gap-3);
    font-size: 20px;
    font-weight: bold;
}
.activity-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: var(--ft-gap-4);
}
/* Cards and the sleep panel are links: reachable by keyboard, open on Enter */
.activity-card,
.sleep-panel {
    color: var(--color-main-text);
    text-decoration: none;
    transition: background-color 0.15s, border-color 0.15s;
}
.activity-card {
    display: flex;
    gap: var(--ft-gap-3);
    padding: var(--ft-gap-4);
    border-radius: var(--border-radius-container);
    border: 1px solid var(--color-border);
    background: var(--color-main-background);
}
.activity-card:hover,
.sleep-panel:hover {
    background: var(--color-background-hover);
    border-color: var(--color-border-maxcontrast);
}
.activity-card:focus-visible,
.sleep-panel:focus-visible {
    outline: 2px solid var(--color-primary-element);
    outline-offset: 2px;
}
.activity-card__body { min-width: 0; }
.activity-card__name {
    font-weight: bold;
    margin-bottom: var(--ft-gap-1);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.activity-card__date {
    font-size: var(--font-size-small);
    color: var(--color-text-maxcontrast);
    margin-bottom: var(--ft-gap-1);
}
.activity-card__stats {
    display: flex;
    flex-wrap: wrap;
    gap: var(--ft-gap-1) var(--ft-gap-3);
    font-size: var(--font-size-small);
}
.sleep-panel {
    display: block;
    flex: none;
    width: 100%;
}
.sleep-summary {
    display: flex;
    align-items: center;
    gap: var(--ft-gap-3);
}
.sleep-summary__body { flex: 1; }
.sleep-summary__top {
    display: flex;
    align-items: baseline;
    gap: var(--ft-gap-3);
}
.sleep-summary__dur { font-size: 20px; font-weight: bold; }
.sleep-summary__hrv { font-weight: bold; }
.sleep-summary__meta { font-size: var(--font-size-small); color: var(--color-text-maxcontrast); }
.sleep-summary__stagebar { margin-top: var(--ft-gap-2); }
.pagination {
    justify-content: center;
    margin-top: var(--ft-gap-6);
}
</style>
