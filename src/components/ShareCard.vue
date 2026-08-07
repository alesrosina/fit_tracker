<template>
    <NcModal size="normal" name="Share activity" @close="$emit('close')">
        <div class="share-card">
            <canvas ref="canvasEl" class="share-card__canvas" :width="CANVAS_W" :height="CANVAS_H"></canvas>
            <div class="share-card__actions">
                <NcButton type="primary" @click="share">Share</NcButton>
            </div>
        </div>
    </NcModal>
</template>

<script>
import { NcModal, NcButton } from '@nextcloud/vue'
import { sportIcon, sportLabel, sportColor, SPEED_NOT_PACE_SPORTS } from '../sports.js'

const CANVAS_W = 1080
const CANVAS_H = 1920

export default {
    name: 'ShareCard',
    components: { NcModal, NcButton },
    props: {
        activity:    { type: Object, required: true },
        trackpoints: { type: Array, default: () => [] },
    },
    emits: ['close'],
    data() {
        return { CANVAS_W, CANVAS_H }
    },
    mounted() {
        this.draw()
    },
    methods: {
        async draw() {
            const ctx = this.$refs.canvasEl.getContext('2d')
            const color = sportColor(this.activity.sport)

            this.drawBackground(ctx, color)
            this.drawHeader(ctx)

            const coords = this.trackpoints
                .filter(tp => tp.lat !== null && tp.lon !== null)
                .map(tp => [tp.lat, tp.lon])

            let statsTop = 380
            if (coords.length > 1) {
                const boxX = 60, boxY = 350, boxW = CANVAS_W - 120, boxH = 1080
                const mapped = await this.drawMap(ctx, coords, color, boxX, boxY, boxW, boxH)
                if (!mapped) this.drawRouteOnly(ctx, coords, color, boxX, boxY, boxW, boxH)
                statsTop = boxY + boxH + 70
            }

            this.drawStats(ctx, color, statsTop)
        },
        // Loads real OpenStreetMap raster tiles and composites them behind the
        // route. tile.openstreetmap.org sends Access-Control-Allow-Origin: *,
        // so tiles loaded with crossOrigin='anonymous' don't taint the canvas
        // and toBlob() still works. Falls back to drawRouteOnly (plain background)
        // if tiles can't be loaded (offline, blocked, etc).
        async drawMap(ctx, coords, color, boxX, boxY, boxW, boxH) {
            const pad = 60
            const innerW = boxW - pad * 2
            const innerH = boxH - pad * 2
            const zoom = this.pickZoom(coords, innerW, innerH)

            const project = ([lat, lon]) => [this.lonToX(lon, zoom) * 256, this.latToY(lat, zoom) * 256]
            const px = coords.map(project)
            const xs = px.map(p => p[0]), ys = px.map(p => p[1])
            const centerX = (Math.min(...xs) + Math.max(...xs)) / 2
            const centerY = (Math.min(...ys) + Math.max(...ys)) / 2
            const toCanvas = ([x, y]) => [
                boxX + boxW / 2 + (x - centerX),
                boxY + boxH / 2 + (y - centerY),
            ]

            const maxTile = Math.pow(2, zoom) - 1
            const minTX = Math.max(0, Math.floor((centerX - boxW / 2) / 256) - 1)
            const maxTX = Math.min(maxTile, Math.floor((centerX + boxW / 2) / 256) + 1)
            const minTY = Math.max(0, Math.floor((centerY - boxH / 2) / 256) - 1)
            const maxTY = Math.min(maxTile, Math.floor((centerY + boxH / 2) / 256) + 1)

            const subdomains = ['a', 'b', 'c']
            let si = 0
            const tiles = []
            for (let tx = minTX; tx <= maxTX; tx++) {
                for (let ty = minTY; ty <= maxTY; ty++) {
                    const sub = subdomains[si++ % subdomains.length]
                    tiles.push({ tx, ty, url: `https://${sub}.tile.openstreetmap.org/${zoom}/${tx}/${ty}.png` })
                }
            }

            const loaded = await Promise.all(tiles.map(t => this.loadTileImage(t.url).then(img => ({ ...t, img }))))
            const ok = loaded.filter(t => t.img)
            if (ok.length === 0) return false

            ctx.save()
            this.roundedRect(ctx, boxX, boxY, boxW, boxH, 32)
            ctx.clip()
            ctx.fillStyle = '#1a1d23'
            ctx.fillRect(boxX, boxY, boxW, boxH)
            for (const t of ok) {
                const [x, y] = toCanvas([t.tx * 256, t.ty * 256])
                ctx.drawImage(t.img, x, y, 256, 256)
            }
            // Dark wash so the tiles sit well with the card's dark theme and the route pops.
            ctx.fillStyle = 'rgba(10,12,16,0.35)'
            ctx.fillRect(boxX, boxY, boxW, boxH)
            ctx.restore()

            this.roundedRect(ctx, boxX, boxY, boxW, boxH, 32)
            ctx.strokeStyle = 'rgba(255,255,255,0.15)'
            ctx.lineWidth = 2
            ctx.stroke()

            const points = coords.map(c => toCanvas(project(c)))
            this.strokeRoute(ctx, points, color)

            ctx.textAlign = 'right'
            ctx.fillStyle = 'rgba(255,255,255,0.55)'
            ctx.font = '400 16px -apple-system, sans-serif'
            ctx.fillText('© OpenStreetMap contributors', boxX + boxW - 16, boxY + boxH - 16)

            return true
        },
        loadTileImage(url) {
            return new Promise(resolve => {
                const img = new Image()
                img.crossOrigin = 'anonymous'
                // Nextcloud sends a strict Referrer-Policy by default, which strips the
                // referer OSM's tile usage policy requires — same override ActivityMap.vue
                // applies on its Leaflet tile layer.
                img.referrerPolicy = 'strict-origin-when-cross-origin'
                img.onload = () => resolve(img)
                img.onerror = () => resolve(null)
                img.src = url
            })
        },
        pickZoom(coords, w, h) {
            const lats = coords.map(c => c[0]), lons = coords.map(c => c[1])
            const minLat = Math.min(...lats), maxLat = Math.max(...lats)
            const minLon = Math.min(...lons), maxLon = Math.max(...lons)
            for (let z = 18; z >= 1; z--) {
                const spanX = (this.lonToX(maxLon, z) - this.lonToX(minLon, z)) * 256
                const spanY = (this.latToY(minLat, z) - this.latToY(maxLat, z)) * 256
                if (spanX <= w && spanY <= h) return z
            }
            return 1
        },
        lonToX(lon, zoom) {
            return (lon + 180) / 360 * Math.pow(2, zoom)
        },
        latToY(lat, zoom) {
            const rad = lat * Math.PI / 180
            return (1 - Math.log(Math.tan(rad) + 1 / Math.cos(rad)) / Math.PI) / 2 * Math.pow(2, zoom)
        },
        strokeRoute(ctx, points, color) {
            ctx.save()
            ctx.shadowColor = color
            ctx.shadowBlur = 16
            ctx.strokeStyle = color
            ctx.lineWidth = 8
            ctx.lineCap = 'round'
            ctx.lineJoin = 'round'
            ctx.beginPath()
            points.forEach(([x, y], i) => i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y))
            ctx.stroke()
            ctx.restore()

            this.dot(ctx, points[0], '#22c55e')
            this.dot(ctx, points[points.length - 1], '#ef4444')
        },
        drawBackground(ctx, color) {
            const grad = ctx.createLinearGradient(0, 0, CANVAS_W, CANVAS_H)
            grad.addColorStop(0, this.shade(color, -0.1))
            grad.addColorStop(1, '#0f1115')
            ctx.fillStyle = grad
            ctx.fillRect(0, 0, CANVAS_W, CANVAS_H)
        },
        drawHeader(ctx) {
            const cx = CANVAS_W / 2
            ctx.textAlign = 'center'

            ctx.font = '120px sans-serif'
            ctx.fillText(sportIcon(this.activity.sport), cx, 175)

            ctx.fillStyle = '#ffffff'
            ctx.font = '600 44px -apple-system, "Segoe UI", Roboto, sans-serif'
            ctx.fillText(sportLabel(this.activity.sport).toUpperCase(), cx, 250)

            ctx.fillStyle = 'rgba(255,255,255,0.7)'
            ctx.font = '400 30px -apple-system, "Segoe UI", Roboto, sans-serif'
            ctx.fillText(this.formatDate(this.activity.startTime), cx, 295)
        },
        // Fallback when tiles can't be loaded (offline, blocked) — same route
        // rendering as drawMap, just without a basemap underneath.
        drawRouteOnly(ctx, coords, color, boxX, boxY, boxW, boxH) {
            this.roundedRect(ctx, boxX, boxY, boxW, boxH, 32)
            ctx.fillStyle = 'rgba(255,255,255,0.05)'
            ctx.fill()
            ctx.strokeStyle = 'rgba(255,255,255,0.12)'
            ctx.lineWidth = 2
            ctx.stroke()

            const pad = 70
            const points = this.projectRoute(coords, boxW - pad * 2, boxH - pad * 2)
                .map(([x, y]) => [x + boxX + pad, y + boxY + pad])

            this.strokeRoute(ctx, points, color)
        },
        dot(ctx, [x, y], fill) {
            ctx.beginPath()
            ctx.arc(x, y, 12, 0, Math.PI * 2)
            ctx.fillStyle = fill
            ctx.fill()
            ctx.lineWidth = 4
            ctx.strokeStyle = '#ffffff'
            ctx.stroke()
        },
        projectRoute(coords, boxW, boxH) {
            const lats = coords.map(c => c[0])
            const lons = coords.map(c => c[1])
            const minLat = Math.min(...lats), maxLat = Math.max(...lats)
            const minLon = Math.min(...lons), maxLon = Math.max(...lons)
            const cosLat = Math.cos((minLat + maxLat) / 2 * Math.PI / 180)

            const xs = coords.map(([, lon]) => lon * cosLat)
            const ys = coords.map(([lat]) => lat)
            const minX = Math.min(...xs), maxX = Math.max(...xs)
            const minY = Math.min(...ys), maxY = Math.max(...ys)
            const spanX = Math.max(maxX - minX, 1e-9)
            const spanY = Math.max(maxY - minY, 1e-9)
            const scale = Math.min(boxW / spanX, boxH / spanY)
            const midX = (minX + maxX) / 2
            const midY = (minY + maxY) / 2

            return coords.map(([lat, lon]) => {
                const x = lon * cosLat
                const y = lat
                return [
                    boxW / 2 + (x - midX) * scale,
                    boxH / 2 - (y - midY) * scale,
                ]
            })
        },
        drawStats(ctx, color, top) {
            const cx = CANVAS_W / 2
            const a = this.activity

            if (a.distance) {
                ctx.textAlign = 'center'
                ctx.fillStyle = 'rgba(255,255,255,0.6)'
                ctx.font = '600 30px -apple-system, "Segoe UI", Roboto, sans-serif'
                ctx.fillText('DISTANCE', cx, top)

                ctx.fillStyle = '#ffffff'
                ctx.font = '700 130px -apple-system, "Segoe UI", Roboto, sans-serif'
                ctx.fillText(this.formatDistance(a.distance), cx, top + 140)
            }

            const secondary = this.secondaryStats()
            if (secondary.length === 0) return

            const rowY = top + 260
            const colW = CANVAS_W / secondary.length
            secondary.forEach((stat, i) => {
                const x = colW * i + colW / 2
                ctx.textAlign = 'center'
                ctx.fillStyle = 'rgba(255,255,255,0.6)'
                ctx.font = '600 26px -apple-system, "Segoe UI", Roboto, sans-serif'
                ctx.fillText(stat.label, x, rowY)

                ctx.fillStyle = color
                ctx.font = '700 52px -apple-system, "Segoe UI", Roboto, sans-serif'
                ctx.fillText(stat.value, x, rowY + 60)
            })
        },
        secondaryStats() {
            const a = this.activity
            const stats = []
            if (a.duration) stats.push({ label: 'DURATION', value: this.formatDuration(a.duration) })
            const paceOrSpeed = this.paceOrSpeed()
            if (paceOrSpeed) stats.push({ label: SPEED_NOT_PACE_SPORTS.includes(a.sport) ? 'AVG SPEED' : 'AVG PACE', value: paceOrSpeed })
            if (a.elevationGain) stats.push({ label: 'ELEVATION', value: `+${Math.round(a.elevationGain)} m` })
            if (a.avgHr) stats.push({ label: 'AVG HR', value: `${a.avgHr} bpm` })
            return stats.slice(0, 3)
        },
        roundedRect(ctx, x, y, w, h, r) {
            ctx.beginPath()
            ctx.moveTo(x + r, y)
            ctx.arcTo(x + w, y, x + w, y + h, r)
            ctx.arcTo(x + w, y + h, x, y + h, r)
            ctx.arcTo(x, y + h, x, y, r)
            ctx.arcTo(x, y, x + w, y, r)
            ctx.closePath()
        },
        shade(hex, percent) {
            const num = parseInt(hex.replace('#', ''), 16)
            const clamp = v => Math.max(0, Math.min(255, v))
            const r = clamp((num >> 16) + Math.round(255 * percent))
            const g = clamp(((num >> 8) & 0x00ff) + Math.round(255 * percent))
            const b = clamp((num & 0x0000ff) + Math.round(255 * percent))
            return `rgb(${r},${g},${b})`
        },
        paceOrSpeed() {
            const a = this.activity
            let speed = a.avgSpeed || null
            if (!speed && a.distance && a.duration > 0) speed = a.distance / a.duration * 3600
            if (!speed) return null
            if (SPEED_NOT_PACE_SPORTS.includes(a.sport)) return `${speed.toFixed(1)} km/h`
            if (['gym', 'swimming', 'breathwork', 'meditation'].includes(a.sport)) return null
            const minPerKm = 60 / speed
            const m = Math.floor(minPerKm)
            const s = Math.round((minPerKm - m) * 60)
            return `${m}:${String(s).padStart(2, '0')} /km`
        },
        formatDate(raw) {
            if (!raw) return ''
            return new Date(raw).toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' })
        },
        formatDistance(km) {
            if (km < 1) return Math.round(km * 1000) + ' m'
            return km.toFixed(2) + ' km'
        },
        formatDuration(s) {
            const h = Math.floor(s / 3600)
            const m = Math.floor((s % 3600) / 60)
            const sec = s % 60
            if (h > 0) return `${h}:${String(m).padStart(2, '0')}:${String(sec).padStart(2, '0')}`
            return `${m}:${String(sec).padStart(2, '0')}`
        },
        filename() {
            return `${this.activity.sport || 'activity'}-${this.activity.startTime?.slice(0, 10) || ''}.png`
        },
        share() {
            this.$refs.canvasEl.toBlob(async blob => {
                const file = new File([blob], this.filename(), { type: 'image/png' })

                if (navigator.canShare && navigator.canShare({ files: [file] })) {
                    try {
                        await navigator.share({ files: [file], title: this.activity.name || 'Activity' })
                        return
                    } catch (e) {
                        if (e.name === 'AbortError') return
                        // Sharing failed for some other reason — fall back to downloading.
                    }
                }

                const url = URL.createObjectURL(blob)
                const link = document.createElement('a')
                link.href = url
                link.download = this.filename()
                link.click()
                URL.revokeObjectURL(url)
            }, 'image/png')
        },
    },
}
</script>

<style scoped>
.share-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 16px;
    padding: 16px;
}
.share-card__canvas {
    width: auto;
    height: auto;
    max-width: min(380px, 100%);
    max-height: 70vh;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
}
.share-card__actions {
    display: flex;
    justify-content: center;
}
</style>
