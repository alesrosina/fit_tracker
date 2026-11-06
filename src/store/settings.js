import { reactive } from 'vue'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

// The user's FIT Tracker settings (see lib/Service/SettingsService.php).
// Numbers are null when not set; the backend then uses defaults derived from
// the data, e.g. the highest recorded heart rate as max HR.
export const settings = reactive({
    loaded: false,
    folder_path: '',
    units: 'metric',
    max_hr: null,
    resting_hr: null,
    ftp: null,
    weather_enabled: false,
    calendar_uri: '',
})

function apply(data) {
    Object.assign(settings, data ?? {}, { loaded: true })
}

export async function loadSettings() {
    const { data } = await axios.get(generateUrl('/apps/fit_tracker/api/config'))
    apply(data)
    return settings
}

// Save some settings; resolves with the stored values, rejects on invalid input
export async function saveSettings(values) {
    const { data } = await axios.post(generateUrl('/apps/fit_tracker/api/config'), values)
    apply(data)
    return settings
}

// For the public page, which has no user settings: show the owner's units
export function useUnits(units) {
    settings.units = units === 'imperial' ? 'imperial' : 'metric'
}
