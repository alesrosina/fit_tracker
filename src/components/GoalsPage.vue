<template>
    <div class="ft-page goals-page">
        <div class="goals-page__header">
            <h2 class="ft-page-title">{{ t('fit_tracker', 'Goals') }}</h2>
            <NcButton v-if="goals.list.length" variant="primary" @click="openEditor(null)">
                <template #icon>
                    <NcIconSvgWrapper :svg="icons.add" :size="20" />
                </template>
                {{ t('fit_tracker', 'Add goal') }}
            </NcButton>
        </div>

        <LoadingSkeleton v-if="loading" />
        <LoadError
            v-else-if="error"
            :heading="t('fit_tracker', 'Could not load your goals')"
            :message="t('fit_tracker', 'Check your connection and try again.')"
            @retry="load"
        />
        <NcEmptyContent
            v-else-if="goals.list.length === 0"
            :name="t('fit_tracker', 'No goals yet')"
            :description="t('fit_tracker', 'Set a weekly, monthly or yearly target, for example 100 km of cycling a month or 7 hours of sleep a night. The dashboard then shows your progress for the selected timeframe.')"
        >
            <template #icon>
                <NcIconSvgWrapper :svg="icons.checklist" />
            </template>
            <template #action>
                <NcButton variant="primary" @click="openEditor(null)">
                    {{ t('fit_tracker', 'Add goal') }}
                </NcButton>
            </template>
        </NcEmptyContent>

        <template v-else>
            <p class="ft-panel__hint goals-page__intro">
                {{ t('fit_tracker', 'The dashboard shows each goal scaled to the selected timeframe: a weekly goal of 50 km is about 214 km over the last 30 days. Below, every goal is checked per calendar week, month or year.') }}
            </p>
            <NcNoteCard v-if="needsTraining && training.analyzing" type="info">
                {{ n('fit_tracker', 'Analyzing heart rate data of %n older activity…', 'Analyzing heart rate data of %n older activities…', training.analyzing) }}
            </NcNoteCard>

            <section v-for="group in groups" :key="group.key" class="goals-page__group">
                <h3>{{ group.title }}</h3>
                <ul class="goal-list">
                    <li v-for="row in group.rows" :key="row.goal.id" class="goal-row">
                        <div class="goal-row__main">
                            <div class="goal-row__title">{{ row.title }}</div>
                            <div class="goal-row__target">{{ row.targetText }}</div>
                        </div>
                        <div>
                        <div class="goal-history__caption" aria-hidden="true">{{ historyCaption(row.goal) }}</div>
                        <ol class="goal-history" :aria-label="historyCaption(row.goal)">
                            <li
                                v-for="(p, i) in row.history.periods"
                                :key="i"
                                class="goal-history__item"
                                :class="{
                                    'goal-history__item--hit': p.hit,
                                    'goal-history__item--miss': !p.hit && !p.running && !p.empty,
                                    'goal-history__item--running': p.running && !p.hit,
                                }"
                                :title="p.description"
                                :aria-label="p.description"
                            />
                        </ol>
                        </div>
                        <div class="goal-row__streak">
                            {{ row.history.streak ? streakText(row.goal, row.history.streak) : t('fit_tracker', 'No streak yet') }}
                        </div>
                        <NcActions :aria-label="t('fit_tracker', 'Goal actions')">
                            <NcActionButton close-after-click @click="openEditor(row.goal)">
                                <template #icon>
                                    <NcIconSvgWrapper :svg="icons.edit" :size="20" />
                                </template>
                                {{ t('fit_tracker', 'Edit') }}
                            </NcActionButton>
                            <NcActionButton close-after-click @click="remove(row.goal)">
                                <template #icon>
                                    <NcIconSvgWrapper :svg="icons.delete" :size="20" />
                                </template>
                                {{ t('fit_tracker', 'Delete') }}
                            </NcActionButton>
                        </NcActions>
                    </li>
                </ul>
            </section>
        </template>

        <NcDialog
            v-if="editing"
            :open="true"
            :name="editing.id ? t('fit_tracker', 'Edit goal') : t('fit_tracker', 'Add goal')"
            :buttons="dialogButtons"
            size="normal"
            @update:open="editing = null"
        >
            <div class="goal-form">
                <NcRadioGroup v-model="editing.kind" :label="t('fit_tracker', 'Goal for')" @update:model-value="kindChanged">
                    <NcRadioGroupButton value="activity" :label="t('fit_tracker', 'Activities')" />
                    <NcRadioGroupButton value="sleep" :label="t('fit_tracker', 'Sleep')" />
                </NcRadioGroup>
                <NcRadioGroup v-model="editing.period" :label="periodQuestion">
                    <NcRadioGroupButton v-for="p in PERIODS" :key="p" :value="p" :label="periodLabel(p)" />
                </NcRadioGroup>
                <NcSelect
                    v-model="editing.metricOption"
                    :options="metricOptions"
                    :input-label="t('fit_tracker', 'Measure')"
                    :clearable="false"
                    label="label"
                />
                <NcSelect
                    v-if="editing.kind === 'activity'"
                    v-model="editing.sportOption"
                    :options="sportOptions"
                    :input-label="t('fit_tracker', 'Sport')"
                    :clearable="false"
                    label="label"
                />
                <NcTextField
                    v-if="editing.metricOption?.value === 'sleep_nights'"
                    v-model="editing.threshold"
                    type="number"
                    :label="t('fit_tracker', 'Count nights with at least (hours)')"
                />
                <NcTextField
                    v-model="editing.target"
                    type="number"
                    :label="t('fit_tracker', 'Target ({unit})', { unit: goalTargetUnit(editing.metricOption?.value) })"
                    :helper-text="targetHint"
                />
                <NcNoteCard v-if="formError" type="error" :text="formError" />
            </div>
        </NcDialog>
    </div>
