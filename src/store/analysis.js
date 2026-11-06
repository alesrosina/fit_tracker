import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

// Activities imported before 1.3 have no stored analysis (HR histogram, best
// efforts, power curve, heatmap track) yet. The server works through them a
// few seconds per request; keep asking until none are left. Shared, so two
// pages opened one after another don't both run it.
let running = null

/** @param {(remaining: number) => void} [onProgress] */
export function ensureAnalyzed(onProgress) {
    if (!running) {
        running = (async () => {
            for (let i = 0; i < 200; i++) {
                const { data } = await axios.post(generateUrl('/apps/fit_tracker/api/stats/analyze'))
                onProgress?.(data.remaining)
                if (data.remaining === 0 || data.analyzed === 0) return
            }
        })().finally(() => { running = null })
    }
    return running
}
