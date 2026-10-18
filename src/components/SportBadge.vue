<template>
    <span class="sport-badge" :style="{ '--sport-color': color }" aria-hidden="true">
        <NcIconSvgWrapper :svg="svg" :size="size" />
    </span>
</template>

<script>
import { NcIconSvgWrapper } from '@nextcloud/vue'
import { icons } from '../icons.js'

// Sport icon on a soft tint of the sport's data colour. The icon itself uses
// the theme text colour so it stays legible in light and dark mode.
export default {
    name: 'SportBadge',
    components: { NcIconSvgWrapper },
    props: {
        icon: { type: String, required: true },
        color: { type: String, default: 'var(--color-primary-element)' },
        size: { type: Number, default: 20 },
    },
    computed: {
        svg() {
            return icons[this.icon] ?? icons.exercise
        },
    },
}
</script>

<style scoped>
.sport-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    padding: var(--ft-gap-2);
    border-radius: var(--border-radius-element);
    color: var(--color-main-text);
    background: color-mix(in srgb, var(--sport-color) 22%, transparent);
}
</style>
