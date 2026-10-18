// Chart.js chrome (axis text, grid lines, tooltips) taken from the active
// Nextcloud theme, so charts stay legible in dark mode and custom themes.
// Data series keep their own colours (sports.js, sleepStages.js).
function cssVar(name, fallback) {
    const value = getComputedStyle(document.body).getPropertyValue(name).trim()
    return value || fallback
}

export function chartTheme() {
    return {
        text: cssVar('--color-text-maxcontrast', '#6b6b6b'),
        mainText: cssVar('--color-main-text', '#222'),
        grid: cssVar('--color-border', 'rgba(0,0,0,0.1)'),
        background: cssVar('--color-main-background', '#fff'),
        font: cssVar('--font-face', 'system-ui, sans-serif'),
    }
}

// Default options shared by every chart; merge chart-specific options on top.
export function baseChartOptions() {
    const c = chartTheme()
    return {
        theme: c,
        tooltip: {
            backgroundColor: c.background,
            titleColor: c.mainText,
            bodyColor: c.mainText,
            borderColor: c.grid,
            borderWidth: 1,
        },
        ticks: { color: c.text, font: { size: 13, family: c.font } },
        grid: { color: c.grid },
    }
}
