<template>
    <div class="activity-map-section">
        <h3>Route</h3>
        <div ref="mapEl" class="map-container"></div>
    </div>
</template>

<script>
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

export default {
    name: 'ActivityMap',
    props: {
        trackpoints: { type: Array, required: true },
    },
    mounted() {
        this.$nextTick(() => this.initMap())
    },
    methods: {
        initMap() {
            const coords = this.trackpoints
                .filter(tp => tp.lat !== null && tp.lon !== null)
                .map(tp => [tp.lat, tp.lon])

            if (coords.length === 0) return

            const map = L.map(this.$refs.mapEl)

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19,
                referrerPolicy: 'strict-origin-when-cross-origin',
            }).addTo(map)

            const polyline = L.polyline(coords, {
                color: '#0082c9',
                weight: 3,
                opacity: 0.85,
            }).addTo(map)

            map.fitBounds(polyline.getBounds(), { padding: [20, 20] })

            // Start marker
            L.circleMarker(coords[0], {
                radius: 8, color: '#22c55e', fillColor: '#22c55e', fillOpacity: 1,
            }).bindTooltip('Start').addTo(map)

            // End marker
            L.circleMarker(coords[coords.length - 1], {
                radius: 8, color: '#ef4444', fillColor: '#ef4444', fillOpacity: 1,
            }).bindTooltip('Finish').addTo(map)
        },
    },
}
</script>

<style scoped>
.map-container {
    height: 400px;
    width: 100%;
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid var(--color-border);
}
</style>
