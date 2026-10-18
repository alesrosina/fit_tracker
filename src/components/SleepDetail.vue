<template>
    <div class="sleep-detail">
        <DetailToolbar
            back-to="/sleep"
            :back-label="t('fit_tracker', 'Sleep')"
            :show-actions="!!session"
            :delete-label="t('fit_tracker', 'Delete sleep session')"
            :delete-title="t('fit_tracker', 'Delete sleep session and move its file to the trash?')"
            :delete-message="deleteMessage"
            :busy-label="busyLabel"
            @delete="deleteSession"
            @reimport="reimport"
        />

        <LoadingSkeleton v-if="loading" variant="detail" />
        <NcEmptyContent
            v-else-if="notFound"
            :name="t('fit_tracker', 'Sleep session not found')"
            :description="t('fit_tracker', 'It may have been deleted.')"
        >
            <template #icon>
                <NcIconSvgWrapper :svg="icons.bedtime" />
            </template>
        </NcEmptyContent>
        <LoadError
            v-else-if="error"
            :heading="t('fit_tracker', 'Could not load sleep session')"
            :message="t('fit_tracker', 'Check your connection and try again.')"
            @retry="load"
        />

        <template v-else-if="session">
            <div class="sleep-detail__header">
                <SportBadge icon="bedtime" :color="stageColors.rem" :size="32" />
                <div>
                    <h2>{{ session.name }}</h2>
                    <div class="subtitle">{{ formatDateTime(session.startTime) }} – {{ formatTime(session.endTime) }}</div>
                </div>
            </div>

            <!-- Key stats -->
            <div class="stats-grid">
                <div v-for="stat in stats" :key="stat.label" class="stat">
                    <div class="stat__label">{{ stat.label }}</div>
                    <div class="stat__value">{{ stat.value }}<span v-if="stat.unit" class="stat__unit">{{ stat.unit }}</span></div>
                </div>
            </div>

            <!-- Stage distribution bar -->
            <section v-if="hasStages">
                <h3>{{ t('fit_tracker', 'Stage distribution') }}</h3>
                <SleepStageBar
                    :timeDeep="session.timeDeep"
                    :timeLight="session.timeLight"
                    :timeRem="session.timeRem"
                    :timeAwake="session.timeAwake"
                    class="dist-bar"
                />
                <div class="stage-legend">
                    <span v-for="key in ['deep', 'rem', 'light', 'awake']" :key="key" class="legend-item">
                        <span class="dot" :style="{ background: stageColors[key] }"></span>{{ stageLabel(key) }}
                    </span>
                </div>
            </section>

            <!-- Sleep stages timeline -->
            <section v-if="stages.length > 0">
                <h3>{{ t('fit_tracker', 'Sleep timeline') }}</h3>
                <div class="timeline">
                    <div class="timeline__track" role="img" :aria-label="t('fit_tracker', 'Sleep stages over the night')">
                        <div
                            v-for="(seg, idx) in timelineSegments"
                            :key="idx"
                            class="timeline__seg"
                            :style="{ width: seg.pct + '%', background: seg.color }"
                            :title="seg.label + '\n' + seg.timeStr"
                        ></div>
                    </div>
                    <div class="timeline__labels" aria-hidden="true">
                        <span
                            v-for="(mark, idx) in timeMarks"
                            :key="idx"
                            class="timeline__mark"
                            :style="{ left: mark.pct + '%' }"
                        >{{ mark.label }}</span>
                    </div>
                </div>
                <!-- Stage list -->
                <table class="stages-table">
                    <thead>
                        <tr>
                            <th>{{ t('fit_tracker', 'Time') }}</th>
                            <th>{{ t('fit_tracker', 'Stage') }}</th>
                            <th>{{ t('fit_tracker', 'Duration') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(s, idx) in stageRows" :key="idx">
                            <td>{{ s.time }}</td>
                            <td>
                                <span class="stage-name"><span class="dot" :style="{ background: s.color }"></span>{{ s.stage }}</span>
                            </td>
                            <td>{{ s.durStr }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </template>
    </div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { t } from '@nextcloud/l10n'
import { NcEmptyContent, NcIconSvgWrapper } from '@nextcloud/vue'
import SleepStageBar from './SleepStageBar.vue'
import DetailToolbar from './DetailToolbar.vue'
import LoadingSkeleton from './LoadingSkeleton.vue'
import LoadError from './LoadError.vue'
import SportBadge from './SportBadge.vue'
import { STAGE_COLORS, stageLabel } from '../sleepStages.js'
import { icons } from '../icons.js'
import { formatDurationShort, formatDateTime, formatTime } from '../format.js'
import { deleteItem, reimportItem, fileName } from '../fileActions.js'

export default {
    name: 'SleepDetail',
    components: { SleepStageBar, DetailToolbar, LoadingSkeleton, LoadError, SportBadge, NcEmptyContent, NcIconSvgWrapper },
    props: { id: { type: String, required: true } },
    data() {
        return {
            icons,
            stageColors: STAGE_COLORS,
            session: null,
            stages: [],
            loading: true,
            error: false,
            notFound: false,
            busy: null, // 'reimport' | 'delete' while running
        }
    },
    computed: {
        busyLabel() {
            if (this.busy === 'reimport') return t('fit_tracker', 'Re-importing…')
            if (this.busy === 'delete') return t('fit_tracker', 'Deleting…')
            return ''
        },
        deleteMessage() {
            // TRANSLATORS: {file} is the name of the .fit file, e.g. "2026-03-01-06-30-00.fit"
            return t('fit_tracker', 'The sleep session will be removed from FIT Tracker and its file {file} will be moved to the Nextcloud trash. If you restore the file from the trash, the sleep session is imported again on the next sync.', {
                file: fileName(this.session?.fitFilePath),
            })
        },
        stats() {
            const s = this.session
            if (!s) return []
            const list = [{ label: t('fit_tracker', 'Duration'), value: formatDurationShort(s.duration) }]
            if (s.score) list.push({ label: t('fit_tracker', 'Sleep score'), value: s.score, unit: '/100' })
            if (s.hrvScore) list.push({ label: t('fit_tracker', 'HRV score'), value: s.hrvScore })
            if (s.timeDeep) list.push({ label: t('fit_tracker', 'Deep sleep'), value: formatDurationShort(s.timeDeep) })
            if (s.timeRem) list.push({ label: t('fit_tracker', 'REM sleep'), value: formatDurationShort(s.timeRem) })
            if (s.timeLight) list.push({ label: t('fit_tracker', 'Light sleep'), value: formatDurationShort(s.timeLight) })
            if (s.timeAwake) list.push({ label: t('fit_tracker', 'Time awake'), value: formatDurationShort(s.timeAwake) })
            return list
        },
        hasStages() {
            const s = this.session
            return s && ((s.timeDeep || 0) + (s.timeLight || 0) + (s.timeRem || 0) + (s.timeAwake || 0)) > 0
        },
        totalSecs() {
            if (!this.stages.length) return 0
            const t0 = new Date(this.stages[0].timestamp.replace(' ', 'T'))
            const tN = new Date(this.stages[this.stages.length - 1].timestamp.replace(' ', 'T'))
            return (tN - t0) / 1000 || this.session?.duration || 1
        },
        timelineSegments() {
            const segs = []
            const t0   = this.stages.length ? new Date(this.stages[0].timestamp.replace(' ', 'T')) : null
            const tot  = this.totalSecs
            for (let i = 0; i < this.stages.length; i++) {
                const curr = this.stages[i]
                const next = this.stages[i + 1]
                const tCurr = new Date(curr.timestamp.replace(' ', 'T'))
                const tNext = next ? new Date(next.timestamp.replace(' ', 'T')) : new Date(tCurr.getTime() + 60000)
                const durSec = (tNext - tCurr) / 1000
                segs.push({
                    color:   STAGE_COLORS[curr.stage] ?? STAGE_COLORS.unmeasurable,
                    pct:     Math.max(0.5, durSec / tot * 100),
                    label:   stageLabel(curr.stage),
                    timeStr: tCurr.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' }),
                })
            }
            return segs
        },
        timeMarks() {
            if (!this.stages.length) return []
            const t0  = new Date(this.stages[0].timestamp.replace(' ', 'T'))
            const tot = this.totalSecs
            const marks = []
            // Mark every full hour
            const startHour = new Date(t0)
            startHour.setMinutes(0, 0, 0)
            startHour.setHours(startHour.getHours() + 1)
            let cur = startHour
            while ((cur - t0) / 1000 < tot) {
                const pct = (cur - t0) / 1000 / tot * 100
                marks.push({
                    pct,
                    label: cur.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' }),
                })
                cur = new Date(cur.getTime() + 3600000)
            }
            return marks
        },
        stageRows() {
            return this.stages.map((s, i) => {
                const next    = this.stages[i + 1]
                const tCurr   = new Date(s.timestamp.replace(' ', 'T'))
                const tNext   = next ? new Date(next.timestamp.replace(' ', 'T')) : null
                const durSec  = tNext ? Math.round((tNext - tCurr) / 1000) : null
                return {
                    time:   tCurr.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' }),
                    stage:  stageLabel(s.stage),
                    color:  STAGE_COLORS[s.stage] ?? STAGE_COLORS.unmeasurable,
                    durStr: durSec ? formatDurationShort(durSec) : '–',
                }
            })
        },
    },
    watch: {
        // Re-import can land on a new id for the same page component
        id() {
            this.load()
        },
    },
    async mounted() {
        await this.load()
    },
    methods: {
        async load() {
            this.loading  = true
            this.error    = false
            this.notFound = false
            try {
                const [sessRes, stagesRes] = await Promise.all([
                    axios.get(generateUrl(`/apps/fit_tracker/api/sleep/${this.id}`)),
                    axios.get(generateUrl(`/apps/fit_tracker/api/sleep/${this.id}/stages`)),
                ])
                this.session = sessRes.data
                this.stages  = stagesRes.data
            } catch (e) {
                if (e.response?.status === 404) this.notFound = true
                else this.error = true
            } finally {
                this.loading = false
            }
        },
        async deleteSession() {
            this.busy = 'delete'
            const deleted = await deleteItem('sleep', this.id, this.session?.fitFilePath)
            this.busy = null
            if (deleted) this.$router.push('/sleep')
        },
        async reimport() {
            // The content is about to be replaced: show the skeleton meanwhile
            this.busy = 'reimport'
            this.loading = true
            const ok = await reimportItem('sleep', this.id, this.$router)
            this.busy = null
            if (!ok) this.loading = false
        },
        stageLabel,
        formatDateTime,
        formatTime,
    },
}
</script>

<style scoped>
.sleep-detail {
    padding: 0 var(--ft-gap-5) var(--ft-gap-6);
    max-width: 960px;
}
.sleep-detail__header {
    display: flex;
    align-items: center;
    gap: var(--ft-gap-4);
    margin-bottom: var(--ft-gap-6);
}
.sleep-detail__header h2 {
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
.stat__unit { font-size: var(--font-size-small); font-weight: normal; }
/* Stage distribution bar */
.dist-bar { height: var(--ft-gap-4); border-radius: var(--border-radius-element); }
.stage-legend {
    display: flex;
    flex-wrap: wrap;
    gap: var(--ft-gap-4);
    margin-top: var(--ft-gap-2);
    font-size: var(--font-size-small);
}
.legend-item,
.stage-name { display: inline-flex; align-items: center; gap: var(--ft-gap-1); }
.dot {
    width: 10px;
    height: 10px;
    border-radius: var(--border-radius-pill);
    display: inline-block;
    flex-shrink: 0;
}
/* Timeline */
.timeline {
    position: relative;
    margin-bottom: var(--ft-gap-3);
}
.timeline__track {
    display: flex;
    height: var(--ft-gap-8);
    border-radius: var(--border-radius-element);
    overflow: hidden;
    gap: 1px;
}
.timeline__seg {
    min-width: 2px;
    transition: opacity 0.1s;
}
.timeline__seg:hover { opacity: 0.8; }
.timeline__labels {
    position: relative;
    height: var(--ft-gap-5);
    margin-top: var(--ft-gap-1);
}
.timeline__mark {
    position: absolute;
    transform: translateX(-50%);
    font-size: var(--font-size-small);
    color: var(--color-text-maxcontrast);
    white-space: nowrap;
}
/* Stages table */
.stages-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: var(--ft-gap-4);
}
.stages-table th, .stages-table td {
    padding: var(--ft-gap-2) var(--ft-gap-3);
    text-align: start;
    border-bottom: 1px solid var(--color-border);
}
.stages-table th {
    font-size: var(--font-size-small);
    font-weight: bold;
    color: var(--color-text-maxcontrast);
}
</style>
