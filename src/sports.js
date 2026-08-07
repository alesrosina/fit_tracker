// Curated icon/color entries for the sports users are most likely to import.
// FitParserService now recognizes the full Garmin FIT sport enum (see
// lib/Service/FitSportProfile.php) and passes through anything not in this
// list as-is, so sports missing here still work — they just fall back to a
// generic icon/color and a title-cased label (see sportIcon/Label below).
export const SPORTS = [
    { value: 'running',    label: 'Running',    icon: '🏃', color: '#ef4444' },
    { value: 'cycling',    label: 'Cycling',    icon: '🚴', color: '#0082c9' },
    { value: 'hiking',     label: 'Hike',       icon: '🥾', color: '#22c55e' },
    { value: 'walking',    label: 'Walk',       icon: '🚶', color: '#14b8a6' },
    { value: 'swimming',   label: 'Swimming',   icon: '🏊', color: '#06b6d4' },
    { value: 'gym',        label: 'Gym',        icon: '🏋️', color: '#f97316' },
    { value: 'breathwork', label: 'Breathwork', icon: '🧘', color: '#8b5cf6' },
    { value: 'meditation', label: 'Meditation', icon: '🕉️', color: '#6366f1' },
    { value: 'skiing',     label: 'Skiing',     icon: '⛷️', color: '#38bdf8' },
    { value: 'rowing',                  label: 'Rowing',                  icon: '🚣', color: '#0ea5e9' },
    { value: 'golf',                    label: 'Golf',                    icon: '⛳', color: '#65a30d' },
    { value: 'sailing',                 label: 'Sailing',                 icon: '⛵', color: '#0369a1' },
    { value: 'kayaking',                label: 'Kayaking',                icon: '🛶', color: '#0891b2' },
    { value: 'canoeing',                label: 'Canoeing',                icon: '🛶', color: '#0e7490' },
    { value: 'surfing',                 label: 'Surfing',                 icon: '🏄', color: '#22d3ee' },
    { value: 'windsurfing',             label: 'Windsurfing',             icon: '🏄', color: '#0284c7' },
    { value: 'kitesurfing',             label: 'Kitesurfing',             icon: '🪁', color: '#7c3aed' },
    { value: 'stand_up_paddleboarding', label: 'Paddleboarding',          icon: '🏄', color: '#2dd4bf' },
    { value: 'water_skiing',            label: 'Water Skiing',            icon: '🎿', color: '#3b82f6' },
    { value: 'wakeboarding',            label: 'Wakeboarding',            icon: '🏄', color: '#4f46e5' },
    { value: 'wakesurfing',             label: 'Wakesurfing',             icon: '🏄', color: '#4338ca' },
    { value: 'boating',                 label: 'Boating',                 icon: '🚤', color: '#1d4ed8' },
    { value: 'motorcycling',            label: 'Motorcycling',            icon: '🏍️', color: '#78716c' },
    { value: 'driving',                 label: 'Driving',                 icon: '🚗', color: '#57534e' },
    { value: 'horseback_riding',        label: 'Horseback Riding',        icon: '🐎', color: '#a16207' },
    { value: 'tennis',                  label: 'Tennis',                  icon: '🎾', color: '#facc15' },
    { value: 'soccer',                  label: 'Soccer',                  icon: '⚽', color: '#16a34a' },
    { value: 'basketball',              label: 'Basketball',              icon: '🏀', color: '#ea580c' },
    { value: 'baseball',                label: 'Baseball',                icon: '⚾', color: '#dc2626' },
    { value: 'volleyball',              label: 'Volleyball',              icon: '🏐', color: '#eab308' },
    { value: 'american_football',       label: 'American Football',      icon: '🏈', color: '#7c2d12' },
    { value: 'rugby',                   label: 'Rugby',                   icon: '🏉', color: '#15803d' },
    { value: 'cricket',                 label: 'Cricket',                 icon: '🏏', color: '#059669' },
    { value: 'hockey',                  label: 'Hockey',                  icon: '🏒', color: '#1e3a8a' },
    { value: 'rock_climbing',           label: 'Rock Climbing',           icon: '🧗', color: '#92400e' },
    { value: 'mountaineering',          label: 'Mountaineering',          icon: '🏔️', color: '#57534e' },
    { value: 'ice_skating',             label: 'Ice Skating',             icon: '⛸️', color: '#7dd3fc' },
    { value: 'boxing',                  label: 'Boxing',                  icon: '🥊', color: '#b91c1c' },
    { value: 'mixed_martial_arts',      label: 'Martial Arts',            icon: '🥋', color: '#991b1b' },
    { value: 'fishing',                 label: 'Fishing',                 icon: '🎣', color: '#0f766e' },
    { value: 'diving',                  label: 'Diving',                  icon: '🤿', color: '#075985' },
    { value: 'snorkeling',              label: 'Snorkeling',              icon: '🤿', color: '#0c4a6e' },
    { value: 'archery',                 label: 'Archery',                 icon: '🏹', color: '#ca8a04' },
    { value: 'disc_golf',               label: 'Disc Golf',               icon: '🥏', color: '#84cc16' },
    { value: 'dance',                   label: 'Dance',                   icon: '💃', color: '#ec4899' },
    { value: 'generic',                 label: 'Activity',                icon: '🏅', color: '#94a3b8' },
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

export const sportIcon  = v => SPORT_MAP[v]?.icon  ?? '🏅'
export const sportColor = v => SPORT_MAP[v]?.color ?? '#94a3b8'
export const sportLabel = v => SPORT_MAP[v]?.label ?? titleCase(v)

function titleCase(v) {
    if (!v) return v
    return v.split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ')
}
