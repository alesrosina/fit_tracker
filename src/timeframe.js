// The dashboard's timeframe, kept in the URL query so reloads, the back
// button and the navigation keep it:
//   ?range=7d|30d|365d|all          rolling windows that include today
//   ?range=custom&from=YYYY-MM-DD&to=YYYY-MM-DD   (both days included)
// plus ?sport=… for a sport's dashboard.
import { t } from '@nextcloud/l10n'
import { dayKey, periodStart } from './stats.js'

export const RANGES = ['7d', '30d', '365d', 'all', 'custom']
export const DEFAULT_RANGE = '30d'
const DAYS = { '7d': 7, '30d': 30, '365d': 365 }
const DAY_MS = 86400000

export function rangeLabel(range) {
    return {
        '7d': t('fit_tracker', 'Last 7 days'),
        '30d': t('fit_tracker', 'Last 30 days'),
        '365d': t('fit_tracker', 'Last 365 days'),
        all: t('fit_tracker', 'All time'),
        custom: t('fit_tracker', 'Custom'),
    }[range]
}

const midnight = d => new Date(d.getFullYear(), d.getMonth(), d.getDate())
const addDays = (d, n) => new Date(d.getFullYear(), d.getMonth(), d.getDate() + n)
export const parseDay = s => {
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s ?? '')
    return m ? new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3])) : null
}

/**
 * The window a route query describes: [from, to) in local time, its length
 * in days, and the window of the same length right before it (null for all
 * time).
 *
 * @param {object} query route query
 * @param {Date|null} firstDay earliest day with data, the start of "all time"
 */
export function resolveTimeframe(query, firstDay, now = new Date()) {
    const today = midnight(now)
    const tomorrow = addDays(today, 1)
    let range = RANGES.includes(query.range) ? query.range : DEFAULT_RANGE
    let from
    let to = tomorrow

    if (range === 'custom') {
        from = parseDay(query.from)
        const last = parseDay(query.to)
        if (!from || !last || last < from) {
            range = DEFAULT_RANGE
        } else {
            to = addDays(last, 1)
        }
    }
    if (range === 'all') {
        from = firstDay ? midnight(firstDay) : today
    } else if (range !== 'custom') {
        from = addDays(tomorrow, -DAYS[range])
    }

    const days = Math.max(1, Math.round((to - from) / DAY_MS))
    return {
        range,
        from,
        to,
        days,
        previous: range === 'all' ? null : { from: addDays(from, -days), to: from },
    }
}

// Query for a timeframe, without the sport (callers add it)
export function timeframeQuery(query) {
    const out = {}
    if (RANGES.includes(query.range) && query.range !== DEFAULT_RANGE) out.range = query.range
    if (query.range === 'custom') {
        out.from = query.from
        out.to = query.to
    }
    return out
}

// "12 Oct – 6 Nov 2026"
export function formatWindow(tf) {
    const last = new Date(tf.to.getTime() - DAY_MS)
    const sameYear = tf.from.getFullYear() === last.getFullYear()
    const fromText = tf.from.toLocaleDateString(undefined, sameYear
        ? { day: 'numeric', month: 'short' }
        : { day: 'numeric', month: 'short', year: 'numeric' })
    const toText = last.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' })
    return fromText === toText ? toText : `${fromText} – ${toText}`
}

// The last timeframe (and dashboard sport) shown, so going back from an
// activity or a night, and switching between Overview, a sport and Sleep,
// keeps the same timeframe
let lastTimeframe = {}
let lastSport = null
export function rememberTimeframe(query, sport = undefined) {
    lastTimeframe = timeframeQuery(query)
    if (sport !== undefined) lastSport = sport
}
export function rememberedTimeframe() {
    return { ...lastTimeframe }
}
export function dashboardRoute() {
    return { path: '/', query: lastSport ? { ...lastTimeframe, sport: lastSport } : { ...lastTimeframe } }
}
export function sleepRoute() {
    return { path: '/sleep', query: { ...lastTimeframe } }
}

// ── Chart bars ───────────────────────────────────────────────────────────────
// Charts over a timeframe show one bar per day, week, month or year. These
// sizes make sense for a timeframe of `days` days (not hundreds of thin bars,
// not a single bar).
export function bucketOptions(days) {
    const out = []
    if (days <= 92) out.push('day')
    if (days >= 14 && days <= 3 * 366) out.push('week')
    if (days >= 60) out.push('month')
    if (days >= 2 * 365) out.push('year')
    return out.length ? out : ['day']
}

export function defaultBucket(days) {
    if (days <= 62) return 'day'
    if (days <= 3 * 366) return days <= 120 ? 'week' : 'month'
    return days > 6 * 366 ? 'year' : 'month'
}

export function bucketLabel(bucket) {
    // TRANSLATORS: Chart bar size buttons: one bar per day, week, month or year
    return {
        day: t('fit_tracker', 'Day'),
        week: t('fit_tracker', 'Week'),
        month: t('fit_tracker', 'Month'),
        year: t('fit_tracker', 'Year'),
    }[bucket]
}

// Local midnight at the start of the bucket containing d
export function bucketStart(d, bucket) {
    if (bucket === 'day') return new Date(d.getFullYear(), d.getMonth(), d.getDate())
    if (bucket === 'year') return new Date(d.getFullYear(), 0, 1)
    return periodStart(d, bucket)
}

export function nextBucket(d, bucket) {
    if (bucket === 'day') return new Date(d.getFullYear(), d.getMonth(), d.getDate() + 1)
    if (bucket === 'week') return new Date(d.getFullYear(), d.getMonth(), d.getDate() + 7)
    if (bucket === 'month') return new Date(d.getFullYear(), d.getMonth() + 1, 1)
    return new Date(d.getFullYear() + 1, 0, 1)
}

// Bucket starts covering [from, to)
export function bucketsBetween(from, to, bucket) {
    const out = []
    for (let d = bucketStart(from, bucket); d < to; d = nextBucket(d, bucket)) out.push(d)
    return out
}

export function formatBucket(d, bucket) {
    if (bucket === 'year') return String(d.getFullYear())
    if (bucket === 'month') return d.toLocaleDateString(undefined, { month: 'short', year: '2-digit' })
    return d.toLocaleDateString(undefined, { day: 'numeric', month: 'short' })
}

export { dayKey }

/**
 * Change against the previous timeframe for a StatTile, or null when there
 * is nothing to compare (all time, or no data in either).
 *
 * @param {boolean} upIsGood false for e.g. sleep debt, where less is better
 */
export function compareChange(current, previous, tf, upIsGood = true) {
    if (!tf.previous || current === null || previous === null) return null
    if (!previous && !current) return null
    if (!previous) return { direction: 'up', good: upIsGood, text: t('fit_tracker', 'None in the previous {days} days', { days: tf.days }) }
    const change = current / previous - 1
    const percent = Math.round(Math.abs(change) * 100)
    if (percent === 0) return { direction: 'same', good: null, text: t('fit_tracker', 'Same as previous {days} days', { days: tf.days }) }
    const up = change > 0
    // TRANSLATORS: Comparison with the previous timeframe of the same length, e.g. "12 % vs previous 30 days"
    return { direction: up ? 'up' : 'down', good: up === upIsGood, text: t('fit_tracker', '{percent} % vs previous {days} days', { percent, days: tf.days }) }
}
