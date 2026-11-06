<template>
    <component
        :is="to ? 'router-link' : 'section'"
        :to="to || undefined"
        class="stat-tile"
        :class="{ 'stat-tile--link': to }"
        :aria-label="to ? undefined : label"
    >
        <div class="stat-tile__label">{{ label }}</div>
        <div v-if="value" class="stat-tile__value">{{ value }}</div>
        <!-- Shown right under the value, e.g. a sleep stage bar -->
        <slot name="value" />
        <div v-if="sub" class="stat-tile__sub">{{ sub }}</div>
        <div v-if="change" class="stat-tile__change" :class="change.good === true ? 'stat-tile__change--good' : change.good === false ? 'stat-tile__change--bad' : ''">
            <span aria-hidden="true">{{ change.direction === 'up' ? '▲' : change.direction === 'down' ? '▼' : '' }}</span>
            {{ change.text }}
        </div>
        <div v-if="goal" class="stat-tile__goal">
            <div
                class="stat-tile__bar"
                role="progressbar"
                :aria-label="t('fit_tracker', 'Goal')"
                :aria-valuenow="Math.round(goal.fraction * 100)"
                aria-valuemin="0"
                aria-valuemax="100"
            >
                <div class="stat-tile__fill" :class="{ 'stat-tile__fill--done': goal.fraction >= 1 }" :style="{ width: Math.min(100, goal.fraction * 100) + '%' }" />
            </div>
            <div class="stat-tile__goal-text">
                {{ t('fit_tracker', 'Goal {target} · {percent} %', { target: goal.targetText, percent: Math.round(goal.fraction * 100) }) }}
            </div>
        </div>
        <slot />
    </component>
</template>

<script>
// One number on the dashboard, styled like the stat boxes on the activity
// page: label, value, an optional line below, the
// change against the previous timeframe, and progress towards a goal when
// one is set for this measure.
export default {
    name: 'StatTile',
    props: {
        label: { type: String, required: true },
        value: { type: String, default: '' },
        sub: { type: String, default: '' },
        // { text, direction: 'up' | 'down' | 'same', good: true | false | null } (timeframe.js compareChange)
        change: { type: Object, default: null },
        // { fraction, targetText }
        goal: { type: Object, default: null },
        // Makes the whole tile a link
        to: { type: [String, Object], default: null },
    },
}
</script>

<style scoped>
.stat-tile {
    display: flex;
    flex-direction: column;
    gap: var(--ft-gap-1);
    padding: var(--ft-gap-3);
    border-radius: var(--border-radius-element);
    background: var(--color-background-dark);
    text-align: center;
    min-width: 0;
}
.stat-tile--link {
    color: var(--color-main-text);
    text-decoration: none;
    transition: background-color 0.15s;
}
.stat-tile--link:hover {
    background: var(--color-background-darker);
}
.stat-tile--link:focus-visible {
    outline: 2px solid var(--color-primary-element);
    outline-offset: 2px;
}
.stat-tile__label {
    font-size: var(--font-size-small);
    color: var(--color-text-maxcontrast);
}
.stat-tile__value {
    font-size: 20px;
    font-weight: bold;
}
.stat-tile__sub,
.stat-tile__goal-text {
    font-size: var(--font-size-small);
    color: var(--color-text-maxcontrast);
}
.stat-tile__change {
    font-size: var(--font-size-small);
    color: var(--color-text-maxcontrast);
}
.stat-tile__change--good { color: var(--color-success-text); }
.stat-tile__change--bad { color: var(--color-error-text); }
.stat-tile__goal {
    margin-top: auto;
    padding-top: var(--ft-gap-2);
}
.stat-tile__bar {
    height: 8px;
    border-radius: var(--border-radius-pill);
    background: var(--color-main-background);
    overflow: hidden;
    margin-bottom: var(--ft-gap-1);
}
.stat-tile__fill {
    height: 100%;
    border-radius: var(--border-radius-pill);
    background: var(--color-primary-element);
}
.stat-tile__fill--done {
    background: var(--color-element-success, var(--color-success-text));
}
</style>
