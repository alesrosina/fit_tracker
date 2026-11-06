<template>
    <div class="ft-page records-page">
        <h2 class="ft-page-title">{{ t('fit_tracker', 'Records') }}</h2>

        <NcNoteCard v-if="analyzing" type="info">
            {{ n('fit_tracker', 'Looking for records in %n older activity…', 'Looking for records in %n older activities…', analyzing) }}
        </NcNoteCard>

        <LoadingSkeleton v-if="loading" />
        <LoadError
            v-else-if="error"
            :heading="t('fit_tracker', 'Could not load records')"
            :message="t('fit_tracker', 'Check your connection and try again.')"
            @retry="load"
        />
        <NcEmptyContent
            v-else-if="isEmpty"
            :name="t('fit_tracker', 'No records yet')"
            :description="t('fit_tracker', 'Records appear once activities with distance, time or power are imported.')"
        >
            <template #icon>
                <NcIconSvgWrapper :svg="icons.trophy" />
            </template>
        </NcEmptyContent>

        <template v-else>
            <section v-for="group in effortGroups" :key="group.family" class="ft-panel">
                <h3>{{ group.title }}</h3>
                <p class="ft-panel__hint">{{ t('fit_tracker', 'Fastest time over each distance within any activity, including stops.') }}</p>
                <table class="ft-table">
                    <thead>
                        <tr>
                            <th>{{ t('fit_tracker', 'Distance') }}</th>
                            <th>{{ t('fit_tracker', 'Best') }}</th>
                            <th>{{ t('fit_tracker', '2nd') }}</th>
                            <th>{{ t('fit_tracker', '3rd') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in group.rows" :key="row.meters">
                            <td :data-label="t('fit_tracker', 'Distance')"><strong>{{ formatEffortDistance(row.meters) }}</strong></td>
                            <td v-for="(place, i) in placesOf(row.top)" :key="i" :data-label="placeLabels[i]">
                                <RecordEntry v-if="place" :entry="place" :value="formatDurationClock(place.value)" :sub="paceOf(group.family, row.meters, place.value)" />
                                <span v-else>–</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section v-if="records.power.length" class="ft-panel">
                <h3>{{ t('fit_tracker', 'Power') }}</h3>
                <p class="ft-panel__hint">{{ t('fit_tracker', 'Best average power over each duration, across all activities with a power meter.') }}</p>
                <canvas ref="powerCanvas" class="records-page__chart" role="img" :aria-label="t('fit_tracker', 'Best power for each duration')"></canvas>
                <table class="ft-table">
                    <thead>
                        <tr>
                            <th>{{ t('fit_tracker', 'Duration') }}</th>
                            <th>{{ t('fit_tracker', 'Best') }}</th>
                            <th>{{ t('fit_tracker', '2nd') }}</th>
                            <th>{{ t('fit_tracker', '3rd') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in records.power" :key="row.seconds">
                            <td :data-label="t('fit_tracker', 'Duration')"><strong>{{ formatPowerDuration(row.seconds) }}</strong></td>
                            <td v-for="(place, i) in placesOf(row.top)" :key="i" :data-label="placeLabels[i]">
                                <RecordEntry v-if="place" :entry="place" :value="t('fit_tracker', '{watts} W', { watts: place.value })" />
                                <span v-else>–</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section v-if="records.longest.length" class="ft-panel">
                <h3>{{ t('fit_tracker', 'Longest and highest') }}</h3>
                <table class="ft-table">
                    <thead>
                        <tr>
                            <th>{{ t('fit_tracker', 'Sport') }}</th>
                            <th>{{ t('fit_tracker', 'Longest distance') }}</th>
                            <th>{{ t('fit_tracker', 'Longest time') }}</th>
                            <th>{{ t('fit_tracker', 'Most elevation gain') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in records.longest" :key="row.sport">
                            <td :data-label="t('fit_tracker', 'Sport')"><strong>{{ sportLabel(row.sport) }}</strong></td>
                            <td :data-label="t('fit_tracker', 'Longest distance')">
                                <RecordEntry v-if="row.distance" :entry="row.distance[0]" :value="formatDistance(row.distance[0].value)" />
                                <span v-else>–</span>
                            </td>
                            <td :data-label="t('fit_tracker', 'Longest time')">
                                <RecordEntry v-if="row.duration" :entry="row.duration[0]" :value="formatDurationShort(row.duration[0].value)" />
                                <span v-else>–</span>
                            </td>
                            <td :data-label="t('fit_tracker', 'Most elevation gain')">
                                <RecordEntry v-if="row.elevation" :entry="row.elevation[0]" :value="formatElevation(row.elevation[0].value, true)" />
                                <span v-else>–</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </template>
    </div>
</template>

<script>
import { Chart, LineController, LineElement, PointElement, LinearScale, CategoryScale, Tooltip, Filler } from 'chart.js'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { t } from '@nextcloud/l10n'
import { h } from 'vue'
import { RouterLink } from 'vue-router'
import { NcEmptyContent, NcIconSvgWrapper, NcNoteCard } from '@nextcloud/vue'
import LoadingSkeleton from './LoadingSkeleton.vue'
import LoadError from './LoadError.vue'
import { icons } from '../icons.js'
import { sportLabel } from '../sports.js'
import { baseChartOptions } from '../chartTheme.js'
import { navData, fetchActivities } from '../store/navData.js'
import { ensureAnalyzed } from '../store/analysis.js'
import { formatDay, formatDistance, formatDurationClock, formatDurationShort, formatElevation, formatPace, formatSpeed } from '../format.js'
import { formatEffortDistance, formatPowerDuration } from '../stats.js'
Chart.register(LineController, LineElement, PointElement, LinearScale, CategoryScale, Tooltip, Filler)

// One record: the value, linked to its activity, with the date below
const RecordEntry = {
    name: 'RecordEntry',
    props: { entry: { type: Object, required: true }, value: { type: String, required: true }, sub: { type: String, default: '' } },
    render() {
        const name = navData.activities.find(a => a.id === this.entry.activityId)?.name ?? this.entry.name
        return h(RouterLink, { to: '/activity/' + this.entry.activityId, class: 'record-entry', title: name }, () => [
            h('span', { class: 'record-entry__value' }, this.value),
            this.sub ? h('span', { class: 'record-entry__sub' }, this.sub) : null,
            h('span', { class: 'record-entry__date' }, formatDay(this.entry.startTime)),
        ])
    },
}

export default {
    name: 'RecordsPage',
    components: { LoadingSkeleton, LoadError, NcEmptyContent, NcIconSvgWrapper, NcNoteCard, RecordEntry },
    data() {
        return {
            icons,
            loading: true,
            error: false,
            analyzing: 0,
            records: { efforts: {}, power: [], longest: [] },
        }
    },
    computed: {
        placeLabels() {
            return [t('fit_tracker', 'Best'), t('fit_tracker', '2nd'), t('fit_tracker', '3rd')]
        },
        effortGroups() {
            const titles = { foot: t('fit_tracker', 'Running and walking'), cycle: t('fit_tracker', 'Cycling') }
            return Object.entries(this.records.efforts ?? {}).map(([family, rows]) => ({ family, title: titles[family] ?? family, rows }))
        },
        isEmpty() {
            return !this.effortGroups.length && !this.records.power.length && !this.records.longest.length
        },
    },
    created() {
        this.chart = null
    },
    mounted() {
        this.load()
    },
    beforeUnmount() {
        this.chart?.destroy()
    },
    methods: {
        sportLabel,
        formatDistance,
        formatDurationClock,
        formatDurationShort,
        formatElevation,
        formatEffortDistance,
        formatPowerDuration,
        placesOf(top) {
            return [0, 1, 2].map(i => top[i] ?? null)
        },
        paceOf(family, meters, seconds) {
            const kmh = meters / 1000 / seconds * 3600
            return family === 'cycle' ? formatSpeed(kmh) : formatPace(kmh)
        },
        async load() {
            this.loading = true
            this.error = false
            try {
                // Names of edited activities come from the activity list
                await Promise.all([
                    fetchActivities(),
                    ensureAnalyzed(remaining => { this.analyzing = remaining }),
                ])
                this.analyzing = 0
                const { data } = await axios.get(generateUrl('/apps/fit_tracker/api/stats/records'))
                this.records = { efforts: data.efforts ?? {}, power: data.power ?? [], longest: data.longest ?? [] }
            } catch (e) {
                this.error = true
            } finally {
                this.loading = false
                this.analyzing = 0
            }
            this.$nextTick(() => this.buildChart())
        },
        buildChart() {
            this.chart?.destroy()
            this.chart = null
            if (!this.$refs.powerCanvas || this.records.power.length < 2) return
            const base = baseChartOptions()
            this.chart = new Chart(this.$refs.powerCanvas, {
                type: 'line',
                data: {
                    labels: this.records.power.map(r => formatPowerDuration(r.seconds)),
                    datasets: [{
                        label: t('fit_tracker', 'Best power'),
                        data: this.records.power.map(r => r.top[0].value),
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
.records-page__chart {
    max-height: 260px;
    margin-bottom: var(--ft-gap-4);
}
</style>

<style>
/* RecordEntry is a render-function component, so its styles aren't scoped */
.record-entry {
    display: inline-flex;
    flex-direction: column;
    color: var(--color-main-text);
    text-decoration: none;
    border-radius: var(--border-radius-small);
}
.record-entry:hover .record-entry__value,
.record-entry:focus-visible .record-entry__value {
    text-decoration: underline;
}
.record-entry__value {
    font-weight: bold;
}
.record-entry__sub,
.record-entry__date {
    font-size: var(--font-size-small);
    color: var(--color-text-maxcontrast);
}
@media (max-width: 600px) {
    .record-entry {
        align-items: flex-end;
        text-align: end;
    }
}
</style>
