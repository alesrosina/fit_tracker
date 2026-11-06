<template>
    <div class="ft-page dashboard">
        <div class="dashboard__header">
            <h2 class="ft-page-title dashboard__title">{{ pageTitle }}</h2>
            <TimeframePicker v-if="hasData" :timeframe="timeframe" />
        </div>

        <LoadingSkeleton v-if="loading" />
        <LoadError
            v-else-if="error"
            :heading="t('fit_tracker', 'Could not load activities')"
            :message="t('fit_tracker', 'Check your connection and try again.')"
            @retry="load(true)"
        />
        <template v-else>
            <NcNoteCard
                v-if="syncErrors.length > 0"
                type="warning"
                :heading="n('fit_tracker', '%n file could not be imported', '%n files could not be imported', syncErrors.length)"
                class="dashboard__sync-errors"
            >
                <ul>
                    <li v-for="e in syncErrors" :key="e">{{ e }}</li>
                </ul>
            </NcNoteCard>

            <NcEmptyContent
                v-if="!hasData"
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
                    <NcButton v-else-if="!sport" @click="load(true)">
                        {{ t('fit_tracker', 'Sync now') }}
                    </NcButton>
                </template>
            </NcEmptyContent>

            <template v-else>
                <!-- Activity numbers -->
                <div class="dashboard-grid">
                    <StatTile
                        v-for="tile in activityTiles"
                        :key="tile.key"
                        :label="tile.label"
                        :value="tile.value"
                        :sub="tile.sub"
                        :change="tile.change"
                        :goal="tile.goal"
                    />
                </div>
                <p v-if="!hasGoals" class="ft-panel__hint dashboard__goal-hint">
                    <router-link to="/goals">{{ t('fit_tracker', 'Set goals') }}</router-link>
                    {{ t('fit_tracker', 'to see your progress on these numbers.') }}
                </p>


                <DashboardTrendChart
                    :activities="sportActivities"
                    :timeframe="timeframe"
                    :by-sport="!sport"
                    :has-distance="hasDistance"
                />

                <div class="dashboard__row">
                    <section v-if="!sport && sportShares.length > 1" class="ft-panel">
                        <h3>{{ t('fit_tracker', 'By sport') }}</h3>
                        <ul class="sport-shares">
                            <li v-for="s in sportShares" :key="s.sport">
                                <router-link :to="{ path: '/', query: { ...$route.query, sport: s.sport } }" class="sport-share">
                                    <span class="sport-share__name">
                                        <NcIconSvgWrapper :svg="icons[sportIcon(s.sport)] ?? icons.exercise" :size="16" />
                                        {{ sportLabel(s.sport) }}
                                    </span>
                                    <span class="sport-share__bar">
                                        <span :style="{ width: s.share * 100 + '%', background: sportColor(s.sport) }" />
                                    </span>
                                    <span class="sport-share__value">{{ formatDurationShort(s.duration) }} · {{ s.count }}</span>
                                </router-link>
                            </li>
                        </ul>
                    </section>

                    <section v-if="trainingNow" class="ft-panel">
                        <h3>{{ t('fit_tracker', 'Training') }}</h3>
                        <div class="training-now">
                            <div>
                                <div class="ft-stat__label">{{ t('fit_tracker', 'Fitness') }}</div>
                                <div class="training-now__value">{{ Math.round(trainingNow.ctl) }}</div>
                            </div>
                            <div>
                                <div class="ft-stat__label">{{ t('fit_tracker', 'Fatigue') }}</div>
                                <div class="training-now__value">{{ Math.round(trainingNow.atl) }}</div>
                            </div>
                            <div>
                                <div class="ft-stat__label">{{ t('fit_tracker', 'Form') }}</div>
                                <div class="training-now__value">{{ trainingNow.tsb > 0 ? '+' : '' }}{{ Math.round(trainingNow.tsb) }}</div>
                            </div>
                        </div>
                        <p class="ft-panel__hint">{{ t('fit_tracker', 'As of today, from heart rate training load.') }}</p>
                        <template v-if="zoneTotal > 0">
                            <h4>{{ t('fit_tracker', 'Time in heart rate zones') }}</h4>
                            <div class="zone-bar" role="img" :aria-label="zoneAria">
                                <span v-for="z in zoneRows" :key="z.index" :style="{ width: z.share * 100 + '%', background: z.color }" :title="z.label + ': ' + z.time" />
                            </div>
                            <ul class="zone-legend">
                                <li v-for="z in zoneRows" :key="z.index">
                                    <span class="zone-legend__dot" :style="{ background: z.color }" />
                                    {{ z.label }} <strong>{{ z.time }}</strong>
                                </li>
                            </ul>
                        </template>
                        <router-link to="/stats" class="dashboard__more">{{ t('fit_tracker', 'Trends') }}</router-link>
                    </section>

                    <section v-if="highlights.length || recordsInWindow.length" class="ft-panel">
                        <h3>{{ t('fit_tracker', 'Highlights') }}</h3>
                        <ul class="highlights">
                            <li v-for="h in highlights" :key="h.key">
                                <router-link :to="'/activity/' + h.activityId" class="highlight" :title="h.title">
                                    <NcIconSvgWrapper :svg="icons[h.icon]" :size="16" />
                                    <span class="highlight__label">{{ h.label }}</span>
                                    <span class="highlight__value">{{ h.value }}</span>
                                </router-link>
                            </li>
                            <li v-if="recordsInWindow.length">
                                <router-link to="/records" class="highlight">
                                    <NcIconSvgWrapper :svg="icons.trophy" :size="16" />
                                    <span class="highlight__label">{{ t('fit_tracker', 'Personal records') }}</span>
                                    <span class="highlight__value">{{ n('fit_tracker', '%n new', '%n new', recordsInWindow.length) }}</span>
                                </router-link>
                            </li>
                        </ul>
                    </section>

                    <section v-if="gearRows.length" class="ft-panel">
                        <h3>{{ t('fit_tracker', 'Gear') }}</h3>
                        <ul class="gear-rows">
                            <li v-for="g in gearRows" :key="g.id" class="gear-row">
                                <div class="gear-row__top">
                                    <span class="gear-row__name">{{ g.name }}</span>
                                    <span class="gear-row__window">{{ t('fit_tracker', '+{distance} in this timeframe', { distance: formatDistance(g.windowDistance, 1) }) }}</span>
                                </div>
                                <template v-if="g.retireDistance">
                                    <div class="gear-row__bar">
                                        <span :class="g.wearClass" :style="{ width: Math.min(100, g.wear * 100) + '%' }" />
                                    </div>
                                    <div class="gear-row__wear" :class="g.wearClass">
                                        <NcIconSvgWrapper v-if="g.wear >= 0.9" :svg="icons.warning" :size="16" />
                                        {{ g.wearText }}
                                    </div>
                                </template>
                                <div v-else class="gear-row__wear">{{ t('fit_tracker', '{distance} in total', { distance: formatDistance(g.totalDistance, 0) }) }}</div>
                            </li>
                        </ul>
                        <router-link to="/gear" class="dashboard__more">{{ t('fit_tracker', 'All gear') }}</router-link>
                    </section>
                </div>

                <DashboardSleep
                    v-if="!sport && navData.sleep.length > 0"
                    class="dashboard__sleep"
                    :ctx="ctx"
                    :goals="goals.list"
                    :timeframe="timeframe"
                    :load-by-day="loadByDay"
                />

                <ActivityCards :activities="windowActivities" />
            </template>
        </template>
    </div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { t, n } from '@nextcloud/l10n'
