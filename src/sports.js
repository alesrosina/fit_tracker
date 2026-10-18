import { t } from '@nextcloud/l10n'

// Labels are getters so t() runs on read, after the locale is loaded.
const sport = (value, label, icon, color) => ({
    value,
    get label() { return label() },
    icon,
    color,
})

// Curated icon/color entries for the sports users are most likely to import.
// FitParserService now recognizes the full Garmin FIT sport enum (see
// lib/Service/FitSportProfile.php) and passes through anything not in this
// list as-is, so sports missing here still work — they just fall back to a
// generic icon/color and a sentence-cased label (see sportIcon/Label below).
// `icon` is a Material Symbols key from icons.js.
export const SPORTS = [
    sport('running', () => t('fit_tracker', 'Running'), 'directions_run', '#ef4444'),
    sport('cycling', () => t('fit_tracker', 'Cycling'), 'directions_bike', '#0082c9'),
    sport('hiking', () => t('fit_tracker', 'Hike'), 'hiking', '#22c55e'),
    sport('walking', () => t('fit_tracker', 'Walk'), 'directions_walk', '#14b8a6'),
    sport('swimming', () => t('fit_tracker', 'Swimming'), 'pool', '#06b6d4'),
    sport('gym', () => t('fit_tracker', 'Gym'), 'fitness_center', '#f97316'),
    sport('breathwork', () => t('fit_tracker', 'Breathwork'), 'air', '#8b5cf6'),
    sport('meditation', () => t('fit_tracker', 'Meditation'), 'self_improvement', '#6366f1'),
    sport('skiing', () => t('fit_tracker', 'Skiing'), 'downhill_skiing', '#38bdf8'),
    sport('rowing', () => t('fit_tracker', 'Rowing'), 'rowing', '#0ea5e9'),
    sport('golf', () => t('fit_tracker', 'Golf'), 'sports_golf', '#65a30d'),
    sport('sailing', () => t('fit_tracker', 'Sailing'), 'sailing', '#0369a1'),
    sport('kayaking', () => t('fit_tracker', 'Kayaking'), 'kayaking', '#0891b2'),
    sport('canoeing', () => t('fit_tracker', 'Canoeing'), 'kayaking', '#0e7490'),
    sport('surfing', () => t('fit_tracker', 'Surfing'), 'surfing', '#22d3ee'),
    sport('windsurfing', () => t('fit_tracker', 'Windsurfing'), 'surfing', '#0284c7'),
    sport('kitesurfing', () => t('fit_tracker', 'Kitesurfing'), 'kitesurfing', '#7c3aed'),
    sport('stand_up_paddleboarding', () => t('fit_tracker', 'Paddleboarding'), 'surfing', '#2dd4bf'),
    sport('water_skiing', () => t('fit_tracker', 'Water skiing'), 'surfing', '#3b82f6'),
    sport('wakeboarding', () => t('fit_tracker', 'Wakeboarding'), 'surfing', '#4f46e5'),
    sport('wakesurfing', () => t('fit_tracker', 'Wakesurfing'), 'surfing', '#4338ca'),
    sport('boating', () => t('fit_tracker', 'Boating'), 'directions_boat', '#1d4ed8'),
    sport('motorcycling', () => t('fit_tracker', 'Motorcycling'), 'motorcycle', '#78716c'),
    sport('driving', () => t('fit_tracker', 'Driving'), 'directions_car', '#57534e'),
    sport('horseback_riding', () => t('fit_tracker', 'Horseback riding'), 'sports_and_outdoors', '#a16207'),
    sport('tennis', () => t('fit_tracker', 'Tennis'), 'sports_tennis', '#facc15'),
    sport('soccer', () => t('fit_tracker', 'Soccer'), 'sports_soccer', '#16a34a'),
    sport('basketball', () => t('fit_tracker', 'Basketball'), 'sports_basketball', '#ea580c'),
    sport('baseball', () => t('fit_tracker', 'Baseball'), 'sports_baseball', '#dc2626'),
    sport('volleyball', () => t('fit_tracker', 'Volleyball'), 'sports_volleyball', '#eab308'),
    sport('american_football', () => t('fit_tracker', 'American football'), 'sports_football', '#7c2d12'),
    sport('rugby', () => t('fit_tracker', 'Rugby'), 'sports_rugby', '#15803d'),
    sport('cricket', () => t('fit_tracker', 'Cricket'), 'sports_cricket', '#059669'),
    sport('hockey', () => t('fit_tracker', 'Hockey'), 'sports_hockey', '#1e3a8a'),
    sport('rock_climbing', () => t('fit_tracker', 'Rock climbing'), 'mountain_flag', '#92400e'),
    sport('mountaineering', () => t('fit_tracker', 'Mountaineering'), 'mountain_flag', '#57534e'),
    sport('ice_skating', () => t('fit_tracker', 'Ice skating'), 'ice_skating', '#7dd3fc'),
    sport('boxing', () => t('fit_tracker', 'Boxing'), 'sports_mma', '#b91c1c'),
    sport('mixed_martial_arts', () => t('fit_tracker', 'Martial arts'), 'sports_martial_arts', '#991b1b'),
    sport('fishing', () => t('fit_tracker', 'Fishing'), 'phishing', '#0f766e'),
    sport('diving', () => t('fit_tracker', 'Diving'), 'scuba_diving', '#075985'),
    sport('snorkeling', () => t('fit_tracker', 'Snorkeling'), 'scuba_diving', '#0c4a6e'),
    sport('archery', () => t('fit_tracker', 'Archery'), 'target', '#ca8a04'),
    sport('disc_golf', () => t('fit_tracker', 'Disc golf'), 'sports_golf', '#84cc16'),
    sport('dance', () => t('fit_tracker', 'Dance'), 'music_note', '#ec4899'),
    sport('generic', () => t('fit_tracker', 'Activity'), 'exercise', '#94a3b8'),
]

// Sports where the natural unit is speed (km/h) rather than pace (min/km) —
// used by ActivityCharts/ActivityDetail. Getting this wrong for e.g. a sailboat
// is the same bug class as detecting it as "running" in the first place: a
// number that's technically computed right but rendered in a unit that makes
// no sense for that activity.
export const SPEED_NOT_PACE_SPORTS = [
    'cycling', 'skiing', 'sailing', 'boating', 'rowing', 'kayaking', 'canoeing',
    'motorcycling', 'driving', 'surfing', 'windsurfing', 'kitesurfing',
    'water_skiing', 'wakeboarding', 'wakesurfing', 'stand_up_paddleboarding',
    'horseback_riding',
]

export const SPORT_MAP = Object.fromEntries(SPORTS.map(s => [s.value, s]))

export const sportIcon  = v => SPORT_MAP[v]?.icon  ?? 'exercise'
export const sportColor = v => SPORT_MAP[v]?.color ?? '#94a3b8'
export const sportLabel = v => SPORT_MAP[v]?.label ?? sentenceCase(v)

function sentenceCase(v) {
    if (!v) return v
    const words = v.split('_').join(' ')
    return words.charAt(0).toUpperCase() + words.slice(1)
}
