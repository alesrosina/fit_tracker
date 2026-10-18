<template>
    <NcContent app-name="fit_tracker">
        <NcAppNavigation>
            <template #list>
                <NcAppNavigationItem
                    v-for="s in topItems"
                    :key="s.value"
                    :name="s.label"
                    :active="currentSport === s.value"
                    @click="selectSport(s.value, $event)"
                >
                    <template #icon>
                        <NcIconSvgWrapper :svg="iconSvg(s.icon)" :size="20" />
                    </template>
                </NcAppNavigationItem>

                <template v-if="sports.length > 0">
                    <NcAppNavigationCaption :name="t('fit_tracker', 'Sports')" is-heading />
                    <NcAppNavigationItem
                        v-for="s in sports"
                        :key="s.value"
                        :name="s.label"
                        :active="currentSport === s.value"
                        @click="selectSport(s.value, $event)"
                    >
                        <template #icon>
                            <NcIconSvgWrapper :svg="iconSvg(s.icon)" :size="20" />
                        </template>
                    </NcAppNavigationItem>
                </template>
            </template>

            <template #footer>
                <NcAppNavigationItem
                    :name="t('fit_tracker', 'Settings')"
                    @click="openSettings($event)"
                >
                    <template #icon>
                        <NcIconSvgWrapper :svg="icons.settings" :size="20" />
                    </template>
                </NcAppNavigationItem>
            </template>
        </NcAppNavigation>

        <NcAppContent>
            <router-view />
        </NcAppContent>

        <NcAppSettingsDialog
            v-model:open="navData.settingsOpen"
            :name="t('fit_tracker', 'FIT Tracker settings')"
            show-navigation
        >
            <NcAppSettingsSection id="fit-tracker-folder" :name="t('fit_tracker', 'Source folder')">
                <NcFormBox>
                    <NcFormBoxButton
                        :label="t('fit_tracker', 'Choose folder')"
                        :description="navData.folderPath || t('fit_tracker', 'No folder selected')"
                        :disabled="saving"
                        @click="pickFolder"
                    >
                        <template #icon>
                            <NcIconSvgWrapper :svg="icons.folder_open" :size="20" />
                        </template>
                    </NcFormBoxButton>
                </NcFormBox>
                <NcNoteCard v-if="saveError" type="error" :text="saveError" />

                <NcFormBox class="settings-gap">
                    <NcFormBoxButton
                        :label="syncing ? t('fit_tracker', 'Syncing…') : t('fit_tracker', 'Sync now')"
                        :description="t('fit_tracker', 'Look for new .fit files in the folder and import them. This already happens every time the app is opened; use it after adding files while the app is open.')"
                        :disabled="syncing || !navData.folderPath"
                        @click="syncNow"
                    >
                        <template #icon>
                            <NcLoadingIcon v-if="syncing" :size="20" />
                            <NcIconSvgWrapper v-else :svg="icons.sync" :size="20" />
                        </template>
                    </NcFormBoxButton>
                </NcFormBox>
                <NcNoteCard v-if="syncError" type="error" :text="syncError" />
                <NcNoteCard v-if="syncResult" :type="syncResult.errors ? 'warning' : 'success'" :text="syncSummary" />
            </NcAppSettingsSection>

            <NcAppSettingsSection id="fit-tracker-maintenance" :name="t('fit_tracker', 'Maintenance')">
                <NcFormBox>
                    <NcFormBoxButton
                        :label="repairing ? t('fit_tracker', 'Re-checking sport types…') : t('fit_tracker', 'Re-check sport types')"
                        :description="t('fit_tracker', 'Classify imported activities again with the current sport detection. Sleep and nap files are moved to Sleep.')"
                        :disabled="repairing"
                        @click="repairSportTypes"
                    >
                        <template #icon>
                            <NcLoadingIcon v-if="repairing" :size="20" />
                            <NcIconSvgWrapper v-else :svg="icons.sync" :size="20" />
                        </template>
                    </NcFormBoxButton>
                </NcFormBox>
                <NcNoteCard v-if="repairError" type="error" :text="repairError" />
                <NcNoteCard v-if="repairResult" type="success" :text="repairSummary" />

                <NcFormBox class="settings-gap">
                    <NcFormBoxButton
                        :label="reimporting ? t('fit_tracker', 'Re-importing all files…') : t('fit_tracker', 'Re-import all files')"
                        :description="t('fit_tracker', 'Remove all imported activities and sleep sessions and import every .fit file in the folder again. Use this after an update that improves file reading. Large folders can take a few minutes.')"
                        :disabled="reimporting || !navData.folderPath"
                        @click="reimportAll"
                    >
                        <template #icon>
                            <NcLoadingIcon v-if="reimporting" :size="20" />
                            <NcIconSvgWrapper v-else :svg="icons.refresh" :size="20" />
                        </template>
                    </NcFormBoxButton>
                </NcFormBox>
                <NcNoteCard v-if="reimportError" type="error" :text="reimportError" />
                <NcNoteCard v-if="reimportResult" :type="reimportResult.errors.length ? 'warning' : 'success'" :text="reimportSummary" />
            </NcAppSettingsSection>
        </NcAppSettingsDialog>
    </NcContent>
