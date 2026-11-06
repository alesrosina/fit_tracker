<template>
    <section class="dashboard-sleep">
        <div v-if="showHeader" class="dashboard-sleep__header">
            <h3>{{ t('fit_tracker', 'Sleep') }}</h3>
            <router-link to="/sleep" class="dashboard-sleep__more">{{ t('fit_tracker', 'All sleep sessions') }}</router-link>
        </div>

        <p v-if="nights.length === 0" class="ft-panel__hint">
            {{ t('fit_tracker', 'No sleep recorded in this timeframe.') }}
        </p>
        <template v-else>
            <div class="dashboard-grid">
                <StatTile
                    :label="t('fit_tracker', 'Sleep per night')"
                    :value="formatDurationShort(summary.duration)"
                    :sub="scoreText"
                    :change="compare('sleep_hours')"
                    :goal="goalFor('sleep_hours') ?? goalFor('sleep_score')"
                >
                    <template v-if="summary.stages" #value>
                        <SleepStageBar class="dashboard-sleep__stagebar" v-bind="averageStageProps" />
                    </template>
                </StatTile>
                <StatTile
                    v-if="best"
                    :to="'/sleep/' + best.session.id"
                    :label="t('fit_tracker', 'Best night')"
                    :value="formatDurationShort(best.session.duration)"
                    :sub="nightText(best)"
                >
                    <template v-if="hasStages(best)" #value>
                        <SleepStageBar class="dashboard-sleep__stagebar" v-bind="nightStageProps(best)" />
                    </template>
                </StatTile>
                <StatTile
                    v-if="worst && worst !== best"
                    :to="'/sleep/' + worst.session.id"
                    :label="t('fit_tracker', 'Worst night')"
                    :value="formatDurationShort(worst.session.duration)"
                    :sub="nightText(worst)"
                >
                    <template v-if="hasStages(worst)" #value>
                        <SleepStageBar class="dashboard-sleep__stagebar" v-bind="nightStageProps(worst)" />
                    </template>
                </StatTile>
                <StatTile
                    :label="t('fit_tracker', 'Sleep debt')"
                    :value="formatDurationShort(debt.total)"
                    :sub="n('fit_tracker', '%n night under {target}', '%n nights under {target}', debt.nights, { target: formatDurationShort(debt.target) })"
                    :change="debtChange"
                />
                <StatTile
                    v-if="summary.bed !== null"
                    :label="t('fit_tracker', 'Usual bedtime')"
                    :value="clock(summary.bed)"
                    :sub="t('fit_tracker', 'Wake-up {time} · ± {minutes} min', { time: clock(summary.wake), minutes: Math.round((summary.bedSpread + summary.wakeSpread) / 2) })"
                />
                <StatTile
                    v-if="nightsGoal"
                    :label="nightsGoalLabel"
                    :value="String(nightsGoal.done)"
                    :goal="nightsGoal.tile"
                />
                <!-- Twice as wide as the other boxes -->
                <StatTile
                    v-if="byLoad"
                    class="dashboard-sleep__wide"
                    :label="t('fit_tracker', 'Sleep after training')"
                    :sub="t('fit_tracker', 'Average night and sleep score')"
                >
                    <ul class="load-rows">
                        <li v-for="row in byLoad" :key="row.key" :title="n('fit_tracker', '%n night', '%n nights', row.count)">
                            <span class="load-rows__label">{{ row.label }}</span>
                            <span class="load-rows__value">{{ row.duration !== null ? formatDurationShort(row.duration) : '–' }}</span>
                            <span class="load-rows__score">{{ row.score !== null ? Math.round(row.score) : '' }}</span>
                        </li>
                    </ul>
                </StatTile>
            </div>

            <ul v-if="summary.stages" class="stage-legend" :aria-label="t('fit_tracker', 'Sleep stage colours')">
                <li v-for="s in stageRows" :key="s.key">
                    <span class="stage-legend__dot" :style="{ background: s.color }" />
                    {{ s.label }}
                </li>
            </ul>

        </template>
    </section>
