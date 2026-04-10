<template>
    <div class="sleep-detail">
        <div class="sleep-detail__back">
            <button class="back-btn" @click="$router.push('/sleep')">← Back</button>
            <button v-if="session" class="delete-btn" @click="deleteSession">Delete</button>
        </div>

        <div v-if="loading" class="loading"></div>
        <div v-else-if="error" class="error">{{ error }}</div>

        <template v-else-if="session">
            <div class="sleep-detail__header">
                <span class="sleep-icon">🛌</span>
                <div>
                    <h2>{{ session.name }}</h2>
                    <div class="subtitle">{{ formatDate(session.startTime) }} → {{ formatTime(session.endTime) }}</div>
                </div>
            </div>

            <!-- Key stats -->
            <div class="stats-grid">
                <div class="stat">
                    <div class="stat__label">Duration</div>
                    <div class="stat__value">{{ formatDuration(session.duration) }}</div>
                </div>
                <div v-if="session.score" class="stat">
                    <div class="stat__label">Sleep Score</div>
                    <div class="stat__value">{{ session.score }}<span class="stat__unit">/100</span></div>
                </div>
                <div v-if="session.hrvScore" class="stat">
                    <div class="stat__label">HRV Score</div>
                    <div class="stat__value">{{ session.hrvScore }}</div>
                </div>
                <div v-if="session.timeDeep" class="stat">
                    <div class="stat__label">Deep Sleep</div>
                    <div class="stat__value">{{ formatMins(session.timeDeep) }}</div>
                </div>
                <div v-if="session.timeRem" class="stat">
                    <div class="stat__label">REM Sleep</div>
                    <div class="stat__value">{{ formatMins(session.timeRem) }}</div>
                </div>
                <div v-if="session.timeLight" class="stat">
                    <div class="stat__label">Light Sleep</div>
                    <div class="stat__value">{{ formatMins(session.timeLight) }}</div>
                </div>
                <div v-if="session.timeAwake" class="stat">
                    <div class="stat__label">Time Awake</div>
                    <div class="stat__value">{{ formatMins(session.timeAwake) }}</div>
                </div>
            </div>

            <!-- Stage distribution bar -->
            <div v-if="hasStages" class="section stage-section">
                <h3>Stage Distribution</h3>
                <SleepStageBar
                    :timeDeep="session.timeDeep"
                    :timeLight="session.timeLight"
                    :timeRem="session.timeRem"
                    :timeAwake="session.timeAwake"
                    class="dist-bar"
                />
                <div class="stage-legend">
                    <span class="legend-item"><span class="dot" style="background:#1e40af"></span>Deep</span>
                    <span class="legend-item"><span class="dot" style="background:#7c3aed"></span>REM</span>
                    <span class="legend-item"><span class="dot" style="background:#60a5fa"></span>Light</span>
                    <span class="legend-item"><span class="dot" style="background:#fbbf24"></span>Awake</span>
                </div>
            </div>

            <!-- Sleep stages timeline -->
            <div v-if="stages.length > 0" class="section">
                <h3>Sleep Timeline</h3>
                <div class="timeline">
                    <div class="timeline__track">
                        <div
                            v-for="(seg, idx) in timelineSegments"
                            :key="idx"
                            class="timeline__seg"
                            :style="{ width: seg.pct + '%', background: seg.color }"
                            :title="seg.label + '\n' + seg.timeStr"
                        ></div>
                    </div>
                    <div class="timeline__labels">
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
                            <th>Time</th>
                            <th>Stage</th>
                            <th>Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(s, idx) in stageRows" :key="idx">
                            <td>{{ s.time }}</td>
                            <td>
                                <span class="stage-badge" :style="{ background: s.color }">{{ s.stage }}</span>
                            </td>
                            <td>{{ s.durStr }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
    </div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import SleepStageBar from './SleepStageBar.vue'

const STAGE_COLORS = {
    deep:          '#1e40af',
    rem:           '#7c3aed',
    light:         '#60a5fa',
    awake:         '#fbbf24',
    unmeasurable:  '#d1d5db',
}

export default {
    name: 'SleepDetail',
    components: { SleepStageBar },
    props: { id: { type: String, required: true } },
    data() {
        return {
            session: null,
            stages: [],
            loading: true,
            error: null,
        }
    },
    computed: {
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
                    color:   STAGE_COLORS[curr.stage] ?? '#d1d5db',
                    pct:     Math.max(0.5, durSec / tot * 100),
                    label:   curr.stage.charAt(0).toUpperCase() + curr.stage.slice(1),
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
                    stage:  s.stage.charAt(0).toUpperCase() + s.stage.slice(1),
                    color:  STAGE_COLORS[s.stage] ?? '#d1d5db',
                    durStr: durSec ? this.formatMins(durSec) : '–',
                }
            })
        },
    },
    async mounted() {
        await this.load()
    },
    methods: {
        async load() {
            this.loading = true
            this.error   = null
            try {
                const [sessRes, stagesRes] = await Promise.all([
                    axios.get(generateUrl(`/apps/fit_tracker/api/sleep/${this.id}`)),
                    axios.get(generateUrl(`/apps/fit_tracker/api/sleep/${this.id}/stages`)),
                ])
                this.session = sessRes.data
                this.stages  = stagesRes.data
            } catch (e) {
                this.error = e.response?.status === 404 ? 'Sleep session not found' : 'Failed to load sleep data'
            } finally {
                this.loading = false
            }
        },
        async deleteSession() {
            if (!confirm('Delete this sleep session? This cannot be undone.')) return
            await axios.delete(generateUrl(`/apps/fit_tracker/api/sleep/${this.id}`))
            this.$router.push('/sleep')
            window.dispatchEvent(new CustomEvent('fit-tracker:refresh'))
        },
        formatDate(raw) {
            if (!raw) return ''
            return new Date(raw).toLocaleString(undefined, { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' })
        },
        formatTime(raw) {
            if (!raw) return ''
            return new Date(raw).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })
        },
        formatDuration(seconds) {
            const h = Math.floor(seconds / 3600)
            const m = Math.floor((seconds % 3600) / 60)
            if (h > 0) return `${h}h ${m}m`
            return `${m}m`
        },
        formatMins(seconds) {
            const h = Math.floor(seconds / 3600)
            const m = Math.floor((seconds % 3600) / 60)
            if (h > 0) return `${h}h ${m}m`
            return `${m}m`
        },
    },
}
</script>

