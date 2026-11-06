import { reactive } from 'vue'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { ensureAnalyzed } from './analysis.js'

// Training load (TRIMP) and time in heart rate zones per activity, from
// /api/stats/load, shared by the dashboard, goals and trends. Older
// activities are analyzed first (see analysis.js).
export const training = reactive({
    // null until loaded
    activities: null,
    settings: null,
    // activities still being analyzed, for a progress note
    analyzing: 0,
})

let pending = null
export function fetchTraining(force = false) {
    if (pending && !force) return pending
    pending = (async () => {
        try {
            await ensureAnalyzed(remaining => { training.analyzing = remaining })
        } catch (e) {
            // Load what is already analyzed
        }
        training.analyzing = 0
        const { data } = await axios.get(generateUrl('/apps/fit_tracker/api/stats/load'))
        training.activities = data.activities
        training.settings = data.settings
        return training
    })()
    pending.catch(() => { pending = null })
    return pending
}
