<template>
    <div class="sleep-list">
        <h2 class="page-title">Sleep</h2>
        <div v-if="loading" class="loading"></div>
        <div v-else-if="error" class="error">{{ error }}</div>
        <template v-else>
            <div v-if="syncErrors.length > 0" class="sync-errors">
                <strong>Import errors:</strong>
                <ul>
                    <li v-for="e in syncErrors" :key="e">{{ e }}</li>
                </ul>
            </div>

            <div v-if="sessions.length > 0" class="week-panel">
                <h3 class="panel-title">Last 7 Nights</h3>
                <SleepWeekChart :sessions="sessions" />
            </div>

            <div v-if="sessions.length === 0" class="empty">
                No sleep sessions found. Make sure your FIT folder contains sleep files.
            </div>
            <template v-else>
                <div class="sleep-grid">
                    <div
                        v-for="session in paginated"
                        :key="session.id"
                        class="sleep-card"
                        @click="$router.push('/sleep/' + session.id)"
                    >
                        <div class="sleep-card__icon">🛌</div>
                        <div class="sleep-card__body">
                            <div class="sleep-card__name">{{ session.name }}</div>
                            <div class="sleep-card__date">{{ formatDate(session.startTime) }}</div>
                            <div class="sleep-card__duration">{{ formatDuration(session.duration) }}</div>
                            <div v-if="session.score" class="sleep-card__score">
                                Score: <strong>{{ session.score }}/100</strong>
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
import SleepStageBar from './SleepStageBar.vue'
import SleepWeekChart from './SleepWeekChart.vue'

export default {
    name: 'SleepList',
    components: { SleepStageBar, SleepWeekChart },
    data() {
        return {
            sessions: [],
            syncErrors: [],
            loading: true,
            error: null,
            currentPage: 1,
        }
    },
    computed: {
        totalPages() {
            return Math.ceil(this.sessions.length / 9) || 1
        },
        paginated() {
            const start = (this.currentPage - 1) * 9
            return this.sessions.slice(start, start + 9)
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
            this.currentPage = 1
            try {
                const { data } = await axios.get(generateUrl('/apps/fit_tracker/api/sleep'))
                this.sessions   = data.sessions   ?? []
                this.syncErrors = data.syncErrors  ?? []
            } catch (e) {
                this.error = 'Failed to load sleep sessions'
            } finally {
                this.loading = false
            }
        },
        formatDate(raw) {
            if (!raw) return ''
            return new Date(raw).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
        },
        formatDuration(seconds) {
            const h = Math.floor(seconds / 3600)
            const m = Math.floor((seconds % 3600) / 60)
            if (h > 0) return `${h}h ${m}m`
            return `${m}m`
        },
        hasStageDurations(session) {
            return (session.timeDeep || 0) + (session.timeLight || 0) + (session.timeRem || 0) + (session.timeAwake || 0) > 0
        },
    },
}
</script>

<style scoped>
.sleep-list {
    padding: 0 20px;
    max-width: 1000px;
}
.page-title {
    margin: 0 0 20px 30px;
    font-size: 20px;
    font-weight: 600;
    padding-top: 8px;
}
.week-panel {
    background: var(--color-main-background);
    border: 1px solid var(--color-border);
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 24px;
}
.panel-title {
    margin: 0 0 12px;
    font-size: 14px;
    font-weight: 600;
    color: var(--color-text-maxcontrast);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.sleep-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
}
.sleep-card {
    display: flex;
    gap: 12px;
    padding: 16px;
    border-radius: 8px;
    border: 1px solid var(--color-border);
    cursor: pointer;
    transition: box-shadow 0.15s;
    background: var(--color-main-background);
}
.sleep-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.sleep-card__icon {
    font-size: 28px;
    line-height: 1;
}
.sleep-card__name {
    font-weight: 600;
    margin-bottom: 2px;
}
.sleep-card__date {
    font-size: 12px;
    color: var(--color-text-maxcontrast);
    margin-bottom: 4px;
}
.sleep-card__duration {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 4px;
}
.sleep-card__score {
    font-size: 13px;
    margin-bottom: 6px;
}
.sleep-card__stagebar {
    margin-top: 6px;
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
