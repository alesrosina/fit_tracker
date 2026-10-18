<template>
    <div class="page-nav">
        <NcButton
            variant="tertiary"
            :aria-label="previousLabel"
            :title="previousLabel"
            :disabled="!hasPrevious"
            @click="$emit('previous')"
        >
            <template #icon>
                <NcIconSvgWrapper :svg="icons.chevron_left" :size="20" directional />
            </template>
        </NcButton>
        <span class="page-nav__label">
            <slot />
        </span>
        <NcButton
            variant="tertiary"
            :aria-label="nextLabel"
            :title="nextLabel"
            :disabled="!hasNext"
            @click="$emit('next')"
        >
            <template #icon>
                <NcIconSvgWrapper :svg="icons.chevron_right" :size="20" directional />
            </template>
        </NcButton>
    </div>
</template>

<script>
import { NcButton, NcIconSvgWrapper } from '@nextcloud/vue'
import { icons } from '../icons.js'

// Previous / label / next control used for pagination and for stepping
// through months and weeks in the dashboard charts.
export default {
    name: 'PageNav',
    components: { NcButton, NcIconSvgWrapper },
    props: {
        previousLabel: { type: String, required: true },
        nextLabel: { type: String, required: true },
        hasPrevious: { type: Boolean, default: true },
        hasNext: { type: Boolean, default: true },
    },
    emits: ['previous', 'next'],
    data() {
        return { icons }
    },
}
</script>

<style scoped>
.page-nav {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--ft-gap-2);
}
.page-nav__label {
    font-weight: bold;
    text-align: center;
}
</style>
