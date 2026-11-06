<template>
    <div class="ft-page gear-page">
        <div class="gear-page__header">
            <h2 class="ft-page-title">{{ t('fit_tracker', 'Gear') }}</h2>
            <NcButton variant="primary" @click="openEditor(null)">
                <template #icon>
                    <NcIconSvgWrapper :svg="icons.add" :size="20" />
                </template>
                {{ t('fit_tracker', 'Add gear') }}
            </NcButton>
        </div>

        <LoadingSkeleton v-if="loading" />
        <LoadError
            v-else-if="error"
            :heading="t('fit_tracker', 'Could not load your gear')"
            :message="t('fit_tracker', 'Check your connection and try again.')"
            @retry="load"
        />
        <NcEmptyContent
            v-else-if="gear.length === 0"
            :name="t('fit_tracker', 'No gear yet')"
            :description="t('fit_tracker', 'Add your shoes or bikes to see how far you have gone with them and when it is time to replace them.')"
        >
            <template #icon>
                <NcIconSvgWrapper :svg="icons.steps" />
            </template>
        </NcEmptyContent>

        <template v-else>
            <section v-for="group in groups" :key="group.key" class="gear-group">
                <h3 v-if="group.items.length">{{ group.title }}</h3>
                <div class="gear-grid">
                    <button
                        v-for="g in group.items"
                        :key="g.id"
                        class="gear-card"
                        :class="{ 'gear-card--retired': g.retiredDate }"
                        :aria-label="t('fit_tracker', 'Edit {gear}', { gear: g.name })"
                        @click="openEditor(g)"
                    >
                        <div class="gear-card__top">
                            <NcIconSvgWrapper :svg="icons[typeIcon(g.type)]" :size="24" />
                            <span class="gear-card__name">{{ g.name }}</span>
                        </div>
                        <div class="gear-card__distance">{{ formatDistance(g.totalDistance, 0) }}</div>
                        <template v-if="g.retireDistance">
                            <div class="gear-card__bar" role="progressbar" :aria-valuenow="Math.round(wear(g) * 100)" aria-valuemin="0" aria-valuemax="100">
                                <div class="gear-card__fill" :class="wearClass(g)" :style="{ width: Math.min(100, wear(g) * 100) + '%' }" />
                            </div>
                            <div class="gear-card__wear" :class="wearClass(g)">
                                <NcIconSvgWrapper v-if="wear(g) >= 0.9 && !g.retiredDate" :svg="icons.warning" :size="16" />
                                {{ wearText(g) }}
                            </div>
                        </template>
                        <div class="gear-card__meta">
                            {{ n('fit_tracker', '%n activity', '%n activities', g.activityCount) }}
                            · {{ formatDurationShort(g.totalDuration) }}
                        </div>
                        <div class="gear-card__meta">
                            <span v-if="g.defaultSport">{{ t('fit_tracker', 'Default for {sport}', { sport: sportLabel(g.defaultSport) }) }}</span>
                            <span v-if="g.lastUsed"> · {{ t('fit_tracker', 'Last used {date}', { date: formatDay(g.lastUsed) }) }}</span>
                            <span v-if="g.retiredDate"> · {{ t('fit_tracker', 'Retired {date}', { date: formatDay(g.retiredDate + ' 12:00:00') }) }}</span>
                        </div>
                    </button>
                </div>
            </section>
            <p class="ft-panel__hint">
                {{ t('fit_tracker', 'Gear that is the default for a sport is used for every activity of that sport from its start date until it is retired, unless you pick other gear for an activity with "Edit".') }}
            </p>
        </template>

        <NcDialog
            v-if="editing"
            :open="true"
            :name="editing.id ? t('fit_tracker', 'Edit gear') : t('fit_tracker', 'Add gear')"
            :buttons="dialogButtons"
            size="normal"
            @update:open="editing = null"
        >
            <div class="gear-form">
                <NcTextField v-model="editing.name" :label="t('fit_tracker', 'Name')" :maxlength="255" required />
                <NcRadioGroup v-model="editing.type" :label="t('fit_tracker', 'Type')">
                    <NcRadioGroupButton value="shoes" :label="t('fit_tracker', 'Shoes')" />
                    <NcRadioGroupButton value="bike" :label="t('fit_tracker', 'Bike')" />
                    <NcRadioGroupButton value="other" :label="t('fit_tracker', 'Other')" />
                </NcRadioGroup>
                <NcSelect
                    v-model="editing.sportOption"
                    :options="sportOptions"
                    :input-label="t('fit_tracker', 'Default for sport')"
                    :clearable="false"
                    label="label"
                />
                <div class="gear-form__row">
                    <NcDateTimePickerNative v-model="editing.startDate" type="date" :label="t('fit_tracker', 'In use since')" />
                    <NcDateTimePickerNative v-model="editing.retiredDate" type="date" :label="t('fit_tracker', 'Retired on')" />
                </div>
                <div class="gear-form__row">
                    <NcTextField
                        v-model="editing.initialDistance"
                        type="number"
                        :label="t('fit_tracker', 'Already used ({unit})', { unit: distanceUnit() })"
                        :helper-text="t('fit_tracker', 'Distance before you started tracking it')"
                    />
                    <NcTextField
                        v-model="editing.retireDistance"
                        type="number"
                        :label="t('fit_tracker', 'Replace after ({unit})', { unit: distanceUnit() })"
                        :helper-text="t('fit_tracker', 'Running shoes usually last 500–800 km')"
                    />
                </div>
                <NcNoteCard v-if="formError" type="error" :text="formError" />
            </div>
        </NcDialog>
    </div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { t } from '@nextcloud/l10n'
