<template>
    <div class="stage-bar" :title="tooltip">
        <div v-for="seg in segments" :key="seg.label"
             class="stage-bar__seg"
             :style="{ width: seg.pct + '%', background: seg.color }"
             :title="seg.label + ': ' + seg.mins + ' min'"
        ></div>
    </div>
</template>

<script>
const STAGE_COLORS = {
    deep:  '#1e40af',
    rem:   '#7c3aed',
    light: '#60a5fa',
    awake: '#fbbf24',
}

export default {
    name: 'SleepStageBar',
    props: {
        timeDeep:  { type: Number, default: 0 },
        timeLight: { type: Number, default: 0 },
        timeRem:   { type: Number, default: 0 },
        timeAwake: { type: Number, default: 0 },
    },
    computed: {
        total() {
            return (this.timeDeep || 0) + (this.timeLight || 0) + (this.timeRem || 0) + (this.timeAwake || 0)
        },
        segments() {
            if (!this.total) return []
            return [
                { label: 'Deep',  color: STAGE_COLORS.deep,  secs: this.timeDeep  || 0 },
                { label: 'REM',   color: STAGE_COLORS.rem,   secs: this.timeRem   || 0 },
                { label: 'Light', color: STAGE_COLORS.light, secs: this.timeLight || 0 },
                { label: 'Awake', color: STAGE_COLORS.awake, secs: this.timeAwake || 0 },
            ]
                .filter(s => s.secs > 0)
                .map(s => ({
                    ...s,
                    pct:  Math.round(s.secs / this.total * 100),
                    mins: Math.round(s.secs / 60),
                }))
        },
        tooltip() {
            return this.segments.map(s => `${s.label}: ${s.mins} min`).join(' | ')
        },
    },
}
</script>

<style scoped>
.stage-bar {
    display: flex;
    height: 8px;
    border-radius: 4px;
    overflow: hidden;
    width: 100%;
    gap: 1px;
}
.stage-bar__seg {
    border-radius: 2px;
    min-width: 2px;
}
</style>
