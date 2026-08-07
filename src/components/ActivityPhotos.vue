<template>
    <div class="activity-photos">
        <h3>Photos</h3>
        <div class="photo-grid">
            <div
                v-for="(photo, i) in photos"
                :key="photo.fileId"
                class="photo-thumb"
                :class="{ 'photo-thumb--wide': orientationMap[photo.fileId] === 'landscape' }"
                @click="$emit('open-photo', i)"
            >
                <img
                    :src="thumbnailUrl(photo.fileId)"
                    :alt="photo.name"
                    loading="lazy"
                    @load="onImgLoad($event, photo)"
                />
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
    data() {
        return {
            orientationMap: {},
        }
    },
    methods: {
        thumbnailUrl(fileId) {
            return generateUrl(`/core/preview?fileId=${fileId}&x=400&y=400&a=1`)
        },
        onImgLoad(event, photo) {
            const { naturalWidth, naturalHeight } = event.target
            if (naturalWidth > naturalHeight * 1.15) {
                this.orientationMap[photo.fileId] = 'landscape'
            }
        },
    },
}
</script>

<style scoped>
.photo-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
    grid-auto-rows: 110px;
    grid-auto-flow: dense;
    gap: 4px;
}
.photo-thumb {
    position: relative;
    display: block;
    overflow: hidden;
    cursor: pointer;
}
.photo-thumb--wide {
    grid-column: span 2;
}
.photo-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform 0.15s ease;
}
</style>
