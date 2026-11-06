// Aggregation helpers shared by the trends, goals and sleep pages. Dates are
// bucketed by the browser's local calendar; weeks start on the locale's first
// day of the week.
import { t, n, getFirstDay } from '@nextcloud/l10n'
import { parseDate, localDayKey, formatDistance, formatDurationShort, formatElevation, distanceValue, elevationValue, distanceUnit, elevationUnit } from './format.js'
import { toNights, nightsBetween } from './sleepStats.js'

// Measures that add up per activity (the Trends page offers these)
export const METRICS = ['distance', 'duration', 'elevation', 'count']
// Everything a goal can measure (lib/Db/Goal.php)
export const ACTIVITY_GOAL_METRICS = ['distance', 'duration', 'elevation', 'count', 'active_days', 'calories', 'load']
export const SLEEP_GOAL_METRICS = ['sleep_hours', 'sleep_score', 'sleep_nights']
// Averages are compared as they are, not scaled to the length of a timeframe
export const AVERAGE_METRICS = ['sleep_hours', 'sleep_score']
export const PERIODS = ['week', 'month', 'year']
export const PERIOD_DAYS = { week: 7, month: 30.44, year: 365.25 }

export function metricLabel(metric) {
    return {
        distance: t('fit_tracker', 'Distance'),
        duration: t('fit_tracker', 'Time'),
        elevation: t('fit_tracker', 'Elevation gain'),
        count: t('fit_tracker', 'Activities'),
        active_days: t('fit_tracker', 'Active days'),
        calories: t('fit_tracker', 'Calories'),
        load: t('fit_tracker', 'Training load'),
        sleep_hours: t('fit_tracker', 'Sleep per night'),
        sleep_score: t('fit_tracker', 'Sleep score'),
        sleep_nights: t('fit_tracker', 'Nights with enough sleep'),
    }[metric]
}

export function periodLabel(period) {
    return {
        week: t('fit_tracker', 'Week'),
        month: t('fit_tracker', 'Month'),
        year: t('fit_tracker', 'Year'),
    }[period]
}

// Stored value of an activity for a metric: km, seconds, meters or 1
export function metricValue(activity, metric) {
    switch (metric) {
    case 'distance': return activity.distance || 0
    case 'duration': return activity.duration || 0
    case 'elevation': return activity.elevationGain || 0
    case 'calories': return activity.calories || 0
    default: return 1
    }
}

// Stored value → number in the user's display unit (km/mi, hours, m/ft, count)
export function displayValue(value, metric) {
    switch (metric) {
    case 'distance': return distanceValue(value)
    case 'duration': return value / 3600
    case 'elevation': return elevationValue(value)
    default: return value
    }
}

export function displayUnit(metric) {
    switch (metric) {
    case 'distance': return distanceUnit()
    case 'duration': return t('fit_tracker', 'h')
    case 'elevation': return elevationUnit()
    case 'calories': return t('fit_tracker', 'kcal')
    case 'sleep_hours': return t('fit_tracker', 'h')
    default: return ''
    }
}

/**
 * A measured value as text. Values are in stored units: km, seconds, m,
 * activities, days, kcal, TRIMP, seconds per night, score, nights.
 */
export function formatMetric(value, metric) {
    const whole = Math.round(value)
    switch (metric) {
    case 'distance': return formatDistance(value, 1)
    case 'duration': return formatDurationShort(value)
    case 'elevation': return formatElevation(value)
    case 'active_days': return n('fit_tracker', '%n day', '%n days', whole)
    case 'calories': return t('fit_tracker', '{kcal} kcal', { kcal: whole.toLocaleString() })
    case 'load': return String(whole)
    case 'sleep_hours': return formatDurationShort(value)
    case 'sleep_score': return String(whole)
    case 'sleep_nights': return n('fit_tracker', '%n night', '%n nights', whole)
    default: return n('fit_tracker', '%n activity', '%n activities', whole)
    }
}

// Local midnight at the start of the week/month/year containing d
export function periodStart(d, period) {
    const date = new Date(d.getFullYear(), d.getMonth(), d.getDate())
    if (period === 'week') {
        const offset = (date.getDay() - getFirstDay() + 7) % 7
        date.setDate(date.getDate() - offset)
    } else if (period === 'month') {
        date.setDate(1)
    } else {
        date.setMonth(0, 1)
    }
    return date
}

export function addPeriods(d, period, count) {
    const date = new Date(d)
    if (period === 'week') date.setDate(date.getDate() + 7 * count)
    else if (period === 'month') date.setMonth(date.getMonth() + count)
    else date.setFullYear(date.getFullYear() + count)
    return date
}

export function formatPeriod(d, period) {
    if (period === 'week') return d.toLocaleDateString(undefined, { day: 'numeric', month: 'short' })
    if (period === 'month') return d.toLocaleDateString(undefined, { month: 'short', year: '2-digit' })
    return String(d.getFullYear())
}

/**
 * Totals per period, oldest first: the `count` periods up to and including
 * the one containing `end`.
 */
