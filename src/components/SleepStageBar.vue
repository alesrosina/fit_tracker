<template>
    <div class="stage-bar" role="img" :title="tooltip" :aria-label="tooltip">
        <div v-for="seg in segments" :key="seg.label"
             class="stage-bar__seg"
             :style="{ width: seg.pct + '%', background: seg.color }"
             :title="seg.text"
        ></div>
    </div>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { STAGE_COLORS, stageLabel } from '../sleepStages.js'

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
                { label: stageLabel('deep'),  color: STAGE_COLORS.deep,  secs: this.timeDeep  || 0 },
                { label: stageLabel('rem'),   color: STAGE_COLORS.rem,   secs: this.timeRem   || 0 },
                { label: stageLabel('light'), color: STAGE_COLORS.light, secs: this.timeLight || 0 },
                { label: stageLabel('awake'), color: STAGE_COLORS.awake, secs: this.timeAwake || 0 },
            ]
                .filter(s => s.secs > 0)
                .map(s => ({
                    ...s,
                    pct:  Math.round(s.secs / this.total * 100),
                    // TRANSLATORS: Sleep stage share, e.g. "Deep: 85 min"
                    text: t('fit_tracker', '{stage}: {minutes} min', { stage: s.label, minutes: Math.round(s.secs / 60) }),
                }))
        },
        tooltip() {
            return this.segments.map(s => s.text).join(' | ')
        },
    },
}
</script>

<style scoped>
.stage-bar {
    display: flex;
    height: 8px;
    border-radius: var(--border-radius-small);
    overflow: hidden;
    width: 100%;
    gap: 1px;
}
.stage-bar__seg {
    min-width: 2px;
}
</style>
