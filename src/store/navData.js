import { reactive } from 'vue'

// Shared reactive snapshot of what's actually been imported, so App.vue's nav
// can show only sport types (and Sleep) that exist, without issuing its own
// redundant fetch — ActivityList/SleepList already load this data on mount
// and on the 'fit-tracker:refresh' event.
export const navData = reactive({
    activities: [],
    sleep: [],
})

export function setActivities(list) {
    navData.activities = list ?? []
}

export function setSleep(list) {
    navData.sleep = list ?? []
}