</template>

<script>
import { t, n } from '@nextcloud/l10n'
import { showError } from '@nextcloud/dialogs'
import {
    NcActionButton, NcActions, NcButton, NcDialog, NcEmptyContent, NcIconSvgWrapper, NcNoteCard,
    NcRadioGroup, NcRadioGroupButton, NcSelect, NcTextField,
} from '@nextcloud/vue'
import LoadingSkeleton from './LoadingSkeleton.vue'
import LoadError from './LoadError.vue'
import { icons } from '../icons.js'
import { sportLabel } from '../sports.js'
import { navData, fetchActivities } from '../store/navData.js'
import { goals, fetchGoals, saveGoal, deleteGoal } from '../store/goals.js'
import { training, fetchTraining } from '../store/training.js'
import {
    PERIODS, ACTIVITY_GOAL_METRICS, SLEEP_GOAL_METRICS, AVERAGE_METRICS, metricLabel, periodLabel,
    measureContext, goalHistory, formatMetric, formatGoalTarget, goalTargetUnit, goalTargetToDisplay, goalTargetFromDisplay,
} from '../stats.js'

// Create, edit and delete goals, and see for each whether it was reached in
// the last calendar weeks, months or years.
export default {
    name: 'GoalsPage',
    components: {
        LoadingSkeleton, LoadError, NcActionButton, NcActions, NcButton, NcDialog, NcEmptyContent, NcIconSvgWrapper,
        NcNoteCard, NcRadioGroup, NcRadioGroupButton, NcSelect, NcTextField,
    },
    data() {
        return { icons, goals, training, PERIODS, loading: !goals.loaded, error: false, editing: null, formError: '' }
    },
    computed: {
        ctx() {
            return measureContext(navData.activities, navData.sleep, training.activities)
        },
        needsTraining() {
            return goals.list.some(g => g.metric === 'load')
        },
        groups() {
            const groups = new Map()
            const add = (key, title, goal) => {
                if (!groups.has(key)) groups.set(key, { key, title, rows: [] })
                groups.get(key).rows.push(this.row(goal))
            }
            const order = { week: 0, month: 1, year: 2 }
            const sorted = [...goals.list].sort((a, b) => order[a.period] - order[b.period])
            for (const g of sorted.filter(g => !SLEEP_GOAL_METRICS.includes(g.metric) && !g.sport)) add('all', t('fit_tracker', 'All sports'), g)
            for (const g of sorted.filter(g => !SLEEP_GOAL_METRICS.includes(g.metric) && g.sport).sort((a, b) => a.sport.localeCompare(b.sport))) {
                add('sport-' + g.sport, sportLabel(g.sport), g)
            }
            for (const g of sorted.filter(g => SLEEP_GOAL_METRICS.includes(g.metric))) add('sleep', t('fit_tracker', 'Sleep'), g)
            return [...groups.values()]
        },
        metricOptions() {
            const list = this.editing?.kind === 'sleep' ? SLEEP_GOAL_METRICS : ACTIVITY_GOAL_METRICS
            return list.map(m => ({ value: m, label: metricLabel(m) }))
        },
        sportOptions() {
            const sports = [...new Set(navData.activities.map(a => a.sport))].sort()
            return [{ value: '', label: t('fit_tracker', 'All sports') }, ...sports.map(s => ({ value: s, label: sportLabel(s) }))]
        },
        periodQuestion() {
            return AVERAGE_METRICS.includes(this.editing?.metricOption?.value)
                ? t('fit_tracker', 'Averaged over a')
                : t('fit_tracker', 'Per')
        },
        targetHint() {
            const m = this.editing?.metricOption?.value
            if (m === 'load') return t('fit_tracker', 'Training load from heart rate; an easy hour is about 50–70, a hard one 120 or more.')
            if (m === 'active_days') return t('fit_tracker', 'Days with at least one activity.')
            if (m === 'sleep_score') return t('fit_tracker', 'Average sleep score, 0 to 100.')
            return ''
        },
        dialogButtons() {
            return [
                { label: t('fit_tracker', 'Cancel'), variant: 'tertiary', callback: () => {} },
                // Returning false keeps the dialog open to show the error
                { label: t('fit_tracker', 'Save'), variant: 'primary', callback: async () => (await this.save()) || false },
            ]
        },
    },
    mounted() {
        this.load()
    },
    methods: {
        periodLabel,
        goalTargetUnit,
        async load() {
            this.loading = !goals.loaded
            this.error = false
            try {
                await Promise.all([fetchGoals(true), fetchActivities()])
            } catch (e) {
                this.error = true
            } finally {
                this.loading = false
            }
            fetchTraining().catch(() => {})
        },
        row(goal) {
            const per = { week: t('fit_tracker', 'per week'), month: t('fit_tracker', 'per month'), year: t('fit_tracker', 'per year') }[goal.period]
            const history = goalHistory(this.ctx, goal)
            history.periods = history.periods.map(p => ({ ...p, description: this.periodDescription(goal, p, history.target) }))
            return {
                goal,
                history,
                title: metricLabel(goal.metric),
                targetText: AVERAGE_METRICS.includes(goal.metric)
                    ? t('fit_tracker', '{target}, averaged {per}', { target: formatGoalTarget(goal), per })
                    : `${formatGoalTarget(goal)} ${per}`,
            }
        },
        periodName(goal, from) {
            if (goal.period === 'week') return t('fit_tracker', 'Week of {date}', { date: from.toLocaleDateString(undefined, { day: 'numeric', month: 'short' }) })
            if (goal.period === 'month') return from.toLocaleDateString(undefined, { month: 'long', year: 'numeric' })
            return String(from.getFullYear())
        },
        periodDescription(goal, p, target) {
            const name = this.periodName(goal, p.from)
            if (goal.metric === 'load' && !training.activities) return name
            if (p.empty) return t('fit_tracker', '{period}: no data', { period: name })
            const done = formatMetric(p.done, goal.metric)
            const of = formatMetric(target, goal.metric)
            if (p.running) return t('fit_tracker', '{period} (so far): {done} of {target}', { period: name, done, target: of })
            return p.hit
                ? t('fit_tracker', '{period}: reached, {done} of {target}', { period: name, done, target: of })
                : t('fit_tracker', '{period}: missed, {done} of {target}', { period: name, done, target: of })
        },
        historyCaption(goal) {
            if (goal.period === 'week') return t('fit_tracker', 'Last 8 weeks and this one')
            if (goal.period === 'month') return t('fit_tracker', 'Last 8 months and this one')
            return t('fit_tracker', 'Last 8 years and this one')
        },
        streakText(goal, streak) {
            if (goal.period === 'week') return n('fit_tracker', 'Reached %n week in a row', 'Reached %n weeks in a row', streak)
            if (goal.period === 'month') return n('fit_tracker', 'Reached %n month in a row', 'Reached %n months in a row', streak)
            return n('fit_tracker', 'Reached %n year in a row', 'Reached %n years in a row', streak)
        },
        openEditor(goal) {
            this.formError = ''
            const kind = goal && SLEEP_GOAL_METRICS.includes(goal.metric) ? 'sleep' : 'activity'
            this.editing = { id: goal?.id ?? null, kind, period: goal?.period ?? 'week', metricOption: null, sportOption: null, target: '', threshold: goal?.threshold ?? 7 }
            this.editing.metricOption = this.metricOptions.find(o => o.value === (goal?.metric ?? 'distance')) ?? this.metricOptions[0]
            this.editing.sportOption = this.sportOptions.find(o => o.value === (goal?.sport ?? '')) ?? this.sportOptions[0]
            this.editing.target = goal ? goalTargetToDisplay(goal.target, goal.metric) : ''
        },
        kindChanged() {
            this.editing.metricOption = this.metricOptions[0]
            this.editing.target = ''
        },
        async save() {
            const e = this.editing
            const metric = e.metricOption?.value
            const target = Number(e.target)
            if (!metric || !(target > 0)) {
                this.formError = t('fit_tracker', 'Enter a target above zero.')
                return false
            }
            if (metric === 'sleep_nights' && !(Number(e.threshold) > 0 && Number(e.threshold) <= 24)) {
                this.formError = t('fit_tracker', 'Enter the hours a night needs, between 0 and 24.')
                return false
            }
            try {
                await saveGoal({
                    id: e.id,
                    metric,
                    period: e.period,
                    sport: e.kind === 'activity' ? e.sportOption?.value : '',
                    target: goalTargetFromDisplay(target, metric),
                    threshold: metric === 'sleep_nights' ? Number(e.threshold) : null,
                })
                return true
            } catch (err) {
                this.formError = err.response?.status === 400
                    ? t('fit_tracker', 'Check the entered values: {error}', { error: err.response.data?.error ?? '' })
                    : t('fit_tracker', 'Could not save the goal. Try again later.')
                return false
            }
        },
        async remove(goal) {
            try {
                await deleteGoal(goal.id)
            } catch (e) {
                showError(t('fit_tracker', 'Could not delete the goal. Try again later.'))
            }
        },
    },
}
</script>

