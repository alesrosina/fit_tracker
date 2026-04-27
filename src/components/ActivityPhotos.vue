<template>
    <div class="activity-photos">
        <h3>Photos ({{ photos.length }})</h3>
        <div class="photo-grid">
            <div
                v-for="photo in sortedPhotos"
                :key="photo.fileId"
                class="photo-thumb"
                @click="$emit('open-photo', photo)"
            >
                <img
                    :src="thumbnailUrl(photo.fileId)"
                    :alt="photo.name"
                    loading="lazy"
                />
                <span v-if="formatTime(photo.takenAt)" class="photo-time">{{ formatTime(photo.takenAt) }}</span>
            </div>
        </div>
    </div>
</template>

<script>
import { generateUrl } from '@nextcloud/router'

export default {
    name: 'ActivityPhotos',
    props: {
        photos: { type: Array, required: true },
    },
    emits: ['open-photo'],
    computed: {
        sortedPhotos() {
            return [...this.photos].sort((a, b) => {
                if (!a.takenAt) return 1
                if (!b.takenAt) return -1
                return new Date(a.takenAt.replace(' ', 'T')) - new Date(b.takenAt.replace(' ', 'T'))
            })
        },
    },
    methods: {
        thumbnailUrl(fileId) {
            return generateUrl(`/core/preview?fileId=${fileId}&x=200&y=200&a=1`)
        },
        formatTime(raw) {
            if (!raw) return ''
            const d = new Date(raw.replace(' ', 'T'))
            return d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })
        },
    },
}
</script>

<style scoped>
.photo-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.photo-thumb {
    position: relative;
    display: block;
    width: 120px;
    height: 120px;
    border-radius: 6px;
    overflow: hidden;
    border: 1px solid var(--color-border);
    flex-shrink: 0;
    cursor: pointer;
}
.photo-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform 0.15s ease;
}
.photo-thumb:hover img {
    transform: scale(1.05);
}
.photo-time {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: rgba(0, 0, 0, 0.55);
    color: #fff;
    font-size: 11px;
    text-align: center;
    padding: 2px 0;
}
</style>
