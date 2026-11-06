import { t } from '@nextcloud/l10n'
import { settings } from './store/settings.js'

const pad = v => String(v).padStart(2, '0')

export const KM_PER_MILE = 1.609344
export const FEET_PER_METER = 3.28084

// The backend stores times as 'YYYY-MM-DD HH:MM:SS' in UTC (FIT files record
// UTC). Strings with an explicit offset or 'Z' are taken as they are.
export const parseDate = raw => {
    if (typeof raw !== 'string') return new Date(raw)
    const iso = raw.replace(' ', 'T')
    return new Date(/(Z|[+-]\d\d:?\d\d)$/.test(iso) ? iso : iso + 'Z')
}

// Photo times come from the camera's EXIF clock, which is local time
export const parseLocalDate = raw => new Date(typeof raw === 'string' ? raw.replace(' ', 'T') : raw)

// Local calendar day of a backend timestamp, as 'YYYY-MM-DD'
export function localDayKey(raw) {
    const d = raw instanceof Date ? raw : parseDate(raw)
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}

// ── Units ────────────────────────────────────────────────────────────────────
// Values are stored metric (km, km/h, m); these convert for display.

export const isImperial = () => settings.units === 'imperial'

// km → km or miles
export const distanceValue = km => isImperial() ? km / KM_PER_MILE : km
// m → m or feet
export const elevationValue = m => isImperial() ? m * FEET_PER_METER : m
// km/h → km/h or mph
export const speedValue = kmh => isImperial() ? kmh / KM_PER_MILE : kmh
// km/h → minutes per km or per mile
export const paceValue = kmh => (isImperial() ? KM_PER_MILE * 60 : 60) / kmh

// One distance unit (1 km or 1 mile) in km, e.g. for splits
export const splitKm = () => isImperial() ? KM_PER_MILE : 1

export const distanceUnit = () => isImperial() ? t('fit_tracker', 'mi') : t('fit_tracker', 'km')
export const elevationUnit = () => isImperial() ? t('fit_tracker', 'ft') : t('fit_tracker', 'm')
export const speedUnit = () => isImperial() ? t('fit_tracker', 'mph') : t('fit_tracker', 'km/h')
export const paceUnit = () => isImperial() ? t('fit_tracker', 'min/mi') : t('fit_tracker', 'min/km')

export function formatDistance(km, decimals = 2) {
    if (isImperial()) {
        // TRANSLATORS: Distance in miles, e.g. "7.65 mi"
        return t('fit_tracker', '{miles} mi', { miles: distanceValue(km).toFixed(decimals) })
    }
    if (km < 1) {
        // TRANSLATORS: Distance in meters, e.g. "850 m"
        return t('fit_tracker', '{meters} m', { meters: Math.round(km * 1000) })
    }
    // TRANSLATORS: Distance in kilometers, e.g. "12.35 km"
    return t('fit_tracker', '{km} km', { km: km.toFixed(decimals) })
}

// Elevation from meters, e.g. "+120 m" or "+394 ft" with signed=true
export function formatElevation(m, signed = false) {
    const value = Math.round(elevationValue(m))
    if (isImperial()) {
        return signed
            // TRANSLATORS: Elevation gain in feet, e.g. "+394 ft"
            ? t('fit_tracker', '+{feet} ft', { feet: value })
            // TRANSLATORS: Elevation in feet, e.g. "394 ft"
            : t('fit_tracker', '{feet} ft', { feet: value })
    }
    return signed
        ? t('fit_tracker', '+{meters} m', { meters: value })
        : t('fit_tracker', '{meters} m', { meters: value })
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
    if (s === 60) return formatDurationClock(Math.floor(seconds) + 1)
    if (h > 0) return `${h}:${pad(m)}:${pad(s)}`
    return `${m}:${pad(s)}`
}

// Minutes as "m:ss", e.g. 5.5 → "5:30"
export function formatMinutes(minutes) {
    let m = Math.floor(minutes)
    let s = Math.round((minutes - m) * 60)
    if (s === 60) { m++; s = 0 }
    return `${m}:${pad(s)}`
}

// Pace from km/h, e.g. "5:30 /km" or "8:51 /mi".
export function formatPace(kmh) {
    const pace = formatMinutes(paceValue(kmh))
    if (isImperial()) {
        // TRANSLATORS: Running pace in minutes per mile, e.g. "8:51 /mi"
        return t('fit_tracker', '{pace} /mi', { pace })
    }
    // TRANSLATORS: Running pace in minutes per kilometer, e.g. "5:30 /km"
    return t('fit_tracker', '{pace} /km', { pace })
}

export function formatSpeed(kmh) {
    if (isImperial()) {
        // TRANSLATORS: Speed in miles per hour, e.g. "15.2 mph"
        return t('fit_tracker', '{speed} mph', { speed: speedValue(kmh).toFixed(1) })
    }
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
    const d = raw instanceof Date ? raw : parseDate(raw)
    return d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })
}

// Photo EXIF times are local, see parseLocalDate
export function formatPhotoTime(raw) {
    if (!raw) return ''
    return parseLocalDate(raw).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })
}
