<template>
    <div class="ft-page sleep-page">
        <div class="sleep-page__header">
            <h2 class="ft-page-title sleep-page__title">{{ t('fit_tracker', 'Sleep') }}</h2>
            <TimeframePicker v-if="sessions.length > 0" :timeframe="timeframe" />
        </div>

        <LoadingSkeleton v-if="loading" />
        <LoadError
            v-else-if="error"
            :heading="t('fit_tracker', 'Could not load sleep sessions')"
            :message="t('fit_tracker', 'Check your connection and try again.')"
            @retry="load(true)"
        />
        <template v-else>
            <NcNoteCard
                v-if="syncErrors.length > 0"
                type="warning"
                :heading="n('fit_tracker', '%n file could not be imported', '%n files could not be imported', syncErrors.length)"
                class="sleep-page__errors"
            >
                <ul>
                    <li v-for="e in syncErrors" :key="e">{{ e }}</li>
                </ul>
            </NcNoteCard>

            <NcEmptyContent
                v-if="sessions.length === 0"
                :name="t('fit_tracker', 'No sleep sessions yet')"
                :description="t('fit_tracker', 'Sleep and nap files from your watch appear here once they are in the source folder.')"
            >
                <template #icon>
                    <NcIconSvgWrapper :svg="icons.bedtime" />
                </template>
                <template #action>
                    <NcButton @click="load(true)">
                        {{ t('fit_tracker', 'Sync now') }}
                    </NcButton>
                </template>
            </NcEmptyContent>

            <template v-else>
                <DashboardSleep
                    v-if="nights.length > 0"
                    :ctx="ctx"
                    :goals="goals.list"
                    :timeframe="timeframe"
                    :load-by-day="loadByDay"
                    :show-header="false"
                    class="sleep-page__boxes"
                />

                <template v-if="nights.length > 0">
                    <SleepNightsChart :nights="nights" :timeframe="timeframe" />
                    <SleepRegularity v-if="nights.length > 1" :nights="nights" />
                </template>

                <section class="sleep-page__sessions">
                    <div class="sleep-page__sessions-header">
                        <h3>{{ t('fit_tracker', 'Sleep sessions') }}</h3>
                        <span class="sleep-page__count">{{ n('fit_tracker', '%n session', '%n sessions', windowSessions.length) }}</span>
                    </div>
                    <p v-if="windowSessions.length === 0" class="ft-panel__hint">
                        {{ t('fit_tracker', 'No sleep recorded in this timeframe.') }}
                    </p>
                    <div v-else class="sleep-grid">
                        <router-link
                            v-for="session in paginated"
                            :key="session.id"
                            :to="'/sleep/' + session.id"
                            class="sleep-card"
                        >
                            <SportBadge icon="bedtime" :color="sleepColor" :size="24" />
                            <div class="sleep-card__body">
                                <div class="sleep-card__name">{{ session.name }}</div>
                                <div class="sleep-card__date">{{ formatDay(session.startTime) }} · {{ formatTime(session.startTime) }} – {{ formatTime(session.endTime) }}</div>
                                <div class="sleep-card__stats">
                                    <strong>{{ formatDurationShort(session.duration) }}</strong>
                                    <span v-if="session.score">{{ t('fit_tracker', 'Score {score}/100', { score: session.score }) }}</span>
                                </div>
                                <SleepStageBar
                                    v-if="hasStageDurations(session)"
                                    :time-deep="session.timeDeep"
                                    :time-light="session.timeLight"
                                    :time-rem="session.timeRem"
                                    :time-awake="session.timeAwake"
                                    class="sleep-card__stagebar"
                                />
                            </div>
                        </router-link>
                    </div>
                    <PageNav
                        v-if="totalPages > 1"
                        class="sleep-page__pages"
                        :previous-label="t('fit_tracker', 'Previous page')"
                        :next-label="t('fit_tracker', 'Next page')"
                        :has-previous="currentPage > 1"
                        :has-next="currentPage < totalPages"
                        @previous="currentPage--"
                        @next="currentPage++"
                    >
                        {{ t('fit_tracker', 'Page {page} of {total}', { page: currentPage, total: totalPages }) }}
                    </PageNav>
                </section>
            </template>
        </template>
    </div>
</template>

<script>
import { NcButton, NcEmptyContent, NcIconSvgWrapper, NcNoteCard } from '@nextcloud/vue'
import SleepStageBar from './SleepStageBar.vue'
import SportBadge from './SportBadge.vue'
import LoadingSkeleton from './LoadingSkeleton.vue'
import LoadError from './LoadError.vue'
import PageNav from './PageNav.vue'
import TimeframePicker from './TimeframePicker.vue'
import DashboardSleep from './DashboardSleep.vue'
import SleepNightsChart from './SleepNightsChart.vue'
import SleepRegularity from './SleepRegularity.vue'
import { navData, fetchActivities } from '../store/navData.js'
import { goals, fetchGoals } from '../store/goals.js'
import { training, fetchTraining } from '../store/training.js'
import { STAGE_COLORS } from '../sleepStages.js'
import { icons } from '../icons.js'
import { formatDurationShort, formatDay, formatTime, parseDate, localDayKey } from '../format.js'
import { measureContext } from '../stats.js'
import { nightsBetween } from '../sleepStats.js'
import { resolveTimeframe, rememberTimeframe } from '../timeframe.js'

const PAGE_SIZE = 12