import { NcButton, NcEmptyContent, NcIconSvgWrapper, NcNoteCard } from '@nextcloud/vue'
import LoadingSkeleton from './LoadingSkeleton.vue'
import LoadError from './LoadError.vue'
import StatTile from './StatTile.vue'
import TimeframePicker from './TimeframePicker.vue'
import DashboardTrendChart from './DashboardTrendChart.vue'
import DashboardSleep from './DashboardSleep.vue'
import ActivityCards from './ActivityCards.vue'
import { icons } from '../icons.js'
import { sportIcon, sportColor, sportLabel, SPEED_NOT_PACE_SPORTS } from '../sports.js'
import { navData, fetchActivities, openSettings } from '../store/navData.js'
import { goals, fetchGoals } from '../store/goals.js'
import { training, fetchTraining } from '../store/training.js'
import { settings } from '../store/settings.js'
import { parseDate, localDayKey, formatDay, formatDistance, formatDurationShort, formatDurationClock, formatElevation, formatPace, formatSpeed } from '../format.js'
import { resolveTimeframe, compareChange, rememberTimeframe } from '../timeframe.js'
import { measureContext, measure, scaledGoal, formatMetric, fitnessFatigue, effortFamily, ZONE_COLORS, zoneLabel, formatEffortDistance, formatPowerDuration } from '../stats.js'

