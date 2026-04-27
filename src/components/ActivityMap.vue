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
        this.$nextTick(() => this.initMap())
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

                const photoData = cluster.map(photo => ({
                    fileId: photo.fileId,
                    name: photo.name || '',
                    thumbUrl: generateUrl(`/core/preview?fileId=${photo.fileId}&x=160&y=160&a=1`),
                    time: photo.takenAt
                        ? new Date(photo.takenAt.replace(' ', 'T')).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })
                        : '',
                }))

                const first   = photoData[0]
                const multi   = count > 1

                const popupHtml = `
                    <div class="photo-popup">
                        <div class="photo-carousel">
                            <img class="photo-carousel__img" src="${first.thumbUrl}" width="160" height="160">
                            ${multi ? `
                            <button class="button-vue photo-carousel__btn photo-carousel__btn--prev" style="display:none">&#8249;</button>
                            <button class="button-vue photo-carousel__btn photo-carousel__btn--next">&#8250;</button>
                            ` : ''}
                        </div>
                        <div class="photo-carousel__footer">
                            <span class="photo-carousel__time">${first.time}</span>
                            ${multi ? `<span class="photo-carousel__counter">1 / ${count}</span>` : ''}
                        </div>
                    </div>`

                const marker = L.marker([lat, lon], { icon })
                    .bindPopup(popupHtml, { maxWidth: 200 })
                    .addTo(this.map)

                marker.on('popupopen', () => {
                    const el        = marker.getPopup().getElement()
                    const img       = el.querySelector('.photo-carousel__img')
                    const prevBtn   = el.querySelector('.photo-carousel__btn--prev')
                    const nextBtn   = el.querySelector('.photo-carousel__btn--next')
                    const counterEl = el.querySelector('.photo-carousel__counter')
                    const timeEl    = el.querySelector('.photo-carousel__time')
                    let idx = 0

                    const refresh = () => {
                        const p = photoData[idx]
                        img.src = p.thumbUrl
                        if (timeEl)    timeEl.textContent    = p.time
                        if (counterEl) counterEl.textContent = `${idx + 1} / ${photoData.length}`
                        if (prevBtn)   prevBtn.style.display = idx === 0 ? 'none' : 'flex'
                        if (nextBtn)   nextBtn.style.display = idx === photoData.length - 1 ? 'none' : 'flex'
                    }

                    img.addEventListener('click', () => {
                        document.dispatchEvent(new CustomEvent('fit-photo-open', {
                            detail: { fileId: photoData[idx].fileId, name: photoData[idx].name },
                        }))
                    })

                    if (prevBtn) {
                        prevBtn.addEventListener('click', (e) => {
                            e.stopPropagation()
                            if (idx > 0) { idx--; refresh() }
                        })
                    }
                    if (nextBtn) {
                        nextBtn.addEventListener('click', (e) => {
                            e.stopPropagation()
                            if (idx < photoData.length - 1) { idx++; refresh() }
                        })
                    }
                })
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
    font-size: 12px;
    padding: 2px;
    width: 160px;
}
.photo-carousel {
    position: relative;
    width: 160px;
    height: 160px;
}
.photo-carousel__img {
    width: 160px;
    height: 160px;
    object-fit: cover;
    border-radius: 4px;
    display: block;
    cursor: pointer;
}
.photo-carousel__btn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background-color: rgba(0, 0, 0, 0.45);
    border-radius: 50%;
    color: #fff;
    border: none;
    width: 26px;
    height: 26px;
    font-size: 20px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    line-height: 1;
}
.photo-carousel__btn:hover { background: rgba(0, 0, 0, 0.65); }
.photo-carousel__btn--prev { left: 4px; }
.photo-carousel__btn--next { right: 4px; }
.photo-carousel__footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 4px;
    color: #666;
}
.photo-carousel__counter { font-weight: 600; }
</style>
