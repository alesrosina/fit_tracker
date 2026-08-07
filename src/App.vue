<template>
    <NcContent app-name="fit_tracker">
        <NcAppNavigation>
            <template #list>
                <NcAppNavigationItem
                    v-for="s in sports"
                    :key="s.value"
                    :name="s.label"
                    :active="currentSport === s.value"
                    @click="selectSport(s.value, $event)"
                >
                    <template #icon>
                        <span class="nav-icon">{{ s.icon }}</span>
                    </template>
                </NcAppNavigationItem>
            </template>

            <template #footer>
                <NcAppNavigationItem
                    name="Settings"
                    @click="openSettings"
                >
                    <template #icon>
                        <span class="nav-icon">⚙</span>
                    </template>
                </NcAppNavigationItem>
            </template>
        </NcAppNavigation>

        <NcAppContent>
            <router-view />
        </NcAppContent>

        <NcModal v-if="showSettings" @close="showSettings = false" size="small">
            <template #default>
                <div class="settings-modal">
                    <h2>FIT Tracker Settings</h2>

                    <label>Folder to scan for .fit files</label>
                    <div class="folder-picker">
                        <span class="folder-path">{{ folderPath || 'No folder selected' }}</span>
                        <NcButton @click="pickFolder" type="secondary" :disabled="saving">
                            Choose folder
                        </NcButton>
                    </div>

                    <div v-if="saveError" class="error">{{ saveError }}</div>
                    <div v-if="saveSuccess" class="success">Settings saved.</div>

                    <div class="repair-section">
                        <label>Fix sport labels on already-imported activities</label>
                        <NcButton @click="repairSportTypes" :disabled="repairing" type="secondary">
                            {{ repairing ? 'Re-checking…' : 'Re-check sport types' }}
                        </NcButton>
                        <div v-if="repairError" class="error">{{ repairError }}</div>
                        <div v-if="repairResult" class="success">
                            Checked {{ repairResult.checked }}, updated {{ repairResult.sportUpdated }}, moved to sleep {{ repairResult.movedToSleep }}, still unrecognized {{ repairResult.stillUnrecognized }}.
                        </div>
                    </div>

                    <div class="settings-actions">
                        <NcButton @click="showSettings = false" type="tertiary">Close</NcButton>
                        <NcButton @click="saveConfig" :disabled="saving || !folderPath" type="primary">Save</NcButton>
                    </div>
                </div>
            </template>
        </NcModal>
    </NcContent>
</template>

<script>
import { NcContent, NcAppNavigation, NcAppNavigationItem, NcAppContent, NcModal, NcButton } from '@nextcloud/vue'
import { getFilePickerBuilder, FilePickerType } from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { SPORTS, SPORT_MAP, sportIcon, sportLabel } from './sports.js'
import { navData } from './store/navData.js'

export default {
    name: 'App',
    components: { NcContent, NcAppNavigation, NcAppNavigationItem, NcAppContent, NcModal, NcButton },
    data() {
        return {
            showSettings: false,
            folderPath: '',
            saving: false,
            saveError: null,
            saveSuccess: false,
            repairing: false,
            repairError: null,
            repairResult: null,
        }
    },
    computed: {
        currentSport() {
            if (this.$route.path.startsWith('/sleep')) return 'sleep'
            return this.$route.query.sport || 'all'
        },
        // Only show nav entries for sport types (and Sleep) that actually
        // exist among what's been imported — ActivityList/SleepList publish
        // their loaded data into the shared navData store as they fetch it.
        sports() {
            const present = new Set(navData.activities.map(a => a.sport))
            const curated = SPORTS.filter(s => present.has(s.value))
            const extra   = [...present]
                .filter(v => !SPORT_MAP[v])
                .sort()
                .map(v => ({ value: v, label: sportLabel(v), icon: sportIcon(v) }))

            const list = [{ value: 'all', label: 'All Activities', icon: '🏅' }, ...curated, ...extra]
            if (navData.sleep.length > 0) {
                list.push({ value: 'sleep', label: 'Sleep', icon: '🛌' })
            }
            return list
        },
    },
    methods: {
        selectSport(sport, event) {
            event?.preventDefault()
            if (sport === 'sleep') {
                this.$router.push('/sleep').catch(() => {})
                return
            }
            const query = sport === 'all' ? {} : { sport }
            this.$router.push({ path: '/', query }).catch(() => {})
        },
        async openSettings() {
            this.saveError = null
            this.saveSuccess = false
            try {
                const { data } = await axios.get(generateUrl('/apps/fit_tracker/api/config'))
                this.folderPath = data.folder_path || ''
            } catch (e) {
                this.folderPath = ''
            }
            this.showSettings = true
        },
        async pickFolder() {
            const picker = getFilePickerBuilder('Select folder for FIT files')
                .setMultiSelect(false)
                .setType(FilePickerType.Choose)
                .allowDirectories(true)
                .build()
            try {
                const path = await picker.pick()
                if (path) {
                    this.folderPath = path
                }
            } catch (e) {
                // user cancelled
            }
        },
        async saveConfig() {
            this.saving = true
            this.saveError = null
            this.saveSuccess = false
            try {
                await axios.post(generateUrl('/apps/fit_tracker/api/config'), { folder_path: this.folderPath })
                this.saveSuccess = true
                this.showSettings = false
                window.dispatchEvent(new CustomEvent('fit-tracker:refresh'))
            } catch (e) {
                this.saveError = e.response?.data?.error ?? 'Failed to save settings'
            } finally {
                this.saving = false
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
                this.repairError = e.response?.data?.error ?? 'Failed to re-check sport types'
            } finally {
                this.repairing = false
            }
        },
    },
}
</script>

<style scoped>
.settings-modal {
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.settings-modal h2 { margin: 0; }
.settings-modal label {
    font-weight: 600;
    font-size: 14px;
}
.folder-picker {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius);
}
.folder-path {
    flex: 1;
    font-size: 13px;
    color: var(--color-text-maxcontrast);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.folder-path:not(:empty) { color: var(--color-main-text); }
.repair-section {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding-top: 8px;
    border-top: 1px solid var(--color-border);
}
.settings-actions {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
    margin-top: 4px;
}
.error { color: var(--color-text-error); font-size: 13px; }
.success { color: var(--color-text-success); font-size: 13px; }
.sync-result { font-size: 13px; }
.nav-icon { font-size: 16px; }
</style>
