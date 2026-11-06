// Camera pins for GPS-tagged photos on a Leaflet map, shared by the activity
// map and the heatmap. Photos close together on screen are grouped into one
// pin with a counter, regrouped after zooming; the popup steps through the
// group's thumbnails.
import L from 'leaflet'
import { generateUrl } from '@nextcloud/router'
import { t } from '@nextcloud/l10n'
import { icons } from './icons.js'
import { formatPhotoTime, formatDay, parseLocalDate } from './format.js'
import './styles/photoMarkers.css'

// Photos closer than this on screen share a pin
const CLUSTER_PIXELS = 36

const cameraSvg = icons.photo_camera
    .replace(/width="\d+"/, 'width="18"')
    .replace(/height="\d+"/, 'height="18"')
    .replace('<svg ', '<svg fill="currentColor" aria-hidden="true" ')

const escapeHtml = str => String(str).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]))

/**
 * @param {L.Map} map
 * @param {object} options
 * @param {(photos: object[], index: number) => void} options.onOpen a thumbnail was clicked
 * @param {(photo: object) => ({ text: string, href: string }|null)} [options.caption] extra link under the photo
 * @param {boolean} [options.showDate] show the date, not just the time (photos from many days)
 * @return {{ setPhotos(photos: object[]): void, remove(): void }}
 */
export function createPhotoLayer(map, { onOpen, caption = null, showDate = false }) {
    const layer = L.layerGroup().addTo(map)
    let photos = []

    const draw = () => {
        layer.clearLayers()
        for (const cluster of clusterByPixels(map, photos)) {
            layer.addLayer(clusterMarker(cluster, { onOpen, caption, showDate }))
        }
    }
    map.on('zoomend', draw)

    return {
        setPhotos(list) {
            photos = list
            draw()
        },
        remove() {
            map.off('zoomend', draw)
            layer.remove()
        },
    }
}

function clusterByPixels(map, photos) {
    const clusters = []
    for (const photo of photos) {
        const point = map.latLngToLayerPoint([photo.lat, photo.lon])
        const near = clusters.find(c => c.point.distanceTo(point) <= CLUSTER_PIXELS)
        if (near) near.photos.push(photo)
        else clusters.push({ point, photos: [photo] })
    }
    return clusters.map(c => c.photos)
}

function clusterMarker(cluster, { onOpen, caption, showDate }) {
    const lat = cluster.reduce((sum, p) => sum + p.lat, 0) / cluster.length
    const lon = cluster.reduce((sum, p) => sum + p.lon, 0) / cluster.length
    const count = cluster.length
    const multi = count > 1

    const icon = L.divIcon({
        html: multi
            ? `<div class="photo-pin photo-pin--multi">${cameraSvg}<span class="photo-pin__count">${count}</span></div>`
            : `<div class="photo-pin">${cameraSvg}</div>`,
        className: '',
        iconSize: [32, 32],
        iconAnchor: [16, 16],
        popupAnchor: [0, -18],
    })

    const items = cluster.map(photo => ({
        name: photo.name || '',
        thumbUrl: generateUrl(`/core/preview?fileId=${photo.fileId}&x=160&y=160&a=1`),
        time: photo.takenAt
            ? (showDate ? `${formatDay(parseLocalDate(photo.takenAt))} · ${formatPhotoTime(photo.takenAt)}` : formatPhotoTime(photo.takenAt))
            : '',
        caption: caption ? caption(photo) : null,
    }))
    const first = items[0]

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
                <span class="photo-carousel__time">${escapeHtml(first.time)}</span>
                ${multi ? `<span class="photo-carousel__counter">1 / ${count}</span>` : ''}
            </div>
            ${caption ? `<a class="photo-carousel__caption" href="${escapeHtml(first.caption?.href ?? '#')}">${escapeHtml(first.caption?.text ?? '')}</a>` : ''}
        </div>`

    const marker = L.marker([lat, lon], { icon }).bindPopup(popupHtml, { maxWidth: 200 })

    marker.on('popupopen', () => {
        const el = marker.getPopup().getElement()
        const img = el.querySelector('.photo-carousel__img')
        const prevBtn = el.querySelector('.photo-carousel__btn--prev')
        const nextBtn = el.querySelector('.photo-carousel__btn--next')
        const counterEl = el.querySelector('.photo-carousel__counter')
        const timeEl = el.querySelector('.photo-carousel__time')
        const captionEl = el.querySelector('.photo-carousel__caption')
        let idx = 0

        const refresh = () => {
            const p = items[idx]
            img.src = p.thumbUrl
            img.alt = p.name
            if (timeEl) timeEl.textContent = p.time
            if (counterEl) counterEl.textContent = `${idx + 1} / ${items.length}`
            if (prevBtn) prevBtn.style.display = idx === 0 ? 'none' : 'flex'
            if (nextBtn) nextBtn.style.display = idx === items.length - 1 ? 'none' : 'flex'
            if (captionEl && p.caption) {
                captionEl.textContent = p.caption.text
                captionEl.href = p.caption.href
            }
        }

        const open = () => onOpen(cluster, idx)
        img.addEventListener('click', open)
        img.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open() }
        })
        prevBtn?.addEventListener('click', (e) => {
            e.stopPropagation()
            if (idx > 0) { idx--; refresh() }
        })
        nextBtn?.addEventListener('click', (e) => {
            e.stopPropagation()
            if (idx < items.length - 1) { idx++; refresh() }
        })
    })
    return marker
}
