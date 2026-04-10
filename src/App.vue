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
            sports: [
                { value: 'all',      label: 'All Activities', icon: '🏅' },
                { value: 'running',  label: 'Running',        icon: '🏃' },
                { value: 'cycling',  label: 'Cycling',        icon: '🚴' },
                { value: 'hiking',   label: 'Hiking',         icon: '🥾' },
                { value: 'swimming', label: 'Swimming',       icon: '🏊' },
                { value: 'gym',        label: 'Gym',            icon: '🏋' },
                { value: 'breathwork',  label: 'Breathwork',  icon: '🧘' },
                { value: 'meditation',  label: 'Meditation',  icon: '🕉️' },
                { value: 'skiing',      label: 'Skiing',      icon: '⛷️' },
                { value: 'sleep',       label: 'Sleep',       icon: '🛌' },
            ],
        }
    },
    computed: {
        currentSport() {
            if (this.$route.path.startsWith('/sleep')) return 'sleep'
            return this.$route.query.sport || 'all'
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
                .setMimeTypeFilter(['httpd/unix-directory'])
                .build()
            try {
                const nodes = await picker.pick()
                const node = Array.isArray(nodes) ? nodes[0] : nodes
                if (node) {
                    // node may be a string path or an object with a path property
                    this.folderPath = typeof node === 'string' ? node : (node.path ?? node.basename ?? String(node))
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
