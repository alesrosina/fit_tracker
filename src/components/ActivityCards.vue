<template>
    <section class="activity-cards">
        <div class="activity-cards__header">
            <h3>{{ t('fit_tracker', 'Activities') }}</h3>
            <span class="activity-cards__count">
                {{ filtersActive
                    ? t('fit_tracker', '{shown} of {total}', { shown: searched.length, total: activities.length })
                    : n('fit_tracker', '%n activity', '%n activities', activities.length) }}
            </span>
        </div>
        <div v-if="activities.length > 0" class="activity-cards__filters" role="search">
            <NcTextField
                v-model="filters.text"
                class="activity-cards__text"
                type="search"
                :label="t('fit_tracker', 'Search name or notes')"
                trailing-button-icon="close"
                :show-trailing-button="filters.text !== ''"
                @trailing-button-click="filters.text = ''"
            >
                <NcIconSvgWrapper :svg="icons.search" :size="20" />
            </NcTextField>
            <NcTextField
                v-model="filters.minDistance"
                class="activity-cards__number"
                type="number"
                :label="t('fit_tracker', 'Min. {unit}', { unit: distanceUnit() })"
            />
            <NcTextField
                v-model="filters.maxDistance"
                class="activity-cards__number"
                type="number"
                :label="t('fit_tracker', 'Max. {unit}', { unit: distanceUnit() })"
            />
            <NcButton v-if="filtersActive" variant="tertiary" @click="clearFilters">
                {{ t('fit_tracker', 'Clear filters') }}
            </NcButton>
        </div>

        <p v-if="activities.length === 0" class="ft-panel__hint">
            {{ t('fit_tracker', 'No activities in this timeframe.') }}
        </p>
        <NcEmptyContent
            v-else-if="searched.length === 0"
            :name="t('fit_tracker', 'No matching activities')"
            :description="t('fit_tracker', 'Try a different search or clear the filters.')"
        >
            <template #icon>
                <NcIconSvgWrapper :svg="icons.search" />
            </template>
        </NcEmptyContent>
        <template v-else>
            <div class="activity-grid">
                <router-link
                    v-for="activity in paginated"
                    :key="activity.id"
                    :to="'/activity/' + activity.id"
                    class="activity-card"
                >
                    <SportBadge :icon="sportIcon(activity.sport)" :color="sportColor(activity.sport)" :size="24" />
                    <div class="activity-card__body">
                        <div class="activity-card__name">{{ activity.name }}</div>
                        <div class="activity-card__date">{{ formatWhen(activity) }}</div>
                        <div class="activity-card__stats">
                            <span v-if="activity.distance">{{ formatDistance(activity.distance) }}</span>
                            <span v-if="activity.duration">{{ formatDurationShort(activity.duration, true) }}</span>
                            <span v-if="activity.avgHr">{{ t('fit_tracker', '{bpm} bpm', { bpm: activity.avgHr }) }}</span>
                            <span v-if="activity.calories">{{ t('fit_tracker', '{kcal} kcal', { kcal: activity.calories }) }}</span>
                        </div>
                    </div>
                </router-link>
            </div>
            <PageNav
                v-if="totalPages > 1"
                class="activity-cards__pages"
                :previous-label="t('fit_tracker', 'Previous page')"
                :next-label="t('fit_tracker', 'Next page')"
                :has-previous="currentPage > 1"
                :has-next="currentPage < totalPages"
                @previous="currentPage--"
                @next="currentPage++"
            >
                {{ t('fit_tracker', 'Page {page} of {total}', { page: currentPage, total: totalPages }) }}
            </PageNav>
        </template>
    </section>
</template>

<script>
import { NcButton, NcEmptyContent, NcIconSvgWrapper, NcTextField } from '@nextcloud/vue'
import SportBadge from './SportBadge.vue'
import PageNav from './PageNav.vue'
import { icons } from '../icons.js'
import { sportIcon, sportColor } from '../sports.js'
import { formatDistance, formatDurationShort, formatDay, formatTime, parseDate, distanceUnit, distanceValue } from '../format.js'

