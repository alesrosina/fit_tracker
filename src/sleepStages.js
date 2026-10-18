import { t } from '@nextcloud/l10n'

// Data colours for sleep stages, shared by every sleep chart and bar so the
// legend always matches. These are chart data colours, not UI chrome, so they
// deliberately don't follow the theme.
export const STAGE_COLORS = {
    deep:         '#1e40af',
    rem:          '#7c3aed',
    light:        '#60a5fa',
    awake:        '#fbbf24',
    unmeasurable: '#d1d5db',
}

export function stageLabel(stage) {
    switch (stage) {
    case 'deep': return t('fit_tracker', 'Deep')
    case 'rem': return t('fit_tracker', 'REM')
    case 'light': return t('fit_tracker', 'Light')
    case 'awake': return t('fit_tracker', 'Awake')
    case 'unmeasurable': return t('fit_tracker', 'Unmeasurable')
    default: return stage ? stage.charAt(0).toUpperCase() + stage.slice(1) : ''
    }
}
