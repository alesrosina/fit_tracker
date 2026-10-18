<template>
    <div class="activity-calendar">
        <PageNav
            class="cal-nav"
            :previous-label="t('fit_tracker', 'Previous month')"
            :next-label="t('fit_tracker', 'Next month')"
            @previous="prevMonth"
            @next="nextMonth"
        >
            {{ monthTitle }}
        </PageNav>
        <div class="cal-grid">
            <div v-for="d in weekDays" :key="d" class="cal-header-cell" aria-hidden="true">{{ d }}</div>
            <template v-for="(cell, idx) in cells" :key="idx">
                <div v-if="!cell" class="cal-cell cal-cell--filler" />
                <button
                    v-else
                    type="button"
                    class="cal-cell"
                    :class="{
                        'cal-cell--today': cell.isToday,
                        'cal-cell--selected': cell.isSelected,
                    }"
                    :aria-label="cellLabel(cell)"
                    :aria-pressed="cell.isSelected"
                    @click="selectDay(cell)"
                >
                    <span class="cal-cell__day">{{ cell.day }}</span>
                    <span class="cal-cell__dots">
                        <span
                            v-for="(a, i) in cell.activities.slice(0, 4)"
                            :key="i"
                            class="cal-dot"
                            :title="a.name"
                            :style="{ background: sportColor(a.sport) }"
                        ></span>
                    </span>
                </button>
            </template>
        </div>
    </div>
</template>

<script>
import { n } from '@nextcloud/l10n'
import PageNav from './PageNav.vue'
import { sportColor } from '../sports.js'

export default {
    name: 'ActivityCalendar',
    components: { PageNav },
    props: {
        activities: { type: Array, required: true },
    },
    emits: ['select-date'],
    data() {
        const now = new Date()
        return { year: now.getFullYear(), month: now.getMonth(), selectedDay: null }
    },
    computed: {
        monthTitle() {
            return new Date(this.year, this.month, 1).toLocaleDateString(undefined, { month: 'long', year: 'numeric' })
        },
        // Localized short weekday names, Monday first (2024-01-01 was a Monday)
        weekDays() {
            return Array.from({ length: 7 }, (_, i) =>
                new Date(2024, 0, 1 + i).toLocaleDateString(undefined, { weekday: 'short' }))
        },
        activityByDay() {
            const map = {}
            for (const a of this.activities) {
                if (!a.startTime) continue
                const d = new Date(a.startTime)
                const key = `${d.getFullYear()}-${d.getMonth()}-${d.getDate()}`
                if (!map[key]) map[key] = []
                map[key].push(a)
            }
            return map
        },
        cells() {
            const { year, month } = this
            const today = new Date()
            const firstDay = new Date(year, month, 1)
            const lastDay = new Date(year, month + 1, 0)
            const startDow = (firstDay.getDay() + 6) % 7  // Monday-based
            const cells = []
            for (let i = 0; i < startDow; i++) cells.push(null)
            for (let d = 1; d <= lastDay.getDate(); d++) {
                const key = `${year}-${month}-${d}`
                cells.push({
                    day: d,
                    date: new Date(year, month, d),
                    isToday: year === today.getFullYear() && month === today.getMonth() && d === today.getDate(),
                    isSelected: this.selectedDay !== null && year === this.selectedDay.getFullYear() && month === this.selectedDay.getMonth() && d === this.selectedDay.getDate(),
                    activities: this.activityByDay[key] ?? [],
                })
            }
            return cells
        },
    },
    methods: {
        prevMonth() {
            if (this.month === 0) { this.year--; this.month = 11 }
            else this.month--
        },
        nextMonth() {
            if (this.month === 11) { this.year++; this.month = 0 }
            else this.month++
        },
        sportColor,
        selectDay(cell) {
            this.selectedDay = cell.date
            this.$emit('select-date', cell.date)
        },
        cellLabel(cell) {
            const date = cell.date.toLocaleDateString(undefined, { weekday: 'long', day: 'numeric', month: 'long' })
            const count = cell.activities.length
            if (!count) return date
            // TRANSLATORS: Calendar day for screen readers, e.g. "Monday, 3 March: 2 activities"
            return n('fit_tracker', '{date}: %n activity', '{date}: %n activities', count, { date })
        },
    },
}
</script>

<style scoped>
.activity-calendar {
    flex: 1;
    min-width: 260px;
}
.cal-nav {
    margin-bottom: var(--ft-gap-2);
}
.cal-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: var(--ft-gap-1);
}
.cal-header-cell {
    font-size: var(--font-size-small);
    font-weight: bold;
    text-align: center;
    color: var(--color-text-maxcontrast);
    padding: var(--ft-gap-1) 0;
}
.cal-cell {
    min-height: calc(var(--default-grid-baseline) * 11);
    margin: 0;
    padding: var(--ft-gap-1);
    border: 1px solid transparent;
    border-radius: var(--border-radius-small);
    background: transparent;
    color: var(--color-main-text);
    display: flex;
    flex-direction: column;
    align-items: center;
    cursor: pointer;
    font: inherit;
}
.cal-cell:hover { background: var(--color-background-hover); }
.cal-cell:focus-visible {
    outline: 2px solid var(--color-primary-element);
    outline-offset: 1px;
}
.cal-cell--filler { cursor: default; }
.cal-cell--filler:hover { background: transparent; }
.cal-cell--today,
.cal-cell--selected {
    border-color: var(--color-primary-element);
    background: var(--color-primary-element-light);
    color: var(--color-primary-element-light-text);
}
.cal-cell__day {
    font-size: var(--font-size-small);
    line-height: 1.5;
}
.cal-cell--today .cal-cell__day { font-weight: bold; }
.cal-cell__dots {
    display: flex;
    flex-wrap: wrap;
    gap: 2px;
    justify-content: center;
    margin-top: 2px;
}
.cal-dot {
    width: 6px;
    height: 6px;
    border-radius: var(--border-radius-pill);
    display: inline-block;
}
</style>
