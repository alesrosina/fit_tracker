<template>
    <section class="power-analysis">
        <h3>{{ t('fit_tracker', 'Power') }}</h3>
        <div class="power-analysis__grid">
            <div class="power-stats">
                <div v-for="s in stats" :key="s.label" class="stat" :title="s.title">
                    <div class="stat__label">{{ s.label }}</div>
                    <div class="stat__value">{{ s.value }}</div>
                </div>
            </div>
            <div v-if="curve.length > 1" class="power-curve">
                <h4 class="chart-label">{{ t('fit_tracker', 'Best average power') }}</h4>
                <canvas ref="canvas" role="img" :aria-label="t('fit_tracker', 'Best average power for durations from 5 seconds to 1 hour')"></canvas>
            </div>
        </div>
        <p v-if="!ftp" class="power-analysis__hint">
            {{ t('fit_tracker', 'Set your functional threshold power (FTP) in the settings to see intensity factor and training stress score.') }}
        </p>
    </section>
</template>

<script>
import { Chart, LineController, LineElement, PointElement, LinearScale, CategoryScale, Filler, Tooltip } from 'chart.js'
import { t } from '@nextcloud/l10n'
import { baseChartOptions } from '../chartTheme.js'
import { formatPowerDuration } from '../stats.js'
Chart.register(LineController, LineElement, PointElement, LinearScale, CategoryScale, Filler, Tooltip)

// Power numbers for an activity with a power meter: normalized power,
// variability index, and with an FTP set, intensity factor and TSS. The
// curve shows the best average power over 5 s … 1 h (AnalysisService.php).
export default {
    name: 'PowerAnalysis',
    props: {
        analysis: { type: Object, required: true },
        avgPower: { type: Number, default: null },
        duration: { type: Number, default: 0 },
        ftp: { type: Number, default: null },
    },
    computed: {
        curve() {
            return Object.entries(this.analysis.power ?? {})
                .map(([s, w]) => ({ seconds: Number(s), watts: w }))
                .sort((a, b) => a.seconds - b.seconds)
        },
        np() { return this.analysis.np ?? null },
        intensity() { return this.np && this.ftp ? this.np / this.ftp : null },
        stats() {
            const w = v => t('fit_tracker', '{watts} W', { watts: Math.round(v) })
            const list = []
            if (this.avgPower) list.push({ label: t('fit_tracker', 'Average power'), value: w(this.avgPower) })
            if (this.np) {
                list.push({
                    label: t('fit_tracker', 'Normalized power'),
                    value: w(this.np),
                    title: t('fit_tracker', 'What the effort cost your body, weighting hard surges more than steady riding'),
                })
            }
            const best5 = this.curve.find(c => c.seconds === 5)
            if (best5) list.push({ label: t('fit_tracker', 'Best 5 s'), value: w(best5.watts) })
            if (this.np && this.avgPower) {
                list.push({
                    label: t('fit_tracker', 'Variability index'),
                    value: (this.np / this.avgPower).toFixed(2),
                    title: t('fit_tracker', 'Normalized ÷ average power: close to 1.0 means a steady effort'),
                })
            }
            if (this.intensity) {
                list.push({ label: t('fit_tracker', 'Intensity factor'), value: this.intensity.toFixed(2), title: t('fit_tracker', 'Normalized power ÷ FTP') })
                const tss = this.duration * this.np * this.intensity / (this.ftp * 3600) * 100
                list.push({ label: t('fit_tracker', 'Training stress score'), value: Math.round(tss), title: t('fit_tracker', '100 equals one hour at FTP') })
            }
            return list
        },
    },
    mounted() {
        this.$nextTick(() => this.buildChart())
    },
    beforeUnmount() {
        this.chart?.destroy()
    },
    methods: {
        buildChart() {
            if (!this.$refs.canvas) return
            const base = baseChartOptions()
            this.chart = new Chart(this.$refs.canvas, {
                type: 'line',
                data: {
                    labels: this.curve.map(c => formatPowerDuration(c.seconds)),
                    datasets: [{
                        label: t('fit_tracker', 'Power'),
                        data: this.curve.map(c => c.watts),
                        borderColor: '#f97316',
                        backgroundColor: '#f9731633',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 3,
                    }],
                },
                options: {
                    responsive: true,
                    animation: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: { ...base.tooltip, callbacks: { label: ctx => t('fit_tracker', '{watts} W', { watts: ctx.parsed.y }) } },
                    },
                    scales: {
                        x: { ticks: base.ticks, grid: { display: false } },
                        y: { beginAtZero: true, ticks: base.ticks, grid: base.grid, border: { color: base.grid.color } },
                    },
                },
            })
        },
    },
}
</script>

<style scoped>
.power-analysis {
    margin-bottom: var(--ft-gap-6);
}
.power-analysis__grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(320px, 100%), 1fr));
    gap: var(--ft-gap-5);
}
.power-stats {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
    gap: var(--ft-gap-3);
    align-content: start;
}
.power-curve {
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-container);
    padding: var(--ft-gap-3);
}
.chart-label {
    margin: 0 0 var(--ft-gap-2);
    font-size: var(--font-size-small);
    font-weight: bold;
    color: var(--color-text-maxcontrast);
}
.power-analysis__hint {
    margin-top: var(--ft-gap-3);
    font-size: var(--font-size-small);
    color: var(--color-text-maxcontrast);
}
.stat {
    background: var(--color-background-dark);
    border-radius: var(--border-radius-element);
    padding: var(--ft-gap-3);
    text-align: center;
}
.stat__label {
    font-size: var(--font-size-small);
    color: var(--color-text-maxcontrast);
    margin-bottom: var(--ft-gap-1);
}
.stat__value {
    font-size: 20px;
    font-weight: bold;
}
</style>
