<template>
    <Teleport to="body">
        <div class="lightbox" @click="onBackdropClick" @touchstart="onTouchStart" @touchend="onTouchEnd">
            <div class="lightbox__bar">
                <span class="lightbox__title">{{ current.name }}</span>
                <button class="lightbox__icon-btn lightbox__close" aria-label="Close" @click.stop="close">✕</button>
            </div>

            <button
                v-if="hasPrev"
                class="lightbox__nav lightbox__nav--prev"
                aria-label="Previous photo"
                @click.stop="prev"
            >‹</button>

            <img
                :key="current.fileId"
                :src="imageUrl"
                :alt="current.name"
                class="lightbox__img"
                @click.stop
            />

            <button
                v-if="hasNext"
                class="lightbox__nav lightbox__nav--next"
                aria-label="Next photo"
                @click.stop="next"
            >›</button>

            <div v-if="photos.length > 1" class="lightbox__counter">{{ index + 1 }} / {{ photos.length }}</div>
        </div>
    </Teleport>
</template>

<script>
import { generateUrl } from '@nextcloud/router'

export default {
    name: 'PhotoLightbox',
    props: {
        photos: { type: Array, required: true },
        startIndex: { type: Number, default: 0 },
    },
    emits: ['close'],
    data() {
        return {
            index: this.startIndex,
            touchStartX: null,
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
        imageUrl() {
            return generateUrl(`/core/preview?fileId=${this.current.fileId}&x=2000&y=2000&a=1`)
        },
    },
    mounted() {
        document.addEventListener('keydown', this.onKeydown)
        this._prevOverflow = document.body.style.overflow
        document.body.style.overflow = 'hidden'
    },
    unmounted() {
        document.removeEventListener('keydown', this.onKeydown)
        document.body.style.overflow = this._prevOverflow
    },
    methods: {
        close() {
            this.$emit('close')
        },
        prev() {
            if (this.hasPrev) this.index--
        },
        next() {
            if (this.hasNext) this.index++
        },
        onKeydown(e) {
            if (e.key === 'Escape') this.close()
            else if (e.key === 'ArrowLeft') this.prev()
            else if (e.key === 'ArrowRight') this.next()
        },
        onBackdropClick() {
            this.close()
        },
        onTouchStart(e) {
            this.touchStartX = e.touches[0].clientX
        },
        onTouchEnd(e) {
            if (this.touchStartX === null) return
            const delta = e.changedTouches[0].clientX - this.touchStartX
            if (Math.abs(delta) > 50) {
                if (delta < 0) this.next()
                else this.prev()
            }
            this.touchStartX = null
        },
    },
}
</script>

<style scoped>
.lightbox {
    position: fixed;
    inset: 0;
    z-index: 10000;
    background: rgba(0, 0, 0, 0.95);
    display: flex;
    align-items: center;
    justify-content: center;
    touch-action: pan-y;
}
.lightbox__bar {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 16px;
    z-index: 1;
    background: linear-gradient(to bottom, rgba(0, 0, 0, 0.55), transparent);
}
.lightbox__title {
    color: #fff;
    font-size: 14px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.lightbox__icon-btn {
    background: rgba(255, 255, 255, 0.12);
    border: none;
    border-radius: 50%;
    color: #fff;
    width: 36px;
    height: 36px;
    font-size: 16px;
    cursor: pointer;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}
.lightbox__icon-btn:hover {
    background: rgba(255, 255, 255, 0.25);
}
.lightbox__img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
    cursor: default;
}
.lightbox__nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(255, 255, 255, 0.12);
    border: none;
    border-radius: 50%;
    color: #fff;
    width: 44px;
    height: 44px;
    font-size: 28px;
    line-height: 1;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1;
}
.lightbox__nav:hover {
    background: rgba(255, 255, 255, 0.25);
}
.lightbox__nav--prev { left: 12px; }
.lightbox__nav--next { right: 12px; }
.lightbox__counter {
    position: absolute;
    bottom: 16px;
    left: 50%;
    transform: translateX(-50%);
    color: #fff;
    font-size: 13px;
    background: rgba(0, 0, 0, 0.45);
    padding: 3px 10px;
    border-radius: 12px;
}
@media (max-width: 600px) {
    .lightbox__nav { width: 36px; height: 36px; font-size: 22px; }
}
</style>
