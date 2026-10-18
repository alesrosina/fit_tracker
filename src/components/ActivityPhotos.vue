<template>
    <div class="activity-photos">
        <h3>{{ t('fit_tracker', 'Photos') }}</h3>
        <div class="photo-grid">
            <button
                v-for="(photo, i) in photos"
                :key="photo.fileId"
                type="button"
                class="photo-thumb"
                :class="{ 'photo-thumb--wide': orientationMap[photo.fileId] === 'landscape' }"
                :aria-label="t('fit_tracker', 'Open {name}', { name: photo.name ?? '' })"
                @click="$emit('open-photo', i)"
            >
                <img
                    :src="thumbnailUrl(photo.fileId)"
                    :alt="photo.name"
                    loading="lazy"
                    @load="onImgLoad($event, photo)"
                />
            </button>
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
    gap: var(--ft-gap-1);
}
.activity-photos {
    margin-bottom: var(--ft-gap-6);
}
.photo-thumb {
    position: relative;
    display: block;
    overflow: hidden;
    cursor: pointer;
    margin: 0;
    padding: 0;
    border: none;
    border-radius: var(--border-radius-small);
    background: var(--color-background-dark);
}
.photo-thumb:focus-visible {
    outline: 2px solid var(--color-primary-element);
    outline-offset: 2px;
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