export function totalsByPeriod(activities, period, metric, count, end = new Date()) {
    const last = periodStart(end, period)
    const starts = Array.from({ length: count }, (_, i) => addPeriods(last, period, i - count + 1))
    const index = new Map(starts.map((s, i) => [s.getTime(), i]))
    const totals = new Array(count).fill(0)
    for (const a of activities) {
        const i = index.get(periodStart(parseDate(a.startTime), period).getTime())
        if (i !== undefined) totals[i] += metricValue(a, metric)
    }
    return { starts, totals }
}

// Sum of a metric over the activities in [from, to)
export function sumBetween(activities, metric, from, to) {
    let sum = 0
    for (const a of activities) {
        const d = parseDate(a.startTime)
        if (d >= from && d < to) sum += metricValue(a, metric)
    }
    return sum
}

/**
 * The data a measurement needs. Build it once with measureContext() and pass
 * it to measure(), scaledGoal() and goalHistory().
 *
 * @param {object[]} activities activity list (navData.activities)
 * @param {object[]} sleep sleep sessions (navData.sleep)
 * @param {object[]|null} training /api/stats/load activities ({ id, trimp }), null while loading
 */
export function measureContext(activities, sleep, training) {
    return {
        activities,
        nights: toNights(sleep ?? []),
        trimpById: new Map((training ?? []).map(a => [a.id, a.trimp])),
        trainingLoaded: training !== null,
    }
}

/**
 * Value of a metric for [from, to), in stored units (see formatMetric).
 * Averages are null when there is nothing to average.
 */
export function measure(ctx, metric, from, to, sport = null) {
    if (SLEEP_GOAL_METRICS.includes(metric)) return measureSleep(ctx, metric, from, to)
    const list = ctx.activities.filter(a => {
        if (sport && a.sport !== sport) return false
        const d = parseDate(a.startTime)
        return d >= from && d < to
    })
    if (metric === 'active_days') return new Set(list.map(a => localDayKey(a.startTime))).size
    if (metric === 'load') return list.reduce((sum, a) => sum + (ctx.trimpById.get(a.id) ?? 0), 0)
    return list.reduce((sum, a) => sum + metricValue(a, metric), 0)
}

function measureSleep(ctx, metric, from, to, threshold = null) {
    const nights = nightsBetween(ctx.nights, from, to)
    if (metric === 'sleep_nights') return nights.filter(n => n.session.duration >= (threshold ?? 0) * 3600).length
    const values = nights.map(n => metric === 'sleep_hours' ? n.session.duration : (n.session.score || null)).filter(v => v !== null)
    return values.length ? values.reduce((a, b) => a + b, 0) / values.length : null
}

function goalValue(ctx, goal, from, to) {
    if (goal.metric === 'sleep_nights') return measureSleep(ctx, 'sleep_nights', from, to, goal.threshold)
    return measure(ctx, goal.metric, from, to, goal.sport)
}

// Goal targets are saved in fixed units (Goal::METRICS); this gives the
// target in the units measure() returns.
function goalTargetToStored(target, metric) {
    return metric === 'duration' || metric === 'sleep_hours' ? target * 3600 : target
}

/**
 * The goal for a metric on a dashboard timeframe, scaled to its length:
 * target × days ÷ days in the goal's period. Averages aren't scaled. When
 * several goals measure the same thing, the one whose period is closest to
 * the timeframe's length is used.
 *
 * @return {{ goal, done, target, fraction }|null} null when no goal is set
 */
export function scaledGoal(ctx, goals, metric, sport, from, to) {
    const candidates = goals.filter(g => g.metric === metric && (SLEEP_GOAL_METRICS.includes(metric) || (g.sport ?? null) === (sport ?? null)))
    if (!candidates.length) return null
    const days = Math.max(1, Math.round((to - from) / 86400000))
    const closeness = g => Math.abs(Math.log(days / PERIOD_DAYS[g.period]))
    const goal = [...candidates].sort((a, b) => closeness(a) - closeness(b))[0]
    const base = goalTargetToStored(goal.target, goal.metric)
    let target = AVERAGE_METRICS.includes(metric) ? base : base * days / PERIOD_DAYS[goal.period]
    if (metric === 'active_days' || metric === 'sleep_nights') target = Math.min(days, target)
    const done = goalValue(ctx, goal, from, to)
    return { goal, done, target, fraction: done !== null && target > 0 ? done / target : 0 }
}

/**
 * Hit or miss per calendar period for the goals page: the current period
 * (still running) and the `count` periods before it, oldest first, plus the
 * number of finished periods in a row that were hit.
 */
export function goalHistory(ctx, goal, count = 8, now = new Date()) {
    const current = periodStart(now, goal.period)
    const target = goalTargetToStored(goal.target, goal.metric)
    const periods = []
    for (let i = count; i >= 0; i--) {
        const from = addPeriods(current, goal.period, -i)
        const to = addPeriods(from, goal.period, 1)
        const done = goalValue(ctx, goal, from, to)
        periods.push({
            from,
            to,
            done,
            hit: done !== null && done >= target,
            running: i === 0,
            empty: done === null,
        })
    }
    let streak = 0
    for (const p of [...periods].reverse()) {
        if (p.running) continue
        if (!p.hit) break
        streak++
    }
    // The running period counts once it's reached
    if (periods[periods.length - 1].hit) streak++
    return { periods, target, streak }
}

