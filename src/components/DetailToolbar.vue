<template>
    <div class="detail-toolbar">
        <NcButton variant="tertiary" :to="backTo">
            <template #icon>
                <NcIconSvgWrapper :svg="icons.arrow_back" :size="20" directional />
            </template>
            {{ backLabel }}
        </NcButton>
        <div v-if="showActions" class="detail-toolbar__actions">
            <slot />
            <span v-if="busyLabel" class="detail-toolbar__busy" role="status">
                <NcLoadingIcon :size="20" />
                {{ busyLabel }}
            </span>
            <NcActions
                v-else :aria-label="t('fit_tracker', 'More actions')">
                <NcActionButton close-after-click @click="$emit('reimport')">
                    <template #icon>
                        <NcIconSvgWrapper :svg="icons.refresh" :size="20" />
                    </template>
                    {{ t('fit_tracker', 'Re-import from file') }}
                </NcActionButton>
                <NcActionSeparator />
                <NcActionButton close-after-click @click="confirmOpen = true">
                    <template #icon>
                        <NcIconSvgWrapper :svg="icons.delete" :size="20" />
                    </template>
                    {{ deleteLabel }}
                </NcActionButton>
            </NcActions>
        </div>

        <NcDialog
            v-model:open="confirmOpen"
            :name="deleteTitle || deleteLabel"
            :message="deleteMessage"
            :buttons="dialogButtons"
            size="small"
        />
    </div>
</template>

<script>
import { NcActions, NcActionButton, NcActionSeparator, NcButton, NcDialog, NcIconSvgWrapper, NcLoadingIcon } from '@nextcloud/vue'
import { t } from '@nextcloud/l10n'
import { icons } from '../icons.js'

// Top bar of the activity and sleep detail pages: a back link, optional extra
// actions in the slot, and a "⋯" menu with "Re-import from file" and, last,
// "Delete", which is confirmed in an NcDialog.
export default {
    name: 'DetailToolbar',
    components: { NcActions, NcActionButton, NcActionSeparator, NcButton, NcDialog, NcIconSvgWrapper, NcLoadingIcon },
    props: {
        backTo: { type: [String, Object], required: true },
        backLabel: { type: String, required: true },
        showActions: { type: Boolean, default: true },
        deleteLabel: { type: String, required: true },
        // Title of the delete confirmation dialog; falls back to deleteLabel
        deleteTitle: { type: String, default: '' },
        deleteMessage: { type: String, required: true },
        // While a re-import or delete runs: replaces the "⋯" menu with a
        // spinner and this text (e.g. "Re-importing…")
        busyLabel: { type: String, default: '' },
    },
    emits: ['delete', 'reimport'],
    data() {
        return { icons, confirmOpen: false }
    },
    computed: {
        dialogButtons() {
            return [
                { label: t('fit_tracker', 'Cancel'), variant: 'tertiary', callback: () => {} },
                { label: this.deleteLabel, variant: 'error', icon: icons.delete, callback: () => this.$emit('delete') },
            ]
        },
    },
}
</script>

<style scoped>
.detail-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--ft-gap-2);
    margin: 0 0 var(--ft-gap-4) var(--ft-gap-8);
    padding-top: var(--ft-gap-2);
}
.detail-toolbar__busy {
    display: inline-flex;
    align-items: center;
    gap: var(--ft-gap-2);
    min-height: var(--default-clickable-area);
    color: var(--color-text-maxcontrast);
}
.detail-toolbar__actions {
    display: flex;
    align-items: center;
    gap: var(--ft-gap-2);
}
</style>