</template>

<script>
import { t, n } from '@nextcloud/l10n'
import StatTile from './StatTile.vue'
import SleepStageBar from './SleepStageBar.vue'
import { STAGE_COLORS, stageLabel } from '../sleepStages.js'
import { formatDay, formatDurationShort } from '../format.js'
import { compareChange } from '../timeframe.js'
import { measure, scaledGoal, formatMetric } from '../stats.js'
import { nightsBetween, summarize, clock, nightsByLoad, avg, DEFAULT_SLEEP_HOURS } from '../sleepStats.js'

const STAGES = ['deep', 'rem', 'light', 'awake']

// Sleep part of the dashboard for the selected timeframe: sleep per night
// with score, stages, best and worst night, debt, regularity, and sleep after
// training, with goals.
export default {
    name: 'DashboardSleep',
    components: { StatTile, SleepStageBar },
    props: {
        // stats.js measureContext()
        ctx: { type: Object, required: true },
        goals: { type: Array, required: true },
        timeframe: { type: Object, required: true },
        // Local day → TRIMP, null while training data loads
        loadByDay: { type: Map, default: null },
        // The sleep page has its own title and doesn't link to itself
        showHeader: { type: Boolean, default: true },
    },
    computed: {
        nights() {
            return nightsBetween(this.ctx.nights, this.timeframe.from, this.timeframe.to)
        },
        previousNights() {
            const p = this.timeframe.previous
            return p ? nightsBetween(this.ctx.nights, p.from, p.to) : []
        },
        summary() {
            return summarize(this.nights)
        },
        scoreText() {
            return this.summary.score !== null ? t('fit_tracker', 'Score {score}', { score: Math.round(this.summary.score) }) : ''
        },
        // Per-night target: the sleep goal, or 8 hours
        targetSeconds() {
            const goal = this.goals.find(g => g.metric === 'sleep_hours')
            return (goal ? goal.target : DEFAULT_SLEEP_HOURS) * 3600
        },
        debt() {
            return this.debtOf(this.nights)
        },
        debtChange() {
            if (!this.timeframe.previous || !this.previousNights.length) return null
            return compareChange(this.debt.total, this.debtOf(this.previousNights).total, this.timeframe, false)
        },
        nightsGoal() {
            const g = scaledGoal(this.ctx, this.goals, 'sleep_nights', null, this.timeframe.from, this.timeframe.to)
            if (!g) return null
            return { done: g.done, threshold: g.goal.threshold, tile: { fraction: g.fraction, targetText: String(Math.round(g.target)) } }
        },
        nightsGoalLabel() {
            return t('fit_tracker', 'Nights with at least {hours} h', { hours: this.nightsGoal?.threshold })
        },
        stageRows() {
            const s = this.summary.stages ?? {}
            return STAGES.map(key => ({ key, label: stageLabel(key), color: STAGE_COLORS[key], percent: Math.round((s[key] || 0) * 100) }))
        },
        // Average time per stage over the nights with stage data, so the
        // bar's tooltip shows real minutes
        averageStageProps() {
            const staged = this.nights.filter(x => this.hasStages(x))
            const mean = field => avg(staged.map(x => x.session[field] || 0)) ?? 0
            return { timeDeep: mean('timeDeep'), timeRem: mean('timeRem'), timeLight: mean('timeLight'), timeAwake: mean('timeAwake') }
        },
        // Ranked by score, or by duration when scores are missing
        ranked() {
            const value = x => x.session.score || x.session.duration / 3600
            return [...this.nights].sort((a, b) => value(b) - value(a))
        },
        best() { return this.nights.length > 1 ? this.ranked[0] : null },
        worst() { return this.nights.length > 1 ? this.ranked[this.ranked.length - 1] : null },
        byLoad() {
            if (!this.loadByDay || this.nights.length < 2) return null
            const groups = nightsByLoad(this.nights, this.loadByDay)
            const row = (key, label, list) => ({
                key,
                label,
                count: list.length,
                duration: avg(list.map(x => x.session.duration)),
                score: avg(list.map(x => x.session.score || null)),
            })
            const rows = [
                row('rest', t('fit_tracker', 'After a rest day'), groups.rest),
                row('easy', t('fit_tracker', 'After an easier day'), groups.easy),
                row('hard', t('fit_tracker', 'After a harder day'), groups.hard),
            ]
            return rows[0].count && (rows[1].count || rows[2].count) ? rows : null
        },
    },
    methods: {
        clock,
        formatDurationShort,
        nightStageProps(night) {
            const s = night.session
            return { timeDeep: s.timeDeep || 0, timeRem: s.timeRem || 0, timeLight: s.timeLight || 0, timeAwake: s.timeAwake || 0 }
        },
        hasStages(night) {
            const s = night.session
            return (s.timeDeep || 0) + (s.timeRem || 0) + (s.timeLight || 0) + (s.timeAwake || 0) > 0
        },
        debtOf(nights) {
            const short = nights.filter(x => x.session.duration < this.targetSeconds)
            return {
                target: this.targetSeconds,
                nights: short.length,
                total: short.reduce((s, x) => s + this.targetSeconds - x.session.duration, 0),
            }
        },
        compare(metric) {
            const p = this.timeframe.previous
            if (!p) return null
            return compareChange(
                measure(this.ctx, metric, this.timeframe.from, this.timeframe.to),
                measure(this.ctx, metric, p.from, p.to),
                this.timeframe,
            )
        },
        goalFor(metric) {
            const g = scaledGoal(this.ctx, this.goals, metric, null, this.timeframe.from, this.timeframe.to)
            return g ? { fraction: g.fraction, targetText: formatMetric(g.target, metric) } : null
        },
        nightText(night) {
            const parts = [formatDay(night.evening)]
            if (night.session.score) parts.push(t('fit_tracker', 'score {score}', { score: night.session.score }))
            return parts.join(' · ')
        },
    },
}
</script>

