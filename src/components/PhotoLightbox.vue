<template>
    <NcModal
        size="full"
        dark
        :name="title"
        :has-previous="hasPrev"
        :has-next="hasNext"
        @previous="prev"
        @next="next"
        @close="$emit('close')"
    >
        <div class="lightbox">
            <img
                :key="current.fileId"
                :src="imageUrl"
                :alt="current.name"
                class="lightbox__img"
            />
        </div>
    </NcModal>
</template>

<script>
import { NcModal } from '@nextcloud/vue'
import { generateUrl } from '@nextcloud/router'
import { t } from '@nextcloud/l10n'

// Full-screen photo viewer. NcModal provides the close button, previous/next
// navigation, arrow-key and swipe handling.
export default {
    name: 'PhotoLightbox',
    components: { NcModal },
    props: {
        photos: { type: Array, required: true },
        startIndex: { type: Number, default: 0 },
    },
    emits: ['close'],
    data() {
        return {
            index: this.startIndex,
        }
    },
    computed: {
        current() {
            return this.photos[this.index]
        },
        hasPrev() {
            return this.index > 0
        },
        hasNext() {
            return this.index < this.photos.length - 1
        },
        title() {
            const name = this.current.name ?? ''
            if (this.photos.length < 2) return name
            // TRANSLATORS: Photo viewer title, e.g. "IMG_2041.jpg (3 of 12)"
            return t('fit_tracker', '{name} ({index} of {total})', {
                name,
                index: this.index + 1,
                total: this.photos.length,
            })
        },
        imageUrl() {
            return generateUrl(`/core/preview?fileId=${this.current.fileId}&x=2000&y=2000&a=1`)
        },
    },
    methods: {
        prev() {
            if (this.hasPrev) this.index--
        },
        next() {
            if (this.hasNext) this.index++
        },
    },
}
</script>

<style scoped>
.lightbox {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}
.lightbox__img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}
</style>
