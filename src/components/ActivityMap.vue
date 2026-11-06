<template>
    <div class="activity-map-section">
        <div class="map-header">
            <h3>{{ t('fit_tracker', 'Route') }}</h3>
            <NcSelect
                v-if="colorOptions.length > 1"
                v-model="colorOption"
                class="map-header__color"
                :options="colorOptions"
                :input-label="t('fit_tracker', 'Colour route by')"
                :clearable="false"
                label="label"
            />
        </div>
        <div ref="mapEl" class="map-container"></div>
        <div v-if="legend" class="map-legend" aria-hidden="true">
            <span>{{ legend.min }}</span>
            <span class="map-legend__bar" :style="{ background: legendGradient }" />
            <span>{{ legend.max }}</span>
        </div>
    </div>
</template>

<script>
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { t } from '@nextcloud/l10n'
import { NcSelect } from '@nextcloud/vue'
import { formatSpeed, formatElevation } from '../format.js'
import { createPhotoLayer } from '../photoMarkers.js'

// Slow/low → fast/high
const ROUTE_COLORS = ['#3b82f6', '#06b6d4', '#22c55e', '#84cc16', '#eab308', '#f97316', '#ef4444']

function percentile(sorted, p) {
    return sorted[Math.min(sorted.length - 1, Math.max(0, Math.round(p * (sorted.length - 1))))]
}

// Centered moving average ignoring nulls, so GPS speed jitter doesn't make the route flicker
function smooth(arr, radius) {
    return arr.map((v, i) => {
        if (v === null) return null
        let sum = 0, n = 0
        for (let j = Math.max(0, i - radius); j <= Math.min(arr.length - 1, i + radius); j++) {
            if (arr[j] !== null) { sum += arr[j]; n++ }
        }
        return sum / n
    })
}

