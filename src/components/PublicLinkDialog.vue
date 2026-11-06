<template>
    <NcDialog
        :open="true"
        :name="t('fit_tracker', 'Public link')"
        size="normal"
        @update:open="$emit('close')"
    >
        <p class="public-link__intro">
            {{ t('fit_tracker', 'Anyone with the link can see this activity\'s stats, route, charts and laps, without logging in. Notes and photos are not shown.') }}
        </p>

        <NcLoadingIcon v-if="loading" :size="32" />
        <template v-else>
            <ul v-if="links.length" class="public-link__list">
                <li v-for="link in links" :key="link.id" class="public-link__item">
                    <NcTextField
                        :model-value="link.url"
                        :label="link.hideEnds ? t('fit_tracker', 'Link (start and end hidden)') : t('fit_tracker', 'Link (full route)')"
                        readonly
                        class="public-link__url"
                        @focus="$event.target.select()"
                    />
                    <NcButton :aria-label="t('fit_tracker', 'Copy link')" :title="t('fit_tracker', 'Copy link')" @click="copy(link.url)">
                        <template #icon>
                            <NcIconSvgWrapper :svg="icons.content_copy" :size="20" />
                        </template>
                    </NcButton>
                    <NcButton :aria-label="t('fit_tracker', 'Delete link')" :title="t('fit_tracker', 'Delete link')" @click="remove(link)">
                        <template #icon>
                            <NcIconSvgWrapper :svg="icons.delete" :size="20" />
                        </template>
                    </NcButton>
                </li>
            </ul>

            <div class="public-link__create">
                <NcCheckboxRadioSwitch v-model="hideEnds" type="switch">
                    {{ t('fit_tracker', 'Hide the first and last 200 m of the route') }}
                </NcCheckboxRadioSwitch>
                <NcButton variant="primary" :disabled="creating" @click="create">
                    <template #icon>
                        <NcLoadingIcon v-if="creating" :size="20" />
                        <NcIconSvgWrapper v-else :svg="icons.link" :size="20" />
                    </template>
                    {{ links.length ? t('fit_tracker', 'Create another link') : t('fit_tracker', 'Create link') }}
                </NcButton>
            </div>
            <NcNoteCard v-if="error" type="error" :text="error" />
        </template>
    </NcDialog>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { t } from '@nextcloud/l10n'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { NcButton, NcCheckboxRadioSwitch, NcDialog, NcIconSvgWrapper, NcLoadingIcon, NcNoteCard, NcTextField } from '@nextcloud/vue'
import { icons } from '../icons.js'

// Create, copy and delete public links of one activity.
export default {
    name: 'PublicLinkDialog',
    components: { NcButton, NcCheckboxRadioSwitch, NcDialog, NcIconSvgWrapper, NcLoadingIcon, NcNoteCard, NcTextField },
    props: {
        activityId: { type: [String, Number], required: true },
    },
    emits: ['close'],
    data() {
        return { icons, links: [], loading: true, creating: false, hideEnds: true, error: '' }
    },
    async mounted() {
        try {
            const { data } = await axios.get(generateUrl(`/apps/fit_tracker/api/activities/${this.activityId}/shares`))
            this.links = data
        } catch (e) {
            this.error = t('fit_tracker', 'Could not load the links. Try again later.')
        } finally {
            this.loading = false
        }
    },
    methods: {
        async create() {
            this.creating = true
            this.error = ''
            try {
                const { data } = await axios.post(generateUrl(`/apps/fit_tracker/api/activities/${this.activityId}/shares`), { hideEnds: this.hideEnds })
                this.links.push(data)
                await this.copy(data.url)
            } catch (e) {
                this.error = e.response?.status === 409
                    ? t('fit_tracker', 'The .fit file of this activity is missing from the folder, so it cannot be shared.')
                    : t('fit_tracker', 'Could not create the link. Try again later.')
            } finally {
                this.creating = false
            }
        },
        async copy(url) {
            try {
                await navigator.clipboard.writeText(url)
                showSuccess(t('fit_tracker', 'Link copied'))
            } catch (e) {
                // Clipboard access can be denied; the link is still shown to copy by hand
            }
        },
        async remove(link) {
            try {
                await axios.delete(generateUrl(`/apps/fit_tracker/api/shares/${link.id}`))
                this.links = this.links.filter(l => l.id !== link.id)
            } catch (e) {
                showError(t('fit_tracker', 'Could not delete the link. Try again later.'))
            }
        },
    },
}
</script>

<style scoped>
.public-link__intro {
    color: var(--color-text-maxcontrast);
    margin-bottom: var(--ft-gap-4);
}
.public-link__list {
    display: flex;
    flex-direction: column;
    gap: var(--ft-gap-2);
    margin-bottom: var(--ft-gap-4);
}
.public-link__item {
    display: flex;
    align-items: flex-end;
    gap: var(--ft-gap-2);
}
.public-link__url {
    flex: 1;
}
.public-link__create {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: var(--ft-gap-3);
    margin-bottom: var(--ft-gap-3);
}
</style>
