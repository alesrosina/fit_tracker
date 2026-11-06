import { reactive } from 'vue'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

// The user's goals, shared by the dashboard (scaled to the timeframe) and
// the goals page (edited there).
export const goals = reactive({ list: [], loaded: false })

let pending = null
export function fetchGoals(force = false) {
    if (pending && !force) return pending
    pending = axios.get(generateUrl('/apps/fit_tracker/api/goals')).then(({ data }) => {
        goals.list = data
        goals.loaded = true
        return goals.list
    })
    pending.catch(() => { pending = null })
    return pending
}

export async function saveGoal(goal) {
    const body = { sport: goal.sport ?? '', metric: goal.metric, period: goal.period, target: goal.target, threshold: goal.threshold ?? null }
    if (goal.id) await axios.put(generateUrl(`/apps/fit_tracker/api/goals/${goal.id}`), body)
    else await axios.post(generateUrl('/apps/fit_tracker/api/goals'), body)
    return fetchGoals(true)
}

export async function deleteGoal(id) {
    await axios.delete(generateUrl(`/apps/fit_tracker/api/goals/${id}`))
    return fetchGoals(true)
}