// What the goal editor shows: the target in the user's display unit
export function goalTargetToDisplay(target, metric) {
    if (metric === 'distance' || metric === 'elevation') return Math.round(displayValue(target, metric) * 100) / 100
    return target
}

export function goalTargetFromDisplay(value, metric) {
    switch (metric) {
    case 'distance': return value / distanceValue(1)
    case 'elevation': return value / elevationValue(1)
    default: return value
    }
}

// Unit shown next to a goal target in the editor
export function goalTargetUnit(metric) {
    switch (metric) {
    case 'distance': return distanceUnit()
    case 'elevation': return elevationUnit()
    case 'duration': return t('fit_tracker', 'hours')
    case 'calories': return t('fit_tracker', 'kcal')
    case 'load': return t('fit_tracker', 'TRIMP')
    case 'sleep_hours': return t('fit_tracker', 'hours per night')
    case 'sleep_score': return t('fit_tracker', 'points')
    case 'active_days': return t('fit_tracker', 'days')
    case 'sleep_nights': return t('fit_tracker', 'nights')
    default: return t('fit_tracker', 'activities')
    }
}

// A goal target as text, e.g. "50 km", "4 days", "7h 30m per night"
export function formatGoalTarget(goal) {
    const stored = goalTargetToStored(goal.target, goal.metric)
    if (goal.metric === 'sleep_hours') return t('fit_tracker', '{duration} per night', { duration: formatDurationShort(stored) })
    if (goal.metric === 'sleep_score') return t('fit_tracker', 'average score {score}', { score: Math.round(stored) })
    if (goal.metric === 'sleep_nights') {
        return n('fit_tracker', '%n night with at least {hours} h', '%n nights with at least {hours} h', Math.round(stored), { hours: goal.threshold })
    }
    if (goal.metric === 'load') return t('fit_tracker', '{load} TRIMP', { load: Math.round(stored) })
    return formatMetric(stored, goal.metric)
}

/**
 * Fitness / fatigue model over daily training load (TRIMP): chronic load
 * (CTL, 42-day exponentially weighted average, "fitness"), acute load (ATL,
 * 7 days, "fatigue") and their difference (TSB, "form").
 *
 * @param {Map<string, number>} loadByDay 'YYYY-MM-DD' → load
 * @param {Date} from first day to report
 * @param {Date} to last day to report
 */
export function fitnessFatigue(loadByDay, from, to) {
    const keys = [...loadByDay.keys()].sort()
    const start = keys.length ? new Date(keys[0] + 'T00:00:00') : new Date(from)
    let ctl = 0
    let atl = 0
    const days = []
    for (let d = new Date(Math.min(start, from)); d <= to; d.setDate(d.getDate() + 1)) {
        const key = dayKey(d)
        const load = loadByDay.get(key) ?? 0
        ctl += (load - ctl) / 42
        atl += (load - atl) / 7
        if (d >= from) days.push({ date: new Date(d), key, load, ctl, atl, tsb: ctl - atl })
    }
    return days
}

export function dayKey(d) {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

// Heart rate zone colours, zone 1 (easy) to 5 (max)
export const ZONE_COLORS = ['#94a3b8', '#22c55e', '#eab308', '#f97316', '#ef4444']

export function zoneLabel(i) {
    return t('fit_tracker', 'Zone {zone}', { zone: i + 1 })
}

// Power curve durations: 5 → "5 s", 300 → "5 min", 3600 → "1 h"
export function formatPowerDuration(seconds) {
    if (seconds < 60) return t('fit_tracker', '{seconds} s', { seconds })
    if (seconds < 3600) return t('fit_tracker', '{minutes} min', { minutes: seconds / 60 })
    return t('fit_tracker', '{hours} h', { hours: seconds / 3600 })
}

// Best effort distances in meters (AnalysisService::EFFORT_DISTANCES)
export function formatEffortDistance(meters) {
    const m = Number(meters)
    if (Math.abs(m - 1609.344) < 1) return t('fit_tracker', '1 mile')
    if (Math.abs(m - 21097.5) < 1) return t('fit_tracker', 'Half marathon')
    if (Math.abs(m - 42195) < 1) return t('fit_tracker', 'Marathon')
    if (m < 1000) return t('fit_tracker', '{meters} m', { meters: m })
    return t('fit_tracker', '{km} km', { km: m / 1000 })
}

// Sport family for best efforts, as in AnalysisService::effortFamily()
export function effortFamily(sport) {
    if (['running', 'walking', 'hiking', 'trail_running'].includes(sport)) return 'foot'
    if (['cycling', 'e_biking', 'mountain_biking'].includes(sport)) return 'cycle'
    return null
}
