<template>
    <div class="ft-page heatmap-page">
        <h2 class="ft-page-title">{{ t('fit_tracker', 'Heatmap') }}</h2>

        <div class="ft-toolbar">
            <NcSelect
                v-model="sportOption"
                class="heatmap-page__select"
                :options="sportOptions"
                :input-label="t('fit_tracker', 'Sport')"
                :clearable="false"
                label="label"
            />
            <NcCheckboxRadioSwitch v-model="bySport" type="switch">
                {{ t('fit_tracker', 'Colour by sport') }}
            </NcCheckboxRadioSwitch>
            <NcCheckboxRadioSwitch v-model="showPhotos" type="switch">
                {{ t('fit_tracker', 'Show photos') }}
            </NcCheckboxRadioSwitch>
            <span v-if="!loading" class="heatmap-page__count">
                {{ n('fit_tracker', '%n route', '%n routes', visibleTracks.length) }}<template v-if="showPhotos">
                    · {{ n('fit_tracker', '%n photo', '%n photos', visiblePhotos.length) }}</template>
                <NcLoadingIcon v-if="showPhotos && photosLoading" :size="16" :name="t('fit_tracker', 'Looking for photos along the routes')" />
            </span>
        </div>

        <NcNoteCard v-if="analyzing" type="info">
            {{ n('fit_tracker', 'Preparing the route of %n older activity…', 'Preparing the routes of %n older activities…', analyzing) }}
        </NcNoteCard>
        <LoadError
            v-if="error"
            :heading="t('fit_tracker', 'Could not load the routes')"
            :message="t('fit_tracker', 'Check your connection and try again.')"
            @retry="load"
        />
        <NcEmptyContent
            v-else-if="!loading && tracks.length === 0"
            :name="t('fit_tracker', 'No routes yet')"
            :description="t('fit_tracker', 'Activities recorded with GPS show up here, all on one map.')"
        >
            <template #icon>
                <NcIconSvgWrapper :svg="icons.map" />
            </template>
        </NcEmptyContent>
        <div v-show="tracks.length > 0" ref="mapEl" class="heatmap-page__map" />
        <p v-if="tracks.length > 0" class="ft-panel__hint">
            {{ t('fit_tracker', 'Routes overlap, so the brighter a line, the more often you were there. Click a route to open the activity. Zoom with Ctrl + scroll.') }}
            <template v-if="showPhotos">
                {{ t('fit_tracker', 'Camera pins are GPS-tagged photos taken along a route during the activity.') }}
            </template>
        </p>

        <PhotoLightbox
            v-if="lightbox"
            :photos="lightbox.photos"
            :start-index="lightbox.index"
            @close="lightbox = null"
        />
    </div>
</template>

<script>
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { t } from '@nextcloud/l10n'
import { defineAsyncComponent } from 'vue'
import { NcCheckboxRadioSwitch, NcEmptyContent, NcIconSvgWrapper, NcLoadingIcon, NcNoteCard, NcSelect } from '@nextcloud/vue'
import LoadError from './LoadError.vue'
import { icons } from '../icons.js'
import { sportColor, sportLabel } from '../sports.js'
import { navData } from '../store/navData.js'
import { ensureAnalyzed } from '../store/analysis.js'
import { createPhotoLayer } from '../photoMarkers.js'

const HEAT_COLOR = '#e8590c'

