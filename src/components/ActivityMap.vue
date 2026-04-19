<template>
    <div class="activity-map-section">
        <h3>Route</h3>
        <div ref="mapEl" class="map-container"></div>
    </div>
</template>

<script>
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { generateUrl } from '@nextcloud/router'

export default {
    name: 'ActivityMap',
    props: {
        trackpoints: { type: Array, required: true },
        photos:      { type: Array, default: () => [] },
    },
    data() {
        return { map: null }
    },
    watch: {
        photos(newPhotos) {
            if (this.map && newPhotos.length > 0) {
                this.addPhotoMarkers(newPhotos)
            }
        },
    },
    mounted() {
        this.$nextTick(() => {
            this.initMap()
            this.registerPhotoOpener()
        })
    },
    methods: {
        initMap() {
            const coords = this.trackpoints
                .filter(tp => tp.lat !== null && tp.lon !== null)
                .map(tp => [tp.lat, tp.lon])

            if (coords.length === 0) return

            this.map = L.map(this.$refs.mapEl)

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19,
                referrerPolicy: 'strict-origin-when-cross-origin',
            }).addTo(this.map)

            const polyline = L.polyline(coords, {
                color: '#0082c9',
                weight: 3,
                opacity: 0.85,
            }).addTo(this.map)

            this.map.fitBounds(polyline.getBounds(), { padding: [20, 20] })

            // Start marker
            L.circleMarker(coords[0], {
                radius: 8, color: '#22c55e', fillColor: '#22c55e', fillOpacity: 1,
            }).bindTooltip('Start').addTo(this.map)

            // End marker
            L.circleMarker(coords[coords.length - 1], {
                radius: 8, color: '#ef4444', fillColor: '#ef4444', fillOpacity: 1,
            }).bindTooltip('Finish').addTo(this.map)

            if (this.photos.length > 0) {
                this.addPhotoMarkers(this.photos)
            }
        },
        addPhotoMarkers(photos) {
            const clusters = this.clusterPhotos(photos, 50)

            for (const cluster of clusters) {
                const lat = cluster.reduce((sum, p) => sum + p.lat, 0) / cluster.length
                const lon = cluster.reduce((sum, p) => sum + p.lon, 0) / cluster.length

                const count = cluster.length
                const icon = L.divIcon({
                    html: count > 1
                        ? `<div class="photo-pin photo-pin--multi">📷<span class="photo-pin__count">${count}</span></div>`
                        : `<div class="photo-pin">📷</div>`,
                    className: '',
                    iconSize: [32, 32],
                    iconAnchor: [16, 16],
                    popupAnchor: [0, -18],
                })

                const items = cluster.map(photo => {
                    const thumbUrl = generateUrl(`/core/preview?fileId=${photo.fileId}&x=120&y=120&a=1`)
                    const time     = photo.takenAt
                        ? new Date(photo.takenAt.replace(' ', 'T')).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })
                        : ''
                    const encodedName = encodeURIComponent(photo.name || '')
                    return `
                        <div class="photo-popup__item" style="cursor:pointer"
                             data-fileid="${photo.fileId}" data-name="${encodedName}"
                             onclick="window.__fitOpenPhoto(this)">
                            <img src="${thumbUrl}" width="120" height="120" style="object-fit:cover;border-radius:4px;display:block;">
                            ${time ? `<div class="photo-popup__time">${time}</div>` : ''}
                        </div>`
                }).join('')

                // 126 = 120px thumb + 6px gap; 30px covers Leaflet's internal padding
                const popupWidth = Math.min(count, 3) * 126 + 30
                L.marker([lat, lon], { icon })
                    .bindPopup(`<div class="photo-popup">${items}</div>`, { maxWidth: popupWidth })
                    .addTo(this.map)
            }
        },
        registerPhotoOpener() {
            window.__fitOpenPhoto = (el) => {
                document.dispatchEvent(new CustomEvent('fit-photo-open', {
                    detail: {
                        fileId: el.dataset.fileid,
                        name: decodeURIComponent(el.dataset.name || ''),
                    },
                }))
            }
        },
        clusterPhotos(photos, thresholdMeters) {
            const clusters = []
            const used = new Set()
            for (let i = 0; i < photos.length; i++) {
                if (used.has(i)) continue
                const cluster = [photos[i]]
                used.add(i)
                for (let j = i + 1; j < photos.length; j++) {
                    if (used.has(j)) continue
                    if (this.haversineJs(photos[i].lat, photos[i].lon, photos[j].lat, photos[j].lon) <= thresholdMeters) {
                        cluster.push(photos[j])
                        used.add(j)
                    }
                }
                clusters.push(cluster)
            }
            return clusters
        },
        haversineJs(lat1, lon1, lat2, lon2) {
            const R = 6371000
            const dLat = (lat2 - lat1) * Math.PI / 180
            const dLon = (lon2 - lon1) * Math.PI / 180
            const a = Math.sin(dLat / 2) ** 2 + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLon / 2) ** 2
            return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))
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

<!-- Non-scoped: Leaflet injects popup/marker HTML outside the Vue component shadow -->
<style>
.photo-pin {
    font-size: 22px;
    line-height: 32px;
    text-align: center;
    filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.4));
    cursor: pointer;
    user-select: none;
    position: relative;
}
.photo-pin--multi {
    display: inline-block;
}
.photo-pin__count {
    position: absolute;
    top: -4px;
    right: -6px;
    background: #0082c9;
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    border-radius: 8px;
    padding: 0 4px;
    line-height: 14px;
    min-width: 14px;
    text-align: center;
}
.photo-popup {
    display: flex;
    flex-direction: row;
    flex-wrap: wrap;
    gap: 6px;
    justify-content: center;
    font-size: 12px;
    padding: 2px;
}
.photo-popup__item {
    flex: 0 0 auto;
    text-align: center;
}
.photo-popup__time {
    margin: 3px 0 0;
    color: #666;
}
</style>
