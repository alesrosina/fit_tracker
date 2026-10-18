<template>
    <NcNoteCard type="error" :heading="heading">
        <p>{{ message }}</p>
        <NcButton class="load-error__retry" @click="$emit('retry')">
            {{ t('fit_tracker', 'Retry') }}
        </NcButton>
    </NcNoteCard>
</template>

<script>
import { NcNoteCard, NcButton } from '@nextcloud/vue'

// Recoverable error with a retry action, as the design guidelines ask for
// instead of an empty-content view.
export default {
    name: 'LoadError',
    components: { NcNoteCard, NcButton },
    props: {
        heading: { type: String, required: true },
        message: { type: String, required: true },
    },
    emits: ['retry'],
}
</script>

<style scoped>
.load-error__retry {
    margin-top: var(--ft-gap-2);
}
</style>