import { showError } from '@nextcloud/dialogs'
import {
    NcButton, NcDateTimePickerNative, NcDialog, NcEmptyContent, NcIconSvgWrapper, NcNoteCard,
    NcRadioGroup, NcRadioGroupButton, NcSelect, NcTextField,
} from '@nextcloud/vue'
import LoadingSkeleton from './LoadingSkeleton.vue'
import LoadError from './LoadError.vue'
import { icons } from '../icons.js'
import { sportLabel } from '../sports.js'
import { navData, fetchActivities } from '../store/navData.js'
import { formatDay, formatDistance, formatDurationShort, distanceUnit, distanceValue } from '../format.js'

const pad = v => String(v).padStart(2, '0')
const toDateString = d => d ? `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}` : null
const fromDateString = s => s ? new Date(s + 'T00:00:00') : null

export default {
    name: 'GearPage',
    components: {
        LoadingSkeleton, LoadError, NcButton, NcDateTimePickerNative, NcDialog, NcEmptyContent, NcIconSvgWrapper,
        NcNoteCard, NcRadioGroup, NcRadioGroupButton, NcSelect, NcTextField,
    },
    data() {
        return { icons, gear: [], loading: true, error: false, editing: null, formError: '' }
    },
    computed: {
        groups() {
            return [
                { key: 'active', title: '', items: this.gear.filter(g => !g.retiredDate) },
                { key: 'retired', title: t('fit_tracker', 'Retired'), items: this.gear.filter(g => g.retiredDate) },
            ]
        },
        sportOptions() {
            const sports = [...new Set(navData.activities.map(a => a.sport))].sort()
            return [{ value: '', label: t('fit_tracker', 'None') }, ...sports.map(s => ({ value: s, label: sportLabel(s) }))]
        },
        dialogButtons() {
            const buttons = [{ label: t('fit_tracker', 'Cancel'), variant: 'tertiary', callback: () => {} }]
            if (this.editing?.id) {
                buttons.push({ label: t('fit_tracker', 'Delete'), variant: 'error', callback: () => this.remove(this.editing.id) })
            }
            // Returning false keeps the dialog open to show the error
            buttons.push({ label: t('fit_tracker', 'Save'), variant: 'primary', callback: async () => (await this.save()) || false })
            return buttons
        },
    },
    mounted() {
        this.load()
        fetchActivities().catch(() => {})
    },
    methods: {
        sportLabel,
        distanceUnit,
        formatDay,
        formatDistance,
        formatDurationShort,
        typeIcon(type) {
            return { shoes: 'steps', bike: 'directions_bike' }[type] ?? 'backpack'
        },
        wear(g) {
            return g.retireDistance ? g.totalDistance / g.retireDistance : 0
        },
        wearClass(g) {
            const w = this.wear(g)
            if (g.retiredDate) return ''
            if (w >= 1) return 'wear--over'
            if (w >= 0.9) return 'wear--soon'
            return ''
        },
        wearText(g) {
            const left = g.retireDistance - g.totalDistance
            if (left <= 0) return t('fit_tracker', 'Time to replace: {over} over the limit', { over: formatDistance(-left, 0) })
            return t('fit_tracker', '{left} left of {limit}', { left: formatDistance(left, 0), limit: formatDistance(g.retireDistance, 0) })
        },
        async load() {
            this.loading = this.gear.length === 0
            this.error = false
            try {
                const { data } = await axios.get(generateUrl('/apps/fit_tracker/api/gear'))
                this.gear = data
            } catch (e) {
                this.error = true
            } finally {
                this.loading = false
            }
        },
        openEditor(g) {
            this.formError = ''
            const km = v => v ? Math.round(distanceValue(v) * 10) / 10 : ''
            this.editing = {
                id: g?.id ?? null,
                name: g?.name ?? '',
                type: g?.type ?? 'shoes',
                sportOption: this.sportOptions.find(o => o.value === (g?.defaultSport ?? '')) ?? this.sportOptions[0],
                startDate: g ? fromDateString(g.startDate) : new Date(),
                retiredDate: fromDateString(g?.retiredDate),
                initialDistance: km(g?.initialDistance),
                retireDistance: km(g?.retireDistance),
            }
        },
        async save() {
            const e = this.editing
            if (!e.name.trim()) {
                this.formError = t('fit_tracker', 'Enter a name.')
                return false
            }
            // Distances are entered in km or miles and stored in km
            const toKm = v => v === '' || v === null ? null : Number(v) / distanceValue(1)
            const body = {
                name: e.name.trim(),
                type: e.type,
                defaultSport: e.sportOption?.value ?? '',
                startDate: toDateString(e.startDate),
                retiredDate: toDateString(e.retiredDate),
                initialDistance: toKm(e.initialDistance) ?? 0,
                retireDistance: toKm(e.retireDistance),
            }
            try {
                if (e.id) await axios.put(generateUrl(`/apps/fit_tracker/api/gear/${e.id}`), body)
                else await axios.post(generateUrl('/apps/fit_tracker/api/gear'), body)
                await this.load()
                window.dispatchEvent(new CustomEvent('fit-tracker:refresh'))
                return true
            } catch (err) {
                this.formError = err.response?.data?.error && err.response.status === 400
                    ? t('fit_tracker', 'Check the entered values.')
                    : t('fit_tracker', 'Could not save. Try again later.')
                return false
            }
        },
        async remove(id) {
            try {
                await axios.delete(generateUrl(`/apps/fit_tracker/api/gear/${id}`))
                await this.load()
            } catch (e) {
                showError(t('fit_tracker', 'Could not delete the gear. Try again later.'))
            }
        },
    },
}
</script>

