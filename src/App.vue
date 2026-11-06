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

                <template v-if="navData.activities.length > 0">
                    <NcAppNavigationCaption :name="t('fit_tracker', 'Insights')" is-heading />
                    <NcAppNavigationItem
                        v-for="p in insightPages"
                        :key="p.path"
                        :name="p.label"
                        :to="p.path"
                        :active="$route.path === p.path"
                    >
                        <template #icon>
                            <NcIconSvgWrapper :svg="icons[p.icon]" :size="20" />
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

            <NcAppSettingsSection id="fit-tracker-units" :name="t('fit_tracker', 'Units')">
                <NcRadioGroup
                    :model-value="settings.units"
                    :label="t('fit_tracker', 'Units')"
                    hide-label
                    @update:model-value="save({ units: $event })"
                >
                    <NcRadioGroupButton value="metric" :label="t('fit_tracker', 'Metric (km, m)')" />
                    <NcRadioGroupButton value="imperial" :label="t('fit_tracker', 'Imperial (mi, ft)')" />
                </NcRadioGroup>
            </NcAppSettingsSection>

            <NcAppSettingsSection id="fit-tracker-body" :name="t('fit_tracker', 'Heart rate and power')">
                <p class="settings-hint">
                    {{ t('fit_tracker', 'Used for heart rate zones, training load and power analysis. Leave empty to use your highest recorded heart rate as maximum and 60 bpm at rest.') }}
                </p>
                <div class="settings-fields">
                    <NcTextField
                        v-for="field in numberFields"
                        :key="field.key"
                        v-model="numberDraft[field.key]"
                        type="number"
                        :label="field.label"
                        :helper-text="numberErrors[field.key] || field.hint"
                        :error="!!numberErrors[field.key]"
                        @change="saveNumber(field.key)"
                    />
                </div>
            </NcAppSettingsSection>

            <NcAppSettingsSection id="fit-tracker-weather" :name="t('fit_tracker', 'Weather')">
                <NcFormBox>
                    <NcFormBoxSwitch
                        :model-value="settings.weather_enabled"
                        :label="t('fit_tracker', 'Show the weather on activities')"
                        :description="t('fit_tracker', 'Looks up temperature, wind and rain at the start of each activity you open. This sends the start location, rounded to about 1 km, and the date to open-meteo.com.')"
                        @update:model-value="save({ weather_enabled: $event })"
                    />
                </NcFormBox>
            </NcAppSettingsSection>

            <NcAppSettingsSection id="fit-tracker-calendar" :name="t('fit_tracker', 'Calendar')">
                <p class="settings-hint">
                    {{ t('fit_tracker', 'Add every new activity as an event to one of your calendars.') }}
                </p>
                <NcSelect
                    :model-value="selectedCalendar"
                    :options="calendarOptions"
                    :input-label="t('fit_tracker', 'Calendar')"
                    :clearable="false"
                    label="name"
                    @update:model-value="save({ calendar_uri: $event?.uri ?? '' })"
                />
                <NcFormBox v-if="settings.calendar_uri" class="settings-gap">
                    <NcFormBoxButton
                        :label="exportingCalendar ? t('fit_tracker', 'Adding activities…') : t('fit_tracker', 'Add existing activities')"
                        :description="t('fit_tracker', 'Add all activities imported so far. Activities already in the calendar are skipped.')"
                        :disabled="exportingCalendar"
                        @click="exportCalendar"
                    >
                        <template #icon>
                            <NcLoadingIcon v-if="exportingCalendar" :size="20" />
                            <NcIconSvgWrapper v-else :svg="icons.calendar_month" :size="20" />
                        </template>
                    </NcFormBoxButton>
                </NcFormBox>
                <NcNoteCard v-if="calendarResult" :type="calendarResult.type" :text="calendarResult.text" />
            </NcAppSettingsSection>

            <NcAppSettingsSection id="fit-tracker-links" :name="t('fit_tracker', 'Public links')">
                <p v-if="publicLinks.length === 0" class="settings-hint">
                    {{ t('fit_tracker', 'No public links. Create one from the Share menu of an activity.') }}
                </p>
                <ul v-else class="public-links">
                    <li v-for="link in publicLinks" :key="link.id" class="public-links__item">
                        <router-link v-if="link.activityId" :to="'/activity/' + link.activityId" class="public-links__name" @click="navData.settingsOpen = false">
                            {{ activityName(link) }}
                        </router-link>
                        <span v-else class="public-links__name">{{ t('fit_tracker', 'Activity no longer imported') }}</span>
                        <NcButton variant="tertiary" :aria-label="t('fit_tracker', 'Copy link')" :title="t('fit_tracker', 'Copy link')" @click="copyLink(link.url)">
                            <template #icon>
                                <NcIconSvgWrapper :svg="icons.content_copy" :size="20" />
                            </template>
                        </NcButton>
                        <NcButton variant="tertiary" :aria-label="t('fit_tracker', 'Delete link')" :title="t('fit_tracker', 'Delete link')" @click="deleteLink(link)">
                            <template #icon>
                                <NcIconSvgWrapper :svg="icons.delete" :size="20" />
                            </template>
                        </NcButton>
                    </li>
                </ul>
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
    NcFormBoxSwitch, NcIconSvgWrapper, NcLoadingIcon, NcNoteCard, NcRadioGroup, NcRadioGroupButton,
    NcSelect, NcTextField, NcButton,
} from '@nextcloud/vue'
import { getFilePickerBuilder, FilePickerType, showError, showSuccess } from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { t, n } from '@nextcloud/l10n'
import { SPORTS, SPORT_MAP, sportIcon, sportLabel } from './sports.js'
import { navData, setFolderPath, setActivities, setSleep, fetchActivities } from './store/navData.js'
import { settings, loadSettings, saveSettings } from './store/settings.js'
import { timeframeQuery, rememberedTimeframe } from './timeframe.js'
import { icons } from './icons.js'