export default {
    name: 'ActivityMap',
    components: { NcSelect },
    props: {
        trackpoints: { type: Array, required: true },
        photos:      { type: Array, default: () => [] },
        // Trackpoint index to mark on the route (hovered in a chart)
        highlightIndex: { type: Number, default: null },
    },
    emits: ['hover'],
    data() {
        return { map: null, colorOption: null, legend: null }
    },
    computed: {
        // Trackpoint indices with a position
        gpsIndices() {
            const out = []
            this.trackpoints.forEach((tp, i) => { if (tp.lat !== null && tp.lon !== null) out.push(i) })
            return out
        },
        colorOptions() {
            const has = key => this.gpsIndices.some(i => this.trackpoints[i][key] !== null)
            const options = [{ value: 'none', label: t('fit_tracker', 'Single colour') }]
            if (has('speed')) options.push({ value: 'speed', label: t('fit_tracker', 'Speed') })
            if (has('heartRate')) options.push({ value: 'heartRate', label: t('fit_tracker', 'Heart rate') })
            if (has('altitude')) options.push({ value: 'altitude', label: t('fit_tracker', 'Elevation') })
            return options
        },
        legendGradient() {
            return `linear-gradient(to right, ${ROUTE_COLORS.join(', ')})`
        },
    },
    watch: {
        colorOption() {
            if (this.map) this.drawRoute()
        },
        highlightIndex(index) {
            if (!this.map) return
            const tp = index === null ? null : this.trackpoints[index]
            if (!tp || tp.lat === null || tp.lon === null) {
                this.hoverMarker?.remove()
                return
            }
            if (!this.hoverMarker) {
                this.hoverMarker = L.circleMarker([tp.lat, tp.lon], {
                    radius: 7, color: '#fff', weight: 2, fillColor: '#0082c9', fillOpacity: 1, interactive: false,
                })
            }
            this.hoverMarker.setLatLng([tp.lat, tp.lon])
            if (!this.map.hasLayer(this.hoverMarker)) this.hoverMarker.addTo(this.map)
        },
        photos(newPhotos) {
            this.photoLayer?.setPhotos(newPhotos)
        },
    },
    created() {
        // Leaflet layers stay out of data(): they must not become reactive proxies
        this.routeLayer = null
        this.hoverMarker = null
        this.photoLayer = null
    },
    mounted() {
        this.colorOption = this.colorOptions[0]
        this.$nextTick(() => this.initMap())
    },
    beforeUnmount() {
        window.removeEventListener('keydown', this.onCtrlZoomKeyDown)
        window.removeEventListener('keyup', this.onCtrlZoomKeyUp)
    },
    methods: {
        initMap() {
            const coords = this.trackpoints
                .filter(tp => tp.lat !== null && tp.lon !== null)
                .map(tp => [tp.lat, tp.lon])

            if (coords.length === 0) return

            this.map = L.map(this.$refs.mapEl, { scrollWheelZoom: false })
            this.setupCtrlScrollZoom()

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19,
                referrerPolicy: 'strict-origin-when-cross-origin',
            }).addTo(this.map)

            this.map.fitBounds(L.latLngBounds(coords), { padding: [20, 20] })
            this.drawRoute()

            // Start marker
            L.circleMarker(coords[0], {
                radius: 8, color: '#22c55e', fillColor: '#22c55e', fillOpacity: 1,
            }).bindTooltip(t('fit_tracker', 'Start')).addTo(this.map)

            // End marker
            L.circleMarker(coords[coords.length - 1], {
                radius: 8, color: '#ef4444', fillColor: '#ef4444', fillOpacity: 1,
            }).bindTooltip(t('fit_tracker', 'Finish')).addTo(this.map)

            // Thumbnails open the activity page's photo viewer
            this.photoLayer = createPhotoLayer(this.map, {
                onOpen: (cluster, index) => document.dispatchEvent(new CustomEvent('fit-photo-open', {
                    detail: { fileId: cluster[index].fileId, name: cluster[index].name },
                })),
            })
            this.photoLayer.setPhotos(this.photos)
        },
        // The route as one line, or as runs of coloured segments by speed/HR/elevation
        drawRoute() {
            this.routeLayer?.remove()
            const tps = this.trackpoints
            const idx = this.gpsIndices
            const mode = this.colorOption?.value ?? 'none'
            const group = L.featureGroup()

            if (mode === 'none') {
                L.polyline(idx.map(i => [tps[i].lat, tps[i].lon]), { color: '#0082c9', weight: 3, opacity: 0.85 }).addTo(group)
                this.legend = null
            } else {
                let values = idx.map(i => tps[i][mode])
                if (mode === 'speed') values = smooth(values, 5)
                const sorted = values.filter(v => v !== null).sort((a, b) => a - b)
                // 5th–95th percentile, so a few outliers don't wash out the colours
                const min = percentile(sorted, 0.05)
                const max = percentile(sorted, 0.95)
                const bucket = v => v === null ? null
                    : Math.round(Math.min(1, Math.max(0, (v - min) / ((max - min) || 1))) * (ROUTE_COLORS.length - 1))
                let run = [[tps[idx[0]].lat, tps[idx[0]].lon]]
                let runBucket = bucket(values[0])
                const flush = () => {
                    if (run.length > 1) {
                        L.polyline(run, { color: runBucket === null ? '#94a3b8' : ROUTE_COLORS[runBucket], weight: 4, opacity: 0.9 }).addTo(group)
                    }
                }
                for (let k = 1; k < idx.length; k++) {
                    const point = [tps[idx[k]].lat, tps[idx[k]].lon]
                    const b = bucket(values[k])
                    run.push(point)
                    if (b !== runBucket) {
                        flush()
                        run = [point]
                        runBucket = b
                    }
                }
                flush()
                const fmt = {
                    speed: v => formatSpeed(v),
                    heartRate: v => t('fit_tracker', '{bpm} bpm', { bpm: Math.round(v) }),
                    altitude: v => formatElevation(v),
                }[mode]
                this.legend = sorted.length ? { min: fmt(min), max: fmt(max) } : null
            }

            // Hovering the route marks the same moment in the charts
            group.on('mousemove', e => this.$emit('hover', this.nearestTrackpoint(e.latlng)))
            group.on('mouseout', () => this.$emit('hover', null))
            group.addTo(this.map)
            group.bringToBack()
            this.routeLayer = group
        },
        nearestTrackpoint(latlng) {
            let best = null
            let bestDist = Infinity
            for (const i of this.gpsIndices) {
                const tp = this.trackpoints[i]
                const d = (tp.lat - latlng.lat) ** 2 + (tp.lon - latlng.lng) ** 2
                if (d < bestDist) { bestDist = d; best = i }
            }
            return best
        },
        setupCtrlScrollZoom() {
            this.onCtrlZoomKeyDown = (e) => {
                if (e.key === 'Control' || e.key === 'Meta') this.map.scrollWheelZoom.enable()
            }
            this.onCtrlZoomKeyUp = (e) => {
                if (e.key === 'Control' || e.key === 'Meta') this.map.scrollWheelZoom.disable()
            }
            window.addEventListener('keydown', this.onCtrlZoomKeyDown)
            window.addEventListener('keyup', this.onCtrlZoomKeyUp)

            this.$refs.mapEl.addEventListener('wheel', (e) => {
                if (!e.ctrlKey && !e.metaKey) return
                e.preventDefault()
                this.map.scrollWheelZoom.enable()
            }, { passive: false })
        },
    },
}
</script>

<style scoped>
.activity-map-section {
    margin-bottom: var(--ft-gap-6);
}
.map-header {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    justify-content: space-between;
    gap: var(--ft-gap-2);
    margin-bottom: var(--ft-gap-2);
}
.map-header h3 {
    margin: 0;
}
.map-header__color {
    min-width: 200px;
}
.map-legend {
    display: flex;
    align-items: center;
    gap: var(--ft-gap-2);
    margin-top: var(--ft-gap-2);
    font-size: var(--font-size-small);
    color: var(--color-text-maxcontrast);
}
.map-legend__bar {
    flex: 0 1 200px;
    height: 8px;
    border-radius: var(--border-radius-pill);
}
.map-container {
    height: 400px;
    width: 100%;
    border-radius: var(--border-radius-container);
    overflow: hidden;
    border: 1px solid var(--color-border);
}
</style>
