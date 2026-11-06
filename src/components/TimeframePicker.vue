<template>
    <div class="timeframe">
        <NcRadioGroup
            :model-value="timeframe.range"
            class="timeframe__ranges"
            :label="t('fit_tracker', 'Timeframe')"
            hide-label
            @update:model-value="selectRange"
        >
            <NcRadioGroupButton v-for="r in options" :key="r.value" :value="r.value" :label="r.label" />
        </NcRadioGroup>
        <div v-if="timeframe.range === 'custom'" class="timeframe__custom">
            <NcDateTimePickerNative
                :model-value="timeframe.from"
                type="date"
                :label="t('fit_tracker', 'From')"
                :max="lastDay"
                @update:model-value="setCustom($event, lastDay)"
            />
            <NcDateTimePickerNative
                :model-value="lastDay"
                type="date"
                :label="t('fit_tracker', 'To')"
                :min="timeframe.from"
                @update:model-value="setCustom(timeframe.from, $event)"
            />
        </div>
        <span class="timeframe__window">{{ windowText }}</span>
    </div>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { NcDateTimePickerNative, NcRadioGroup, NcRadioGroupButton } from '@nextcloud/vue'
import { dayKey } from '../stats.js'
import { formatWindow } from '../timeframe.js'

// Rolling timeframe selector of the dashboard. Writes the choice into the
// route query (see timeframe.js); the page reads it back from there.
export default {
    name: 'TimeframePicker',
    components: { NcDateTimePickerNative, NcRadioGroup, NcRadioGroupButton },
    props: {
        // resolveTimeframe() result for the current route
        timeframe: { type: Object, required: true },
    },
    computed: {
        options() {
            return [
                // TRANSLATORS: Short timeframe button labels, e.g. "7 days" for the last 7 days
                { value: '7d', label: t('fit_tracker', '7 days') },
                { value: '30d', label: t('fit_tracker', '30 days') },
                { value: '365d', label: t('fit_tracker', '365 days') },
                { value: 'all', label: t('fit_tracker', 'All time') },
                { value: 'custom', label: t('fit_tracker', 'Custom') },
            ]
        },
        lastDay() {
            return new Date(this.timeframe.to.getTime() - 86400000)
        },
        windowText() {
            return formatWindow(this.timeframe)
        },
    },
    methods: {
        push(query) {
            this.$router.push({ path: this.$route.path, query }).catch(() => {})
        },
        selectRange(range) {
            const query = { ...this.$route.query, range }
            delete query.from
            delete query.to
            if (range === 'custom') {
                // Start from what is shown now
                query.from = dayKey(this.timeframe.from)
                query.to = dayKey(this.lastDay)
            }
            if (range === '30d') delete query.range
            this.push(query)
        },
        setCustom(from, to) {
            if (!from || !to) return
            if (to < from) [from, to] = [to, from]
            this.push({ ...this.$route.query, range: 'custom', from: dayKey(from), to: dayKey(to) })
        },
    },
}
</script>

<style scoped>
.timeframe {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: var(--ft-gap-2) var(--ft-gap-4);
}
.timeframe__ranges {
    flex: 0 1 520px;
    min-width: 0;
}
.timeframe__custom {
    display: flex;
    flex-wrap: wrap;
    gap: var(--ft-gap-2);
}
.timeframe__window {
    color: var(--color-text-maxcontrast);
    padding-bottom: var(--ft-gap-2);
}
</style>