export default {
    name: 'App',
    components: {
        NcContent, NcAppNavigation, NcAppNavigationItem, NcAppNavigationCaption,
        NcAppContent, NcAppSettingsDialog, NcAppSettingsSection, NcFormBox, NcFormBoxButton,
        NcFormBoxSwitch, NcIconSvgWrapper, NcLoadingIcon, NcNoteCard, NcRadioGroup, NcRadioGroupButton,
        NcSelect, NcTextField, NcButton,
    },
    data() {
        return {
            navData,
            settings,
            icons,
            numberDraft: { max_hr: '', resting_hr: '', ftp: '' },
            numberErrors: {},
            calendars: [],
            exportingCalendar: false,
            calendarResult: null,
            publicLinks: [],
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
        insightPages() {
            return [
                { path: '/goals', label: t('fit_tracker', 'Goals'), icon: 'checklist' },
                { path: '/stats', label: t('fit_tracker', 'Trends'), icon: 'trending_up' },
                { path: '/records', label: t('fit_tracker', 'Records'), icon: 'trophy' },
                { path: '/heatmap', label: t('fit_tracker', 'Heatmap'), icon: 'map' },
                { path: '/gear', label: t('fit_tracker', 'Gear'), icon: 'steps' },
            ]
        },
        numberFields() {
            return [
                { key: 'max_hr', label: t('fit_tracker', 'Maximum heart rate (bpm)'), hint: t('fit_tracker', 'Zones are 50, 60, 70, 80 and 90 % of this') },
                { key: 'resting_hr', label: t('fit_tracker', 'Resting heart rate (bpm)'), hint: '' },
                { key: 'ftp', label: t('fit_tracker', 'Functional threshold power (W)'), hint: t('fit_tracker', 'For intensity factor and training stress score of rides with power') },
            ]
        },
        calendarOptions() {
            return [{ uri: '', name: t('fit_tracker', 'Do not add to a calendar') }, ...this.calendars]
        },
        selectedCalendar() {
            return this.calendarOptions.find(c => c.uri === settings.calendar_uri)
                ?? { uri: settings.calendar_uri, name: settings.calendar_uri }
        },
        currentSport() {
            if (this.$route.path.startsWith('/sleep')) return 'sleep'
            if (this.$route.path !== '/') return null
            return this.$route.query.sport || 'all'
        },
        topItems() {
            const items = [{ value: 'all', label: t('fit_tracker', 'Overview'), icon: 'dashboard' }]
            if (navData.sleep.length > 0) {
                items.push({ value: 'sleep', label: t('fit_tracker', 'Sleep'), icon: 'bedtime' })
            }
            return items
        },
        // Only show nav entries for sport types that actually exist among
        // what's been imported — the dashboard and SleepList publish their loaded
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
    watch: {
        'navData.settingsOpen'(open) {
            if (open) this.loadSettingsExtras()
        },
    },
    mounted() {
        this.loadConfig()
        // Fills the navigation; the dashboard reuses the same request
        fetchActivities().catch(() => {})
    },
    methods: {
        iconSvg(name) {
            return icons[name] ?? icons.exercise
        },
        selectSport(sport, event) {
            event?.preventDefault()
            // Keep the timeframe of the page currently or last shown
            const timeframe = ['/', '/sleep'].includes(this.$route.path) ? timeframeQuery(this.$route.query) : rememberedTimeframe()
            if (sport === 'sleep') {
                this.$router.push({ path: '/sleep', query: timeframe }).catch(() => {})
                return
            }
            const query = { ...timeframe }
            if (sport !== 'all') query.sport = sport
            this.$router.push({ path: '/', query }).catch(() => {})
        },
        async loadConfig() {
            try {
                await loadSettings()
                setFolderPath(settings.folder_path)
                this.resetNumberDraft()
            } catch (e) {
                setFolderPath('')
            }
        },
        resetNumberDraft() {
            for (const key of Object.keys(this.numberDraft)) {
                this.numberDraft[key] = settings[key] ?? ''
            }
        },
        // Calendars and public links are only needed while the dialog is open
        async loadSettingsExtras() {
            this.calendarResult = null
            this.resetNumberDraft()
            this.numberErrors = {}
            const [calendars, links] = await Promise.all([
                axios.get(generateUrl('/apps/fit_tracker/api/calendars')).catch(() => null),
                axios.get(generateUrl('/apps/fit_tracker/api/shares')).catch(() => null),
            ])
            this.calendars = calendars?.data ?? []
            this.publicLinks = links?.data ?? []
        },
        async save(values) {
            try {
                await saveSettings(values)
                window.dispatchEvent(new CustomEvent('fit-tracker:settings'))
                return true
            } catch (e) {
                showError(t('fit_tracker', 'Could not save the setting. Try again later.'))
                return false
            }
        },
        async saveNumber(key) {
            const raw = String(this.numberDraft[key] ?? '').trim()
            this.numberErrors = { ...this.numberErrors, [key]: '' }
            try {
                await saveSettings({ [key]: raw === '' ? null : Number(raw) })
                window.dispatchEvent(new CustomEvent('fit-tracker:settings'))
            } catch (e) {
                this.numberErrors = {
                    ...this.numberErrors,
                    [key]: e.response?.status === 400
                        ? t('fit_tracker', 'Enter a realistic value, or leave the field empty.')
                        : t('fit_tracker', 'Could not save the setting. Try again later.'),
                }
            }
        },
        async exportCalendar() {
            this.exportingCalendar = true
            this.calendarResult = null
            try {
                const { data } = await axios.post(generateUrl('/apps/fit_tracker/api/calendars/export'))
                this.calendarResult = {
                    type: 'success',
                    text: n('fit_tracker', 'Added %n activity to the calendar.', 'Added %n activities to the calendar.', data.added),
                }
            } catch (e) {
                this.calendarResult = { type: 'error', text: t('fit_tracker', 'Could not add the activities to the calendar. Try again later.') }
            } finally {
                this.exportingCalendar = false
            }
        },
        activityName(link) {
            return navData.activities.find(a => a.id === link.activityId)?.name ?? link.activityName
        },
        async copyLink(url) {
            try {
                await navigator.clipboard.writeText(url)
                showSuccess(t('fit_tracker', 'Link copied'))
            } catch (e) {
                showError(t('fit_tracker', 'Could not copy the link'))
            }
        },
        async deleteLink(link) {
            try {
                await axios.delete(generateUrl(`/apps/fit_tracker/api/shares/${link.id}`))
                this.publicLinks = this.publicLinks.filter(l => l.id !== link.id)
            } catch (e) {
                showError(t('fit_tracker', 'Could not delete the link. Try again later.'))
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
                await saveSettings({ folder_path: path })
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
.settings-hint {
    color: var(--color-text-maxcontrast);
    margin-bottom: var(--ft-gap-3);
}
.settings-fields {
    display: flex;
    flex-direction: column;
    gap: var(--ft-gap-3);
    max-width: 400px;
}
.public-links {
    display: flex;
    flex-direction: column;
    gap: var(--ft-gap-1);
}
.public-links__item {
    display: flex;
    align-items: center;
    gap: var(--ft-gap-1);
}
.public-links__name {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
</style>