<style scoped>
.goals-page__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--ft-gap-3);
}
.goals-page__intro {
    margin-bottom: var(--ft-gap-5);
}
.goals-page__group {
    margin-bottom: var(--ft-gap-6);
}
.goal-list {
    display: flex;
    flex-direction: column;
    gap: var(--ft-gap-2);
}
.goal-row {
    display: grid;
    grid-template-columns: minmax(180px, 1.2fr) auto minmax(150px, 1fr) auto;
    align-items: center;
    gap: var(--ft-gap-2) var(--ft-gap-5);
    padding: var(--ft-gap-3) var(--ft-gap-4);
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-container);
}
.goal-row__title {
    font-weight: bold;
}
.goal-row__target,
.goal-row__streak {
    font-size: var(--font-size-small);
    color: var(--color-text-maxcontrast);
}
.goal-history {
    display: flex;
    gap: var(--ft-gap-1);
    list-style: none;
    margin: 0;
    padding: 0;
}
.goal-history__caption {
    font-size: var(--font-size-small);
    color: var(--color-text-maxcontrast);
    margin-bottom: var(--ft-gap-1);
}
.goal-history__item {
    width: 16px;
    height: 16px;
    border-radius: var(--border-radius-small);
    background: var(--color-background-dark);
}
.goal-history__item--hit {
    background: var(--color-element-success, var(--color-success-text));
}
.goal-history__item--miss {
    background: var(--color-element-error, var(--color-error-text));
}
.goal-history__item--running {
    background: transparent;
    border: 2px solid var(--color-primary-element);
    box-sizing: border-box;
}
.goal-form {
    display: flex;
    flex-direction: column;
    gap: var(--ft-gap-3);
}
@media (max-width: 700px) {
    .goal-row {
        grid-template-columns: 1fr auto;
        grid-template-areas: "main menu" "history history" "streak streak";
    }
    .goal-row__main { grid-area: main; }
    .goal-row > :nth-child(2) { grid-area: history; }
    .goal-row__streak { grid-area: streak; }
    .goal-row > :last-child { grid-area: menu; }
}
</style>