const PAGE_SIZE = 12

// The dashboard's list of activities in the selected timeframe (newest
// first), with a text search and a distance filter.
export default {
    name: 'ActivityCards',
    components: { NcButton, NcEmptyContent, NcIconSvgWrapper, NcTextField, SportBadge, PageNav },
    props: {
        activities: { type: Array, required: true },
    },
    data() {
        return { icons, currentPage: 1, filters: { text: '', minDistance: '', maxDistance: '' } }
    },
    computed: {
        filtersActive() {
            const f = this.filters
            return f.text !== '' || f.minDistance !== '' || f.maxDistance !== ''
        },
        searched() {
            const f = this.filters
            const text = f.text.trim().toLocaleLowerCase()
            const min = f.minDistance === '' ? null : Number(f.minDistance)
            const max = f.maxDistance === '' ? null : Number(f.maxDistance)
            return this.activities.filter(a => {
                if (text && !`${a.name} ${a.notes ?? ''}`.toLocaleLowerCase().includes(text)) return false
                const dist = distanceValue(a.distance || 0)
                if (min !== null && dist < min) return false
                if (max !== null && dist > max) return false
                return true
            })
        },
        totalPages() {
            return Math.ceil(this.searched.length / PAGE_SIZE) || 1
        },
        paginated() {
            const start = (this.currentPage - 1) * PAGE_SIZE
            return this.searched.slice(start, start + PAGE_SIZE)
        },
    },
    watch: {
        searched() {
            this.currentPage = 1
        },
    },
    methods: {
        sportIcon,
        sportColor,
        distanceUnit,
        formatDistance,
        formatDurationShort,
        clearFilters() {
            this.filters = { text: '', minDistance: '', maxDistance: '' }
        },
        // "Oct 12, 2026 · 07:15 – 08:02"
        formatWhen(a) {
            const day = formatDay(a.startTime)
            const from = formatTime(a.startTime)
            if (!a.duration) return `${day} · ${from}`
            const end = new Date(parseDate(a.startTime).getTime() + a.duration * 1000)
            return `${day} · ${from} – ${formatTime(end)}`
        },
    },
}
</script>

<style scoped>
.activity-cards__header {
    display: flex;
    align-items: baseline;
    gap: var(--ft-gap-3);
    margin-bottom: var(--ft-gap-3);
}
.activity-cards__header h3 {
    margin: 0;
}
.activity-cards__count {
    color: var(--color-text-maxcontrast);
}
.activity-cards__filters {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: var(--ft-gap-2) var(--ft-gap-3);
    margin-bottom: var(--ft-gap-4);
}
.activity-cards__text {
    flex: 1 1 220px;
}
.activity-cards__number {
    flex: 0 1 110px;
}
.activity-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(280px, 100%), 1fr));
    gap: var(--ft-gap-4);
}
.activity-card {
    display: flex;
    gap: var(--ft-gap-3);
    padding: var(--ft-gap-4);
    border-radius: var(--border-radius-container);
    border: 1px solid var(--color-border);
    background: var(--color-main-background);
    color: var(--color-main-text);
    text-decoration: none;
    transition: background-color 0.15s, border-color 0.15s;
}
.activity-card:hover {
    background: var(--color-background-hover);
    border-color: var(--color-border-maxcontrast);
}
.activity-card:focus-visible {
    outline: 2px solid var(--color-primary-element);
    outline-offset: 2px;
}
.activity-card__body { min-width: 0; }
.activity-card__name {
    font-weight: bold;
    margin-bottom: var(--ft-gap-1);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.activity-card__date {
    font-size: var(--font-size-small);
    color: var(--color-text-maxcontrast);
    margin-bottom: var(--ft-gap-1);
}
.activity-card__stats {
    display: flex;
    flex-wrap: wrap;
    gap: var(--ft-gap-1) var(--ft-gap-3);
    font-size: var(--font-size-small);
}
.activity-cards__pages {
    justify-content: center;
    margin-top: var(--ft-gap-6);
}
</style>