<style scoped>
.dashboard-sleep__header {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: var(--ft-gap-3);
    margin-bottom: var(--ft-gap-3);
}
.dashboard-sleep__header h3 {
    margin: 0;
}
.dashboard-sleep__more {
    text-decoration: underline;
}
/* Flex instead of a grid: boxes that wrap onto a last row stretch to fill
   it, so the number of boxes never leaves an empty slot */
.dashboard-grid {
    display: flex;
    flex-wrap: wrap;
    gap: var(--ft-gap-3);
    margin-bottom: var(--ft-gap-2);
}
.dashboard-grid > * {
    flex: 1 1 130px;
}
/* Two boxes plus the gap between them */
.dashboard-grid > .dashboard-sleep__wide {
    flex: 2 1 calc(260px + var(--ft-gap-3));
}
.dashboard-sleep__stagebar {
    margin: var(--ft-gap-1) 0;
}
.stage-legend {
    display: flex;
    flex-wrap: wrap;
    gap: var(--ft-gap-1) var(--ft-gap-4);
    margin-bottom: var(--ft-gap-4);
    font-size: var(--font-size-small);
    color: var(--color-text-maxcontrast);
}
.stage-legend li {
    display: flex;
    align-items: center;
    gap: var(--ft-gap-1);
}
.stage-legend__dot {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 50%;
}
.load-rows {
    display: grid;
    grid-template-columns: auto auto auto;
    justify-content: center;
    gap: 2px var(--ft-gap-2);
    margin-top: var(--ft-gap-1);
    font-size: var(--font-size-small);
}
/* Each row's cells sit directly in the three-column grid */
.load-rows li {
    display: contents;
}
.load-rows__label {
    color: var(--color-text-maxcontrast);
    text-align: start;
}
.load-rows__value {
    font-weight: bold;
    white-space: nowrap;
    text-align: end;
}
.load-rows__score {
    color: var(--color-text-maxcontrast);
    text-align: end;
}
</style>