</template>

<script>
import {
    NcContent, NcAppNavigation, NcAppNavigationItem, NcAppNavigationCaption,
    NcAppContent, NcAppSettingsDialog, NcAppSettingsSection, NcFormBox, NcFormBoxButton,
    NcIconSvgWrapper, NcLoadingIcon, NcNoteCard,
} from '@nextcloud/vue'
import { getFilePickerBuilder, FilePickerType } from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { t, n } from '@nextcloud/l10n'
import { SPORTS, SPORT_MAP, sportIcon, sportLabel } from './sports.js'
import { navData, setFolderPath, setActivities, setSleep } from './store/navData.js'
import { icons } from './icons.js'

export default {
    name: 'App',
    components: {
        NcContent, NcAppNavigation, NcAppNavigationItem, NcAppNavigationCaption,
        NcAppContent, NcAppSettingsDialog, NcAppSettingsSection, NcFormBox, NcFormBoxButton,
        NcIconSvgWrapper, NcLoadingIcon, NcNoteCard,
    },
    data() {
        return {
            navData,
            icons,
            saving: false,
            saveError: null,
            syncing: false,
            syncError: null,
            syncResult: null,
            repairing: false,
            repairError: null,
            repairResult: null,
            reimporting: false,
            reimportError: null,
            reimportResult: null,
        }
    },
    computed: {
        currentSport() {
            if (this.$route.path.startsWith('/sleep')) return 'sleep'
            return this.$route.query.sport || 'all'
        },
        topItems() {
            const items = [{ value: 'all', label: t('fit_tracker', 'All activities'), icon: 'exercise' }]
            if (navData.sleep.length > 0) {
                items.push({ value: 'sleep', label: t('fit_tracker', 'Sleep'), icon: 'bedtime' })
            }
            return items
        },
        // Only show nav entries for sport types that actually exist among
        // what's been imported — ActivityList/SleepList publish their loaded
        // data into the shared navData store as they fetch it.
        sports() {
            const present = new Set(navData.activities.map(a => a.sport))
            const curated = SPORTS.filter(s => present.has(s.value))
            const extra   = [...present]
                .filter(v => !SPORT_MAP[v])
                .sort()
                .map(v => ({ value: v, label: sportLabel(v), icon: sportIcon(v) }))
            return [...curated, ...extra]
        },
        repairSummary() {
            const r = this.repairResult
            if (!r) return ''
            // TRANSLATORS: Result of re-checking sport types. {checked} is the number of activities looked at, the others are counts of how many were changed.
            return t('fit_tracker', 'Checked {checked} activities: {updated} updated, {moved} moved to sleep, {unrecognized} still unrecognized.', {
                checked: r.checked,
                updated: r.sportUpdated,
                moved: r.movedToSleep,
                unrecognized: r.stillUnrecognized,
            })
        },
        syncSummary() {
            const r = this.syncResult
            if (!r) return ''
            const imported = n('fit_tracker', '%n new activity', '%n new activities', r.activities)
                + ' · ' + n('fit_tracker', '%n new sleep session', '%n new sleep sessions', r.sleep)
            if (!r.errors) return imported
            return imported + ' · ' + n('fit_tracker', '%n file could not be imported', '%n files could not be imported', r.errors)
        },
        reimportSummary() {
            const r = this.reimportResult
            if (!r) return ''
            const imported = n('fit_tracker', 'Imported %n activity', 'Imported %n activities', r.activities)
                + ' · ' + n('fit_tracker', '%n sleep session', '%n sleep sessions', r.sleep)
            if (!r.errors.length) return imported
            return imported + ' · ' + n('fit_tracker', '%n file could not be imported', '%n files could not be imported', r.errors.length)
        },
    },
    mounted() {
        this.loadConfig()
    },
    methods: {
        iconSvg(name) {
            return icons[name] ?? icons.exercise
        },
        selectSport(sport, event) {
            event?.preventDefault()
            if (sport === 'sleep') {
                this.$router.push('/sleep').catch(() => {})
                return
            }
            const query = sport === 'all' ? {} : { sport }
            this.$router.push({ path: '/', query }).catch(() => {})
        },
        async loadConfig() {
            try {
                const { data } = await axios.get(generateUrl('/apps/fit_tracker/api/config'))
                setFolderPath(data.folder_path)
            } catch (e) {
                setFolderPath('')
            }
        },
        openSettings(event) {
            event?.preventDefault()
            this.saveError = null
            this.repairError = null
            this.repairResult = null
            this.reimportError = null
            this.reimportResult = null
            navData.settingsOpen = true
        },
        async pickFolder() {
            const picker = getFilePickerBuilder(t('fit_tracker', 'Choose the folder with your .fit files'))
                .setMultiSelect(false)
                .setType(FilePickerType.Choose)
                .allowDirectories(true)
                .build()
            let path
            try {
                path = await picker.pick()
            } catch (e) {
                return // picker closed without a choice
            }
            if (path) await this.saveFolder(path)
        },
        async saveFolder(path) {
            this.saving = true
            this.saveError = null
            try {
                await axios.post(generateUrl('/apps/fit_tracker/api/config'), { folder_path: path })
                setFolderPath(path)
                window.dispatchEvent(new CustomEvent('fit-tracker:refresh'))
            } catch (e) {
                this.saveError = t('fit_tracker', 'Could not save the folder. Check that it still exists and try again.')
            } finally {
                this.saving = false
            }
        },
        // The backend imports new files from the folder whenever the lists are fetched
        async syncNow() {
            this.syncing = true
            this.syncError = null
            this.syncResult = null
            const before = { activities: navData.activities.length, sleep: navData.sleep.length }
            try {
                const [actRes, sleepRes] = await Promise.all([
                    axios.get(generateUrl('/apps/fit_tracker/api/activities')),
                    axios.get(generateUrl('/apps/fit_tracker/api/sleep')),
                ])
                const activities = actRes.data.activities ?? []
                const sessions   = sleepRes.data.sessions ?? []
                setActivities(activities)
                setSleep(sessions)
                this.syncResult = {
                    activities: Math.max(0, activities.length - before.activities),
                    sleep: Math.max(0, sessions.length - before.sleep),
                    errors: new Set([...(actRes.data.syncErrors ?? []), ...(sleepRes.data.syncErrors ?? [])]).size,
                }
                window.dispatchEvent(new CustomEvent('fit-tracker:refresh'))
            } catch (e) {
                this.syncError = t('fit_tracker', 'Could not sync the folder. Try again later.')
            } finally {
                this.syncing = false
            }
        },
        async reimportAll() {
            this.reimporting = true
            this.reimportError = null
            this.reimportResult = null
            try {
                const { data } = await axios.post(generateUrl('/apps/fit_tracker/api/reimport'))
                this.reimportResult = data
                window.dispatchEvent(new CustomEvent('fit-tracker:refresh'))
            } catch (e) {
                this.reimportError = t('fit_tracker', 'Could not re-import the files. Try again later; already imported entries come back with the next sync.')
            } finally {
                this.reimporting = false
            }
        },
        async repairSportTypes() {
            this.repairing = true
            this.repairError = null
            this.repairResult = null
            try {
                const { data } = await axios.post(generateUrl('/apps/fit_tracker/api/activities/repair-sport'))
                this.repairResult = data
                window.dispatchEvent(new CustomEvent('fit-tracker:refresh'))
            } catch (e) {
                this.repairError = t('fit_tracker', 'Could not re-check sport types. Try again later.')
            } finally {
                this.repairing = false
            }
        },
    },
}
</script>

<style scoped>
.settings-gap {
    margin-top: var(--ft-gap-4);
}
</style>
