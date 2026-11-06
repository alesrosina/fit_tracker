// Sleep numbers shared by the dashboard, the goals page and the sleep trends.
// Clock times are handled as hours after 18:00, so a night from 23:30 to
// 07:00 is 5.5 → 13 and nights don't wrap around midnight.
import { parseDate, localDayKey } from './format.js'

export const EVENING = 18
// Naps are short sessions without stage data (see SleepParserService::parseNap)
export const NAP_MAX_SECONDS = 3 * 3600
export const DEFAULT_SLEEP_HOURS = 8

export const hoursAfterEvening = d => ((d.getHours() + d.getMinutes() / 60 - EVENING) + 24) % 24

export const avg = values => {
    const v = values.filter(x => x !== null && x !== undefined)
    return v.length ? v.reduce((a, b) => a + b, 0) / v.length : null
}

export const spread = values => {
    const m = avg(values)
    if (m === null) return 0
    return Math.sqrt(avg(values.map(v => (v - m) ** 2)))
}

export const isNap = s => s.duration < NAP_MAX_SECONDS && !(s.timeDeep || s.timeLight || s.timeRem)

/**
 * Sessions as nights, oldest first. A night belongs to the evening it
 * started on: falling asleep after midnight still counts for the day before.
 */
export function toNights(sessions) {
    return sessions
        .filter(s => !isNap(s))
        .map(s => {
            const bed = parseDate(s.startTime)
            const wake = parseDate(s.endTime)
            const evening = new Date(bed)
            if (bed.getHours() < 12) evening.setDate(evening.getDate() - 1)
            evening.setHours(0, 0, 0, 0)
            return { session: s, bed, wake, evening, eveningKey: localDayKey(evening) }
        })
        .sort((a, b) => a.bed - b.bed)
}

// Nights whose evening falls in [from, to)
export const nightsBetween = (nights, from, to) => nights.filter(n => n.evening >= from && n.evening < to)

export function naps(sessions, from, to) {
    return sessions.filter(s => {
        if (!isNap(s)) return false
        const d = parseDate(s.startTime)
        return d >= from && d < to
    })
}

export function summarize(nights) {
    const beds = nights.map(x => hoursAfterEvening(x.bed))
    const wakes = nights.map(x => hoursAfterEvening(x.wake))
    const stageTotal = n => (n.session.timeDeep || 0) + (n.session.timeLight || 0) + (n.session.timeRem || 0) + (n.session.timeAwake || 0)
    const staged = nights.filter(n => stageTotal(n) > 0)
    const share = key => staged.length ? avg(staged.map(n => (n.session[key] || 0) / stageTotal(n))) : null
    return {
        count: nights.length,
        duration: avg(nights.map(x => x.session.duration)),
        score: avg(nights.map(x => x.session.score || null)),
        hrv: avg(nights.map(x => x.session.hrvScore || null)),
        bed: avg(beds),
        wake: avg(wakes),
        bedSpread: spread(beds) * 60,
        wakeSpread: spread(wakes) * 60,
        stages: staged.length
            ? { deep: share('timeDeep'), rem: share('timeRem'), light: share('timeLight'), awake: share('timeAwake') }
            : null,
    }
}

// Hours after 18:00 → local clock time
export function clock(hours) {
    if (hours === null || hours === undefined) return '–'
    const d = new Date(2000, 0, 1, EVENING, 0)
    d.setMinutes(Math.round(hours * 60))
    return d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })
}

/**
 * Nights grouped by the training load (TRIMP) of the day before: rest days,
 * days up to the median training day, and harder days.
 *
 * @param {Map<string, number>} loadByDay local 'YYYY-MM-DD' → load
 */
export function nightsByLoad(nights, loadByDay) {
    const load = n => loadByDay.get(n.eveningKey) ?? 0
    const values = nights.map(load).filter(v => v > 0).sort((a, b) => a - b)
    const median = values.length ? values[Math.floor(values.length / 2)] : 0
    return {
        median,
        rest: nights.filter(n => load(n) === 0),
        easy: nights.filter(n => load(n) > 0 && load(n) <= median),
        hard: nights.filter(n => load(n) > median),
    }
}
