<template>
    <div class="activity-map-section">
        <h3>{{ t('fit_tracker', 'Route') }}</h3>
        <div ref="mapEl" class="map-container"></div>
    </div>
</template>

<script>
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { generateUrl } from '@nextcloud/router'
import { t } from '@nextcloud/l10n'
import { icons } from '../icons.js'
import { formatTime } from '../format.js'

// Icon markup for Leaflet's divIcon (rendered outside Vue)
const cameraSvg = icons.photo_camera
    .replace(/width="\d+"/, 'width="18"')
    .replace(/height="\d+"/, 'height="18"')
    .replace('<svg ', '<svg fill="currentColor" aria-hidden="true" ')
const escapeHtml = str => String(str).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]))

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

            const polyline = L.polyline(coords, {
                color: '#0082c9',
                weight: 3,
                opacity: 0.85,
            }).addTo(this.map)

            this.map.fitBounds(polyline.getBounds(), { padding: [20, 20] })

            // Start marker
            L.circleMarker(coords[0], {
                radius: 8, color: '#22c55e', fillColor: '#22c55e', fillOpacity: 1,
            }).bindTooltip(t('fit_tracker', 'Start')).addTo(this.map)

            // End marker
            L.circleMarker(coords[coords.length - 1], {
                radius: 8, color: '#ef4444', fillColor: '#ef4444', fillOpacity: 1,
            }).bindTooltip(t('fit_tracker', 'Finish')).addTo(this.map)

            if (this.photos.length > 0) {
                this.addPhotoMarkers(this.photos)
            }
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
        addPhotoMarkers(photos) {
            const clusters = this.clusterPhotos(photos, 50)

            for (const cluster of clusters) {
                const lat = cluster.reduce((sum, p) => sum + p.lat, 0) / cluster.length
                const lon = cluster.reduce((sum, p) => sum + p.lon, 0) / cluster.length

                const count = cluster.length
                const icon = L.divIcon({
                    html: count > 1
                        ? `<div class="photo-pin photo-pin--multi">${cameraSvg}<span class="photo-pin__count">${count}</span></div>`
                        : `<div class="photo-pin">${cameraSvg}</div>`,
                    className: '',
                    iconSize: [32, 32],
                    iconAnchor: [16, 16],
                    popupAnchor: [0, -18],
                })

                const photoData = cluster.map(photo => ({
                    fileId: photo.fileId,
                    name: photo.name || '',
                    thumbUrl: generateUrl(`/core/preview?fileId=${photo.fileId}&x=160&y=160&a=1`),
                    time: photo.takenAt ? formatTime(photo.takenAt) : '',
                }))

                const first   = photoData[0]
                const multi   = count > 1

                const popupHtml = `
                    <div class="photo-popup">
                        <div class="photo-carousel">
                            <img class="photo-carousel__img" src="${first.thumbUrl}" width="160" height="160" alt="${escapeHtml(first.name)}" title="${escapeHtml(t('fit_tracker', 'Open photo'))}" tabindex="0" role="button">
                            ${multi ? `
                            <button class="photo-carousel__btn photo-carousel__btn--prev" style="display:none" aria-label="${escapeHtml(t('fit_tracker', 'Previous photo'))}">${icons.chevron_left}</button>
                            <button class="photo-carousel__btn photo-carousel__btn--next" aria-label="${escapeHtml(t('fit_tracker', 'Next photo'))}">${icons.chevron_right}</button>
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
                        img.alt = p.name
                        if (timeEl)    timeEl.textContent    = p.time
                        if (counterEl) counterEl.textContent = `${idx + 1} / ${photoData.length}`
                        if (prevBtn)   prevBtn.style.display = idx === 0 ? 'none' : 'flex'
                        if (nextBtn)   nextBtn.style.display = idx === photoData.length - 1 ? 'none' : 'flex'
                    }

                    const openPhoto = () => {
                        document.dispatchEvent(new CustomEvent('fit-photo-open', {
                            detail: { fileId: photoData[idx].fileId, name: photoData[idx].name },
                        }))
                    }
                    img.addEventListener('click', openPhoto)
                    img.addEventListener('keydown', (e) => {
                        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openPhoto() }
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
.activity-map-section {
    margin-bottom: var(--ft-gap-6);
}
.map-container {
    height: 400px;
    width: 100%;
    border-radius: var(--border-radius-container);
    overflow: hidden;
    border: 1px solid var(--color-border);
}
</style>

<!-- Non-scoped: Leaflet injects popup/marker HTML outside the Vue component -->
<style>
.activity-map-section .leaflet-popup-content-wrapper,
.activity-map-section .leaflet-popup-tip {
    background: var(--color-main-background);
    color: var(--color-main-text);
}
.photo-pin {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: var(--border-radius-pill);
    background: var(--color-primary-element);
    color: var(--color-primary-element-text);
    border: 2px solid var(--color-main-background);
    box-shadow: 0 0 4px var(--color-box-shadow);
    cursor: pointer;
    user-select: none;
    position: relative;
    box-sizing: border-box;
}
.photo-pin__count {
    position: absolute;
    top: -6px;
    right: -8px;
    background: var(--color-main-background);
    color: var(--color-main-text);
    border: 1px solid var(--color-border-maxcontrast);
    font-size: var(--font-size-small);
    font-weight: bold;
    border-radius: var(--border-radius-pill);
    padding: 0 var(--ft-gap-1);
    line-height: 16px;
    min-width: 16px;
    text-align: center;
}
.photo-popup {
    font-size: var(--font-size-small);
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
    border-radius: var(--border-radius-small);
    display: block;
    cursor: pointer;
}
.photo-carousel__img:focus-visible {
    outline: 2px solid var(--color-primary-element);
    outline-offset: 2px;
}
/* Overlaid on the photo, so a fixed dark scrim keeps the icon legible */
.photo-carousel__btn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: var(--default-clickable-area);
    height: var(--default-clickable-area);
    min-height: 0;
    margin: 0;
    padding: 0;
    border: none;
    border-radius: var(--border-radius-pill);
    background-color: rgba(0, 0, 0, 0.5);
    color: #fff;
    fill: currentColor;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}
.photo-carousel__btn svg {
    width: 20px;
    height: 20px;
}
.photo-carousel__btn:hover,
.photo-carousel__btn:focus-visible { background-color: rgba(0, 0, 0, 0.7); }
.photo-carousel__btn--prev { left: var(--ft-gap-1); }
.photo-carousel__btn--next { right: var(--ft-gap-1); }
.photo-carousel__footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: var(--ft-gap-1);
    color: var(--color-text-maxcontrast);
}
.photo-carousel__counter { font-weight: bold; }
</style>
