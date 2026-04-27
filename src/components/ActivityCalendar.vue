<template>
    <div class="activity-calendar">
        <div class="cal-nav">
            <button class="cal-btn" @click="prevMonth">‹</button>
            <strong>{{ monthTitle }}</strong>
            <button class="cal-btn" @click="nextMonth">›</button>
        </div>
        <div class="cal-grid">
            <div v-for="d in weekDays" :key="d" class="cal-header-cell">{{ d }}</div>
            <div
                v-for="(cell, idx) in cells"
                :key="idx"
                class="cal-cell"
                :class="{
                    'cal-cell--filler': !cell,
                    'cal-cell--today': cell && cell.isToday,
                    'cal-cell--selected': cell && cell.isSelected,
                }"
                @click="cell && selectDay(cell)"
            >
                <template v-if="cell">
                    <span class="cal-cell__day">{{ cell.day }}</span>
                    <div class="cal-cell__dots">
                        <span
                            v-for="(a, i) in cell.activities.slice(0, 4)"
                            :key="i"
                            class="cal-dot"
                            :title="a.name"
                            :style="{ background: sportColor(a.sport) }"
                        ></span>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>

<script>
import { sportColor } from '../sports.js'

export default {
    name: 'ActivityCalendar',
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
        weekDays() {
            return ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
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
    },
}
</script>

<style scoped>
.activity-calendar {
    flex: 1;
    min-width: 280px;
}
.cal-nav {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}
.cal-btn {
    background: none;
    border: 1px solid var(--color-border);
    border-radius: 4px;
    cursor: pointer;
    font-size: 18px;
    line-height: 1;
    padding: 2px 10px;
    color: var(--color-main-text);
}
.cal-btn:hover { background: var(--color-background-dark); }
.cal-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 2px;
}
.cal-header-cell {
    font-size: 11px;
    font-weight: 600;
    text-align: center;
    color: var(--color-text-maxcontrast);
    padding: 4px 0;
    text-transform: uppercase;
}
.cal-cell {
    min-height: 46px;
    border-radius: 4px;
    padding: 3px;
    border: 1px solid transparent;
    display: flex;
    flex-direction: column;
    align-items: center;
}
.cal-cell--filler { border: none; }
.cal-cell { cursor: pointer; }
.cal-cell--filler { cursor: default; }
.cal-cell--selected {
    background: var(--color-primary-element-light, rgba(0, 130, 201, 0.15));
    border-color: var(--color-primary-element);
}
.cal-cell--today {
    border-color: var(--color-primary-element);
    background: var(--color-primary-element-light, rgba(0, 130, 201, 0.1));
}
.cal-cell__day {
    font-size: 12px;
    font-weight: 500;
    line-height: 1.5;
}
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
    border-radius: 50%;
    display: inline-block;
}
</style>
