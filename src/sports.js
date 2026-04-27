export const SPORTS = [
    { value: 'running',    label: 'Running',    icon: '🏃', color: '#ef4444' },
    { value: 'cycling',    label: 'Cycling',    icon: '🚴', color: '#0082c9' },
    { value: 'hiking',     label: 'Hike',       icon: '🥾', color: '#22c55e' },
    { value: 'walking',    label: 'Walk',       icon: '🚶', color: '#14b8a6' },
    { value: 'swimming',   label: 'Swimming',   icon: '🏊', color: '#06b6d4' },
    { value: 'gym',        label: 'Gym',        icon: '🏋', color: '#f97316' },
    { value: 'breathwork', label: 'Breathwork', icon: '🧘', color: '#8b5cf6' },
    { value: 'meditation', label: 'Meditation', icon: '🕉️', color: '#6366f1' },
    { value: 'skiing',     label: 'Skiing',     icon: '⛷️', color: '#38bdf8' },
]

export const SPORT_MAP = Object.fromEntries(SPORTS.map(s => [s.value, s]))

export const sportIcon  = v => SPORT_MAP[v]?.icon  ?? '🏅'
export const sportColor = v => SPORT_MAP[v]?.color ?? '#94a3b8'
export const sportLabel = v => SPORT_MAP[v]?.label ?? v