<style scoped>
.sleep-detail {
    padding: 20px;
    padding-top: 52px;
    max-width: 960px;
}
.sleep-detail__back {
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
.delete-btn:hover { background: var(--color-error-hover); }
.sleep-detail__header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
}
.sleep-icon { font-size: 40px; }
.sleep-detail__header h2 { margin: 0 0 4px; }
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
.stat__unit { font-size: 13px; font-weight: 400; }
.section { margin-bottom: 32px; }
.section h3 { margin-bottom: 12px; }
/* Stage distribution bar */
.stage-section {}
.dist-bar { height: 16px; border-radius: 8px; }
.stage-legend {
    display: flex;
    gap: 16px;
    margin-top: 8px;
    font-size: 12px;
}
.legend-item { display: flex; align-items: center; gap: 4px; }
.dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
/* Timeline */
.timeline {
    position: relative;
    margin-bottom: 12px;
}
.timeline__track {
    display: flex;
    height: 32px;
    border-radius: 6px;
    overflow: hidden;
    gap: 1px;
}
.timeline__seg {
    cursor: default;
    min-width: 2px;
    transition: opacity 0.1s;
}
.timeline__seg:hover { opacity: 0.8; }
.timeline__labels {
    position: relative;
    height: 20px;
    margin-top: 4px;
}
.timeline__mark {
    position: absolute;
    transform: translateX(-50%);
    font-size: 10px;
    color: var(--color-text-maxcontrast);
    white-space: nowrap;
}
/* Stages table */
.stages-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
    margin-top: 16px;
}
.stages-table th, .stages-table td {
    padding: 6px 12px;
    text-align: left;
    border-bottom: 1px solid var(--color-border);
}
.stages-table th { font-weight: 600; color: var(--color-text-maxcontrast); }
.stage-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 12px;
    color: #fff;
    font-weight: 500;
}
.loading, .error { padding: 40px; text-align: center; }
.error { color: var(--color-error); }
</style>
