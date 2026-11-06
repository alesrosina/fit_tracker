import { reactive } from 'vue'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

// Shared reactive snapshot of what's actually been imported, so App.vue's nav
// can show only sport types (and Sleep) that exist, without issuing its own
// redundant fetch — the dashboard and SleepList already load this data on
// mount and on the 'fit-tracker:refresh' event.
export const navData = reactive({
    activities: [],
    sleep: [],
    // null until /api/config has answered; '' when no folder is set
    folderPath: null,
    settingsOpen: false,
})

export function setActivities(list) {
    navData.activities = list ?? []
}

export function setSleep(list) {
    navData.sleep = list ?? []
}

export function setFolderPath(path) {
    navData.folderPath = path ?? ''
}

export function openSettings() {
    navData.settingsOpen = true
}

// One shared request for the activity list (which also syncs the folder) and
// the sleep sessions, so pages opened directly by URL and the dashboard don't
// each trigger their own sync. Pass force to fetch again, e.g. on refresh.
let pending = null
export function fetchActivities(force = false) {
    if (pending && !force) return pending
    pending = Promise.all([
        axios.get(generateUrl('/apps/fit_tracker/api/activities')),
        axios.get(generateUrl('/apps/fit_tracker/api/sleep')).catch(() => null),
    ]).then(([actRes, sleepRes]) => {
        const activities = actRes.data.activities ?? actRes.data
        const sessions = sleepRes?.data?.sessions ?? []
        setActivities(activities)
        setSleep(sessions)
        return { activities, sessions, syncErrors: actRes.data.syncErrors ?? [] }
    })
    pending.catch(() => { pending = null })
    return pending
}

// Replace one activity in the shared list after it was edited
export function updateActivity(activity) {
    const i = navData.activities.findIndex(a => a.id === activity.id)
    if (i !== -1) navData.activities.splice(i, 1, activity)
}
