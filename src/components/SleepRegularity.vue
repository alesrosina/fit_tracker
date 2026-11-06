<template>
    <section class="ft-panel sleep-regularity">
        <h3>{{ t('fit_tracker', 'Bedtime and wake-up') }}</h3>
        <p class="ft-panel__hint">{{ t('fit_tracker', 'Each bar runs from falling asleep to waking up. The more the bars line up, the more regular your sleep.') }}</p>
        <div class="sleep-regularity__canvas">
            <canvas ref="canvas" role="img" :aria-label="t('fit_tracker', 'Bedtime and wake-up time per night')"></canvas>
        </div>

        <h4>{{ t('fit_tracker', 'Weekdays and weekends') }}</h4>
        <table class="ft-table">
            <thead>
                <tr>
                    <th />
                    <th>{{ t('fit_tracker', 'Nights') }}</th>
                    <th>{{ t('fit_tracker', 'Sleep') }}</th>
                    <th>{{ t('fit_tracker', 'Score') }}</th>
                    <th>{{ t('fit_tracker', 'Bedtime') }}</th>
                    <th>{{ t('fit_tracker', 'Wake-up') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="row in weekRows" :key="row.key">
                    <td><strong>{{ row.label }}</strong></td>
                    <td :data-label="t('fit_tracker', 'Nights')">{{ row.count }}</td>
                    <td :data-label="t('fit_tracker', 'Sleep')">{{ row.count ? formatDurationShort(row.duration) : '–' }}</td>
                    <td :data-label="t('fit_tracker', 'Score')">{{ row.score !== null ? Math.round(row.score) : '–' }}</td>
                    <td :data-label="t('fit_tracker', 'Bedtime')">{{ row.count ? clock(row.bed) : '–' }}</td>
                    <td :data-label="t('fit_tracker', 'Wake-up')">{{ row.count ? clock(row.wake) : '–' }}</td>
                </tr>
            </tbody>
        </table>
    </section>
</template>

<script>
import { Chart, BarController, BarElement, LinearScale, CategoryScale, Tooltip } from 'chart.js'
import { t } from '@nextcloud/l10n'
import { baseChartOptions } from '../chartTheme.js'
import { STAGE_COLORS } from '../sleepStages.js'
import { formatDurationShort } from '../format.js'
import { clock, hoursAfterEvening, avg } from '../sleepStats.js'
Chart.register(BarController, BarElement, LinearScale, CategoryScale, Tooltip)

// How regular sleep is in the selected timeframe: bed and wake-up time per
// night, and weekday against weekend nights.
export default {
    name: 'SleepRegularity',
    props: {
        // sleepStats.js toNights(), already limited to the timeframe
        nights: { type: Array, required: true },
    },
    computed: {
        weekRows() {
            // Friday and Saturday nights are the weekend
            const isWeekend = n => [5, 6].includes(n.evening.getDay())
            return [
                { key: 'week', label: t('fit_tracker', 'Weekday nights'), nights: this.nights.filter(n => !isWeekend(n)) },
                { key: 'weekend', label: t('fit_tracker', 'Weekend nights'), nights: this.nights.filter(isWeekend) },
            ].map(({ key, label, nights }) => ({
                key,
                label,
                count: nights.length,
                duration: avg(nights.map(n => n.session.duration)),
                score: avg(nights.map(n => n.session.score || null)),
                bed: avg(nights.map(n => hoursAfterEvening(n.bed))),
                wake: avg(nights.map(n => hoursAfterEvening(n.wake))),
            }))
        },
    },
    watch: {
        nights() {
            this.$nextTick(() => this.draw())
        },
    },
    created() {
        this.chart = null
    },
    mounted() {
        this.$nextTick(() => this.draw())
    },
    beforeUnmount() {
        this.chart?.destroy()
    },
    methods: {
        clock,
        formatDurationShort,
        draw() {
            this.chart?.destroy()
            this.chart = null
            if (!this.$refs.canvas || this.nights.length === 0) return
            const base = baseChartOptions()
            const nights = this.nights
            const span = n => {
                const from = hoursAfterEvening(n.bed)
                let to = hoursAfterEvening(n.wake)
                if (to < from) to += 24
                return [from, to]
            }
            this.chart = new Chart(this.$refs.canvas, {
                type: 'bar',
                data: {
                    labels: nights.map(n => n.evening.toLocaleDateString(undefined, { day: 'numeric', month: 'short' })),
                    datasets: [{
                        label: t('fit_tracker', 'Asleep'),
                        data: nights.map(span),
                        backgroundColor: STAGE_COLORS.rem,
                        borderRadius: 3,
                        barPercentage: 0.7,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            ...base.tooltip,
                            callbacks: {
                                label: ctx => {
                                    const [from, to] = ctx.raw
                                    return t('fit_tracker', '{from} – {to}', { from: clock(from), to: clock(to % 24) })
                                },
                            },
                        },
                    },
                    scales: {
                        x: { ticks: { ...base.ticks, maxRotation: 0, autoSkip: true }, grid: { display: false } },
                        y: {
                            reverse: true,
                            min: Math.max(0, Math.floor(Math.min(...nights.map(n => span(n)[0]))) - 1),
                            max: Math.min(30, Math.ceil(Math.max(...nights.map(n => span(n)[1]))) + 1),
                            ticks: { ...base.ticks, stepSize: 1, callback: v => clock(v % 24) },
                            grid: base.grid,
                            border: { color: base.grid.color },
                        },
                    },
                },
            })
        },
    },
}
</script>

<style scoped>
.sleep-regularity h3 {
    margin: 0 0 var(--ft-gap-1);
}
.sleep-regularity h4 {
    margin: var(--ft-gap-5) 0 var(--ft-gap-2);
    font-weight: bold;
}
.sleep-regularity__canvas {
    position: relative;
    height: 260px;
}
</style>
