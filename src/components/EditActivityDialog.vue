<template>
    <NcDialog
        :open="true"
        :name="t('fit_tracker', 'Edit activity')"
        :buttons="buttons"
        size="normal"
        @update:open="$emit('close')"
    >
        <div class="edit-form">
            <NcTextField
                v-model="name"
                :label="t('fit_tracker', 'Name')"
                :placeholder="activity.autoName"
                :helper-text="t('fit_tracker', 'Leave empty to use {name}', { name: activity.autoName })"
                :maxlength="255"
            />
            <NcTextArea
                v-model="notes"
                :label="t('fit_tracker', 'Notes')"
                :placeholder="t('fit_tracker', 'How did it go?')"
                resize="vertical"
                rows="4"
            />
            <NcSelect
                v-model="rpe"
                :options="rpeOptions"
                :input-label="t('fit_tracker', 'Perceived effort')"
                :placeholder="t('fit_tracker', 'Not set')"
                label="label"
            />
            <NcSelect
                v-model="gear"
                :options="gearOptions"
                :input-label="t('fit_tracker', 'Gear')"
                :clearable="false"
                :loading="gearLoading"
                label="label"
            />
            <NcNoteCard v-if="error" type="error" :text="error" />
        </div>
    </NcDialog>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { t } from '@nextcloud/l10n'
import { NcDialog, NcNoteCard, NcSelect, NcTextArea, NcTextField } from '@nextcloud/vue'
import { rpeLabel } from '../sports.js'

// "Edit activity" dialog: name, notes, perceived effort (RPE 1-10) and gear.
// Emits `saved` with the updated activity from the server.
export default {
    name: 'EditActivityDialog',
    components: { NcDialog, NcNoteCard, NcSelect, NcTextArea, NcTextField },
    props: {
        activity: { type: Object, required: true },
    },
    emits: ['close', 'saved'],
    data() {
        const a = this.activity
        return {
            name: a.name === a.autoName ? '' : a.name,
            notes: a.notes ?? '',
            rpe: null,
            gear: null,
            gears: [],
            gearLoading: true,
            saving: false,
            error: '',
        }
    },
    computed: {
        rpeOptions() {
            return Array.from({ length: 10 }, (_, i) => ({ value: i + 1, label: `${i + 1} · ${rpeLabel(i + 1)}` }))
        },
        // null = default gear for the sport, 0 = no gear
        gearOptions() {
            const usable = this.gears.filter(g => !g.retiredDate || g.id === this.activity.gearId)
            const defaultGear = this.gears.find(g => g.id === this.defaultGearId)
            return [
                {
                    value: null,
                    label: defaultGear
                        ? t('fit_tracker', 'Default for this sport ({gear})', { gear: defaultGear.name })
                        : t('fit_tracker', 'Default for this sport (none)'),
                },
                { value: 0, label: t('fit_tracker', 'No gear') },
                ...usable.map(g => ({ value: g.id, label: g.name })),
            ]
        },
        // The gear the sport default rule would pick (see GearService.php)
        defaultGearId() {
            if (!this.activity.gearExplicit) return this.activity.gearId
            const day = this.activity.startTime.slice(0, 10)
            const candidates = this.gears.filter(g => g.defaultSport === this.activity.sport
                && (!g.startDate || g.startDate <= day) && (!g.retiredDate || g.retiredDate >= day))
            candidates.sort((x, y) => (y.startDate ?? '').localeCompare(x.startDate ?? '') || y.id - x.id)
            return candidates[0]?.id ?? null
        },
        buttons() {
            return [
                { label: t('fit_tracker', 'Cancel'), variant: 'tertiary', callback: () => {} },
                // Returning false keeps the dialog open to show the error
                { label: t('fit_tracker', 'Save'), variant: 'primary', callback: async () => (await this.save()) || false },
            ]
        },
    },
    async mounted() {
        this.rpe = this.rpeOptions.find(o => o.value === this.activity.rpe) ?? null
        try {
            const { data } = await axios.get(generateUrl('/apps/fit_tracker/api/gear'))
            this.gears = data
        } catch (e) {
            this.gears = []
        } finally {
            this.gearLoading = false
        }
        const current = this.activity.gearExplicit ? (this.activity.gearId ?? 0) : null
        this.gear = this.gearOptions.find(o => o.value === current) ?? this.gearOptions[0]
    },
    methods: {
        async save() {
            this.saving = true
            this.error = ''
            try {
                const { data } = await axios.put(generateUrl(`/apps/fit_tracker/api/activities/${this.activity.id}/meta`), {
                    name: this.name.trim(),
                    notes: this.notes,
                    rpe: this.rpe?.value ?? null,
                    gearId: this.gear?.value ?? null,
                })
                this.$emit('saved', data)
                return true
            } catch (e) {
                this.error = e.response?.status === 409
                    ? t('fit_tracker', 'The .fit file of this activity is missing from the folder, so changes cannot be saved.')
                    : t('fit_tracker', 'Could not save the changes. Try again later.')
                return false
            } finally {
                this.saving = false
            }
        },
    },
}
</script>

<style scoped>
.edit-form {
    display: flex;
    flex-direction: column;
    gap: var(--ft-gap-3);
}
</style>