// Sleep for the selected timeframe, with the same timeframe picker and boxes
// as the Overview: averages and goals, sleep per night, regularity, and the
// sessions in the timeframe.
export default {
    name: 'SleepList',
    components: {
        SleepStageBar, SportBadge, LoadingSkeleton, LoadError, PageNav, TimeframePicker, DashboardSleep,
        SleepNightsChart, SleepRegularity, NcButton, NcEmptyContent, NcIconSvgWrapper, NcNoteCard,
    },
    data() {
        return {
            icons,
            goals,
            sleepColor: STAGE_COLORS.rem,
            syncErrors: [],
            loading: navData.sleep.length === 0,
            error: false,
            currentPage: 1,
        }
    },
    computed: {
        sessions() {
            return navData.sleep
        },
        firstDay() {
            const times = this.sessions.map(s => parseDate(s.startTime))
            return times.length ? new Date(Math.min(...times)) : null
        },
        timeframe() {
            return resolveTimeframe(this.$route.query, this.firstDay)
        },
        ctx() {
            return measureContext(navData.activities, navData.sleep, training.activities)
        },
        nights() {
            return nightsBetween(this.ctx.nights, this.timeframe.from, this.timeframe.to)
        },
        // Sessions (naps included) that started in the timeframe, newest first
        windowSessions() {
            return this.sessions.filter(s => {
                const d = parseDate(s.startTime)
                return d >= this.timeframe.from && d < this.timeframe.to
            })
        },
        loadByDay() {
            if (!training.activities) return null
            const map = new Map()
            for (const a of training.activities) {
                const k = localDayKey(a.startTime)
                map.set(k, (map.get(k) ?? 0) + a.trimp)
            }
            return map
        },
        totalPages() {
            return Math.ceil(this.windowSessions.length / PAGE_SIZE) || 1
        },
        paginated() {
            const start = (this.currentPage - 1) * PAGE_SIZE
            return this.windowSessions.slice(start, start + PAGE_SIZE)
        },
    },
    watch: {
        '$route.query': {
            handler(query) { rememberTimeframe(query) },
            immediate: true,
        },
        windowSessions() {
            this.currentPage = 1
        },
    },
    mounted() {
        this.load(false)
        window.addEventListener('fit-tracker:refresh', this.refresh)
    },
    beforeUnmount() {
        window.removeEventListener('fit-tracker:refresh', this.refresh)
    },
    methods: {
        formatDay,
        formatTime,
        formatDurationShort,
        refresh() {
            this.load(true)
        },
        // force: fetch (and sync) again instead of using what's already loaded
        async load(force) {
            this.loading = navData.sleep.length === 0 || force
            this.error = false
            try {
                const { syncErrors } = await fetchActivities(force)
                this.syncErrors = syncErrors
            } catch (e) {
                this.error = true
                return
            } finally {
                this.loading = false
            }
            fetchGoals(force).catch(() => {})
            fetchTraining(force).catch(() => {})
        },
        hasStageDurations(session) {
            return (session.timeDeep || 0) + (session.timeLight || 0) + (session.timeRem || 0) + (session.timeAwake || 0) > 0
        },
    },
}
</script>

<style scoped>
.sleep-page {
    max-width: 1200px;
}
.sleep-page__header {
    display: flex;
    flex-direction: column;
    gap: var(--ft-gap-3);
    margin-bottom: var(--ft-gap-5);
}
.sleep-page__title {
    margin-bottom: 0;
}
.sleep-page__errors ul {
    margin: var(--ft-gap-1) 0 0 var(--ft-gap-4);
    padding: 0;
    list-style: disc;
}
.sleep-page__boxes {
    margin-bottom: var(--ft-gap-3);
}
.sleep-page__sessions-header {
    display: flex;
    align-items: baseline;
    gap: var(--ft-gap-3);
    margin: var(--ft-gap-2) 0 var(--ft-gap-3);
}
.sleep-page__sessions-header h3 {
    margin: 0;
}
.sleep-page__count {
    color: var(--color-text-maxcontrast);
}
.sleep-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(280px, 100%), 1fr));
    gap: var(--ft-gap-4);
}
.sleep-card {
    display: flex;
    gap: var(--ft-gap-3);
    padding: var(--ft-gap-4);
    border-radius: var(--border-radius-container);
    border: 1px solid var(--color-border);
    background: var(--color-main-background);
    color: var(--color-main-text);
    text-decoration: none;
    transition: background-color 0.15s, border-color 0.15s;
}
.sleep-card:hover {
    background: var(--color-background-hover);
    border-color: var(--color-border-maxcontrast);
}
.sleep-card:focus-visible {
    outline: 2px solid var(--color-primary-element);
    outline-offset: 2px;
}
.sleep-card__body { flex: 1; min-width: 0; }
.sleep-card__name {
    font-weight: bold;
    margin-bottom: var(--ft-gap-1);
}
.sleep-card__date {
    font-size: var(--font-size-small);
    color: var(--color-text-maxcontrast);
    margin-bottom: var(--ft-gap-1);
}
.sleep-card__stats {
    display: flex;
    flex-wrap: wrap;
    gap: var(--ft-gap-1) var(--ft-gap-3);
    font-size: var(--font-size-small);
}
.sleep-card__stagebar {
    margin-top: var(--ft-gap-2);
}
.sleep-page__pages {
    justify-content: center;
    margin-top: var(--ft-gap-6);
}
</style>
