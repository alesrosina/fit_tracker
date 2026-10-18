import { t } from '@nextcloud/l10n'

const pad = v => String(v).padStart(2, '0')

// Accepts both ISO strings and the backend's 'YYYY-MM-DD HH:MM:SS' format.
export const parseDate = raw => new Date(typeof raw === 'string' ? raw.replace(' ', 'T') : raw)

export function formatDistance(km) {
    if (km < 1) {
        // TRANSLATORS: Distance in meters, e.g. "850 m"
        return t('fit_tracker', '{meters} m', { meters: Math.round(km * 1000) })
    }
    // TRANSLATORS: Distance in kilometers, e.g. "12.35 km"
    return t('fit_tracker', '{km} km', { km: km.toFixed(2) })
}

// Compact duration for cards and summaries: "1h 5m", or "5m 30s" when withSeconds.
export function formatDurationShort(seconds, withSeconds = false) {
    const h = Math.floor(seconds / 3600)
    const m = Math.floor((seconds % 3600) / 60)
    const s = Math.round(seconds % 60)
    if (h > 0) {
        // TRANSLATORS: Short duration, e.g. "1h 5m" (hours and minutes)
        return t('fit_tracker', '{hours}h {minutes}m', { hours: h, minutes: m })
    }
    if (withSeconds) {
        // TRANSLATORS: Short duration, e.g. "5m 30s" (minutes and seconds)
        return t('fit_tracker', '{minutes}m {seconds}s', { minutes: m, seconds: s })
    }
    // TRANSLATORS: Short duration in minutes, e.g. "45m"
    return t('fit_tracker', '{minutes}m', { minutes: m })
}

// Stopwatch style: "1:05:30" or "5:30".
export function formatDurationClock(seconds) {
    const h = Math.floor(seconds / 3600)
    const m = Math.floor((seconds % 3600) / 60)
    const s = Math.round(seconds % 60)
    if (h > 0) return `${h}:${pad(m)}:${pad(s)}`
    return `${m}:${pad(s)}`
}

// Pace from km/h, e.g. "5:30 /km".
export function formatPace(kmh) {
    const minPerKm = 60 / kmh
    let m = Math.floor(minPerKm)
    let s = Math.round((minPerKm - m) * 60)
    if (s === 60) { m++; s = 0 }
    // TRANSLATORS: Running pace in minutes per kilometer, e.g. "5:30 /km"
    return t('fit_tracker', '{pace} /km', { pace: `${m}:${pad(s)}` })
}

export function formatSpeed(kmh) {
    // TRANSLATORS: Speed, e.g. "24.5 km/h"
    return t('fit_tracker', '{speed} km/h', { speed: kmh.toFixed(1) })
}

export function formatDay(raw) {
    if (!raw) return ''
    return parseDate(raw).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
}

export function formatDateTime(raw) {
    if (!raw) return ''
    return parseDate(raw).toLocaleString(undefined, { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

export function formatTime(raw) {
    if (!raw) return ''
    return parseDate(raw).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })
}