<style scoped>
.gear-page__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--ft-gap-3);
}
.gear-page__header .ft-page-title {
    margin-bottom: var(--ft-gap-5);
}
.gear-group {
    margin-bottom: var(--ft-gap-6);
}
.gear-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(260px, 100%), 1fr));
    gap: var(--ft-gap-4);
}
.gear-card {
    display: flex;
    flex-direction: column;
    gap: var(--ft-gap-1);
    margin: 0;
    padding: var(--ft-gap-4);
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius-container);
    background: var(--color-main-background);
    color: var(--color-main-text);
    text-align: start;
    font-weight: normal;
    cursor: pointer;
    height: auto;
    white-space: normal;
}
.gear-card:hover {
    background: var(--color-background-hover);
    border-color: var(--color-border-maxcontrast);
}
.gear-card:focus-visible {
    outline: 2px solid var(--color-primary-element);
    outline-offset: 2px;
}
.gear-card--retired {
    opacity: 0.7;
}
.gear-card__top {
    display: flex;
    align-items: center;
    gap: var(--ft-gap-2);
}
.gear-card__name {
    font-weight: bold;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.gear-card__distance {
    font-size: 24px;
    font-weight: bold;
}
.gear-card__bar {
    height: 8px;
    border-radius: var(--border-radius-pill);
    background: var(--color-background-dark);
    overflow: hidden;
}
.gear-card__fill {
    height: 100%;
    background: var(--color-primary-element);
}
.gear-card__fill.wear--soon { background: var(--color-element-warning, var(--color-warning-text)); }
.gear-card__fill.wear--over { background: var(--color-element-error, var(--color-error-text)); }
.gear-card__wear {
    display: flex;
    align-items: center;
    gap: var(--ft-gap-1);
    font-size: var(--font-size-small);
}
.gear-card__wear.wear--soon { color: var(--color-warning-text); }
.gear-card__wear.wear--over { color: var(--color-error-text); font-weight: bold; }
.gear-card__meta {
    font-size: var(--font-size-small);
    color: var(--color-text-maxcontrast);
}
.gear-form {
    display: flex;
    flex-direction: column;
    gap: var(--ft-gap-3);
}
.gear-form__row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: var(--ft-gap-3);
}
</style>
