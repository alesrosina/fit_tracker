<template>
    <div class="sleep-list">
        <h2 class="page-title">{{ t('fit_tracker', 'Sleep') }}</h2>
        <LoadingSkeleton v-if="loading" />
        <LoadError
            v-else-if="error"
            :heading="t('fit_tracker', 'Could not load sleep sessions')"
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

            <section v-if="sessions.length > 0" class="month-panel">
                <SleepMonthChart :sessions="sessions" />
            </section>

            <NcEmptyContent
                v-if="sessions.length === 0"
                :name="t('fit_tracker', 'No sleep sessions yet')"
                :description="t('fit_tracker', 'Sleep and nap files from your watch appear here once they are in the source folder.')"
            >
                <template #icon>
                    <NcIconSvgWrapper :svg="icons.bedtime" />
                </template>
                <template #action>
                    <NcButton @click="load">
                        {{ t('fit_tracker', 'Sync now') }}
                    </NcButton>
                </template>
            </NcEmptyContent>
            <template v-else>
                <div class="sleep-grid">
                    <router-link
                        v-for="session in paginated"
                        :key="session.id"
                        :to="'/sleep/' + session.id"
                        class="sleep-card"
                    >
                        <SportBadge icon="bedtime" :color="sleepColor" :size="24" />
                        <div class="sleep-card__body">
                            <div class="sleep-card__name">{{ session.name }}</div>
                            <div class="sleep-card__date">{{ formatDay(session.startTime) }}</div>
                            <div class="sleep-card__duration">{{ formatDurationShort(session.duration) }}</div>
                            <div v-if="session.score" class="sleep-card__score">
                                {{ t('fit_tracker', 'Score {score}/100', { score: session.score }) }}
                            </div>
                            <SleepStageBar
                                v-if="hasStageDurations(session)"
                                :timeDeep="session.timeDeep"
                                :timeLight="session.timeLight"
                                :timeRem="session.timeRem"
                                :timeAwake="session.timeAwake"
                                class="sleep-card__stagebar"
                            />
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
import { NcButton, NcEmptyContent, NcIconSvgWrapper, NcNoteCard } from '@nextcloud/vue'
import SleepStageBar from './SleepStageBar.vue'
import SleepMonthChart from './SleepMonthChart.vue'
import SportBadge from './SportBadge.vue'
import LoadingSkeleton from './LoadingSkeleton.vue'
import LoadError from './LoadError.vue'
import PageNav from './PageNav.vue'
import { setSleep } from '../store/navData.js'
import { STAGE_COLORS } from '../sleepStages.js'
import { icons } from '../icons.js'
import { formatDurationShort, formatDay } from '../format.js'

const PAGE_SIZE = 9

export default {
    name: 'SleepList',
    components: {
        SleepStageBar, SleepMonthChart, SportBadge, LoadingSkeleton, LoadError, PageNav,
        NcButton, NcEmptyContent, NcIconSvgWrapper, NcNoteCard,
    },
    data() {
        return {
            icons,
            sleepColor: STAGE_COLORS.rem,
            sessions: [],
            syncErrors: [],
            loading: true,
            error: false,
            currentPage: 1,
        }
    },
    computed: {
        totalPages() {
            return Math.ceil(this.sessions.length / PAGE_SIZE) || 1
        },
        paginated() {
            const start = (this.currentPage - 1) * PAGE_SIZE
            return this.sessions.slice(start, start + PAGE_SIZE)
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
            this.currentPage = 1
            try {
                const { data } = await axios.get(generateUrl('/apps/fit_tracker/api/sleep'))
                this.sessions   = data.sessions   ?? []
                this.syncErrors = data.syncErrors  ?? []
                setSleep(this.sessions)
            } catch (e) {
                this.error = true
            } finally {
                this.loading = false
            }
        },
        formatDay,
        formatDurationShort,
        hasStageDurations(session) {
            return (session.timeDeep || 0) + (session.timeLight || 0) + (session.timeRem || 0) + (session.timeAwake || 0) > 0
        },
    },
}
</script>

<style scoped>
.sleep-list {
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
.month-panel {
    background: var(--color-main-background);
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-container);
    padding: var(--ft-gap-4);
    margin-bottom: var(--ft-gap-6);
}
.sleep-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
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
.sleep-card__duration {
    font-size: 20px;
    font-weight: bold;
    margin-bottom: var(--ft-gap-1);
}
.sleep-card__score {
    font-size: var(--font-size-small);
}
.sleep-card__stagebar {
    margin-top: var(--ft-gap-2);
}
.pagination {
    justify-content: center;
    margin-top: var(--ft-gap-6);
}
</style>
