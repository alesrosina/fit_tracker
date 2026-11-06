<template>
    <div v-if="weather" class="weather" role="group" :aria-label="t('fit_tracker', 'Weather at the start')">
        <NcIconSvgWrapper :svg="icons[condition.icon]" :size="32" class="weather__icon" />
        <div class="weather__main">
            <div class="weather__temp">{{ temperature(weather.temperature) }}</div>
            <div class="weather__desc">{{ condition.label }}</div>
        </div>
        <dl class="weather__details">
            <div v-if="weather.apparentTemperature !== null">
                <dt>{{ t('fit_tracker', 'Feels like') }}</dt>
                <dd>{{ temperature(weather.apparentTemperature) }}</dd>
            </div>
            <div v-if="weather.windSpeed !== null">
                <dt>{{ t('fit_tracker', 'Wind') }}</dt>
                <dd>{{ formatSpeed(weather.windSpeed) }} {{ windDirection(weather.windDirection) }}</dd>
            </div>
            <div v-if="weather.humidity !== null">
                <dt>{{ t('fit_tracker', 'Humidity') }}</dt>
                <dd>{{ Math.round(weather.humidity) }} %</dd>
            </div>
            <div v-if="weather.precipitation">
                <dt>{{ t('fit_tracker', 'Rain') }}</dt>
                <dd>{{ precipitation(weather.precipitation) }}</dd>
            </div>
        </dl>
        <a class="weather__source" href="https://open-meteo.com/" target="_blank" rel="noopener noreferrer">
            {{ t('fit_tracker', 'Weather data by Open-Meteo') }}
        </a>
    </div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { t } from '@nextcloud/l10n'
import { NcIconSvgWrapper } from '@nextcloud/vue'
import { icons } from '../icons.js'
import { formatSpeed, isImperial } from '../format.js'

// WMO weather interpretation codes used by Open-Meteo
function describe(code) {
    if (code === 0) return { icon: 'sunny', label: t('fit_tracker', 'Clear sky') }
    if (code <= 2) return { icon: 'partly_cloudy_day', label: t('fit_tracker', 'Partly cloudy') }
    if (code === 3) return { icon: 'cloud', label: t('fit_tracker', 'Overcast') }
    if (code <= 48) return { icon: 'foggy', label: t('fit_tracker', 'Fog') }
    if (code <= 57) return { icon: 'rainy', label: t('fit_tracker', 'Drizzle') }
    if (code <= 67) return { icon: 'rainy', label: t('fit_tracker', 'Rain') }
    if (code <= 77) return { icon: 'weather_snowy', label: t('fit_tracker', 'Snow') }
    if (code <= 82) return { icon: 'rainy', label: t('fit_tracker', 'Rain showers') }
    if (code <= 86) return { icon: 'weather_snowy', label: t('fit_tracker', 'Snow showers') }
    return { icon: 'thunderstorm', label: t('fit_tracker', 'Thunderstorm') }
}

// Weather at the start of an activity (only shown when turned on in the
// settings; the backend fetches it once and caches it).
export default {
    name: 'WeatherStrip',
    components: { NcIconSvgWrapper },
    props: {
        activityId: { type: [String, Number], required: true },
    },
    data() {
        return { icons, weather: null }
    },
    computed: {
        condition() {
            return describe(this.weather?.weatherCode ?? 3)
        },
    },
    async mounted() {
        try {
            const { data } = await axios.get(generateUrl(`/apps/fit_tracker/api/activities/${this.activityId}/weather`))
            this.weather = data.weather
        } catch (e) {
            // Weather is a nice-to-have; leave it out when Open-Meteo can't be reached
        }
    },
    methods: {
        formatSpeed,
        temperature(celsius) {
            return isImperial()
                ? t('fit_tracker', '{degrees} °F', { degrees: Math.round(celsius * 9 / 5 + 32) })
                : t('fit_tracker', '{degrees} °C', { degrees: Math.round(celsius) })
        },
        precipitation(mm) {
            return isImperial()
                ? t('fit_tracker', '{inches} in', { inches: (mm / 25.4).toFixed(2) })
                : t('fit_tracker', '{mm} mm', { mm: mm.toFixed(1) })
        },
        // Degrees the wind comes from → compass point
        windDirection(degrees) {
            if (degrees === null || degrees === undefined) return ''
            const points = [
                t('fit_tracker', 'N'), t('fit_tracker', 'NE'), t('fit_tracker', 'E'), t('fit_tracker', 'SE'),
                t('fit_tracker', 'S'), t('fit_tracker', 'SW'), t('fit_tracker', 'W'), t('fit_tracker', 'NW'),
            ]
            return points[Math.round(degrees / 45) % 8]
        },
    },
}
</script>

<style scoped>
.weather {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--ft-gap-3) var(--ft-gap-5);
    padding: var(--ft-gap-3) var(--ft-gap-4);
    margin-bottom: var(--ft-gap-6);
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-container);
}
.weather__icon {
    color: var(--color-text-maxcontrast);
}
.weather__temp {
    font-size: 20px;
    font-weight: bold;
}
.weather__desc {
    color: var(--color-text-maxcontrast);
}
.weather__details {
    display: flex;
    flex-wrap: wrap;
    gap: var(--ft-gap-2) var(--ft-gap-5);
    margin: 0;
}
.weather__details > div {
    display: flex;
    flex-direction: column;
}
.weather__details dt {
    font-size: var(--font-size-small);
    color: var(--color-text-maxcontrast);
}
.weather__details dd {
    margin: 0;
    font-weight: bold;
}
.weather__source {
    margin-inline-start: auto;
    font-size: var(--font-size-small);
    color: var(--color-text-maxcontrast);
}
</style>