// Every GPS route on one map, with the photos taken along them. Tracks are
// the ~300-point versions stored at import (AnalysisService.php), so even
// hundreds of activities stay light; photos are matched on the server in
// batches (RoutePhotoService.php) and appear as they are found.
export default {
    name: 'HeatmapPage',
    components: {
        LoadError, NcCheckboxRadioSwitch, NcEmptyContent, NcIconSvgWrapper, NcLoadingIcon, NcNoteCard, NcSelect,
        PhotoLightbox: defineAsyncComponent(() => import('./PhotoLightbox.vue')),
    },
    data() {
        return {
            icons,
            tracks: [],
            loading: true,
            error: false,
            analyzing: 0,
            sportOption: null,
            bySport: false,
            showPhotos: true,
            photos: [],
            photosLoading: false,
            // { photos, index } while the photo viewer is open
            lightbox: null,
        }
    },
    computed: {
        sportOptions() {
            const sports = [...new Set(this.tracks.map(tr => tr.sport))].sort()
            return [{ value: '', label: t('fit_tracker', 'All sports') }, ...sports.map(s => ({ value: s, label: sportLabel(s) }))]
        },
        visibleTracks() {
            const sport = this.sportOption?.value
            return sport ? this.tracks.filter(tr => tr.sport === sport) : this.tracks
        },
        visiblePhotos() {
            const sport = this.sportOption?.value
            return sport ? this.photos.filter(p => p.sport === sport) : this.photos
        },
        trackNames() {
            return new Map(this.tracks.map(tr => [tr.id, navData.activities.find(a => a.id === tr.id)?.name ?? tr.name]))
        },
    },
    watch: {
        // After the DOM update: the map container is hidden until there are tracks
        visibleTracks() { this.$nextTick(() => this.draw(true)) },
        bySport() { this.draw(false) },
        visiblePhotos() { this.drawPhotos() },
        showPhotos() { this.drawPhotos() },
    },
    created() {
        // Leaflet objects stay out of data(): they must not become reactive proxies
        this.map = null
        this.layer = null
        this.photoLayer = null
        this.destroyed = false
    },
    mounted() {
        this.sportOption = this.sportOptions[0]
        this.load()
    },
    beforeUnmount() {
        // Stops loadPhotos() from asking for further batches
        this.destroyed = true
        window.removeEventListener('keydown', this.onKey)
        window.removeEventListener('keyup', this.onKey)
        this.map?.remove()
    },
    methods: {
        async load() {
            this.loading = true
            this.error = false
            try {
                await ensureAnalyzed(remaining => { this.analyzing = remaining })
                this.analyzing = 0
                const { data } = await axios.get(generateUrl('/apps/fit_tracker/api/stats/heatmap'))
                this.tracks = data
            } catch (e) {
                this.error = true
            } finally {
                this.loading = false
                this.analyzing = 0
            }
            this.$nextTick(() => this.draw(true))
            if (this.tracks.length) this.loadPhotos()
        },
        // Batches until the server has gone through every activity
        async loadPhotos() {
            this.photosLoading = true
            const seen = new Set()
            let cursor = 0
            try {
                while (cursor !== null && !this.destroyed) {
                    const { data } = await axios.get(generateUrl('/apps/fit_tracker/api/stats/photos'), { params: { cursor } })
                    // A photo can lie on two routes (e.g. the same ride imported twice); show it once
                    const fresh = data.photos.filter(p => !seen.has(p.fileId))
                    fresh.forEach(p => seen.add(p.fileId))
                    if (fresh.length) this.photos = [...this.photos, ...fresh]
                    cursor = data.next
                }
            } catch (e) {
                // Photos are extra; the routes work without them
            } finally {
                this.photosLoading = false
            }
        },
        drawPhotos() {
            if (!this.map) return
            if (!this.photoLayer) {
                this.photoLayer = createPhotoLayer(this.map, {
                    showDate: true,
                    onOpen: (photos, index) => { this.lightbox = { photos, index } },
                    caption: photo => ({ text: this.trackNames.get(photo.activityId) ?? '', href: '#/activity/' + photo.activityId }),
                })
            }
            this.photoLayer.setPhotos(this.showPhotos ? this.visiblePhotos : [])
        },
        initMap() {
            this.map = L.map(this.$refs.mapEl, { scrollWheelZoom: false, preferCanvas: true })
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19,
                referrerPolicy: 'strict-origin-when-cross-origin',
            }).addTo(this.map)
            // Same as the activity map: zoom with Ctrl/Cmd + scroll, so the page still scrolls
            this.onKey = e => {
                if (e.key === 'Control' || e.key === 'Meta') {
                    e.type === 'keydown' ? this.map.scrollWheelZoom.enable() : this.map.scrollWheelZoom.disable()
                }
            }
            window.addEventListener('keydown', this.onKey)
            window.addEventListener('keyup', this.onKey)
        },
        draw(fit) {
            if (!this.tracks.length || !this.$refs.mapEl) return
            // Leaflet needs a laid-out container; try again on the next frame until it has one
            if (this.$refs.mapEl.clientHeight === 0) {
                requestAnimationFrame(() => this.draw(fit))
                return
            }
            if (!this.map) this.initMap()
            this.layer?.remove()
            const group = L.featureGroup()
            for (const track of this.visibleTracks) {
                const line = L.polyline(track.track, {
                    color: this.bySport ? sportColor(track.sport) : HEAT_COLOR,
                    weight: 3,
                    opacity: this.bySport ? 0.6 : 0.35,
                })
                const name = navData.activities.find(a => a.id === track.id)?.name ?? track.name
                // Leaflet renders tooltip strings as HTML; a text node keeps names plain text
                line.bindTooltip(document.createTextNode(name), { sticky: true })
                line.on('click', () => this.$router.push('/activity/' + track.id))
                line.addTo(group)
            }
            group.addTo(this.map)
            this.layer = group
            if (fit && this.visibleTracks.length) {
                this.map.fitBounds(group.getBounds(), { padding: [20, 20] })
            }
            this.drawPhotos()
        },
    },
}
</script>

<style scoped>
.heatmap-page {
    max-width: 1200px;
}
.heatmap-page__select {
    min-width: 200px;
}
.heatmap-page__count {
    display: inline-flex;
    align-items: center;
    gap: var(--ft-gap-2);
    color: var(--color-text-maxcontrast);
    padding-bottom: var(--ft-gap-2);
}
.heatmap-page__map {
    height: min(70vh, 720px);
    width: 100%;
    border-radius: var(--border-radius-container);
    overflow: hidden;
    border: 1px solid var(--color-border);
    margin-bottom: var(--ft-gap-2);
}
</style>