// The first page and each sport's page: numbers for the selected rolling
// timeframe compared with the one before, progress on goals scaled to the
// timeframe, training, highlights, gear, sleep and the activities in it.
export default {
    name: 'DashboardPage',
    components: {
        LoadingSkeleton, LoadError, StatTile, TimeframePicker, DashboardTrendChart, DashboardSleep, ActivityCards,
        NcButton, NcEmptyContent, NcIconSvgWrapper, NcNoteCard,
    },
    data() {
        return {
            icons,
            navData,
            goals,
            training,
            settings,
            loading: navData.activities.length === 0,
            error: false,
            syncErrors: [],
            gear: [],
            records: null,
        }
    },
    computed: {
        sport() {
            return this.$route.query.sport || null
        },
        pageTitle() {
            return this.sport ? sportLabel(this.sport) : t('fit_tracker', 'Overview')
        },
        // First day with any data, the start of "all time"
        firstDay() {
            const times = [
                ...this.sportActivities.map(a => parseDate(a.startTime)),
                ...(this.sport ? [] : navData.sleep.map(s => parseDate(s.startTime))),
            ]
            return times.length ? new Date(Math.min(...times)) : null
        },
        timeframe() {
            return resolveTimeframe(this.$route.query, this.firstDay)
        },
        sportActivities() {
            return this.sport ? navData.activities.filter(a => a.sport === this.sport) : navData.activities
        },
        hasData() {
            return this.sportActivities.length > 0 || (!this.sport && navData.sleep.length > 0)
        },
        windowActivities() {
            return this.inWindow(this.sportActivities, this.timeframe.from, this.timeframe.to)
        },
        previousActivities() {
            const p = this.timeframe.previous
            return p ? this.inWindow(this.sportActivities, p.from, p.to) : null
        },
        hasDistance() {
            return this.sportActivities.some(a => a.distance > 0)
        },
        ctx() {
            return measureContext(navData.activities, navData.sleep, training.activities)
        },
        hasGoals() {
            return goals.list.some(g => !g.metric.startsWith('sleep_') && (g.sport ?? null) === this.sport)
        },
        activityTiles() {
            const tf = this.timeframe
            const tile = (key, icon, label, valueFn, opts = {}) => {
                const current = measure(this.ctx, key, tf.from, tf.to, this.sport)
                const previous = tf.previous ? measure(this.ctx, key, tf.previous.from, tf.previous.to, this.sport) : null
                const g = scaledGoal(this.ctx, goals.list, key, this.sport, tf.from, tf.to)
                return {
                    key,
                    icon,
                    label,
                    value: valueFn(current),
                    sub: opts.sub ?? '',
                    change: compareChange(current, previous, tf),
                    goal: g ? { fraction: g.fraction, targetText: formatMetric(g.target, key) } : null,
                }
            }
            const tiles = []
            if (this.hasDistance) tiles.push(tile('distance', 'straighten', t('fit_tracker', 'Distance'), v => formatDistance(v, 1)))
            tiles.push(tile('duration', 'timer', t('fit_tracker', 'Time'), v => formatDurationShort(v)))
            if (this.sportActivities.some(a => a.elevationGain > 0)) {
                tiles.push(tile('elevation', 'landscape', t('fit_tracker', 'Elevation gain'), v => formatElevation(v)))
            }
            tiles.push(tile('count', 'exercise', t('fit_tracker', 'Activities'), v => String(v), {
                sub: this.windowActivities.length ? t('fit_tracker', '{perWeek} per week', { perWeek: (this.windowActivities.length / this.timeframe.days * 7).toFixed(1) }) : '',
            }))
            tiles.push(tile('active_days', 'calendar_month', t('fit_tracker', 'Active days'), v => String(v), { sub: this.streakText }))
            if (this.sportActivities.some(a => a.calories > 0)) {
                tiles.push(tile('calories', 'bolt', t('fit_tracker', 'Calories'), v => formatMetric(v, 'calories')))
            }
            if (training.activities) {
                const loadTile = tile('load', 'trending_up', t('fit_tracker', 'Training load'), v => String(Math.round(v)), { sub: t('fit_tracker', 'TRIMP from heart rate') })
                if (loadTile.value !== '0' || loadTile.goal) tiles.push(loadTile)
            }
            if (this.sport) tiles.push(...this.sportTiles)
            return tiles
        },
        // Averages that only make sense within one sport
        sportTiles() {
            const tf = this.timeframe
            const tiles = []
            const speedOf = list => {
                const withDist = list.filter(a => a.distance > 0 && a.duration > 0)
                const time = withDist.reduce((s, a) => s + a.duration, 0)
                return time ? withDist.reduce((s, a) => s + a.distance, 0) / time * 3600 : null
            }
            const hrOf = list => {
                const withHr = list.filter(a => a.avgHr && a.duration)
                const time = withHr.reduce((s, a) => s + a.duration, 0)
                return time ? withHr.reduce((s, a) => s + a.avgHr * a.duration, 0) / time : null
            }
            const speed = speedOf(this.windowActivities)
            if (speed) {
                const usesSpeed = SPEED_NOT_PACE_SPORTS.includes(this.sport)
                tiles.push({
                    key: 'speed',
                    icon: 'speed',
                    label: usesSpeed ? t('fit_tracker', 'Average speed') : t('fit_tracker', 'Average pace'),
                    value: usesSpeed ? formatSpeed(speed) : formatPace(speed),
                    sub: '',
                    change: this.previousActivities ? compareChange(speed, speedOf(this.previousActivities), tf) : null,
                    goal: null,
                })
            }
            const hr = hrOf(this.windowActivities)
            if (hr) {
                const change = this.previousActivities ? compareChange(hr, hrOf(this.previousActivities), tf) : null
                tiles.push({
                    key: 'hr',
                    icon: 'monitor_heart',
                    label: t('fit_tracker', 'Average heart rate'),
                    value: t('fit_tracker', '{bpm} bpm', { bpm: Math.round(hr) }),
                    sub: '',
                    // Neither better nor worse by itself
                    change: change ? { ...change, good: null } : null,
                    goal: null,
                })
            }
            return tiles
        },
        streakText() {
            const days = new Set(this.sportActivities.map(a => localDayKey(a.startTime)))
            const key = d => localDayKey(d)
            // Longest run of active days inside the timeframe
            let longest = 0
            let run = 0
            for (let d = new Date(this.timeframe.from); d < this.timeframe.to; d.setDate(d.getDate() + 1)) {
                run = days.has(key(d)) ? run + 1 : 0
                longest = Math.max(longest, run)
            }
            // Current streak: ending today, or yesterday if today has nothing yet
            let current = 0
            const d = new Date()
            if (!days.has(key(d))) d.setDate(d.getDate() - 1)
            while (days.has(key(d))) {
                current++
                d.setDate(d.getDate() - 1)
            }
            return t('fit_tracker', 'Longest streak {longest} · current {current}', {
                longest: n('fit_tracker', '%n day', '%n days', longest),
                current: n('fit_tracker', '%n day', '%n days', current),
            })
        },
        sportShares() {
            const rows = {}
            for (const a of this.windowActivities) {
                const r = rows[a.sport] ?? (rows[a.sport] = { sport: a.sport, duration: 0, count: 0 })
                r.duration += a.duration || 0
                r.count++
            }
            const list = Object.values(rows).sort((x, y) => y.duration - x.duration)
            const max = list[0]?.duration || 1
            return list.map(r => ({ ...r, share: r.duration / max }))
        },
        // Training load per local day, for fitness/fatigue and sleep after training
        loadByDay() {
            if (!training.activities) return null
            const map = new Map()
            for (const a of training.activities) {
                if (this.sport && a.sport !== this.sport) continue
                const k = localDayKey(a.startTime)
                map.set(k, (map.get(k) ?? 0) + a.trimp)
            }
            return map
        },
        trainingNow() {
            if (!this.loadByDay || this.loadByDay.size === 0) return null
            const today = new Date()
            today.setHours(0, 0, 0, 0)
            const days = fitnessFatigue(this.loadByDay, today, today)
            return days[days.length - 1] ?? null
        },
        zoneSeconds() {
            const zones = [0, 0, 0, 0, 0]
            for (const a of training.activities ?? []) {
                if (this.sport && a.sport !== this.sport) continue
                const d = parseDate(a.startTime)
                if (d < this.timeframe.from || d >= this.timeframe.to) continue
                a.zones.forEach((s, i) => { zones[i] += s })
            }
            return zones
        },
        zoneTotal() {
            return this.zoneSeconds.reduce((a, b) => a + b, 0)
        },
        zoneRows() {
            return this.zoneSeconds.map((s, i) => ({
                index: i,
                label: zoneLabel(i),
                color: ZONE_COLORS[i],
                share: s / (this.zoneTotal || 1),
                time: formatDurationShort(s),
            }))
        },
        zoneAria() {
            return this.zoneRows.map(z => `${z.label}: ${z.time}`).join(', ')
        },
        // Records (best of all time) set in the timeframe
        recordsInWindow() {
            const tf = this.timeframe
            const inTf = e => {
                const d = parseDate(e.startTime)
                return d >= tf.from && d < tf.to
            }
            const sportOf = id => navData.activities.find(a => a.id === id)?.sport
            const ofSport = e => !this.sport || sportOf(e.activityId) === this.sport
            const records = []
            // Records set in this timeframe (best of all time)
            if (this.records) {
                for (const [family, rows] of Object.entries(this.records.efforts ?? {})) {
                    if (this.sport && effortFamily(this.sport) !== family) continue
                    for (const row of rows) {
                        const best = row.top[0]
                        if (best && inTf(best) && ofSport(best)) {
                            records.push({
                                key: `effort-${family}-${row.meters}`,
                                icon: 'trophy',
                                label: t('fit_tracker', 'Best {distance}', { distance: formatEffortDistance(row.meters) }),
                                value: formatDurationClock(best.value),
                                activityId: best.activityId,
                                startTime: best.startTime,
                            })
                        }
                    }
                }
                for (const row of this.records.power ?? []) {
                    const best = row.top[0]
                    if (best && inTf(best) && ofSport(best)) {
                        records.push({
                            key: `power-${row.seconds}`,
                            icon: 'trophy',
                            label: t('fit_tracker', 'Best {duration} power', { duration: formatPowerDuration(row.seconds) }),
                            value: t('fit_tracker', '{watts} W', { watts: best.value }),
                            activityId: best.activityId,
                            startTime: best.startTime,
                        })
                    }
                }
            }
            return records
        },
        // Biggest efforts of the timeframe; the activity name is in the tooltip
        highlights() {
            const list = this.windowActivities
            if (!list.length) return []
            const top = value => list.reduce((best, a) => (value(a) || 0) > (value(best) || 0) ? a : best, list[0])
            const item = (key, icon, label, a, value) => ({ key, icon, label, value, activityId: a.id, title: `${a.name} · ${formatDay(a.startTime)}` })
            const out = []
            const far = top(a => a.distance)
            if (far.distance) out.push(item('far', 'straighten', t('fit_tracker', 'Longest distance'), far, formatDistance(far.distance, 1)))
            const long = top(a => a.duration)
            out.push(item('long', 'timer', t('fit_tracker', 'Longest time'), long, formatDurationShort(long.duration)))
            const high = top(a => a.elevationGain)
            if (high.elevationGain) out.push(item('high', 'landscape', t('fit_tracker', 'Biggest climb'), high, formatElevation(high.elevationGain, true)))
            return out
        },
        gearRows() {
            return this.gear
                .filter(g => !g.retiredDate && (!this.sport || g.defaultSport === this.sport || this.windowActivities.some(a => a.gearId === g.id)))
                .map(g => {
                    const windowDistance = this.windowActivities.filter(a => a.gearId === g.id).reduce((s, a) => s + (a.distance || 0), 0)
                    const wear = g.retireDistance ? g.totalDistance / g.retireDistance : 0
                    const left = (g.retireDistance ?? 0) - g.totalDistance
                    return {
                        ...g,
                        windowDistance,
                        wear,
                        wearClass: wear >= 1 ? 'wear--over' : wear >= 0.9 ? 'wear--soon' : '',
                        wearText: left <= 0
                            ? t('fit_tracker', 'Time to replace: {over} over the limit', { over: formatDistance(-left, 0) })
                            : t('fit_tracker', '{left} left of {limit}', { left: formatDistance(left, 0), limit: formatDistance(g.retireDistance, 0) }),
                    }
                })
                .filter(g => g.windowDistance > 0 || g.wear >= 0.9)
        },
        emptyTitle() {
            if (navData.folderPath === '') return t('fit_tracker', 'No folder selected yet')
            if (this.sport) return t('fit_tracker', 'No activities of this type')
            return t('fit_tracker', 'No activities yet')
        },
        emptyDescription() {
            if (navData.folderPath === '') return t('fit_tracker', 'Choose the folder where your watch exports its .fit files.')
            if (this.sport) return ''
            return t('fit_tracker', 'No activity files were found in {folder}. New files are imported on every sync.', { folder: navData.folderPath ?? '' })
        },
    },
    watch: {
        '$route.query': {
            handler(query) { rememberTimeframe(query, query.sport || null) },
            immediate: true,
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
        sportIcon,
        sportColor,
        sportLabel,
        openSettings,
        formatDay,
        formatDistance,
        formatDurationShort,
        inWindow(list, from, to) {
            return list.filter(a => {
                const d = parseDate(a.startTime)
                return d >= from && d < to
            })
        },
        refresh() {
            this.load(true)
        },
        // force: fetch (and sync) again instead of using what's already loaded
        async load(force) {
            this.loading = navData.activities.length === 0 || force
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
            // Extras fill in as they arrive; the page works without them
            fetchGoals(force).catch(() => {})
            fetchTraining(force).catch(() => {})
            axios.get(generateUrl('/apps/fit_tracker/api/gear')).then(({ data }) => { this.gear = data }).catch(() => {})
            axios.get(generateUrl('/apps/fit_tracker/api/stats/records')).then(({ data }) => { this.records = data }).catch(() => {})
        },
    },
}
</script>

<style scoped>
.dashboard {
    max-width: 1200px;
}
.dashboard__header {
    display: flex;
    flex-direction: column;
    gap: var(--ft-gap-3);
    margin-bottom: var(--ft-gap-5);
}
.dashboard__title {
    margin-bottom: 0;
}
.dashboard__sync-errors ul {
    margin: var(--ft-gap-1) 0 0 var(--ft-gap-4);
    padding: 0;
    list-style: disc;
}
.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(150px, 100%), 1fr));
    gap: var(--ft-gap-3);
    margin-bottom: var(--ft-gap-3);
}
.dashboard__goal-hint {
    margin-bottom: var(--ft-gap-5);
}
.dashboard__goal-hint a,
.dashboard__more {
    text-decoration: underline;
}
.dashboard__more {
    display: inline-block;
    margin-top: var(--ft-gap-3);
}
.dashboard__row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(300px, 100%), 1fr));
    gap: 0 var(--ft-gap-4);
}
.dashboard__row h4 {
    margin: var(--ft-gap-3) 0 var(--ft-gap-2);
    font-weight: bold;
}
.dashboard__sleep {
    margin: var(--ft-gap-4) 0 var(--ft-gap-6);
}
.sport-shares {
    display: flex;
    flex-direction: column;
    gap: var(--ft-gap-1);
}
.sport-share {
    display: grid;
    grid-template-columns: minmax(0, 150px) minmax(60px, 1fr) auto;
    align-items: center;
    gap: var(--ft-gap-3);
    padding: var(--ft-gap-1) var(--ft-gap-2);
    border-radius: var(--border-radius-element);
    color: var(--color-main-text);
}
.sport-share:hover,
.sport-share:focus-visible {
    background: var(--color-background-hover);
}
.sport-share__name {
    display: flex;
    align-items: center;
    gap: var(--ft-gap-1);
    min-width: 0;
}
.sport-share__bar {
    height: 10px;
    border-radius: var(--border-radius-pill);
    background: var(--color-background-dark);
    overflow: hidden;
}
.sport-share__bar span {
    display: block;
    height: 100%;
    border-radius: var(--border-radius-pill);
}
.sport-share__value {
    font-size: var(--font-size-small);
    color: var(--color-text-maxcontrast);
    white-space: nowrap;
    text-align: end;
}
.training-now {
    display: flex;
    gap: var(--ft-gap-6);
}
.training-now__value {
    font-size: 24px;
    font-weight: bold;
}
.zone-bar {
    display: flex;
    height: 12px;
    border-radius: var(--border-radius-pill);
    overflow: hidden;
    background: var(--color-background-dark);
}
.zone-legend {
    display: flex;
    flex-wrap: wrap;
    gap: var(--ft-gap-1) var(--ft-gap-4);
    margin-top: var(--ft-gap-2);
    font-size: var(--font-size-small);
}
.zone-legend__dot {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    margin-inline-end: var(--ft-gap-1);
}
.highlights,
.gear-rows {
    display: flex;
    flex-direction: column;
    gap: var(--ft-gap-1);
}
.highlight {
    display: flex;
    align-items: center;
    gap: var(--ft-gap-2);
    padding: var(--ft-gap-1) var(--ft-gap-2);
    border-radius: var(--border-radius-element);
    color: var(--color-main-text);
}
.highlight:hover,
.highlight:focus-visible {
    background: var(--color-background-hover);
}
.highlight__label {
    flex: 1;
    min-width: 0;
    color: var(--color-text-maxcontrast);
}
.highlight__value {
    font-weight: bold;
    white-space: nowrap;
}
.gear-row {
    padding: var(--ft-gap-2) 0;
}
.gear-row__top {
    display: flex;
    justify-content: space-between;
    gap: var(--ft-gap-2);
    margin-bottom: var(--ft-gap-1);
}
.gear-row__name {
    font-weight: bold;
}
.gear-row__window,
.gear-row__wear {
    font-size: var(--font-size-small);
    color: var(--color-text-maxcontrast);
}
.gear-row__wear {
    display: flex;
    align-items: center;
    gap: var(--ft-gap-1);
}
.gear-row__bar {
    height: 8px;
    border-radius: var(--border-radius-pill);
    background: var(--color-background-dark);
    overflow: hidden;
    margin-bottom: var(--ft-gap-1);
}
.gear-row__bar span {
    display: block;
    height: 100%;
    background: var(--color-primary-element);
}
.gear-row__bar span.wear--soon { background: var(--color-element-warning, var(--color-warning-text)); }
.gear-row__bar span.wear--over { background: var(--color-element-error, var(--color-error-text)); }
.gear-row__wear.wear--soon { color: var(--color-warning-text); }
.gear-row__wear.wear--over { color: var(--color-error-text); font-weight: bold; }
</style>
